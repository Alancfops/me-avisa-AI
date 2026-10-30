<?php

namespace App\Modules\Fiis\Infrastructure\Brapi;

use App\Modules\Fiis\Application\CatalogEntry;
use App\Modules\Fiis\Application\FundCatalog;
use App\Modules\Fiis\Application\SourceUnavailable;
use Illuminate\Support\Arr;

final readonly class BrapiFundCatalog implements FundCatalog
{
    private const PAGE_SIZE = 100;

    /** Trava de segurança: uma paginação quebrada não pode gastar a cota inteira. */
    private const MAX_PAGES = 50;

    public function __construct(private BrapiClient $client) {}

    public function all(): array
    {
        $entries = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $body = $this->client->get('/api/v2/tickers', ['type' => 'fund', 'subType' => 'fii', 'page' => $page, 'limit' => self::PAGE_SIZE]);
            $results = $body['results'] ?? null;

            if (! is_array($results) || ! is_bool(Arr::get($body, 'pagination.hasNextPage'))) {
                throw new SourceUnavailable('A brapi enviou um catálogo inválido.');
            }

            foreach ($results as $result) {
                $entry = $this->entryFrom($result);

                if ($entry !== null) {
                    $entries[$entry->ticker] = $entry;
                }
            }

            if (Arr::get($body, 'pagination.hasNextPage') === false) {
                return array_values($entries);
            }
        }

        throw new SourceUnavailable('O catálogo da brapi não terminou de paginar.');
    }

    private function entryFrom(mixed $result): ?CatalogEntry
    {
        if (! is_array($result) || ($result['isActive'] ?? true) !== true) {
            return null;
        }

        $ticker = BrapiNumbers::normalizeTicker($result['symbol'] ?? null);

        if ($ticker === null) {
            return null;
        }

        $name = array_find(
            [$result['longName'] ?? null, $result['name'] ?? null],
            fn (mixed $candidate): bool => is_string($candidate) && trim($candidate) !== '',
        );

        return new CatalogEntry($ticker, is_string($name) ? trim($name) : $ticker);
    }
}
