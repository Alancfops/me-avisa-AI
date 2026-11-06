<?php

use App\Modules\Fiis\Domain\AutomaticRule;
use App\Modules\Fiis\Domain\BuyRangeRule;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\PriceTargetRule;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\SharpDropRule;

function money(string $value): Money
{
    return Money::fromString($value);
}

it('starts automatic with sharp drop on', function () {
    $rules = FavoriteSettings::default()->rules();

    expect(array_keys($rules))->toBe(['automatic', 'sharp_drop'])
        ->and($rules['automatic'])->toBeInstanceOf(AutomaticRule::class)
        ->and($rules['sharp_drop'])->toBeInstanceOf(SharpDropRule::class);
});

it('uses one reference rule, plus sharp drop only when on', function () {
    expect(array_keys(FavoriteSettings::target(money('9.50'), false)->rules()))->toBe(['price_target'])
        ->and(FavoriteSettings::target(money('9.50'), false)->rules()['price_target'])->toBeInstanceOf(PriceTargetRule::class)
        ->and(array_keys(FavoriteSettings::range(money('9'), money('9.6'), true)->rules()))->toBe(['buy_range', 'sharp_drop'])
        ->and(FavoriteSettings::range(money('9'), money('9.6'), true)->rules()['buy_range'])->toBeInstanceOf(BuyRangeRule::class);
});

it('rejects an inverted range', function () {
    expect(fn () => FavoriteSettings::range(money('10'), money('9'), true))->toThrow(InvalidArgumentException::class);
});

it('accepts a range of a single price', function () {
    expect(array_keys(FavoriteSettings::range(money('9'), money('9.00'), false)->rules()))->toBe(['buy_range']);
});

it('reports only the rules that really changed', function (FavoriteSettings $from, FavoriteSettings $to, array $changed) {
    expect(array_map(fn (RuleKind $kind) => $kind->value, $from->changedRules($to)))->toBe($changed);
})->with([
    'nada mudou' => [FavoriteSettings::target(money('9.50'), true), FavoriteSettings::target(money('9.5'), true), []],
    'alvo novo' => [FavoriteSettings::target(money('9.50'), true), FavoriteSettings::target(money('9.60'), true), ['price_target']],
    'desliga a queda' => [FavoriteSettings::target(money('9.50'), true), FavoriteSettings::target(money('9.50'), false), ['sharp_drop']],
    'troca de modo' => [FavoriteSettings::default(), FavoriteSettings::target(money('9.50'), true), ['price_target', 'automatic']],
    'muda a faixa' => [FavoriteSettings::range(money('9'), money('10'), true), FavoriteSettings::range(money('9'), money('11'), true), ['buy_range']],
]);
