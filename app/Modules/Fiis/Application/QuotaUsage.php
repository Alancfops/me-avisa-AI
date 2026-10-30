<?php

namespace App\Modules\Fiis\Application;

final readonly class QuotaUsage
{
    public function __construct(
        public string $plan,
        public int $limit,
        public int $used,
        public int $remaining,
    ) {}
}
