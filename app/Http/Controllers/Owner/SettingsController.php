<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $tenant = auth()->user()->tenant;

        return Inertia::render('Owner/Settings/Index', [
            'tenant' => [
                'name'    => $tenant->name,
                'address' => $tenant->address,
                'phone'   => $tenant->phone,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'address' => 'nullable|string|max:500',
            'phone'   => 'nullable|string|max:50',
        ]);

        auth()->user()->tenant->update($validated);

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
