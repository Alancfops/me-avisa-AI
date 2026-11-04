<?php

use App\Modules\Fiis\Application\EvaluateFavorites;
use App\Modules\Fiis\Infrastructure\Persistence\AlertModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use App\Modules\Fiis\Infrastructure\Persistence\SourceEventModel;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use App\Modules\Telegram\Infrastructure\SendTelegramMessageJob;
use Illuminate\Support\Carbon;
use Tests\Support\Telegram\FakeTelegramApi;

beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    FundModel::create(['ticker' => 'MXRF11', 'name' => 'Maxi Renda']);
    FavoriteModel::create(['ticker' => 'MXRF11', 'mode' => 'target', 'target' => '9.50', 'sharp_drop' => false]);
    QuoteModel::query()->create(['ticker' => 'MXRF11', 'price' => '9.42', 'change_percent' => '-0.5', 'quoted_at' => '2026-10-30T14:30:00-03:00']);
});
afterEach(fn () => Carbon::setTestNow());

it('sends every recorded alert to the connected chat', function () {
    $api = new FakeTelegramApi;
    app()->instance(TelegramApi::class, $api);
    app(TelegramState::class)->connect(42, new DateTimeImmutable);

    app(EvaluateFavorites::class)->handle();

    expect(AlertModel::query()->count())->toBe(1)
        ->and($api->sent)->toHaveCount(1)
        ->and($api->sent[0][0])->toBe(42)
        ->and($api->sent[0][1])->toContain('🔔 <b>MXRF11</b> · 🎯 Preço alvo atingido');
});

it('sends nothing without a connected chat', function () {
    $api = new FakeTelegramApi;
    app()->instance(TelegramApi::class, $api);

    app(EvaluateFavorites::class)->handle();

    expect(AlertModel::query()->count())->toBe(1)->and($api->sent)->toBe([]);
});

it('keeps the alert and shows the failure in Health when Telegram is down', function () {
    app()->instance(TelegramApi::class, new FakeTelegramApi(down: true));
    app(TelegramState::class)->connect(42, new DateTimeImmutable);

    app(EvaluateFavorites::class)->handle();

    expect(AlertModel::query()->count())->toBe(1)
        ->and(SourceEventModel::query()->where('source', 'telegram')->where('kind', 'failure')->exists())->toBeTrue();
});

it('keeps retrying the delivery for a few minutes, so a short outage does not lose it', function () {
    $job = new SendTelegramMessageJob('oi');

    expect($job->tries)->toBe(3)->and(array_sum($job->backoff))->toBeGreaterThanOrEqual(300);
});
