<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Proses checkout — atomic transaction.
     */
    public function checkout(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $tenantId = auth()->user()->tenant_id;

            // 1. Generate kode transaksi
            $code = $this->generateTransactionCode($tenantId);

            // 2. Hitung total
            $totalAmount = $this->calculateTotal($data['items']);

            // 3. Hitung kembalian
            $totalPaid = collect($data['payments'])->sum('amount');
            $changeAmount = max(0, $totalPaid - $totalAmount);

            // 4. Buat transaksi
            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'code' => $code,
                'status' => Transaction::STATUS_PENDING,
                'total_amount' => $totalAmount,
                'change_amount' => $changeAmount,
                'notes' => $data['notes'] ?? null,
            ]);

            // 5. Simpan items + modifiers (SNAPSHOT)
            foreach ($data['items'] as $item) {
                // Lock row variant untuk mencegah race condition
                $variant = ProductVariant::lockForUpdate()->find($item['variant_id']);

                if (!$variant || $variant->stock < $item['qty']) {
                    throw new \Exception(
                        "Stok {$variant?->name ?? 'produk'} tidak cukup. " .
                        "Tersedia: {$variant?->stock ?? 0}, diminta: {$item['qty']}"
                    );
                }

                $subtotal = ($item['unit_price'] * $item['qty']);

                // Hitung total modifier extra price per item
                $modifierTotal = 0;
                if (!empty($item['modifiers'])) {
                    $modifierTotal = collect($item['modifiers'])->sum('extra_price') * $item['qty'];
                }
                $subtotal += $modifierTotal;

                $txItem = $transaction->items()->create([
                    'product_variant_id' => $variant->id,
                    'variant_name' => $item['variant_name'],      // SNAPSHOT
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],          // SNAPSHOT
                    'subtotal' => $subtotal,
                ]);

                // Simpan modifier snapshots
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $modifier) {
                        $txItem->modifiers()->create([
                            'modifier_id' => $modifier['id'],
                            'modifier_name' => $modifier['name'],     // SNAPSHOT
                            'extra_price' => $modifier['extra_price'], // SNAPSHOT
                        ]);
                    }
                }

                // 6. Kurangi stok
                $this->stockService->deduct($variant, $item['qty'], $transaction->id);
            }

            // 7. Simpan pembayaran
            foreach ($data['payments'] as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference_code' => $payment['reference_code'] ?? null,
                ]);
            }

            // 8. Update status
            $transaction->update(['status' => Transaction::STATUS_COMPLETED]);

            return $transaction->load(['items.modifiers', 'payments.paymentMethod']);
        });
    }

    /**
     * Void transaksi — kembalikan stok.
     */
    public function void(Transaction $transaction): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_COMPLETED) {
            throw new \Exception('Hanya transaksi completed yang bisa di-void.');
        }

        // MVP: hanya bisa void transaksi hari ini
        if (!$transaction->created_at->isToday()) {
            throw new \Exception('Hanya bisa void transaksi hari ini.');
        }

        return DB::transaction(function () use ($transaction) {
            // Kembalikan stok
            foreach ($transaction->items as $item) {
                $this->stockService->restore($item->variant, $item->qty, $transaction->id);
            }

            $transaction->update(['status' => Transaction::STATUS_VOIDED]);

            return $transaction->fresh();
        });
    }

    /**
     * Generate kode transaksi: TRX-YYYYMMDD-XXX
     */
    private function generateTransactionCode(int $tenantId): string
    {
        $today = now()->format('Ymd');

        $lastTransaction = Transaction::where('tenant_id', $tenantId)
            ->where('code', 'like', "TRX-{$today}-%")
            ->lockForUpdate()
            ->orderByDesc('code')
            ->first();

        if ($lastTransaction) {
            $lastNumber = (int) Str::afterLast($lastTransaction->code, '-');
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf("TRX-%s-%03d", $today, $nextNumber);
    }

    /**
     * Hitung total dari items (harga variant + modifier extra).
     */
    private function calculateTotal(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            $itemTotal = $item['unit_price'] * $item['qty'];

            if (!empty($item['modifiers'])) {
                $modifierExtra = collect($item['modifiers'])->sum('extra_price');
                $itemTotal += $modifierExtra * $item['qty'];
            }

            $total += $itemTotal;
        }

        return $total;
    }
}
