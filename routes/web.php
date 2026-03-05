<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
        Route::get('/dashboard', function () {
            return Inertia::render('Owner/Dashboard');
        })->name('dashboard');

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
    });

// --- Cashier Routes (owner juga bisa akses) ---
Route::middleware(['auth', 'tenant', 'role:cashier,owner'])
    ->prefix('cashier')
    ->name('cashier.')
    ->group(function () {
        Route::get('/pos', function () {
            return Inertia::render('Cashier/POS');
        })->name('pos');
        // Diisi lebih lanjut di Phase 3
    });

// Redirect root ke login atau dashboard
Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isOwner()
            ? redirect('/owner/dashboard')
            : redirect('/cashier/pos');
    }
    return redirect('/login');
});
