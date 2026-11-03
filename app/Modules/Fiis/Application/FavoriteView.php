<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\MonthlyDividend;
use App\Modules\Fiis\Domain\PricePoint;
use App\Modules\Fiis\Domain\WindowAverage;

final readonly class FavoriteView
{
    /**
     * @param  FavoriteSettings|null  $settings  `null` se o fundo não é favorito
     * @param  DailyClose|null  $close  último fechamento, só para quem não é favorito: sem cotação própria, é o preço mostrado
     * @param  list<RuleStatus>  $rules  situação agora; vazia sem cotação
     * @param  string|null  $dividendYield12m  fração
     * @param  list<PricePoint>  $history  só na página do fundo; vazio na lista
     * @param  list<MonthlyDividend>  $dividends  idem
     */
    public function __construct(
        public string $ticker,
        public ?string $name,
        public ?Quote $quote,
        public ?FavoriteSettings $settings,
        public ?FundSnapshot $snapshot,
        public array $rules,
        public ?string $dividendYield12m,
        public ?FundProfile $profile,
        public WindowAverage $average6m,
        public WindowAverage $average12m,
        public array $history,
        public array $dividends,
        public ?DailyClose $close = null,
    ) {}
}
