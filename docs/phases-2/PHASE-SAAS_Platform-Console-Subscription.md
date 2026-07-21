# PHASE SAAS — Platform Console & Langganan Dua Jalur

**Status:** Rencana — belum dikerjakan
**Estimasi:** Besar — dipecah jadi 4 tahap yang bisa dikerjakan berurutan (Tahap A paling kecil & mandiri)
**Dependency:** `PHASE-RBAC_Module-Access-Control` (sudah selesai — pola modul+centang dipakai ulang), `spatie/laravel-permission` (sudah ada). Belum butuh dependency baru.
**Output:** Panel khusus pemilik SaaS untuk mengelola tenant, langganan, dan pembayaran — dengan batas privasi yang ditegakkan di lapisan query, plus skema harga dua jalur (normal & subsidi UMKM berbasis omset).
**Dipakai oleh:** Pemilik SaaS (platform console), owner tenant (halaman consent, upgrade, pemilihan jalur harga).
**Sumber:** `docs/BACKLOG.md` → `[BL-005]` & `[BL-006]`

> Tujuan: pemilik SaaS bisa menjalankan bisnisnya (tahu siapa pelanggannya, siapa yang bayar, berapa) **tanpa** bisa mengintip data operasional klien — kecuali satu hal yang dibuka secara sukarela dengan imbalan diskon, dan itu pun dinyatakan terang-terangan.

---

## Bagian 0 — Keputusan Desain (hasil diskusi 2026-07-21)

| Keputusan | Pilihan | Alasan |
|---|---|---|
| **Identitas platform admin** | Tabel + guard **terpisah** (`platform_users`, guard `platform`) | Akun platform tidak punya `tenant_id`. Menumpangkannya di `users` membuat `TenantScope` & spatie *teams* berperilaku aneh. **Lihat peringatan di Bagian 2 — pemisahan ini punya sisi tajam.** |
| **RBAC platform** | Pakai ulang **pola** modul+centang dari Phase RBAC, tapi **tanpa spatie** — tabel sendiri | Pola UI persis sama seperti yang diminta. spatie tidak bisa dipakai karena pivot `model_has_roles` menuntut `tenant_id` non-null (bagian dari primary key) — terverifikasi, lihat Bagian 3. |
| **Data omset (jalur subsidi)** | Dihitung otomatis, **angka persis dalam rupiah** | Otomatis = tidak bisa diakali tenant. Angka persis = penetapan harga bisa dibuktikan saat tenant menyanggah. |
| **Laba / margin** | **Tidak dibuka sama sekali** | Omset persis + margin persen = laba rupiah tinggal satu perkalian. Untuk penetapan harga berbasis kemampuan bayar, omset saja sudah cukup. |
| **Periode awal tenant baru** | **Trial gratis 1 bulan** | Bulan trial sekaligus jadi periode pengukuran omset → masalah ayam-telur (tenant baru belum punya data) selesai sendiri. |
| **Halaman consent** | **Dua dokumen terpisah** (subsidi & normal), berversi | Dokumen bersyarat mengaburkan hal terpenting. Dipisah = masing-masing pendek, jujur, benar-benar terbaca. |
| **Pendaftaran** | Self-serve (**sudah ada** di `AuthController@register`) | Yang baru hanya: mulai trial, pemilihan jalur harga, dan consent. |
| **Pembayaran v1** | Dicatat **manual** oleh pemilik SaaS + bukti transfer | Cukup untuk jumlah tenant awal. Payment gateway ditunda. |
| **Batas seat** | Penegakan **keras** — tolak sampai upgrade | Bagi UMKM, tagihan mendadak jauh lebih menyakitkan daripada tombol yang menolak dengan penjelasan. |
| **Upgrade saat mentok seat** | **Provisional** — aktif begitu bukti transfer diunggah, diverifikasi belakangan | Tanpa ini, blokir keras + pembayaran manual bisa mengunci tenant di jam sibuk sampai pemilik SaaS sempat memeriksa. |

### Prinsip WAJIB

- **Privasi ditegakkan di lapisan query, bukan UI.** Menyembunyikan kolom di Vue tidak cukup — datanya tetap terkirim di payload Inertia dan terbaca lewat DevTools. Controller platform hanya boleh mengirim resource/DTO yang memang tidak pernah memuat field terlarang.
- **Controller platform tidak menyentuh model operasional.** `Transaction`, `Product`, `AiAnalysis`, `StockMovement`, dan sejenisnya haram diimpor dari namespace `App\Http\Controllers\Platform`. Ditegakkan dengan arch test, bukan disiplin manual.
- **Satu-satunya pintu ke data mentah** adalah job penghitung omset (Bagian 5). Satu pintu = mudah diaudit.
- **Tidak ada kolom laba/HPP** di tabel metrik. Bukan disimpan lalu disembunyikan — memang tidak pernah dihitung.
- **Setiap akses & perubahan dicatat** di audit log. Inilah yang membuat janji privasi bisa dibuktikan, bukan sekadar diklaim.
- **Pint** setelah edit PHP; tiap perubahan **diuji** (Pest).

---

## Bagian 1 — Temuan Kode (peta jalan saat ini)

| Hal | Kondisi sekarang | Implikasi |
|---|---|---|
| Tingkatan akses | Hanya **tenant** & **user di dalam tenant** (`users.role` enum owner/cashier + RBAC modul) | Tingkat ketiga (platform) belum ada sama sekali. |
| `Tenant` | Kolom: `name`, `slug`, `logo`, `address`, `phone`, `ai_provider`, `ai_api_key`, `ai_model` | Tidak ada satu pun konsep langganan/harga. Semua baru. |
| `TenantScope` | `app/Models/Scopes/TenantScope.php:13` — hanya berlaku **bila `auth()->check()`** (guard default) | ⚠️ **Sisi tajam** — lihat Bagian 2. Di job/console scope ini jadi no-op. |
| `BelongsToTenant` | `app/Traits/BelongsToTenant.php` — global scope + auto-isi `tenant_id` saat create | Dipakai `Transaction` dkk. |
| Registrasi | `Auth/AuthController@register:26-57` membuat `Tenant` + `User` role `owner` | **Self-serve sudah jalan.** Tinggal ditambah: mulai trial, pilih jalur, consent. |
| Omset | `Owner/ReportController@daily:30-35` → `status = completed` lalu `sum('total_amount')` | Pola yang sama dipakai job metrik bulanan. |
| Tanggal efektif | `Transaction::whereEffectiveBetween()` / `effectiveDateSql()` = `COALESCE(occurred_at, created_at)` | **Wajib** dipakai — penjualan offline dibuat di server saat sync, bisa beda bulan. |
| Status transaksi | enum `pending` / `completed` / `voided` | Omset hanya menjumlahkan `completed`. |
| Staf | `Owner/StaffController@store:37` | Titik penegakan batas seat. |
| RBAC | `config/rbac.php` (katalog modul) → `Owner/RoleController@store:47-52` (validasi `Rule::in` + `syncPermissions`) | **Pola** ini dipakai ulang untuk platform, dengan katalog terpisah. |
| Middleware alias | `tenant`, `tenant.api`, `role`, `permission` (`bootstrap/app.php:27-32`) | Semua mengasumsikan user punya `tenant_id`. Panel platform ada di luar semuanya. |
| Inertia share | `HandleInertiaRequests@share:29` memanggil `$request->user()->isOwner()` | ⚠️ **Blocker Tahap A** — `PlatformUser` tidak punya method itu → `BadMethodCallException` di **setiap** halaman platform. Lihat Bagian 2d. |

---

## Bagian 2 — Identitas Platform Admin (guard terpisah)

### 2a. Tabel & guard

```bash
php artisan make:model PlatformUser -m --no-interaction
```

`platform_users`: `id`, `name`, `email` (unique), `password`, `remember_token`, `timestamps`. Tanpa `tenant_id` — memang tidak punya tenant.

`config/auth.php`:
```php
'guards' => [
    // ... 'web', 'sanctum' tetap
    'platform' => ['driver' => 'session', 'provider' => 'platform_users'],
],
'providers' => [
    // ...
    'platform_users' => ['driver' => 'eloquent', 'model' => App\Models\PlatformUser::class],
],
```

### 2b. ⚠️ Sisi tajam yang WAJIB dipahami sebelum menulis kode

`TenantScope` berbunyi:

```php
// app/Models/Scopes/TenantScope.php:13
if (auth()->check()) {
    $builder->where($model->getTable().'.tenant_id', auth()->user()->tenant_id);
}
```

`auth()->check()` memeriksa **guard default**, dan guard default itu **berpindah**: middleware `auth:platform` memanggil `Auth::shouldUse('platform')` (terverifikasi di `vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php:83`), sehingga sisa request memakai guard platform sebagai default. Hasilnya:

| Konteks | `auth()->check()` | `auth()->user()->tenant_id` | Efek `TenantScope` | Akibat |
|---|---|---|---|---|
| Request tenant biasa | `true` | id tenant | Filter per tenant | Aman, seperti sekarang |
| Request platform **di balik `auth:platform`** | `true` | `null` (kolomnya tidak ada di `PlatformUser`) | `where tenant_id = null` | **Nol baris** — gagal menutup, bukan gagal membuka |
| Rute platform **tanpa** `auth:platform` (mis. halaman login, atau lupa dipasang) | `false` | — | **Tidak difilter** | Data **seluruh tenant** |
| Queue job / command / seeder | `false` | — | **Tidak difilter** | Data **seluruh tenant** |

Dua pelajaran dari tabel ini, dan keduanya penting:

1. **Kabar baik:** request platform yang benar-benar terautentikasi **gagal menutup** (`where tenant_id = null` tidak cocok dengan apa pun), bukan membocorkan. Ini jaring pengaman tak sengaja yang layak disyukuri — tapi **jangan dijadikan andalan**, karena ia lahir dari kebetulan bahwa `PlatformUser` tidak punya kolom `tenant_id`, bukan dari desain.
2. **Kabar buruk:** di **queue job, command, seeder, dan rute platform yang belum/lupa dipasangi `auth:platform`**, scope-nya benar-benar tidak aktif. Di konteks itu `Transaction::query()` mengembalikan data semua tenant tanpa satu pun peringatan.

> **Jebakan turunan yang perlu diantisipasi:** karena request platform yang sehat menghasilkan **nol baris**, developer yang kelak menulis fitur platform bisa mengira ada bug ("kok datanya kosong?") lalu "memperbaikinya" dengan membuang global scope. Justru di situ kebocoran akan lahir. Karena itu arch test di Bagian 11 bukan formalitas — dialah yang akan menahan perubahan semacam itu.

**Karena itu isolasi tidak boleh bergantung pada `TenantScope`, melainkan pada tiga lapis:**

1. **Arch test** (Bagian 11) yang gagal bila namespace `Platform` mengimpor model operasional. Ini pertahanan utama.
2. **Resource/DTO khusus** — controller platform hanya boleh mengembalikan `PlatformTenantResource` dkk., tidak pernah model mentah.
3. **Filter `tenant_id` eksplisit** di job metrik (Bagian 5), ditulis dengan `withoutGlobalScopes()` supaya niatnya terbaca, bukan bergantung pada scope yang kebetulan tidak aktif.

> Alternatif yang **ditolak**: memperketat `TenantScope` agar melempar exception bila tidak ada auth. Terdengar aman, tapi akan merusak seeder, factory, dan semua job yang ada sekarang. Risikonya lebih besar daripada manfaatnya; arch test memberi perlindungan yang setara dengan biaya jauh lebih kecil.

### 2c. Rute & layout

```php
// routes/web.php — grup baru, di luar middleware 'tenant'
Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('/login', ...)->name('login');
    Route::post('/login', ...);

    Route::middleware(['auth:platform'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/tenants', [TenantController::class, 'index'])
            ->middleware('platform.can:tenants')->name('tenants.index');
        // ... modul lain, tiap modul digerbang permission-nya sendiri
    });
});
```

Layout terpisah `resources/js/Pages/Platform/` + `PlatformLayout.vue`. Sengaja tidak memakai `OwnerLayout` agar tidak ada nav tenant yang tercampur.

### 2d. ⚠️ Blocker konkret: `HandleInertiaRequests` akan crash

Ini bukan risiko teoretis — akan terjadi di halaman platform pertama yang dirender.

```php
// app/Http/Middleware/HandleInertiaRequests.php:16-35
'user' => $request->user() ? [
    // ...
    'role'       => $request->user()->role,        // PlatformUser: kolom tidak ada → null (aman)
    'tenant_id'  => $request->user()->tenant_id,   // idem → null (aman)
    'permissions' => fn () => $request->user()->isOwner()   // ← PlatformUser tidak punya isOwner()
        ? ['*'] : /* ... config('rbac.modules') ... */,
] : null,
```

Karena `auth:platform` memindahkan guard default, `$request->user()` **mengembalikan `PlatformUser`**, bukan `null`. Cabang ternary-nya diambil, lalu closure `permissions` memanggil `isOwner()` yang tidak ada di `PlatformUser` → `BadMethodCallException`. Kolom `role`/`tenant_id` sendiri aman (Eloquent mengembalikan `null` untuk atribut yang tidak ada), jadi **satu-satunya** yang meledak adalah baris `isOwner()` — tapi itu cukup untuk membuat seluruh panel tidak bisa dibuka.

Perbaikan (dikerjakan di Tahap A, sebelum halaman platform pertama):
```php
'user' => $request->user() instanceof \App\Models\User ? [ /* ...seperti sekarang... */ ] : null,
'platformUser' => $request->user() instanceof \App\Models\PlatformUser ? [
    'id' => ..., 'name' => ..., 'email' => ...,
    'modules' => fn () => $request->user()->modules(),   // dari platform_user_modules
] : null,
```

> Sengaja **dipisah** jadi `auth.platformUser`, bukan menumpang `auth.user`. Kalau ditumpangkan, tiap komponen Vue yang membaca `auth.user.role` atau `auth.user.tenant_id` akan menerima `null` diam-diam dan berperilaku aneh — dan itu jenis bug yang sulit dilacak. Dengan dipisah, komponen tenant tetap melihat `auth.user = null` di konteks platform, yang jujur dan mudah dibaca.

---

## Bagian 3 — RBAC Platform (pola modul + centang)

Pola sama persis dengan Phase RBAC, katalog berbeda.

`config/platform-rbac.php`:
```php
return [
    'modules' => [
        'tenants'       => ['label' => 'Daftar Tenant',      'sensitive' => false],
        'subscriptions' => ['label' => 'Langganan',          'sensitive' => false],
        'payments'      => ['label' => 'Pembayaran',         'sensitive' => false],
        'pricing_rules' => ['label' => 'Aturan Harga',       'sensitive' => true],
        'revenue_data'  => ['label' => 'Data Omset Subsidi', 'sensitive' => true],
        'audit_logs'    => ['label' => 'Log Audit',          'sensitive' => true],
    ],
];
```

> `revenue_data` sengaja dipisah dari `tenants`. Melihat daftar tenant dan melihat omset mereka adalah dua kewenangan berbeda — kelak staf platform bisa diberi yang pertama tanpa yang kedua.

### ❌ spatie TIDAK dipakai untuk sisi platform — sudah diverifikasi, bukan dugaan

Rencana awal ingin memakai spatie dengan `team_id = null` untuk role platform. **Itu tidak mungkin.** Lihat migration yang sudah ter-generate di repo, `database/migrations/2026_07_20_201925_create_permission_tables.php:88-93`:

```php
if ($teams) {
    $table->unsignedBigInteger($columnNames['team_foreign_key']);   // ← TANPA ->nullable()
    $table->index(...);
    $table->primary([$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'], ...);
}
```

Di `model_has_roles` (dan `model_has_permissions`), kolom `tenant_id` **tidak nullable** *dan* menjadi bagian **primary key** — dan kolom primary key tidak boleh NULL. Jadi menugaskan role ke akun platform dengan `team_id = null` akan gagal di tingkat database. (Tabel `roles` sendiri memang membolehkan `tenant_id` null di baris 41, tapi itu tidak menolong: yang bermasalah adalah pivot penugasannya.)

Menyiasatinya dengan nilai sentinel (`tenant_id = 0`) secara teknis jalan — tidak ada foreign key di kolom itu — tapi berarti menaruh angka ajaib yang berarti "bukan tenant" di dalam kolom bernama `tenant_id`. Itu jebakan bagi siapa pun yang membaca skema ini setahun lagi.

**Keputusan: pakai tabel sendiri.**

```
platform_user_modules: platform_user_id, module   (unique bersama)
```

Kebutuhannya memang hanya "akun ini boleh membuka modul ini" untuk segelintir akun — tidak butuh role hierarkis, guard, cache, maupun teams. Katalog di `config/platform-rbac.php` tetap jadi sumber kebenaran, dan **pola UI-nya tetap sama** dengan Phase RBAC (daftar modul + centang), yang memang itulah yang diminta. Yang dipakai ulang adalah polanya, bukan paketnya.

Middleware `platform.can:{module}` dibuat sendiri, memeriksa tabel di atas. Tidak menyentuh alias `permission` milik tenant sama sekali — jadi tidak ada risiko bertabrakan dengan team-id.

---

## Bagian 4 — Model Data Langganan

```bash
php artisan make:model Plan -m --no-interaction
php artisan make:model PricingRule -m --no-interaction
php artisan make:model Subscription -m --no-interaction
php artisan make:model Invoice -m --no-interaction
php artisan make:model TenantConsent -m --no-interaction
php artisan make:model TenantMonthlyMetric -m --no-interaction
php artisan make:model PlatformAuditLog -m --no-interaction
```

| Tabel | Kolom inti | Catatan |
|---|---|---|
| `plans` | `name`, `base_price`, `included_seats`, `extra_seat_price`, `is_active` | Tarif publik jalur normal. |
| `pricing_rules` | `plan_id`, `min_revenue`, `max_revenue`, `price`, `effective_from` | Bracket omset → harga, jalur subsidi. **`effective_from` wajib** — lihat Bagian 8. |
| `subscriptions` | `tenant_id`, `plan_id`, `pricing_track`, `status`, `seats`, `price_locked`, `trial_ends_at`, `current_period_start/end` | `price_locked` = harga yang benar-benar berlaku untuk tenant ini (hasil grandfathering). |
| `invoices` | `tenant_id`, `subscription_id`, `amount`, `period`, `status`, `paid_at`, `proof_path`, `verified_by`, `verified_at` | v1 dicatat manual. `proof_path` dipakai upgrade provisional. |
| `tenant_consents` | `tenant_id`, `user_id`, `type`, `version`, `agreed_at`, `ip`, `revoked_at` | `type` = `normal` \| `subsidized`. **Versi wajib disimpan.** |
| `tenant_monthly_metrics` | `tenant_id`, `period` (YYYY-MM), `revenue`, `transaction_count`, `computed_at` | **Tidak ada kolom laba/HPP.** Hanya diisi untuk tenant jalur subsidi. |
| `platform_audit_logs` | `platform_user_id`, `action`, `subject_type`, `subject_id`, `meta`, `ip`, `created_at` | Append-only. |

`tenants` bertambah: `pricing_track` (`normal` \| `subsidized`, default `normal`), `status` (`trial` \| `active` \| `suspended`).

> **Kenapa `pricing_track` di `tenants` dan bukan hanya di `subscriptions`:** job metrik perlu memfilter tenant tanpa harus join ke langganan, dan filternya harus semurah & sesederhana mungkin karena inilah gerbang privasinya.

---

## Bagian 5 — Job Penghitung Omset (satu-satunya pintu ke data mentah)

```bash
php artisan make:job ComputeTenantMonthlyRevenue --no-interaction
```

```php
public function handle(): void
{
    $period = now()->subMonth();                      // hitung bulan yang sudah tutup

    Tenant::query()
        ->where('pricing_track', 'subsidized')        // GERBANG PRIVASI — jalur normal tidak pernah tersentuh
        ->each(function (Tenant $tenant) use ($period) {
            // withoutGlobalScopes() ditulis EKSPLISIT: di konteks job, TenantScope
            // memang tidak aktif (auth()->check() false), tapi jangan bergantung
            // pada kebetulan itu — nyatakan niatnya, lalu filter tenant_id sendiri.
            $revenue = Transaction::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->whereEffectiveBetween(
                    $period->copy()->startOfMonth(),
                    $period->copy()->endOfMonth(),
                )
                ->sum('total_amount');

            TenantMonthlyMetric::updateOrCreate(
                ['tenant_id' => $tenant->id, 'period' => $period->format('Y-m')],
                ['revenue' => $revenue, 'computed_at' => now()],
            );
        });
}
```

**Yang wajib diperhatikan:**
- `whereEffectiveBetween`, **bukan** `created_at` — penjualan offline dibuat di server saat sync dan bisa jatuh di bulan berikutnya. Ini konsisten dengan `ReportController@daily`.
- Hanya `STATUS_COMPLETED`. `voided` dan `pending` tidak dihitung — kalau ikut terhitung, tenant bisa menaikkan omsetnya (dan karenanya harganya) tanpa penjualan nyata, atau sebaliknya dirugikan oleh transaksi batal.
- Dashboard platform **hanya membaca `tenant_monthly_metrics`**, tidak pernah `transactions`. Ini yang membuat arch test di Bagian 11 bisa ditegakkan.
- Dijadwalkan di `routes/console.php`, awal tiap bulan.

---

## Bagian 6 — Consent (dua dokumen, berversi)

### 6a. Isi wajib tiap dokumen

Apa persisnya yang dibagikan · seberapa sering · untuk apa · berapa lama disimpan · cara mencabut · **akibat pencabutan**.

- **Dokumen `subsidized`** harus menyatakan lugas, tanpa eufemisme: *omset bulanan dalam angka rupiah dihitung otomatis dari transaksi dan dilihat oleh pengelola layanan untuk menentukan harga.* Wajib juga menyebut bahwa perhitungan **mencakup periode trial yang sudah berlalu** — datanya terkumpul sebelum persetujuan ditandatangani, dan itu harus disampaikan di muka, bukan ditemukan sendiri oleh tenant belakangan.
- **Dokumen `normal`** menyatakan sebaliknya: tidak ada data bisnis yang dibuka.

### 6b. Anti dark pattern

Checkbox **tidak boleh** ter-centang duluan · tombol setuju baru aktif setelah digulir sampai bawah · ringkasan bahasa manusia di atas, detail di bawah · hanya **owner tenant** yang boleh menyetujui (staf tidak).

### 6c. Bukti

Simpan `type`, `version`, `user_id`, `agreed_at`, `ip`. Teks consent disimpan sebagai file berversi (mis. `resources/consents/subsidized-v1.md`) sehingga "dia dulu setuju" selalu bisa dirujuk ke teks yang mana.

---

## Bagian 7 — Trial & Alur Pemilihan Jalur

Registrasi self-serve **sudah ada** (`AuthController@register:26-57`). Yang ditambahkan:

1. Saat tenant dibuat → `status = 'trial'`, buat `subscriptions` dengan `trial_ends_at = now()->addMonth()`, `pricing_track = 'normal'`.
2. Selama trial: aplikasi berjalan normal, tidak ada consent subsidi, tidak ada perhitungan omset.
3. Menjelang & saat trial berakhir, owner memilih:
   - **Bayar tarif normal** → setujui consent `normal` → tagihan pertama terbit.
   - **Ajukan subsidi** → setujui consent `subsidized` → `pricing_track = 'subsidized'` → job menghitung omset bulan trial → bracket & harga ditetapkan.
4. Notifikasi in-app menjelang akhir trial (H-7, H-1).

**Belum diputuskan (lihat Bagian 12):** apa yang terjadi bila owner tidak memilih apa pun sampai trial habis, dan bagaimana mencegah pendaftaran berulang demi trial gratis baru.

---

## Bagian 8 — Aturan Harga & Grandfathering

Semua tarif adalah **data**, di-CRUD dari platform console — bukan konstanta di kode.

**Aturan yang tidak boleh dilanggar:** mengubah `pricing_rules` **tidak boleh** mengubah tagihan tenant yang sudah berjalan. Tanpa ini, satu kali edit angka bracket akan mengubah tagihan semua orang seketika — termasuk tenant yang sudah menyetujui harga lain.

Mekanismenya:
- `pricing_rules.effective_from` — aturan baru berlaku untuk periode setelah tanggal itu.
- `subscriptions.price_locked` — harga yang benar-benar ditagihkan ke tenant, ditetapkan saat periode dimulai dan tidak ikut berubah di tengah jalan.
- Perubahan tarif **wajib** masuk `platform_audit_logs`.

---

## Bagian 9 — Batas Seat & Upgrade Provisional

### 9a. Penegakan keras

Di `Owner/StaffController@store` (baris 37), **sebelum** user dibuat:

```php
if ($tenant->users()->count() >= $subscription->seats) {
    return back()->with('error', "Paket Anda mencakup {$subscription->seats} pengguna. ...");
}
```

Menghitung seat **tidak butuh data bisnis** — cukup `count()` di `users` — jadi bagian ini aman untuk semua jalur harga.

**Wajib ditutup juga jalur lain yang bisa membuat user**: API mobile, seeder, dan registrasi. Gerbang di satu tempat saja bukan gerbang.

### 9b. Upgrade provisional

Tenant mengunggah bukti transfer → paket **langsung aktif** → pemilik SaaS memverifikasi belakangan.

Bila bukti ditolak (palsu, nominal kurang, salah unggah):
- Beri tenggang (mis. 3×24 jam) dengan pemberitahuan agar tenant memperbaiki.
- Bila tetap gagal: **kunci penambahan staf baru, jangan nonaktifkan staf yang sudah terlanjur dibuat.** Menonaktifkan akun yang sedang dipakai bekerja jauh lebih merusak kepercayaan daripada sekadar menahan penambahan berikutnya.
- Batasi agar tidak jadi celah: maksimal satu upgrade provisional yang belum terverifikasi dalam satu waktu, dan tenant yang pernah gagal verifikasi tidak lagi mendapat fasilitas ini.

---

## Bagian 10 — Audit Log

Dicatat **wajib**: login platform, membuka data omset tenant, mengubah `plans`/`pricing_rules`, memverifikasi/menolak pembayaran, mengubah status tenant (suspend/aktif).

Append-only — tidak ada UI hapus/edit. Log yang bisa disunting tidak membuktikan apa pun.

> Di `[BL-005]` audit log sempat berstatus "bagus untuk dimiliki". Karena v1 ternyata **harus** bisa menulis (pemilik SaaS mengatur tarif sendiri), statusnya naik jadi **wajib ada sejak Tahap A**.

---

## Bagian 11 — Tests (Pest)

| Test | Inti yang diuji |
|---|---|
| `PlatformAuthTest` | User tenant tidak bisa masuk `/platform`; platform admin tidak bisa masuk rute tenant. |
| `PlatformIsolationTest` | **Yang terpenting.** Request platform tidak pernah mengembalikan data operasional. Termasuk kasus eksplisit: platform admin login → `TenantScope` tidak aktif → pastikan tidak ada endpoint platform yang membocorkan `transactions`/`products`. |
| `PlatformArchTest` | `arch()` — namespace `App\Http\Controllers\Platform` tidak boleh mengimpor `Transaction`, `Product`, `AiAnalysis`, `StockMovement`, `Category`. |

> **Batas arch test yang harus disadari.** `arch()` memeriksa **impor**, jadi ia **tidak** menangkap tiga jalur ini:
> - traversal relasi — `$tenant->transactions()->sum(...)` tidak mengimpor apa pun;
> - query mentah — `DB::table('transactions')`;
> - `Tenant::with('transactions')` di resource.
>
> Karena itu arch test **harus** ditemani `PlatformIsolationTest` yang menguji di tingkat HTTP: buat 2 tenant berisi transaksi, login sebagai platform admin, panggil tiap endpoint platform, lalu assert bahwa payload responsnya tidak pernah memuat kunci/angka milik data operasional. Test itulah yang menangkap tiga jalur di atas. Arch test menjaga niat, isolation test menjaga hasil — keduanya perlu.
| `ComputeTenantMonthlyRevenueTest` | Hanya tenant `subsidized` yang punya baris metrik · `voided`/`pending` tidak ikut terhitung · transaksi offline dengan `occurred_at` bulan lalu masuk ke periode yang benar. |
| `TenantConsentTest` | Consent tercatat lengkap dengan versi · staf tidak bisa menyetujui · pencabutan berfungsi. |
| `SeatLimitTest` | Staf ke-N+1 ditolak · upgrade provisional membuka batas · penolakan bukti tidak menonaktifkan staf yang sudah ada. |
| `PricingGrandfatherTest` | Mengubah `pricing_rules` tidak mengubah `price_locked` langganan berjalan. |
| `PlatformRbacTest` | Modul yang tidak dicentang tidak bisa dibuka; `revenue_data` terpisah dari `tenants`. |

---

## Bagian 12 — Keputusan Terbuka

Belum menghalangi Tahap A, tapi **harus** selesai sebelum Tahap C/D:

1. Apa yang terjadi bila tenant tidak memilih apa pun di akhir trial — dibekukan, read-only, atau tetap jalan sampai ditagih? Dan berapa lama datanya disimpan.
2. Mitigasi pendaftaran berulang demi trial gratis baru (verifikasi telepon? persetujuan manual untuk identitas serupa?).
3. Pencabutan consent subsidi: diskon langsung hilang atau berlaku sampai akhir periode? (saran: **akhir periode** — pencabutan yang langsung menaikkan tagihan membuat orang takut mencabut, dan consent yang tidak bisa dicabut dengan tenang bukan consent).
4. Apakah daftar tenant menampilkan bracket saja, dengan angka persis dibuka lewat aksi "lihat rincian" yang tercatat di audit log?
5. Metrik agregat untuk tenant **jalur normal** — jumlah transaksi, jumlah produk, kuota AI: boleh terlihat atau tidak?
6. Definisi seat: semua user atau hanya yang aktif? Apakah menonaktifkan staf membebaskan seat (dan bagaimana mencegah pola aktif–nonaktif bergantian)?
7. Boleh pindah jalur normal ↔ subsidi kapan saja, atau ada periode minimum?
8. Boleh suspend tenant yang menunggak? Apa yang terjadi pada datanya selama suspend?

---

## Bagian 13 — Checklist Eksekusi (bertahap)

### Tahap A — Fondasi platform (mandiri, tidak menyentuh alur tenant)
- [ ] `platform_users` + guard `platform` + login/logout
- [ ] **Perbaiki `HandleInertiaRequests` (Bagian 2d) — kerjakan lebih dulu, ini blocker.** Ini satu-satunya berkas milik alur tenant yang tersentuh di Tahap A, jadi butuh regresi: pastikan test RBAC/owner yang ada tetap hijau.
- [ ] `platform_user_modules` + `config/platform-rbac.php`
- [ ] Middleware `platform.can:{module}`
- [ ] `PlatformLayout.vue` + rute `/platform`
- [ ] `platform_audit_logs` + pencatatan login
- [ ] Daftar tenant read-only via `PlatformTenantResource` (nama, owner, jumlah user, tanggal daftar)
- [ ] **`PlatformArchTest` & `PlatformIsolationTest` — dikerjakan di tahap ini, bukan belakangan**

### Tahap B — Langganan dasar (jalur normal)
- [ ] `plans`, `subscriptions`, `invoices` + `tenants.status`/`pricing_track`
- [ ] Trial 1 bulan saat registrasi
- [ ] Consent `normal` + halaman persetujuan
- [ ] Pencatatan pembayaran manual + verifikasi
- [ ] Batas seat keras di `StaffController@store` + tutup jalur lain
- [ ] Upgrade provisional dengan bukti transfer

### Tahap C — Jalur subsidi
- [ ] `tenant_monthly_metrics` + `ComputeTenantMonthlyRevenue` + jadwal
- [ ] Consent `subsidized` (termasuk klausul data trial)
- [ ] Alur pengajuan subsidi di akhir trial
- [ ] Tampilan omset di platform console (digerbang `revenue_data` + audit log)

### Tahap D — Aturan harga dinamis
- [ ] `pricing_rules` + CRUD di platform console
- [ ] `price_locked` + grandfathering
- [ ] Audit log untuk tiap perubahan tarif

### Aturan emas
- Kerjakan **Tahap A sampai selesai berikut testnya** sebelum menyentuh Tahap B. Arch test dan isolation test adalah fondasi yang menjaga semua tahap berikutnya — dibuat belakangan artinya tidak pernah dibuat.
- `vendor/bin/pint --dirty --format agent` setelah tiap edit PHP.
- Setiap bagian ditutup dengan test yang hijau sebelum lanjut.
