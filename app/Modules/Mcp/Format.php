<?php

namespace App\Modules\Mcp;

use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\AlertMode;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\RuleKind;
use DateTimeImmutable;

/** Textos em português das respostas das ferramentas. Dado ausente nunca vira zero. */
final class Format
{
    private const NO_DATA = 'sem dado';

    public static function money(?Money $money): string
    {
        return $money === null ? self::NO_DATA : (string) $money;
    }

    public static function percent(?Percentage $percentage): string
    {
        if ($percentage === null) {
            return self::NO_DATA;
        }

        $magnitude = $percentage->magnitude();

        // "-0,001" arredonda para 0: sem sinal, para não sugerir variação.
        if ($magnitude === '0%') {
            return $magnitude;
        }

        return (str_starts_with($percentage->toDecimal(), '-') ? '-' : '+').$magnitude;
    }

    public static function moment(DateTimeImmutable $moment): string
    {
        return MarketTime::local($moment)->format('d/m H:i');
    }

    public static function day(string $ymd): string
    {
        return (new DateTimeImmutable($ymd))->format('d/m/Y');
    }

    public static function priceSource(?Quote $quote, ?DailyClose $close): string
    {
        return match (true) {
            $quote !== null => 'cotação da brapi, '.self::moment($quote->quotedAt),
            $close !== null => 'fechamento de '.self::day($close->date),
            default => 'sem preço',
        };
    }

    public static function settings(FavoriteSettings $settings): string
    {
        $reference = match ($settings->mode) {
            AlertMode::Automatic => 'automático',
            AlertMode::Target => 'preço alvo '.self::money($settings->target),
            AlertMode::Range => 'faixa de compra '.self::money($settings->rangeMin).' a '.self::money($settings->rangeMax),
        };

        if ($settings->sharpDrop) {
            return "{$reference} + queda forte";
        }

        return "{$reference}, sem queda forte";
    }

    public static function rule(RuleKind $kind): string
    {
        return match ($kind) {
            RuleKind::PriceTarget => 'preço alvo',
            RuleKind::BuyRange => 'faixa de compra',
            RuleKind::Automatic => 'automática',
            RuleKind::SharpDrop => 'queda forte',
        };
    }

    public static function ticker(string $ticker): string
    {
        return strtoupper(trim($ticker));
    }
}
