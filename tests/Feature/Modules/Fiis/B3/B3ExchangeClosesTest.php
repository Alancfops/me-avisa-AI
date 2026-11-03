<?php

use App\Modules\Fiis\Application\ExchangeClose;
use App\Modules\Fiis\Application\ExchangeCloses;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\ExchangeImportModel;
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

    it('records a success only when no day failed', function () {
        Http::fake(['*COTAHIST_D30102026.ZIP' => Http::response(b3Zip()), '*' => Http::response('', 404)]);

        $this->artisan('fiis:import-exchange-closes')->assertSuccessful();

        expect(SourceEventModel::where('source', 'b3')->where('kind', 'success')->count())->toBe(1);
    });
});
