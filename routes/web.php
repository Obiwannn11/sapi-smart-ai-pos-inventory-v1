<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// --- Auth (Guest) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// --- Owner Routes ---
Route::middleware(['auth', 'tenant', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Owner\DashboardController::class, 'index'])
            ->name('dashboard');

        // Reports
        Route::get('reports/daily', [\App\Http\Controllers\Owner\ReportController::class, 'daily'])
            ->name('reports.daily');

        // Transaction History
        Route::get('transactions', [\App\Http\Controllers\Owner\ReportController::class, 'transactions'])
            ->name('transactions.index');
        Route::get('transactions/{transaction}', [\App\Http\Controllers\Owner\ReportController::class, 'transactionDetail'])
            ->name('transactions.show');

        // Cash Drawer History
        Route::get('cash-drawers', [\App\Http\Controllers\Owner\ReportController::class, 'cashDrawers'])
            ->name('cash-drawers.index');

        // Categories
        Route::resource('categories', \App\Http\Controllers\Owner\CategoryController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Products
        Route::resource('products', \App\Http\Controllers\Owner\ProductController::class);

        // Variants (nested under products)
        Route::post('products/{product}/variants', [\App\Http\Controllers\Owner\VariantController::class, 'store'])
            ->name('products.variants.store');
        Route::put('products/{product}/variants/{variant}', [\App\Http\Controllers\Owner\VariantController::class, 'update'])
            ->name('products.variants.update');
        Route::delete('products/{product}/variants/{variant}', [\App\Http\Controllers\Owner\VariantController::class, 'destroy'])
            ->name('products.variants.destroy');

        // Modifier Groups
        Route::resource('modifiers', \App\Http\Controllers\Owner\ModifierController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['modifiers' => 'modifierGroup']);

        // Payment Methods
        Route::resource('payment-methods', \App\Http\Controllers\Owner\PaymentMethodController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Stock Management
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

        // Open Bill — bayar pesanan pending
        Route::post('/transactions/{transaction}/pay', [\App\Http\Controllers\Cashier\POSController::class, 'payOpenBill'])
            ->name('transactions.pay');

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

// Redirect root ke login atau dashboard
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();

        return $user && $user->isOwner()
            ? redirect('/owner/dashboard')
            : redirect('/cashier/pos');
    }
    return redirect('/login');
});
