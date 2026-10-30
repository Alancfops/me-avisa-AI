<?php

namespace App\Modules\Fiis\Domain;

final readonly class RuleResult
{
    private function __construct(
        public bool $matched,
        public ?string $reason,
        /** O que o aviso guarda como referência (alvo, faixa, média, P/VP). */
        public ?string $reference,
        /** Por que a regra não pôde ser avaliada: dado ausente, nunca zero. */
        public ?string $unavailableReason,
        /** Ressalva para a tela, com a regra avaliada ou não. */
        public ?string $note,
    ) {}

    public static function matched(string $reason, string $reference, ?string $note = null): self
    {
        return new self(true, $reason, $reference, null, $note);
    }

    public static function notMatched(?string $note = null): self
    {
        return new self(false, null, null, null, $note);
    }

    public static function unavailable(string $reason): self
    {
        return new self(false, null, null, $reason, null);
    }
}
