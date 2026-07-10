<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetMenuTool;
use App\Mcp\Tools\GetProfitTool;
use App\Mcp\Tools\GetSalesSummaryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('SAPI Business Data')]
#[Version('1.0.0')]
#[Instructions('Menyediakan data agregat penjualan, profit, dan menu untuk satu tenant F&B (bisnis milik owner yang terautentikasi). Semua angka sudah teragregasi tanpa data pelanggan. Gunakan tool untuk menjawab pertanyaan bisnis: get-sales-summary untuk penjualan, get-profit untuk profit & proyeksi, get-menu untuk daftar produk.')]
class SapiBusinessServer extends Server
{
    protected array $tools = [
        GetSalesSummaryTool::class,
        GetProfitTool::class,
        GetMenuTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
