<?php

namespace App\Modules\Mcp;

use App\Modules\Mcp\Connect\ConnectCommand;
use Illuminate\Support\ServiceProvider;

final class McpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ConnectCommand::class]);
        }
    }
}
