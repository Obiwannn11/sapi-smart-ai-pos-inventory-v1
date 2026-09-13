<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellEvent;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\BusinessPresetService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant uji: Squid Coffee & Eatery, kafe 24 jam di Jl. Topaz Raya No.3,
 * Panakkukang, Makassar.
 *
 * Menu lengkapnya tidak dipublikasikan di luar Instagram/TikTok mereka, jadi
 * daftar di bawah adalah REKONSTRUKSI dari yang bisa dipastikan: kafe 24 jam
 * dengan espresso-based, manual brew, non-kopi, teh, makanan berat (nasi
 * goreng dll.) dan camilan; minuman mulai Rp8.000, kopi Rp20–35 ribu, makanan
 * berat Rp30–50 ribu. Nama dan harga per item adalah perkiraan, bukan salinan.
 *
 * Akun terdaftar 15 Juli 2026. Omzet bulan pertama (15 Jul – 14 Agu) ±Rp50 juta,
 * bulan kedua (15 Agu – 14 Sep) ±Rp70 juta. Aturan saran jual dibuat owner
 * 1 Agustus, jadi event upsell baru muncul sejak tanggal itu.
 *
 * Tidak idempoten: menolak berjalan bila tenant `squid-coffee` sudah ada.
 */
class SquidCoffeeSeeder extends Seeder
{
    private const SLUG = 'squid-coffee';

    private const SIGNUP_AT = '2026-07-15 10:00:00';

    private const RULES_CREATED_AT = '2026-08-01 09:00:00';

    private const LAST_SALES_DAY = '2026-09-14';

    private const OPENING_CASH = 500000;

    /** @var array<int, array{0: string, 1: string, 2: int}> */
    private const MONTHS = [
        ['2026-07-15', '2026-08-14', 50_000_000],
        ['2026-08-15', '2026-09-14', 70_000_000],
    ];

    /** @var array<int, int> bobot jam buka 24 jam — ramai sore sampai tengah malam */
    private const HOUR_WEIGHTS = [
        0 => 5, 1 => 3, 2 => 2, 3 => 1, 4 => 1, 5 => 1, 6 => 2, 7 => 4,
        8 => 6, 9 => 7, 10 => 7, 11 => 7, 12 => 8, 13 => 8, 14 => 7, 15 => 7,
        16 => 8, 17 => 8, 18 => 9, 19 => 11, 20 => 12, 21 => 12, 22 => 10, 23 => 7,
    ];

    private const CUSTOMER_NAMES = [
        'Andi', 'Fajar', 'Rahmat', 'Nurul', 'Ayu', 'Dewi', 'Rizky', 'Ilham', 'Wahyu', 'Fitri',
        'Sari', 'Anto', 'Ical', 'Uni', 'Mega', 'Putri', 'Yusuf', 'Irma', 'Arif', 'Syahrul',
        'Asri', 'Rini', 'Taufik', 'Halim', 'Nisa', 'Farhan', 'Dian', 'Aco', 'Becce', 'Ulla',
    ];

    public function run(): void
    {
        if (Tenant::where('slug', self::SLUG)->exists()) {
            $this->command?->error('Tenant squid-coffee sudah ada. Hapus dulu bila ingin menyemai ulang.');

            return;
        }

        $realNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse(self::SIGNUP_AT));

        try {
            [$tenant, $cashier] = $this->seedTenantAndUsers();
            $variants = $this->seedMenu($tenant);
            $paymentMethods = $this->seedPaymentMethods($tenant);
        } finally {
            Carbon::setTestNow($realNow);
        }

        $rules = $this->seedUpsellRules($tenant, $variants);

        $soldPerWindow = $this->seedTransactions($tenant, $cashier, $variants, $paymentMethods, $rules);
        $this->seedStock($tenant, $variants, $soldPerWindow);

        $this->command?->info('✅ SquidCoffeeSeeder selesai. Login: owner@squid.id / kasir@squid.id (password: password)');
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function seedTenantAndUsers(): array
    {
        $presets = app(BusinessPresetService::class);

        $tenant = Tenant::create([
            'name' => 'Squid Coffee & Eatery',
            'slug' => self::SLUG,
            'business_type' => 'kuliner',
            'selling_style' => 'warung_menetap',
            'address' => 'Jl. Topaz Raya No.3, Panakkukang, Makassar, Sulawesi Selatan',
            'status' => Tenant::STATUS_TRIAL,
            'is_demo' => true,
            ...$presets->presetColumnsFor('warung_menetap'),
            ...$presets->columnsFor($presets->featuresFor('warung_menetap')),
        ]);

        $tenant->forceFill([
            'upsell_mandatory' => false,
            'upsell_attach_enabled' => true,
            'upsell_pressed_stock_enabled' => true,
            'upsell_upsize_enabled' => true,
            'upsell_manual_enabled' => true,
        ])->save();

        $subscription = app(SubscriptionService::class)->startTrial($tenant);

        // Masa gratis dua bulan berakhir 15 September. Supaya tenant uji ini
        // tidak masuk tenggang besok, anggap owner sudah membayar periode
        // berikutnya di muka.
        $paidPlan = Plan::where('is_post_trial_target', true)->first() ?? Plan::default();
        $subscription->update([
            'plan_id' => $paidPlan->id,
            'seats' => max(2, $paidPlan->included_seats),
            'seat_high_water' => 2,
            'price_locked' => $paidPlan->base_price > 0 ? $paidPlan->base_price : null,
            'current_period_start' => '2026-09-15',
            'current_period_end' => '2026-10-15',
        ]);
        $tenant->update(['status' => Tenant::STATUS_ACTIVE]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner Squid Coffee',
            'email' => 'owner@squid.id',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        $cashier = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Kasir Squid Coffee',
            'email' => 'kasir@squid.id',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'email_verified_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $cashierRole = Role::findOrCreate('Kasir', 'web');
        $cashierRole->syncPermissions(['pos', 'cash_drawer', 'stock']);
        $cashier->assignRole($cashierRole);

        return [$tenant, $cashier];
    }

    /**
     * Varian dikembalikan dengan atribut bantu `weight` (seberapa laris) dan
     * `category_name`, keduanya hanya dipakai di memori.
     *
     * @return Collection<string, ProductVariant> dikunci "Produk|Varian"
     */
    private function seedMenu(Tenant $tenant): Collection
    {
        // produk, kategori, [[varian, harga, modal, bobot laris]]
        $menu = [
            ['Espresso', 'Espresso Based', [['Single', 18000, 5000, 3], ['Double', 23000, 7000, 2]]],
            ['Americano', 'Espresso Based', [['Hot', 22000, 6000, 6], ['Ice', 24000, 6500, 9]]],
            ['Cappuccino', 'Espresso Based', [['Hot', 27000, 8500, 5], ['Ice', 29000, 9000, 6]]],
            ['Cafe Latte', 'Espresso Based', [['Hot', 27000, 8500, 5], ['Ice', 29000, 9000, 8]]],
            ['Caramel Macchiato', 'Espresso Based', [['Hot', 32000, 10000, 3], ['Ice', 34000, 10500, 6]]],
            ['Vanilla Latte', 'Espresso Based', [['Ice', 32000, 10000, 5]]],

            ['Es Kopi Susu Squid', 'Signature', [['Regular', 22000, 7000, 16], ['Large', 28000, 9000, 9]]],
            ['Kopi Susu Gula Aren', 'Signature', [['Regular', 24000, 7500, 11], ['Large', 30000, 9500, 6]]],
            ['Squid Black Charcoal Latte', 'Signature', [['Ice', 33000, 11000, 5]]],
            ['Butterscotch Latte', 'Signature', [['Ice', 32000, 10500, 6]]],
            ['Kopi Pandan', 'Signature', [['Ice', 28000, 9000, 4]]],

            ['V60 Manual Brew', 'Manual Brew', [['Toraja Sapan', 30000, 11000, 3], ['Kalosi Enrekang', 32000, 12000, 3], ['Gayo', 30000, 11000, 2]]],
            ['Japanese Iced Coffee', 'Manual Brew', [['Ice', 32000, 11500, 2]]],

            ['Matcha Latte', 'Non Coffee', [['Hot', 30000, 10500, 2], ['Ice', 32000, 11000, 6]]],
            ['Chocolate', 'Non Coffee', [['Hot', 27000, 9000, 2], ['Ice', 29000, 9500, 5]]],
            ['Red Velvet Latte', 'Non Coffee', [['Ice', 29000, 9500, 4]]],
            ['Taro Latte', 'Non Coffee', [['Ice', 29000, 9500, 4]]],

            ['Es Teh Manis', 'Tea & Refresher', [['Regular', 10000, 2500, 9]]],
            ['Lemon Tea', 'Tea & Refresher', [['Hot', 16000, 4000, 2], ['Ice', 18000, 4500, 6]]],
            ['Lychee Tea', 'Tea & Refresher', [['Ice', 22000, 6500, 5]]],
            ['Squid Blue Ocean', 'Tea & Refresher', [['Ice', 25000, 7500, 3]]],
            ['Air Mineral', 'Tea & Refresher', [['Botol 600ml', 8000, 3000, 4]]],

            ['Nasi Goreng Squid', 'Main Course', [['Original', 32000, 13000, 8], ['Seafood', 42000, 19000, 4]]],
            ['Nasi Goreng Merah Makassar', 'Main Course', [['Porsi', 35000, 14000, 5]]],
            ['Chicken Katsu Rice Bowl', 'Main Course', [['Porsi', 38000, 16000, 6]]],
            ['Rice Bowl Ayam Sambal Matah', 'Main Course', [['Porsi', 35000, 14500, 5]]],
            ['Spaghetti Aglio Olio', 'Main Course', [['Chicken', 38000, 15000, 3], ['Shrimp', 45000, 20000, 2]]],
            ['Mie Goreng Jawa', 'Main Course', [['Porsi', 30000, 12000, 3]]],
            ['Indomie Telur', 'Main Course', [['Goreng', 18000, 7000, 6], ['Kuah', 18000, 7000, 3]]],
            ['Cumi Goreng Tepung', 'Main Course', [['Porsi', 38000, 17000, 3]]],

            ['French Fries', 'Snack', [['Regular', 22000, 8000, 6]]],
            ['Pisang Goreng Keju', 'Snack', [['Porsi', 22000, 8000, 5]]],
            ['Roti Bakar', 'Snack', [['Coklat Keju', 22000, 8500, 4], ['Srikaya', 20000, 7500, 2]]],
            ['Croffle', 'Snack', [['Original', 20000, 7500, 5], ['Chocolate', 25000, 9500, 3]]],
            ['Chicken Wings', 'Snack', [['6 pcs', 35000, 15000, 3]]],
            ['Mix Platter', 'Snack', [['Porsi', 42000, 18000, 3]]],
        ];

        $categories = collect($menu)->pluck(1)->unique()->mapWithKeys(
            fn (string $name) => [$name => Category::create(['tenant_id' => $tenant->id, 'name' => $name])]
        );

        $products = collect();
        $variants = collect();

        foreach ($menu as [$productName, $categoryName, $variantDefs]) {
            $product = Product::create([
                'tenant_id' => $tenant->id,
                'category_id' => $categories[$categoryName]->id,
                'name' => $productName,
                'is_active' => true,
            ]);
            $products->put($productName, [$product, $categoryName]);

            foreach ($variantDefs as [$variantName, $price, $cost, $weight]) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variantName,
                    'price' => $price,
                    'cost_price' => $cost,
                    'stock' => 0,
                ]);
                $variant->setAttribute('weight', $weight);
                $variant->setAttribute('product_name', $productName);
                $variants->put("{$productName}|{$variantName}", $variant);
            }
        }

        $this->seedModifiers($tenant, $products);

        return $variants;
    }

    /**
     * @param  Collection<string, array{0: Product, 1: string}>  $products
     */
    private function seedModifiers(Tenant $tenant, Collection $products): void
    {
        $sugar = ModifierGroup::create(['tenant_id' => $tenant->id, 'name' => 'Sugar Level', 'is_required' => false, 'is_multiple' => false]);
        foreach (['Normal Sugar', 'Less Sugar', 'No Sugar'] as $name) {
            Modifier::create(['modifier_group_id' => $sugar->id, 'name' => $name, 'extra_price' => 0]);
        }

        $addOns = ModifierGroup::create(['tenant_id' => $tenant->id, 'name' => 'Add-ons', 'is_required' => false, 'is_multiple' => true]);
        foreach ([['Extra Shot', 6000], ['Oat Milk', 8000], ['Hazelnut Syrup', 5000], ['Cheese Foam', 7000]] as [$name, $price]) {
            Modifier::create(['modifier_group_id' => $addOns->id, 'name' => $name, 'extra_price' => $price]);
        }

        $spicy = ModifierGroup::create(['tenant_id' => $tenant->id, 'name' => 'Level Pedas', 'is_required' => false, 'is_multiple' => false]);
        foreach (['Tidak Pedas', 'Sedang', 'Pedas', 'Extra Pedas'] as $name) {
            Modifier::create(['modifier_group_id' => $spicy->id, 'name' => $name, 'extra_price' => 0]);
        }

        foreach ($products as [$product, $categoryName]) {
            $groups = match ($categoryName) {
                'Espresso Based', 'Signature', 'Non Coffee' => [$sugar->id, $addOns->id],
                'Manual Brew', 'Tea & Refresher' => [$sugar->id],
                'Main Course' => [$spicy->id],
                default => [],
            };

            if ($groups !== []) {
                $product->modifierGroups()->attach($groups);
            }
        }
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    private function seedPaymentMethods(Tenant $tenant): Collection
    {
        return collect([
            ['Cash', 'cash'],
            ['QRIS', 'qris_static'],
            ['Transfer BCA', 'bank_transfer'],
        ])->map(fn (array $method) => PaymentMethod::create([
            'tenant_id' => $tenant->id,
            'name' => $method[0],
            'type' => $method[1],
            'is_active' => true,
        ]));
    }

    /**
     * Aturan saran jual yang ditulis owner sendiri pada 1 Agustus.
     *
     * @param  Collection<string, ProductVariant>  $variants
     * @return Collection<int, UpsellRule>
     */
    private function seedUpsellRules(Tenant $tenant, Collection $variants): Collection
    {
        // pemicu (null = selalu tampil), disarankan, prioritas, catatan, mulai
        $definitions = [
            ['Es Kopi Susu Squid|Regular', 'Croffle|Original', 90, 'Kopi susu + croffle, paket nongkrong', null],
            ['Nasi Goreng Squid|Original', 'Es Teh Manis|Regular', 85, 'Nasi goreng tawarkan es teh', null],
            ['Americano|Hot', 'Pisang Goreng Keju|Porsi', 80, 'Americano panas cocok dengan pisang goreng', null],
            ['Chicken Katsu Rice Bowl|Porsi', 'Lychee Tea|Ice', 75, 'Rice bowl + lychee tea', null],
            ['Indomie Telur|Goreng', 'Lemon Tea|Ice', 70, 'Menu tengah malam: indomie + lemon tea', null],
            ['Cafe Latte|Ice', 'Roti Bakar|Coklat Keju', 60, 'Latte dingin + roti bakar', null],
            [null, 'Squid Black Charcoal Latte|Ice', 50, 'Dorong signature charcoal latte', null],
            [null, 'Cumi Goreng Tepung|Porsi', 40, 'Promo cumi goreng September', '2026-09-01'],
        ];

        $realNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse(self::RULES_CREATED_AT));

        try {
            return collect($definitions)->map(fn (array $rule) => UpsellRule::create([
                'tenant_id' => $tenant->id,
                'trigger_variant_id' => $rule[0] !== null ? $variants[$rule[0]]->id : null,
                'suggested_variant_id' => $variants[$rule[1]]->id,
                'priority' => $rule[2],
                'note' => $rule[3],
                'starts_on' => $rule[4],
                'is_active' => true,
            ]));
        } finally {
            Carbon::setTestNow($realNow);
        }
    }

    /**
     * Semai transaksi harian dan sesi kas per hari, lalu kembalikan jumlah
     * terjual per varian per jendela restock supaya stok bisa disusun mundur.
     *
     * @param  Collection<string, ProductVariant>  $variants
     * @param  Collection<int, PaymentMethod>  $paymentMethods
     * @param  Collection<int, UpsellRule>  $rules
     * @return array<string, array<int, int>> [tanggal restock => [variant_id => qty]]
     */
    private function seedTransactions(Tenant $tenant, User $cashier, Collection $variants, Collection $paymentMethods, Collection $rules): array
    {
        $variantList = $variants->values();
        $variantById = $variantList->keyBy('id');
        $weightTotal = $variantList->sum('weight');
        $triggeredRules = $rules->whereNotNull('trigger_variant_id')->keyBy('trigger_variant_id');
        $rulesStart = Carbon::parse(self::RULES_CREATED_AT);
        $now = Carbon::now();

        $soldPerWindow = [];
        $grandTotal = 0;
        $transactionCount = 0;

        foreach (self::MONTHS as [$monthStart, $monthEnd, $monthTarget]) {
            $days = [];
            for ($date = Carbon::parse($monthStart); $date->lte(Carbon::parse($monthEnd)); $date->addDay()) {
                if ($date->gt(Carbon::parse(self::LAST_SALES_DAY))) {
                    break;
                }
                $days[] = [$date->copy(), $this->dayWeight($date)];
            }

            $remainingWeight = array_sum(array_column($days, 1));
            $monthRevenue = 0;

            foreach ($days as [$date, $weight]) {
                $dayTarget = ($monthTarget - $monthRevenue) * $weight / $remainingWeight;
                $remainingWeight -= $weight;

                $isFirstDay = $date->isSameDay(Carbon::parse(self::SIGNUP_AT));
                $isToday = $date->isSameDay($now);
                $dayOpensAt = $isFirstDay ? Carbon::parse(self::SIGNUP_AT)->addMinutes(30) : $date->copy()->startOfDay();
                $dayClosesAt = $isToday ? $now->copy() : $date->copy()->setTime(23, 59, 59);

                $drawerId = DB::table('cash_drawers')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'user_id' => $cashier->id,
                    'opening_amount' => self::OPENING_CASH,
                    'opened_at' => $dayOpensAt,
                    'created_at' => $dayOpensAt,
                    'updated_at' => $dayOpensAt,
                ]);

                $window = $this->restockWindowFor($date);
                $dayRevenue = 0;
                $cashIn = 0;
                $counter = 1;
                $items = [];
                $payments = [];
                $movements = [];
                $events = [];

                while ($dayRevenue < $dayTarget && $counter < 400) {
                    $time = $this->randomTimeOn($date, $dayOpensAt, $dayClosesAt);
                    $picked = $this->pickVariants($variantList, $weightTotal);

                    $lines = [];
                    foreach ($picked as $variant) {
                        $lines[$variant->id] = rand(1, 100) <= 85 ? 1 : 2;
                    }

                    $txEvents = [];
                    if ($time->gte($rulesStart)) {
                        foreach (array_keys($lines) as $variantId) {
                            $rule = $triggeredRules->get($variantId);
                            if ($rule === null || isset($lines[$rule->suggested_variant_id])) {
                                continue;
                            }
                            if ($rule->starts_on !== null && $time->lt($rule->starts_on)) {
                                continue;
                            }

                            $suggested = $variantById[$rule->suggested_variant_id];
                            $roll = rand(1, 100);
                            $status = $roll <= 35 ? UpsellEvent::STATUS_ACCEPTED
                                : ($roll <= 55 ? UpsellEvent::STATUS_REJECTED : UpsellEvent::STATUS_IGNORED);

                            if ($status === UpsellEvent::STATUS_ACCEPTED) {
                                $lines[$suggested->id] = 1;
                            }

                            $txEvents[] = [
                                'tenant_id' => $tenant->id,
                                'type' => UpsellEvent::TYPE_MANUAL,
                                'surface' => UpsellEvent::SURFACE_POS,
                                'status' => $status,
                                'reason' => UpsellEvent::REASON_OWNER_RULE,
                                'trigger_variant_id' => $variantId,
                                'suggested_variant_id' => $suggested->id,
                                'suggested_modifier_id' => null,
                                'label' => "{$suggested->product_name} {$suggested->name}",
                                'extra_amount' => $status === UpsellEvent::STATUS_ACCEPTED ? $suggested->price : 0,
                                'created_at' => $time,
                                'updated_at' => $time,
                            ];
                        }
                    }

                    $total = 0;
                    foreach ($lines as $variantId => $qty) {
                        $total += $variantById[$variantId]->price * $qty;
                    }

                    $method = $this->pickPaymentMethod($paymentMethods);
                    $tender = $total;
                    if ($method->type === 'cash') {
                        $roundTo = [1000, 5000, 10000, 50000][rand(0, 3)];
                        $tender = (int) (ceil($total / $roundTo) * $roundTo);
                        $cashIn += $total;
                    }

                    $code = 'TRX-'.$date->format('Ymd').'-'.str_pad((string) $counter, 5, '0', STR_PAD_LEFT);
                    $orderType = rand(1, 100) <= 70 ? 'dine_in' : 'pickup';

                    $transactionId = DB::table('transactions')->insertGetId([
                        'tenant_id' => $tenant->id,
                        'user_id' => $cashier->id,
                        'cash_drawer_id' => $drawerId,
                        'code' => $code,
                        'status' => 'completed',
                        'subtotal_amount' => $total,
                        'tax_amount' => 0,
                        'service_charge_amount' => 0,
                        'total_amount' => $total,
                        'change_amount' => $tender - $total,
                        'source' => 'pos',
                        'order_type' => $orderType,
                        'fulfillment_status' => 'done',
                        'customer_name' => self::CUSTOMER_NAMES[array_rand(self::CUSTOMER_NAMES)],
                        'channel' => 'online',
                        'occurred_at' => $time,
                        'created_at' => $time,
                        'updated_at' => $time,
                    ]);

                    foreach ($lines as $variantId => $qty) {
                        $variant = $variantById[$variantId];
                        $items[] = [
                            'transaction_id' => $transactionId,
                            'product_variant_id' => $variantId,
                            'variant_name' => $variant->name,
                            'qty' => $qty,
                            'unit_price' => $variant->price,
                            'discount_amount' => 0,
                            'cost_price_at_sale' => $variant->cost_price,
                            'subtotal' => $variant->price * $qty,
                        ];
                        $movements[] = [
                            'tenant_id' => $tenant->id,
                            'product_variant_id' => $variantId,
                            'type' => 'sale',
                            'qty' => -$qty,
                            'notes' => 'Penjualan '.$code,
                            'reference_id' => $transactionId,
                            'created_at' => $time,
                        ];
                        $soldPerWindow[$window][$variantId] = ($soldPerWindow[$window][$variantId] ?? 0) + $qty;
                    }

                    foreach ($txEvents as $event) {
                        $events[] = ['transaction_id' => $transactionId, ...$event];
                    }

                    $payments[] = [
                        'transaction_id' => $transactionId,
                        'payment_method_id' => $method->id,
                        'amount' => $tender,
                        'reference_code' => null,
                        'created_at' => $time,
                    ];

                    $dayRevenue += $total;
                    $counter++;
                }

                foreach (array_chunk($items, 500) as $chunk) {
                    DB::table('transaction_items')->insert($chunk);
                }
                foreach (array_chunk($movements, 500) as $chunk) {
                    DB::table('stock_movements')->insert($chunk);
                }
                foreach (array_chunk($payments, 500) as $chunk) {
                    DB::table('transaction_payments')->insert($chunk);
                }
                foreach (array_chunk($events, 500) as $chunk) {
                    DB::table('upsell_events')->insert($chunk);
                }

                if (! $isToday) {
                    $this->closeDrawer($drawerId, $cashIn, $date);
                }

                $monthRevenue += $dayRevenue;
                $transactionCount += $counter - 1;
            }

            $grandTotal += $monthRevenue;
            $this->command?->info("Omzet {$monthStart} s.d. {$monthEnd}: Rp".number_format($monthRevenue, 0, ',', '.'));
        }

        $this->command?->info("Total {$transactionCount} transaksi, omzet Rp".number_format($grandTotal, 0, ',', '.'));

        return $soldPerWindow;
    }

    /**
     * Restock di awal tiap jendela sebanyak yang akan terjual di jendela itu,
     * ditambah stok akhir acak pada restock pertama. Stok berjalan tidak pernah
     * negatif, dan stok akhir tiap varian = angka acak tersebut.
     *
     * @param  Collection<string, ProductVariant>  $variants
     * @param  array<string, array<int, int>>  $soldPerWindow
     */
    private function seedStock(Tenant $tenant, Collection $variants, array $soldPerWindow): void
    {
        $movements = [];

        foreach ($variants as $variant) {
            $roll = rand(1, 100);
            $finalStock = match (true) {
                $roll <= 8 => rand(1, 4),
                $roll <= 25 => rand(5, 15),
                default => rand(25, 180),
            };

            foreach ($this->restockWindows() as $window => $restockAt) {
                $qty = ($soldPerWindow[$window][$variant->id] ?? 0);
                if ($window === array_key_first($this->restockWindows())) {
                    $qty += $finalStock;
                }
                if ($qty === 0) {
                    continue;
                }

                $movements[] = [
                    'tenant_id' => $tenant->id,
                    'product_variant_id' => $variant->id,
                    'type' => 'restock',
                    'qty' => $qty,
                    'notes' => $window === array_key_first($this->restockWindows()) ? 'Stok awal' : 'Restock '.Carbon::parse($restockAt)->translatedFormat('j F Y'),
                    'reference_id' => null,
                    'created_at' => $restockAt,
                ];
            }

            DB::table('product_variants')->where('id', $variant->id)->update(['stock' => $finalStock]);
        }

        foreach (array_chunk($movements, 500) as $chunk) {
            DB::table('stock_movements')->insert($chunk);
        }
    }

    /**
     * @return array<string, string> [kunci jendela => waktu restock]
     */
    private function restockWindows(): array
    {
        return [
            '2026-07-15' => '2026-07-15 10:15:00',
            '2026-08-01' => '2026-08-01 00:00:00',
            '2026-08-15' => '2026-08-15 00:00:00',
            '2026-09-01' => '2026-09-01 00:00:00',
        ];
    }

    private function restockWindowFor(Carbon $date): string
    {
        $current = array_key_first($this->restockWindows());

        foreach (array_keys($this->restockWindows()) as $windowStart) {
            if ($date->gte(Carbon::parse($windowStart))) {
                $current = $windowStart;
            }
        }

        return $current;
    }

    private function dayWeight(Carbon $date): float
    {
        $weight = match ($date->dayOfWeek) {
            Carbon::FRIDAY => 1.2,
            Carbon::SATURDAY => 1.4,
            Carbon::SUNDAY => 1.25,
            default => 1.0,
        };

        if ($date->format('m-d') === '08-17') {
            $weight *= 1.3;
        }

        return $weight * rand(85, 115) / 100;
    }

    private function randomTimeOn(Carbon $date, Carbon $opensAt, Carbon $closesAt): Carbon
    {
        $roll = rand(1, array_sum(self::HOUR_WEIGHTS));
        $hour = 12;
        foreach (self::HOUR_WEIGHTS as $candidate => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                $hour = $candidate;
                break;
            }
        }

        $time = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));

        if ($time->lt($opensAt) || $time->gt($closesAt)) {
            $time = Carbon::createFromTimestamp(rand($opensAt->timestamp, max($opensAt->timestamp, $closesAt->timestamp)), $date->getTimezone());
        }

        return $time;
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<int, ProductVariant>
     */
    private function pickVariants(Collection $variants, int $weightTotal): array
    {
        $roll = rand(1, 100);
        $count = $roll <= 35 ? 1 : ($roll <= 70 ? 2 : ($roll <= 90 ? 3 : 4));

        $picked = [];
        $guard = 0;
        while (count($picked) < $count && $guard++ < 20) {
            $target = rand(1, $weightTotal);
            foreach ($variants as $variant) {
                $target -= $variant->weight;
                if ($target <= 0) {
                    $picked[$variant->id] = $variant;
                    break;
                }
            }
        }

        return array_values($picked);
    }

    /**
     * @param  Collection<int, PaymentMethod>  $methods
     */
    private function pickPaymentMethod(Collection $methods): PaymentMethod
    {
        $roll = rand(1, 100);
        $type = $roll <= 50 ? 'qris_static' : ($roll <= 85 ? 'cash' : 'bank_transfer');

        return $methods->firstWhere('type', $type);
    }

    private function closeDrawer(int $drawerId, int $cashIn, Carbon $date): void
    {
        $expected = self::OPENING_CASH + $cashIn;
        $roll = rand(1, 100);
        $difference = match (true) {
            $roll <= 60 => 0,
            $roll <= 80 => -[2000, 5000, 10000, 20000][rand(0, 3)],
            default => [1000, 2000, 5000, 10000][rand(0, 3)],
        };
        $closedAt = $date->copy()->setTime(23, 59, 59);

        DB::table('cash_drawers')->where('id', $drawerId)->update([
            'expected_amount' => $expected,
            'closing_amount' => $expected + $difference,
            'difference' => $difference,
            'notes' => $difference === 0 ? null : ($difference < 0 ? 'Kas fisik kurang dari catatan sistem.' : 'Kas fisik lebih dari catatan sistem.'),
            'closed_at' => $closedAt,
            'updated_at' => $closedAt,
        ]);
    }
}
