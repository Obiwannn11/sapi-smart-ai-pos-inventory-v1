<?php

namespace Database\Seeders;

use App\Models\CashDrawer;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Concerns\FillsMissingSalesDays;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CafeStudyCaseSeeder extends Seeder
{
    use FillsMissingSalesDays;

    /**
     * Studi kasus: coffee shop bergaya kedai kopi susu kekinian populer
     * (mis. Kopi Kenangan/Fore/Janji Jiwa), lengkap dengan menu bervariasi,
     * restock stok bulanan, dan transaksi harian dari Januari 2026 s.d. hari ini,
     * dengan target omset harian Rp1.000.000 - Rp2.000.000.
     *
     * Tenant terpisah dari seeder demo lain (slug: kopi-story) agar tidak
     * mengganggu data yang dipakai DatabaseSeeder / DemoTransactionSeeder.
     *
     * Idempotent, dan sejak 2026-08-01 caranya berubah: menu/tenant/user/payment
     * method tetap `firstOrCreate` dan sesi kas tetap `updateOrCreate`, tapi
     * data transaksional tidak lagi dihapus lebih dulu. Yang disemai hanya hari
     * yang belum punya transaksi sama sekali, dan restock hanya untuk bulan yang
     * belum punya restock. Menjalankannya ulang menjelang demo kini menambal
     * jarak sejak seed terakhir — tanpa menggandakan omzet, dan tanpa membuang
     * transaksi yang baru saja dibuat lewat UI. Untuk data yang benar-benar
     * bersih, jalurnya `migrate:fresh --seed`.
     */
    private const START_DATE = '2026-01-01';

    private const INITIAL_STOCK = 300;

    private const RESTOCK_QTY = 150;

    private const OPENING_CASH = 500000;

    public function run(): void
    {
        $tenant = $this->seedTenantAndUsers();
        [$products, $variants] = $this->seedMenu($tenant);
        $this->seedModifiers($tenant, $products);
        $paymentMethods = $this->seedPaymentMethods($tenant);

        $stockLevels = $this->seedMonthlyStock($tenant, $variants);
        $dailySummary = $this->seedDailyTransactions($tenant, $variants, $paymentMethods, $stockLevels);
        $this->seedCashDrawerSessions($tenant, $dailySummary);

        $this->command->info('✅ CafeStudyCaseSeeder selesai.');
    }

    private function seedTenantAndUsers(): Tenant
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'kopi-story'],
            ['name' => 'Kopi Story']
        );

        User::firstOrCreate(
            ['email' => 'owner@kopistory.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Owner Kopi Story',
                'password' => Hash::make('password'),
                'role' => 'owner',
            ]
        );

        User::firstOrCreate(
            ['email' => 'kasir@kopistory.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Kasir Kopi Story',
                'password' => Hash::make('password'),
                'role' => 'cashier',
            ]
        );

        return $tenant;
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, Product>, 1: \Illuminate\Support\Collection<int, ProductVariant>}
     */
    private function seedMenu(Tenant $tenant): array
    {
        $categories = collect([
            'Kopi Susu' => Category::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Kopi Susu']),
            'Coffee' => Category::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Coffee']),
            'Non-Coffee' => Category::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Non-Coffee']),
            'Tea' => Category::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Tea']),
            'Pastry & Snack' => Category::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Pastry & Snack']),
        ]);

        // name, category, [ [variant, price, cost] ]
        $menu = [
            ['Kopi Susu Signature', 'Kopi Susu', [['Hot', 18000, 6000], ['Iced', 22000, 7000]]],
            ['Kopi Susu Gula Aren', 'Kopi Susu', [['Hot', 20000, 7000], ['Iced', 24000, 8000]]],
            ['Vietnam Drip', 'Kopi Susu', [['Hot', 20000, 7000], ['Iced', 25000, 8500]]],
            ['Hazelnut Kopi Susu', 'Kopi Susu', [['Iced', 26000, 9000]]],

            ['Espresso', 'Coffee', [['Single', 15000, 4000], ['Double', 22000, 6000]]],
            ['Americano', 'Coffee', [['Hot', 18000, 5000], ['Iced', 20000, 5500]]],
            ['Cappuccino', 'Coffee', [['Hot', 25000, 8000], ['Iced', 28000, 8500]]],
            ['Cafe Latte', 'Coffee', [['Hot', 26000, 8000], ['Iced', 29000, 8500]]],
            ['Caramel Macchiato', 'Coffee', [['Hot', 28000, 9000], ['Iced', 32000, 9500]]],

            ['Matcha Latte', 'Non-Coffee', [['Hot', 28000, 10000], ['Iced', 32000, 11000]]],
            ['Chocolate', 'Non-Coffee', [['Hot', 25000, 9000], ['Iced', 28000, 9500]]],
            ['Taro Latte', 'Non-Coffee', [['Regular', 30000, 11000], ['Large', 35000, 12500]]],
            ['Red Velvet Latte', 'Non-Coffee', [['Hot', 28000, 10000], ['Iced', 32000, 11000]]],

            ['Lemon Tea', 'Tea', [['Regular', 15000, 4000], ['Large', 18000, 5000]]],
            ['Thai Tea', 'Tea', [['Regular', 22000, 7000], ['Large', 26000, 8000]]],
            ['Lychee Tea', 'Tea', [['Regular', 20000, 6000]]],

            ['Croissant', 'Pastry & Snack', [['Plain', 22000, 9000], ['Chocolate', 26000, 11000]]],
            ['Butter Cookies', 'Pastry & Snack', [['Pack', 20000, 8000]]],
            ['Banana Cake', 'Pastry & Snack', [['Slice', 18000, 7000]]],
            ['French Fries', 'Pastry & Snack', [['Regular', 18000, 6000], ['Large', 24000, 8000]]],
        ];

        $products = collect();
        $variants = collect();

        foreach ($menu as [$name, $categoryName, $variantDefs]) {
            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['category_id' => $categories[$categoryName]->id, 'is_active' => true]
            );
            $products->push($product);

            foreach ($variantDefs as [$variantName, $price, $cost]) {
                $variant = ProductVariant::firstOrCreate(
                    ['product_id' => $product->id, 'name' => $variantName],
                    ['price' => $price, 'cost_price' => $cost, 'stock' => self::INITIAL_STOCK]
                );
                $variants->push($variant);
            }
        }

        return [$products, $variants];
    }

    private function seedModifiers(Tenant $tenant, \Illuminate\Support\Collection $products): void
    {
        $sugarGroup = ModifierGroup::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Sugar Level'],
            ['is_required' => false, 'is_multiple' => false]
        );
        if ($sugarGroup->wasRecentlyCreated) {
            Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Normal', 'extra_price' => 0]);
            Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Less Sugar', 'extra_price' => 0]);
            Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Extra Sweet', 'extra_price' => 0]);
        }

        $addonGroup = ModifierGroup::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Add-ons'],
            ['is_required' => false, 'is_multiple' => true]
        );
        if ($addonGroup->wasRecentlyCreated) {
            Modifier::create(['modifier_group_id' => $addonGroup->id, 'name' => 'Extra Shot', 'extra_price' => 5000]);
            Modifier::create(['modifier_group_id' => $addonGroup->id, 'name' => 'Oat Milk', 'extra_price' => 8000]);
            Modifier::create(['modifier_group_id' => $addonGroup->id, 'name' => 'Boba', 'extra_price' => 6000]);
        }

        $drinkProducts = $products->reject(fn (Product $p) => $p->category->name === 'Pastry & Snack');
        foreach ($drinkProducts as $product) {
            $product->modifierGroups()->syncWithoutDetaching([$sugarGroup->id, $addonGroup->id]);
        }
    }

    private function seedPaymentMethods(Tenant $tenant): \Illuminate\Support\Collection
    {
        $methods = [
            ['name' => 'Cash', 'type' => 'cash'],
            ['name' => 'QRIS', 'type' => 'qris_static'],
            ['name' => 'Debit Card', 'type' => 'bank_transfer'],
        ];

        return collect($methods)->map(
            fn ($m) => PaymentMethod::firstOrCreate(
                ['tenant_id' => $tenant->id, 'type' => $m['type']],
                ['name' => $m['name'], 'is_active' => true]
            )
        );
    }

    /**
     * Restock bulanan (tanggal 1 tiap bulan, sejak Januari 2026) menggunakan
     * StockMovement type=restock, dan mengembalikan stok berjalan tiap variant
     * (dilacak di memori) agar penjualan harian tidak melebihi stok tersedia.
     *
     * Bulan yang sudah punya restock dilewati. Tanpa itu, seeder yang tidak
     * lagi menghapus data lebih dulu akan menambah satu gelombang restock tiap
     * kali dijalankan — stok mengembang, dan riwayat mutasi memperlihatkan
     * kiriman barang yang tak pernah datang.
     *
     * Titik awal stoknya adalah nilai yang tercatat sekarang, bukan baseline:
     * penjualan hari-hari sebelumnya sudah memotongnya, dan mengembalikannya ke
     * baseline berarti menghadiahkan stok yang sudah terjual.
     *
     * @return array<int, int> stock level per product_variant_id
     */
    private function seedMonthlyStock(Tenant $tenant, \Illuminate\Support\Collection $variants): array
    {
        $stockLevels = DB::table('product_variants')
            ->whereIn('id', $variants->pluck('id'))
            ->pluck('stock', 'id')
            ->map(fn ($stock) => (int) $stock)
            ->all();

        $bulanTerisi = DB::table('stock_movements')
            ->where('tenant_id', $tenant->id)
            ->where('type', 'restock')
            ->selectRaw('DISTINCT '.$this->monthExpression('created_at').' AS restock_month')
            ->pluck('restock_month')
            ->flip()
            ->all();

        $end = Carbon::now()->startOfDay();
        $cursor = Carbon::parse(self::START_DATE)->startOfMonth();

        $movements = [];
        while ($cursor->lte($end)) {
            if (isset($bulanTerisi[$cursor->format('Y-m')])) {
                $cursor->addMonthNoOverflow();

                continue;
            }

            foreach ($variants as $variant) {
                $stockLevels[$variant->id] += self::RESTOCK_QTY;

                $movements[] = [
                    'tenant_id' => $tenant->id,
                    'product_variant_id' => $variant->id,
                    'type' => 'restock',
                    'qty' => self::RESTOCK_QTY,
                    'notes' => 'Restock bulanan '.$cursor->translatedFormat('F Y'),
                    'reference_id' => null,
                    'created_at' => $cursor->copy()->setTime(7, 0),
                ];
            }
            $cursor->addMonthNoOverflow();
        }

        if (! empty($movements)) {
            DB::table('stock_movements')->insert($movements);
        }

        return $stockLevels;
    }

    /**
     * Ekspresi SQL "bulan dari sebuah kolom tanggal" sebagai 'YYYY-MM'.
     *
     * MySQL dan SQLite tidak berbagi satu fungsi tanggal untuk ini, dan suite
     * test berjalan di SQLite sementara demo berjalan di MySQL.
     */
    private function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * @param  array<int, int>  $stockLevels
     * @return array<string, array{cash_collected: float, change_given: float, day: Carbon}> rekap kas per hari (key: Y-m-d)
     */
    private function seedDailyTransactions(
        Tenant $tenant,
        \Illuminate\Support\Collection $variants,
        \Illuminate\Support\Collection $paymentMethods,
        array $stockLevels
    ): array {
        $kasir = User::where('tenant_id', $tenant->id)->where('role', 'cashier')->firstOrFail();

        $start = Carbon::parse(self::START_DATE)->startOfDay();
        $end = Carbon::now()->startOfDay();

        $itemsBulk = [];
        $paymentsBulk = [];
        $movementsBulk = [];
        $totalTransactions = 0;
        $hariDiisi = 0;
        $hariDilewati = 0;
        $dailySummary = [];

        $hariTerisi = $this->existingSalesDays($tenant->id, $start, $end);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (isset($hariTerisi[$date->toDateString()])) {
                $hariDilewati++;

                continue;
            }
            $hariDiisi++;

            // Direset tiap hari: kode transaksi unik per (tenant, code) dan
            // tanggalnya sudah ikut di dalam kodenya, jadi penomoran per hari
            // tak pernah bertabrakan dengan hari yang disemai di kesempatan lain.
            $txCounter = 1;

            $isWeekend = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
            $dailyTarget = $isWeekend ? rand(1_600_000, 2_000_000) : rand(1_000_000, 1_600_000);

            $dailyRevenue = 0;
            $cashCollected = 0;
            $changeGiven = 0;
            $safetyCounter = 0;

            while ($dailyRevenue < $dailyTarget && $safetyCounter < 200) {
                $safetyCounter++;

                $itemCount = rand(1, 3);
                $available = $variants->filter(fn (ProductVariant $v) => $stockLevels[$v->id] >= 1);
                if ($available->isEmpty()) {
                    break;
                }

                $selectedVars = $available->random(min($itemCount, $available->count()));
                $totalAmount = 0;
                $txItemPayloads = [];

                foreach ($selectedVars as $variant) {
                    $maxQty = min(2, $stockLevels[$variant->id]);
                    if ($maxQty < 1) {
                        continue;
                    }
                    $qty = rand(1, $maxQty);
                    $stockLevels[$variant->id] -= $qty;

                    $subtotal = $variant->price * $qty;
                    $totalAmount += $subtotal;

                    $txItemPayloads[] = [
                        'product_variant_id' => $variant->id,
                        'variant_name' => $variant->name,
                        'qty' => $qty,
                        'unit_price' => $variant->price,
                        'subtotal' => $subtotal,
                    ];
                }

                if (empty($txItemPayloads)) {
                    continue;
                }

                $hour = $this->randomHour($isWeekend);
                $txTime = $date->copy()->setHour($hour)->setMinute(rand(0, 59))->setSecond(rand(0, 59));

                $code = 'TRX-'.$txTime->format('Ymd').'-'.str_pad($txCounter, 5, '0', STR_PAD_LEFT);
                $payMethod = $paymentMethods->random();

                if ($payMethod->type === 'cash') {
                    $roundTo = [1000, 2000, 5000][rand(0, 2)];
                    $tenderAmount = ceil($totalAmount / $roundTo) * $roundTo;
                    $changeAmount = $tenderAmount - $totalAmount;
                    $cashCollected += $tenderAmount;
                    $changeGiven += $changeAmount;
                } else {
                    $tenderAmount = $totalAmount;
                    $changeAmount = 0;
                }

                $txId = DB::table('transactions')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'user_id' => $kasir->id,
                    'code' => $code,
                    'status' => 'completed',
                    'total_amount' => $totalAmount,
                    'change_amount' => $changeAmount,
                    'source' => 'pos',
                    'order_type' => rand(0, 1) ? 'dine_in' : 'pickup',
                    'fulfillment_status' => null,
                    'customer_name' => null,
                    'table_number' => null,
                    'notes' => null,
                    'created_at' => $txTime,
                    'updated_at' => $txTime,
                ]);

                foreach ($txItemPayloads as $item) {
                    $item['transaction_id'] = $txId;
                    $itemsBulk[] = $item;

                    $movementsBulk[] = [
                        'tenant_id' => $tenant->id,
                        'product_variant_id' => $item['product_variant_id'],
                        'type' => 'sale',
                        'qty' => -$item['qty'],
                        'notes' => 'Penjualan '.$code,
                        'reference_id' => $txId,
                        'created_at' => $txTime,
                    ];
                }

                $paymentsBulk[] = [
                    'transaction_id' => $txId,
                    'payment_method_id' => $payMethod->id,
                    'amount' => $tenderAmount,
                    'reference_code' => null,
                    'created_at' => $txTime,
                ];

                $dailyRevenue += $totalAmount;
                $txCounter++;
                $totalTransactions++;

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

            $dailySummary[$date->toDateString()] = [
                'cash_collected' => $cashCollected,
                'change_given' => $changeGiven,
                'day' => $date->copy(),
            ];
        }

        if (! empty($itemsBulk)) {
            DB::table('transaction_items')->insert($itemsBulk);
        }
        if (! empty($paymentsBulk)) {
            DB::table('transaction_payments')->insert($paymentsBulk);
        }
        if (! empty($movementsBulk)) {
            DB::table('stock_movements')->insert($movementsBulk);
        }

        foreach ($stockLevels as $variantId => $stock) {
            DB::table('product_variants')->where('id', $variantId)->update(['stock' => $stock]);
        }

        $this->command->info("Transaksi harian: {$totalTransactions} baris pada {$hariDiisi} hari kosong; {$hariDilewati} hari dilewati ({$start->toDateString()} s.d. {$end->toDateString()}).");

        return $dailySummary;
    }

    /**
     * Sesi kas harian per kasir, dihitung dari kas cash & kembalian hari itu
     * (logika sama seperti CashDrawerController::close()). Selisih penutupan
     * kas disebar tiga kasus: pas (selisih 0), minus (kurang), dan plus (lebih),
     * agar data demo mencakup skenario nyata rekonsiliasi kas.
     *
     * updateOrCreate dikunci ke tenant_id+user_id+opened_at supaya seeder ini
     * aman dijalankan ulang tanpa membuat sesi kas duplikat. Yang masuk ke sini
     * hanya hari yang penjualannya baru saja dibuat seeder ini; hari yang sudah
     * berisi tidak ikut, karena angka kasnya harus tetap cocok dengan penjualan
     * tunai yang sudah tercatat di sana.
     *
     * @param  array<string, array{cash_collected: float, change_given: float, day: Carbon}>  $dailySummary
     */
    private function seedCashDrawerSessions(Tenant $tenant, array $dailySummary): void
    {
        $kasir = User::where('tenant_id', $tenant->id)->where('role', 'cashier')->firstOrFail();

        foreach ($dailySummary as $data) {
            $day = $data['day'];
            $expectedAmount = self::OPENING_CASH + $data['cash_collected'] - $data['change_given'];

            $variance = rand(1, 100);
            if ($variance <= 55) {
                // Pas — selisih 0, hitungan kasir sama dengan sistem.
                $difference = 0;
                $notes = null;
            } elseif ($variance <= 78) {
                // Minus — kasir kurang (mis. salah kembalian, uang terselip).
                $difference = -1 * ([2000, 5000, 8000, 10000, 15000, 20000][rand(0, 5)]);
                $notes = 'Selisih kurang Rp'.number_format(abs($difference), 0, ',', '.').' — kas fisik lebih kecil dari catatan sistem.';
            } else {
                // Plus — kasir lebih (mis. kembalian kurang diberikan ke customer).
                $difference = [2000, 5000, 8000, 10000, 15000, 20000][rand(0, 5)];
                $notes = 'Selisih lebih Rp'.number_format($difference, 0, ',', '.').' — kas fisik lebih besar dari catatan sistem.';
            }

            $closingAmount = $expectedAmount + $difference;
            $openedAt = $day->copy()->setTime(7, 0);
            $closedAt = $day->copy()->setTime(22, 0);

            CashDrawer::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $kasir->id,
                    'opened_at' => $openedAt,
                ],
                [
                    'opening_amount' => self::OPENING_CASH,
                    'closing_amount' => $closingAmount,
                    'expected_amount' => $expectedAmount,
                    'difference' => $difference,
                    'notes' => $notes,
                    'closed_at' => $closedAt,
                ]
            );
        }

        $this->command->info('Sesi kas harian: '.count($dailySummary).' sesi (pas/minus/plus).');
    }

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
        $rand = rand(1, $total);
        $cumul = 0;
        foreach ($buckets as $hour => $weight) {
            $cumul += $weight;
            if ($rand <= $cumul) {
                return $hour;
            }
        }

        return 10;
    }
}
