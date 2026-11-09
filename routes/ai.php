<?php

use App\Modules\Mcp\FiisServer;
use Laravel\Mcp\Facades\Mcp;

// Este arquivo carrega em todo boot (site e bot). Só no `mcp:start` a saída padrão é do
// protocolo, e aí qualquer aviso do PHP vai para o stderr.
if (in_array('mcp:start', $_SERVER['argv'] ?? [], true)) {
    ini_set('display_errors', 'stderr');
}

// Só transporte local (stdio): sem rota web, o app não abre porta para o MCP.
Mcp::local('fiis', FiisServer::class);
