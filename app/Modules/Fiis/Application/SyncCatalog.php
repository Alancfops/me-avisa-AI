<?php

namespace App\Modules\Fiis\Application;

final readonly class SyncCatalog
{
    public function __construct(
        private FundCatalog $source,
        private CatalogRepository $catalog,
    ) {}

    /**
     * @return int quantos fundos a fonte informou
     *
     * @throws SourceUnavailable
     */
    public function handle(): int
    {
        $entries = $this->source->all();
        $this->catalog->save($entries);

        return count($entries);
    }
}
