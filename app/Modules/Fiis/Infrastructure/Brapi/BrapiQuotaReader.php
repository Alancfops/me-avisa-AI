<?php

namespace App\Modules\Fiis\Infrastructure\Brapi;

use App\Modules\Fiis\Application\QuotaReader;
use App\Modules\Fiis\Application\QuotaUsage;
use App\Modules\Fiis\Application\SourceUnavailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

final readonly class BrapiQuotaReader implements QuotaReader
{
    /** A tela de Saúde não pode gastar a cota que ela mesma mede. */
    private const CACHE_SECONDS = 300;

    public function __construct(private BrapiClient $client) {}

    public function usage(): ?QuotaUsage
    {
        /** @var array{plan: string, limit: int, used: int, remaining: int}|null $cached */
        $cached = Cache::get('brapi.usage');
        $values = $cached ?? $this->fetch();

        if ($values === null) {
            return null;
        }

        if ($cached === null) {
            Cache::put('brapi.usage', $values, self::CACHE_SECONDS);
        }

        return new QuotaUsage($values['plan'], $values['limit'], $values['used'], $values['remaining']);
    }

    /** @return array{plan: string, limit: int, used: int, remaining: int}|null */
    private function fetch(): ?array
    {
        try {
            $body = $this->client->get('/api/v2/user/usage');
        } catch (SourceUnavailable) {
            return null;
        }

        $plan = Arr::get($body, 'usage.planName');
        $limit = Arr::get($body, 'usage.planLimit');
        $used = Arr::get($body, 'usage.currentUsage');
        $remaining = Arr::get($body, 'usage.remainingUsage');

        if (! is_string($plan) || ! is_int($limit) || ! is_int($used) || ! is_int($remaining)) {
            return null;
        }

        return ['plan' => $plan, 'limit' => $limit, 'used' => $used, 'remaining' => $remaining];
    }
}
