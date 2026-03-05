<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\CashDrawer;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class POSController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    public function index(): Response|RedirectResponse
    {
        // Cek apakah kasir sudah buka kas
        $openDrawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (!$openDrawer) {
            return redirect()->route('cashier.cash-drawer.index');
        }

        // Load data untuk POS
        $categories = Category::select('id', 'name')->get();

        $products = Product::where('is_active', true)
            ->with([
                'variants' => fn($q) => $q->select('id', 'product_id', 'name', 'price', 'stock'),
                'modifierGroups.modifiers:id,modifier_group_id,name,extra_price',
                'category:id,name',
            ])
            ->get();

        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        return Inertia::render('Cashier/POS', [
            'categories' => $categories,
            'products' => $products,
            'paymentMethods' => $paymentMethods,
            'cashDrawer' => $openDrawer,
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $transaction = $this->transactionService->checkout($request->validated());

            return back()->with('success', "Transaksi {$transaction->code} berhasil!")
                ->with('lastTransaction', $transaction->toArray());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Void transaksi (hanya owner).
     */
    public function void(Transaction $transaction): RedirectResponse
    {
        try {
            $this->transactionService->void($transaction);

            return back()->with('success', "Transaksi {$transaction->code} berhasil di-void.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
