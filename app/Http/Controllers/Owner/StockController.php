<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestockRequest;
use App\Http\Requests\AdjustStockRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Halaman manajemen stok — list semua variant + stok saat ini.
     */
    public function index(): Response
    {
        $products = Product::with([
            'variants' => fn($q) => $q->select('id', 'product_id', 'name', 'sku', 'stock', 'expiry_date'),
            'category:id,name',
        ])->get();

        return Inertia::render('Owner/Stock/Index', [
            'products' => $products,
        ]);
    }

    /**
     * Restock — tambah stok variant.
     */
    public function restock(RestockRequest $request, ProductVariant $variant): RedirectResponse
    {
        $this->authorizeVariant($variant);

        try {
            $this->stockService->restock(
                variant: $variant,
                qty: $request->validated('qty'),
                notes: $request->validated('notes'),
                expiryDate: $request->validated('expiry_date'),
            );

            return back()->with('success', "Restock {$variant->name}: +{$request->qty} berhasil.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Adjustment — koreksi stok manual.
     */
    public function adjust(AdjustStockRequest $request, ProductVariant $variant): RedirectResponse
    {
        $this->authorizeVariant($variant);

        try {
            $this->stockService->adjust(
                variant: $variant,
                qty: $request->validated('qty'),
                notes: $request->validated('notes'),
            );

            $direction = $request->qty > 0 ? "+{$request->qty}" : "{$request->qty}";
            return back()->with('success', "Adjustment {$variant->name}: {$direction} berhasil.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Riwayat stock movement per variant.
     */
    public function history(ProductVariant $variant): Response
    {
        $this->authorizeVariant($variant);

        $movements = StockMovement::where('product_variant_id', $variant->id)
            ->latest('created_at')
            ->paginate(50);

        $variant->load('product:id,name');

        return Inertia::render('Owner/Stock/History', [
            'variant' => $variant,
            'movements' => $movements,
        ]);
    }

    /**
     * Semua stock movements (global tenant) — filterable.
     */
    public function movements(Request $request): Response
    {
        $query = StockMovement::with(['variant.product:id,name']);

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by product
        if ($request->filled('product_id')) {
            $query->whereHas('variant', fn($q) => $q->where('product_id', $request->product_id));
        }

        $movements = $query->latest('created_at')->paginate(50)->withQueryString();

        $products = Product::select('id', 'name')->get();

        return Inertia::render('Owner/Stock/Movements', [
            'movements' => $movements,
            'products' => $products,
            'filters' => $request->only(['type', 'date_from', 'date_to', 'product_id']),
        ]);
    }

    private function authorizeVariant(ProductVariant $variant): void
    {
        $variant->loadMissing('product');
        if ($variant->product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
    }
}
