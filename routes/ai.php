<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', App\Mcp\Servers\McpServer::class)
    ->middleware(['toggle:mcp', 'auth:sanctum', 'ability:mcp']);
