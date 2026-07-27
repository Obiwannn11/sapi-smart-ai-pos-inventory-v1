<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Upsell\UpsellIndexBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saran upsell untuk jalur self-order.
 *
 * Permukaan ini justru lebih mudah daripada POS: tak ada kasir yang harus
 * mengucapkan tawaran, tak ada antrean yang melambat, dan penerimaan/penolakan
 * terekam dengan sendirinya lewat `upsell_events` di POST /orders.
 *
 * Berbeda dari POS, di sini keranjangnya dikirim langsung — jadi yang
 * dikembalikan sudah berupa daftar datar terurut, bukan indeks penuh.
 */
class ApiUpsellController extends Controller
{
    public function __construct(
        private UpsellIndexBuilder $upsellIndexBuilder
    ) {}

    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_ids' => 'required|array|min:1|max:50',
            'variant_ids.*' => 'required|integer',
        ]);

        $tenant = $request->user()?->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 403);
        }

        $variantIds = array_map('intval', $validated['variant_ids']);

        return response()->json([
            'success' => true,
            'suggestions' => $this->upsellIndexBuilder->forCart($tenant, $variantIds),
        ]);
    }
}
