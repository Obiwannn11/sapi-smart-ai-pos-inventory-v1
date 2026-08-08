<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Inertia middleware
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // 'tenant' dan 'tenant.api' sengaja GRUP, bukan alias: gerbang langganan
        // harus ikut di setiap rute bertenant, dan menuliskannya satu per satu
        // di tujuh grup rute berarti grup kedelapan pasti akan melupakannya.
        // Menempelkannya di sini membuat "punya konteks tenant" dan "langganan
        // masih berlaku" tak terpisahkan.
        // Urutannya disengaja: verifikasi surel lebih dulu daripada gerbang
        // langganan. Akun yang alamatnya belum terbukti tidak perlu sampai ke
        // pertanyaan "langganannya masih berlaku atau tidak".
        $middleware->group('tenant', [
            \App\Http\Middleware\EnsureTenant::class,
            \App\Http\Middleware\EnsureEmailVerified::class,
            \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);

        $middleware->group('tenant.api', [
            \App\Http\Middleware\EnsureTenantApi::class,
            \App\Http\Middleware\EnsureEmailVerified::class,
            \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);

        // Alias middleware
        // NOTE: our 'role' alias is EnsureRole (enum owner/cashier gate) and must
        // NOT be overwritten by spatie's RoleMiddleware. Module gating uses
        // 'permission:' (spatie) instead.
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            // Gerbang langganan yang bisa dipasang SENDIRIAN, di luar grup
            // 'tenant'/'tenant.api'. Dibutuhkan token mesin (n8n/self-order):
            // ia harus tunduk pada siklus hidup langganan, tapi tidak pada
            // EnsureEmailVerified — token mesin tidak punya kotak masuk untuk
            // membuktikan apa pun. Rute bertenant biasa tetap memakai grupnya;
            // alias ini bukan jalan pintas untuk melewati verifikasi surel.
            'subscription' => \App\Http\Middleware\EnsureSubscriptionActive::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            // Kembaran JSON dari 'permission' untuk API: katalog dan Gate yang
            // sama, hanya jawabannya yang berbeda bentuk.
            'permission.api' => \App\Http\Middleware\EnsureModuleApi::class,
            // Panel platform: gerbang modul milik pemilik SaaS. Terpisah dari
            // 'permission' karena akun platform tidak punya tenant/team-id.
            'platform.can' => \App\Http\Middleware\EnsurePlatformModule::class,
            // Manajemen akun platform: dijaga penanda is_owner, bukan modul
            // grantable — agar staf platform tak bisa menaikkan izinnya sendiri.
            'platform.owner' => \App\Http\Middleware\EnsurePlatformOwner::class,
            // Kapabilitas per tenant. Ortogonal terhadap 'permission':
            // 'permission' menjawab "pengguna ini boleh?", 'feature' menjawab
            // "outlet ini punya kapabilitasnya?". Keduanya kerap dipasang
            // berdampingan, dan 'feature' ditaruh lebih dulu supaya sebab
            // penolakannya benar — "fitur tidak aktif" mengarahkan owner ke
            // Settings, "tidak punya izin" mengarahkannya ke halaman Role.
            'feature' => \App\Http\Middleware\EnsureTenantFeature::class,
            'feature.api' => \App\Http\Middleware\EnsureTenantFeatureApi::class,
        ]);

        // Webhook penyedia pembayaran datang dari mesin di luar sana: tidak ada
        // sesi, jadi tidak ada token CSRF yang bisa dikirim. Penggantinya bukan
        // "tidak ada pemeriksaan" melainkan tanda tangan pada badan permintaan,
        // yang diverifikasi sebelum satu medan pun dibaca — lihat
        // PaymentWebhookController.
        $middleware->validateCsrfTokens(except: [
            'webhook/pembayaran/*',
        ]);

        // Tamu di area platform diarahkan ke login platform, bukan login tenant.
        // Tanpa ini keduanya jatuh ke route('login') — pemilik SaaS yang sesinya
        // habis akan mendarat di halaman masuk pemilik usaha, dan (karena akunnya
        // ada di tabel lain) tidak akan pernah bisa masuk dari sana.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform', 'platform/*')
            ? route('platform.login')
            : route('login'));

        // Kembarannya untuk arah sebaliknya: pengguna yang SUDAH masuk lalu
        // membuka halaman khusus tamu (bookmark, tombol Back, mengetik ulang).
        // Tanpa ini middleware `guest` memakai tujuan bawaan framework, yang
        // mencari rute bernama persis `dashboard` lalu `home`. Aplikasi ini
        // tidak punya keduanya — rutenya `owner.dashboard` dan
        // `platform.dashboard` — sehingga jatuh ke '/', yaitu landing publik
        // yang tombol utamanya menuju login TENANT. Akibatnya pemilik SaaS
        // yang sesinya masih hidup terlempar ke dunia yang salah.
        // Pemilahannya memakai prefiks yang sama dengan redirectGuestsTo di
        // atas, dan ditaruh berdampingan supaya kedua arah pengalihan terbaca
        // sebagai satu keputusan.
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('platform', 'platform/*')) {
                return route('platform.dashboard');
            }

            // Sisi tenant memilih berdasarkan peran, persis seperti yang
            // dilakukan Auth\AuthController::login() setelah kata sandi cocok.
            return $request->user()?->isOwner()
                ? route('owner.dashboard')
                : route('cashier.pos');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render a branded Inertia error page for 400/500 responses in
        // production. Admins (owners) and cashier staff get their own page so
        // the primary action returns them to the right place.
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if (! app()->environment(['local', 'testing']) && in_array($status, [400, 500], true)) {
                $component = $request->user()?->isOwner() ? 'Errors/Admin' : 'Errors/User';

                return Inertia::render($component, ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
