<?php

namespace App\Modules\Fiis\Application;

enum Source: string
{
    case Brapi = 'brapi';
    case Cvm = 'cvm';
    case B3 = 'b3';
    /** Arquivos anuais da primeira carga: falha aqui não pode sumir sob o sucesso do arquivo diário. */
    case B3History = 'b3_history';
}
