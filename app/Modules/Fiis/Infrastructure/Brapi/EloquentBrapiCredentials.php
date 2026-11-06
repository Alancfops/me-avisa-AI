<?php

namespace App\Modules\Fiis\Infrastructure\Brapi;

use App\Modules\Fiis\Application\BrapiCredentials;
use App\Modules\Fiis\Infrastructure\Persistence\AppSecretModel;
use Illuminate\Support\Facades\Cache;

final class EloquentBrapiCredentials implements BrapiCredentials
{
    private const NAME = 'brapi_token';

    public function token(): ?string
    {
        $env = $this->envToken();

        return $env !== '' ? $env : $this->saved();
    }

    public function source(): ?string
    {
        if ($this->envToken() !== '') {
            return 'env';
        }

        return $this->saved() === null ? null : 'app';
    }

    public function save(string $token): void
    {
        AppSecretModel::query()->updateOrCreate(['name' => self::NAME], ['value' => $token]);
        $this->forgetCachedState();
    }

    public function forget(): void
    {
        AppSecretModel::query()->where('name', self::NAME)->delete();
        $this->forgetCachedState();
    }

    private function saved(): ?string
    {
        $value = AppSecretModel::query()->where('name', self::NAME)->first()?->value;

        return $value === null || $value === '' ? null : $value;
    }

    private function envToken(): string
    {
        return trim((string) config('services.brapi.token'));
    }

    /** Chave nova: a cota mostrada na Saúde e um bloqueio por 429 eram da anterior. */
    private function forgetCachedState(): void
    {
        Cache::forget('brapi.usage');
        Cache::forget(BrapiClient::BLOCK_KEY);
    }
}
