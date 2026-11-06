<?php

namespace App\Modules\Fiis\Application;

/**
 * Entrega um aviso já gravado para fora do site (Telegram). O módulo de FIIs não
 * conhece quem implementa: o padrão é não fazer nada.
 */
interface AlertNotifier
{
    public function notify(Alert $alert): void;
}
