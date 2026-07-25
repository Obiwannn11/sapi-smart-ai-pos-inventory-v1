<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MobileTenantController extends Controller
{
    public function profile(): JsonResponse
    {
        $user = auth()->user();
        $tenant = $user->tenant;

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
            ],
            // Izin ikut dikembalikan di sini, bukan hanya saat login. Owner bisa
            // mengubah role staf kapan saja, sementara token mobile bertahan
            // berminggu-minggu — tanpa jalan menyegarkan, aplikasi kasir akan
            // memakai daftar izin yang usang sampai penggunanya kebetulan
            // keluar dan masuk lagi.
            'permissions' => $user->modulePermissions(),
        ]);
    }
}
