<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda panel platform.
 *
 * Angka di sini sengaja hanya cacah administratif (berapa tenant, berapa akun)
 * — bukan metrik bisnis klien. Metrik langganan menyusul di Tahap B, omset
 * jalur subsidi di Tahap C lewat tabel ringkasan tersendiri.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Platform/Dashboard', [
            'stats' => [
                'tenant_count' => Tenant::count(),
                'user_count' => User::count(),
            ],
        ]);
    }
}
