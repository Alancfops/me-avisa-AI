<?php

use App\Modules\Fiis\Application\AlertChange;
use App\Modules\Fiis\Application\AlertChangeStatus;
use App\Modules\Fiis\Application\BuildFundSnapshot;
use App\Modules\Fiis\Application\ChangeAlert;
use App\Modules\Fiis\Application\EvaluateFavorites;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Application\UpdateFavoriteSettings;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use Psr\Log\NullLogger;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryAlerts;
use Tests\Support\Fiis\InMemoryFavorites;
use Tests\Support\Fiis\InMemoryMarketData;
use Tests\Support\Fiis\InMemoryReports;

final class AlertWorld
{
    public InMemoryFavorites $favorites;

    public InMemoryMarketData $market;

    public function __construct(?string $price = '9.62')
    {
        $this->favorites = new InMemoryFavorites(['MXRF11']);
        $this->market = new InMemoryMarketData;

        if ($price !== null) {
            $this->market->saveQuote(new Quote('MXRF11', Money::fromString($price), null, null, new DateTimeImmutable('2026-11-09 10:00:00-03:00')));
        }
    }

    public function change(FavoriteSettings $settings, bool $confirmed = false, string $ticker = 'MXRF11'): AlertChange
    {
        $alerts = new InMemoryAlerts;
        $evaluate = new EvaluateFavorites(
            $this->favorites,
            $this->market,
            $alerts,
            new BuildFundSnapshot($this->market, new InMemoryReports),
            new FixedClock('2026-11-09 10:00:00-03:00'),
            new NullLogger,
        );

        return (new ChangeAlert($this->favorites, $this->market, new UpdateFavoriteSettings($this->favorites, $alerts, $evaluate)))
            ->handle($ticker, $settings, $confirmed);
    }
}

function targetOf(string $price): FavoriteSettings
{
    return FavoriteSettings::target(Money::fromString($price), true);
}

it('saves a target close to the price', function () {
    $world = new AlertWorld('9.62');
    $change = $world->change(targetOf('9.50'));

    expect($change->status)->toBe(AlertChangeStatus::Saved)
        ->and($world->favorites->settings('MXRF11')->target?->toDecimal())->toBe('9.5')
        ->and($change->before?->target)->toBeNull()
        ->and($change->currentPrice?->toDecimal())->toBe('9.62')
        ->and($change->quote)->not->toBeNull()
        ->and($change->exceeded)->toBe([])
        ->and($change->triggersNow)->toBeFalse()
        ->and($change->priceUnchecked)->toBeFalse();
});

it('asks for confirmation and saves nothing when the target is far', function () {
    $world = new AlertWorld('9.62');
    $before = $world->favorites->settings('MXRF11');
    $change = $world->change(targetOf('95.00'));

    expect($change->status)->toBe(AlertChangeStatus::NeedsConfirmation)
        ->and(array_keys($change->exceeded))->toBe(['target'])
        ->and($world->favorites->settings('MXRF11'))->toBe($before);
});

it('saves the far target once confirmed', function () {
    $world = new AlertWorld('9.62');
    $change = $world->change(targetOf('95.00'), confirmed: true);

    expect($change->status)->toBe(AlertChangeStatus::Saved)
        ->and(array_keys($change->exceeded))->toBe(['target'])
        ->and($world->favorites->settings('MXRF11')->target?->toDecimal())->toBe('95');
});

it('saves without the lock when there is no price at all', function () {
    $world = new AlertWorld(null);
    $change = $world->change(targetOf('95.00'));

    expect($change->status)->toBe(AlertChangeStatus::Saved)
        ->and($change->priceUnchecked)->toBeTrue()
        ->and($change->currentPrice)->toBeNull()
        ->and($change->triggersNow)->toBeFalse();
});

it('falls back to the last close when there is no quote', function () {
    $world = new AlertWorld(null);
    $world->market->saveCloses('MXRF11', [new DailyClose('2026-11-06', Money::fromString('9.62'))]);
    $change = $world->change(targetOf('95.00'));

    expect($change->status)->toBe(AlertChangeStatus::NeedsConfirmation)
        ->and($change->close?->date)->toBe('2026-11-06')
        ->and($change->quote)->toBeNull()
        ->and($change->priceUnchecked)->toBeFalse();
});

it('says the target already triggers', function () {
    $change = (new AlertWorld('8.80'))->change(targetOf('10.00'));

    expect($change->status)->toBe(AlertChangeStatus::Saved)->and($change->triggersNow)->toBeTrue();
});

it('says the range already contains the price', function () {
    $settings = FavoriteSettings::range(Money::fromString('9.00'), Money::fromString('10.00'), false);

    expect((new AlertWorld('9.62'))->change($settings)->triggersNow)->toBeTrue();
});

it('reports unchanged when the alert is the same as before', function () {
    $world = new AlertWorld('9.62');
    $world->favorites->saveSettings('MXRF11', targetOf('9.50'));

    expect($world->change(targetOf('9.50'))->status)->toBe(AlertChangeStatus::Unchanged);
});

it('reports a fund that is not a favorite', function () {
    $world = new AlertWorld('9.62');
    $change = $world->change(targetOf('9.50'), ticker: 'KNRI11');

    expect($change->status)->toBe(AlertChangeStatus::NotFavorite)
        ->and($world->favorites->exists('KNRI11'))->toBeFalse();
});

it('does not lock a far target that was already saved when only the sharp drop changes', function () {
    $world = new AlertWorld('10.00');
    $world->favorites->saveSettings('MXRF11', FavoriteSettings::target(Money::fromString('13.50'), true));
    $change = $world->change(FavoriteSettings::target(Money::fromString('13.50'), false));

    expect($change->status)->toBe(AlertChangeStatus::Saved)
        ->and($change->exceeded)->toBe([])
        ->and($world->favorites->settings('MXRF11')->sharpDrop)->toBeFalse();
});

it('locks again when the far target changes to another far value', function () {
    $world = new AlertWorld('10.00');
    $world->favorites->saveSettings('MXRF11', FavoriteSettings::target(Money::fromString('13.50'), true));
    $change = $world->change(FavoriteSettings::target(Money::fromString('13.60'), true));

    expect($change->status)->toBe(AlertChangeStatus::NeedsConfirmation)
        ->and(array_keys($change->exceeded))->toBe(['target']);
});

it('checks the range minimum and maximum separately', function () {
    $world = new AlertWorld('10.00');
    $world->favorites->saveSettings('MXRF11', FavoriteSettings::range(Money::fromString('5.00'), Money::fromString('9.00'), false));
    $change = $world->change(FavoriteSettings::range(Money::fromString('5.00'), Money::fromString('14.00'), false));

    expect($change->status)->toBe(AlertChangeStatus::NeedsConfirmation)
        ->and(array_keys($change->exceeded))->toBe(['rangeMax']);
});
