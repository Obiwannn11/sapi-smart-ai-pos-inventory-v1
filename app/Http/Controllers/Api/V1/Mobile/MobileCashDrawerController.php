<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CashDrawer;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\PaymentMethodRecap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileCashDrawerController extends Controller
{
    public function __construct(private PaymentMethodRecap $paymentRecap) {}

    public function status(): JsonResponse
    {
        $drawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        return response()->json([
            'is_open' => (bool) $drawer,
            'drawer_id' => $drawer?->id,
            'opened_at' => $drawer?->opened_at,
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
        ]);

        $user = auth()->user();

        $existingOpen = CashDrawer::where('user_id', $user->id)
            ->whereNull('closed_at')
            ->exists();

        if ($existingOpen) {
            return response()->json(['message' => 'Anda masih memiliki sesi kas yang terbuka.'], 422);
        }

        $drawer = CashDrawer::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'opening_amount' => $request->opening_amount,
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Kas berhasil dibuka.',
            'drawer_id' => $drawer->id,
            'opened_at' => $drawer->opened_at,
        ], 201);
    }

    public function close(Request $request): JsonResponse
    {
        $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $drawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (! $drawer) {
            return response()->json(['message' => 'Tidak ada sesi kas yang terbuka.'], 422);
        }

        $expectedCashFromPayments = TransactionPayment::query()
            ->whereHas('paymentMethod', fn ($q) => $q->where('type', 'cash'))
            ->whereHas('transaction', function ($q) use ($drawer) {
                $q->where('tenant_id', $drawer->tenant_id)
                    ->where('status', 'completed')
                    ->where('created_at', '>=', $drawer->opened_at)
                    ->where('created_at', '<=', now());
            })
            ->sum('amount');

        $totalChangeGiven = Transaction::where('tenant_id', $drawer->tenant_id)
            ->where('status', 'completed')
            ->where('created_at', '>=', $drawer->opened_at)
            ->where('created_at', '<=', now())
            ->sum('change_amount');

        $expectedAmount = $drawer->opening_amount + $expectedCashFromPayments - $totalChangeGiven;
        $closingAmount = $request->closing_amount;

        $drawer->update([
            'closing_amount' => $closingAmount,
            'expected_amount' => $expectedAmount,
            'difference' => $closingAmount - $expectedAmount,
            'notes' => $request->notes,
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Kas berhasil ditutup.',
            'drawer_id' => $drawer->id,
            'expected_amount' => $expectedAmount,
            'closing_amount' => $closingAmount,
            'difference' => $drawer->difference,
        ]);
    }

    public function summary(CashDrawer $cashDrawer): JsonResponse
    {
        $user = auth()->user();

        if ($cashDrawer->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Kasir hanya bisa lihat kas sendiri; owner bisa lihat semua
        if ($user->isCashier() && $cashDrawer->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke sesi kas ini.'], 403);
        }

        // Satu kueri transaksi untuk dua jawaban: rekapnya dan jumlahnya.
        // Sebelumnya keduanya menulis penyaring sesinya masing-masing, dan
        // rekapnya menjumlahkan uang yang diserahkan — kembalian ikut terhitung
        // sebagai pendapatan tunai ([BL-109]). `close()` di berkas ini tidak
        // pernah punya cacat itu; ia sudah mengurangkan `change_amount` sendiri.
        $sessionTransactions = Transaction::where('tenant_id', $cashDrawer->tenant_id)
            ->where('status', 'completed')
            ->where('created_at', '>=', $cashDrawer->opened_at)
            ->where('created_at', '<=', $cashDrawer->closed_at ?? now());

        $paymentSummary = $this->paymentRecap->for(
            clone $sessionTransactions,
            $cashDrawer->tenant_id
        );

        $transactionCount = (clone $sessionTransactions)->count();

        return response()->json([
            'data' => [
                'drawer' => [
                    'id' => $cashDrawer->id,
                    'opening_amount' => $cashDrawer->opening_amount,
                    'closing_amount' => $cashDrawer->closing_amount,
                    'expected_amount' => $cashDrawer->expected_amount,
                    'difference' => $cashDrawer->difference,
                    'notes' => $cashDrawer->notes,
                    'opened_at' => $cashDrawer->opened_at,
                    'closed_at' => $cashDrawer->closed_at,
                    'is_open' => is_null($cashDrawer->closed_at),
                ],
                'transaction_count' => $transactionCount,
                'payment_summary' => $paymentSummary,
            ],
        ]);
    }
}
