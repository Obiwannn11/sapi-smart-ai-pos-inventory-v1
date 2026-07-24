<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// --- Auth (Guest) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Pemulihan kata sandi tenant. Nama rute `password.reset` sengaja dipakai
    // karena itulah yang dirujuk kontrak CanResetPassword bawaan Laravel.
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// --- Langganan (sisi tenant) ---
// Satu-satunya halaman bertenant yang tetap terbuka saat tenant ditangguhkan —
// lihat daftar ALWAYS_ALLOWED di EnsureSubscriptionActive. Menutupnya berarti
// tenant tak punya jalan keluar dari penangguhan, termasuk dengan membayar.
Route::middleware(['auth', 'tenant'])
    ->name('billing.')
    ->group(function () {
        Route::get('/langganan', [\App\Http\Controllers\Billing\SubscriptionController::class, 'show'])
            ->name('show');
    });

// --- Owner Routes: modul grantable (digerbang per-permission; owner auto-lolos
//     via Gate::before). Staf non-owner butuh permission modul yang sesuai. ---
Route::middleware(['auth', 'tenant'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        // Modul: Produk (kategori, produk, varian, modifier)
        Route::middleware('permission:products')->group(function () {
            Route::resource('categories', \App\Http\Controllers\Owner\CategoryController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource('products', \App\Http\Controllers\Owner\ProductController::class);

            Route::post('products/{product}/variants', [\App\Http\Controllers\Owner\VariantController::class, 'store'])
                ->name('products.variants.store');
            Route::put('products/{product}/variants/{variant}', [\App\Http\Controllers\Owner\VariantController::class, 'update'])
                ->name('products.variants.update');
            Route::delete('products/{product}/variants/{variant}', [\App\Http\Controllers\Owner\VariantController::class, 'destroy'])
                ->name('products.variants.destroy');

            Route::resource('modifiers', \App\Http\Controllers\Owner\ModifierController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['modifiers' => 'modifierGroup']);
            Route::patch('modifiers/{modifierGroup}/settings', [\App\Http\Controllers\Owner\ModifierController::class, 'updateSettings'])
                ->name('modifiers.settings');
        });

        // Modul: Stok / Inventori
        Route::middleware('permission:stock')->group(function () {
            Route::get('stock', [\App\Http\Controllers\Owner\StockController::class, 'index'])
                ->name('stock.index');
            Route::post('stock/{variant}/restock', [\App\Http\Controllers\Owner\StockController::class, 'restock'])
                ->name('stock.restock');
            Route::post('stock/{variant}/adjust', [\App\Http\Controllers\Owner\StockController::class, 'adjust'])
                ->name('stock.adjust');
            Route::get('stock/{variant}/history', [\App\Http\Controllers\Owner\StockController::class, 'history'])
                ->name('stock.history');
            Route::get('stock/movements', [\App\Http\Controllers\Owner\StockController::class, 'movements'])
                ->name('stock.movements');
        });

        // Modul: Laporan & Riwayat (laporan harian, transaksi, sesi kas)
        Route::middleware('permission:reports')->group(function () {
            Route::get('reports/daily', [\App\Http\Controllers\Owner\ReportController::class, 'daily'])
                ->name('reports.daily');

            Route::get('transactions', [\App\Http\Controllers\Owner\ReportController::class, 'transactions'])
                ->name('transactions.index');
            Route::get('transactions/{transaction}', [\App\Http\Controllers\Owner\ReportController::class, 'transactionDetail'])
                ->name('transactions.show');

            Route::get('cash-drawers', [\App\Http\Controllers\Owner\ReportController::class, 'cashDrawers'])
                ->name('cash-drawers.index');
        });

        // Modul: Metode Pembayaran (sensitif — grantable, default tidak dicentang)
        Route::middleware('permission:payment_methods')->group(function () {
            Route::resource('payment-methods', \App\Http\Controllers\Owner\PaymentMethodController::class)
                ->only(['index', 'store', 'update', 'destroy']);
        });

        // Modul: AI Analysis (sensitif — grantable, default tidak dicentang)
        Route::middleware('permission:ai_analysis')->group(function () {
            Route::get('ai-analysis', [\App\Http\Controllers\Owner\AiAnalysisController::class, 'index'])
                ->name('ai-analysis.index');
            Route::post('ai-analysis', [\App\Http\Controllers\Owner\AiAnalysisController::class, 'store'])
                ->name('ai-analysis.store');
            Route::get('ai-analysis/{aiAnalysis}', [\App\Http\Controllers\Owner\AiAnalysisController::class, 'show'])
                ->name('ai-analysis.show');
        });
    });

// --- Owner-eksklusif: tetap role:owner (tak jadi permission grantable) ---
Route::middleware(['auth', 'tenant', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        // Dashboard (beranda owner)
        Route::get('/dashboard', [\App\Http\Controllers\Owner\DashboardController::class, 'index'])
            ->name('dashboard');

        // Koreksi transaksi offline yang tersinkron dengan anomali (sensitif)
        Route::get('offline-review', [\App\Http\Controllers\Owner\OfflineReviewController::class, 'index'])
            ->name('offline-review.index');
        Route::post('offline-review/{transaction}/resolve', [\App\Http\Controllers\Owner\OfflineReviewController::class, 'resolve'])
            ->name('offline-review.resolve');

        // Manajemen Staf & Role (hanya owner)
        Route::resource('staff', \App\Http\Controllers\Owner\StaffController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::patch('staff/{staff}/active', [\App\Http\Controllers\Owner\StaffController::class, 'toggleActive'])
            ->name('staff.toggle-active');
        Route::resource('roles', \App\Http\Controllers\Owner\RoleController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Settings
        Route::get('settings', [\App\Http\Controllers\Owner\SettingsController::class, 'index'])
            ->name('settings.index');
        Route::patch('settings', [\App\Http\Controllers\Owner\SettingsController::class, 'update'])
            ->name('settings.update');
        Route::post('settings/mcp-token', [\App\Http\Controllers\Owner\SettingsController::class, 'generateMcpToken'])
            ->name('settings.mcp-token.generate');
        Route::delete('settings/mcp-token', [\App\Http\Controllers\Owner\SettingsController::class, 'revokeMcpToken'])
            ->name('settings.mcp-token.revoke');
    });

// --- Cashier Routes (owner juga bisa akses) ---
Route::middleware(['auth', 'tenant', 'role:cashier,owner'])
    ->prefix('cashier')
    ->name('cashier.')
    ->group(function () {
        // POS
        Route::get('/pos', [\App\Http\Controllers\Cashier\POSController::class, 'index'])
            ->name('pos');
        Route::post('/transactions', [\App\Http\Controllers\Cashier\POSController::class, 'store'])
            ->name('transactions.store');

        // Sinkronisasi transaksi offline (batch, JSON) — didaftarkan sebelum
        // rute ber-parameter agar "sync" tidak tertangkap sebagai {transaction}.
        Route::post('/transactions/sync', [\App\Http\Controllers\Cashier\POSController::class, 'sync'])
            ->name('transactions.sync');

        // Open Bill — bayar pesanan pending
        Route::post('/transactions/{transaction}/pay', [\App\Http\Controllers\Cashier\POSController::class, 'payOpenBill'])
            ->name('transactions.pay');

        // Edit transaksi (owner kapan saja; kasir hanya dalam shift laci terbuka — ditegakkan di service)
        Route::put('/transactions/{transaction}', [\App\Http\Controllers\Cashier\TransactionEditController::class, 'update'])
            ->name('transactions.update');

        // Transaction History (kasir)
        Route::get('/transactions', [\App\Http\Controllers\Cashier\POSController::class, 'history'])
            ->name('transactions.index');

        // Cash Drawer
        Route::get('/cash-drawer', [\App\Http\Controllers\Cashier\CashDrawerController::class, 'index'])
            ->name('cash-drawer.index');
        Route::post('/cash-drawer/open', [\App\Http\Controllers\Cashier\CashDrawerController::class, 'open'])
            ->name('cash-drawer.open');
        Route::post('/cash-drawer/close', [\App\Http\Controllers\Cashier\CashDrawerController::class, 'close'])
            ->name('cash-drawer.close');
        Route::get('/cash-drawer/{cashDrawer}/summary', [\App\Http\Controllers\Cashier\CashDrawerController::class, 'summary'])
            ->name('cash-drawer.summary');
    });

// --- Void Transaction (owner only) ---
Route::middleware(['auth', 'tenant', 'role:owner'])
    ->post('/owner/transactions/{transaction}/void', [\App\Http\Controllers\Cashier\POSController::class, 'void'])
    ->name('owner.transactions.void');

// --- Platform Console (pemilik SaaS) ---
// Sengaja DI LUAR middleware 'tenant': akun platform tidak punya tenant_id.
// Tiap modul digerbang izinnya sendiri lewat 'platform.can', bukan satu gerbang
// kasar untuk seluruh grup — supaya kelak staf platform bisa diberi sebagian.
Route::prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::middleware('guest:platform')->group(function () {
            Route::get('/login', [\App\Http\Controllers\Platform\AuthController::class, 'showLogin'])
                ->name('login');
            Route::post('/login', [\App\Http\Controllers\Platform\AuthController::class, 'login'])
                ->middleware('throttle:platform-login');

            // Pemulihan kata sandi — broker & tabel token terpisah dari tenant.
            Route::get('/forgot-password', [\App\Http\Controllers\Platform\PasswordResetController::class, 'showForgot'])
                ->name('password.request');
            Route::post('/forgot-password', [\App\Http\Controllers\Platform\PasswordResetController::class, 'sendLink'])
                ->middleware('throttle:platform-password-reset')
                ->name('password.email');
            Route::get('/reset-password/{token}', [\App\Http\Controllers\Platform\PasswordResetController::class, 'showReset'])
                ->name('password.reset');
            Route::post('/reset-password', [\App\Http\Controllers\Platform\PasswordResetController::class, 'reset'])
                ->middleware('throttle:platform-password-reset')
                ->name('password.update');
        });

        Route::middleware('auth:platform')->group(function () {
            Route::post('/logout', [\App\Http\Controllers\Platform\AuthController::class, 'logout'])
                ->name('logout');

            Route::get('/', [\App\Http\Controllers\Platform\DashboardController::class, 'index'])
                ->name('dashboard');

            // Modul: Daftar Tenant (read-only di Tahap A)
            Route::middleware('platform.can:tenants')->group(function () {
                Route::get('/tenants', [\App\Http\Controllers\Platform\TenantController::class, 'index'])
                    ->name('tenants.index');
            });

            // Modul: Jejak Audit (read-only)
            Route::middleware('platform.can:audit_logs')->group(function () {
                Route::get('/audit-logs', [\App\Http\Controllers\Platform\AuditLogController::class, 'index'])
                    ->name('audit-logs.index');
            });

            // Manajemen akun platform — pemilik SaaS saja, bukan modul grantable
            // (lihat EnsurePlatformOwner untuk alasannya).
            Route::middleware('platform.owner')->group(function () {
                Route::resource('users', \App\Http\Controllers\Platform\PlatformUserController::class)
                    ->only(['index', 'store', 'update', 'destroy'])
                    ->parameters(['users' => 'platformUser']);
            });
        });
    });

// Halaman Landing
Route::get('/', [\App\Http\Controllers\Public\LandingController::class, 'index'])->name('landing');

// Halaman API Docs
Route::get('/api-docs', [\App\Http\Controllers\Public\LandingController::class, 'docs'])->name('api-docs');
