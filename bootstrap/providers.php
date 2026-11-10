<?php

use App\Modules\Fiis\FiisServiceProvider;
use App\Modules\Mcp\McpServiceProvider;
use App\Modules\Telegram\TelegramServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    FiisServiceProvider::class,
    TelegramServiceProvider::class,
    McpServiceProvider::class,
];
