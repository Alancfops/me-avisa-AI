<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\DescribeFund;
use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\FavoriteView;
use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Fiis\Application\RuleStatus;
use App\Modules\Fiis\Application\UpdateFavoriteSettings;
use App\Modules\Fiis\Http\Requests\UpdateFavoriteSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class FundController
{
    public function show(string $ticker, DescribeFund $describe): Response
    {
        $view = $describe->handle(strtoupper($ticker));
        abort_if($view === null, 404);

        return Inertia::render('funds/show', [
            'fund' => self::fundProps($view),
            'settings' => [
                'mode' => $view->settings->mode->value,
                'target' => $view->settings->target?->toDecimal(),
                'rangeMin' => $view->settings->rangeMin?->toDecimal(),
                'rangeMax' => $view->settings->rangeMax?->toDecimal(),
                'sharpDrop' => $view->settings->sharpDrop,
            ],
            'rules' => array_map(fn (RuleStatus $status): array => [
                'rule' => $status->rule->value,
                'matched' => $status->result->matched,
                'reason' => $status->result->reason,
                'unavailableReason' => $status->result->unavailableReason,
                'note' => $status->result->note,
            ], $view->rules),
        ]);
    }

    public function update(
        string $ticker,
        UpdateFavoriteSettingsRequest $request,
        FavoriteRepository $favorites,
        UpdateFavoriteSettings $update,
    ): RedirectResponse {
        $ticker = strtoupper($ticker);
        abort_unless($favorites->exists($ticker), 404);

        $update->handle($ticker, $request->settings());

        return redirect()->route('funds.show', $ticker);
    }

    /** @return array<string, mixed> */
    public static function fundProps(FavoriteView $view): array
    {
        $matched = array_values(array_filter($view->rules, fn (RuleStatus $status): bool => $status->result->matched));

        return [
            'ticker' => $view->ticker,
            'name' => $view->name,
            'price' => $view->quote?->price->toDecimal(),
            'changePercent' => $view->quote?->changePercent?->toDecimal(),
            'quotedAt' => $view->quote === null ? null : MarketTime::local($view->quote->quotedAt)->format(DATE_ATOM),
            'average30d' => $view->snapshot?->average30d?->toDecimal(),
            'priceToBook' => $view->snapshot?->priceToBook?->toDecimal(),
            'medianPriceToBook' => $view->snapshot?->medianPriceToBook?->toDecimal(),
            'priceToBookDays' => $view->snapshot === null ? 0 : $view->snapshot->priceToBookDays,
            'bookValueMonth' => $view->snapshot?->bookValueMonth,
            'dividendYield12m' => $view->dividendYield12m,
            'matchedRules' => array_map(fn (RuleStatus $status): string => $status->rule->value, $matched),
        ];
    }
}
