<?php

namespace App\Modules\Fiis\Application;

use DomainException;

/** Entrada recusada, com uma mensagem em português por campo. */
final class InvalidInput extends DomainException
{
    public const NOT_IN_CATALOG = 'Esse fundo não está no catálogo. Confira o código na B3.';

    /** @param array<string, string> $errors campo => mensagem */
    public function __construct(public array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
