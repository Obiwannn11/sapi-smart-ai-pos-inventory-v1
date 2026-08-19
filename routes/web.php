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

// --- Verifikasi Email ---
// Di bawah 'auth' saja, DI LUAR grup 'tenant': grup itu memuat gerbang
// verifikasi, jadi menaruh halaman verifikasinya di dalam sana akan
// mengalihkan orang ke halaman yang sedang ia buka.
Route::middleware('auth')->group(function () {
    Route::get('/verifikasi-email', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/verifikasi-email/{id}/{hash}', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/verifikasi-email/kirim-ulang', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// --- Media bertenant ---
// Juga 'auth' saja, di luar grup 'tenant', tapi dengan alasan berbeda: tag
// <img> tidak bisa menampilkan halaman pengalihan. Kalau rute ini ikut gerbang
// langganan atau verifikasi surel, gambar akan dijawab 302 ke halaman HTML dan
// yang terlihat pengguna hanyalah ikon rusak. Pemisahan antar toko tetap
// ditegakkan di MediaController, dan sekali lagi oleh TenantScope saat
// route-model binding mencari produknya.
Route::middleware('auth')
    ->get('/media/products/{product}/{size}', [\App\Http\Controllers\MediaController::class, 'productImage'])
    ->whereIn('size', ['full', 'thumb'])
    ->name('media.product-image');

// Foto bukti bayar non-tunai ([BL-075]). Alasan ia ada di sini dan bukan di
// grup kasir sama persis dengan gambar produk di atas — <img> tidak bisa
// menampilkan halaman pengalihan. Yang berbeda hanya isinya: tangkapan layar
// e-wallet kerap memuat nama dan nomor telepon pelanggan, jadi pemeriksaan
// tenantnya di MediaController lebih penting di sini, bukan kurang.
Route::middleware('auth')
    ->get('/media/bukti-bayar/{payment}/{size}', [\App\Http\Controllers\MediaController::class, 'paymentProof'])
    ->whereIn('size', ['full', 'thumb'])
    ->name('media.payment-proof');

// --- Langganan (sisi tenant) ---
// Satu-satunya halaman bertenant yang tetap terbuka saat tenant ditangguhkan —
// lihat daftar ALWAYS_ALLOWED di EnsureSubscriptionActive. Menutupnya berarti
// tenant tak punya jalan keluar dari penangguhan, termasuk dengan membayar.
Route::middleware(['auth', 'tenant'])
    ->name('billing.')
    ->group(function () {
        Route::get('/langganan', [\App\Http\Controllers\Billing\SubscriptionController::class, 'show'])
            ->name('show');

        // Halaman pengajuan Harga Adaptif (`[BL-055]`). Sengaja bukan
        // `role:owner`: ia menjelaskan tarif, dan staf yang mendarat di sini
        // dari halaman langganan berhak membaca alasannya. Yang mengikat usaha
        // pada perjanjian tetap milik owner, digerbang di rute persetujuan.
        Route::get('/langganan/harga-adaptif', [\App\Http\Controllers\Billing\AdaptiveController::class, 'show'])
            ->name('adaptive.show');

        // `{type?}` = normal | subsidized. Dua dokumen terpisah dengan halaman
        // yang sama; tipe yang tak dikenal jatuh ke 404.
        Route::get('/langganan/persetujuan/{type?}', [\App\Http\Controllers\Billing\ConsentController::class, 'show'])
            ->name('consent.show');
        // Hanya owner yang boleh menyetujui — staf tidak mengikat usaha pada
        // perjanjian apa pun.
        Route::post('/langganan/persetujuan/{type?}', [\App\Http\Controllers\Billing\ConsentController::class, 'store'])
            ->middleware('role:owner')
            ->name('consent.store');
        Route::post('/langganan/subsidi/cabut', [\App\Http\Controllers\Billing\ConsentController::class, 'revokeSubsidy'])
            ->middleware('role:owner')
            ->name('subsidy.revoke');

        // Penambahan/pelepasan pengguna & bukti bayar — keputusan komersial,
        // jadi owner saja. Staf tidak menggerakkan tagihan usaha tempatnya
        // bekerja, ke atas maupun ke bawah.
        Route::middleware('role:owner')->group(function () {
            Route::post('/langganan/tambah-pengguna', [\App\Http\Controllers\Billing\UpgradeController::class, 'store'])
                ->name('upgrade.store');
            Route::post('/langganan/lepas-pengguna', [\App\Http\Controllers\Billing\UpgradeController::class, 'destroy'])
                ->name('upgrade.destroy');
            Route::post('/langganan/tagihan/{invoice}/bukti', [\App\Http\Controllers\Billing\UpgradeController::class, 'storeProof'])
                ->name('proof.store');

            // Alur bayar lewat payment gateway (`[BL-059]`). Namanya diawali
            // `billing.` sehingga ikut tercakup ALWAYS_ALLOWED di
            // EnsureSubscriptionActive — tenant yang ditangguhkan justru
            // paling butuh halaman ini.
            Route::get('/langganan/tagihan/{invoice}/bayar', [\App\Http\Controllers\Billing\PaymentController::class, 'create'])
                ->name('payment.create');
            Route::post('/langganan/tagihan/{invoice}/bayar', [\App\Http\Controllers\Billing\PaymentController::class, 'store'])
                ->name('payment.store');
            Route::get('/langganan/pembayaran/{attempt}', [\App\Http\Controllers\Billing\PaymentController::class, 'show'])
                ->name('payment.show');

            // Panel peragaan pada halaman instruksi. Ia TIDAK melunasi apa pun
            // sendiri — lihat docblock PaymentController::simulate().
            Route::post('/langganan/pembayaran/{attempt}/peragakan', [\App\Http\Controllers\Billing\PaymentController::class, 'simulate'])
                ->name('payment.simulate');
        });
    });

// Notifikasi penyedia pembayaran. Di luar seluruh grup: yang memanggilnya mesin
// tanpa sesi, tanpa tenant, dan tanpa token CSRF (dikecualikan di
// bootstrap/app.php). Yang menggantikan ketiganya adalah tanda tangan pada
// badan permintaan — lihat PaymentWebhookController.
Route::post('/webhook/pembayaran/{gateway}', [\App\Http\Controllers\Billing\PaymentWebhookController::class, 'handle'])
    ->name('payment.webhook');

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

            Route::get('reports/monthly', [\App\Http\Controllers\Owner\ReportController::class, 'monthly'])
                ->name('reports.monthly');
            Route::get('reports/monthly/export', [\App\Http\Controllers\Owner\ReportController::class, 'monthlyExport'])
                ->name('reports.monthly.export');

            Route::get('reports/upsell', [\App\Http\Controllers\Owner\ReportController::class, 'upsell'])
                ->name('reports.upsell');

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
        // Urutannya disengaja: `feature` lebih dulu daripada `permission`.
        // Keduanya harus dijawab ya, tapi tenant yang fiturnya mati perlu
        // mendengar "fitur tidak aktif untuk outlet Anda" — bukan "Anda tidak
        // punya izin", yang akan mengirim owner memeriksa halaman Role.
        Route::middleware(['feature:ai', 'permission:ai_analysis'])->group(function () {
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

        // Aturan saran jual buatan owner ([BL-074]).
        //
        // Owner-eksklusif, bukan modul RBAC baru: memilih barang mana yang
        // didorong adalah keputusan pemilik usaha, dan menggantungkannya pada
        // permission `reports` akan memberi kuasa MENULIS kepada siapa pun yang
        // hanya diberi hak MEMBACA laporan.
        Route::get('upsell-rules', [\App\Http\Controllers\Owner\UpsellRuleController::class, 'index'])
            ->name('upsell-rules.index');
        Route::post('upsell-rules', [\App\Http\Controllers\Owner\UpsellRuleController::class, 'store'])
            ->name('upsell-rules.store');
        Route::put('upsell-rules/{upsellRule}', [\App\Http\Controllers\Owner\UpsellRuleController::class, 'update'])
            ->name('upsell-rules.update');
        Route::post('upsell-rules/{upsellRule}/toggle', [\App\Http\Controllers\Owner\UpsellRuleController::class, 'toggle'])
            ->name('upsell-rules.toggle');
        Route::delete('upsell-rules/{upsellRule}', [\App\Http\Controllers\Owner\UpsellRuleController::class, 'destroy'])
            ->name('upsell-rules.destroy');

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

        // Settings — TIGA halaman, tiga endpoint ([BL-039]).
        //
        // Pemecahannya bukan soal tata letak: satu `update` yang lama menerima
        // identitas usaha, dasar tarif, kapabilitas modul, dan kunci API dalam
        // SATU permintaan. Endpoint yang terpisah membuat "form ini tidak boleh
        // bisa menulis kredensial" jadi sifat rutenya, bukan kesepakatan yang
        // dijaga kehati-hatian.
        //
        // Path `settings` yang lama tetap jadi milik Profil & Merek, beserta
        // nama rute `owner.settings.index`/`.update` — tautan dan bookmark yang
        // sudah beredar mendarat di tempat yang masih masuk akal.
        Route::get('settings', [\App\Http\Controllers\Owner\Settings\BusinessProfileController::class, 'index'])
            ->name('settings.index');
        Route::patch('settings', [\App\Http\Controllers\Owner\Settings\BusinessProfileController::class, 'update'])
            ->name('settings.update');

        Route::get('settings/operations', [\App\Http\Controllers\Owner\Settings\SystemBehaviorController::class, 'index'])
            ->name('settings.operations.index');
        Route::patch('settings/operations', [\App\Http\Controllers\Owner\Settings\SystemBehaviorController::class, 'update'])
            ->name('settings.operations.update');

        Route::get('settings/integrations', [\App\Http\Controllers\Owner\Settings\IntegrationController::class, 'index'])
            ->name('settings.integrations.index');
        Route::patch('settings/integrations', [\App\Http\Controllers\Owner\Settings\IntegrationController::class, 'update'])
            ->name('settings.integrations.update');
        Route::post('settings/integrations/mcp-token', [\App\Http\Controllers\Owner\Settings\IntegrationController::class, 'generateMcpToken'])
            ->name('settings.integrations.mcp-token.generate');
        Route::delete('settings/integrations/mcp-token', [\App\Http\Controllers\Owner\Settings\IntegrationController::class, 'revokeMcpToken'])
            ->name('settings.integrations.mcp-token.revoke');
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

        // Foto bukti bayar non-tunai ([BL-075]). Berdiri sendiri, SEBELUM
        // penjualannya disimpan: checkout tetap JSON dan hanya membawa
        // tokennya. Alasan lengkapnya di PaymentProofService.
        Route::post('/bukti-bayar', [\App\Http\Controllers\Cashier\PaymentProofController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('payment-proofs.store');

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

        // Antrian Dapur — digerbang kapabilitas outlet. Flag mati → 403, dan
        // alur kasir cafe tidak berubah sama sekali.
        Route::middleware('feature:kitchen_queue')->group(function () {
            Route::get('/queue', [\App\Http\Controllers\Cashier\QueueController::class, 'index'])
                ->name('queue');
            Route::post('/queue/{transaction}/advance', [\App\Http\Controllers\Cashier\QueueController::class, 'advance'])
                ->name('queue.advance');
            Route::post('/queue/{transaction}/move-to-top', [\App\Http\Controllers\Cashier\QueueController::class, 'moveToTop'])
                ->name('queue.move-to-top');
            Route::post('/queue/{transaction}/move-up', [\App\Http\Controllers\Cashier\QueueController::class, 'moveUp'])
                ->name('queue.move-up');
            Route::post('/queue/{transaction}/move-down', [\App\Http\Controllers\Cashier\QueueController::class, 'moveDown'])
                ->name('queue.move-down');
        });

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

            // Faktor kedua ([BL-013]). Di dalam `guest:platform` dengan
            // sengaja: sesinya BELUM terautentikasi di sini — kata sandi sudah
            // benar, tapi yang tersimpan barulah id yang menunggu. Menaruhnya
            // di bawah `auth:platform` berarti akun sudah masuk sambil diminta
            // kode, dan gerbang seperti itu bisa dilewati dengan menutup
            // halamannya.
            Route::get('/two-factor', [\App\Http\Controllers\Platform\AuthController::class, 'showChallenge'])
                ->name('two-factor.challenge');
            Route::post('/two-factor', [\App\Http\Controllers\Platform\AuthController::class, 'challenge'])
                // Throttle-nya sama dengan login: kode enam angka punya sejuta
                // kemungkinan, dan tanpa batas percobaan sejuta bukan angka
                // besar sama sekali.
                ->middleware('throttle:platform-login')
                ->name('two-factor.verify');
        });

        Route::middleware('auth:platform')->group(function () {
            Route::post('/logout', [\App\Http\Controllers\Platform\AuthController::class, 'logout'])
                ->name('logout');

            // Pendaftaran faktor kedua ([BL-013]). Tidak digerbang
            // `platform.can` mana pun: ini keamanan akun sendiri, bukan modul
            // — staf platform yang tidak dipegangi satu modul pun tetap harus
            // bisa mengamankan akunnya.
            Route::get('/keamanan/two-factor', [\App\Http\Controllers\Platform\TwoFactorController::class, 'show'])
                ->name('two-factor.show');
            Route::post('/keamanan/two-factor', [\App\Http\Controllers\Platform\TwoFactorController::class, 'create'])
                ->name('two-factor.create');
            Route::post('/keamanan/two-factor/konfirmasi', [\App\Http\Controllers\Platform\TwoFactorController::class, 'confirm'])
                ->name('two-factor.confirm');
            Route::post('/keamanan/two-factor/kode-pemulihan', [\App\Http\Controllers\Platform\TwoFactorController::class, 'regenerateRecoveryCodes'])
                ->name('two-factor.recovery-codes');
            Route::delete('/keamanan/two-factor', [\App\Http\Controllers\Platform\TwoFactorController::class, 'destroy'])
                ->name('two-factor.destroy');

            Route::get('/', [\App\Http\Controllers\Platform\DashboardController::class, 'index'])
                ->name('dashboard');

            // Modul: Daftar Tenant — read-only, tanpa kecuali. Tipe usaha kini
            // milik pemilik toko dan diubah dari Pengaturan mereka sendiri.
            Route::middleware('platform.can:tenants')->group(function () {
                Route::get('/tenants', [\App\Http\Controllers\Platform\TenantController::class, 'index'])
                    ->name('tenants.index');
            });

            // Rincian satu tenant tinggal DI SINI, bukan di bawah langganan.
            // Sebelumnya daftar tenant digerbang `tenants` sementara rinciannya
            // digerbang `subscriptions,payments`, sehingga staf yang hanya
            // dipegangi daftar tenant menabrak 403 di tiap barisnya.
            //
            // Gerbangnya "salah satu cukup" dari TIGA modul, karena dua daftar
            // bermuara ke halaman yang sama: daftar tenant dan daftar langganan.
            // Menyempitkannya ke satu modul hanya akan memindahkan 403 yang sama
            // ke pintu yang lain. Apa yang TERLIHAT di dalamnya tetap ditentukan
            // modul per orang di `AccountOverview` — yang tidak boleh dilihat
            // tidak ikut terkirim, bukan halamannya yang ditutup.
            Route::middleware('platform.can:tenants,subscriptions,payments')->group(function () {
                Route::get('/tenants/{tenant}', [\App\Http\Controllers\Platform\TenantController::class, 'show'])
                    ->name('tenants.show');
            });

            // Modul: Langganan & Tagihan — satu bagian, dua modul.
            //
            // Halamannya digerbang "salah satu cukup" karena langganan dan
            // tagihannya adalah urusan yang sama dilihat dari dua sisi; yang
            // menentukan apa yang TERLIHAT di dalamnya tetap modul per orang,
            // diperiksa di controller.
            Route::middleware('platform.can:subscriptions,payments')->group(function () {
                Route::get('/subscriptions', [\App\Http\Controllers\Platform\SubscriptionController::class, 'index'])
                    ->name('subscriptions.index');
            });

            // Alamat lama rincian akun. Dipertahankan sebagai pengalihan dengan
            // alasan yang sama seperti `/platform/invoices`: tautan yang mati
            // tidak memberi tahu siapa pun ke mana halamannya pindah. Sengaja
            // tidak digerbang — yang menentukan boleh-tidaknya adalah tujuannya.
            Route::redirect('/subscriptions/{tenant}', '/platform/tenants/{tenant}')
                ->name('subscriptions.show');

            // Batas pengguna satu tenant. Digerbang modul `subscriptions`
            // sendiri, bukan "salah satu cukup": ini menulis, dan yang hanya
            // memegang tagihan tidak berkepentingan mengubah batas paket.
            Route::middleware('platform.can:subscriptions')->group(function () {
                Route::put('/subscriptions/{subscription}/seats', [\App\Http\Controllers\Platform\SubscriptionController::class, 'updateSeats'])
                    ->name('subscriptions.seats.update');

                // Perpindahan paket. Digerbang modul yang sama dengan batas
                // pengguna, bukan `pricing_rules`: yang diatur di sini bukan
                // bentuk paketnya melainkan penempatan satu tenant — dan staf
                // yang boleh menyunting daftar paket tidak dengan sendirinya
                // boleh memindahkan klien di antaranya.
                Route::put('/subscriptions/{subscription}/plan', [\App\Http\Controllers\Platform\SubscriptionController::class, 'updatePlan'])
                    ->name('subscriptions.plan.update');
            });

            // Modul: Aturan Harga — tarif sebagai data, bukan konstanta di kode.
            Route::middleware('platform.can:pricing_rules')->group(function () {
                Route::get('/pricing-rules', [\App\Http\Controllers\Platform\PricingRuleController::class, 'index'])
                    ->name('pricing-rules.index');
                Route::post('/pricing-rules', [\App\Http\Controllers\Platform\PricingRuleController::class, 'storeRule'])
                    ->name('pricing-rules.store');
                Route::put('/pricing-rules/{rule}', [\App\Http\Controllers\Platform\PricingRuleController::class, 'updateRule'])
                    ->name('pricing-rules.update');
                Route::delete('/pricing-rules/{rule}', [\App\Http\Controllers\Platform\PricingRuleController::class, 'destroyRule'])
                    ->name('pricing-rules.destroy');
                Route::post('/plans', [\App\Http\Controllers\Platform\PricingRuleController::class, 'storePlan'])
                    ->name('plans.store');
                Route::put('/plans/{plan}', [\App\Http\Controllers\Platform\PricingRuleController::class, 'updatePlan'])
                    ->name('plans.update');
            });

            // Modul: Kuota AI — kebijakan jatah analisis harian, dan tombol
            // mengembalikan pemakaian hari ini. Sengaja TERPISAH dari
            // `pricing_rules` meski batas per paket disetel di sana: yang di
            // halaman ini menyentuh tagihan kunci bersama milik pemilik SaaS,
            // bukan tarif yang dibayar tenant.
            Route::middleware('platform.can:ai_quota')->group(function () {
                Route::get('/ai-quota', [\App\Http\Controllers\Platform\AiQuotaController::class, 'index'])
                    ->name('ai-quota.index');
                Route::post('/ai-quota/policies', [\App\Http\Controllers\Platform\AiQuotaController::class, 'store'])
                    ->name('ai-quota.store');
                Route::put('/ai-quota/policies/{aiQuotaPolicy}', [\App\Http\Controllers\Platform\AiQuotaController::class, 'update'])
                    ->name('ai-quota.update');
                Route::delete('/ai-quota/policies/{aiQuotaPolicy}', [\App\Http\Controllers\Platform\AiQuotaController::class, 'destroy'])
                    ->name('ai-quota.destroy');
                Route::post('/ai-quota/reset', [\App\Http\Controllers\Platform\AiQuotaController::class, 'resetUsage'])
                    ->name('ai-quota.reset');
            });

            // Modul: Data Omzet jalur Harga Adaptif — sengaja TERPISAH dari
            // `subscriptions`. Staf platform bisa diberi daftar langganan tanpa
            // diberi angka omzet kliennya.
            //
            // Halamannya sendiri sudah lebur ke rincian akun; yang tersisa di
            // sini adalah rute yang MEMBUKA angkanya, dan pembukaan itulah yang
            // tercatat di jejak audit. Memisahkannya dari halaman rincian
            // disengaja: menengok keadaan langganan tenant tidak boleh ikut
            // menghasilkan catatan "membuka data bisnis klien" yang tak pernah
            // benar-benar terjadi.
            Route::middleware('platform.can:revenue_data')->group(function () {
                Route::get('/tenants/{tenant}/revenue', [\App\Http\Controllers\Platform\RevenueController::class, 'show'])
                    ->name('revenue.show');
            });

            // Ikut pindah bersama halaman rincian yang dirender ulangnya.
            Route::redirect('/subscriptions/{tenant}/revenue', '/platform/tenants/{tenant}/revenue');

            // Modul: Pembayaran — modul yang bisa MENULIS. Tiap tindakannya
            // dicatat sebagai kejadian sensitif. Daftarnya kini hidup di halaman
            // Langganan & Tagihan; yang tinggal di sini adalah tindakannya.
            Route::middleware('platform.can:payments')->group(function () {
                // Alamat lama halaman Pembayaran. Dipertahankan sebagai
                // pengalihan, bukan dihapus: tautan yang mati tidak memberi tahu
                // siapa pun ke mana halamannya pindah.
                Route::redirect('/invoices', '/platform/subscriptions')
                    ->name('invoices.index');

                // Usulan nominal dari aturan harga yang berlaku. Terpisah dari
                // halaman daftar dengan sengaja: menghitungnya untuk SELURUH
                // tenant berarti satu rangkaian query per tenant, hanya demi
                // angka yang paling banyak dipakai satu kali per kunjungan.
                Route::get('/invoices/suggestion', [\App\Http\Controllers\Platform\InvoiceController::class, 'suggestion'])
                    ->name('invoices.suggestion');
                Route::post('/invoices', [\App\Http\Controllers\Platform\InvoiceController::class, 'store'])
                    ->name('invoices.store');
                Route::post('/invoices/{invoice}/verify', [\App\Http\Controllers\Platform\InvoiceController::class, 'verify'])
                    ->name('invoices.verify');
                Route::post('/invoices/{invoice}/reject', [\App\Http\Controllers\Platform\InvoiceController::class, 'reject'])
                    ->name('invoices.reject');
                Route::get('/invoices/{invoice}/proof', [\App\Http\Controllers\Platform\InvoiceController::class, 'proof'])
                    ->name('invoices.proof');
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

// Halaman API Docs — referensi endpoint REST, berdiri sendiri karena punya
// komponen yang tidak terwakili markdown (kartu endpoint, badge method).
Route::get('/api-docs', [\App\Http\Controllers\Public\LandingController::class, 'docs'])->name('api-docs');

// Halaman harga publik — membacakan aturan tarif yang benar-benar berlaku
// kepada orang yang belum punya sesi. Tenant yang sudah masuk melihat versi
// miliknya sendiri di `/langganan/harga-adaptif` (BL-055); halaman ini tidak
// menyentuh tenant sama sekali.
Route::get('/harga', [\App\Http\Controllers\Public\PricingController::class, 'index'])->name('pricing');

// Hub Dokumentasi — dua jalur: panduan penggunaan & dokumentasi developer.
Route::get('/dokumentasi', [\App\Http\Controllers\Public\DocsController::class, 'index'])->name('docs.index');
Route::get('/dokumentasi/{track}/{page?}', [\App\Http\Controllers\Public\DocsController::class, 'show'])
    ->name('docs.show');
