<?php

use App\Modules\Fiis\Application\BuildFundSnapshot;
use App\Modules\Fiis\Application\EvaluateFavorites;
use App\Modules\Fiis\Application\MarketData;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Application\UpdateFavoriteSettings;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\RuleKind;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryAlerts;
use Tests\Support\Fiis\InMemoryFavorites;
use Tests\Support\Fiis\InMemoryMarketData;
use Tests\Support\Fiis\InMemoryReports;

final class EvaluationWorld
{
    public InMemoryFavorites $favorites;

    public InMemoryMarketData $market;

    public InMemoryAlerts $alerts;

    public FixedClock $clock;

    public function __construct()
    {
        $this->favorites = new InMemoryFavorites(['MXRF11']);
        $this->market = new InMemoryMarketData;
        $this->alerts = new InMemoryAlerts;
        $this->clock = new FixedClock('2026-10-29 10:00:00-03:00');
    }

    public function evaluator(): EvaluateFavorites
    {
        return new EvaluateFavorites(
            $this->favorites,
            $this->market,
            $this->alerts,
            new BuildFundSnapshot($this->market, new InMemoryReports),
            $this->clock,
            new NullLogger,
        );
    }

    public function updater(): UpdateFavoriteSettings
    {
        return new UpdateFavoriteSettings($this->favorites, $this->alerts, $this->evaluator());
    }

    /** Publica uma cotação e roda a avaliação no horário dela. */
    public function tick(string $at, string $price, ?string $change = null): void
    {
        $this->quote($at, $price, $change);
        $this->clock->current = new DateTimeImmutable($at);
        $this->evaluator()->handle();
    }

    public function quote(string $at, string $price, ?string $change = null): void
    {
        $this->market->saveQuote(new Quote(
            'MXRF11',
            Money::fromString($price),
            $change === null ? null : Percentage::fromString($change),
            null,
            new DateTimeImmutable($at),
        ));
    }

    /** @return list<string> */
    public function alertRules(): array
    {
        return array_map(fn ($alert) => $alert->rule->value, $this->alerts->alerts);
    }
}

function world(?FavoriteSettings $settings = null): EvaluationWorld
{
    $world = new EvaluationWorld;
    $world->favorites->saveSettings('MXRF11', $settings ?? FavoriteSettings::target(Money::fromString('9.50'), false));

    return $world;
}

it('records fund, rule, price, reference and moment', function () {
    $world = world();

    $world->tick('2026-10-29 14:00:00-03:00', '9.42');

    $alert = $world->alerts->alerts[0];

    expect($alert->ticker)->toBe('MXRF11')
        ->and($alert->rule)->toBe(RuleKind::PriceTarget)
        ->and($alert->price->toDecimal())->toBe('9.42')
        ->and($alert->reference)->toBe('alvo R$ 9,50')
        ->and($alert->reason)->toBe('Preço R$ 9,42 no alvo R$ 9,50')
        ->and($alert->occurredAt)->toEqual(new DateTimeImmutable('2026-10-29 14:00:00-03:00'))
        ->and($alert->quotedAt)->toEqual(new DateTimeImmutable('2026-10-29 14:00:00-03:00'));
});

it('alerts on the crossing, not while it keeps holding', function () {
    $world = world();

    $world->tick('2026-10-29 10:00:00-03:00', '9.60');
    $world->tick('2026-10-29 10:30:00-03:00', '9.50');
    $world->tick('2026-10-29 11:00:00-03:00', '9.49');
    $world->tick('2026-10-30 11:00:00-03:00', '9.49');

    expect($world->alertRules())->toBe(['price_target']);
});

it('does not repeat when the quote has nothing new, on holidays or after a clock change', function () {
    $world = world();

    $world->tick('2026-10-29 14:00:00-03:00', '9.42');
    $world->tick('2026-10-29 14:30:00-03:00', '9.60'); // deixou de valer

    // A mesma cotação, 30 h depois: o relógio andou e o teto abriu, mas nada aconteceu no mercado.
    $world->clock->current = new DateTimeImmutable('2026-10-30 20:30:00-03:00');
    $world->evaluator()->handle();

    expect($world->alertRules())->toBe(['price_target'])
        ->and($world->alerts->state('MXRF11', RuleKind::PriceTarget)->active)->toBeFalse();
});

it('treats the same instant in another offset as nothing new', function () {
    $world = world();

    $world->tick('2026-10-29 14:00:00-03:00', '9.42');
    $world->tick('2026-10-29 14:30:00-03:00', '9.60');
    // 17:30 em UTC é o mesmo instante de 14:30 em São Paulo; o preço abaixo não vale.
    $world->market->quotes['MXRF11'] = new Quote('MXRF11', Money::fromString('9.40'), null, null, new DateTimeImmutable('2026-10-29 17:30:00+00:00'));
    $world->clock->current = new DateTimeImmutable('2026-10-31 10:00:00-03:00');
    $world->evaluator()->handle();

    expect($world->alertRules())->toBe(['price_target']);
});

it('sends a blocked alert when the ceiling opens, if the rule still holds', function () {
    $world = world();

    $world->tick('2026-10-29 10:00:00-03:00', '9.40');
    $world->tick('2026-10-29 10:30:00-03:00', '9.60');
    $world->tick('2026-10-29 11:00:00-03:00', '9.40'); // volta: bloqueado
    expect($world->alertRules())->toHaveCount(1);

    $world->tick('2026-10-30 09:59:00-03:00', '9.40');
    expect($world->alertRules())->toHaveCount(1);

    $world->tick('2026-10-30 10:00:00-03:00', '9.40');
    expect($world->alertRules())->toHaveCount(2);
});

it('forgets a blocked alert when the rule stopped holding before the ceiling opened', function () {
    $world = world();

    $world->tick('2026-10-29 10:00:00-03:00', '9.40');
    $world->tick('2026-10-29 10:30:00-03:00', '9.60');
    $world->tick('2026-10-29 11:00:00-03:00', '9.40');
    $world->tick('2026-10-29 11:30:00-03:00', '9.60');
    $world->tick('2026-10-30 10:30:00-03:00', '9.60');

    expect($world->alertRules())->toHaveCount(1);
});

it('keeps a sharp drop in the morning from blocking a target in the afternoon', function () {
    $world = world(FavoriteSettings::target(Money::fromString('9.50'), true));

    $world->tick('2026-10-29 10:00:00-03:00', '9.60', '-3.5');
    $world->tick('2026-10-29 14:00:00-03:00', '9.45', '-3.5');

    expect($world->alertRules())->toBe(['sharp_drop', 'price_target']);
});

it('treats a sharp drop on the next trading day as a new event', function () {
    $world = world(FavoriteSettings::target(Money::fromString('1.00'), true));

    $world->tick('2026-10-29 10:00:00-03:00', '9.60', '-4');
    $world->tick('2026-10-29 16:00:00-03:00', '9.60', '-4');
    expect($world->alertRules())->toHaveCount(1);

    $world->tick('2026-10-30 10:30:00-03:00', '9.60', '-4');
    expect($world->alertRules())->toHaveCount(2);
});

it('does not fire sharp drop at exactly -3%', function () {
    $world = world(FavoriteSettings::target(Money::fromString('1.00'), true));

    $world->tick('2026-10-29 10:00:00-03:00', '9.60', '-3');

    expect($world->alertRules())->toBe([]);
});

it('keeps the cycle when the data is missing: an unavailable rule did not stop holding', function () {
    $world = world(FavoriteSettings::target(Money::fromString('1.00'), true));

    $world->tick('2026-10-29 10:00:00-03:00', '9.60', '-4');
    $world->tick('2026-10-29 10:30:00-03:00', '9.60'); // variação ausente
    $world->tick('2026-10-29 11:00:00-03:00', '9.60', '-4');

    expect($world->alertRules())->toHaveCount(1)
        ->and($world->alerts->state('MXRF11', RuleKind::SharpDrop)->active)->toBeTrue();
});

it('does not repeat after the machine comes back', function () {
    $world = world();
    $world->tick('2026-10-29 10:00:00-03:00', '9.40');

    // Novo processo, mesmo banco, dias depois, e a regra ainda vale.
    $world->tick('2026-11-02 10:00:00-03:00', '9.41');

    expect($world->alertRules())->toHaveCount(1);
});

it('alerts on the first evaluation after coming back if the rule only holds now', function () {
    $world = world();
    $world->tick('2026-10-29 10:00:00-03:00', '9.60');

    $world->tick('2026-11-02 10:00:00-03:00', '9.40');

    expect($world->alertRules())->toHaveCount(1);
});

it('reevaluates right away and starts a new cycle for the changed rule', function () {
    $world = world();
    $world->tick('2026-10-29 10:00:00-03:00', '9.40');

    // Novo alvo, ainda batido: sai agora, sem herdar o teto de 24 h.
    $world->clock->current = new DateTimeImmutable('2026-10-29 10:05:00-03:00');
    $world->updater()->handle('MXRF11', FavoriteSettings::target(Money::fromString('9.45'), false));

    expect($world->alertRules())->toHaveCount(2)
        ->and($world->alerts->alerts[1]->reference)->toBe('alvo R$ 9,45');
});

it('does not touch the cycle of the rule that did not change', function () {
    $world = world(FavoriteSettings::target(Money::fromString('9.50'), true));
    $world->tick('2026-10-29 10:00:00-03:00', '9.40', '-4');
    expect($world->alertRules())->toHaveCount(2);

    $world->updater()->handle('MXRF11', FavoriteSettings::target(Money::fromString('9.45'), true));

    expect($world->alertRules())->toBe(['price_target', 'sharp_drop', 'price_target']);
});

it('saving the same settings changes nothing', function () {
    $world = world();
    $world->tick('2026-10-29 10:00:00-03:00', '9.40');

    $world->updater()->handle('MXRF11', FavoriteSettings::target(Money::fromString('9.5'), false));

    expect($world->alertRules())->toHaveCount(1);
});

it('saves the settings without alerting when there is no quote yet', function () {
    $world = world();

    $world->updater()->handle('MXRF11', FavoriteSettings::target(Money::fromString('9.45'), false));

    expect($world->alertRules())->toBe([])
        ->and($world->favorites->settings('MXRF11')->rules())->toHaveKey('price_target');
});

it('does not reevaluate the same quote after a settings change', function () {
    $world = world();
    $world->quote('2026-10-29 10:00:00-03:00', '9.40');
    $world->updater()->handle('MXRF11', FavoriteSettings::target(Money::fromString('9.45'), false));
    expect($world->alertRules())->toHaveCount(1);

    $world->evaluator()->handle();

    expect($world->alertRules())->toHaveCount(1);
});

it('keeps evaluating the others when one favorite blows up, and logs it', function () {
    $world = world();
    $world->favorites->add('AAAA11');
    $world->favorites->saveSettings('AAAA11', FavoriteSettings::target(Money::fromString('9.50'), false));
    $world->quote('2026-10-29 14:00:00-03:00', '9.42');
    $world->market->quotes['AAAA11'] = new Quote('AAAA11', Money::fromString('9.42'), null, null, new DateTimeImmutable('2026-10-29 14:00:00-03:00'));
    $market = new class($world->market) implements MarketData
    {
        public function __construct(private MarketData $inner) {}

        public function latestQuote(string $ticker): ?Quote
        {
            return $ticker === 'AAAA11' ? throw new RuntimeException('coluna quebrada') : $this->inner->latestQuote($ticker);
        }

        public function saveQuote(Quote $quote): void {}

        public function deleteQuote(string $ticker): void {}

        public function saveCloses(string $ticker, array $closes): void {}

        public function closesBefore(string $ticker, string $day, string $fromDay): array
        {
            return [];
        }
    };
    $logger = new class extends AbstractLogger
    {
        public array $messages = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->messages[] = (string) $message;
        }
    };

    (new EvaluateFavorites($world->favorites, $market, $world->alerts, new BuildFundSnapshot($market, new InMemoryReports), $world->clock, $logger))->handle();

    expect($world->alertRules())->toBe(['price_target'])
        ->and($logger->messages[0])->toContain('AAAA11');
});
