<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ProductCatalogService;
use Illuminate\Http\JsonResponse;

class ApiProductController extends Controller
{
    /**
     * Ambil semua produk aktif milik tenant yang login,
     * beserta variant yang masih punya stok.
     * Dipakai oleh n8n untuk context AI parsing order.
     */
    public function index(ProductCatalogService $catalog): JsonResponse
    {
        return response()->json([
            'data' => $catalog->activeMenu(inStockOnly: true),
        ]);
    }
}
