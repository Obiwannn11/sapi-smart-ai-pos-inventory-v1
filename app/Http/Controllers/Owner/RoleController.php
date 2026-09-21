<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        return Inertia::render('Owner/Roles/Index', [
            // Ditunda ([BL-037]): daftarnya menyusul sementara tombol tambah dan
            // formulirnya sudah bisa dipakai sejak cat pertama.
            'roles' => Inertia::defer(fn () => Role::with('permissions:id,name')
                ->where('tenant_id', $tenantId)
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'modules' => $role->permissions->pluck('name'),
                    'users_count' => $role->users_count,
                ])),
            'modules' => collect(config('rbac.modules'))->map(fn ($meta, $name) => [
                'name' => $name,
                'label' => $meta['label'],
                'sensitive' => $meta['sensitive'],
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('tenant_id', $tenantId)],
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(array_keys(config('rbac.modules')))],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']); // tenant_id auto dari team-id
        $role->syncPermissions($validated['modules'] ?? []);

        return back()->with('success', 'Role berhasil dibuat.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        abort_if($role->tenant_id !== $tenantId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('tenant_id', $tenantId)->ignore($role->id)],
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(array_keys(config('rbac.modules')))],
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['modules'] ?? []);

        return back()->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->tenant_id !== $request->user()->tenant_id, 403);

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Role masih dipakai staf. Pindahkan staf ke role lain dulu.');
        }

        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }
}
