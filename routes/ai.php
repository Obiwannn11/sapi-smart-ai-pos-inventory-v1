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

// `feature.api:ai` ditaruh sebelum `role:owner` supaya sebab penolakannya
// benar: "fitur tidak aktif" mengarahkan owner ke Settings, bukan membuatnya
// mengira akunnya kehilangan wewenang. Gerbang di tingkat server sudah cukup —
// ketiga tool membaca agregat yang sama dan tak ada yang butuh flag berbeda.
Mcp::web('/mcp/business', SapiBusinessServer::class)
    ->middleware(['auth:sanctum', 'tenant.api', 'feature.api:ai', 'role:owner', 'throttle:mcp']);
