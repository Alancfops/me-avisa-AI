<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\ExchangeImports;
use App\Modules\Fiis\Application\FundListItem;
use App\Modules\Fiis\Application\ListFunds;
use Inertia\Inertia;
use Inertia\Response;

final class FundListController
{
    public function __invoke(ListFunds $listFunds, ExchangeImports $imports): Response
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
            // Os fechamentos vêm de arquivos diários da B3: o último dia lido diz quão fresca é a lista.
            // Não sai dos fechamentos gravados, porque a brapi grava o dia dos favoritos antes da B3.
            'closesOn' => $imports->latestDay(),
        ]);
    }
}
