<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DemoTransactionSeeder extends Seeder
{
    /**
     * Isi 90 hari data transaksi realistis untuk demo dashboard Owner.
     *
     * Relasi FK yang dijaga:
     *   transactions        → tenants (tenant_id), users (user_id)
     *   transaction_items   → transactions (transaction_id), product_variants (product_variant_id)
     *   transaction_payments → transactions (transaction_id), payment_methods (payment_method_id)
     *   stock_movements     → tenants (tenant_id), product_variants (product_variant_id),
     *                         soft-ref ke transactions (reference_id, tanpa FK constraint)
     */
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'kopi-nusantara')->firstOrFail();
        $kasir  = User::where('tenant_id', $tenant->id)->where('role', 'cashier')->firstOrFail();

        // Hanya ambil variant aktif (tidak soft-deleted) milik tenant ini
        $variants = ProductVariant::whereHas(
            'product',
            fn($q) => $q->where('tenant_id', $tenant->id)->whereNull('deleted_at')
        )->whereNull('deleted_at')->get();

        $paymentMethods = PaymentMethod::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        if ($variants->isEmpty() || $paymentMethods->isEmpty()) {
            $this->command->error('Jalankan DatabaseSeeder terlebih dahulu sebelum DemoTransactionSeeder.');
            return;
        }

        $totalTransactions = 0;
        $txCounter         = 1;

        // Buffer bulk insert – flush setiap N rows agar memory aman
        $itemsBulk     = [];
        $paymentsBulk  = [];
        $movementsBulk = [];

        for ($daysAgo = 89; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo)->startOfDay();

            $isWeekend = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
            $isRecent  = $daysAgo < 30;   // bulan terakhir: lebih ramai

            $baseMin = $isWeekend ? 18 : 10;
            $baseMax = $isWeekend ? 28 : 18;
            $txCount = rand($baseMin, $baseMax);
            if ($isRecent) {
                $txCount = (int) ceil($txCount * 1.25);
            }

            for ($i = 0; $i < $txCount; $i++) {
                $hour   = $this->randomHour($isWeekend);
                $txTime = $date->copy()
                    ->setHour($hour)
                    ->setMinute(rand(0, 59))
                    ->setSecond(rand(0, 59));

                // --- Susun item payload dulu (belum ada transaction_id) ---
                $itemCount      = rand(1, 4);
                $selectedVars   = $variants->random(min($itemCount, $variants->count()));
                $totalAmount    = 0;
                $txItemPayloads = [];

                foreach ($selectedVars as $variant) {
                    $qty      = rand(1, 3);
                    $subtotal = $variant->price * $qty;
                    $totalAmount += $subtotal;

                    $txItemPayloads[] = [
                        // FK → product_variants.id (sudah divalidasi aktif di atas)
                        'product_variant_id' => $variant->id,
                        'variant_name'       => $variant->name,
                        'qty'                => $qty,
                        'unit_price'         => $variant->price,
                        'subtotal'           => $subtotal,
                        'notes'              => null,
                    ];
                }

                // --- Hitung pembayaran ---
                $code      = 'TRX-' . $txTime->format('Ymd') . '-' . str_pad($txCounter, 5, '0', STR_PAD_LEFT);
                $payMethod = $paymentMethods->random();

                if ($payMethod->type === 'cash') {
                    // Tender = pembulatan ke atas (customer bayar lebih → dapat kembalian)
                    $roundTo      = [1000, 2000, 5000, 10000][rand(0, 3)];
                    $tenderAmount = ceil($totalAmount / $roundTo) * $roundTo;
                    $changeAmount = $tenderAmount - $totalAmount;
                } else {
                    // Non-cash: bayar pas, tidak ada kembalian
                    $tenderAmount = $totalAmount;
                    $changeAmount = 0;
                }

                // --- Insert transaksi utama (butuh ID dulu untuk child records) ---
                $txId = DB::table('transactions')->insertGetId([
                    'tenant_id'          => $tenant->id,   // FK → tenants.id
                    'user_id'            => $kasir->id,    // FK → users.id
                    'code'               => $code,
                    'status'             => 'completed',
                    'total_amount'       => $totalAmount,
                    'change_amount'      => $changeAmount,
                    'source'             => 'pos',
                    'order_type'         => rand(0, 1) ? 'dine_in' : 'pickup',
                    'fulfillment_status' => null,           // POS = tidak perlu fulfillment tracking
                    'customer_name'      => null,
                    'table_number'       => null,
                    'notes'              => null,
                    'created_at'         => $txTime,
                    'updated_at'         => $txTime,
                ]);

                // --- Masukkan items & movements ke buffer ---
                foreach ($txItemPayloads as $item) {
                    $item['transaction_id'] = $txId; // FK → transactions.id (baru dapat ID-nya)
                    $itemsBulk[] = $item;

                    // stock_movements: selalu terhubung ke tenant & variant yang sama
                    $movementsBulk[] = [
                        'tenant_id'          => $tenant->id,              // FK → tenants.id
                        'product_variant_id' => $item['product_variant_id'], // FK → product_variants.id
                        'type'               => 'sale',
                        'qty'                => -$item['qty'],             // negatif = stok keluar
                        'notes'              => 'Penjualan ' . $code,
                        'reference_id'       => $txId,                    // soft-ref → transactions.id
                        'created_at'         => $txTime,
                    ];
                }

                // --- Payment buffer ---
                $paymentsBulk[] = [
                    'transaction_id'    => $txId,          // FK → transactions.id
                    'payment_method_id' => $payMethod->id, // FK → payment_methods.id
                    'amount'            => $tenderAmount,  // jumlah DITERIMA dari customer (bukan total_amount)
                    'reference_code'    => null,
                    'created_at'        => $txTime,
                ];

                $txCounter++;
                $totalTransactions++;

                // --- Flush buffer secara berkala agar tidak OOM ---
                if (count($itemsBulk) >= 800) {
                    DB::table('transaction_items')->insert($itemsBulk);
                    $itemsBulk = [];
                }
                if (count($paymentsBulk) >= 200) {
                    DB::table('transaction_payments')->insert($paymentsBulk);
                    $paymentsBulk = [];
                }
                if (count($movementsBulk) >= 800) {
                    DB::table('stock_movements')->insert($movementsBulk);
                    $movementsBulk = [];
                }
            }
        }

        // --- Flush sisa buffer ---
        if (!empty($itemsBulk))     DB::table('transaction_items')->insert($itemsBulk);
        if (!empty($paymentsBulk))  DB::table('transaction_payments')->insert($paymentsBulk);
        if (!empty($movementsBulk)) DB::table('stock_movements')->insert($movementsBulk);

        $this->command->info("✅ DemoTransactionSeeder selesai: {$totalTransactions} transaksi (90 hari historis).");
        $this->command->info("   Payments: {$totalTransactions} | Items & Movements: ~" . ($totalTransactions * 2) . " baris.");
    }

    /**
     * Distribusi jam secara weighted agar pola harian lebih realistis.
     * Weekday: puncak pagi jam 08-11 (working coffee habit).
     * Weekend: merata dari pagi sampai malam.
     */
    private function randomHour(bool $isWeekend): int
    {
        $buckets = $isWeekend
            ? [
                8  => 5,  9  => 8,  10 => 10, 11 => 9,
                12 => 12, 13 => 12, 14 => 8,
                15 => 5,  16 => 6,  17 => 5,
                18 => 9,  19 => 10, 20 => 8,  21 => 5,
            ]
            : [
                8  => 10, 9  => 15, 10 => 12, 11 => 10,
                12 => 8,  13 => 8,  14 => 5,
                15 => 4,  16 => 4,  17 => 4,
                18 => 5,  19 => 4,  20 => 3,  21 => 2,
            ];

        $total = array_sum($buckets);
        $rand  = rand(1, $total);
        $cumul = 0;
        foreach ($buckets as $hour => $weight) {
            $cumul += $weight;
            if ($rand <= $cumul) return $hour;
        }
        return 10;
    }
}
