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
     * Pola: weekday ramai pagi, weekend ramai siang, revenue naik bulan ini.
     */
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'kopi-nusantara')->firstOrFail();
        $kasir  = User::where('tenant_id', $tenant->id)->where('role', 'cashier')->firstOrFail();

        $variants      = ProductVariant::whereHas('product', fn($q) => $q->where('tenant_id', $tenant->id))->get();
        $paymentMethods = PaymentMethod::where('tenant_id', $tenant->id)->where('is_active', true)->get();

        if ($variants->isEmpty() || $paymentMethods->isEmpty()) {
            $this->command->error('Jalankan DatabaseSeeder terlebih dahulu sebelum DemoTransactionSeeder.');
            return;
        }

        $totalTransactions = 0;
        $txCounter         = 1;

        // Iterasi 90 hari ke belakang dari hari ini
        for ($daysAgo = 89; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo);

            // Tentukan banyak transaksi per hari
            // Weekend: 18–28 tx, Weekday: 10–18 tx
            // Bulan terakhir (30 hari pertama dari sekarang): boost +20%
            $isWeekend    = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
            $isRecent     = $daysAgo < 30;

            $baseMin = $isWeekend ? 18 : 10;
            $baseMax = $isWeekend ? 28 : 18;
            $txCount = rand($baseMin, $baseMax);
            if ($isRecent) {
                $txCount = (int) ceil($txCount * 1.25); // +25% untuk bulan terbaru
            }

            // Buat jam buka: pukul 08:00 – 21:00
            for ($i = 0; $i < $txCount; $i++) {
                $hour = $this->randomHour($isWeekend);
                $txTime = $date->copy()->setHour($hour)->setMinute(rand(0, 59))->setSecond(rand(0, 59));

                // Pilih 1-4 item per transaksi
                $itemCount    = rand(1, 4);
                $selectedVars = $variants->random(min($itemCount, $variants->count()));
                $totalAmount  = 0;

                $itemPayloads = [];
                foreach ($selectedVars as $variant) {
                    $qty      = rand(1, 3);
                    $subtotal = $variant->price * $qty;
                    $totalAmount += $subtotal;
                    $itemPayloads[] = [
                        'product_variant_id' => $variant->id,
                        'variant_name'       => $variant->name,
                        'qty'                => $qty,
                        'unit_price'         => $variant->price,
                        'subtotal'           => $subtotal,
                    ];
                }

                $code        = 'TRX-' . $txTime->format('Ymd') . '-' . str_pad($txCounter, 5, '0', STR_PAD_LEFT);
                $payMethod   = $paymentMethods->random();
                $changeAmount = ($payMethod->type === 'cash') ? rand(0, 10) * 1000 : 0;

                // Sisipkan transaksi
                $txId = DB::table('transactions')->insertGetId([
                    'tenant_id'     => $tenant->id,
                    'user_id'       => $kasir->id,
                    'code'          => $code,
                    'status'        => 'completed',
                    'total_amount'  => $totalAmount,
                    'change_amount' => $changeAmount,
                    'source'        => 'pos',
                    'order_type'    => rand(0, 1) ? 'dine_in' : 'pickup',
                    'notes'         => null,
                    'created_at'    => $txTime,
                    'updated_at'    => $txTime,
                ]);

                // Sisipkan item
                foreach ($itemPayloads as &$item) {
                    $item['transaction_id'] = $txId;
                }
                DB::table('transaction_items')->insert($itemPayloads);

                // Sisipkan pembayaran
                DB::table('transaction_payments')->insert([
                    'transaction_id'    => $txId,
                    'payment_method_id' => $payMethod->id,
                    'amount'            => $totalAmount + $changeAmount,
                    'reference_code'    => null,
                    'created_at'        => $txTime,
                ]);

                // Sisipkan stock_movements (sale)
                foreach ($itemPayloads as $item) {
                    DB::table('stock_movements')->insert([
                        'tenant_id'          => $tenant->id,
                        'product_variant_id' => $item['product_variant_id'],
                        'type'               => 'sale',
                        'qty'                => -$item['qty'],
                        'notes'              => 'Penjualan transaksi ' . $code,
                        'reference_id'       => $txId,
                        'created_at'         => $txTime,
                    ]);
                }

                $txCounter++;
                $totalTransactions++;
            }
        }

        $this->command->info("✅ DemoTransactionSeeder selesai: {$totalTransactions} transaksi berhasil dibuat (90 hari historis).");
    }

    /**
     * Pilih jam secara weighted:
     * - Pagi (08-11): jam "working coffee" → lebih ramai
     * - Siang (12-14): lunch rush
     * - Sore (15-17): slow
     * - Malam (18-21): ramai lagi di weekend
     */
    private function randomHour(bool $isWeekend): int
    {
        $buckets = $isWeekend
            ? [
                8 => 5, 9 => 8, 10 => 10, 11 => 9,
                12 => 12, 13 => 12, 14 => 8,
                15 => 5, 16 => 6, 17 => 5,
                18 => 9, 19 => 10, 20 => 8, 21 => 5,
            ]
            : [
                8 => 10, 9 => 15, 10 => 12, 11 => 10,
                12 => 8, 13 => 8, 14 => 5,
                15 => 4, 16 => 4, 17 => 4,
                18 => 5, 19 => 4, 20 => 3, 21 => 2,
            ];

        $total = array_sum($buckets);
        $rand  = rand(1, $total);
        $cumul = 0;
        foreach ($buckets as $hour => $weight) {
            $cumul += $weight;
            if ($rand <= $cumul) {
                return $hour;
            }
        }
        return 10; // fallback
    }
}
