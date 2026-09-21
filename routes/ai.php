<?php

use App\Mcp\Servers\TaskBoardServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| The Task Board server is exposed over Streamable HTTP and authenticated
| with the same Sanctum bearer tokens as the REST API (POST /api/tokens),
| so an agent always acts as one user inside one workspace.
|
| It is deliberately not registered as a local (stdio) server: without an
| authenticated user there is no tenant to scope to.
|
*/

Mcp::web('/mcp', TaskBoardServer::class)->middleware('auth:sanctum');
