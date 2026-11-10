<?php

namespace App\Modules\Fiis\Domain;

/**
 * Trava contra erro de digitação: alvo ou faixa a mais de 30% do preço atual (para
 * cima ou para baixo) só valem se o usuário confirmar. Os 30% são exclusivos.
 * Origem: docs/superpowers/specs/2026-11-09-servidor-mcp-design.md, "Alertas".
 */
final class PriceLock
{
    public const LIMIT = '30';

    /** @return array<string, Percentage> valor => distância do preço atual, só dos que o modo usa */
    public static function distances(FavoriteSettings $settings, Money $current): array
    {
        $values = array_filter(
            ['target' => $settings->target, 'rangeMin' => $settings->rangeMin, 'rangeMax' => $settings->rangeMax],
            fn (?Money $value): bool => $value !== null,
        );

        return array_map(fn (Money $value): Percentage => Percentage::change($current, $value), $values);
    }

    /** @return array<string, Percentage> só os que passam do limite */
    public static function exceeded(FavoriteSettings $settings, Money $current): array
    {
        $limit = Percentage::fromString(self::LIMIT);
        $negativeLimit = Percentage::fromString('-'.self::LIMIT);

        return array_filter(
            self::distances($settings, $current),
            fn (Percentage $distance): bool => $limit->isBelow($distance) || $distance->isBelow($negativeLimit),
        );
    }
}
