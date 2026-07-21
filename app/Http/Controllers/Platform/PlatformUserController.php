<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\PlatformUserModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen akun platform — hanya pemilik SaaS (middleware `platform.owner`).
 *
 * Mengikuti pola Owner\RoleController: katalog modul dari config, owner tinggal
 * mencentang. Bedanya modul di sini melekat langsung ke akun, bukan lewat role
 * perantara — jumlah akun platform terlalu sedikit untuk membenarkan satu
 * lapisan abstraksi lagi.
 */
class PlatformUserController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = PlatformUser::query()
            ->with('modules:id,platform_user_id,module')
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get()
            ->map(fn (PlatformUser $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'is_owner' => $account->is_owner,
                'modules' => $account->modules->pluck('module'),
                'is_self' => $account->id === $request->user()->id,
            ]);

        return Inertia::render('Platform/Users/Index', [
            'accounts' => $accounts,
            'modules' => collect(config('platform-rbac.modules'))
                ->map(fn (array $meta, string $name) => [
                    'name' => $name,
                    'label' => $meta['label'],
                    'sensitive' => $meta['sensitive'],
                    'available' => $meta['available'],
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:platform_users,email'],
            'password' => ['required', 'string', 'min:8'],
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(self::grantableModules())],
        ]);

        $account = PlatformUser::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // cast 'hashed'
            'is_owner' => false, // status pemilik tak pernah diberikan lewat form
        ]);

        $this->syncModules($account, $validated['modules'] ?? []);

        PlatformAuditLog::record('platform_users.create', $account, [
            'email' => $account->email,
            'modules' => $validated['modules'] ?? [],
        ]);

        return back()->with('success', 'Akun platform berhasil dibuat.');
    }

    public function update(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('platform_users', 'email')->ignore($platformUser->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(self::grantableModules())],
        ]);

        $platformUser->name = $validated['name'];
        $platformUser->email = $validated['email'];
        if (! empty($validated['password'])) {
            $platformUser->password = $validated['password'];
        }
        $platformUser->save();

        // Modul owner tidak disimpan: aksesnya berasal dari penanda is_owner,
        // dan menuliskan baris modul untuknya hanya akan jadi data menyesatkan
        // yang seolah bisa dicabut padahal tidak berpengaruh.
        if (! $platformUser->isOwner()) {
            $this->syncModules($platformUser, $validated['modules'] ?? []);
        }

        PlatformAuditLog::record('platform_users.update', $platformUser, [
            'modules' => $platformUser->isOwner() ? null : ($validated['modules'] ?? []),
            'password_changed' => ! empty($validated['password']),
        ]);

        return back()->with('success', 'Akun platform berhasil diperbarui.');
    }

    public function destroy(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        // Dua penjaga terhadap terkunci dari panel sendiri.
        if ($platformUser->id === $request->user()->id) {
            return back()->with('error', 'Tidak bisa menghapus akun Anda sendiri.');
        }

        if ($platformUser->isOwner() && PlatformUser::where('is_owner', true)->count() <= 1) {
            return back()->with('error', 'Ini satu-satunya akun pemilik. Tunjuk pemilik lain sebelum menghapusnya.');
        }

        PlatformAuditLog::record('platform_users.delete', $platformUser, ['email' => $platformUser->email]);

        $platformUser->delete();

        return back()->with('success', 'Akun platform berhasil dihapus.');
    }

    /**
     * Modul yang boleh dicentang: hanya yang halamannya sudah ada.
     *
     * @return list<string>
     */
    private static function grantableModules(): array
    {
        return collect(config('platform-rbac.modules'))
            ->filter(fn (array $meta) => $meta['available'])
            ->keys()
            ->all();
    }

    /**
     * @param  list<string>  $modules
     */
    private function syncModules(PlatformUser $account, array $modules): void
    {
        $account->modules()->delete();

        foreach ($modules as $module) {
            PlatformUserModule::create([
                'platform_user_id' => $account->id,
                'module' => $module,
            ]);
        }
    }
}
