<?php

namespace App\Http\Middleware;

use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TenantLogoService;
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
                    // Logo usaha, dibagikan berdampingan dengan namanya karena
                    // dipakai untuk hal yang sama: menjawab "saya sedang di toko
                    // mana". Ia tinggal di sini dan bukan di prop halaman sebab
                    // kepala sidebar owner dan topbar kasir ada di SETIAP
                    // halaman dan tak satu pun punya controller sendiri.
                    //
                    // null bila belum diunggah — layar jatuh kembali ke inisial
                    // nama toko, bukan ke kotak kosong.
                    //
                    // Tidak ada query tambahan: tenant-nya sudah dimuat, dan
                    // `logo` ada di barisnya.
                    'logo_url' => app(TenantLogoService::class)->urlFor($user->tenant),
                    // Bukan kapabilitas, jadi tidak ikut `features`: ia
                    // menentukan apa yang ditanyakan kasir sebelum menyimpan,
                    // bukan pintu mana yang terbuka ([BL-026]). Skalar biasa —
                    // tenant-nya sudah dimuat, jadi tidak ada query tambahan.
                    'order_identity_mode' => $user->tenant->order_identity_mode,
                    // Juga bukan kapabilitas: ia menjawab "apakah semua pintu
                    // lain sedang terkunci?", bukan "apakah outlet ini punya
                    // fitur X". Sidebar memerlukannya karena pada tenant yang
                    // ditangguhkan setiap tautannya memantul balik ke halaman
                    // langganan — menu yang tak satu pun bisa dibuka lebih
                    // menyesatkan daripada menu yang jujur mengaku mati.
                    // Skalar biasa; tenant-nya sudah dimuat.
                    'is_suspended' => $user->tenant->isSuspended(),
                    // Keadaan langganan yang membatasi, dibagikan ke SETIAP
                    // layar ([BL-045]). Penegakannya sudah lama benar, tapi ia
                    // tak terlihat: kasir yang membuka POS langsung, atau owner
                    // yang seharian di halaman Produk, tidak tahu apa-apa
                    // sampai ia menekan Simpan dan mendapat penolakan.
                    // Peringatan sebelum tombol ditekan jauh lebih murah
                    // daripada penolakan sesudahnya.
                    //
                    // `null` untuk tenant yang tidak sedang dibatasi, dan itu
                    // yang membuatnya gratis pada jalur panas: `status` sudah
                    // ada di baris tenant yang termuat, jadi mayoritas request
                    // tidak pernah menyentuh query tambahan sama sekali.
                    'subscription' => $this->restrictionFor($user->tenant),
                    'features' => fn () => [
                        'kitchen_queue' => $user->tenant->hasFeature('kitchen_queue'),
                        'self_order' => $user->tenant->hasFeature('self_order'),
                        'ai' => $user->tenant->hasFeature('ai'),
                        // Dibagikan, bukan dikirim per halaman: pelunasan
                        // tagihan terbuka hidup di CashierTopbar, yang ada di
                        // SETIAP halaman kasir dan tidak punya controller
                        // sendiri untuk menitipkan propnya ([BL-075]).
                        'payment_proof' => $user->tenant->hasFeature('payment_proof'),
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
                // Faktor kedua platform ([BL-013]). Lewat flash, BUKAN prop
                // halaman: rahasia TOTP dan kode pemulihan hanya boleh
                // menyeberang sekali, pada respons yang menerbitkannya. Prop
                // tetap berarti keduanya ikut di setiap kunjungan berikutnya
                // ke halaman itu, termasuk lama setelah pendaftaran selesai.
                'twoFactorSetup' => fn () => $request->session()->get('twoFactorSetup'),
                'recoveryCodes' => fn () => $request->session()->get('recoveryCodes'),
            ],
        ]);
    }

    /**
     * Keadaan langganan yang membatasi tenant ini, atau null bila tidak ada.
     *
     * Sejak `[BL-054]` isinya membawa TAHAP tenggatnya, bukan cuma status:
     * hari 1–14 halus, 15–19 mengganggu, 20+ menulis dicabut. Tanpa tahap,
     * layar hanya punya dua pilihan — diam atau berteriak — dan tangga tekanan
     * yang diputuskan pemilik tidak akan pernah terlihat oleh pengguna.
     *
     * Hanya `grace` dan `suspended` yang menghasilkan isi. Peringatan sebelum
     * periodenya lewat — tagihan yang sudah terbit tapi belum jatuh tempo —
     * sengaja TIDAK ikut di sini dan tetap tinggal di kartu langganan Dashboard
     * (`[BL-040]`): mengetahuinya menuntut satu query tagihan pada setiap
     * request, termasuk tiap ketukan di POS, dan itu ongkos yang tidak sepadan
     * untuk keterangan yang belum mendesak. Yang mendesak — akses sudah
     * menyempit — justru tidak butuh query apa pun untuk diketahui.
     *
     * @return array{status: string, stage: string|null, grace_day: int|null, grace_days: int, lock_from_day: int, can_write: bool, payment_pending: bool, adaptive_pending: bool, suspends_at: string|null, period_ends_at: string|null}|null
     */
    private function restrictionFor(Tenant $tenant): ?array
    {
        if (! $tenant->isInGrace() && ! $tenant->isSuspended()) {
            return null;
        }

        $subscriptions = app(SubscriptionService::class);

        return [
            'status' => $tenant->status,
            'stage' => $tenant->graceStage(),
            'grace_day' => $tenant->graceDay(),
            'grace_days' => SubscriptionService::graceDays(),
            // Layar menghitung "tinggal berapa hari lagi" dari sini, bukan dari
            // angka yang diketik ulang di JavaScript — kebijakan tenggat yang
            // diubah di config harus ikut mengubah kalimat di layar.
            'lock_from_day' => SubscriptionService::graceLockFromDay(),
            // Dikirim apa adanya supaya layar tidak perlu menyusun ulang tangga
            // tenggat dari tiga angka — satu-satunya sumber "boleh menulis?"
            // tetap `Tenant::canWrite()`.
            'can_write' => $tenant->canWrite(),
            // Modal penagihan padam selagi pembayaran berjalan. Menagih orang
            // yang sudah membayar adalah cara tercepat kehilangan mereka
            // (`[BL-054]`(c)). Yang padam HANYA notifikasinya — jam tenggatnya
            // jalan terus, karena kalau tidak, menerbitkan instruksi bayar lalu
            // mendiamkannya akan jadi cara membeli waktu tanpa membayar
            // (`[BL-054]`(d)).
            'payment_pending' => $this->hasPaymentInFlight($tenant),
            // Pemadam kedua yang `[BL-054]`(c) minta dan `[BL-055]` sediakan:
            // tenant yang sudah mengajukan keringanan juga berhenti ditagih
            // lewat modal. Ia sudah melakukan hal yang diminta halaman itu —
            // menyerahkan data omzetnya — dan meneruskan teriakan kepadanya
            // menghukum orang yang justru menurut.
            //
            // Tidak butuh query: jalur harga sudah ada di baris tenant, dan
            // perpindahan ke jalur adaptif hanya terjadi setelah persetujuannya
            // tercatat.
            'adaptive_pending' => $tenant->pricing_track === Subscription::TRACK_SUBSIDIZED,
            // Dihitung, bukan disimpan — satu-satunya sumbernya sama dengan
            // yang dipakai kartu Dashboard, supaya tanggal di pita dan tanggal
            // di kartu tidak pernah berselisih.
            'suspends_at' => $subscriptions->suspensionDateFor($tenant)?->toDateString(),
            'period_ends_at' => $subscriptions->ensureFor($tenant)->current_period_end?->toDateString(),
        ];
    }

    /**
     * Apakah tenant ini punya instruksi bayar yang masih berlaku.
     *
     * Satu query, dan hanya untuk tenant yang memang sedang dibatasi — jalur
     * panas (tenant `active`) tidak pernah sampai ke sini.
     */
    private function hasPaymentInFlight(Tenant $tenant): bool
    {
        return PaymentAttempt::where('tenant_id', $tenant->id)
            ->where('status', PaymentAttempt::STATUS_PENDING)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    /**
     * Tagihan terbuka milik kasir ini, seperlunya untuk panel topbar.
     *
     * Ikut membawa itemnya: panel menampilkan isi pesanan supaya kasir tahu
     * tagihan mana yang ia buka tanpa harus melunasinya dulu. Jumlahnya
     * dibatasi keadaan — tagihan terbuka yang menumpuk sampai berat adalah
     * masalah operasional yang harus terlihat, bukan disembunyikan paginasi.
     *
     * **Berumur, sejak [BL-031].** Batasnya bukan pengulangan sapuan
     * terjadwal melainkan penutup celah di antaranya: `open-bills:expire`
     * berjalan tiap jam, jadi tanpa penyaring ini ada sampai satu jam di mana
     * kasir masih melihat — dan bisa menekan "Bayar" pada — tagihan yang
     * menurut aturan sudah mati. Keduanya membaca ambang yang sama dari
     * `Transaction::openBillCutoff()`.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function openBillsFor(User $user): \Illuminate\Support\Collection
    {
        return Transaction::where('user_id', $user->id)
            ->liveOpenBills()
            ->with(['items:id,transaction_id,variant_name,qty,notes'])
            ->latest()
            ->get(['id', 'code', 'customer_name', 'table_number', 'total_amount', 'created_at', 'occurred_at'])
            ->map(fn (Transaction $bill) => [
                'id' => $bill->id,
                'code' => $bill->code,
                'customer_name' => $bill->customer_name,
                'table_number' => $bill->table_number,
                'total_amount' => $bill->total_amount,
                'created_at' => $bill->created_at,
                // Kapan tagihan ini mati. Dikirim sebagai cap waktu, bukan
                // sebagai kalimat: layar yang menghitung sendiri sisa waktunya
                // tetap benar walau tab kasir dibiarkan terbuka berjam-jam.
                'expires_at' => $bill->effectiveDate()->copy()->addHours(Transaction::OPEN_BILL_LIFETIME_HOURS),
                'items' => $bill->items->map(fn ($item) => [
                    'id' => $item->id,
                    'variant_name' => $item->variant_name,
                    'qty' => $item->qty,
                    'notes' => $item->notes,
                ]),
            ]);
    }
}
