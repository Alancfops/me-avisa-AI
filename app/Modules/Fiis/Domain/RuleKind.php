<?php

namespace App\Modules\Fiis\Domain;

enum RuleKind: string
{
    case PriceTarget = 'price_target';
    case BuyRange = 'buy_range';
    case Automatic = 'automatic';
    case SharpDrop = 'sharp_drop';

    /** Cada pregão é um evento novo só na queda forte (docs/fiis/alert.md, seção 4). */
    public function isNewEventEachTradingDay(): bool
    {
        return $this === self::SharpDrop;
    }
}
