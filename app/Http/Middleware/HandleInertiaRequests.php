<?php

namespace App\Http\Middleware;

use App\Models\PlatformUser;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        // Dibaca sekali: sejak ada guard `platform`, $request->user() bisa
        // mengembalikan PlatformUser (middleware auth:platform memanggil
        // Auth::shouldUse, sehingga guard default berpindah untuk sisa request).
        // Keduanya dibedakan lewat instanceof — bukan sekadar cek null — karena
        // PlatformUser tidak punya isOwner(), role, maupun tenant_id.
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'tenant_id' => $user->tenant_id,
                    // Owner bypass -> ['*']; staf -> daftar modul yang dimiliki.
                    // Lazy (closure): Inertia memanggil share() di awal middleware
                    // global, sebelum EnsureTenant men-set team-id spatie. Menunda
                    // resolusi ke fase render memastikan team-id sudah benar.
                    // Perhitungannya sendiri ada di User::modulePermissions(),
                    // dipakai bersama payload autentikasi mobile.
                    'permissions' => fn () => $user->modulePermissions(),
                ] : null,

                // Sengaja kunci terpisah, bukan menumpang `auth.user`. Kalau
                // ditumpangkan, tiap komponen Vue yang membaca auth.user.role
                // atau auth.user.tenant_id akan menerima null diam-diam di
                // konteks platform — bug yang sulit dilacak. Dipisah = komponen
                // tenant melihat auth.user null, jujur dan mudah dibaca.
                'platformUser' => $user instanceof PlatformUser ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_owner' => $user->is_owner,
                    'modules' => fn () => $user->moduleNames(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'lastTransaction' => fn () => $request->session()->get('lastTransaction'),
                'mcpToken' => fn () => $request->session()->get('mcpToken'),
            ],
        ]);
    }
}
