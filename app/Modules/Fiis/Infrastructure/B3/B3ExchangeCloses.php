<?php

namespace App\Modules\Fiis\Infrastructure\B3;

use App\Modules\Fiis\Application\ExchangeClose;
use App\Modules\Fiis\Application\ExchangeCloses;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Série histórica COTAHIST da B3: texto posicional em Latin-1, uma linha por papel e
 * mercado. Só os campos ASCII (data, ticker, preço) são lidos, então não há conversão.
 */
final class B3ExchangeCloses implements ExchangeCloses
{
    private const TIMEOUT_SECONDS = 180;

    private const QUOTE_RECORD = '01';

    /** CODBDI 12 é fundo imobiliário. */
    private const FII_BDI_CODE = '12';

    /** Mercado à vista: os outros (opções, termo, fracionário) repetem o ticker com outro preço. */
    private const CASH_MARKET = '010';

    /** Tamanho mínimo para conter PREULT, que termina na coluna 121. */
    private const MIN_LINE_LENGTH = 121;

    public function forDay(string $day): iterable
    {
        [$year, $month, $date] = explode('-', $day);

        return $this->closes("COTAHIST_D{$date}{$month}{$year}.ZIP", $day, missingIsEmpty: true);
    }

    public function forYear(int $year): iterable
    {
        return $this->closes("COTAHIST_A{$year}.ZIP", "{$year}", missingIsEmpty: false);
    }

    /** @return Generator<int, ExchangeClose> */
    private function closes(string $file, string $label, bool $missingIsEmpty): Generator
    {
        $path = $this->download($file, $label, $missingIsEmpty);

        if ($path === null) {
            return;
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($path, ZipArchive::RDONLY) !== true || $zip->numFiles < 1) {
                throw new SourceUnavailable("O arquivo da B3 de {$label} está corrompido.");
            }

            $stream = $zip->getStream((string) $zip->getNameIndex(0));

            if ($stream === false) {
                throw new SourceUnavailable("O arquivo da B3 de {$label} está corrompido.");
            }

            try {
                while (($line = fgets($stream)) !== false) {
                    $close = $this->close($line);

                    if ($close !== null) {
                        yield $close;
                    }
                }
            } finally {
                fclose($stream);
                $zip->close();
            }
        } finally {
            @unlink($path);
        }
    }

    private function download(string $file, string $label, bool $missingIsEmpty): ?string
    {
        $url = rtrim((string) config('services.b3.history_url'), '/')."/{$file}";
        $path = (string) tempnam(sys_get_temp_dir(), 'b3');

        try {
            // Direto para o disco: o arquivo anual passa de 90 MB.
            $response = Http::timeout(self::TIMEOUT_SECONDS)->sink($path)->get($url);
        } catch (ConnectionException) {
            @unlink($path);

            throw new SourceUnavailable('Não foi possível conectar à B3.');
        }

        if ($response->successful()) {
            return $path;
        }

        @unlink($path);

        if ($response->status() === 404 && $missingIsEmpty) {
            return null;
        }

        throw new SourceUnavailable("A B3 não entregou o arquivo de {$label} ({$response->status()}).");
    }

    private function close(string $line): ?ExchangeClose
    {
        // Posições da documentação do COTAHIST, 1-based; substr é 0-based.
        if (strlen($line) < self::MIN_LINE_LENGTH
            || substr($line, 0, 2) !== self::QUOTE_RECORD
            || substr($line, 10, 2) !== self::FII_BDI_CODE
            || substr($line, 24, 3) !== self::CASH_MARKET) {
            return null;
        }

        $date = substr($line, 2, 8);
        $ticker = rtrim(substr($line, 12, 12));
        $cents = substr($line, 108, 13);

        if (! ctype_digit($date) || ! checkdate((int) substr($date, 4, 2), (int) substr($date, 6, 2), (int) substr($date, 0, 4))
            || preg_match('/^[A-Z0-9]{5,12}$/D', $ticker) !== 1 || ! ctype_digit($cents)) {
            return null;
        }

        $close = Money::fromString(substr_replace(str_pad(ltrim($cents, '0'), 3, '0', STR_PAD_LEFT), '.', -2, 0));

        // Sem negócio no dia o preço vem zerado: é ausência de dado, não fechamento de R$ 0.
        if (! $close->isPositive()) {
            return null;
        }

        return new ExchangeClose($ticker, new DailyClose(
            substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2),
            $close,
        ));
    }
}
