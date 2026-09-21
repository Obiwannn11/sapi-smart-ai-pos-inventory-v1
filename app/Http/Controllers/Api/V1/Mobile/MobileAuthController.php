<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Diperiksa setelah kata sandi cocok, supaya endpoint ini tidak bisa
        // dipakai memastikan sebuah akun ada.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun ini dinonaktifkan. Hubungi pemilik usaha Anda.'],
            ]);
        }

        // Satu device satu token — revoke token mobile lama
        $user->tokens()->where('name', 'mobile-app')->delete();

        $token = $user->createToken('mobile-app')->plainTextToken;

        $tenant = $user->tenant;

        // Konteks team spatie disetel manual di sini. Endpoint login berada di
        // LUAR middleware `tenant.api` — ia harus terbuka untuk yang belum
        // punya token — jadi tidak ada yang menyetelnya untuk kita, dan tanpa
        // itu daftar izinnya akan pulang kosong untuk setiap staf.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                // Owner -> ['*'], staf -> daftar modul yang dimiliki. Sumber
                // perhitungannya sama persis dengan yang dipakai sisi web.
                'permissions' => $user->modulePermissions(),
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
            ] : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
