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
