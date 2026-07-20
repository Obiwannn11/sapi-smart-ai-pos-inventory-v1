<?php

namespace App\Services;

use App\Models\Modifier;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $isOpenBill = ! empty($data['is_open_bill']);

        return DB::transaction(function () use ($data, $isOpenBill) {
            $user = Auth::user();

            if (! $user) {
                throw new \Exception('User tidak terautentikasi.');
            }

            $tenantId = $user->tenant_id;

            // 0. Idempotensi: kalau client_uuid sudah pernah diproses, kembalikan
            //    transaksi lama (retry jaringan / double-submit jadi no-op).
            if (! empty($data['client_uuid'])) {
                $existing = Transaction::where('tenant_id', $tenantId)
                    ->where('client_uuid', $data['client_uuid'])
                    ->first();

                if ($existing) {
                    return $existing->load(['items.modifiers', 'payments.paymentMethod']);
                }
            }

            // 1. Generate kode transaksi
            $code = $this->generateTransactionCode($tenantId);

            // 2. Determine fulfillment_status
            // Open bill → waiting (perlu tracking), POS langsung bayar → null (skip tracking)
            $fulfillmentStatus = $isOpenBill ? Transaction::FULFILLMENT_WAITING : null;

            // 3. Buat transaksi
            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'code' => $code,
                'client_uuid' => $data['client_uuid'] ?? null,
                'status' => Transaction::STATUS_PENDING,
                'total_amount' => 0,
                'change_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? Transaction::SOURCE_POS,
                'order_type' => $data['order_type'] ?? Transaction::ORDER_TYPE_DINE_IN,
                'fulfillment_status' => $fulfillmentStatus,
                'customer_name' => $data['customer_name'] ?? null,
                'table_number' => $data['table_number'] ?? null,
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
     * Buat self-order — TANPA deduct stok, TANPA pembayaran.
     * Stok baru dikurangi setelah pembayaran dikonfirmasi via webhook.
     * Ini mencegah stok berkurang untuk order fiktif / tidak dibayar.
     */
    public function createSelfOrder(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            if (! $user) {
                throw new \Exception('User tidak terautentikasi.');
            }

            $tenantId = $user->tenant_id;
            $code = $this->generateTransactionCode($tenantId);

            // Buat transaksi — fulfillment NULL karena belum bayar
            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'code' => $code,
                'status' => Transaction::STATUS_PENDING,
                'total_amount' => 0,
                'change_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'source' => Transaction::SOURCE_SELF_ORDER,
                'order_type' => $data['order_type'] ?? Transaction::ORDER_TYPE_DINE_IN,
                'fulfillment_status' => null, // Belum aktif — menunggu bayar
                'customer_name' => $data['customer_name'] ?? null,
                'table_number' => $data['table_number'] ?? null,
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

                if (! $variant || $variant->stock < $item->qty) {
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
                if (! $paymentMethod) {
                    $paymentMethod = \App\Models\PaymentMethod::where('is_active', true)
                        ->where('type', 'cash')
                        ->first();
                }

                if ($paymentMethod) {
                    $transaction->payments()->create([
                        'payment_method_id' => $paymentMethod->id,
                        'amount' => $transaction->total_amount,
                        'reference_code' => $referenceCode,
                    ]);
                }
            }

            // 3. Update status + aktifkan fulfillment
            $transaction->update([
                'status' => Transaction::STATUS_COMPLETED,
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
                    'Total pembayaran kurang. Harus: '.number_format($totalAmount).', dibayar: '.number_format($totalPaid)
                );
            }

            // Simpan pembayaran (support semua payment method: cash, QRIS, transfer, dll)
            foreach ($payments as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference_code' => $payment['reference_code'] ?? null,
                ]);
            }

            $transaction->update([
                'change_amount' => $changeAmount,
                'status' => Transaction::STATUS_COMPLETED,
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
        if (! $transaction->created_at->isToday()) {
            throw new \Exception('Hanya bisa void transaksi hari ini.');
        }

        return DB::transaction(function () use ($transaction) {
            // Kembalikan stok (handle soft-deleted variants)
            foreach ($transaction->items as $item) {
                $variant = $item->variant()->withTrashed()->first();
                if (! $variant) {
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
     * @param  bool  $deductStock  true = kurangi stok (POS), false = cek saja (self-order)
     * @return float Total amount dari semua items
     */
    private function processItems(Transaction $transaction, array $items, bool $deductStock): float
    {
        $totalAmount = 0;

        foreach ($items as $item) {
            // Lock row variant untuk mencegah race condition
            $variant = ProductVariant::lockForUpdate()->find($item['variant_id']);

            if (! $variant || $variant->stock < $item['qty']) {
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
            if (! empty($item['modifiers'])) {
                foreach ($item['modifiers'] as $mod) {
                    $dbModifier = Modifier::find($mod['id']);
                    if (! $dbModifier) {
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
                'unit_price' => $unitPrice,                 // SNAPSHOT from DB
                'subtotal' => $subtotal,
                'notes' => $item['notes'] ?? null,     // Catatan per item
            ]);

            // Simpan modifier snapshots (from DB values)
            foreach ($resolvedModifiers as $modifier) {
                $txItem->modifiers()->create([
                    'modifier_id' => $modifier['id'],
                    'modifier_name' => $modifier['name'],           // SNAPSHOT
                    'extra_price' => $modifier['extra_price'],    // SNAPSHOT from DB
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
        return $this->generateTransactionCodeFor($tenantId, now());
    }

    /**
     * Generate kode transaksi untuk tanggal tertentu.
     *
     * Transaksi offline disinkronkan setelah kejadian — kadang esok harinya —
     * jadi kodenya harus memakai tanggal transaksi sebenarnya, bukan now(),
     * supaya TRX-YYYYMMDD-XXX konsisten dengan hari penjualan.
     */
    private function generateTransactionCodeFor(int $tenantId, Carbon $occurredAt): string
    {
        $day = $occurredAt->format('Ymd');

        $lastTransaction = Transaction::where('tenant_id', $tenantId)
            ->where('code', 'like', "TRX-{$day}-%")
            ->lockForUpdate()
            ->orderByDesc('code')
            ->first();

        if ($lastTransaction) {
            $lastNumber = (int) Str::afterLast($lastTransaction->code, '-');
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('TRX-%s-%03d', $day, $nextNumber);
    }

    // ── Offline sync ────────────────────────────────────────────────────────

    /**
     * Batas kewajaran occurred_at.
     *
     * Ke depan: toleransi clock skew perangkat saja — penjualan tidak bisa
     * terjadi di masa depan. Ke belakang: melindungi dari payload yang
     * di-replay setelah berbulan-bulan (dan dari clock yang salah total).
     */
    private const OCCURRED_AT_FUTURE_TOLERANCE_MINUTES = 5;

    private const OCCURRED_AT_MAX_AGE_DAYS = 30;

    /**
     * Simpan transaksi yang ditangkap saat perangkat offline.
     *
     * Jalur TERPISAH dari checkout(), dengan asumsi yang berkebalikan:
     *
     *   checkout()      — server adalah kebenaran. Stok tak cukup → tolak.
     *   commitOffline() — penjualan SUDAH terjadi secara fisik. Tidak pernah
     *                     ditolak karena stok; stok boleh minus dan transaksi
     *                     ditandai needs_review agar owner mengoreksi.
     *
     * Yang tetap ditolak hanyalah payload yang secara struktural tidak sah
     * (milik tenant lain, non-tunai, waktu mustahil) — itu bukan anomali
     * operasional, itu payload yang tidak bisa dipercaya.
     *
     * @param  array{client_uuid:string, occurred_at:string, device_id?:string, items:array, payments:array, total_amount?:numeric, notes?:string}  $data
     *
     * @throws \Exception bila payload tidak sah
     */
    public function commitOffline(array $data, User $cashier): Transaction
    {
        $tenantId = $cashier->tenant_id;

        // 0. Idempotensi — flush yang diulang (atau dua tab) tidak boleh menggandakan.
        $existing = Transaction::where('tenant_id', $tenantId)
            ->where('client_uuid', $data['client_uuid'])
            ->first();

        if ($existing) {
            return $existing->load(['items.modifiers', 'payments.paymentMethod']);
        }

        $occurredAt = $this->parseOccurredAt($data['occurred_at'] ?? null);
        $payments = $this->assertCashOnly($data['payments'] ?? [], $tenantId);

        if (empty($data['items'])) {
            throw new \Exception('Transaksi offline tanpa item tidak sah.');
        }

        return DB::transaction(function () use ($data, $cashier, $tenantId, $occurredAt, $payments) {
            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'user_id' => $cashier->id,
                'code' => $this->generateTransactionCodeFor($tenantId, $occurredAt),
                'client_uuid' => $data['client_uuid'],
                'status' => Transaction::STATUS_COMPLETED,
                'source' => Transaction::SOURCE_POS,
                'channel' => Transaction::CHANNEL_OFFLINE,
                'occurred_at' => $occurredAt,
                'synced_at' => now(),
                'device_id' => $data['device_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total_amount' => 0,
                'change_amount' => 0,
            ]);

            [$totalAmount, $needsReview] = $this->processOfflineItems(
                $transaction,
                $data['items'],
                $tenantId,
            );

            // Total SELALU dihitung ulang dari item. `total_amount` kiriman client
            // hanya dipakai sebagai cross-check — kalau beda, ada yang salah di
            // perangkat (atau payload dimanipulasi) dan owner perlu melihatnya.
            if (isset($data['total_amount']) && ! $this->amountsMatch((float) $data['total_amount'], $totalAmount)) {
                $needsReview = true;
            }

            $totalPaid = collect($payments)->sum('amount');

            $transaction->update([
                'total_amount' => $totalAmount,
                'change_amount' => max(0, $totalPaid - $totalAmount),
                'sync_status' => $needsReview ? Transaction::SYNC_NEEDS_REVIEW : null,
            ]);

            foreach ($payments as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                ]);
            }

            return $transaction->load(['items.modifiers', 'payments.paymentMethod']);
        });
    }

    /**
     * Simpan item offline + deduct stok optimistik.
     *
     * @return array{0: float, 1: bool} [totalAmount, needsReview]
     */
    private function processOfflineItems(Transaction $transaction, array $items, int $tenantId): array
    {
        $totalAmount = 0;
        $needsReview = false;

        foreach ($items as $line) {
            $qty = (int) ($line['qty'] ?? 0);

            if ($qty < 1) {
                throw new \Exception('Kuantitas item offline tidak sah.');
            }

            // Scope ke tenant secara EKSPLISIT. ProductVariant tidak memakai
            // BelongsToTenant, dan TenantScope pada Product hanya aktif bila
            // auth()->check() — sedangkan method ini menerima $cashier sebagai
            // argumen dan bisa dipanggil tanpa sesi. Tanpa where ini, payload
            // bisa merujuk variant tenant lain dan mengurangi stok mereka.
            //
            // withTrashed() disengaja: produk yang di-soft-delete SEJAK perangkat
            // offline tetap punya baris (FK aman) dan penjualannya nyata, jadi
            // harus bisa dicatat. Bedakan dari variant yang benar-benar asing.
            $variant = ProductVariant::withTrashed()
                ->whereHas('product', fn ($q) => $q->withTrashed()->where('tenant_id', $tenantId))
                ->lockForUpdate()
                ->find($line['variant_id'] ?? null);

            if (! $variant) {
                // Tidak dikenal atau milik tenant lain. Ini bukan anomali
                // operasional melainkan payload yang tidak bisa dipercaya — tolak,
                // jangan simpan dengan flag (lihat taksonomi §3f).
                throw new \Exception('Item offline merujuk produk yang tidak dikenal.');
            }

            // Harga yang dibayar pelanggan offline = harga di katalog lokal saat itu.
            // Server tidak menimpanya (pelanggan sudah membayar segitu), tapi wajib
            // menandai bila berbeda dari harga sekarang.
            $unitPrice = (float) ($line['unit_price'] ?? 0);

            if ($variant->trashed()) {
                $needsReview = true;
            }

            if (! $this->amountsMatch((float) $variant->price, $unitPrice)) {
                $needsReview = true;
            }

            // Deduct OPTIMISTIK — sengaja tidak lewat StockService::deduct(), yang
            // menolak saat stok tak cukup (WHERE stock >= qty). Di sini stok BOLEH
            // minus: barangnya sudah keluar dari rak.
            //
            // Jangan pakai decrement() lalu baca $variant->stock: atribut in-memory
            // diturunkan dari nilai yang model tahu sebelumnya, bukan hasil baca
            // ulang DB, sehingga cek minus bisa meleset. Baris sudah di-lock, jadi
            // hitung eksplisit dari nilai ter-lock.
            $newStock = $variant->stock - $qty;
            $variant->update(['stock' => $newStock]);

            if ($newStock < 0) {
                $needsReview = true;
            }

            StockMovement::create([
                'tenant_id' => $tenantId,
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_SALE,
                'qty' => -$qty,
                'notes' => "Penjualan offline (sync) #{$transaction->id}",
                'reference_id' => $transaction->id,
            ]);

            [$modifierTotal, $resolvedModifiers, $modifierNeedsReview] = $this->resolveOfflineModifiers(
                $line['modifiers'] ?? [],
                $qty,
                $tenantId,
            );
            $needsReview = $needsReview || $modifierNeedsReview;

            $subtotal = ($unitPrice * $qty) + $modifierTotal;

            $txItem = $transaction->items()->create([
                'product_variant_id' => $variant->id,
                'variant_name' => $line['variant_name'],
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'notes' => $line['notes'] ?? null,
            ]);

            foreach ($resolvedModifiers as $modifier) {
                $txItem->modifiers()->create([
                    'modifier_id' => $modifier['id'],
                    'modifier_name' => $modifier['name'],
                    'extra_price' => $modifier['extra_price'],
                ]);
            }

            $totalAmount += $subtotal;
        }

        return [$totalAmount, $needsReview];
    }

    /**
     * Resolve modifier dari payload offline.
     *
     * @return array{0: float, 1: array<int, array{id:int, name:string, extra_price:float}>, 2: bool}
     */
    private function resolveOfflineModifiers(array $modifiers, int $qty, int $tenantId): array
    {
        $extraTotal = 0;
        $resolved = [];
        $needsReview = false;

        foreach ($modifiers as $mod) {
            // Modifier juga tanpa global scope — lewat group-nya ke tenant.
            $dbModifier = Modifier::whereHas('group', fn ($q) => $q->where('tenant_id', $tenantId))
                ->find($mod['id'] ?? null);

            if (! $dbModifier) {
                // Dihapus sejak offline: pertahankan snapshot dari perangkat supaya
                // struk cocok dengan yang dibayar pelanggan, tapi tandai.
                $needsReview = true;

                continue;
            }

            $extraPrice = (float) ($mod['extra_price'] ?? 0);

            if (! $this->amountsMatch((float) $dbModifier->extra_price, $extraPrice)) {
                $needsReview = true;
            }

            $resolved[] = [
                'id' => $dbModifier->id,
                'name' => $dbModifier->name,
                'extra_price' => $extraPrice,
            ];

            $extraTotal += $extraPrice;
        }

        return [$extraTotal * $qty, $resolved, $needsReview];
    }

    /**
     * Pastikan seluruh pembayaran offline tunai & milik tenant ini.
     *
     * Dua lapis: UI menyembunyikan metode non-tunai saat offline, tapi UI bukan
     * penjaga. Non-tunai butuh verifikasi gateway yang mustahil dilakukan offline.
     *
     * @return array<int, array{payment_method_id:int, amount:float}>
     *
     * @throws \Exception
     */
    private function assertCashOnly(array $payments, int $tenantId): array
    {
        if (empty($payments)) {
            throw new \Exception('Transaksi offline wajib menyertakan pembayaran tunai.');
        }

        foreach ($payments as $payment) {
            // PaymentMethod tanpa global scope → scope tenant eksplisit.
            $method = PaymentMethod::where('tenant_id', $tenantId)
                ->find($payment['payment_method_id'] ?? null);

            if (! $method) {
                throw new \Exception('Metode pembayaran tidak ditemukan.');
            }

            if ($method->type !== 'cash') {
                throw new \Exception('Transaksi offline hanya menerima pembayaran tunai.');
            }
        }

        return $payments;
    }

    /**
     * Validasi occurred_at ada dan masuk akal.
     *
     * @throws \Exception
     */
    private function parseOccurredAt(?string $value): Carbon
    {
        if (blank($value)) {
            throw new \Exception('Waktu transaksi offline (occurred_at) wajib diisi.');
        }

        try {
            $occurredAt = Carbon::parse($value);
        } catch (\Exception $e) {
            throw new \Exception('Format waktu transaksi offline tidak sah.');
        }

        if ($occurredAt->isAfter(now()->addMinutes(self::OCCURRED_AT_FUTURE_TOLERANCE_MINUTES))) {
            throw new \Exception('Waktu transaksi offline berada di masa depan.');
        }

        if ($occurredAt->isBefore(now()->subDays(self::OCCURRED_AT_MAX_AGE_DAYS))) {
            throw new \Exception('Waktu transaksi offline terlalu lampau untuk disinkronkan.');
        }

        return $occurredAt;
    }

    /**
     * Bandingkan dua nilai uang dengan toleransi pembulatan float.
     */
    private function amountsMatch(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }
}
