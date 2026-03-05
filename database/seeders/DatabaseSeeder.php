<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tenant
        $tenant = Tenant::create([
            'name' => 'Kopi Nusantara',
            'slug' => 'kopi-nusantara',
        ]);

        // 2. Users
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner Demo',
            'email' => 'owner@sapi.test',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Kasir Demo',
            'email' => 'kasir@sapi.test',
            'password' => Hash::make('password'),
            'role' => 'cashier',
        ]);

        // 3. Categories
        $kopi = Category::create(['tenant_id' => $tenant->id, 'name' => 'Kopi']);
        $nonKopi = Category::create(['tenant_id' => $tenant->id, 'name' => 'Non-Kopi']);
        $makanan = Category::create(['tenant_id' => $tenant->id, 'name' => 'Makanan']);

        // 4. Products + Variants
        $espresso = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $kopi->id,
            'name' => 'Espresso',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $espresso->id,
            'name' => 'Single',
            'price' => 18000,
            'cost_price' => 5000,
            'stock' => 100,
        ]);
        ProductVariant::create([
            'product_id' => $espresso->id,
            'name' => 'Double',
            'price' => 25000,
            'cost_price' => 8000,
            'stock' => 100,
        ]);

        $latte = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $kopi->id,
            'name' => 'Cafe Latte',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $latte->id,
            'name' => 'Hot',
            'price' => 28000,
            'cost_price' => 8000,
            'stock' => 50,
        ]);
        ProductVariant::create([
            'product_id' => $latte->id,
            'name' => 'Iced',
            'price' => 30000,
            'cost_price' => 9000,
            'stock' => 50,
        ]);

        $matcha = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $nonKopi->id,
            'name' => 'Matcha Latte',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $matcha->id,
            'name' => 'Regular',
            'price' => 32000,
            'cost_price' => 12000,
            'stock' => 30,
        ]);

        $croissant = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $makanan->id,
            'name' => 'Croissant',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $croissant->id,
            'name' => 'Plain',
            'price' => 25000,
            'cost_price' => 10000,
            'stock' => 20,
            'expiry_date' => now()->addDays(3), // Untuk test badge near-expiry
        ]);

        // 5. Modifier Groups + Modifiers
        $tempGroup = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Temperature',
            'is_required' => true,
            'is_multiple' => false,
        ]);
        Modifier::create(['modifier_group_id' => $tempGroup->id, 'name' => 'Hot', 'extra_price' => 0]);
        Modifier::create(['modifier_group_id' => $tempGroup->id, 'name' => 'Iced', 'extra_price' => 3000]);

        $sugarGroup = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sugar Level',
            'is_required' => false,
            'is_multiple' => false,
        ]);
        Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Normal', 'extra_price' => 0]);
        Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Less Sugar', 'extra_price' => 0]);
        Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Extra Sweet', 'extra_price' => 0]);

        $addonGroup = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Add-ons',
            'is_required' => false,
            'is_multiple' => true,
        ]);
        Modifier::create(['modifier_group_id' => $addonGroup->id, 'name' => 'Extra Shot', 'extra_price' => 5000]);
        Modifier::create(['modifier_group_id' => $addonGroup->id, 'name' => 'Oat Milk', 'extra_price' => 8000]);

        // 6. Attach modifier groups ke produk kopi
        $espresso->modifierGroups()->attach([$tempGroup->id, $sugarGroup->id, $addonGroup->id]);
        $latte->modifierGroups()->attach([$tempGroup->id, $sugarGroup->id, $addonGroup->id]);
        $matcha->modifierGroups()->attach([$tempGroup->id, $sugarGroup->id]);

        // 7. Payment Methods
        PaymentMethod::create(['tenant_id' => $tenant->id, 'name' => 'Cash', 'type' => 'cash', 'is_active' => true]);
        PaymentMethod::create(['tenant_id' => $tenant->id, 'name' => 'QRIS', 'type' => 'qris_static', 'is_active' => true]);
        PaymentMethod::create(['tenant_id' => $tenant->id, 'name' => 'Transfer BCA', 'type' => 'bank_transfer', 'is_active' => true]);
    }
}
