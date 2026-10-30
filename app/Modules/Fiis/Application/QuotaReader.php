<?php

namespace App\Modules\Fiis\Application;

interface QuotaReader
{
    /** @return QuotaUsage|null `null` se a fonte não informar agora */
    public function usage(): ?QuotaUsage;
}
