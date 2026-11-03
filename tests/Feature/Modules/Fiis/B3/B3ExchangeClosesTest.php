<?php

use App\Modules\Fiis\Application\ExchangeClose;
use App\Modules\Fiis\Application\ExchangeCloses;
use App\Modules\Fiis\Application\ExchangeFileMissing;
use App\Modules\Fiis\Application\Source;
use App\Modules\Fiis\Application\SourceLog;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\ExchangeImportModel;
use App\Modules\Fiis\Infrastructure\Persistence\ExchangeYearImportModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\SourceEventModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function b3Zip(string $name = 'COTAHIST_D30102026.TXT', ?string $content = null): string
{
    $path = tempnam(sys_get_temp_dir(), 'b3');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString($name, $content ?? (string) file_get_contents(__DIR__.'/../../../../Fixtures/b3/COTAHIST_D30102026.TXT'));
    $zip->close();
    $content = (string) file_get_contents($path);
    unlink($path);

    return $content;
}

/** @param iterable<ExchangeClose> $closes */
function closesByTicker(iterable $closes): array
{
    $byTicker = [];

    foreach ($closes as $close) {
        $byTicker[$close->ticker] = [$close->close->date, $close->close->close->toDecimal()];
    }

    return $byTicker;
}

it('reads FII closes from the daily file', function () {
    Http::fake(['*COTAHIST_D30102026.ZIP' => Http::response(b3Zip())]);

    $closes = closesByTicker(app(ExchangeCloses::class)->forDay('2026-10-30'));

    // Sem ação (CODBDI 02), sem mercado 020 (repete o KNRI11 com outro preço de exercício) e sem PREULT zero.
    expect($closes)->toBe([
        'KNRI11' => ['2026-10-30', '150.5'],
        'MXRF11' => ['2026-10-30', '8.88'],
        'GARE11' => ['2026-10-30', '8.2'],
        'BTRU11' => ['2026-10-30', '85'],
    ]);
});

it('reads lines ending in CRLF, LF or nothing at all', function (string $ending) {
    $lines = array_filter(explode("\n", str_replace("\r", '', (string) file_get_contents(__DIR__.'/../../../../Fixtures/b3/COTAHIST_D30102026.TXT'))));
    $knri = array_values(array_filter($lines, fn ($line) => str_starts_with($line, '012026103012KNRI11') && substr($line, 24, 3) === '010'));
    Http::fake(['*' => Http::response(b3Zip(content: $knri[0].$ending))]);

    expect(closesByTicker(app(ExchangeCloses::class)->forDay('2026-10-30')))->toBe(['KNRI11' => ['2026-10-30', '150.5']]);
})->with(['CRLF' => ["\r\n"], 'LF' => ["\n"], 'sem quebra' => ['']]);

it('returns nothing when the file does not exist yet', function () {
    Http::fake(['*' => Http::response('', 404)]);

    expect(iterator_to_array(app(ExchangeCloses::class)->forDay('2026-11-03')))->toBe([]);
});

it('fails on a broken zip', function (Closure $response) {
    Http::fake(['*' => $response]);

    expect(fn () => iterator_to_array(app(ExchangeCloses::class)->forDay('2026-10-30')))->toThrow(SourceUnavailable::class);
})->with([
    'não é zip' => [fn () => Http::response('<html>manutenção</html>')],
    'status 500' => [fn () => Http::response('', 500)],
    'zip vazio' => [fn () => Http::response(base64_decode('UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA=='))],
]);

it('reads the annual file as a stream', function () {
    Http::fake(['*COTAHIST_A2026.ZIP' => Http::response(b3Zip('COTAHIST_A2026.TXT'))]);

    $closes = app(ExchangeCloses::class)->forYear(2026);

    expect($closes)->toBeInstanceOf(Generator::class)
        ->and(array_keys(closesByTicker($closes)))->toBe(['KNRI11', 'MXRF11', 'GARE11', 'BTRU11']);
});

describe('importing', function () {
    beforeEach(function () {
        Carbon::setTestNow('2026-10-30 21:00:00-03:00');
        foreach (['KNRI11', 'MXRF11'] as $ticker) {
            FundModel::create(['ticker' => $ticker, 'name' => $ticker]);
        }
    });
    afterEach(fn () => Carbon::setTestNow());

    it('saves catalog funds, marks the day and does not request it again', function () {
        Http::fake(['*COTAHIST_D30102026.ZIP' => Http::response(b3Zip()), '*' => Http::response('', 404)]);

        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        expect(DailyPriceModel::where('day', '2026-10-30')->orderBy('ticker')->pluck('close', 'ticker')->map(fn ($c) => (float) $c)->all())
            ->toBe(['KNRI11' => 150.5, 'MXRF11' => 8.88])
            ->and(ExchangeImportModel::whereKey('2026-10-30')->exists())->toBeTrue()
            ->and(ExchangeImportModel::whereKey('2026-10-29')->exists())->toBeFalse();

        Http::fake(['*' => Http::response('', 404)]);
        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'D30102026'));
    });

    it('overwrites the brapi close of the same day', function () {
        DailyPriceModel::create(['ticker' => 'MXRF11', 'day' => '2026-10-30', 'close' => '8.80']);
        Http::fake(['*COTAHIST_D30102026.ZIP' => Http::response(b3Zip()), '*' => Http::response('', 404)]);

        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        expect((float) DailyPriceModel::where('ticker', 'MXRF11')->where('day', '2026-10-30')->value('close'))->toBe(8.88);
    });

    it('shows a failed day on the health page and does not treat a missing file as one', function () {
        Http::fake(['*COTAHIST_D30102026.ZIP' => Http::response('', 500), '*' => Http::response('', 404)]);

        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        $events = SourceEventModel::where('source', 'b3')->get();
        expect($events->where('kind', 'failure'))->toHaveCount(1)
            ->and($events->first(fn ($e) => $e->kind === 'failure')->message)->toBe('A B3 não entregou o arquivo de 2026-10-30 (500).');
    });

    it('keeps a failed day visible when a later day succeeds or is missing', function () {
        Carbon::setTestNow('2026-10-30 21:00:00-03:00');
        Http::fake([
            '*COTAHIST_D29102026.ZIP' => Http::response('', 500),
            '*COTAHIST_D30102026.ZIP' => Http::response(b3Zip()),
            '*' => Http::response('', 404),
        ]);

        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        expect(SourceEventModel::where('source', 'b3')->where('kind', 'success')->exists())->toBeFalse()
            ->and(SourceEventModel::where('source', 'b3')->where('kind', 'failure')->count())->toBe(1);
    });

    it('does not download anything with an empty catalog', function () {
        FundModel::query()->delete();
        Http::fake();

        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        Http::assertNothingSent();
        expect(ExchangeImportModel::count())->toBe(0)->and(SourceEventModel::count())->toBe(0);
    });

    it('logs no success when every day file is missing', function () {
        Http::fake(['*' => Http::response('', 404)]);

        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        expect(SourceEventModel::count())->toBe(0);
    });

    it('records a success only when no day failed', function () {
        Http::fake(['*COTAHIST_D30102026.ZIP' => Http::response(b3Zip()), '*' => Http::response('', 404)]);

        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        expect(SourceEventModel::where('source', 'b3')->where('kind', 'success')->count())->toBe(1);
    });
});

it('reads an annual file with several dates', function () {
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', (string) file_get_contents(__DIR__.'/../../../../Fixtures/b3/COTAHIST_D30102026.TXT'))),
        fn ($line) => str_starts_with($line, '012026103012KNRI11') && substr($line, 24, 3) === '010'));
    $other = '01'.'20261029'.substr($lines[0], 10);
    Http::fake(['*COTAHIST_A2026.ZIP' => Http::response(b3Zip('COTAHIST_A2026.TXT', $other."\r\n".$lines[0]."\r\n"))]);

    $days = [];
    foreach (app(ExchangeCloses::class)->forYear(2026) as $close) {
        $days[] = $close->close->date;
    }

    expect($days)->toBe(['2026-10-29', '2026-10-30']);
});

it('streams a yearly file of 20,000 lines without growing memory', function () {
    $fixture = (string) file_get_contents(__DIR__.'/../../../../Fixtures/b3/COTAHIST_D30102026.TXT');
    preg_match('/^012026103012KNRI11.{6}010.*$/mU', $fixture, $found);
    $line = rtrim($found[0]);
    unset($fixture, $found);

    $text = tempnam(sys_get_temp_dir(), 'b3txt');
    $handle = fopen($text, 'w');
    for ($i = 0; $i < 20_000; $i++) {
        fwrite($handle, $line."\r\n");
    }
    fclose($handle);
    $path = tempnam(sys_get_temp_dir(), 'b3zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFile($text, 'COTAHIST_A2026.TXT');
    $zip->close();
    Http::fake(['*' => fn () => Http::response((string) file_get_contents($path))]);
    gc_collect_cycles();

    $before = memory_get_usage();
    memory_reset_peak_usage();
    $count = 0;
    foreach (app(ExchangeCloses::class)->forYear(2026) as $close) {
        $count++;
    }
    $growth = memory_get_peak_usage() - $before;
    unlink($text);
    unlink($path);

    expect($count)->toBe(20_000)
        ->and($growth)->toBeLessThan(20 * 1024 * 1024);
});

it('fails on an annual file that does not exist', function () {
    Http::fake(['*' => Http::response('', 404)]);

    expect(fn () => iterator_to_array(app(ExchangeCloses::class)->forYear(2025)))->toThrow(ExchangeFileMissing::class)
        ->and(fn () => iterator_to_array(app(ExchangeCloses::class)->forYear(2025)))->toThrow(SourceUnavailable::class);
});

describe('history command', function () {
    beforeEach(function () {
        Carbon::setTestNow('2026-11-03 21:00:00-03:00');
        FundModel::create(['ticker' => 'KNRI11', 'name' => 'KNRI11']);
    });
    afterEach(fn () => Carbon::setTestNow());

    it('loads two years once and skips the second run', function () {
        // Uma resposta nova por pedido: o corpo de uma resposta só pode ser gravado uma vez no sink.
        Http::fake(['*COTAHIST_A*' => fn () => Http::response(b3Zip('COTAHIST_A.TXT'))]);

        $this->artisan('fiis:import-exchange-history')->assertSuccessful();

        expect(DailyPriceModel::where('ticker', 'KNRI11')->count())->toBe(1)
            ->and(SourceEventModel::where('source', 'b3_history')->where('kind', 'success')->count())->toBe(1);
        Http::assertSentCount(2);

        // Anos marcados: a segunda rodada sai sem pedir nada.
        Http::fake(['*' => fn () => Http::response(b3Zip('COTAHIST_A.TXT'))]);
        $this->artisan('fiis:import-exchange-history')->assertSuccessful();
        Http::assertNothingSent();

        $this->artisan('fiis:import-exchange-history --force')->assertSuccessful();
        Http::assertSentCount(2);
    });

    it('records the failure and still exits successfully', function () {
        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('fiis:import-exchange-history')->assertSuccessful();

        expect(SourceEventModel::where('source', 'b3_history')->where('kind', 'failure')->count())->toBe(2)
            ->and(SourceEventModel::where('source', 'b3_history')->where('kind', 'success')->exists())->toBeFalse()
            ->and(SourceEventModel::where('source', 'b3')->exists())->toBeFalse();
    });

    it('retries only the year that failed, even though the other one is loaded', function () {
        $b3Down = true;
        Http::fake([
            '*COTAHIST_A2025.ZIP' => function () use (&$b3Down) {
                return $b3Down ? Http::response('', 500) : Http::response(b3Zip('COTAHIST_A2025.TXT'));
            },
            '*COTAHIST_A2026.ZIP' => fn () => Http::response(b3Zip('COTAHIST_A2026.TXT')),
        ]);
        $this->artisan('fiis:import-exchange-history')->assertSuccessful();

        $b3Down = false;
        $this->artisan('fiis:import-exchange-history')->assertSuccessful();

        // Pedidos: 2025 (falhou), 2026, e só 2025 de novo.
        Http::assertSentCount(3);
        expect(ExchangeYearImportModel::pluck('year')->sort()->values()->all())->toBe([2025, 2026])
            ->and(SourceEventModel::where('source', 'b3_history')->where('kind', 'success')->count())->toBe(1);
    });

    it('keeps the history error visible when the daily run later finds only missing files', function () {
        Http::fake(['*COTAHIST_A*' => Http::response('', 500)]);
        $this->artisan('fiis:import-exchange-history')->assertSuccessful();

        Carbon::setTestNow('2026-11-04 08:00:00-03:00');
        Http::fake(['*' => Http::response('', 404)]);
        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        $history = app(SourceLog::class)->status(Source::B3History);
        expect($history->errors)->toHaveCount(2)
            ->and($history->lastSuccessAt)->toBeNull()
            ->and(app(SourceLog::class)->status(Source::B3)->lastSuccessAt)->toBeNull();
    });

    it('does not show an error nor mark the year when the current-year file is not out yet', function () {
        Http::fake([
            '*COTAHIST_A2025.ZIP' => fn () => Http::response(b3Zip('COTAHIST_A2025.TXT')),
            '*COTAHIST_A2026.ZIP' => Http::response('', 404),
        ]);

        $this->artisan('fiis:import-exchange-history')->assertSuccessful();

        expect(SourceEventModel::where('kind', 'failure')->exists())->toBeFalse()
            ->and(ExchangeYearImportModel::pluck('year')->all())->toBe([2025]);
    });

    it('keeps a missing previous-year file as an error and does not mark it', function () {
        Http::fake([
            '*COTAHIST_A2025.ZIP' => Http::response('', 404),
            '*COTAHIST_A2026.ZIP' => fn () => Http::response(b3Zip('COTAHIST_A2026.TXT')),
        ]);

        $this->artisan('fiis:import-exchange-history')->assertSuccessful();

        expect(SourceEventModel::where('source', 'b3_history')->where('kind', 'failure')->count())->toBe(1)
            ->and(ExchangeYearImportModel::pluck('year')->all())->toBe([2026]);
    });
});
