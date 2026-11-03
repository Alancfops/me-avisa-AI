<?php

namespace App\Modules\Fiis\Domain;

enum FundKind: string
{
    case Brick = 'tijolo';
    case Paper = 'papel';
    case FundOfFunds = 'fundo_de_fundos';
    case Hybrid = 'hibrido';
}
