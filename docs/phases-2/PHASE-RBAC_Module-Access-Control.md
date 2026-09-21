# PHASE RBAC — Kontrol Akses Modul untuk Staf Non-Owner

**Status:** Rencana — belum dikerjakan
**Estimasi:** 1 fase mandiri (bisa dikerjakan bertahap per bagian)
**Dependency:** `laravel/sanctum` (sudah ada), pola `TenantScope` + middleware `tenant`/`role` (sudah ada). Menambah **dependency baru** `spatie/laravel-permission` (disetujui owner).
**Output:** Owner bisa membuat **role per-tenant** (mis. "Kasir", "Kasir + Gudang", "Supervisor") berisi kumpulan **modul/menu**, lalu membuat akun staf dan mengikatkannya ke role. Staf non-owner hanya melihat & mengakses modul yang diberikan.
**Dipakai oleh:** Owner (kelola staf + role), staf non-owner (akses modul terbatas).

> Tujuan: memenuhi kebutuhan "Kasir 1 hanya POS; Kasir 2 POS + Inventori". Yang dikontrol adalah **modul/menu mana yang boleh dibuka** oleh staf non-owner. Owner selalu punya akses penuh.

---

## Bagian 0 — Keputusan Desain (hasil diskusi)

| Keputusan | Pilihan | Alasan |
|---|---|---|
| **Model akses** | RBAC berbasis modul (1 permission = 1 modul/menu) | Cocok dengan kebutuhan "buka menu tertentu". Bukan ABAC penuh — tak perlu evaluasi atribut per-request. |
| **Implementasi** | `spatie/laravel-permission` (fitur **teams** untuk scoping per-tenant) | Teruji, lengkap (role/permission/assign). Fitur *teams* memetakan `team_id → tenant_id` sehingga role terisolasi per tenant. |
| **Cakupan role** | Role **custom per-tenant** | Tiap usaha bikin role sendiri di atas beberapa role default yang di-seed. |
| **Migrasi enum** | **Additive** — enum `role` (owner/cashier) tetap ada sebagai gerbang kasar | `owner` = akses penuh (bypass), `cashier` = staf yang diatur permission. ~70 file yang pakai `role` tak perlu diubah serentak; test lama tetap hijau. |

### Prinsip WAJIB
- **`owner` = super-admin dalam tenant.** Owner **bypass** semua cek permission (`Gate::before`). Tak perlu assign permission ke owner.
- **`cashier` = staf.** Akses modul di luar POS ditentukan oleh role/permission yang diberikan owner.
- **Satu sumber kebenaran:** gerbang route (middleware `permission:`) dan gerbang nav (menu bersyarat) memanggil data permission yang sama.
- **Isolasi tenant:** role tenant A tak pernah terlihat/terpakai tenant B. Team-id spatie **wajib** di-set setelah auth.
- **Pint** setelah edit PHP; tiap perubahan **diuji** (Pest).

---

## Bagian 1 — Temuan Kode (peta jalan saat ini)

| Hal | Kondisi sekarang | Implikasi |
|---|---|---|
| Kolom `role` | `enum('role', ['owner','cashier'])` di `users`, dipakai ±70 file (`role:owner`, `isOwner()`, dst.) | Dipertahankan sebagai gerbang kasar (additive). |
| Middleware | `tenant` (`EnsureTenant`), `role` (`EnsureRole`), `tenant.api` | Tambah 1 langkah "set team-id spatie" & satu gerbang permission. |
| Route modul | Semua modul "owner" ada di grup tunggal `Route::middleware(['auth','tenant','role:owner'])->prefix('owner')` | **Harus dipecah** agar tiap modul bisa digerbang permission masing-masing. |
| Route kasir | `role:cashier,owner` → `prefix('cashier')` (POS, transaksi, kas) | Tetap; POS adalah modul dasar kasir. |
| Nav owner | `OwnerLayout.vue` → `sidebarGroups` (array statis) | Difilter berdasarkan permission agar staf lihat menu terbatas. |
| Nav kasir | `CashierTopbar.vue` → menu statis (Riwayat, Kas) | Perlu jalur agar staf sampai ke modul tambahan (lihat Bagian 6 & Keputusan A). |
| Inertia share | `HandleInertiaRequests@share` kirim `auth.user` (id, name, email, role, tenant_id) | Tambah `auth.user.permissions` (daftar modul). |
| Register | Membuat Tenant + User `role=owner` | Tak berubah. Akun **staf** dibuat owner via UI baru (Bagian 5). |
| Mobile API | `MobileAuthController` balas `user.role` | Opsional: sertakan `permissions` bila app mobile perlu (Keputusan C). |

---

## Bagian 2 — Fondasi spatie (teams = per-tenant)

### 2a. Install & publish
```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

### 2b. Aktifkan teams + petakan ke tenant
`config/permission.php`:
```php
'teams' => true,
'team_foreign_key' => 'tenant_id',   // kolom scoping = tenant_id (bukan default team_id)
```
> Dengan `teams=true`, tabel `roles`, `model_has_roles`, `model_has_permissions` mendapat kolom `tenant_id`. **Permission bersifat global** (katalog modul, di-seed sekali); **role bersifat per-tenant**. Persis kebutuhan kita.

### 2c. Migration
Jalankan migration bawaan spatie (sudah menyertakan kolom team bila `teams=true` di config saat migrate dibuat). Verifikasi kolom `tenant_id` muncul di `roles` & pivot. Bila belum, sesuaikan file migration publish sebelum `migrate`.
```bash
php artisan migrate
```

### 2d. Trait di model User
`app/Models/User.php`:
```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    // ...
}
```

> **Catatan cache:** spatie meng-cache permission. Di test pakai `RefreshDatabase` + reset cache bila perlu (`app(PermissionRegistrar::class)->forgetCachedPermissions()`), dan **selalu set team-id** sebelum assign/cek (Bagian 3 & 7).

---

## Bagian 3 — Set Team-Id (isolasi tenant) + Owner bypass

### 3a. Set team-id spatie dari user login
Karena team_id spatie = `tenant_id`, ia harus di-set tiap request **setelah** user terautentikasi. Perluas `EnsureTenant` (sudah jalan di semua route ber-`tenant`) agar sekalian set konteks tim:

```php
// app/Http/Middleware/EnsureTenant.php — di dalam handle(), setelah cek tenant_id
app(\Spatie\Permission\PermissionRegistrar::class)
    ->setPermissionsTeamId(auth()->user()->tenant_id);
```
> Ditaruh di sini karena `EnsureTenant` sudah menjamin `auth()->check()` & `tenant_id` ada. Semua route modul memakai `tenant`, jadi konteks tim selalu benar. Untuk API (`EnsureTenantApi`) lakukan hal serupa.

### 3b. Owner bypass semua permission
`app/Providers/AppServiceProvider@boot` (atau `AuthServiceProvider`):
```php
use Illuminate\Support\Facades\Gate;

Gate::before(function ($user, $ability) {
    return $user->isOwner() ? true : null; // null = lanjut cek normal
});
```
> Efek: `->can('stock')`, middleware `permission:stock`, dan `@can` di Vue semuanya lolos untuk owner tanpa perlu assign apa pun.

---

## Bagian 4 — Katalog Modul (permission global) + Seeder

Satu permission = "boleh membuka modul ini". Nama dibuat stabil & dipetakan ke route + nav.

| Permission (nama) | Modul / Menu | Route utama | Grantable ke staf? |
|---|---|---|---|
| `pos` | Kasir (POS) | `cashier.pos`, `cashier.transactions.*` | Ya (default kasir) |
| `cash_drawer` | Sesi Kas | `cashier.cash-drawer.*` | Ya |
| `products` | Produk | `owner.products.*`, `owner.categories.*`, `owner.modifiers.*` | Ya |
| `stock` | Stok / Inventori | `owner.stock.*` | Ya |
| `reports` | Laporan & Riwayat | `owner.reports.*`, `owner.transactions.*`, `owner.cash-drawers.*` | Ya |
| `payment_methods` | Metode Pembayaran | `owner.payment-methods.*` | Ya, tapi **default tidak dicentang** (Keputusan B) |
| `ai_analysis` | AI Analysis | `owner.ai-analysis.*` | Ya, tapi **default tidak dicentang** (Keputusan B) |

**Owner-eksklusif (TIDAK jadi permission grantable; tetap `role:owner`):**
- `owner.settings.*` (profil usaha, token MCP, AI key) — sensitif.
- **Manajemen Staf & Role** (Bagian 5) — hanya owner.

Seeder katalog (idempotent, dijalankan sekali; permission global tanpa tenant):
```php
// database/seeders/PermissionCatalogSeeder.php
use Spatie\Permission\Models\Permission;

foreach (['pos','cash_drawer','products','stock','reports','payment_methods','ai_analysis'] as $name) {
    Permission::findOrCreate($name, 'web');
}
```
> Daftarkan di `DatabaseSeeder`. Untuk permission global set team-id ke `null` sebelum `findOrCreate` (`setPermissionsTeamId(null)`), lalu kembalikan konteks.

---

## Bagian 5 — Route Gating (pecah grup owner)

Ubah **hanya modul yang grantable** dari `role:owner` menjadi `permission:<modul>`. Owner tetap lolos via `Gate::before`. Modul owner-eksklusif tetap `role:owner`.

Pola sasaran (`routes/web.php`):
```php
// Modul yang boleh diberikan ke staf → gerbang per-permission (owner auto-lolos)
Route::middleware(['auth','tenant'])->prefix('owner')->name('owner.')->group(function () {

    Route::middleware('permission:products')->group(function () {
        Route::resource('categories', CategoryController::class)->only(['index','store','update','destroy']);
        Route::resource('products', ProductController::class);
        // variants + modifiers ...
    });

    Route::middleware('permission:stock')->group(function () {
        Route::get('stock', [StockController::class,'index'])->name('stock.index');
        // restock/adjust/history/movements ...
    });

    Route::middleware('permission:reports')->group(function () {
        Route::get('reports/daily', ...)->name('reports.daily');
        Route::get('transactions', ...)->name('transactions.index');
        Route::get('cash-drawers', ...)->name('cash-drawers.index');
    });

    Route::middleware('permission:payment_methods')->group(function () { /* payment-methods */ });
    Route::middleware('permission:ai_analysis')->group(function () { /* ai-analysis */ });
});

// Modul owner-eksklusif → tetap role:owner
Route::middleware(['auth','tenant','role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', ...)->name('dashboard');   // beranda owner
    Route::get('settings', ...); Route::patch('settings', ...); // + mcp-token
    // Manajemen Staf & Role (Bagian 5b)
});
```
> `permission:` adalah middleware bawaan spatie (`Spatie\Permission\Middleware\PermissionMiddleware`) — daftarkan aliasnya di `bootstrap/app.php`:
> ```php
> 'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
> 'role.spatie' => \Spatie\Permission\Middleware\RoleMiddleware::class, // opsional, hindari bentrok alias 'role' yang sudah ada
> ```
> **Penting:** alias `role` kita **sudah dipakai** `EnsureRole`. Jangan ditimpa. Pakai `permission:` untuk gating modul.

### 5b. UI Manajemen Staf & Role (owner-only)
Controller & route baru di grup `role:owner`:
```
GET    owner/staff                → daftar staf (users non-owner tenant ini)
POST   owner/staff                → buat akun staf (role=cashier) + assign role spatie
PATCH  owner/staff/{user}         → ubah nama/role/status
DELETE owner/staff/{user}         → hapus/nonaktifkan staf
GET    owner/roles                → daftar role tenant + permission tercakup
POST   owner/roles                → buat role (nama + pilih modul)
PATCH  owner/roles/{role}         → ubah modul role
DELETE owner/roles/{role}         → hapus role (blokir bila masih dipakai)
```
Controller (contoh inti):
```php
// StaffController@store
$user = User::create([
    'tenant_id' => $request->user()->tenant_id,
    'name' => $validated['name'],
    'email' => $validated['email'],
    'password' => $validated['password'], // cast 'hashed'
    'role' => 'cashier',
]);
$user->syncRoles([$validated['role_name']]); // team-id sudah di-set middleware

// RoleController@store
$role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']); // tenant_id auto dari team-id
$role->syncPermissions($validated['modules']); // subset katalog Bagian 4
```
UI Vue: `Owner/Staff/Index.vue` (tabel + modal create/edit), `Owner/Roles/Index.vue` (tabel + modal dengan `Checkbox.vue` per modul). Reuse komponen `Modal.vue`, `Checkbox.vue`, `Button.vue`, `ConfirmDialog.vue` yang sudah ada.

> **Default centang modul (Keputusan B):** saat modal buat role dibuka, checkbox `payment_methods` & `ai_analysis` **tidak tercentang** secara default (owner harus sengaja mencentangnya). Modul lain boleh mengikuti default yang wajar; `settings` & manajemen staf/role **tidak muncul** sebagai opsi (owner-eksklusif).

---

## Bagian 6 — Navigation Gating (menu bersyarat)

### 6a. Bagikan permission ke frontend
`HandleInertiaRequests@share`:
```php
'auth' => [
    'user' => $request->user() ? [
        // ... field lama ...
        'permissions' => $request->user()->isOwner()
            ? ['*']                                   // owner: semua
            : $request->user()->getPermissionNames(), // staf: daftar modul
    ] : null,
],
```

### 6b. Sidebar difilter
`OwnerLayout.vue` — tambahkan `permission` per item lalu filter:
```js
const can = (p) => auth.user?.permissions?.includes('*') || auth.user?.permissions?.includes(p);
// tandai tiap item nav dengan { ..., perm: 'stock' } lalu:
const visibleGroups = computed(() =>
  sidebarGroups
    .map(g => ({ ...g, items: g.items.filter(i => !i.perm || can(i.perm)) }))
    .filter(g => g.items.length)
);
```
Render `visibleGroups`. Owner tak berubah (semua lolos). Staf hanya lihat modul miliknya.

### 6c. Jalur staf ke modul tambahan — **Keputusan A → DIPUTUSKAN: opsi 1**
Saat ini staf memakai `CashierTopbar` (tak punya sidebar). **Keputusan final (2026-07-21): opsi 1.**
1. **✅ DIPILIH.** Staf non-owner yang punya ≥1 permission modul memakai **shell sidebar yang sama** (OwnerLayout difilter). POS tetap bisa dibuka via item nav "Kasir". Konsisten, satu komponen nav.
2. ~~Tambahkan link modul terpilih ke `CashierTopbar` (menu ringkas). Lebih kecil perubahannya, tapi dua sumber nav untuk dijaga.~~ — tidak dipakai.

> **Implikasi opsi 1:** staf yang **hanya** punya `pos`/`cash_drawer` (tanpa modul owner) tetap memakai `CashierTopbar` seperti sekarang — tak perlu sidebar. Begitu staf punya ≥1 modul owner (mis. `stock`), ia dialihkan ke shell `OwnerLayout` yang difilter. Ambang pemicunya: "punya minimal satu permission di luar `pos`/`cash_drawer`".

Halaman **beranda** untuk staf: dashboard owner adalah `role:owner`. Untuk staf, landing default tetap `cashier.pos`. Item "Beranda"/dashboard hanya render untuk owner.

---

## Bagian 7 — Backward-Compat, Seeder Data Lama, Factory

- **Cashier lama** (sebelum fitur): tanpa role spatie = hanya bisa POS/kas (tak punya permission modul owner). Aman — default paling ketat. Bila ingin, seed role "Kasir" (permission `pos`,`cash_drawer`) dan assign ke semua cashier eksisting via migration data.
- **Owner lama:** tak perlu apa-apa (bypass).
- **Factory/seeder:** saat membuat user di test yang butuh permission, set team-id dulu:
  ```php
  app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
  $role = Role::create(['name' => 'Kasir Gudang']);
  $role->givePermissionTo(['pos','stock']);
  $user->assignRole($role);
  ```
- **`CafeStudyCaseSeeder` / `ProductionSeeder`:** tambah contoh 1–2 role per tenant demo agar UI ada isinya.

---

## Bagian 8 — Keputusan Terbuka

**A. Shell nav untuk staf multi-modul** (Bagian 6c). ✅ **DIPUTUSKAN (2026-07-21): opsi 1** — sidebar difilter dipakai bersama. Staf hanya-POS tetap di `CashierTopbar`; staf dengan ≥1 modul owner naik ke shell `OwnerLayout` yang difilter.

**B. Modul sensitif sebagai permission grantable?** `payment_methods` & `ai_analysis`. ✅ **DIPUTUSKAN (2026-07-21): grantable tetapi TIDAK dicentang secara default** saat owner membuat/mengedit role. `settings` tetap owner-eksklusif.

**C. Mobile API** — sertakan `permissions` di respons `MobileAuthController` & gerbang endpoint mobile per modul? ✅ **DIPUTUSKAN (2026-07-21): tetap dibuat, tetapi DITUNDA (backlog) sampai fitur utama RBAC (Bagian 2–9) selesai penuh.** Saat dikerjakan, pakai `permission:` versi API (JSON 403) seperti pola `feature.api` di `PHASE-FEATURE-FLAGS`, dan sertakan array `permissions` di payload auth mobile. Di-track sebagai `[BL-003]` di `docs/BACKLOG.md`.

**D. Granularitas** — v1 = per-modul (buka/tidak). Aksi halus (lihat vs ubah vs hapus) ditunda. Bila nanti perlu, tambah permission `stock.manage` dst. tanpa membongkar struktur.

**E. Larangan hapus role terpakai** — blokir `DELETE role` bila masih ada user memakainya (tampilkan jumlah pemakai). Rekomendasi: blokir + minta reassign.

---

## Bagian 9 — Tests (Pest)

**File:** `tests/Feature/Authorization/ModuleAccessTest.php`

- **Owner bypass:** owner tanpa role spatie tetap bisa `GET owner/stock` → 200.
- **Staf tanpa permission:** cashier polos `GET owner/stock` → 403; `GET cashier/pos` → 200.
- **Staf dengan permission:** cashier di-assign role `pos,stock` → `owner/stock` 200, `owner/products` 403.
- **Isolasi tenant:** role tenant A tak terbawa ke user tenant B; user B dengan nama role sama tak dapat akses A. Verifikasi team-id men-scope.
- **Nav share:** response Inertia untuk staf hanya memuat `permissions` miliknya; owner `['*']`.
- **Staff CRUD:** owner buat staf + assign role → user tercipta `role=cashier` dengan role spatie benar; non-owner **tak bisa** akses `owner/staff` (403).
- **Role CRUD:** owner buat role dengan subset modul valid; modul di luar katalog ditolak; hapus role terpakai diblokir.
- **Regresi:** seluruh `AuthTest` lama tetap hijau (enum owner/cashier utuh).

Jalankan: `php artisan test --compact --filter="ModuleAccess|Auth"`
> Di `Pest.php`/`TestCase`: pastikan set/reset team-id & `forgetCachedPermissions()` pada `beforeEach` agar tak ada kebocoran cache antar test.

---

## Bagian 10 — Checklist Eksekusi

- [x] `composer require spatie/laravel-permission` + publish config & migration (v8.3)
- [x] `config/permission.php`: `teams=true`, `team_foreign_key='tenant_id'`; kolom `tenant_id` terverifikasi di `roles` & pivot
- [x] `migrate`; trait `HasRoles` di `User`
- [x] Set team-id di `EnsureTenant` (+ `EnsureTenantApi`) setelah cek tenant
- [x] `Gate::before` owner bypass
- [x] `PermissionCatalogSeeder` (7 modul, global) + daftar di `DatabaseSeeder`
- [x] Alias middleware `permission` di `bootstrap/app.php` (alias `role` lama tak diubah)
- [x] Pecah grup route owner: modul grantable → `permission:<modul>`; sensitif/eksklusif → tetap `role:owner`
- [x] Controller + route + Vue: `Owner/Staff` (CRUD staf + assign role) & `Owner/Roles` (CRUD role + pilih modul)
- [x] `HandleInertiaRequests`: share `auth.user.permissions` (lazy closure via `can()` per modul)
- [x] `OwnerLayout` sidebar difilter `can()` + grup "Tim & Akses"; staf hanya-POS tetap di `CashierTopbar` dengan jalur "Kelola Toko" (Keputusan A = opsi 1)
- [x] UI role: `payment_methods` & `ai_analysis` ditandai "Sensitif" & default tidak dicentang (Keputusan B)
- [ ] (Backlog `[BL-003]`) Mobile API `permissions` + gating endpoint — dikerjakan **setelah** RBAC web selesai
- [x] Role contoh di seeder demo (`DatabaseSeeder`); assign role "Kasir + Gudang" ke cashier demo
- [x] Tests `ModuleAccessTest` (11) hijau + suite penuh hijau (217 passed)
- [x] `vendor/bin/pint --dirty --format agent` bersih
- [x] Entri `docs/CHANGELOG.md` ditambahkan

> **Catatan implementasi:** share permission dihitung via `can()` per modul (jalur registrar spatie), bukan `getPermissionNames()` relasi Eloquent — yang mengembalikan kosong bila dipanggil dari middleware Inertia karena kepekaan konteks team. Semua enum `role` lama tetap utuh (additive).

### Aturan emas
Menambah **modul baru** = tambah 1 permission di katalog (Bagian 4) + 1 gerbang `permission:` di route + 1 item nav ber-`perm`. Selama gerbang route & filter nav memakai nama permission yang sama, akses & tampilan selalu sinkron. Owner tak pernah perlu disentuh (bypass by design).
