<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\RestockRequest;
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
        return Inertia::render('Owner/Stock/Index', [
            // Ditunda ([BL-037]): seluruh katalog beserta varian dan stoknya
            // adalah satu-satunya isi halaman ini, dan tidak ada tindakan yang
            // bisa dimulai sebelum barisnya terlihat — jadi yang dijaga di sini
            // bukan interaksi, melainkan bentuk halaman yang muncul lebih awal.
            'products' => Inertia::defer(fn () => Product::with([
                'variants' => fn ($q) => $q->select('id', 'product_id', 'name', 'sku', 'stock', 'expiry_date'),
                'category:id,name',
            ])->get()),
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

        $variant->load('product:id,name');

        return Inertia::render('Owner/Stock/History', [
            // Varian dan stok berjalannya tetap eager: itulah judul halaman
            // ini. Riwayat mutasinya ditunda ([BL-037]) — 50 baris yang hanya
            // dibaca, dan tidak dipakai untuk apa pun di kepala halaman.
            'variant' => $variant,
            'movements' => Inertia::defer(fn () => StockMovement::where('product_variant_id', $variant->id)
                ->latest('created_at')
                ->paginate(50)),
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
            $query->whereHas('variant', fn ($q) => $q->where('product_id', $request->product_id));
        }

        return Inertia::render('Owner/Stock/Movements', [
            // Ditunda ([BL-037]): daftar mutasi bisa panjang dan tiap barisnya
            // menarik varian beserta produknya. Daftar produk untuk penyaring
            // tetap eager supaya filternya bisa dipakai sambil menunggu.
            'movements' => Inertia::defer(fn () => $query->latest('created_at')->paginate(50)->withQueryString()),
            'products' => Product::select('id', 'name')->get(),
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
