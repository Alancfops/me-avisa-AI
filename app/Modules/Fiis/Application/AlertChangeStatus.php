<?php

namespace App\Modules\Fiis\Application;

enum AlertChangeStatus: string
{
    case Saved = 'saved';
    case Unchanged = 'unchanged';
    /** Alvo ou faixa longe do preço: nada foi gravado até o usuário confirmar. */
    case NeedsConfirmation = 'needs_confirmation';
    case NotFavorite = 'not_favorite';
}
