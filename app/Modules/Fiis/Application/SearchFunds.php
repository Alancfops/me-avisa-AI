<?php

namespace App\Modules\Fiis\Application;

final readonly class SearchFunds
{
    public function __construct(private CatalogRepository $catalog) {}

    /** @return list<CatalogEntry> */
    public function handle(string $query): array
    {
        $query = trim($query);

        return $query === '' ? [] : $this->catalog->search($query);
    }
}
