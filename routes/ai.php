<?php

use App\Modules\Mcp\FiisServer;
use Laravel\Mcp\Facades\Mcp;

// No stdio a saída padrão é só do protocolo: qualquer aviso do PHP vai para o stderr.
ini_set('display_errors', 'stderr');

// Só transporte local (stdio): sem rota web, o app não abre porta para o MCP.
Mcp::local('fiis', FiisServer::class);
