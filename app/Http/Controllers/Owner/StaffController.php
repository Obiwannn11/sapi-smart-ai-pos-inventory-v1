<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $subscription = $this->subscriptions->ensureFor($tenant);

        return Inertia::render('Owner/Staff/Index', [
            // Ditunda ([BL-037]) bersama baris pemilik: keduanya satu tabel,
            // jadi keduanya satu grup — separuh tabel yang datang lebih dulu
            // hanya akan menggeser separuh sisanya beberapa saat kemudian.
            //
            // `roles.permissions` ikut dimuat karena `modulePermissions()`
            // menanyakan tiap modul lewat Gate. Tanpa ini setiap pertanyaan
            // menarik relasinya sendiri: tujuh kueri per orang, dikali jumlah
            // staf — dan itulah yang membuat daftar ini pantas ditunda.
            'staff' => Inertia::defer(fn () => User::where('tenant_id', $tenant->id)
                ->where('role', 'cashier')
                ->with('roles.permissions')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'roles' => $user->getRoleNames(),
                    // Pertanyaan "orang ini bisa membuka apa saja" sudah dijawab
                    // server sejak lama; yang belum ada hanya tempat
                    // menampilkannya. Dikirim mentah (nama modul), labelnya
                    // diambil dari katalog di bawah supaya tidak ada daftar
                    // label kedua yang harus dijaga.
                    'modules' => $user->modulePermissions(),
                ]), 'tabel'),
            // Owner ikut berbaris meski ia bukan staf dan tidak memakan seat.
            // Justru dialah satu-satunya akun yang aksesnya TIDAK berasal dari
            // role — ia melewati seluruh pemeriksaan (`Gate::before`) — dan
            // tanpa barisnya itu jadi satu-satunya fakta akses yang tak pernah
            // terlihat di layar mana pun. Akibat praktisnya: mencabut modul dari
            // role owner tidak mengubah apa-apa, dan tak ada yang memberi tahu.
            'owners' => Inertia::defer(fn () => $this->ownerRows($tenant->id), 'tabel'),
            'roles' => Role::where('tenant_id', $tenant->id)->orderBy('name')->pluck('name'),
            // Katalog label modul, bentuknya sama dengan yang dipakai halaman
            // Role. Sumbernya satu: `config/rbac.php`.
            'modules' => collect(config('rbac.modules'))->map(fn (array $meta, string $name) => [
                'name' => $name,
                'label' => $meta['label'],
            ])->values(),
            // Angka seat ditampilkan sebelum tombol ditekan, bukan hanya di
            // pesan penolakan. Batas yang baru terlihat saat dilanggar terasa
            // seperti jebakan.
            'seats' => [
                'used' => $subscription->activeSeatsUsed(),
                'total' => $subscription->seats,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_name' => ['nullable', 'string', Rule::exists('roles', 'name')->where('tenant_id', $tenant->id)],
        ]);

        // Penegakan keras SEBELUM user dibuat. Menolak dengan sopan jauh lebih
        // baik daripada menerima lalu menagih: bagi UMKM, tagihan yang naik
        // tanpa diminta jauh lebih menyakitkan daripada tombol yang menolak.
        if (! $this->subscriptions->hasSeatFor($tenant)) {
            return back()->with('error', $this->subscriptions->seatLimitMessage($tenant));
        }

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // cast 'hashed'
            'role' => 'cashier',
            // Staf langsung terverifikasi: owner-lah yang menjaminnya, dan
            // kata sandinya pun diberikan langsung. Menuntut verifikasi di
            // sini akan mengunci kasir yang tidak punya alamat surel sendiri —
            // hal yang lumrah di warung kecil.
            'email_verified_at' => now(),
        ]);

        if (! empty($validated['role_name'])) {
            $user->syncRoles([$validated['role_name']]); // team-id set oleh middleware tenant
        }

        $this->subscriptions->ensureFor($tenant)->recordSeatUsage();

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

    /**
     * Aktifkan atau nonaktifkan akun staf.
     *
     * Menonaktifkan menggantikan kebiasaan menghapus: staf yang keluar
     * kehilangan akses, tapi jejaknya di transaksi lama tetap utuh — dan
     * seat-nya kembali bebas untuk penggantinya.
     */
    public function toggleActive(Request $request, User $staff): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $this->authorizeStaff($staff, $tenant->id);

        if ($staff->is_active) {
            $staff->update(['is_active' => false]);

            return back()->with('success', "Akun {$staff->name} dinonaktifkan.");
        }

        // Mengaktifkan kembali menempati seat, jadi ia melewati gerbang yang
        // sama dengan menambah staf baru. Tanpa ini batas seat bisa dilewati
        // dengan menonaktifkan lalu mengaktifkan beramai-ramai.
        if (! $this->subscriptions->hasSeatFor($tenant)) {
            return back()->with('error', $this->subscriptions->seatLimitMessage($tenant));
        }

        $staff->update(['is_active' => true]);
        $this->subscriptions->ensureFor($tenant)->recordSeatUsage();

        return back()->with('success', "Akun {$staff->name} diaktifkan.");
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $this->authorizeStaff($staff, $request->user()->tenant_id);

        $staff->delete();

        return back()->with('success', 'Akun staf berhasil dihapus.');
    }

    /**
     * Baris owner untuk tabel staf — dibaca saja, tanpa tombol aksi.
     *
     * Jamak, bukan tunggal: yang dipakai adalah kolom `role`, bukan user yang
     * sedang masuk. Satu usaha yang dijalankan berdua akan punya dua baris, dan
     * daftar yang hanya menampilkan dirinya sendiri justru menyembunyikan
     * rekannya — persis pertanyaan yang halaman ini ada untuk menjawabnya.
     *
     * @return Collection<int, array{id: int, name: string, email: string, modules: list<string>}>
     */
    private function ownerRows(int $tenantId): Collection
    {
        return User::where('tenant_id', $tenantId)
            ->where('role', 'owner')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // `['*']` — bukan daftar tujuh modul. Owner tidak memegang modul
                // terbanyak, ia melewati pemeriksaannya, dan dua hal itu terlihat
                // sama di layar kalau yang dikirim daftar biasa.
                'modules' => $user->modulePermissions(),
            ]);
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
