<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\FundListItem;
use App\Modules\Fiis\Application\ListFunds;
use Inertia\Inertia;
use Inertia\Response;

final class FundListController
{
    public function __invoke(ListFunds $listFunds): Response
    {
        $items = $listFunds->handle();

        return Inertia::render('funds/index', [
            'funds' => array_map(fn (FundListItem $item): array => [
                'ticker' => $item->ticker,
                'name' => $item->name,
                'kind' => $item->kind?->value,
                'segment' => $item->segment,
                'close' => $item->close?->toDecimal(),
                'closeDay' => $item->closeDay,
                'priceToBook' => $item->priceToBook?->toDecimal(),
                'dividendYield12m' => $item->dividendYield12m,
                'netAssets' => $item->netAssets,
                'favorite' => $item->favorite,
            ], $items),
            // Os fechamentos vêm de arquivos diários: o dia mais recente diz quão fresca é a lista.
            'closesOn' => $items === [] ? null : max(array_map(fn (FundListItem $item): ?string => $item->closeDay, $items)),
        ]);
    }
}
