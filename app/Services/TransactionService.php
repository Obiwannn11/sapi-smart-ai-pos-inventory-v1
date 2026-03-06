<?php

namespace App\Services;

use App\Models\Modifier;
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

            // 2. Buat transaksi (total dihitung ulang dari DB prices setelah item loop)
            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'code' => $code,
                'status' => Transaction::STATUS_PENDING,
                'total_amount' => 0,
                'change_amount' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $totalAmount = 0;

            // 5. Simpan items + modifiers (SNAPSHOT)
            foreach ($data['items'] as $item) {
                // Lock row variant untuk mencegah race condition
                $variant = ProductVariant::lockForUpdate()->find($item['variant_id']);

                if (!$variant || $variant->stock < $item['qty']) {
                    $variantName = $variant?->name ?? 'produk';
                    $variantStock = $variant?->stock ?? 0;
                    throw new \Exception(
                        "Stok {$variantName} tidak cukup. Tersedia: {$variantStock}, diminta: {$item['qty']}"
                    );
                }

                // Use authoritative DB price, NOT client-supplied price
                $unitPrice = $variant->price;
                $subtotal = ($unitPrice * $item['qty']);

                // Hitung total modifier extra price per item from DB
                $modifierTotal = 0;
                $resolvedModifiers = [];
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $mod) {
                        $dbModifier = Modifier::find($mod['id']);
                        if (!$dbModifier) {
                            throw new \Exception("Modifier #{$mod['id']} tidak ditemukan.");
                        }
                        $resolvedModifiers[] = [
                            'id' => $dbModifier->id,
                            'name' => $dbModifier->name,
                            'extra_price' => $dbModifier->extra_price,
                        ];
                        $modifierTotal += $dbModifier->extra_price;
                    }
                    $modifierTotal *= $item['qty'];
                }
                $subtotal += $modifierTotal;

                $txItem = $transaction->items()->create([
                    'product_variant_id' => $variant->id,
                    'variant_name' => $item['variant_name'],      // SNAPSHOT
                    'qty' => $item['qty'],
                    'unit_price' => $unitPrice,                   // SNAPSHOT from DB
                    'subtotal' => $subtotal,
                ]);

                // Simpan modifier snapshots (from DB values)
                foreach ($resolvedModifiers as $modifier) {
                    $txItem->modifiers()->create([
                        'modifier_id' => $modifier['id'],
                        'modifier_name' => $modifier['name'],     // SNAPSHOT
                        'extra_price' => $modifier['extra_price'], // SNAPSHOT from DB
                    ]);
                }

                $totalAmount += $subtotal;

                // 6. Kurangi stok
                $this->stockService->deduct($variant, $item['qty'], $transaction->id);
            }

            // 7. Update total from DB-verified prices
            $totalPaid = collect($data['payments'])->sum('amount');
            $changeAmount = max(0, $totalPaid - $totalAmount);
            $transaction->update([
                'total_amount' => $totalAmount,
                'change_amount' => $changeAmount,
            ]);

            // 8. Simpan pembayaran
            foreach ($data['payments'] as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference_code' => $payment['reference_code'] ?? null,
                ]);
            }

            // 9. Update status
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
            // Kembalikan stok (handle soft-deleted variants)
            foreach ($transaction->items as $item) {
                $variant = $item->variant()->withTrashed()->first();
                if (!$variant) {
                    continue; // Variant permanently deleted, skip restore
                }
                $this->stockService->restore($variant, $item->qty, $transaction->id);
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

}
