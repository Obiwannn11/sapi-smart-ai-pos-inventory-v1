<?php

namespace App\Mcp\Tools;

use App\Models\Tenant;
use App\Services\ProductCatalogService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Daftar menu tenant: produk aktif beserta varian (nama, harga, sisa stok) dan kategorinya.')]
class GetMenuTool extends BusinessDataTool
{
    public function __construct(private ProductCatalogService $catalog) {}

    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request, Tenant $tenant): array
    {
        return [
            'menu' => $this->catalog->activeMenu(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
