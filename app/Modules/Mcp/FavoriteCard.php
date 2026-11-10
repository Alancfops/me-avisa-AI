<?php

namespace App\Modules\Mcp;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\FavoriteSummary;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\Ratio;
use Brick\Math\BigDecimal;

/** O favorito como as ferramentas o contam: chaves em português, valores já formatados. */
final class FavoriteCard
{
    /** @return array<string, mixed> */
    public static function of(FavoriteSummary $summary): array
    {
        $view = $summary->view;
        $snapshot = $view->snapshot;

        return [
            'codigo' => $view->ticker,
            'nome' => $view->name,
            'preco' => Format::money($view->quote->price ?? $view->close?->close),
            'preco_de' => Format::priceSource($view->quote, $view->close),
            'variacao_dia' => Format::percent($view->quote?->changePercent),
            'variacao_7d' => Format::percent($summary->change7d),
            'variacao_30d' => Format::percent($summary->change30d),
            'pvp' => self::ratio($snapshot?->priceToBook),
            'pvp_mediana_12m' => self::ratio($snapshot?->medianPriceToBook),
            'dy_12m' => self::fraction($view->dividendYield12m),
            'ultimo_rendimento_por_cota' => Format::money($summary->lastDividend?->perShare),
            'alerta' => $view->settings === null ? 'sem dado' : Format::settings($view->settings),
            'avisos_hoje' => array_map(fn (Alert $alert): array => [
                'regra' => Format::rule($alert->rule),
                'motivo' => $alert->reason,
                'horario' => Format::moment($alert->occurredAt),
            ], $summary->alertsToday),
        ];
    }

    public static function ratio(?Ratio $ratio): string
    {
        return $ratio === null ? 'sem dado' : (string) $ratio;
    }

    /** Fração (0.0912) em porcentagem (9,12%). */
    private static function fraction(?string $fraction): string
    {
        if ($fraction === null) {
            return 'sem dado';
        }

        return Percentage::fromString((string) BigDecimal::of($fraction)->multipliedBy(100))->magnitude();
    }
}
