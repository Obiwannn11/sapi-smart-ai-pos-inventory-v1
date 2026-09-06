<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayOpenBillRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\SyncOfflineTransactionsRequest;
use App\Models\CashDrawer;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\DiscountService;
use App\Services\TransactionService;
use App\Services\Upsell\UpsellIndexBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class POSController extends Controller
{
    public function __construct(
        private TransactionService $transactionService,
        private UpsellIndexBuilder $upsellIndexBuilder,
        private DiscountService $discounts,
    ) {}

    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();
        $userId = $user->id;

        // Cek apakah kasir sudah buka kas.
        // Hanya kasir yang wajib membuka sesi kas; owner boleh langsung masuk POS.
        $openDrawer = CashDrawer::where('user_id', $userId)
            ->whereNull('closed_at')
            ->first();

        if (! $openDrawer && $user->isCashier()) {
            return redirect()->route('cashier.cash-drawer.index');
        }

        // Load data untuk POS.
        // Kategori dan metode bayar tetap eager: keduanya satu kueri pendek dan
        // keduanya dipakai pada cat pertama — chip kategori langsung terlihat,
        // dan metode bayar harus sudah ada sebelum kasir menekan Bayar. Katalog
        // produk yang berat justru ditunda, lihat Inertia::defer() di bawah.
        $categories = Category::select('id', 'name')->get();

        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        // Tagihan terbuka TIDAK lagi dikirim dari sini: ia dibagikan lewat
        // HandleInertiaRequests supaya topbar bisa menampilkannya di semua
        // halaman kasir, bukan hanya POS ([BL-023]).

        return Inertia::render('Cashier/POS', [
            'categories' => $categories,
            'paymentMethods' => $paymentMethods,
            'cashDrawer' => $openDrawer,
            'tenantName' => Auth::user()->tenant->name,
            // Saklar foto bukti bayar non-tunai ([BL-075]). Eager, bukan
            // ditunda: modal pembayaran harus tahu jawabannya sebelum kasir
            // menekan Bayar, dan jawabannya satu boolean.
            'paymentProofEnabled' => $user->tenant->payment_proof_enabled,
            // Konteks pajak ([BL-065]). Eager dan ikut snapshot katalog, bukan
            // ditunda: kasir offline harus bisa menghitung total yang sama
            // dengan yang akan dihitung server saat sinkronisasi. Tanpa ini
            // tiap penjualan offline milik tenant berpajak akan mendarat
            // sebagai needs_review.
            'tax' => [
                'enabled' => $user->tenant->tax_enabled,
                'mode' => $user->tenant->tax_mode,
                'rate' => (float) $user->tenant->tax_rate,
                'label' => $user->tenant->tax_label,
            ],

            // --- Katalog dan indeks upsell: ditunda ([BL-037]) ---
            // Keduanya kueri terberat di halaman ini (produk membawa varian,
            // grup modifier, dan kategorinya; indeks upsell menelusuri seluruh
            // varian yang bisa dijual), dan selama ini layar kasir tidak muncul
            // sama sekali sampai keduanya selesai. Sekarang kerangka grid
            // produk yang tampil lebih dulu — lihat Cashier/POS.vue.
            //
            // Satu grup, bukan dua: indeks upsell ikut disimpan useCatalogCache
            // bersama katalognya, jadi keduanya harus sampai bersamaan supaya
            // snapshot offline tidak pernah menyimpan katalog tanpa sarannya.
            'products' => Inertia::defer(fn () => $this->catalogWithDiscounts($user->tenant)),
            // Indeks saran upsell ikut props, bukan endpoint tersendiri: dengan
            // begitu ia ikut ter-snapshot useCatalogCache dan tetap hidup saat
            // perangkat offline — lihat PHASE-UPSELL §Tahap C.
            'upsell' => Inertia::defer(fn () => $this->upsellIndexBuilder->build($user->tenant)),
        ]);
    }

    /**
     * Katalog POS, dengan harga berdiskon yang berlaku hari ini ([BL-018]).
     *
     * **Harga efektifnya HARUS ikut ke klien, bukan hanya dihitung di server.**
     * Checkout menghitung ulang harganya sendiri dan mengabaikan angka kiriman
     * klien — jadi kalau layar kasir menampilkan harga katalog sementara server
     * memotongnya, pelanggan akan dimintai satu angka lalu ditagih angka lain.
     * Kasir tidak punya cara menjelaskan selisih itu, dan angka di struk tidak
     * akan cocok dengan yang barusan ia sebutkan.
     *
     * `expiry_date` ikut diambil karena potongan `near_expiry` mendalam seiring
     * tanggalnya, jadi ia bagian dari perhitungan harganya — bukan sekadar
     * keterangan.
     */
    private function catalogWithDiscounts(Tenant $tenant): Collection
    {
        $products = Product::where('is_active', true)
            ->with([
                'variants' => fn ($q) => $q->select('id', 'product_id', 'name', 'price', 'cost_price', 'stock', 'expiry_date'),
                'modifierGroups.modifiers:id,modifier_group_id,name,extra_price',
                'category:id,name',
            ])
            ->get();

        $variants = $products->flatMap->variants;

        // Satu kueri untuk seluruh katalog, bukan satu per varian: ini jalur
        // terpanas aplikasi dan ia sudah berat tanpa N+1 tambahan.
        $rules = $this->discounts->rulesFor($tenant, $variants->pluck('id')->all());

        foreach ($variants as $variant) {
            $pricing = $this->discounts->priceFor($variant, $tenant, $rules->get($variant->id));

            // `price` sengaja TIDAK ditimpa. Layar kasir perlu menunjukkan
            // keduanya — harga coret dan harga bayar — karena potongan yang
            // tidak terlihat tidak pernah jadi alasan orang membeli.
            $variant->setAttribute('effective_price', $pricing['price']);
            $variant->setAttribute('discount_amount', $pricing['discount']);
            $variant->setAttribute('discount_reason', $pricing['rule']?->reason);
        }

        return $products;
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $transaction = $this->transactionService->checkout($request->validated());

            $isOpenBill = $request->boolean('is_open_bill');

            if ($isOpenBill) {
                // Batas umurnya disebut di sini, pada detik kasir menekan
                // "Tunda Bayar" ([BL-031]). Aturan yang hanya hidup di
                // penjadwal adalah aturan yang baru diketahui pengguna saat ia
                // sudah merugikannya.
                return back()->with('success', sprintf(
                    'Open bill %s berhasil disimpan! Berlaku %d jam — lewat itu jadi kas negatif.',
                    $transaction->code,
                    Transaction::OPEN_BILL_LIFETIME_HOURS,
                ));
            }

            $transaction->loadMissing('user:id,name');

            return back()->with('success', "Transaksi {$transaction->code} berhasil!")
                ->with('lastTransaction', $transaction->toArray());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Sinkronkan batch transaksi yang ditangkap saat perangkat offline.
     *
     * Dipanggil lewat fetch (bukan Inertia) sehingga mengembalikan JSON: client
     * butuh hasil PER transaksi untuk menandai outbox-nya, bukan redirect.
     *
     * Setiap item diproses dalam try/catch-nya sendiri. Satu payload rusak
     * (variant terhapus, metode bayar hilang) hanya menggagalkan dirinya sendiri
     * — kalau satu kegagalan me-rollback seluruh batch, satu transaksi beracun
     * akan menahan semua penjualan lain di antrean selamanya.
     */
    public function sync(SyncOfflineTransactionsRequest $request): JsonResponse
    {
        $cashier = Auth::user();
        $results = [];

        foreach ($request->validated()['transactions'] as $payload) {
            $clientUuid = $payload['client_uuid'];

            try {
                $wasQueued = ! Transaction::where('tenant_id', $cashier->tenant_id)
                    ->where('client_uuid', $clientUuid)
                    ->exists();

                $transaction = $this->transactionService->commitOffline($payload, $cashier);

                $results[] = [
                    'client_uuid' => $clientUuid,
                    // 'duplicate' bukan kegagalan: flush yang diulang setelah respons
                    // hilang di jaringan. Client menghapusnya dari outbox sama seperti 'synced'.
                    'status' => $wasQueued ? 'synced' : 'duplicate',
                    'code' => $transaction->code,
                    'needs_review' => $transaction->needsReview(),
                ];
            } catch (\Exception $e) {
                Log::warning('Offline transaction sync failed', [
                    'client_uuid' => $clientUuid,
                    'tenant_id' => $cashier->tenant_id,
                    'user_id' => $cashier->id,
                    'message' => $e->getMessage(),
                ]);

                $results[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'results' => $results,
            'synced' => collect($results)->whereIn('status', ['synced', 'duplicate'])->count(),
            'failed' => collect($results)->where('status', 'error')->count(),
        ]);
    }

    /**
     * Bayar open bill.
     */
    public function payOpenBill(PayOpenBillRequest $request, Transaction $transaction): RedirectResponse
    {
        $user = Auth::user();

        // Authorization
        if (! $user || $transaction->tenant_id !== $user->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        try {
            $transaction = $this->transactionService->payOpenBill(
                $transaction,
                $request->input('payments'),
                // Uangnya masuk laci YANG MELUNASI, bukan laci yang membuka
                // tagihannya ([BL-028]).
                $user,
            );

            $transaction->loadMissing('user:id,name');

            return back()->with('success', "Open bill {$transaction->code} berhasil dibayar!")
                ->with('lastTransaction', $transaction->toArray());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Daftar riwayat transaksi kasir.
     *
     * Dibatasi ke SESI BERJALAN, bukan seluruh riwayat akun. Tanpa batas ini
     * halaman membuka semua transaksi sejak akun dibuat — bising untuk kasir
     * yang hanya ingin mengoreksi penjualan barusan, dan memperlebar data yang
     * terbaca dari mesin kasir yang dipakai bergantian ([BL-027]).
     *
     * Batas tanggal memakai tanggal EFEKTIF, sejalan dengan rekonsiliasi kas:
     * penjualan offline muncul di shift yang benar-benar melakukannya, bukan
     * di shift yang kebetulan sedang berjalan saat ia tersinkron.
     */
    public function history(Request $request): Response
    {
        $user = Auth::user();

        // Diambil lebih awal daripada sebelumnya: laci terbuka kini menentukan
        // BATAS daftarnya, bukan sekadar hak edit tiap baris.
        $openDrawer = $user->isOwner()
            ? null
            : CashDrawer::where('user_id', $user->id)
                ->whereNull('closed_at')
                ->latest('opened_at')
                ->first();

        $query = Transaction::where('user_id', $user->id)
            ->with(['items.modifiers', 'payments.paymentMethod'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Penyetelan tanggal manual milik owner saja. Kasir yang bisa memilih
        // tanggal sendiri membuat pembatasan sesi di bawah cuma hiasan.
        $manualDate = $user->isOwner() && $request->filled('date')
            ? $request->input('date')
            : null;

        if ($manualDate) {
            $query->whereEffectiveDate($manualDate);
        } elseif ($openDrawer) {
            $query->whereEffectiveBetween($openDrawer->opened_at, now());
        } else {
            $query->whereEffectiveDate(now()->toDateString());
        }

        return Inertia::render('Cashier/TransactionHistory', [
            // Ditunda ([BL-037]): filter, cakupan, dan tombolnya sudah bisa
            // dipakai sementara satu halaman transaksi beserta item dan
            // pembayarannya masih dimuat. Baris riwayat hanya dibaca, tidak
            // ada yang bisa dikerjakan kasir sebelum daftarnya sampai.
            'transactions' => Inertia::defer(function () use ($query, $user, $openDrawer) {
                $transactions = $query->paginate(20)->withQueryString();

                // Tandai transaksi mana yang boleh diedit oleh user ini.
                // Owner: semua completed. Kasir: completed dalam shift laci terbuka miliknya.
                $transactions->getCollection()->transform(function (Transaction $tx) use ($user, $openDrawer) {
                    $tx->can_edit = $this->canEditTransaction($tx, $user, $openDrawer);

                    return $tx;
                });

                return $transactions;
            }),
            'filters' => [
                'status' => $request->input('status'),
                'date' => $manualDate,
            ],
            // Daftar yang diam-diam terpotong lebih buruk daripada daftar
            // panjang — permukaannya harus menyebutkan batas yang berlaku.
            'scope' => [
                'label' => $this->historyScopeLabel($manualDate, $openDrawer),
                'can_filter_date' => $user->isOwner(),
            ],
            // Katalog untuk modal edit — deferred agar payload awal ringan.
            'products' => Inertia::defer(fn () => Product::where('is_active', true)
                ->with([
                    'variants' => fn ($q) => $q->select('id', 'product_id', 'name', 'price', 'stock'),
                    'modifierGroups.modifiers:id,modifier_group_id,name,extra_price',
                    'category:id,name',
                ])
                ->get()),
            'paymentMethods' => Inertia::defer(fn () => PaymentMethod::where('is_active', true)->get()),
        ]);
    }

    /**
     * Kalimat yang menyebutkan batas daftar riwayat yang sedang berlaku.
     */
    private function historyScopeLabel(?string $manualDate, ?CashDrawer $openDrawer): string
    {
        if ($manualDate) {
            return 'Transaksi tanggal '.$manualDate;
        }

        if ($openDrawer) {
            return 'Sesi kas berjalan — sejak '.$openDrawer->opened_at->format('d M, H:i');
        }

        return 'Hari ini';
    }

    /**
     * Apakah $user boleh mengedit $tx sekarang? (mirror TransactionEditService::assertEditable)
     */
    private function canEditTransaction(Transaction $tx, \App\Models\User $user, ?CashDrawer $openDrawer): bool
    {
        if ($tx->status !== Transaction::STATUS_COMPLETED) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        return $openDrawer !== null && $tx->created_at >= $openDrawer->opened_at;
    }

    /**
     * Void transaksi (hanya owner).
     */
    public function void(Transaction $transaction): RedirectResponse
    {
        $user = Auth::user();

        if (! $user || $transaction->tenant_id !== $user->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        try {
            $this->transactionService->void($transaction);

            return back()->with('success', "Transaksi {$transaction->code} berhasil di-void.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
