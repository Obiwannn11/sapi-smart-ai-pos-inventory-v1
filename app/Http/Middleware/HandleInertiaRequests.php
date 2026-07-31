<?php

namespace App\Http\Middleware;

use App\Models\PaymentMethod;
use App\Models\PlatformUser;
use App\Models\Transaction;
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

                // Kapabilitas outlet — dipakai menu nav agar bersyarat.
                // Kunci terpisah dengan alasan yang sama seperti platformUser
                // di bawah, dan closure dengan alasan yang sama seperti
                // permissions di atas. Penjaga instanceof wajib: PlatformUser
                // tidak punya tenant sama sekali.
                //
                // Sidebar hanyalah cermin — gerbang rute tetap sumber
                // kebenarannya. Menyembunyikan menu bukan pengamanan.
                'tenant' => $user instanceof User && $user->tenant ? [
                    'name' => $user->tenant->name,
                    // Bukan kapabilitas, jadi tidak ikut `features`: ia
                    // menentukan apa yang ditanyakan kasir sebelum menyimpan,
                    // bukan pintu mana yang terbuka ([BL-026]). Skalar biasa —
                    // tenant-nya sudah dimuat, jadi tidak ada query tambahan.
                    'order_identity_mode' => $user->tenant->order_identity_mode,
                    'features' => fn () => [
                        'kitchen_queue' => $user->tenant->hasFeature('kitchen_queue'),
                        'self_order' => $user->tenant->hasFeature('self_order'),
                        'ai' => $user->tenant->hasFeature('ai'),
                    ],
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
            // Tagihan terbuka menempel di topbar kasir, bukan di dalam gulir
            // keranjang — pesanan yang menunggu dibayar tidak boleh hilang dari
            // pandangan justru saat kasir paling sibuk ([BL-023]).
            //
            // Dibatasi ke rute kasir: topbar-nya hanya ada di sana, dan
            // menghitungnya pada tiap request halaman owner/platform adalah
            // query yang tak pernah dibaca siapa pun.
            'cashier' => $user instanceof User && $request->routeIs('cashier.*')
                ? [
                    'openBills' => fn () => $this->openBillsFor($user),
                    'paymentMethods' => fn () => PaymentMethod::where('is_active', true)
                        ->get(['id', 'name', 'type']),
                ]
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'lastTransaction' => fn () => $request->session()->get('lastTransaction'),
                'mcpToken' => fn () => $request->session()->get('mcpToken'),
            ],
        ]);
    }

    /**
     * Tagihan terbuka milik kasir ini, seperlunya untuk panel topbar.
     *
     * Ikut membawa itemnya: panel menampilkan isi pesanan supaya kasir tahu
     * tagihan mana yang ia buka tanpa harus melunasinya dulu. Jumlahnya
     * dibatasi keadaan — tagihan terbuka yang menumpuk sampai berat adalah
     * masalah operasional yang harus terlihat, bukan disembunyikan paginasi.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function openBillsFor(User $user): \Illuminate\Support\Collection
    {
        return Transaction::where('user_id', $user->id)
            ->where('status', Transaction::STATUS_PENDING)
            ->with(['items:id,transaction_id,variant_name,qty,notes'])
            ->latest()
            ->get(['id', 'code', 'customer_name', 'table_number', 'total_amount', 'created_at'])
            ->map(fn (Transaction $bill) => [
                'id' => $bill->id,
                'code' => $bill->code,
                'customer_name' => $bill->customer_name,
                'table_number' => $bill->table_number,
                'total_amount' => $bill->total_amount,
                'created_at' => $bill->created_at,
                'items' => $bill->items->map(fn ($item) => [
                    'id' => $item->id,
                    'variant_name' => $item->variant_name,
                    'qty' => $item->qty,
                    'notes' => $item->notes,
                ]),
            ]);
    }
}
