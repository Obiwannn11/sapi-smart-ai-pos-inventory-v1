<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ApiProductController extends Controller
{
    /**
     * Ambil semua produk aktif milik tenant yang login,
     * beserta variant yang masih punya stok.
     * Dipakai oleh n8n untuk context AI parsing order.
     */
    public function index(): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->with([
                'variants' => fn($q) => $q
                    ->select('id', 'product_id', 'name', 'price', 'stock')
                    ->where('stock', '>', 0),
                'category:id,name',
            ])
            ->get(['id', 'name', 'category_id']);

        return response()->json([
            'data' => $products,
        ]);
    }
}
