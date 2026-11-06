<?php

namespace App\Modules\Fiis\Domain;

enum AlertMode: string
{
    case Automatic = 'automatic';
    case Target = 'target';
    case Range = 'range';
}
