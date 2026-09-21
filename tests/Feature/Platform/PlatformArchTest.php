<?php

/**
 * Pertahanan utama batas privasi panel platform.
 *
 * Panel platform boleh tahu SIAPA kliennya (tenant, akun, kontak), tapi tidak
 * boleh tahu APA yang mereka jual atau berapa yang mereka jual. Aturan itu
 * mudah diucapkan dan mudah dilanggar tanpa sengaja — satu `use` yang tampak
 * praktis sudah cukup. Test ini yang menahannya.
 *
 * Batasnya: arch() memeriksa IMPOR, bukan perilaku. Traversal relasi dan query
 * mentah lolos dari sini — itu tugas PlatformIsolationTest.
 */
$operationalModels = [
    App\Models\Transaction::class,
    App\Models\TransactionItem::class,
    App\Models\TransactionPayment::class,
    App\Models\TransactionEdit::class,
    App\Models\TransactionItemModifier::class,
    App\Models\Product::class,
    App\Models\ProductVariant::class,
    App\Models\Category::class,
    App\Models\Modifier::class,
    App\Models\ModifierGroup::class,
    App\Models\StockMovement::class,
    App\Models\CashDrawer::class,
    App\Models\AiAnalysis::class,
    App\Models\AiUsage::class,
    App\Models\PaymentMethod::class,
];

arch('platform controllers never touch tenant operational models')
    ->expect('App\Http\Controllers\Platform')
    ->not->toUse($operationalModels);

// Berbasis NAMESPACE, bukan satu nama kelas: resource platform berikutnya ikut
// terjaga tanpa harus ingat menambahkannya ke daftar di sini.
arch('platform resources never touch tenant operational models')
    ->expect('App\Http\Resources\Platform')
    ->not->toUse($operationalModels);

arch('platform controllers do not reach for raw database queries')
    // DB::table() melewati Eloquent sekaligus global scope-nya — jalur paling
    // mudah untuk tanpa sadar membaca lintas tenant.
    ->expect('App\Http\Controllers\Platform')
    ->not->toUse([Illuminate\Support\Facades\DB::class]);
