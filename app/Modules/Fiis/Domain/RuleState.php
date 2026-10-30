<?php

namespace App\Modules\Fiis\Domain;

final readonly class RuleState
{
    public function __construct(
        /** A regra valia na avaliação anterior. */
        public bool $active,
        /** Passou a valer, mas o teto de 24 h segurou o aviso. */
        public bool $pending,
        /** Pregão do evento em curso, nas regras em que cada pregão é um evento. */
        public ?string $eventKey,
        public ?Money $lastAlertPrice,
        /** Em segundos corridos, para horário de verão e fuso não mexerem no teto. */
        public ?int $lastAlertAt,
    ) {}

    public static function fresh(): self
    {
        return new self(false, false, null, null, null);
    }
}
