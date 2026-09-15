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
// `ability:mcp:use` menjaga arah sebaliknya dari `[BL-112]`: token yang dibuat
// untuk MCP hanya berlaku di sini, dan rute API lain menolaknya.
Mcp::web('/mcp/business', SapiBusinessServer::class)
    ->middleware(['auth:sanctum', 'ability:mcp:use', 'tenant.api', 'feature.api:ai', 'role:owner', 'throttle:mcp']);
