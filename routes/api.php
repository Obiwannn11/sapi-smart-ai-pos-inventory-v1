<?php

use App\Http\Controllers\Api\V1\ApiOrderController;
use App\Http\Controllers\Api\V1\ApiProductController;
use App\Http\Controllers\Api\V1\Mobile\MobileAuthController;
use App\Http\Controllers\Api\V1\Mobile\MobileCashDrawerController;
use App\Http\Controllers\Api\V1\Mobile\MobileTenantController;
use App\Http\Controllers\Api\V1\Mobile\MobileTransactionController;
use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SAPI
|--------------------------------------------------------------------------
| Prefix /api otomatis dari Laravel.
| Semua endpoint consumer berada di bawah /api/v1/.
| Xendit webhook tidak diversi-kan (URL dikonfigurasi di dashboard eksternal).
*/

// --- Xendit Webhook (No Auth — verifikasi via x-callback-token) ---
Route::post('/xendit/webhook', [XenditWebhookController::class, 'handle']);

// ─────────────────────────────────────────────────────────────────
// API v1
// ─────────────────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // --- Self Order / n8n (Sanctum) ---
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/products', [ApiProductController::class, 'index']);
        Route::post('/orders', [ApiOrderController::class, 'store'])
            ->middleware('throttle:60,1');
        Route::patch('/orders/{transaction}/fulfillment', [ApiOrderController::class, 'updateFulfillment']);
    });

    // ─── Mobile App POS ───────────────────────────────────────────

    // Login (public, throttled). Limiter bernama, bukan `throttle:5,1`: yang
    // terakhir mengunci per IP saja, sehingga beberapa perangkat kasir di balik
    // satu IP publik saling menghabiskan jatah. `mobile-login` mengunci per
    // email + IP — lihat AppServiceProvider.
    Route::post('/mobile/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:mobile-login');

    // Protected: auth + tenant
    Route::middleware(['auth:sanctum', 'tenant.api'])->group(function () {
        Route::post('/mobile/logout', [MobileAuthController::class, 'logout']);
        Route::get('/mobile/tenant/profile', [MobileTenantController::class, 'profile']);
        Route::get('/mobile/products', [ApiProductController::class, 'index']);
        Route::get('/mobile/cash-drawer/status', [MobileCashDrawerController::class, 'status']);
        Route::post('/mobile/transactions', [MobileTransactionController::class, 'store']);
        Route::get('/mobile/transactions/{transaction}/receipt', [MobileTransactionController::class, 'receipt']);
    });

    // Kasir + Owner: operasi kas & transaksi
    Route::middleware(['auth:sanctum', 'tenant.api', 'role:cashier,owner'])->group(function () {
        Route::post('/mobile/cash-drawer/open', [MobileCashDrawerController::class, 'open']);
        Route::post('/mobile/cash-drawer/close', [MobileCashDrawerController::class, 'close']);
        Route::get('/mobile/cash-drawer/{cashDrawer}/summary', [MobileCashDrawerController::class, 'summary']);
        Route::post('/mobile/transactions/{transaction}/pay', [MobileTransactionController::class, 'pay']);
        Route::get('/mobile/transactions', [MobileTransactionController::class, 'index']);
    });

    // Owner only: void transaksi
    Route::middleware(['auth:sanctum', 'tenant.api', 'role:owner'])->group(function () {
        Route::post('/mobile/transactions/{transaction}/void', [MobileTransactionController::class, 'void']);
    });

});
