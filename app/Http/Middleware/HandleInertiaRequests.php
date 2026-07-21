<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'tenant_id' => $request->user()->tenant_id,
                    // Owner bypass -> ['*']; staf -> daftar modul yang dimiliki.
                    // Lazy (closure): Inertia memanggil share() di awal middleware
                    // global, sebelum EnsureTenant men-set team-id spatie. Menunda
                    // resolusi ke fase render memastikan team-id sudah benar.
                    // Cek via Gate (can) memakai registrar spatie — jalur yang sama
                    // dengan middleware permission: — bukan relasi Eloquent yang
                    // rapuh terhadap konteks team.
                    'permissions' => fn () => $request->user()->isOwner()
                        ? ['*']
                        : collect(array_keys(config('rbac.modules')))
                            ->filter(fn (string $module) => $request->user()->can($module))
                            ->values()
                            ->all(),
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
