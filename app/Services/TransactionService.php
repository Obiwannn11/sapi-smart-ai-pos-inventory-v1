<?php

namespace App\Services;

use App\Models\CashDrawer;
use App\Models\Modifier;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\UpsellEvent;
use App\Models\User;
use App\Services\Queue\QueueNumberAllocator;
use App\Services\Upsell\UpsellEventRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private StockService $stockService,
        private UpsellEventRecorder $upsellEventRecorder,
        private QueueNumberAllocator $queueNumberAllocator,
        private PaymentProofService $paymentProofs,
        private DiscountService $discounts,
        private TaxCalculator $tax,
    ) {}

    /**
     * Baris pembayaran, berikut foto buktinya bila ada ([BL-075]).
     *
     * Satu tempat untuk kedua jalur online — checkout dan bayar open bill —
     * karena aturannya identik dan menuliskannya dua kali berarti suatu hari
     * ia akan berbeda di satu tempat.
     *
     * `claim()` tidak pernah melempar. Kalau tokennya tidak sah atau berkasnya
     * sudah hilang, pembayarannya tetap tersimpan tanpa bukti: penjualan yang
     * uangnya sudah diterima tidak boleh gagal karena sebuah foto. Yang menjaga
     * agar hal itu tak terjadi diam-diam adalah validasi di
     * StoreTransactionRequest, yang sudah menolak request-nya jauh sebelum
     * sampai ke sini.
     *
     * @param  array<string, mixed>  $payment
     */
    private function recordPayment(Transaction $transaction, array $payment, int $tenantId): void
    {
        $transaction->payments()->create([
            'payment_method_id' => $payment['payment_method_id'],
            'amount' => $payment['amount'],
            'reference_code' => $payment['reference_code'] ?? null,
            'proof_path' => $this->paymentProofs->claim($payment['proof_token'] ?? null, $tenantId),
        ]);
    }

    /**
     * Beri kartu ini label panggil dan posisi di papan.
     *
     * Keduanya diturunkan dari `effectiveDate()`, bukan `now()`. Untuk transaksi
     * online keduanya identik, jadi hari ini gratis. Wajib nanti: penjualan
     * offline disinkronkan belakangan, sehingga `now()` saat sync akan
     * melemparkannya ke dasar papan padahal pesanannya datang paling awal.
     */
    private function assignQueuePosition(Transaction $transaction): void
    {
        $occurredAt = $transaction->effectiveDate();

        $transaction->update([
            'queue_number' => $this->queueNumberAllocator->allocate($transaction->tenant_id, $occurredAt),
            'sort_index' => $occurredAt->getTimestampMs(),
        ]);
    }

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
            //
            // Bersyarat MODE ANTRIAN, bukan bersyarat open bill. Versi lama
            // berbunyi `$isOpenBill ? WAITING : null`, sehingga open bill
            // memperoleh `waiting` bahkan saat tak ada papan yang akan
            // mengerjakannya — itulah sumber timbunan yang dibersihkan migrasi
            // backfill_stale_fulfillment_status. Mengganti syaratnya menutup
            // sumbernya, bukan cuma menyapu akibatnya.
            //
            // Mode antrian hidup → open bill DAN POS langsung-bayar sama-sama
            // masuk papan; memasak dan membayar dua hal berbeda.
            // Mode antrian mati → null, persis perilaku cafe hari ini.
            $queueMode = $user->tenant?->hasFeature('kitchen_queue') ?? false;
            $fulfillmentStatus = $queueMode ? Transaction::FULFILLMENT_WAITING : null;

            // Nomor panggil lepas dari papan dapur ([BL-026]).
            //
            // Sebelumnya nomor hanya lahir bila `kitchen_queue` menyala, jadi
            // warung yang cuma ingin memanggil pelanggan harus menyalakan papan
            // dapur yang tak akan pernah dilihat siapa pun. Dua kebutuhan
            // berbeda, dua syarat berbeda — dan `fulfillment_status` tetap
            // milik papan seorang diri: nomor panggil TIDAK memasukkan pesanan
            // ke papan.
            $needsCallNumber = $queueMode || ($user->tenant?->usesCallNumber() ?? false);

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

            // 3b. Label & urutan papan. Dipanggil SETELAH create karena
            //     effectiveDate() jatuh ke created_at bila occurred_at kosong.
            if ($needsCallNumber) {
                $this->assignQueuePosition($transaction);
            }

            // 4. Simpan items + modifiers (SNAPSHOT) + deduct stok
            $baseAmount = $this->processItems($transaction, $data['items'], deductStock: true);

            // 5. Update total from DB-verified prices, lalu pajaknya ([BL-065]).
            //    Konteks pajak DIBEKUKAN di sini, bukan dibaca ulang nanti:
            //    struk yang dicetak ulang berbulan-bulan kemudian harus
            //    menghasilkan angka yang sama dengan kertas yang dipegang
            //    pelanggan, walau tarifnya sudah berubah sejak itu.
            $taxColumns = $this->tax->columnsFor(
                $baseAmount,
                $this->tax->contextFor($user->tenant),
                $this->tax->serviceContextFor($user->tenant),
            );

            $transaction->update($taxColumns);

            // Mulai titik ini `$totalAmount` berarti YANG DIBAYAR PELANGGAN,
            // sudah termasuk pajak. Pemeriksaan cukup-bayar dan kembalian di
            // bawah bersandar padanya — memakai angka sebelum pajak di sana
            // berarti kasir menerima kurang bayar tanpa menyadarinya.
            $totalAmount = $taxColumns['total_amount'];

            // 5b. Nasib saran upsell yang dilihat kasir pada keranjang ini.
            //     Dicatat sebelum cabang open bill agar tagihan tertunda tidak
            //     kehilangan datanya.
            $this->upsellEventRecorder->record(
                $transaction,
                $data['upsell_events'] ?? [],
                UpsellEvent::SURFACE_POS,
            );

            // 6. Jika open bill → selesai, tetap pending tanpa pembayaran
            if ($isOpenBill) {
                return $transaction->load(['items.modifiers']);
            }

            // 7. Simpan pembayaran
            $totalPaid = collect($data['payments'])->sum('amount');

            // Cukup-bayar diuji ulang terhadap harga DB, bukan harga kiriman
            // klien. StoreTransactionRequest sudah mengujinya lebih dulu, tapi
            // di sana yang tersedia hanya `unit_price` dari klien — perangkat
            // yang memakai katalog offline basi bisa lolos validasi lalu
            // tercatat `completed` dalam keadaan kurang bayar. Penjaga yang
            // sama sudah lama ada di payOpenBill(); [BL-022].
            //
            // Yang dijaga sengaja hanya "tidak boleh kurang", BUKAN "harga
            // klien harus sama persis dengan DB" — syarat kedua akan menabrak
            // harga diskon yang sah begitu [BL-018] dikerjakan.
            //
            // commitOffline() punya jalur sendiri dan TIDAK lewat sini:
            // penjualan offline yang sudah terjadi secara fisik ditandai
            // needs_review, tidak pernah ditolak.
            if ($totalPaid < $totalAmount) {
                throw new \Exception(
                    'Total pembayaran kurang. Harga katalog mungkin berubah — harus: '
                    .number_format($totalAmount).', dibayar: '.number_format($totalPaid)
                );
            }

            $changeAmount = max(0, $totalPaid - $totalAmount);
            $transaction->update([
                'change_amount' => $changeAmount,
            ]);

            foreach ($data['payments'] as $payment) {
                $this->recordPayment($transaction, $payment, $user->tenant_id);
            }

            // 8. Update status, berikut laci yang menerima uangnya.
            //
            // Ditulis DI SINI dan bukan saat `create()` di langkah 3, karena
            // langkah 3 juga melahirkan open bill — dan open bill belum
            // menerima uang siapa pun. Lacinya baru ditentukan saat ia dilunasi
            // (`payOpenBill()`), oleh laci yang melunasi. Lihat drawerReceiving().
            $transaction->update([
                'status' => Transaction::STATUS_COMPLETED,
                'cash_drawer_id' => $this->drawerReceiving($user),
            ]);

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
            $baseAmount = $this->processItems($transaction, $data['items'], deductStock: false);

            // Pesanan mandiri dipajaki dengan aturan yang sama seperti kasir
            // ([BL-065]) — pelanggan yang memesan sendiri membayar pajak yang
            // sama dengan pelanggan yang dilayani kasir.
            $transaction->update($this->tax->columnsFor(
                $baseAmount,
                $this->tax->contextFor($user->tenant),
                $this->tax->serviceContextFor($user->tenant),
            ));

            $this->upsellEventRecorder->record(
                $transaction,
                $data['upsell_events'] ?? [],
                UpsellEvent::SURFACE_SELF_ORDER,
            );

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

                // Pelanggannya sudah membayar, jadi menolak di sini tidak
                // menolong siapa pun. Barang basi hanya bisa terambil kalau stok
                // yang baik habis di antara pesan dan bayar; kalau itu terjadi,
                // barisnya menyebutnya TANPA nama pengonfirmasi, supaya owner
                // menemukannya di laporan ([BL-108]).
                $taken = $this->stockService->deduct($variant, $item->qty, $transaction->id, allowExpired: true);

                if ($taken['expired_qty'] > 0) {
                    $item->update([
                        'expired_qty' => $taken['expired_qty'],
                        'expiry_date_at_sale' => $taken['earliest_expired'],
                    ]);
                }
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
            //
            // `cash_drawer_id` tetap null dan itu disengaja ([BL-028] Tahap B
            // langkah 1): pembayarannya lewat Xendit, tidak ada kasir yang
            // menerimanya dan tidak ada laci yang kemasukan uangnya.
            $transaction->update([
                'status' => Transaction::STATUS_COMPLETED,
                'fulfillment_status' => Transaction::FULFILLMENT_WAITING,
            ]);

            // 4. Nomor antrian baru lahir di sini — bukan saat pesanan dibuat —
            //    karena self-order belum tentu dibayar. Tanpa langkah ini
            //    pesanan QR tidak akan pernah punya nomor untuk dipanggil.
            //
            //    Syaratnya sama dengan checkout(): papan dapur ATAU mode
            //    identitas kode. Pelanggan yang memesan lewat QR justru yang
            //    paling butuh dipanggil — ia tidak berdiri di depan kasir.
            $tenant = $transaction->tenant;
            if (($tenant?->hasFeature('kitchen_queue') ?? false) || ($tenant?->usesCallNumber() ?? false)) {
                $this->assignQueuePosition($transaction);
            }

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
     * Bayar open bill yang masih hidup.
     *
     * Tagihan yang sudah lewat 24 jam ditolak di sini, dengan kalimatnya
     * sendiri ([BL-031]). Penjaga `!== STATUS_PENDING` di bawah sebenarnya
     * sudah menolaknya — `unsettled` bukan `pending` — tapi pesannya akan
     * berbunyi "sudah dibayar", dan kasir yang membaca itu akan mencari uang
     * yang tidak pernah masuk alih-alih memanggil pemilik.
     */
    public function payOpenBill(Transaction $transaction, array $payments, ?User $paidBy = null): Transaction
    {
        if ($transaction->isUnsettled()) {
            throw new \Exception(
                'Tagihan ini sudah lewat '.Transaction::OPEN_BILL_LIFETIME_HOURS.' jam dan tercatat sebagai kas negatif. Hanya pemilik yang dapat membereskannya.'
            );
        }

        if ($transaction->status !== Transaction::STATUS_PENDING) {
            throw new \Exception('Transaksi ini bukan open bill / sudah dibayar.');
        }

        // Uangnya jatuh ke laci yang MELUNASI ([BL-028]) — itu sudah jadi
        // aturan sejak Tahap A, tapi sampai kolomnya ada ia tidak pernah
        // benar-benar berlaku: rekonsiliasi mencocokkan `user_id` pembuat
        // tagihan dengan tanggal saat tagihan itu DIBUKA, jadi tagihan pagi
        // yang dilunasi malam menaruh uangnya di laci pagi.
        //
        // `$paidBy` opsional supaya pemanggil lama tetap sah; tanpanya ia jatuh
        // ke pembuat tagihan, yaitu perilaku sebelum kolom ini ada.
        return $this->completeWithPayments(
            $transaction,
            $payments,
            cashDrawerId: $this->drawerReceiving($paidBy ?? $transaction->user),
        );
    }

    /**
     * Pemilik menerima pelunasan tagihan yang sudah jadi kas negatif ([BL-031]).
     *
     * Jalur terpisah dari `payOpenBill()` justru supaya penjaga di sana tidak
     * perlu dilonggarkan: satu-satunya cara sebuah tagihan lewat umur bisa
     * dilunasi adalah lewat pintu yang memeriksa kepemilikan, dan pintu itu
     * hanya ada di dashboard transaksi pemilik.
     *
     * Uangnya TIDAK jatuh ke laci mana pun — keputusan pemilik menyebutnya
     * langsung: sesudah 24 jam tidak ada laci yang akan menerimanya. Tanggal
     * efektif penjualannya sudah di luar jendela sesi kas mana pun, jadi
     * rekonsiliasi memang tidak akan memungutnya. Yang tertutup di sini adalah
     * kas negatifnya.
     *
     * @param  array<int, array<string, mixed>>  $payments
     */
    public function paySettledLateBill(Transaction $transaction, array $payments, User $owner): Transaction
    {
        if (! $transaction->isUnsettled()) {
            throw new \Exception('Transaksi ini bukan kas negatif.');
        }

        if (! $owner->isOwner() || $owner->tenant_id !== $transaction->tenant_id) {
            throw new \Exception('Hanya pemilik yang dapat melunasi kas negatif.');
        }

        // `cash_drawer_id` sengaja dibiarkan null: alinea di atas menyebutnya
        // sebagai keputusan, dan kolomnya sekarang menyatakannya.
        return $this->completeWithPayments($transaction, $payments, $owner);
    }

    /**
     * Laci yang menerima uang sebuah penjualan ([BL-028] Tahap B langkah 1).
     *
     * Satu-satunya cara sebuah penjualan memperoleh `cash_drawer_id`-nya lewat
     * jalur online. Sebelum kolom ini ada, jawabannya DITURUNKAN saat membaca —
     * `transactions.user_id` dicocokkan dengan rentang jam sesi — dan turunan
     * itu benar hanya selama satu kasir per outlet.
     *
     * Kebijakan lengkapnya, karena tiap barisnya keputusan dan bukan penurunan
     * mekanis:
     *
     *   checkout() selesai        laci terbuka kasirnya — uang masuk sekarang
     *   checkout() open bill      null; belum ada uang, lacinya menyusul
     *   payOpenBill()             laci YANG MELUNASI, bukan pembuat tagihan
     *   paySettledLateBill()      null — sesudah 24 jam tak ada laci yang
     *                             menerimanya ([BL-031])
     *   commitOffline()           laci yang jendelanya melingkupi occurred_at,
     *                             boleh yang sudah tertutup
     *   confirmSelfOrderPayment() null — non-tunai, tak ada kasir dan tak ada
     *                             laci
     *   void()                    tidak disentuh; jejak bahwa transaksi itu
     *                             memang pernah ada di laci itu
     *
     * `null` juga sah untuk penjualan biasa: pemilik berjualan di POS tanpa
     * pernah membuka sesi kas, dan kasir bisa menjual di sela dua sesi. Itu
     * keadaan nyata, bukan kegagalan — jangan diubah jadi pengecualian.
     */
    private function drawerReceiving(?User $receiver): ?int
    {
        return $receiver ? CashDrawer::openFor($receiver)?->id : null;
    }

    /**
     * Catat pembayaran lalu tutup transaksinya.
     *
     * Satu badan untuk dua pintu — pelunasan biasa dan pelunasan terlambat
     * oleh pemilik. Menuliskannya dua kali berarti suatu hari kurang-bayar
     * ditolak di satu pintu dan diterima di pintu lain.
     *
     * @param  array<int, array<string, mixed>>  $payments
     */
    private function completeWithPayments(
        Transaction $transaction,
        array $payments,
        ?User $settledBy = null,
        ?int $cashDrawerId = null,
    ): Transaction {
        return DB::transaction(function () use ($transaction, $payments, $settledBy, $cashDrawerId) {
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
                $this->recordPayment($transaction, $payment, $transaction->tenant_id);
            }

            $transaction->update([
                'change_amount' => $changeAmount,
                'status' => Transaction::STATUS_COMPLETED,
                // Laci yang MELUNASI, bukan yang membuat tagihannya
                // ([BL-028]). Ditentukan pemanggil karena hanya ia tahu siapa
                // yang membayar — dan untuk pelunasan terlambat oleh pemilik
                // jawabannya memang `null`.
                'cash_drawer_id' => $cashDrawerId,
            ] + ($settledBy ? [
                'edited_at' => now(),
                'edited_by' => $settledBy->id,
            ] : []));

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

            $transaction->update([
                'status' => Transaction::STATUS_VOIDED,
                // Tanpa ini, pesanan yang dibatalkan tetap tampil sebagai kartu
                // aktif di papan dan akan dimasak. Query papan JUGA
                // mengecualikan voided — dua lapis, karena satu lapis akan
                // bocor lewat jalur pembatalan yang belum ada hari ini.
                'fulfillment_status' => null,
            ]);

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

        $tenant = $transaction->tenant ?? Tenant::find($transaction->tenant_id);
        $user = Auth::user();

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

            // Pesanan mandiri tidak punya kasir untuk ditanya, jadi barang yang
            // sudah kedaluwarsa tidak pernah tersedia di sana — bukan "boleh
            // dengan konfirmasi", karena tidak ada siapa pun yang bisa
            // mengonfirmasinya ([BL-108]).
            if (! $deductStock) {
                $freshStock = $this->stockService->freshUnits($variant);

                if ($freshStock < $item['qty']) {
                    throw new \Exception(
                        "Stok {$variant->name} tidak cukup. Tersedia: {$freshStock}, diminta: {$item['qty']}"
                    );
                }
            }

            // Harga tetap datang dari SERVER, tidak pernah dari klien — yang
            // berubah sejak [BL-018] hanyalah bahwa "harga server" kini bisa
            // berarti harga berdiskon, bukan selalu harga katalog.
            $pricing = $this->resolveItemPrice($variant, $item, $tenant, $user);

            $unitPrice = $pricing['price'];
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
                ...$pricing['columns'],
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
                // Gerbang barang basi berdiri DI SINI, di server ([BL-108]).
                // Layar kasir menanyakannya lebih dulu, tapi penolakan yang
                // hanya hidup di layar tidak berlaku bagi siapa pun yang
                // mengirim request sendiri — pelajaran yang sudah dibayar
                // [BL-022]. Keputusan pemilik 2026-09-15: kasir boleh
                // mengonfirmasi, dengan alasan tertulis.
                $expiredReason = trim((string) ($item['expired_confirmation_reason'] ?? ''));

                $taken = $this->stockService->deduct(
                    $variant,
                    $item['qty'],
                    $transaction->id,
                    allowExpired: $expiredReason !== '',
                );

                // Alasan yang dikirim untuk baris yang ternyata tidak menyentuh
                // barang basi tidak ditulis: kolom-kolom ini hanya boleh menyala
                // pada penjualan yang benar-benar terjadi.
                if ($taken['expired_qty'] > 0) {
                    $txItem->update([
                        'expired_qty' => $taken['expired_qty'],
                        'expiry_date_at_sale' => $taken['earliest_expired'],
                        'expired_sale_confirmed_by' => $user?->id,
                        'expired_sale_reason' => $expiredReason,
                    ]);
                }
            }
        }

        return $totalAmount;
    }

    /**
     * Harga satu baris beserta seluruh jejak potongannya ([BL-018]).
     *
     * TIGA JALUR, dan urutannya menentukan siapa boleh melakukan apa:
     *
     *   1. TANPA POTONGAN — harga katalog. Jalur mayoritas penjualan.
     *   2. ATURAN DISKON yang owner setujui. Sistem memberlakukannya sendiri;
     *      ia tidak pernah bisa turun di bawah lantai margin, karena
     *      DiscountService menjepitnya di sana.
     *   3. PENEMBUSAN LANTAI oleh owner, dengan alasan tertulis. Ini
     *      satu-satunya jalan ke bawah lantai, dan ia selalu tindakan manusia
     *      yang disengaja — tidak pernah hasil rumus (keputusan pemilik
     *      2026-07-29).
     *
     * Wewenang jalur (3) MILIK OWNER SAJA, dan ditegakkan DI SINI — di sisi
     * server, bukan dengan menyembunyikan tombolnya. Kasir tidak bisa
     * menembus lantai sama sekali: bukan "bisa tapi dicatat", melainkan tidak
     * tersedia. Dipilih begitu karena menaikkan izin belakangan jauh lebih
     * mudah daripada menariknya kembali dari kasir yang sudah terbiasa.
     *
     * @param  array<string, mixed>  $item
     * @return array{price: float, columns: array<string, mixed>}
     */
    private function resolveItemPrice(ProductVariant $variant, array $item, ?Tenant $tenant, ?User $user): array
    {
        $catalog = (float) $variant->price;

        if (! $tenant) {
            return ['price' => $catalog, 'columns' => []];
        }

        $floor = $this->discounts->floorFor($variant, $tenant);

        // Harga modal dan lantai DIBEKUKAN bersama barisnya. Tanpa keduanya,
        // "seberapa dalam tembusnya" dan "apakah ini tetap untung" tidak bisa
        // dihitung ulang setelah harga modal berubah — dan harga modal pasti
        // berubah.
        $base = [
            'original_unit_price' => $catalog,
            'cost_price_at_sale' => $variant->cost_price,
            'margin_floor_at_sale' => $floor,
        ];

        $override = $item['override_unit_price'] ?? null;

        if ($override !== null) {
            $override = $this->discounts->roundUp((float) $override);

            // Penjaga server. Kasir yang mengirim request sendiri tetap
            // ditolak di sini, bukan hanya di layar.
            if (! $user || ! $user->isOwner()) {
                throw new \Exception('Hanya pemilik yang bisa menetapkan harga di bawah lantai margin.');
            }

            $reason = trim((string) ($item['discount_reason'] ?? ''));

            if ($reason === '') {
                throw new \Exception('Harga khusus wajib disertai alasan.');
            }

            if ($override > $catalog) {
                throw new \Exception('Harga khusus tidak boleh di atas harga katalog.');
            }

            return [
                'price' => $override,
                'columns' => [
                    ...$base,
                    'discount_amount' => round($catalog - $override, 2),
                    'discount_reason' => $reason,
                    // Diisi HANYA saat benar-benar menembus lantai. NULL-nya
                    // inilah yang memisahkan "diskon biasa" dari "yang
                    // benar-benar dikorbankan" di laporan.
                    'below_floor_approved_by' => $this->discounts->belowFloor($variant, $tenant, $override)
                        ? $user->id
                        : null,
                ],
            ];
        }

        $pricing = $this->discounts->priceFor($variant, $tenant);

        if ($pricing['rule'] === null) {
            return ['price' => $catalog, 'columns' => $base];
        }

        return [
            'price' => $pricing['price'],
            'columns' => [
                ...$base,
                'discount_amount' => $pricing['discount'],
                'discount_rule_id' => $pricing['rule']->id,
                // Alasan aturannya DISALIN ke barisnya, bukan cuma dirujuk:
                // aturan bisa disunting atau dihapus, dan laporan bulan lalu
                // harus tetap bisa menjelaskan dirinya sendiri.
                'discount_reason' => $pricing['rule']->reason,
            ],
        ];
    }

    /**
     * Apakah harga yang dibayar offline cocok dengan salah satu harga yang sah
     * hari ini — katalog atau berdiskon ([BL-018] poin 7)?
     */
    private function matchesAnyValidPrice(ProductVariant $variant, ?Tenant $tenant, float $paid): bool
    {
        if ($this->amountsMatch((float) $variant->price, $paid)) {
            return true;
        }

        if (! $tenant) {
            return false;
        }

        return $this->amountsMatch(
            $this->discounts->priceFor($variant, $tenant)['price'],
            $paid,
        );
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
                // Laci yang benar-benar menerima uangnya, yaitu yang terbuka
                // saat penjualannya TERJADI — bukan yang terbuka saat payload
                // ini sampai, dan boleh sesi yang sudah lama ditutup
                // ([BL-028] Tahap B langkah 1).
                //
                // Sesi yang sudah tertutup tidak akan berubah angkanya karena
                // itu: `expected_amount`-nya dibekukan saat tutup kas. Yang
                // berubah adalah penjualan ini berhenti tak-bertuan — sesudah
                // sakelar baca langkah 2, ia bisa muncul sebagai penjualan yang
                // datang terlambat ke sesi yang benar alih-alih hilang.
                'cash_drawer_id' => CashDrawer::coveringAt($cashier, $occurredAt)?->id,
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
                // Identitas ikut menumpang outbox, dengan alasan yang sama
                // seperti upsell_events di bawah: tanpa ini, periode offline
                // terlihat seolah tidak ada pesanan yang pernah diberi nama.
                //
                // Nomor panggil sengaja TIDAK dialokasikan di sini. Nomor
                // berguna karena tercetak di struk yang dipegang pelanggan;
                // nomor yang lahir saat sinkronisasi — berjam-jam setelah
                // strukanya dibawa pulang — tidak memanggil siapa pun.
                'customer_name' => $data['customer_name'] ?? null,
                'table_number' => $data['table_number'] ?? null,
            ]);

            [$baseAmount, $needsReview] = $this->processOfflineItems(
                $transaction,
                $data['items'],
                $tenantId,
                $occurredAt,
            );

            // Pajak dihitung ulang di server dengan setelan tenant SEKARANG
            // ([BL-065]). Perangkat yang seharian offline mungkin memakai
            // tarif yang sudah berubah; kalau begitu totalnya meleset dan
            // penjualan ini jatuh ke needs_review lewat penjaga di bawah —
            // mekanisme yang memang sudah ada untuk kasus persis ini, jadi
            // tidak perlu konsep baru.
            $taxColumns = $this->tax->columnsFor(
                $baseAmount,
                $this->tax->contextFor($cashier->tenant),
                $this->tax->serviceContextFor($cashier->tenant),
            );

            $totalAmount = $taxColumns['total_amount'];

            // Total SELALU dihitung ulang dari item. `total_amount` kiriman client
            // hanya dipakai sebagai cross-check — kalau beda, ada yang salah di
            // perangkat (atau payload dimanipulasi) dan owner perlu melihatnya.
            if (isset($data['total_amount']) && ! $this->amountsMatch((float) $data['total_amount'], $totalAmount)) {
                $needsReview = true;
            }

            $totalPaid = collect($payments)->sum('amount');

            $transaction->update($taxColumns + [
                'change_amount' => max(0, $totalPaid - $totalAmount),
                'sync_status' => $needsReview ? Transaction::SYNC_NEEDS_REVIEW : null,
            ]);

            // Jalur pembuat pembayaran KETIGA, dan satu-satunya yang tidak
            // pernah menyimpan foto bukti bayar ([BL-075]) — bukan karena
            // terlewat, melainkan karena assertCashOnly() di atas menolak
            // setiap pembayaran non-tunai jauh sebelum sampai ke sini.
            // Penjualan offline hari ini SELALU tunai, dan pembayaran tunai
            // tidak pernah punya bukti untuk difoto.
            //
            // Begitu penjualan non-tunai offline dibuka (lihat [BL-016]),
            // barisan inilah yang harus ikut berubah, dan bersamanya seluruh
            // bagian offline [BL-075]: kompresi sebelum masuk antrean,
            // penyimpanan sebagai Blob, dan unggahan yang terpisah dari
            // pengiriman penjualannya.
            foreach ($payments as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                ]);
            }

            // Saran yang muncul saat perangkat offline ikut menumpang outbox.
            // Tanpa ini, periode offline akan terlihat seolah tidak ada upsell
            // sama sekali — justru di tempat yang sinyalnya paling buruk.
            $this->upsellEventRecorder->record(
                $transaction,
                $data['upsell_events'] ?? [],
                UpsellEvent::SURFACE_POS,
            );

            return $transaction->load(['items.modifiers', 'payments.paymentMethod']);
        });
    }

    /**
     * Simpan item offline + deduct stok optimistik.
     *
     * @return array{0: float, 1: bool} [totalAmount, needsReview]
     */
    private function processOfflineItems(Transaction $transaction, array $items, int $tenantId, Carbon $occurredAt): array
    {
        $tenant = Tenant::find($tenantId);

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

            // Harga berdiskon yang SAH bukan anomali ([BL-018] poin 7).
            //
            // Sebelum entri ini, satu-satunya harga yang dianggap benar adalah
            // harga katalog — sehingga setiap penjualan berdiskon offline akan
            // membanjiri needs_review, dan sinyal yang dibangun untuk menangkap
            // anomali sungguhan jadi berisik lalu berhenti dipercaya.
            //
            // Yang dibandingkan sekarang adalah harga katalog DAN harga
            // berdiskon yang berlaku. Keduanya sah; apa pun di luar itu tetap
            // ditandai, termasuk harga yang lebih murah dari yang mana pun —
            // sebuah snapshot katalog yang basi persis terlihat begitu, dan
            // memang harus sampai ke meja owner.
            if (! $this->matchesAnyValidPrice($variant, $tenant, $unitPrice)) {
                $needsReview = true;
            }

            // Deduct OPTIMISTIK — sengaja tidak lewat StockService::deduct(), yang
            // menolak saat stok tak cukup (WHERE stock >= qty) dan saat barang
            // basi belum dikonfirmasi. Di sini keduanya BOLEH: barangnya sudah
            // keluar dari rak. "Basi" dinilai pada hari penjualannya TERJADI,
            // bukan hari sinkronisasinya.
            $taken = $this->stockService->deductOffline(
                $variant,
                $qty,
                $transaction->id,
                $tenantId,
                $occurredAt->toDateString(),
            );

            if ($taken['went_negative']) {
                $needsReview = true;
            }

            // Barang basi yang terjual offline tetap dicatat apa adanya. Dengan
            // alasan dari kasir, ia sama sahnya dengan penjualan online yang
            // dikonfirmasi; tanpa alasan, ia sampai ke meja owner ([BL-108]).
            $expiredReason = trim((string) ($line['expired_confirmation_reason'] ?? ''));
            $expiredColumns = [];

            if ($taken['expired_qty'] > 0) {
                $expiredColumns = [
                    'expired_qty' => $taken['expired_qty'],
                    'expiry_date_at_sale' => $taken['earliest_expired'],
                    'expired_sale_confirmed_by' => $expiredReason !== '' ? $transaction->user_id : null,
                    'expired_sale_reason' => $expiredReason !== '' ? $expiredReason : null,
                ];

                $needsReview = $needsReview || $expiredReason === '';
            }

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
                ...$expiredColumns,
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
            // Dibawa ke zona bisnis, bukan dipakai apa adanya ([BL-082]):
            // peramban mengirim instan UTC (`toISOString()`), dan Eloquent
            // menyimpan kolom datetime dengan memformat objeknya apa adanya.
            // Tanpa ini, penjualan pukul 09.00 WITA tersimpan sebagai 01.00
            // dan jatuh ke hari yang salah di Laporan Harian.
            $occurredAt = BusinessClock::fromClient($value);
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
