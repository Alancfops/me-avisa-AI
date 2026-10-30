<?php

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\AlertRepository;
use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\MarketData;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\RuleState;

$instant = '2026-10-30T15:00:00-03:00';

it('keeps the instant of a quote, whatever the offset', function (string $at) use ($instant) {
    app(MarketData::class)->saveQuote(new Quote('MXRF11', Money::fromString('9'), null, null, new DateTimeImmutable($at)));

    expect(app(MarketData::class)->latestQuote('MXRF11')->quotedAt->getTimestamp())
        ->toBe((new DateTimeImmutable($instant))->getTimestamp());
})->with(['2026-10-30T15:00:00-03:00', '2026-10-30T18:00:00Z', '2026-10-30T20:00:00+02:00']);

it('keeps the instant of the last evaluated quote', function () use ($instant) {
    $favorites = app(FavoriteRepository::class);
    $favorites->add('MXRF11');

    $favorites->markEvaluated('MXRF11', new DateTimeImmutable($instant));

    expect($favorites->lastEvaluatedQuoteAt('MXRF11')->getTimestamp())->toBe((new DateTimeImmutable($instant))->getTimestamp());
});

it('keeps the instants of an alert and of the rule state', function () use ($instant) {
    $moment = new DateTimeImmutable($instant);
    $state = new RuleState(true, false, null, Money::fromString('9'), $moment->getTimestamp());
    $alert = new Alert('MXRF11', RuleKind::PriceTarget, Money::fromString('9'), 'alvo', 'motivo', $moment, $moment);
    $alerts = app(AlertRepository::class);

    $alerts->apply('MXRF11', RuleKind::PriceTarget, $state, $alert);

    expect($alerts->state('MXRF11', RuleKind::PriceTarget)->lastAlertAt)->toBe($moment->getTimestamp())
        ->and($alerts->latest(1)[0]->occurredAt->getTimestamp())->toBe($moment->getTimestamp())
        ->and($alerts->latest(1)[0]->quotedAt->getTimestamp())->toBe($moment->getTimestamp());
});
