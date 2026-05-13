# SAPI Smart POS Inventory — Security & Bug Audit Report v1.0

**Tanggal audit:** 2026-05-13  
**Auditor:** Claude Code (Anthropic)  
**Status project:** Staging / soon-to-production  
**Stack:** Laravel 12, Vue 3, Inertia.js, Laravel Sanctum, Xendit, MySQL  

---

## Executive Summary

Audit ini mencakup seluruh modul SAPI: auth, authorization, API/Sanctum, POS checkout, self-order, inventory, Xendit payment, multi-tenant, frontend, CI/CD, dependencies, dan konfigurasi. Audit dilakukan secara statis (baca source code, config, migrations, docs) sekaligus dinamis (composer audit, npm audit, php artisan route:list, php artisan config:show, php artisan test, verifikasi manual controller logic).

**Hasil keseluruhan:** Test suite 94 ✅ (163 assertions) — logika bisnis inti (money math, stok, tenant isolation) sudah solid. Namun **ditemukan 4 item Critical yang wajib dibereskan sebelum go-live**, terutama satu bug di deploy workflow yang bisa menghapus seluruh database production setiap kali push ke main.

| Severity | Jumlah | Go-live blocker |
|---|---|---|
| 🔴 CRITICAL | 4 | Ya |
| 🟠 HIGH | 5 | Sangat dianjurkan |
| 🟡 MEDIUM | 5 | Jadwalkan segera post-launch |
| 🟢 LOW | 6 | Nice-to-have / hardening |

---

## Methodology

| Tahap | Aktivitas |
|---|---|
| Static code review | Routes, controllers, services, models, migrations, seeders, config, deploy workflow, docs |
| Dependency scan | `composer audit`, `npm audit --omit=dev` |
| Test suite | `php artisan test` — 94 tests, 163 assertions |
| Runtime inspection | `php artisan route:list`, `php artisan config:show session`, vendor source read (FilesystemServiceProvider, ReceiveFile) |
| Manual logic trace | `TransactionService::processItems()` → konfirmasi harga dari DB, bukan client |
| Git history check | `git log --all -- .env` → `.env` tidak pernah ter-commit |

---

## Findings

---

### 🔴 CRITICAL — Wajib fix sebelum go-live

---

#### OPS-001 · Deploy workflow menghapus seluruh database setiap push ke main

**File:** `.github/workflows/deploy.yml:38`  
**Go-live blocker:** Ya

```yaml
# line 36-37 (komentar di file itu sendiri!):
# CATATAN: Gunakan migrate --force untuk produksi,
# HINDARI migrate:fresh --seed di production agar data tidak hilang!
# line 38 (yang dieksekusi):
php artisan migrate:fresh --force --seed   # ← CATASTROPHIC
```

**Dampak:** `migrate:fresh` men-drop lalu re-create **seluruh tabel database**. Setiap push ke branch `main` — termasuk hotfix, update minor, atau typo fix — akan menghapus semua data transaksi, user, produk, dan riwayat stok production. Ironisnya, file ini sendiri punya komentar yang memperingatkan agar tidak melakukan ini.

**Rekomendasi fix:**
```yaml
# Ganti baris 38 dengan:
php artisan migrate --force
# Hapus --seed dari production (seed hanya untuk fresh install)
```

---

#### OPS-002 · Konfigurasi .env tidak aman untuk production

**File:** `.env` (local) — harus dibereskan sebelum disalin/dikonfigurasi di server  
**Go-live blocker:** Ya

| Setting | Nilai saat ini | Nilai aman untuk production |
|---|---|---|
| `APP_DEBUG` (line 4) | `true` | `false` |
| `LOG_LEVEL` (line 21) | `debug` | `warning` atau `error` |
| `SESSION_ENCRYPT` (line 32) | `false` | `true` |
| `XENDIT_WEBHOOK_TOKEN` (line 44) | `isi_bebas_string_random_untuk_verifikasi` | String random 64+ karakter |
| `XENDIT_SECRET_KEY` (line 43) | `xnd_development_...` | Key production dari Xendit dashboard |

**Dampak `APP_DEBUG=true`:** Jika ada error di production, Laravel akan menampilkan full stack trace lengkap dengan nilai variabel, path file, dan environment ke browser pengguna/attacker.  
**Dampak `XENDIT_WEBHOOK_TOKEN` placeholder:** Jika token mudah ditebak atau bocor dari log, attacker bisa mengirim fake webhook `PAID` → sistem menganggap transaksi lunas → stok dikurangi tanpa pembayaran nyata.  
**Catatan:** `.env` tidak pernah ter-commit ke git (diverifikasi via `git log`), jadi tidak ada kebocoran historis.

**Rekomendasi fix:** Buat `.env.production.example` terpisah dengan semua nilai aman sebagai template wajib checklist sebelum deploy.

---

#### SEC-001 · Sanctum token tidak pernah kedaluwarsa

**File:** [`config/sanctum.php:50`](../config/sanctum.php)  
**Go-live blocker:** Ya

```php
'expiration' => null,  // token hidup selamanya
```

**Dampak:** Token API yang diissue untuk n8n/self-order tidak pernah expire. Jika token bocor (misalnya via log, environment leak, atau kompromi server n8n), attacker memiliki akses permanen untuk membuat order atas nama tenant tersebut — tanpa batasan waktu.

**Rekomendasi fix:**
```php
// config/sanctum.php:50
'expiration' => env('SANCTUM_TOKEN_EXPIRY', 525600), // 365 hari default
```
Untuk token n8n yang dipakai 24/7, expiry 365 hari sudah aman. Tambahkan rotation policy: buat token baru setiap 30-90 hari di dashboard.

---

#### SEC-002 · Default admin credential hardcoded di ProductionSeeder

**File:** [`database/seeders/ProductionSeeder.php:25`](../database/seeders/ProductionSeeder.php)  
**Go-live blocker:** Ya

```php
User::firstOrCreate(
    ['email' => 'admin@sapi.com'],
    [
        'password' => Hash::make('change-me-immediately'),  // ← hardcoded, di repo
        'role' => 'owner',
    ]
);
```

**Dampak:** Email `admin@sapi.com` dan password `change-me-immediately` tertulis di repository. Jika deploy seeder dijalankan tanpa mengganti password (kemungkinan besar, apalagi dikejar waktu go-live), akun owner pertama bisa diakses siapa saja yang tahu repo ini. Credential ini tidak berubah di-redeploy karena memakai `firstOrCreate`.

**Rekomendasi fix:**
```php
// Opsi 1: Generate random password dan print ke console, paksa ganti saat login pertama
$password = Str::random(16);
$this->command->info("Password admin awal: {$password} — ganti segera setelah login!");
User::firstOrCreate(
    ['email' => env('ADMIN_EMAIL', 'admin@sapi.com')],
    ['password' => Hash::make($password), ...]
);

// Opsi 2: Ambil dari env
'password' => Hash::make(env('ADMIN_INITIAL_PASSWORD') ?? Str::random(16)),
```

---

### 🟠 HIGH — Sangat dianjurkan sebelum go-live

---

#### SEC-003 · Xendit webhook hanya verifikasi token header, tanpa HMAC

**File:** [`app/Http/Controllers/Api/XenditWebhookController.php:28-31`](../app/Http/Controllers/Api/XenditWebhookController.php)

```php
$callbackToken = $request->header('x-callback-token');
if ($callbackToken !== config('services.xendit.webhook_token')) {
    return response()->json(['message' => 'Unauthorized'], 403);
}
```

**Dampak:** Jika `XENDIT_WEBHOOK_TOKEN` bocor (via log, env dump, atau misconfigured monitoring), attacker bisa mengirim fake webhook `PAID` untuk transaksi manapun → sistem menganggap pembayaran berhasil → stok dikurangi + order ditandai completed tanpa ada uang yang berpindah. Xendit mendukung verifikasi tambahan via IP allowlist dan dapat menggunakan header signature.

**Rekomendasi fix:**
1. **Segera:** Ganti `XENDIT_WEBHOOK_TOKEN` ke random string 64+ karakter yang unik
2. **Di Xendit Dashboard:** Aktifkan IP allowlist untuk webhook hanya dari IP Xendit
3. **Opsional hardening:** Verifikasi juga `invoice_id` ke Xendit API sebelum konfirmasi (verifikasi dua arah)

---

#### SEC-005 · Tidak ada rate limiting pada POST /api/orders (self-order)

**File:** [`routes/api.php:20`](../routes/api.php)

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/orders', [ApiOrderController::class, 'store']); // No throttle
});
```

**Dampak:** Siapa saja dengan token Sanctum valid (termasuk token yang bocor atau token n8n yang tidak pernah expire) bisa membuat ribuan invoice Xendit dalam hitungan menit. Xendit mengenakan biaya per invoice yang dibuat, dan setiap invoice yang expired juga perlu di-void. Ini bisa menyebabkan kerugian finansial dan banjir notifikasi.

**Rekomendasi fix:**
```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/orders', [ApiOrderController::class, 'store']);
    // ...
});
```
Atau gunakan throttle lebih ketat sesuai flow bisnis (misalnya 10 order per menit per token).

---

#### SEC-009 · Session cookie tidak di-harden untuk HTTPS production

**File:** [`config/session.php`](../config/session.php) dan `.env`

```
SESSION_ENCRYPT=false   # Data session tidak terenkripsi
secure => null          # Cookie dikirim via HTTP/HTTPS (tidak dipaksakan HTTPS)
```

**Dampak:** Di production dengan HTTPS, `secure: null` akan otomatis menggunakan HTTPS (acceptable). Namun jika ada aksidental HTTP traffic atau load balancer yang terminasi SSL, cookie session bisa terkirim via plain HTTP → session hijacking. Session yang tidak terenkripsi menyimpan `tenant_id` dan role dalam readable format di database sessions.

**Rekomendasi fix:**
```bash
# .env production
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

---

#### BUG-002 · Jalur kritis webhook PAID tidak ter-cover test

**File:** `tests/` (missing), [`app/Services/TransactionService.php:145-203`](../app/Services/TransactionService.php)

`confirmSelfOrderPayment()` adalah fungsi yang paling sensitif secara finansial: ia deduct stok dan menandai transaksi lunas. Fungsi ini dipanggil **hanya** oleh Xendit webhook, dan tidak ada satu pun test yang mengeksekusi path ini.

**Coverage gap yang teridentifikasi:**
- `confirmSelfOrderPayment()` webhook PAID → stok deducted ✗
- `voidExpiredSelfOrder()` webhook EXPIRED → status voided ✗  
- Replay webhook sama 2x → idempotency check (status check di line 74) ✗
- Webhook dengan `external_id` tidak dikenal → graceful 200 ✗

**Dampak:** Tanpa test, ada risiko regresi tersembunyi di jalur pembayaran.

**Rekomendasi:** Tambahkan `tests/Feature/Api/XenditWebhookTest.php` dengan minimal 4 cases di atas.

---

#### DEP-001 · npm dependencies dengan CVE High

**Command:** `npm audit --omit=dev`

| Package | Severity | CVE / Issue |
|---|---|---|
| `axios ^1.11.0` | High | SSRF (NO_PROXY bypass), prototype pollution, header injection, CRLF injection (15 issues) |
| `vite ^7.0.7` | High | Path traversal di dev server, arbitrary file read via WebSocket, `server.fs.deny` bypass |
| `lodash-es` | High | Code injection via `_.template`, prototype pollution |
| `postcss` | Moderate | XSS via unescaped `</style>` |
| `follow-redirects` | Moderate | Auth header leak ke cross-domain redirect |

**Konteks:**
- **Axios:** Dipakai browser-side. SSRF tidak relevan (tidak ada request server-side via axios). Prototype pollution theoretical — perlu review apakah ada user input yang masuk ke axios config.
- **Vite:** CVE pada dev server — **TIDAK relevan di production** (Vite tidak jalan di production, hanya build artifacts yang dipakai). Tetap update karena dev environment juga berisiko.
- **lodash-es:** Cek apakah `_.template` dipakai dengan user input.

**Rekomendasi:**
```bash
npm audit fix
# Review breaking changes jika ada
```

---

### 🟡 MEDIUM — Jadwalkan segera post-launch

---

#### BUG-003 · Idempotency webhook belum ditest (meski sudah terimplementasi)

**File:** [`app/Http/Controllers/Api/XenditWebhookController.php:74`](../app/Http/Controllers/Api/XenditWebhookController.php)

```php
private function handlePaid(Transaction $transaction, string $invoiceId): void
{
    if ($transaction->status !== Transaction::STATUS_PENDING) {
        return; // ← idempotency guard
    }
    // ...
}
```

Implementasi idempotency sudah ada dan benar. Xendit kadang mengirim webhook dua kali. Guard ini mencegah double-processing, tapi tidak ada test yang memverifikasi perilaku ini. Risiko: perubahan kode di masa depan bisa menghapus guard ini tanpa terdeteksi.

**Rekomendasi:** Tambahkan test case: kirim webhook PAID dua kali → verifikasi stok hanya terkurangi sekali.

---

#### BUG-004 · Tidak ada manual override jika webhook Xendit gagal

**File:** [`app/Http/Controllers/Api/XenditWebhookController.php`](../app/Http/Controllers/Api/XenditWebhookController.php)

Jika webhook Xendit gagal terkirim (network timeout, server restart, Xendit maintenance), transaksi self-order akan tertinggal dalam status `PENDING` selamanya. Tidak ada:
- Endpoint admin untuk manually confirm payment
- Scheduled job untuk query ulang status invoice ke Xendit API
- UI untuk owner melihat daftar transaksi pending self-order

**Dampak operasional:** Pelanggan sudah bayar tapi order tidak diproses. Butuh intervensi manual langsung ke database.

**Rekomendasi:** Tambahkan endpoint owner-only `POST /owner/transactions/{transaction}/confirm-payment` yang memverifikasi ke Xendit API lalu jalankan `confirmSelfOrderPayment()`.

---

#### OPS-003 · Missing database index pada kolom yang sering di-query

**File:** `database/migrations/`

| Kolom | Query | Status index | Dampak |
|---|---|---|---|
| `transactions.code` | `WHERE code = ?` (webhook lookup line 43) | Composite `(tenant_id, code)` — kurang optimal untuk single-column lookup | Webhook query tidak bisa menggunakan composite index secara optimal |
| `stock_movements.product_variant_id` | Riwayat mutasi per produk | Tidak ada index | Full table scan saat laporan stok |
| `transactions.(tenant_id, status)` | Dashboard: count per status | Tidak ada composite index | Lambat saat data bertambah |

**Catatan:** `transactions.code` punya composite unique `(tenant_id, code)`. MySQL bisa menggunakan ini untuk `WHERE code = ?` dengan index scan, tapi tidak seefisien index tunggal pada `code`. Untuk volume tinggi, tambahkan standalone index.

**Rekomendasi:** Buat migration baru:
```php
$table->index('code'); // transactions
$table->index('product_variant_id'); // stock_movements
$table->index(['tenant_id', 'status']); // transactions
```

---

#### OPS-004 · FK transactions.user_id tanpa cascade

**File:** [`database/migrations/2026_03_06_000011_create_transactions_table.php:14`](../database/migrations/2026_03_06_000011_create_transactions_table.php)

```php
$table->foreignId('user_id')->constrained('users'); // no cascade
```

**Dampak:** Jika user (kasir) dihapus dari sistem, semua transaksi yang dibuat oleh kasir tersebut akan gagal di-query karena FK constraint (atau jadi orphan jika constraint tidak aktif). Riwayat transaksi jadi tidak valid untuk laporan.

**Rekomendasi:** Tambahkan `->nullOnDelete()` agar `user_id` menjadi `NULL` jika user dihapus, sehingga transaksi historis tetap ada. Atau gunakan soft-delete untuk user.

---

#### DEP-002 · PHP dependency dengan CVE Medium

**Command:** `composer audit`

| Package | CVE | Deskripsi |
|---|---|---|
| `league/commonmark 2.x` | CVE-2026-33347 | Embed extension allowed_domains bypass |
| `league/commonmark 2.x` | CVE-2026-30838 | DisallowedRawHtml bypass via whitespace |

**Konteks:** `league/commonmark` adalah dependency transitif dari Laravel. CVE ini hanya relevan jika fitur `embed` atau `DisallowedRawHtml` extension CommonMark digunakan untuk merender konten dari user. Pada SAPI, CommonMark tidak terlihat dipakai langsung.

**Rekomendasi:**
```bash
composer update league/commonmark
```

---

### 🟢 LOW / Nice-to-have

---

#### SEC-006 · v-html pada komponen pagination (risiko XSS rendah)

**File:** 5 Vue components menggunakan `v-html="link.label"`:
- [`resources/js/Pages/Cashier/TransactionHistory.vue:196,198`](../resources/js/Pages/Cashier/TransactionHistory.vue)
- [`resources/js/Pages/Owner/CashDrawers/Index.vue:105`](../resources/js/Pages/Owner/CashDrawers/Index.vue)
- [`resources/js/Pages/Owner/Stock/History.vue:129`](../resources/js/Pages/Owner/Stock/History.vue)
- [`resources/js/Pages/Owner/Stock/Movements.vue:235`](../resources/js/Pages/Owner/Stock/Movements.vue)
- [`resources/js/Pages/Owner/Transactions/Index.vue:190`](../resources/js/Pages/Owner/Transactions/Index.vue)

`link.label` berasal dari Laravel paginator yang menghasilkan string seperti `"&laquo; Previous"`, `"1"`, `"Next &raquo;"` — seluruhnya di-generate server-side oleh framework, bukan dari input user. Risiko XSS aktual sangat rendah. Namun praktik terbaik adalah menghindari `v-html` untuk konten apapun.

**Rekomendasi:** Ganti dengan:
```vue
<span>{{ decodeHtmlEntities(link.label) }}</span>
<!-- atau buat helper yang parse "Previous"/"Next"/number -->
```

---

#### SEC-007 · Tidak ada formal Policies/Gates

**File:** `app/` (tidak ada direktori `Policies/`)

Authorization sepenuhnya bergantung pada:
1. Middleware `EnsureRole` di route level
2. Manual `if ($transaction->tenant_id !== $user->tenant_id) abort(403)` di controller

Ini berfungsi, tapi sulit di-audit dan rentan human error saat menambah endpoint baru (developer baru lupa menambahkan check tenant_id).

**Rekomendasi:** Buat `TransactionPolicy`, `ProductPolicy` menggunakan `php artisan make:policy`. Tidak urgent untuk MVP tapi penting untuk maintainability.

---

#### SEC-008 · Tidak ada Content Security Policy (CSP) header

Tidak ada middleware atau config yang menambahkan `Content-Security-Policy` header ke response. CSP membantu mencegah eksekusi script yang tidak diizinkan.

**Rekomendasi:** Tambahkan middleware atau gunakan package `spatie/laravel-csp` dengan policy strict untuk Inertia SPA.

---

#### OPS-005 · Direktori /storage tidak di .gitignore

**File:** `.gitignore`

`/storage` directory tidak ada di `.gitignore`, sehingga file log (`storage/logs/laravel.log`) bisa ter-commit secara tidak sengaja jika developer menjalankan `git add .`.

**Rekomendasi:** Tambahkan ke `.gitignore`:
```
/storage/logs/
/storage/framework/sessions/
/storage/framework/cache/
/storage/app/private/
```
(Pertahankan `storage/app/public/` jika ada file placeholder yang perlu di-track.)

---

#### OPS-006 · Log channel single file tanpa rotasi

**File:** `.env:19-21`

```
LOG_STACK=single
LOG_LEVEL=debug
```

Log single file akan terus bertumbuh tanpa rotasi. Di production dengan traffic tinggi, `laravel.log` bisa mencapai GB dalam beberapa hari.

**Rekomendasi:**
```bash
# .env production
LOG_STACK=daily
LOG_LEVEL=warning
```
`daily` channel otomatis rotasi per hari dan mempertahankan 14 hari terakhir.

---

## Verification Log

| ID | Test | Metode | Hasil |
|---|---|---|---|
| OPS-001 | `migrate:fresh` di deploy.yml | `cat .github/workflows/deploy.yml` | ✅ CONFIRMED — line 38 |
| OPS-002 | `.env` committed ke git? | `git log --all -- .env` | ✅ SAFE — tidak pernah ter-commit |
| OPS-002 | APP_DEBUG, LOG_LEVEL, SESSION_ENCRYPT | `cat .env` | ✅ CONFIRMED — semua di nilai dev |
| SEC-001 | Sanctum expiration | `cat config/sanctum.php:50` | ✅ CONFIRMED — `null` |
| SEC-002 | Hardcoded credential seeder | `cat database/seeders/ProductionSeeder.php:25` | ✅ CONFIRMED |
| SEC-003 | Webhook hanya token header | `cat XenditWebhookController.php:28-31` | ✅ CONFIRMED — tidak ada HMAC |
| SEC-003 | Log payload webhook | `cat XenditWebhookController.php:58-62` | ✅ SAFE — hanya log external_id+status+error, bukan full payload |
| SEC-004 | (Dibatalkan) Log payload raw | Verifikasi manual | ❌ FALSE POSITIVE — log tidak dump payload |
| SEC-005 | Rate limiting di routes | `grep -rn "throttle" routes/ app/Http/` | ✅ CONFIRMED — tidak ada throttle |
| SEC-006 | `v-html` usage | `grep -rn "v-html" resources/js/Pages/` | ✅ CONFIRMED — 5 files, semuanya `link.label` dari paginator |
| SEC-009 | Session cookie secure flag | `php artisan config:show session` | ✅ CONFIRMED — `secure: null`, `encrypt: false` |
| BUG-001 | storage PUT route tanpa auth | `php artisan route:list --name=storage`, `cat vendor/.../ReceiveFile.php` | ✅ SAFE — butuh signed URL (`hasValidSignature`) |
| BUG-002 | Test untuk webhook PAID | `ls tests/Feature/Api/` | ✅ CONFIRMED — tidak ada |
| Price integrity | Modifier harga dari DB/client? | `cat TransactionService.php:317-335` | ✅ SAFE — `$dbModifier->extra_price` dari DB |
| Money math | Harga variant dari DB/client? | `TransactionService.php:318` | ✅ SAFE — `$unitPrice = $variant->price` dari DB |
| Multi-tenant | TenantScope dan test isolasi | `php artisan test` | ✅ CONFIRMED — 15 isolation tests pass |
| Test suite | Semua test pass | `php artisan test` | ✅ 94 passed / 163 assertions |
| DEP-001 | npm CVE | `npm audit --omit=dev` | ✅ CONFIRMED — 4 high, 2 moderate |
| DEP-002 | Composer CVE | `composer audit` | ✅ CONFIRMED — 2 medium (commonmark) |
| OPS-003 | Index `transactions.code` | Baca migrations | ✅ CONFIRMED — composite index saja, no standalone |

---

## Quick-Fix Checklist (Pre Go-Live)

Copy checklist ini dan tandai ✅ sebelum deploy ke production:

### CRITICAL — harus selesai sebelum deploy
- [ ] **OPS-001** Ganti `.github/workflows/deploy.yml:38` dari `migrate:fresh --force --seed` ke `migrate --force`
- [ ] **OPS-002** Set `APP_DEBUG=false` di server production
- [ ] **OPS-002** Set `LOG_LEVEL=warning` di server production
- [ ] **OPS-002** Set `SESSION_ENCRYPT=true` di server production
- [ ] **OPS-002** Ganti `XENDIT_WEBHOOK_TOKEN` ke random string 64+ karakter
- [ ] **OPS-002** Ganti `XENDIT_SECRET_KEY` ke key production dari Xendit dashboard
- [ ] **SEC-001** Set `SANCTUM_TOKEN_EXPIRY=525600` (atau nilai sesuai kebijakan) di `config/sanctum.php`
- [ ] **SEC-002** Sebelum run seeder: pastikan ADMIN_EMAIL dan password sudah diganti, atau paksa ganti password saat login pertama

### HIGH — selesaikan segera
- [ ] **SEC-003** Ganti `XENDIT_WEBHOOK_TOKEN` (sudah di atas), aktifkan IP allowlist di Xendit Dashboard
- [ ] **SEC-005** Tambahkan `throttle:60,1` ke route group API di `routes/api.php`
- [ ] **SEC-009** Set `SESSION_SECURE_COOKIE=true` di `.env` production
- [ ] **BUG-002** Tulis minimal 4 test cases untuk `XenditWebhookController@handle`
- [ ] **DEP-001** Jalankan `npm audit fix` dan review hasilnya

### MEDIUM — jadwalkan dalam 2 minggu pertama post-launch
- [ ] **BUG-003** Tambahkan test idempotency webhook
- [ ] **BUG-004** Buat endpoint manual confirm payment untuk owner
- [ ] **OPS-003** Buat migration tambah index `transactions.code`, `stock_movements.product_variant_id`, `(transactions.tenant_id, status)`
- [ ] **OPS-004** Update FK `transactions.user_id` tambahkan `->nullOnDelete()`
- [ ] **DEP-002** Jalankan `composer update league/commonmark`
- [ ] **LOG** Set `LOG_STACK=daily` di production

### LOW — backlog
- [ ] Ganti `v-html` di pagination dengan text interpolation biasa
- [ ] Implementasi `TransactionPolicy` dan `ProductPolicy`
- [ ] Tambahkan CSP header middleware
- [ ] Update `.gitignore` untuk storage/logs
- [ ] Buat `tests/Feature/Api/XenditWebhookTest.php` (BUG-002, BUG-003)
- [ ] Dokumentasikan bahwa `void()` dibatasi hari ini saja (design decision)
