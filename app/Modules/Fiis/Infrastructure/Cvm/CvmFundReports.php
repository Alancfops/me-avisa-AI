<?php

namespace App\Modules\Fiis\Infrastructure\Cvm;

use App\Modules\Fiis\Application\FundReportRecord;
use App\Modules\Fiis\Application\FundReports;
use App\Modules\Fiis\Application\SourceUnavailable;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use ZipArchive;

final class CvmFundReports implements FundReports
{
    private const TIMEOUT_SECONDS = 120;

    private const GENERAL_COLUMNS = ['CNPJ_Fundo_Classe', 'Data_Referencia', 'Versao', 'Data_Entrega', 'Codigo_ISIN'];

    private const COMPLEMENT_COLUMNS = ['CNPJ_Fundo_Classe', 'Data_Referencia', 'Versao', 'Valor_Patrimonial_Cotas', 'Percentual_Dividend_Yield_Mes'];

    /** Limites das colunas: fora disso é dado quebrado, e gravar derrubaria o ano inteiro. */
    private const MAX_BOOK_VALUE = '999999999999999';

    private const MAX_YIELD_MAGNITUDE = '99999999';

    public function forYear(int $year): array
    {
        $path = $this->download($year);

        try {
            $zip = new ZipArchive;

            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                throw new SourceUnavailable("O arquivo da CVM de {$year} está corrompido.");
            }

            $complements = $this->complements($this->rows($zip, 'complemento', self::COMPLEMENT_COLUMNS));
            $records = $this->latestVersions($this->rows($zip, 'geral', self::GENERAL_COLUMNS), $complements);
            $zip->close();

            return $records;
        } finally {
            @unlink($path);
        }
    }

    private function download(int $year): string
    {
        $url = rtrim((string) config('services.cvm.reports_url'), '/')."/inf_mensal_fii_{$year}.zip";

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (ConnectionException) {
            throw new SourceUnavailable('Não foi possível conectar à CVM.');
        }

        if (! $response->successful()) {
            throw new SourceUnavailable("A CVM não entregou o arquivo de {$year} ({$response->status()}).");
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'cvm');
        file_put_contents($path, $response->body());

        return $path;
    }

    /**
     * @param  list<string>  $required
     * @return list<array<string, string>>
     */
    private function rows(ZipArchive $zip, string $kind, array $required): array
    {
        $stream = $this->openEntry($zip, $kind);
        $header = $this->readRow($stream);

        if ($header === null || array_diff($required, $header) !== []) {
            throw new SourceUnavailable("O arquivo {$kind} da CVM mudou de formato.");
        }

        $rows = [];
        $wanted = array_flip($required);

        while (($row = $this->readRow($stream)) !== null) {
            // Linha com colunas a menos ou a mais é corrupção: não dá para saber qual é qual.
            if (count($row) === count($header)) {
                // Só as colunas usadas: o arquivo tem 37, e guardar todas estoura a memória.
                $rows[] = array_intersect_key(array_combine($header, $row), $wanted);
            }
        }

        fclose($stream);

        return $rows;
    }

    /** @return resource */
    private function openEntry(ZipArchive $zip, string $kind)
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_contains($name, "_{$kind}_")) {
                $stream = $zip->getStream($name);

                if ($stream !== false) {
                    return $stream;
                }
            }
        }

        throw new SourceUnavailable("O arquivo {$kind} não veio no pacote da CVM.");
    }

    /**
     * @param  resource  $stream
     * @return list<string>|null
     */
    private function readRow($stream): ?array
    {
        $row = fgetcsv($stream, null, ';', '"', '');

        if ($row === false) {
            return null;
        }

        return array_map(
            fn (?string $cell): string => trim((string) mb_convert_encoding((string) $cell, 'UTF-8', 'ISO-8859-1')),
            $row,
        );
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, array<string, string>> por CNPJ, competência e versão
     */
    private function complements(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed["{$row['CNPJ_Fundo_Classe']}|{$row['Data_Referencia']}|{$row['Versao']}"] = $row;
        }

        return $indexed;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, array<string, string>>  $complements
     * @return list<FundReportRecord>
     */
    private function latestVersions(array $rows, array $complements): array
    {
        $latest = [];
        $latestWithBookValue = [];

        foreach ($rows as $row) {
            $record = $this->record($row, $complements);

            if ($record === null) {
                continue;
            }

            $key = "{$record->cnpj}|{$record->month}";

            if (! isset($latest[$key]) || $record->version > $latest[$key]->version) {
                $latest[$key] = $record;
            }

            if ($record->bookValue !== null && (! isset($latestWithBookValue[$key]) || $record->version > $latestWithBookValue[$key]->version)) {
                $latestWithBookValue[$key] = $record;
            }
        }

        // Correção que veio sem VP não apaga o último VP válido da competência.
        return array_values(array_map(
            fn (FundReportRecord $record): FundReportRecord => $record->bookValue === null
                ? ($latestWithBookValue["{$record->cnpj}|{$record->month}"] ?? $record)
                : $record,
            $latest,
        ));
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, array<string, string>>  $complements
     */
    private function record(array $row, array $complements): ?FundReportRecord
    {
        $reference = $row['Data_Referencia'];
        $delivered = $row['Data_Entrega'];

        if (! $this->isDate($reference) || ! $this->isDate($delivered) || ! ctype_digit($row['Versao']) || $row['CNPJ_Fundo_Classe'] === '') {
            return null;
        }

        $complement = $complements["{$row['CNPJ_Fundo_Classe']}|{$reference}|{$row['Versao']}"] ?? [];
        $bookValue = $this->decimal($complement['Valor_Patrimonial_Cotas'] ?? '');

        return new FundReportRecord(
            $row['CNPJ_Fundo_Classe'],
            $row['Codigo_ISIN'],
            substr($reference, 0, 7),
            (int) $row['Versao'],
            $delivered,
            $this->validBookValue($bookValue),
            $this->validYield($this->decimal($complement['Percentual_Dividend_Yield_Mes'] ?? '')),
        );
    }

    private function validBookValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $number = BigDecimal::of($value);

        return $number->isPositive() && $number->isLessThanOrEqualTo(self::MAX_BOOK_VALUE) ? $value : null;
    }

    private function validYield(?string $value): ?string
    {
        return $value !== null && BigDecimal::of($value)->abs()->isLessThanOrEqualTo(self::MAX_YIELD_MAGNITUDE) ? $value : null;
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1;
    }

    /** O texto da CVM é mantido como veio: passar por float mudaria as últimas casas. */
    private function decimal(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            BigDecimal::of($value);
        } catch (MathException) {
            return null;
        }

        return preg_match('/^-?\d+(\.\d+)?$/D', $value) === 1 ? $value : (string) BigDecimal::of($value);
    }
}
