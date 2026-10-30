<?php

namespace App\Modules\Fiis\Application;

final readonly class SyncCatalog
{
    public function __construct(
        private FundCatalog $source,
        private CatalogRepository $catalog,
        private SourceLog $sourceLog,
    ) {}

    /**
     * @return int quantos fundos a fonte informou
     *
     * @throws SourceUnavailable
     */
    public function handle(): int
    {
        try {
            $entries = $this->source->all();
        } catch (SourceUnavailable $failure) {
            $this->sourceLog->failure(Source::Brapi, "Catálogo: {$failure->getMessage()}");

            throw $failure;
        }

        $this->catalog->save($entries);
        $this->sourceLog->success(Source::Brapi);

        return count($entries);
    }
}
