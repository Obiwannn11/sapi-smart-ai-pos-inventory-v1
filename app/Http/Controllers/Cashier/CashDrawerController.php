<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashDrawerRequest;
use App\Http\Requests\OpenCashDrawerRequest;
use App\Models\CashDrawer;
use App\Models\TransactionPayment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CashDrawerController extends Controller
{
    /**
     * Halaman cash drawer — form buka kas atau summary sesi aktif.
     */
    public function index(): Response
    {
        $openDrawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        return Inertia::render('Cashier/CashDrawer', [
            'openDrawer' => $openDrawer,
        ]);
    }

    /**
     * Buka kas baru.
     */
    public function open(OpenCashDrawerRequest $request): RedirectResponse
    {
        // Validasi: tidak boleh ada sesi terbuka
        $existingOpen = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->exists();

        if ($existingOpen) {
            return back()->with('error', 'Anda masih memiliki sesi kas yang terbuka.');
        }

        CashDrawer::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'opening_amount' => $request->validated('opening_amount'),
            'opened_at' => now(),
        ]);

        return redirect()->route('cashier.pos')->with('success', 'Kas berhasil dibuka.');
    }

    /**
     * Tutup kas.
     */
    public function close(CloseCashDrawerRequest $request): RedirectResponse
    {
        $drawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->firstOrFail();

        // Hitung expected_amount dari SEMUA payment methods (sesuai kesepakatan)
        $expectedFromTransactions = TransactionPayment::whereHas('transaction', function ($q) use ($drawer) {
            $q->where('tenant_id', $drawer->tenant_id)
                ->where('status', 'completed')
                ->where('created_at', '>=', $drawer->opened_at)
                ->where('created_at', '<=', now());
        })->sum('amount');

        $expectedAmount = $drawer->opening_amount + $expectedFromTransactions;
        $closingAmount = $request->validated('closing_amount');

        $drawer->update([
            'closing_amount' => $closingAmount,
            'expected_amount' => $expectedAmount,
            'difference' => $closingAmount - $expectedAmount,
            'notes' => $request->validated('notes'),
            'closed_at' => now(),
        ]);

        return redirect()->route('cashier.cash-drawer.summary', $drawer)
            ->with('success', 'Kas berhasil ditutup.');
    }

    /**
     * Rekap sesi kas (detail per metode pembayaran).
     */
    public function summary(CashDrawer $cashDrawer): Response
    {
        // Rekap per payment method
        $paymentSummary = TransactionPayment::query()
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->join('payment_methods', 'transaction_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereHas('transaction', function ($q) use ($cashDrawer) {
                $q->where('tenant_id', $cashDrawer->tenant_id)
                    ->where('status', 'completed')
                    ->where('created_at', '>=', $cashDrawer->opened_at)
                    ->where('created_at', '<=', $cashDrawer->closed_at ?? now());
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get();

        // Hitung jumlah transaksi dalam sesi
        $transactionCount = \App\Models\Transaction::where('tenant_id', $cashDrawer->tenant_id)
            ->where('status', 'completed')
            ->where('created_at', '>=', $cashDrawer->opened_at)
            ->where('created_at', '<=', $cashDrawer->closed_at ?? now())
            ->count();

        return Inertia::render('Cashier/CashDrawerSummary', [
            'cashDrawer' => $cashDrawer,
            'paymentSummary' => $paymentSummary,
            'transactionCount' => $transactionCount,
        ]);
    }
}
