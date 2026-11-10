<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\MonthlyDividend;
use App\Modules\Fiis\Domain\Percentage;

/** O favorito como o MCP o conta: a visão da tela mais variação, último rendimento e avisos de hoje. */
final readonly class FavoriteSummary
{
    /**
     * @param  Percentage|null  $change7d  `null` sem preço atual ou sem fechamento que cubra o período
     * @param  MonthlyDividend|null  $lastDividend  estimativa (VP × rendimento do informe), não o valor pago
     * @param  list<Alert>  $alertsToday  avisos do dia de São Paulo, do mais novo ao mais velho
     */
    public function __construct(
        public FavoriteView $view,
        public ?Percentage $change7d,
        public ?Percentage $change30d,
        public ?MonthlyDividend $lastDividend,
        public array $alertsToday,
    ) {}
}
