<?php

namespace App\Modules\Fiis\Application;

interface FundCatalog
{
    /**
     * @return list<CatalogEntry>
     *
     * @throws SourceUnavailable
     */
    public function all(): array;
}
