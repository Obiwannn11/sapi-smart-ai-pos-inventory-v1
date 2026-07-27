<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\SyncOfflineTransactionsRequest;
use App\Models\CashDrawer;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Services\Upsell\UpsellIndexBuilder;
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

        // Load data untuk POS
        $categories = Category::select('id', 'name')->get();

        $products = Product::where('is_active', true)
            ->with([
                'variants' => fn ($q) => $q->select('id', 'product_id', 'name', 'price', 'stock'),
                'modifierGroups.modifiers:id,modifier_group_id,name,extra_price',
                'category:id,name',
            ])
            ->get();

        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        // Open bills milik kasir ini (atau sesi ini)
        $openBills = Transaction::where('user_id', $userId)
            ->where('status', Transaction::STATUS_PENDING)
            ->with(['items.modifiers'])
            ->latest()
            ->get();

        return Inertia::render('Cashier/POS', [
            'categories' => $categories,
            'products' => $products,
            'paymentMethods' => $paymentMethods,
            'cashDrawer' => $openDrawer,
            'openBills' => $openBills,
            'tenantName' => Auth::user()->tenant->name,
            // Indeks saran upsell ikut props, bukan endpoint tersendiri: dengan
            // begitu ia ikut ter-snapshot useCatalogCache dan tetap hidup saat
            // perangkat offline — lihat PHASE-UPSELL §Tahap C.
            'upsell' => $this->upsellIndexBuilder->build($user->tenant),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $transaction = $this->transactionService->checkout($request->validated());

            $isOpenBill = $request->boolean('is_open_bill');

            if ($isOpenBill) {
                return back()->with('success', "Open bill {$transaction->code} berhasil disimpan!");
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
    public function payOpenBill(Request $request, Transaction $transaction): RedirectResponse
    {
        $user = Auth::user();

        // Authorization
        if (! $user || $transaction->tenant_id !== $user->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference_code' => 'nullable|string|max:255',
        ]);

        try {
            $transaction = $this->transactionService->payOpenBill(
                $transaction,
                $request->input('payments')
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
     */
    public function history(Request $request): Response
    {
        $query = Transaction::where('user_id', Auth::id())
            ->with(['items.modifiers', 'payments.paymentMethod'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $transactions = $query->paginate(20)->withQueryString();

        // Tandai transaksi mana yang boleh diedit oleh user ini.
        // Owner: semua completed. Kasir: completed dalam shift laci terbuka miliknya.
        $user = Auth::user();
        $openDrawer = $user->isOwner()
            ? null
            : CashDrawer::where('user_id', $user->id)
                ->whereNull('closed_at')
                ->latest('opened_at')
                ->first();

        $transactions->getCollection()->transform(function (Transaction $tx) use ($user, $openDrawer) {
            $tx->can_edit = $this->canEditTransaction($tx, $user, $openDrawer);

            return $tx;
        });

        return Inertia::render('Cashier/TransactionHistory', [
            'transactions' => $transactions,
            'filters' => $request->only(['status', 'date']),
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
