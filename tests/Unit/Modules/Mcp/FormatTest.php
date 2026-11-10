<?php

use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Mcp\Format;

it('formats money or says there is no data', function () {
    expect(Format::money(Money::fromString('9.5')))->toBe('R$ 9,50')
        ->and(Format::money(null))->toBe('sem dado');
});

it('formats a signed percentage with at most two decimals', function () {
    expect(Format::percent(Percentage::fromString('1.2')))->toBe('+1,2%')
        ->and(Format::percent(Percentage::fromString('-3.6')))->toBe('-3,6%')
        ->and(Format::percent(Percentage::fromString('2.345')))->toBe('+2,35%')
        ->and(Format::percent(Percentage::fromString('5')))->toBe('+5%')
        ->and(Format::percent(Percentage::fromString('0')))->toBe('0%')
        ->and(Format::percent(Percentage::fromString('-0.001')))->toBe('0%')
        ->and(Format::percent(null))->toBe('sem dado');
});

it('formats a moment in Sao Paulo time', function () {
    expect(Format::moment(new DateTimeImmutable('2026-11-06T20:00:00Z')))->toBe('06/11 17:00');
});

it('formats a day', function () {
    expect(Format::day('2026-11-06'))->toBe('06/11/2026');
});

it('says where the price came from', function () {
    $quote = new Quote('MXRF11', Money::fromString('9.62'), null, null, new DateTimeImmutable('2026-11-06T20:00:00Z'));
    $close = new DailyClose('2026-11-06', Money::fromString('9.60'));

    expect(Format::priceSource($quote, $close))->toBe('cotação da brapi, 06/11 17:00')
        ->and(Format::priceSource(null, $close))->toBe('fechamento de 06/11/2026')
        ->and(Format::priceSource(null, null))->toBe('sem preço');
});

it('describes the alert settings', function () {
    $money = fn (string $value): Money => Money::fromString($value);

    expect(Format::settings(FavoriteSettings::automatic(true)))->toBe('automático + queda forte')
        ->and(Format::settings(FavoriteSettings::automatic(false)))->toBe('automático, sem queda forte')
        ->and(Format::settings(FavoriteSettings::target($money('150'), true)))->toBe('preço alvo R$ 150,00 + queda forte')
        ->and(Format::settings(FavoriteSettings::range($money('8'), $money('9'), false)))->toBe('faixa de compra R$ 8,00 a R$ 9,00, sem queda forte');
});

it('names the rules', function () {
    expect(Format::rule(RuleKind::PriceTarget))->toBe('preço alvo')
        ->and(Format::rule(RuleKind::BuyRange))->toBe('faixa de compra')
        ->and(Format::rule(RuleKind::Automatic))->toBe('automática')
        ->and(Format::rule(RuleKind::SharpDrop))->toBe('queda forte');
});

it('normalizes the ticker', function () {
    expect(Format::ticker('  mxrf11 '))->toBe('MXRF11');
});
