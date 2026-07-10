<?php

use App\Mcp\Servers\SapiBusinessServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers — SAPI
|--------------------------------------------------------------------------
| Server data bisnis untuk AI client milik owner (mis. Claude Desktop).
| Auth via bearer token Sanctum; hanya owner; rate limited (anti-abuse).
*/

Mcp::web('/mcp/business', SapiBusinessServer::class)
    ->middleware(['auth:sanctum', 'tenant.api', 'role:owner', 'throttle:mcp']);
