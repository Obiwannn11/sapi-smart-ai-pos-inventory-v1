<?php

namespace App\Services;

use App\Models\Modifier;
use App\Models\ProductVariant;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Proses checkout — atomic transaction.
     * Mendukung open bill (simpan tanpa bayar) jika is_open_bill = true.
     * Stok SELALU dikurangi di sini (POS & open bill).
     */
    public function checkout(array $data): Transaction
    {
        $isOpenBill = !empty($data['is_open_bill']);

        return DB::transaction(function () use ($data, $isOpenBill) {
            $user = Auth::user();

            if (!$user) {
                throw new \Exception('User tidak terautentikasi.');
            }

            $tenantId = $user->tenant_id;

            // 1. Generate kode transaksi
            $code = $this->generateTransactionCode($tenantId);

            // 2. Determine fulfillment_status
            // Open bill → waiting (perlu tracking), POS langsung bayar → null (skip tracking)
            $fulfillmentStatus = $isOpenBill ? Transaction::FULFILLMENT_WAITING : null;

            // 3. Buat transaksi
            $transaction = Transaction::create([
                'tenant_id'          => $tenantId,
                'user_id'            => $user->id,
                'code'               => $code,
                'status'             => Transaction::STATUS_PENDING,
                'total_amount'       => 0,
                'change_amount'      => 0,
                'notes'              => $data['notes'] ?? null,
                'source'             => $data['source'] ?? Transaction::SOURCE_POS,
                'order_type'         => $data['order_type'] ?? Transaction::ORDER_TYPE_DINE_IN,
                'fulfillment_status' => $fulfillmentStatus,
                'customer_name'      => $data['customer_name'] ?? null,
                'table_number'       => $data['table_number'] ?? null,
            ]);

            $totalAmount = 0;

            // 4. Simpan items + modifiers (SNAPSHOT) + deduct stok
            $totalAmount = $this->processItems($transaction, $data['items'], deductStock: true);

            // 5. Update total from DB-verified prices
            $transaction->update([
                'total_amount' => $totalAmount,
            ]);

            // 6. Jika open bill → selesai, tetap pending tanpa pembayaran
            if ($isOpenBill) {
                return $transaction->load(['items.modifiers']);
            }

            // 7. Simpan pembayaran
            $totalPaid = collect($data['payments'])->sum('amount');
            $changeAmount = max(0, $totalPaid - $totalAmount);
            $transaction->update([
                'change_amount' => $changeAmount,
            ]);

            foreach ($data['payments'] as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount'            => $payment['amount'],
                    'reference_code'    => $payment['reference_code'] ?? null,
                ]);
            }

            // 8. Update status
            $transaction->update(['status' => Transaction::STATUS_COMPLETED]);

            return $transaction->load(['items.modifiers', 'payments.paymentMethod']);
        });
    }

    /**
     * Buat self-order — TANPA deduct stok, TANPA pembayaran.
     * Stok baru dikurangi setelah pembayaran dikonfirmasi via webhook.
     * Ini mencegah stok berkurang untuk order fiktif / tidak dibayar.
     */
    public function createSelfOrder(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            if (!$user) {
                throw new \Exception('User tidak terautentikasi.');
            }

            $tenantId = $user->tenant_id;
            $code = $this->generateTransactionCode($tenantId);

            // Buat transaksi — fulfillment NULL karena belum bayar
            $transaction = Transaction::create([
                'tenant_id'          => $tenantId,
                'user_id'            => $user->id,
                'code'               => $code,
                'status'             => Transaction::STATUS_PENDING,
                'total_amount'       => 0,
                'change_amount'      => 0,
                'notes'              => $data['notes'] ?? null,
                'source'             => Transaction::SOURCE_SELF_ORDER,
                'order_type'         => $data['order_type'] ?? Transaction::ORDER_TYPE_DINE_IN,
                'fulfillment_status' => null, // Belum aktif — menunggu bayar
                'customer_name'      => $data['customer_name'] ?? null,
                'table_number'       => $data['table_number'] ?? null,
            ]);

            // Simpan items + modifiers (SNAPSHOT) — TANPA deduct stok
            // Hanya cek ketersediaan, tidak dikurangi
            $totalAmount = $this->processItems($transaction, $data['items'], deductStock: false);

            $transaction->update([
                'total_amount' => $totalAmount,
            ]);

            return $transaction->load(['items.modifiers']);
        });
    }

    /**
     * Konfirmasi pembayaran self-order (dipanggil oleh Xendit webhook).
     * Baru di sini stok dikurangi + status jadi completed + fulfillment aktif.
     */
    public function confirmSelfOrderPayment(Transaction $transaction, ?string $referenceCode = null): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            throw new \Exception('Transaksi ini bukan pending / sudah dibayar.');
        }

        if ($transaction->source !== Transaction::SOURCE_SELF_ORDER) {
            throw new \Exception('Transaksi ini bukan self-order.');
        }

        return DB::transaction(function () use ($transaction, $referenceCode) {
            // 1. Deduct stok sekarang (setelah bayar confirmed)
            foreach ($transaction->items as $item) {
                $variant = ProductVariant::lockForUpdate()->find($item->product_variant_id);

                if (!$variant || $variant->stock < $item->qty) {
                    $variantName = $variant?->name ?? 'produk';
                    $variantStock = $variant?->stock ?? 0;
                    throw new \Exception(
                        "Stok {$variantName} tidak cukup. Tersedia: {$variantStock}, diminta: {$item->qty}"
                    );
                }

                $this->stockService->deduct($variant, $item->qty, $transaction->id);
            }

            // 2. Simpan payment record (Xendit — semua payment method termasuk QRIS, transfer, e-wallet)
            // Payment method record dari Xendit tidak perlu di-map ke PaymentMethod lokal
            // karena Xendit handle semua channel. Simpan sebagai reference.
            if ($referenceCode) {
                // Cari payment method Xendit/online di tenant, fallback ke apapun yang aktif
                $paymentMethod = \App\Models\PaymentMethod::where('is_active', true)
                    ->whereIn('type', ['qris', 'transfer', 'e_wallet'])
                    ->first();

                // Fallback: kalau tidak ada, pakai cash (placeholder)
                if (!$paymentMethod) {
                    $paymentMethod = \App\Models\PaymentMethod::where('is_active', true)
                        ->where('type', 'cash')
                        ->first();
                }

                if ($paymentMethod) {
                    $transaction->payments()->create([
                        'payment_method_id' => $paymentMethod->id,
                        'amount'            => $transaction->total_amount,
                        'reference_code'    => $referenceCode,
                    ]);
                }
            }

            // 3. Update status + aktifkan fulfillment
            $transaction->update([
                'status'             => Transaction::STATUS_COMPLETED,
                'fulfillment_status' => Transaction::FULFILLMENT_WAITING,
            ]);

            return $transaction->fresh()->load(['items.modifiers', 'payments.paymentMethod']);
        });
    }

    /**
     * Void self-order yang expired (invoice Xendit tidak dibayar).
     * Karena stok belum dikurangi, TIDAK perlu restore stok.
     */
    public function voidExpiredSelfOrder(Transaction $transaction): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            throw new \Exception('Hanya transaksi pending yang bisa di-void karena expired.');
        }

        if ($transaction->source !== Transaction::SOURCE_SELF_ORDER) {
            throw new \Exception('Hanya self-order yang bisa di-void via expired.');
        }

        // Self-order pending → stok belum dikurangi → langsung void tanpa restore
        $transaction->update(['status' => Transaction::STATUS_VOIDED]);

        return $transaction->fresh();
    }

    /**
     * Bayar open bill yang masih pending.
     */
    public function payOpenBill(Transaction $transaction, array $payments): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            throw new \Exception('Transaksi ini bukan open bill / sudah dibayar.');
        }

        return DB::transaction(function () use ($transaction, $payments) {
            $totalPaid = collect($payments)->sum('amount');
            $totalAmount = (float) $transaction->total_amount;
            $changeAmount = max(0, $totalPaid - $totalAmount);

            if ($totalPaid < $totalAmount) {
                throw new \Exception(
                    "Total pembayaran kurang. Harus: " . number_format($totalAmount) . ", dibayar: " . number_format($totalPaid)
                );
            }

            // Simpan pembayaran (support semua payment method: cash, QRIS, transfer, dll)
            foreach ($payments as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount'            => $payment['amount'],
                    'reference_code'    => $payment['reference_code'] ?? null,
                ]);
            }

            $transaction->update([
                'change_amount' => $changeAmount,
                'status'        => Transaction::STATUS_COMPLETED,
            ]);

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
     * Proses items: simpan snapshot + opsional deduct stok.
     * Dipakai oleh checkout() dan createSelfOrder().
     *
     * @param bool $deductStock true = kurangi stok (POS), false = cek saja (self-order)
     * @return float Total amount dari semua items
     */
    private function processItems(Transaction $transaction, array $items, bool $deductStock): float
    {
        $totalAmount = 0;

        foreach ($items as $item) {
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
                        'id'          => $dbModifier->id,
                        'name'        => $dbModifier->name,
                        'extra_price' => $dbModifier->extra_price,
                    ];
                    $modifierTotal += $dbModifier->extra_price;
                }
                $modifierTotal *= $item['qty'];
            }
            $subtotal += $modifierTotal;

            $txItem = $transaction->items()->create([
                'product_variant_id' => $variant->id,
                'variant_name'       => $item['variant_name'],      // SNAPSHOT
                'qty'                => $item['qty'],
                'unit_price'         => $unitPrice,                 // SNAPSHOT from DB
                'subtotal'           => $subtotal,
                'notes'              => $item['notes'] ?? null,     // Catatan per item
            ]);

            // Simpan modifier snapshots (from DB values)
            foreach ($resolvedModifiers as $modifier) {
                $txItem->modifiers()->create([
                    'modifier_id'   => $modifier['id'],
                    'modifier_name' => $modifier['name'],           // SNAPSHOT
                    'extra_price'   => $modifier['extra_price'],    // SNAPSHOT from DB
                ]);
            }

            $totalAmount += $subtotal;

            // Deduct stok hanya jika diminta (POS = ya, self-order = tidak)
            if ($deductStock) {
                $this->stockService->deduct($variant, $item['qty'], $transaction->id);
            }
        }

        return $totalAmount;
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
