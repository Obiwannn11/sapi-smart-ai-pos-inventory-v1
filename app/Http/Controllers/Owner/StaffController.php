<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $staff = User::where('tenant_id', $tenantId)
            ->where('role', 'cashier')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ]);

        return Inertia::render('Owner/Staff/Index', [
            'staff' => $staff,
            'roles' => Role::where('tenant_id', $tenantId)->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_name' => ['nullable', 'string', Rule::exists('roles', 'name')->where('tenant_id', $tenantId)],
        ]);

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // cast 'hashed'
            'role' => 'cashier',
        ]);

        if (! empty($validated['role_name'])) {
            $user->syncRoles([$validated['role_name']]); // team-id set oleh middleware tenant
        }

        return back()->with('success', 'Akun staf berhasil dibuat.');
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $this->authorizeStaff($staff, $tenantId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_name' => ['nullable', 'string', Rule::exists('roles', 'name')->where('tenant_id', $tenantId)],
        ]);

        $staff->name = $validated['name'];
        $staff->email = $validated['email'];
        if (! empty($validated['password'])) {
            $staff->password = $validated['password'];
        }
        $staff->save();

        $staff->syncRoles(array_filter([$validated['role_name'] ?? null]));

        return back()->with('success', 'Akun staf berhasil diperbarui.');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $this->authorizeStaff($staff, $request->user()->tenant_id);

        $staff->delete();

        return back()->with('success', 'Akun staf berhasil dihapus.');
    }

    /**
     * Pastikan target adalah staf (cashier) milik tenant yang sama — bukan owner
     * dan bukan lintas tenant.
     */
    private function authorizeStaff(User $staff, int $tenantId): void
    {
        abort_if($staff->tenant_id !== $tenantId || $staff->isOwner(), 403);
    }
}
