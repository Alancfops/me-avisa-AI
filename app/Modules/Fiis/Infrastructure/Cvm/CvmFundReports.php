<?php

namespace App\Modules\Fiis\Infrastructure\Cvm;

use App\Modules\Fiis\Application\FundReportRecord;
use App\Modules\Fiis\Application\FundReports;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\Portfolio;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use ZipArchive;

final class CvmFundReports implements FundReports
{
    private const TIMEOUT_SECONDS = 120;

    private const GENERAL_COLUMNS = ['CNPJ_Fundo_Classe', 'Data_Referencia', 'Versao', 'Data_Entrega', 'Codigo_ISIN'];

    private const COMPLEMENT_COLUMNS = ['CNPJ_Fundo_Classe', 'Data_Referencia', 'Versao', 'Valor_Patrimonial_Cotas', 'Percentual_Dividend_Yield_Mes'];

    /** Perfil do fundo: ausente no arquivo vira null, não "mudou de formato". */
    private const GENERAL_OPTIONAL_COLUMNS = ['Segmento_Atuacao', 'Nome_Administrador', 'Data_Funcionamento'];

    private const COMPLEMENT_OPTIONAL_COLUMNS = ['Patrimonio_Liquido', 'Total_Numero_Cotistas'];

    /** Composição da carteira: o arquivo todo é opcional, e a falta dele não derruba VP e DY. */
    private const PORTFOLIO_KEYS = ['CNPJ_Fundo_Classe', 'Data_Referencia', 'Versao'];

    private const PORTFOLIO_AMOUNTS = ['Total_Investido', 'Direitos_Bens_Imoveis', 'CRI', 'CRI_CRA', 'LCI', 'LCI_LCA', 'Letras_Hipotecarias', 'LIG', 'FII'];

    private const MAX_SEGMENT_LENGTH = 120;

    private const MAX_ADMINISTRATOR_LENGTH = 255;

    private const MAX_NET_ASSETS = '9999999999999999999999';

    private const MAX_HOLDERS = 4294967295;

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

            $complements = $this->complements($this->rows($zip, 'complemento', self::COMPLEMENT_COLUMNS, self::COMPLEMENT_OPTIONAL_COLUMNS));
            $portfolios = $this->portfolios($zip);
            $records = $this->latestVersions($this->rows($zip, 'geral', self::GENERAL_COLUMNS, self::GENERAL_OPTIONAL_COLUMNS), $complements, $portfolios);
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
     * @param  list<string>  $optional  lidas só se estiverem no cabeçalho
     * @return Generator<int, array<string, string>>
     */
    private function rows(ZipArchive $zip, string $kind, array $required, array $optional = []): Generator
    {
        $stream = $this->openEntry($zip, $kind);
        $header = $this->readRow($stream);

        if ($header === null || array_diff($required, $header) !== []) {
            throw new SourceUnavailable("O arquivo {$kind} da CVM mudou de formato.");
        }

        $wanted = array_flip([...$required, ...array_intersect($optional, $header)]);

        while (($row = $this->readRow($stream)) !== null) {
            // Linha com colunas a menos ou a mais é corrupção: não dá para saber qual é qual.
            if (count($row) === count($header)) {
                // Só as colunas usadas: o arquivo tem 37, e guardar todas estoura a memória.
                yield array_intersect_key(array_combine($header, $row), $wanted);
            }
        }

        fclose($stream);
    }

    /**
     * Composição por CNPJ, competência e versão. Arquivo ausente ou em outro formato
     * dá mapa vazio: o tipo do fundo fica sem dados, sem derrubar o ano.
     *
     * @return array<string, Portfolio>
     */
    private function portfolios(ZipArchive $zip): array
    {
        $portfolios = [];

        try {
            foreach ($this->rows($zip, 'ativo_passivo', [...self::PORTFOLIO_KEYS, ...self::PORTFOLIO_AMOUNTS]) as $row) {
                $portfolio = $this->portfolio($row);

                if ($portfolio !== null) {
                    $portfolios["{$row['CNPJ_Fundo_Classe']}|{$row['Data_Referencia']}|{$row['Versao']}"] = $portfolio;
                }
            }
        } catch (SourceUnavailable) {
            return [];
        }

        return $portfolios;
    }

    /**
     * Célula vazia é posição zero (o fundo não tem aquele ativo); texto ilegível ou
     * fora da coluna invalida a composição inteira, e `null` não vira tipo.
     *
     * @param  array<string, string>  $row
     */
    private function portfolio(array $row): ?Portfolio
    {
        $amounts = [];

        foreach (self::PORTFOLIO_AMOUNTS as $column) {
            $value = $row[$column] === '' ? '0' : $this->decimal($row[$column]);

            if ($value === null || BigDecimal::of($value)->isNegative() || BigDecimal::of($value)->isGreaterThan(self::MAX_NET_ASSETS)) {
                return null;
            }

            $amounts[$column] = BigDecimal::of($value);
        }

        if ($row['Total_Investido'] === '') {
            return null;
        }

        $receivables = $amounts['CRI']->plus($amounts['CRI_CRA'])->plus($amounts['LCI'])->plus($amounts['LCI_LCA'])->plus($amounts['Letras_Hipotecarias'])->plus($amounts['LIG']);

        if ($receivables->isGreaterThan(self::MAX_NET_ASSETS)) {
            return null;
        }

        return new Portfolio(
            (string) $amounts['Total_Investido']->toScale(2, RoundingMode::HalfUp),
            (string) $amounts['Direitos_Bens_Imoveis']->toScale(2, RoundingMode::HalfUp),
            (string) $receivables->toScale(2, RoundingMode::HalfUp),
            (string) $amounts['FII']->toScale(2, RoundingMode::HalfUp),
        );
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
     * @param  iterable<array<string, string>>  $rows
     * @return array<string, array<string, string>> por CNPJ, competência e versão
     */
    private function complements(iterable $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed["{$row['CNPJ_Fundo_Classe']}|{$row['Data_Referencia']}|{$row['Versao']}"] = $row;
        }

        return $indexed;
    }

    /**
     * @param  iterable<array<string, string>>  $rows
     * @param  array<string, array<string, string>>  $complements
     * @param  array<string, Portfolio>  $portfolios
     * @return list<FundReportRecord>
     */
    private function latestVersions(iterable $rows, array $complements, array $portfolios): array
    {
        $latest = [];
        $latestWithBookValue = [];
        $latestWithPortfolio = [];

        foreach ($rows as $row) {
            $record = $this->record($row, $complements, $portfolios);

            if ($record === null) {
                continue;
            }

            $key = "{$record->cnpj}|{$record->month}";

            if (! isset($latest[$key]) || $record->version > $latest[$key]->version) {
                $latest[$key] = $record;
            }

            if ($record->portfolio !== null && (! isset($latestWithPortfolio[$key]) || $record->version > $latestWithPortfolio[$key]->version)) {
                $latestWithPortfolio[$key] = $record;
            }

            if ($record->bookValue !== null && (! isset($latestWithBookValue[$key]) || $record->version > $latestWithBookValue[$key]->version)) {
                $latestWithBookValue[$key] = $record;
            }
        }

        // Correção que veio sem VP ou sem composição não apaga o último valor válido da competência.
        return array_values(array_map(function (FundReportRecord $record) use ($latestWithBookValue, $latestWithPortfolio): FundReportRecord {
            $key = "{$record->cnpj}|{$record->month}";
            $record = $record->bookValue === null ? ($latestWithBookValue[$key] ?? $record) : $record;

            return $record->portfolio === null ? $record->withPortfolio($latestWithPortfolio[$key]->portfolio ?? null) : $record;
        }, $latest));
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, array<string, string>>  $complements
     * @param  array<string, Portfolio>  $portfolios
     */
    private function record(array $row, array $complements, array $portfolios): ?FundReportRecord
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
            $this->text($row['Segmento_Atuacao'] ?? '', self::MAX_SEGMENT_LENGTH),
            $this->text($row['Nome_Administrador'] ?? '', self::MAX_ADMINISTRATOR_LENGTH),
            $this->validDate($row['Data_Funcionamento'] ?? ''),
            $this->validNetAssets($this->decimal($complement['Patrimonio_Liquido'] ?? '')),
            $this->validHolders($complement['Total_Numero_Cotistas'] ?? ''),
            $portfolios["{$row['CNPJ_Fundo_Classe']}|{$reference}|{$row['Versao']}"] ?? null,
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

    /** Texto maior que a coluna é dado quebrado: vira null em vez de derrubar o ano. */
    private function text(string $value, int $maxLength): ?string
    {
        return $value !== '' && mb_strlen($value) <= $maxLength ? $value : null;
    }

    private function validDate(string $value): ?string
    {
        return $this->isDate($value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }

    private function validNetAssets(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $number = BigDecimal::of($value);

        return $number->isPositive() && $number->isLessThanOrEqualTo(self::MAX_NET_ASSETS) ? $value : null;
    }

    private function validHolders(string $value): ?int
    {
        // Zero cotistas não existe num fundo com informe: é campo vazio, e ausente é null.
        if (! ctype_digit($value) || strlen($value) > 10 || (int) $value < 1 || (int) $value > self::MAX_HOLDERS) {
            return null;
        }

        return (int) $value;
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
