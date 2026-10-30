<?php

namespace App\Modules\Fiis\Domain;

use InvalidArgumentException;

final readonly class FavoriteSettings
{
    private function __construct(
        public AlertMode $mode,
        public ?Money $target,
        public ?Money $rangeMin,
        public ?Money $rangeMax,
        public bool $sharpDrop,
    ) {}

    /** Favorito novo: modo automático, com queda forte ligada. */
    public static function default(): self
    {
        return self::automatic(true);
    }

    public static function automatic(bool $sharpDrop): self
    {
        return new self(AlertMode::Automatic, null, null, null, $sharpDrop);
    }

    public static function target(Money $target, bool $sharpDrop): self
    {
        return new self(AlertMode::Target, $target, null, null, $sharpDrop);
    }

    public static function range(Money $min, Money $max, bool $sharpDrop): self
    {
        if (! $min->isAtMost($max)) {
            throw new InvalidArgumentException("Faixa invertida: {$min} a {$max}");
        }

        return new self(AlertMode::Range, null, $min, $max, $sharpDrop);
    }

    /** @return array<string, AlertRule> regras em vigor, pelo valor de RuleKind */
    public function rules(): array
    {
        $rules = [$this->referenceKind()->value => $this->referenceRule()];

        if ($this->sharpDrop) {
            $rules[RuleKind::SharpDrop->value] = new SharpDropRule;
        }

        return $rules;
    }

    /**
     * Regras que começam um ciclo novo ao trocar para `$other`: as que entraram, as
     * que mudaram de valor e as que saíram.
     *
     * @return list<RuleKind>
     */
    public function changedRules(self $other): array
    {
        $before = $this->signatures();
        $after = $other->signatures();
        $changed = [];

        foreach ($after as $kind => $signature) {
            if (($before[$kind] ?? null) !== $signature) {
                $changed[] = RuleKind::from($kind);
            }
        }

        foreach (array_keys($before) as $kind) {
            if (! array_key_exists($kind, $after)) {
                $changed[] = RuleKind::from($kind);
            }
        }

        return $changed;
    }

    private function referenceKind(): RuleKind
    {
        return match ($this->mode) {
            AlertMode::Automatic => RuleKind::Automatic,
            AlertMode::Target => RuleKind::PriceTarget,
            AlertMode::Range => RuleKind::BuyRange,
        };
    }

    private function referenceRule(): AlertRule
    {
        return match (true) {
            $this->target !== null => new PriceTargetRule($this->target),
            $this->rangeMin !== null && $this->rangeMax !== null => new BuyRangeRule($this->rangeMin, $this->rangeMax),
            default => new AutomaticRule,
        };
    }

    /** @return array<string, string> */
    private function signatures(): array
    {
        $signatures = [
            $this->referenceKind()->value => implode('|', [
                $this->target?->toDecimal(),
                $this->rangeMin?->toDecimal(),
                $this->rangeMax?->toDecimal(),
            ]),
        ];

        if ($this->sharpDrop) {
            $signatures[RuleKind::SharpDrop->value] = 'on';
        }

        return $signatures;
    }
}
