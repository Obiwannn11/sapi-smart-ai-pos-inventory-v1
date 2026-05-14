<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SAPI Self Order
|--------------------------------------------------------------------------
| Prefix /api otomatis dari Laravel.
| Auth via Sanctum token (Bearer token dari n8n).
*/

// --- Authenticated Routes (Sanctum) ---
Route::middleware(['auth:sanctum'])->group(function () {

    // Daftar produk aktif + variant tersedia (untuk AI context di n8n)
    Route::get('/products', [\App\Http\Controllers\Api\ApiProductController::class, 'index']);

    // Buat self-order baru (dari n8n setelah AI parsing)
    Route::post('/orders', [\App\Http\Controllers\Api\ApiOrderController::class, 'store']);

    // Update fulfillment status (kasir advance status penyajian)
    Route::patch('/orders/{transaction}/fulfillment', [\App\Http\Controllers\Api\ApiOrderController::class, 'updateFulfillment']);

});

// --- Xendit Webhook (No Auth — verifikasi via x-callback-token) ---
Route::post('/xendit/webhook', [\App\Http\Controllers\Api\XenditWebhookController::class, 'handle']);

// ─────────────────────────────────────────────────────────────────
// MOBILE APP — POS Kasir
// ─────────────────────────────────────────────────────────────────

Route::post('/mobile/login', [\App\Http\Controllers\Api\Mobile\MobileAuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'tenant.api'])->group(function () {

    Route::post('/mobile/logout',
        [\App\Http\Controllers\Api\Mobile\MobileAuthController::class, 'logout']);

    Route::get('/mobile/tenant/profile',
        [\App\Http\Controllers\Api\Mobile\MobileTenantController::class, 'profile']);

    Route::get('/mobile/products',
        [\App\Http\Controllers\Api\ApiProductController::class, 'index']);

    Route::get('/mobile/cash-drawer/status',
        [\App\Http\Controllers\Api\Mobile\MobileCashDrawerController::class, 'status']);

    Route::post('/mobile/transactions',
        [\App\Http\Controllers\Api\Mobile\MobileTransactionController::class, 'store']);

    Route::get('/mobile/transactions/{transaction}/receipt',
        [\App\Http\Controllers\Api\Mobile\MobileTransactionController::class, 'receipt']);

});
