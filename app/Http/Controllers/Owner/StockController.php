<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\RestockRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    /**
     * Batas "kritis". Angkanya sama dengan yang dipakai lencana beranda
     * (`BadgeHelperService`) — dua tempat yang menyebut varian yang sama
     * "kritis" harus memakai batas yang sama, kalau tidak pemilik melihat
     * satu angka di beranda dan angka lain di halaman ini.
     */
    private const LOW_STOCK_THRESHOLD = 5;

    /** Rentang "mendekati kedaluwarsa", dalam hari. */
    private const NEAR_EXPIRY_DAYS = 7;

    /** @var list<string> */
    private const STATUSES = ['out', 'low', 'near_expiry', 'expired', 'ok'];

    /** @var list<string> */
    private const SORTS = ['urgency', 'product', 'variant', 'stock', 'expiry'];

    /** @var list<int> */
    private const PER_PAGE_OPTIONS = [10, 20, 25, 30, 50];

    /** Jumlah baris bawaan; harus salah satu dari PER_PAGE_OPTIONS. */
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Halaman manajemen stok — satu baris per varian.
     *
     * Penyaringan, pengurutan, dan paginasinya di server. Bentuk sebelumnya
     * mengirim seluruh katalog beserta variannya dalam satu prop lalu membuka
     * semua accordion-nya sekaligus: pada toko dengan ribuan varian itu satu
     * muatan besar yang seluruhnya dirender, padahal yang dicari pemilik
     * hampir selalu segelintir baris yang perlu ditindak.
     */
    public function index(Request $request): Response
    {
        $filters = $this->resolveFilters($request);

        return Inertia::render('Owner/Stock/Index', [
            'filters' => $filters,
            'categories' => Category::select('id', 'name')->orderBy('name')->get(),
            // Ditunda ([BL-037]) dalam SATU grup: kartu ringkasan dan tabelnya
            // adalah satu jawaban atas satu penyaringan. Dipisah ke dua grup,
            // keduanya berjalan paralel lalu tiba pada saat berbeda — angka
            // kartunya sempat menyebut kumpulan baris yang belum terlihat.
            'variants' => Inertia::defer(fn () => $this->paginateVariants($filters), 'stock'),
            'summary' => Inertia::defer(fn () => $this->summarize($filters), 'stock'),
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

    /**
     * Query string jadi penyaring yang sudah dibersihkan.
     *
     * Nilai di luar daftar yang dikenal dijatuhkan ke bawaannya, bukan
     * diteruskan: `sort` dan `dir` ikut menyusun SQL, dan `per_page` yang
     * bebas diisi adalah undangan untuk meminta sejuta baris sekaligus.
     *
     * @return array{q: string, category: string, status: string, sort: string, dir: string, per_page: int}
     */
    private function resolveFilters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'urgency');
        $status = (string) $request->query('status', '');
        $category = (string) $request->query('category', '');
        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        return [
            'q' => trim((string) $request->query('q', '')),
            'category' => $category === 'none' || ctype_digit($category) ? $category : '',
            'status' => in_array($status, self::STATUSES, true) ? $status : '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'urgency',
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE,
        ];
    }

    /**
     * Varian tenant ini yang lolos pencarian dan kategori — tanpa status.
     *
     * Statusnya sengaja tidak ikut: builder inilah yang dipakai ulang untuk
     * menghitung isi tiap kartu ringkasan, dan kartu yang sudah tersaring
     * oleh dirinya sendiri selalu menunjukkan angka yang sama.
     *
     * @param  array{q: string, category: string}  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        // Lewat `whereHas('product')`, jadi TenantScope pada Product yang
        // menjaga batas tenantnya — ProductVariant tidak punya tenant_id.
        $query = ProductVariant::query()
            ->whereHas('product', function (Builder $q) use ($filters) {
                if ($filters['category'] === 'none') {
                    $q->whereNull('category_id');
                } elseif ($filters['category'] !== '') {
                    $q->where('category_id', $filters['category']);
                }
            });

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';

            $query->where(function (Builder $q) use ($term) {
                $q->where('product_variants.name', 'like', $term)
                    ->orWhere('product_variants.sku', 'like', $term)
                    ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', $term));
            });
        }

        return $query;
    }

    /**
     * Satu ember status.
     *
     * Embernya boleh bertindih — varian yang habis DAN kedaluwarsa terhitung
     * di dua kartu — karena tiap kartu menjawab "berapa baris yang muncul
     * kalau saya menekan ini", bukan membagi katalognya jadi potongan yang
     * saling lepas.
     */
    private function applyStatus(Builder $query, string $status): Builder
    {
        // Aplikasinya berjalan di zona bisnisnya (`config('app.timezone')`),
        // jadi `today()` di sini adalah HARI TOKO — batas yang sama dengan
        // yang dipakai halamannya ([BL-082]).
        $today = today();
        $nearLimit = $today->copy()->addDays(self::NEAR_EXPIRY_DAYS);

        return match ($status) {
            'out' => $query->where('stock', '<=', 0),
            'low' => $query->where('stock', '>', 0)->where('stock', '<=', self::LOW_STOCK_THRESHOLD),
            'expired' => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today),
            'near_expiry' => $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $nearLimit),
            'ok' => $query->where('stock', '>', self::LOW_STOCK_THRESHOLD)
                ->where(fn (Builder $q) => $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>', $nearLimit)),
            default => $query,
        };
    }

    /**
     * Urutan baris.
     *
     * Bawaannya `urgency`, bukan abjad: halaman ini dibuka untuk mengurus
     * stok yang bermasalah, dan pada katalog besar baris yang bermasalah itu
     * tidak akan pernah ditemukan kalau ia jatuh di halaman 14.
     */
    private function applySort(Builder $query, string $sort, string $dir): Builder
    {
        $productName = Product::select('name')->whereColumn('products.id', 'product_variants.product_id');

        return match ($sort) {
            'product' => $query->orderBy($productName, $dir)->orderBy('product_variants.name'),
            'variant' => $query->orderBy('product_variants.name', $dir),
            'stock' => $query->orderBy('stock', $dir)->orderBy($productName),
            // Varian tanpa tanggal kedaluwarsa selalu di bawah, ke arah mana
            // pun urutannya: "tidak punya tanggal" bukan tanggal paling awal.
            'expiry' => $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date', $dir)
                ->orderBy($productName),
            default => $query
                ->orderByRaw('CASE WHEN stock <= 0 THEN 0 WHEN stock <= ? THEN 1 ELSE 2 END', [self::LOW_STOCK_THRESHOLD])
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')
                ->orderBy($productName),
        };
    }

    /**
     * Satu halaman baris varian, sudah rata: nama produk dan kategorinya ikut
     * di barisnya sendiri, bukan sebagai objek bersarang.
     *
     * @param  array{q: string, category: string, status: string, sort: string, dir: string, per_page: int}  $filters
     */
    private function paginateVariants(array $filters): LengthAwarePaginator
    {
        $query = $this->applyStatus($this->baseQuery($filters), $filters['status'])
            ->select('id', 'product_id', 'name', 'sku', 'stock', 'expiry_date')
            ->with(['product:id,name,category_id', 'product.category:id,name']);

        return $this->applySort($query, $filters['sort'], $filters['dir'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'stock' => $variant->stock,
                'expiry_date' => $variant->expiry_date?->toDateString(),
                'product_id' => $variant->product_id,
                'product_name' => $variant->product->name,
                'category_name' => $variant->product->category?->name,
            ]);
    }

    /**
     * Isi tiap kartu ringkasan, dihitung atas pencarian dan kategori yang
     * sedang berlaku — supaya kartunya menyaring DI DALAM apa yang sedang
     * dilihat, bukan melompat balik ke seluruh katalog.
     *
     * @param  array{q: string, category: string}  $filters
     * @return array<string, int>
     */
    private function summarize(array $filters): array
    {
        $counts = ['total' => $this->baseQuery($filters)->count()];

        foreach (self::STATUSES as $status) {
            $counts[$status] = $this->applyStatus($this->baseQuery($filters), $status)->count();
        }

        return $counts;
    }

    private function authorizeVariant(ProductVariant $variant): void
    {
        $variant->loadMissing('product');
        if ($variant->product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
    }
}
