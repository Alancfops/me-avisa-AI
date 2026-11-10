<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\AlertMode;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\PriceLock;

/** Troca o alerta de um favorito, travando alvo ou faixa longe do preço até o usuário confirmar. */
final readonly class ChangeAlert
{
    public function __construct(
        private FavoriteRepository $favorites,
        private MarketData $market,
        private UpdateFavoriteSettings $update,
    ) {}

    public function handle(string $ticker, FavoriteSettings $settings, bool $confirmed): AlertChange
    {
        $before = $this->favorites->settings($ticker);
        $quote = $this->market->latestQuote($ticker);
        $close = $quote === null ? $this->market->latestClose($ticker) : null;
        $price = $quote->price ?? $close?->close;

        $change = fn (AlertChangeStatus $status, array $exceeded = [], bool $triggersNow = false): AlertChange => new AlertChange(
            $status, $ticker, $before, $settings, $price, $quote, $close, $exceeded, $triggersNow, $price === null,
        );

        if ($before === null) {
            return $change(AlertChangeStatus::NotFavorite);
        }

        if ($before->changedRules($settings) === []) {
            return $change(AlertChangeStatus::Unchanged);
        }

        $exceeded = $price === null ? [] : $this->exceededByChange($before, $settings, $price);

        if ($exceeded !== [] && ! $confirmed) {
            return $change(AlertChangeStatus::NeedsConfirmation, $exceeded, $this->triggersNow($ticker, $settings, $price));
        }

        $this->update->handle($ticker, $settings);

        return $change(AlertChangeStatus::Saved, $exceeded, $this->triggersNow($ticker, $settings, $price));
    }

    /**
     * A trava olha só o que a pessoa mexeu: um valor igual ao já salvo foi aceito antes,
     * mesmo que o preço tenha andado desde então.
     *
     * @return array<string, Percentage>
     */
    private function exceededByChange(FavoriteSettings $before, FavoriteSettings $settings, Money $price): array
    {
        $saved = [
            'target' => $before->target?->toDecimal(),
            'rangeMin' => $before->rangeMin?->toDecimal(),
            'rangeMax' => $before->rangeMax?->toDecimal(),
        ];
        $new = [
            'target' => $settings->target?->toDecimal(),
            'rangeMin' => $settings->rangeMin?->toDecimal(),
            'rangeMax' => $settings->rangeMax?->toDecimal(),
        ];

        return array_filter(
            PriceLock::exceeded($settings, $price),
            fn (string $field): bool => $new[$field] !== $saved[$field],
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** Só alvo e faixa: o modo automático e a queda forte dependem de histórico que aqui não há. */
    private function triggersNow(string $ticker, FavoriteSettings $settings, ?Money $price): bool
    {
        if ($price === null || $settings->mode === AlertMode::Automatic) {
            return false;
        }

        $reference = array_values($settings->rules())[0];

        return $reference->evaluate(new FundSnapshot($ticker, $price))->matched;
    }
}
