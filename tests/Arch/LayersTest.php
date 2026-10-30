<?php

arch('domain has no framework')
    ->expect('App\Modules\Fiis\Domain')
    ->not->toUse(['Illuminate', 'Laravel', 'App\Modules\Fiis\Application', 'App\Modules\Fiis\Infrastructure', 'App\Modules\Fiis\Http']);

arch('application does not know outer layers')
    ->expect('App\Modules\Fiis\Application')
    ->not->toUse(['Illuminate', 'Laravel', 'App\Modules\Fiis\Infrastructure', 'App\Modules\Fiis\Http']);

arch('http does not know infrastructure')
    ->expect('App\Modules\Fiis\Http')
    ->not->toUse('App\Modules\Fiis\Infrastructure');
