<?php

use App\Modules\Fiis\Domain\AlertDecision;
use App\Modules\Fiis\Domain\AlertPolicy;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\RuleState;

const HOUR = 3600;
const T0 = 1_000_000_000;

function step(RuleState $state, bool $matches, string $price, int $at, ?string $eventKey = null): AlertDecision
{
    return (new AlertPolicy)->decide($state, $matches, Money::fromString($price), $at, $eventKey);
}

function afterFirstAlert(string $price = '10.00'): RuleState
{
    return step(RuleState::fresh(), true, $price, T0)->state;
}

it('alerts when the rule starts to hold', function () {
    expect(step(RuleState::fresh(), true, '10.00', T0)->alert)->toBeTrue();
});

it('does not alert while the rule does not hold', function () {
    expect(step(RuleState::fresh(), false, '10.00', T0)->alert)->toBeFalse();
});

it('does not repeat while the rule keeps holding, even after 24 hours', function (int $elapsed) {
    expect(step(afterFirstAlert(), true, '10.00', T0 + $elapsed)->alert)->toBeFalse();
})->with([HOUR, 24 * HOUR, 72 * HOUR]);

it('alerts again before 24 hours only at 2% below the last alert', function (string $price, bool $expected) {
    expect(step(afterFirstAlert('10.00'), true, $price, T0 + HOUR)->alert)->toBe($expected);
})->with([
    ['9.81', false],
    ['9.8001', false],
    ['9.80', true],
    ['9.79', true],
]);

it('measures the 2% from the last alert, not the first', function () {
    $second = step(afterFirstAlert('10.00'), true, '9.80', T0 + HOUR);

    expect(step($second->state, true, '9.70', T0 + 2 * HOUR)->alert)->toBeFalse()
        ->and(step($second->state, true, '9.604', T0 + 2 * HOUR)->alert)->toBeTrue();
});

it('resets when the rule stops holding: a later crossing after 24 hours alerts', function () {
    $stopped = step(afterFirstAlert(), false, '11.00', T0 + HOUR)->state;

    expect($stopped->active)->toBeFalse()
        ->and(step($stopped, true, '10.50', T0 + 24 * HOUR)->alert)->toBeTrue();
});

it('blocks a new crossing inside the 24 hour ceiling and releases it when the ceiling opens', function () {
    $stopped = step(afterFirstAlert('10.00'), false, '11.00', T0 + HOUR)->state;
    $blocked = step($stopped, true, '10.00', T0 + 2 * HOUR);

    expect($blocked->alert)->toBeFalse();

    $stillInside = step($blocked->state, true, '10.00', T0 + 24 * HOUR - 1);
    expect($stillInside->alert)->toBeFalse();

    $released = step($stillInside->state, true, '10.00', T0 + 24 * HOUR);
    expect($released->alert)->toBeTrue();

    // E só uma vez.
    expect(step($released->state, true, '10.00', T0 + 25 * HOUR)->alert)->toBeFalse();
});

it('drops a blocked alert if the rule no longer holds when the ceiling opens', function () {
    $stopped = step(afterFirstAlert(), false, '11.00', T0 + HOUR)->state;
    $blocked = step($stopped, true, '10.00', T0 + 2 * HOUR)->state;
    $stoppedAgain = step($blocked, false, '11.00', T0 + 3 * HOUR)->state;

    expect(step($stoppedAgain, false, '11.00', T0 + 25 * HOUR)->alert)->toBeFalse()
        ->and($stoppedAgain->pending)->toBeFalse();
});

it('lets the 2% exception through a blocked crossing', function () {
    $stopped = step(afterFirstAlert('10.00'), false, '11.00', T0 + HOUR)->state;

    expect(step($stopped, true, '9.80', T0 + 2 * HOUR)->alert)->toBeTrue();
});

it('counts elapsed time, so a clock change cannot open or hold the ceiling', function () {
    // 24 h medidas em segundos corridos: o relógio de parede não entra na conta.
    expect(step(step(afterFirstAlert(), false, '11', T0 + 1)->state, true, '10', T0 + 23 * HOUR)->alert)->toBeFalse();
});

describe('sharp drop, a new event on each trading day', function () {
    it('does not repeat within the same trading day', function () {
        $first = step(RuleState::fresh(), true, '10.00', T0, '2026-10-29');

        expect(step($first->state, true, '10.00', T0 + HOUR, '2026-10-29')->alert)->toBeFalse();
    });

    it('alerts again on the next trading day once 24 hours have passed', function () {
        $first = step(RuleState::fresh(), true, '10.00', T0, '2026-10-29');

        expect(step($first->state, true, '10.00', T0 + 24 * HOUR, '2026-10-30')->alert)->toBeTrue();
    });

    it('still respects the ceiling: a next-day drop inside 24 hours waits, then goes out', function () {
        $first = step(RuleState::fresh(), true, '10.00', T0, '2026-10-29');
        $nextDay = step($first->state, true, '10.00', T0 + 20 * HOUR, '2026-10-30');

        expect($nextDay->alert)->toBeFalse();
        expect(step($nextDay->state, true, '10.00', T0 + 24 * HOUR, '2026-10-30')->alert)->toBeTrue();
    });

    it('lets the 2% exception through on the next day', function () {
        $first = step(RuleState::fresh(), true, '10.00', T0, '2026-10-29');

        expect(step($first->state, true, '9.80', T0 + 20 * HOUR, '2026-10-30')->alert)->toBeTrue();
    });
});
