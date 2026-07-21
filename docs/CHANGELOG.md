# SAPI — Changelog & Revision Log

**Format:** Setiap perubahan yang dilakukan **di luar fase/phase** yang sudah direncanakan, atau perubahan keputusan arsitektur, WAJIB dicatat di file ini.

---

## Cara Menggunakan File Ini

### Kapan Harus Dicatat
- Perubahan skema database yang tidak ada di dokumen fase
- Perubahan keputusan arsitektur (auth method, status naming, dll)
- Hotfix atau patch di luar fase yang sedang berjalan
- Penambahan dependency baru yang tidak direncanakan
- Perubahan API contract / endpoint
- Bug fix kritis yang mengubah behavior

### Format Entry

```markdown
### [TIPE] Judul Singkat
- **Tanggal:** YYYY-MM-DD
- **Fase Terkait:** Phase-X / Di Luar Fase / Cross-Phase
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config
- **Breaking Change:** Ya / Tidak
- **Deskripsi:** Penjelasan singkat perubahan
- **Alasan:** Kenapa perubahan ini dilakukan
- **File Terdampak:**
  - `path/to/file.php` — deskripsi perubahan
- **Catatan Migrasi:** (jika ada) Instruksi khusus untuk apply perubahan
```

### Tipe Entry
| Tag | Keterangan |
|---|---|
| `[RECONCILE]` | Rekonsiliasi perbedaan antara dokumen v1.0 ↔ v1.1 ↔ diskusi |
| `[HOTFIX]` | Perbaikan mendesak di luar fase |
| `[DECISION]` | Perubahan keputusan arsitektur/teknis |
| `[ADDITION]` | Penambahan fitur/komponen yang tidak direncanakan |
| `[SCHEMA]` | Perubahan skema database |
| `[DEPRECATE]` | Fitur/approach yang ditinggalkan |

---

## Revision History

---

### [ADDITION] Platform Console — Fondasi Panel Pemilik SaaS (Tahap A)
- **Tanggal:** 2026-07-21
- **Fase Terkait:** `docs/phases-2/PHASE-SAAS_Platform-Console-Subscription.md` — Tahap A (dari `[BL-005]`/`[BL-006]`)
- **Dampak:** Migration | Model | Middleware | Controller | Config | Route | Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Tingkat akses ketiga di atas tenant: akun **pemilik SaaS** dengan tabel & guard sendiri (`platform_users` / guard `platform`), izin per modul lewat `platform_user_modules` + `config/platform-rbac.php`, panel `/platform` berisi beranda dan daftar tenant **read-only**, serta jejak audit `platform_audit_logs`. Belum ada langganan, harga, maupun data omset — itu Tahap B–D.
- **Alasan:** Pemilik SaaS perlu tahu siapa kliennya dan berapa akunnya untuk menjalankan bisnis, **tanpa** bisa melihat data operasional klien. Fondasi isolasinya sengaja dibangun lebih dulu berikut testnya, karena semua tahap berikutnya menumpuk di atasnya.
- **File Terdampak:**
  - `database/migrations/2026_07_21_1324{29,30,31}_*` — `platform_users`, `platform_audit_logs`, `platform_user_modules`
  - `app/Models/PlatformUser.php`, `PlatformUserModule.php`, `PlatformAuditLog.php` — model baru
  - `app/Models/Tenant.php` — relasi `owners()` (kontak penagihan)
  - `config/auth.php` — guard `platform` + provider `platform_users`
  - `config/platform-rbac.php` — katalog 6 modul platform
  - `app/Http/Middleware/EnsurePlatformModule.php` + alias `platform.can` di `bootstrap/app.php`
  - `app/Http/Middleware/HandleInertiaRequests.php` — bedakan `User` vs `PlatformUser` lewat `instanceof`; akun platform dibagikan di kunci **terpisah** `auth.platformUser`
  - `bootstrap/app.php` — `redirectGuestsTo()` bersyarat untuk area `/platform`
  - `app/Http/Controllers/Platform/{Auth,Dashboard,Tenant}Controller.php`, `app/Http/Resources/PlatformTenantResource.php`
  - `resources/js/Layouts/PlatformLayout.vue`, `resources/js/Pages/Platform/**`
  - `database/seeders/PlatformUserSeeder.php`, `database/factories/PlatformUserFactory.php`
  - `tests/Feature/Platform/{PlatformAuthTest,PlatformIsolationTest,PlatformArchTest}.php` — 16 test
- **Keputusan teknis yang perlu diketahui:**
  1. **spatie TIDAK dipakai di sisi platform.** Pivot `model_has_roles` menuntut `tenant_id` non-null karena kolom itu bagian dari primary key-nya, sedangkan akun platform tak punya tenant. Diganti tabel `platform_user_modules` yang sederhana; pola UI modul+centang tetap sama seperti Phase RBAC.
  2. **Isolasi tidak bersandar pada `TenantScope`.** Di request platform scope itu memfilter `tenant_id = null` (nol baris — gagal menutup, aman), tapi di job/command/seeder ia tidak aktif sama sekali. Karena itu batasnya dijaga tiga lapis: arch test (impor), isolation test (payload HTTP), dan resource daftar-putih.
  3. **`PlatformTenantResource` memakai daftar putih**, bukan `parent::toArray()` — kolom baru di `tenants` (mis. `ai_api_key`) tidak ikut bocor dengan sendirinya.
- **Catatan Migrasi:** `php artisan migrate` lalu `php artisan db:seed --class=PlatformUserSeeder`. Akun pertama diambil dari `PLATFORM_ADMIN_EMAIL`/`PLATFORM_ADMIN_PASSWORD`; nilai bawaan hanya untuk lokal dan **wajib** diganti sebelum dipakai di server sungguhan. Perlu `npm run build` karena ada komponen Vue baru.

---

### [HOTFIX] Lima Kendala Implementasi RBAC & Resolusinya
- **Tanggal:** 2026-07-21
- **Fase Terkait:** Phase RBAC (menyertai entri `[ADDITION]` RBAC di bawah)
- **Dampak:** Middleware | Controller | Config | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Lima kendala yang ditemukan saat mengimplementasikan RBAC, semuanya sudah diselesaikan dan diverifikasi (suite penuh hijau — 218 passed):
  1. **Inertia `share()` berjalan sebelum team-id spatie di-set.** Inertia mengevaluasi `share()` di awal middleware global, sebelum `EnsureTenant` men-set team-id → evaluasi eager `permissions` mendapat team-id `null` (kosong). **Resolusi:** `auth.user.permissions` dijadikan **lazy closure** agar diresolusi di fase render (team-id sudah benar).
  2. **`getPermissionNames()` (relasi Eloquent) kosong dari konteks share Inertia** meski team-id benar. **Resolusi:** daftar permission share dihitung via `$user->can($module)` per modul katalog — jalur registrar/Gate spatie yang sama dengan middleware `permission:`. Terverifikasi (test `staff page role list…`) bahwa `getRoleNames()`/`getPermissionNames()` **tetap benar** di controller biasa; hanya fase-share yang rapuh.
  3. **Kebocoran daftar role antar-tenant** di `StaffController@index` (query `Role` tak di-scope team). **Resolusi:** filter `where('tenant_id', …)` + test regresi memastikan role tenant B tak muncul di halaman staf tenant A.
  4. **Ekspektasi test POS kasir salah** — kasir tanpa sesi kas **sengaja** di-redirect ke `cashier.cash-drawer` (baseline, bukan blokir RBAC). **Resolusi:** ekspektasi test dikoreksi (`assertRedirect`), bukan mengubah perilaku aplikasi.
  5. **Katalog modul terkopel ke class Seeder + metadata terduplikasi** (middleware & `RoleController` meng-import `PermissionCatalogSeeder`; `RoleController` punya `MODULE_META` sendiri). **Resolusi:** katalog diekstrak ke **`config/rbac.php`** sebagai single source of truth (dipakai seeder, controller, middleware).
- **Alasan:** Menuntaskan kendala agar tidak menyisakan workaround rapuh / hutang teknis, dan menegakkan satu sumber kebenaran untuk katalog modul.
- **File Terdampak:**
  - `config/rbac.php` — **baru**: katalog modul (name → label, sensitive)
  - `app/Http/Middleware/HandleInertiaRequests.php` — lazy closure + `can()` + baca `config('rbac.modules')`
  - `app/Http/Controllers/Owner/StaffController.php` — daftar role di-scope tenant
  - `app/Http/Controllers/Owner/RoleController.php` — hapus `MODULE_META`, baca `config('rbac.modules')`
  - `database/seeders/PermissionCatalogSeeder.php` — baca `config('rbac.modules')` (hapus const `MODULES`)
  - `tests/Feature/Authorization/ModuleAccessTest.php` — test regresi isolasi role di halaman staf (kini 12 test)
- **Catatan Migrasi:** Bila config di-cache di server, jalankan `php artisan config:clear` (atau `config:cache`) agar `config/rbac.php` termuat.

---

### [ADDITION] RBAC — Kontrol Akses Modul untuk Staf Non-Owner
- **Tanggal:** 2026-07-21
- **Fase Terkait:** Phase RBAC (`docs/phases-2/PHASE-RBAC_Module-Access-Control.md`)
- **Dampak:** Dependency | Migration | Model | Middleware | Controller | Route | Frontend | Config
- **Breaking Change:** Tidak (additive — enum `role` owner/cashier tetap sebagai gerbang kasar)
- **Deskripsi:** Owner kini bisa membuat **role per-tenant** berisi kumpulan **modul** dan mengikat akun staf ke role tersebut. Staf non-owner hanya melihat & mengakses modul yang diberikan. Owner tetap super-admin (bypass semua cek permission via `Gate::before`). Implementasi memakai `spatie/laravel-permission` fitur **teams** (`team_foreign_key = tenant_id`) sehingga role terisolasi per tenant. Katalog 7 modul global: `pos`, `cash_drawer`, `products`, `stock`, `reports`, `payment_methods`, `ai_analysis`. Keputusan: shell nav staf memakai sidebar terfilter yang sama (Keputusan A); modul sensitif (`payment_methods`, `ai_analysis`) grantable tapi default tidak dicentang (Keputusan B); Mobile API ditunda ke backlog `BL-003` (Keputusan C).
- **Alasan:** Memenuhi kebutuhan "Kasir 1 hanya POS; Kasir 2 POS + Inventori" dengan role kustom per usaha.
- **File Terdampak:**
  - `composer.json` — tambah dependency `spatie/laravel-permission`
  - `config/permission.php` — `teams=true`, `team_foreign_key='tenant_id'`
  - `config/rbac.php` — katalog modul (single source of truth; lihat entri `[HOTFIX]` di atas)
  - `database/migrations/*_create_permission_tables.php` — tabel permission (kolom `tenant_id`)
  - `app/Models/User.php` — trait `HasRoles`
  - `app/Http/Middleware/EnsureTenant.php` + `EnsureTenantApi.php` — set team-id spatie setelah cek tenant
  - `app/Providers/AppServiceProvider.php` — `Gate::before` owner bypass
  - `bootstrap/app.php` — alias middleware `permission` (alias `role` lama tak diubah)
  - `database/seeders/PermissionCatalogSeeder.php` — katalog 7 modul (global, idempotent); dipanggil di `DatabaseSeeder`
  - `app/Http/Controllers/Owner/StaffController.php` + `RoleController.php` — CRUD staf & role (owner-only)
  - `routes/web.php` — grup owner dipecah: modul grantable → `permission:<modul>`; sensitif/owner-eksklusif → tetap `role:owner`
  - `app/Http/Middleware/HandleInertiaRequests.php` — share `auth.user.permissions` (lazy closure; owner `['*']`)
  - `resources/js/Layouts/OwnerLayout.vue` — sidebar difilter `can()` + grup "Tim & Akses"
  - `resources/js/Components/CashierTopbar.vue` — jalur staf ke modul owner (Keputusan A)
  - `resources/js/Pages/Owner/Staff/Index.vue` + `Owner/Roles/Index.vue` — UI kelola staf & role
  - `tests/Feature/Authorization/ModuleAccessTest.php` — 11 test (owner bypass, gating, isolasi tenant, share, CRUD)
- **Catatan Migrasi:** Jalankan `php artisan migrate` lalu `php artisan db:seed --class=PermissionCatalogSeeder` (idempotent, additif). Suite penuh hijau — 218 passed. **Catatan teknis:** share permission dihitung via `can()` per modul (jalur registrar spatie), bukan relasi Eloquent `getPermissionNames()` yang rapuh terhadap konteks team saat dipanggil dari middleware Inertia.

---

### [HOTFIX] Ekspektasi Dua Test Usang (BL-002)
- **Tanggal:** 2026-07-21
- **Fase Terkait:** Di Luar Fase (backlog `BL-002`)
- **Dampak:** Test
- **Breaking Change:** Tidak
- **Deskripsi:** Dua test dengan ekspektasi usang membuat suite selalu merah (menutupi sinyal regresi). `ExampleTest` meng-assert `GET /` → `302`, padahal `/` kini me-render landing page (`200`). `AuthTest > owner can access cashier routes` meng-assert `GET /cashier/cash-drawer` → `200` untuk owner, padahal rute itu **sengaja** mengalihkan owner ke POS (`302`). Keduanya adalah ekspektasi test yang salah, bukan bug aplikasi.
- **Alasan:** Suite hijau adalah pagar agar regresi baru mudah terdeteksi; dua kegagalan pre-existing mengaburkannya.
- **File Terdampak:**
  - `tests/Feature/ExampleTest.php` — assert `200` (sesuai nama test "successful response")
  - `tests/Feature/Auth/AuthTest.php` — arahkan ke `/cashier/pos` (rute kasir yang ter-render untuk owner) alih-alih `/cashier/cash-drawer`
- **Catatan:** Tidak ada test yang dihapus. Suite penuh hijau — 206 passed.

---

### [ADDITION] Transaksi Offline + Sinkronisasi (PWA Fase B/C/D)
- **Tanggal:** 2026-07-16
- **Fase Terkait:** Phase PWA (`docs/phases-2/PHASE-PWA_Offline-Transaction-Sync.md`) — Fase B, C, D
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Kasir kini bisa terus menjual (tunai) saat internet putus. Katalog di-cache ke IndexedDB, transaksi offline diantre di outbox lokal, lalu tersinkron otomatis saat online lewat `POST /cashier/transactions/sync`. Konflik ditangani optimistik: penjualan yang sudah terjadi fisik **tidak pernah ditolak** — stok boleh minus, transaksi ditandai `needs_review`, owner mengoreksi lewat halaman Koreksi Offline. Payload offline tidak pernah dipercaya mentah: total dihitung ulang server, kepemilikan tenant & cash-only ditegakkan, `occurred_at` dicek kewajarannya.
- **Alasan:** Menutup kehilangan penjualan saat koneksi putus tanpa mengorbankan integritas data. Melanjutkan Fase A (`client_uuid`) yang sudah menyediakan fondasi idempotensi.
- **Dependency Baru:** `idb@8.0.3` (~1–2 KB gzip) — disetujui owner 2026-07-16, lihat Keputusan Terbuka A di dokumen fase.
- **File Terdampak:**
  - `database/migrations/2026_07_16_231216_add_offline_sync_to_transactions.php` — kolom `channel`, `occurred_at`, `synced_at`, `sync_status`, `device_id` + index `(tenant_id, sync_status)` & `(tenant_id, occurred_at)`
  - `app/Models/Transaction.php` — konstanta channel/sync_status, `effectiveDate()`, `effectiveDateSql()`, scope `whereEffectiveDate`/`whereEffectiveBetween`/`whereEffectiveFrom`
  - `app/Services/TransactionService.php` — `commitOffline()`, `processOfflineItems()`, `resolveOfflineModifiers()`, `assertCashOnly()`, `parseOccurredAt()`, `generateTransactionCodeFor()`
  - `app/Http/Controllers/Cashier/POSController.php` — `sync()` (JSON, try/catch per item)
  - `app/Http/Requests/SyncOfflineTransactionsRequest.php` — validasi bentuk batch
  - `app/Http/Controllers/Owner/OfflineReviewController.php` + `resources/js/Pages/Owner/OfflineReview/Index.vue` — halaman koreksi
  - `app/Services/BadgeHelperService.php` — badge `needs_review`
  - `app/Http/Controllers/Owner/ReportController.php`, `Owner/DashboardController.php`, `app/Services/ProfitService.php`, `app/Services/AiContextService.php` — laporan pakai tanggal efektif
  - `resources/js/services/offlineDb.js`, `offlineSession.js`, `deviceId.js` — layer IndexedDB & siklus hidup data offline
  - `resources/js/composables/useCatalogCache.js`, `useOfflineQueue.js`, `useOnlineStatus.js`
  - `resources/js/Pages/Cashier/POS.vue` — cache katalog, enqueue offline, cash-only guard, pemicu sync
  - `public/sw.js` — `PAGE_CACHE` untuk shell POS, `CLEAR_PRIVATE_CACHES`, `CACHE_VERSION` → v2
  - `tests/Feature/OfflineSyncTest.php` — 30 test
- **Keputusan Arsitektur Penting:**
  - `channel` kolom string baru, **bukan** value tambahan pada enum `source` — `source` (pos|self_order) dan `channel` (online|offline) ortogonal; mengubah enum juga bermasalah di SQLite test suite.
  - Antrean transaksi ada di layer aplikasi (IndexedDB), **bukan** Background Sync di SW — kontrol penuh atas idempotensi & konflik. Background Sync tetap di luar scope (Keputusan C).
  - Outbox menyimpan `cashier_id` dan hanya di-flush oleh kasir yang membuatnya — server mengatribusikan penjualan ke user yang login saat sync, sehingga tanpa penjaga ini penjualan bisa masuk ke laporan shift kasir yang salah.
  - `PAGE_CACHE` (HTML ter-autentikasi) dibersihkan saat logout; outbox **tidak** — outbox berisi uang yang belum sampai ke server.
  - Open bill & pembayaran tagihan terbuka diblokir saat offline (memutasi baris server yang bisa disentuh till lain).
- **Catatan Migrasi:** `php artisan migrate` lalu `npm install && npm run build`. `CACHE_VERSION` sudah dinaikkan sehingga SW lama otomatis membuang cache-nya saat activate.

---

### [HOTFIX] Penomoran Ordered List Hasil AI (BL-001)
- **Tanggal:** 2026-07-15
- **Fase Terkait:** Di Luar Fase (backlog `BL-001`)
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** `renderMarkdown()` menutup `<ol>`/`<ul>` setiap kali menemui baris kosong. Karena output LLM sering menyisipkan baris kosong antar item list, tiap item jadi list terpisah dan browser menomori ulang mulai "1." (tampil "1. 1. 1."). Kini saat menemui baris kosong, renderer mengintip baris non-kosong berikutnya: bila masih item list dengan tipe sama, list dibiarkan terbuka (baris kosong = pemisah item), selain itu list ditutup seperti semula. List campuran (ol→ul) dan list yang diikuti paragraf tetap terpisah dengan benar.
- **Alasan:** Menampilkan penomoran hasil analisis AI sesuai maksud ("1. 2. 3.") tanpa menambah dependency markdown.
- **File Terdampak:**
  - `resources/js/Pages/Owner/AiAnalysis/Index.vue` — `renderMarkdown()` look-ahead pada baris kosong

---

### [SCHEMA] Idempotency `client_uuid` di Checkout (PWA Fase A)
- **Tanggal:** 2026-07-15
- **Fase Terkait:** Phase PWA (`docs/phases-2/PHASE-PWA_Offline-Transaction-Sync.md`) — Fase A
- **Dampak:** Migration | Model | Service | Request | Frontend
- **Breaking Change:** Tidak (kolom baru `nullable`; payload `client_uuid` opsional)
- **Deskripsi:** Menambah kolom `client_uuid` (nullable) di `transactions` dengan unique `(tenant_id, client_uuid)`. Client (`POS.vue`) membuat `crypto.randomUUID()` sekali per percobaan checkout dan mempertahankannya lintas retry; direset hanya setelah sukses. `TransactionService::checkout()` mendedup by `client_uuid` — request identik (retry jaringan flaky / double-submit) mengembalikan transaksi lama sebagai no-op alih-alih menggandakan. Berlaku untuk checkout normal maupun open bill. Ini fondasi idempotensi untuk offline-sync (Fase C) sekaligus menutup celah double-input online sekarang.
- **Alasan:** Guard `processing` di UI tidak melindungi dari retry jaringan; `client_uuid` memberi perlindungan double-input di lapisan jaringan/server.
- **File Terdampak:**
  - `database/migrations/2026_07_13_175345_add_client_uuid_to_transactions_table.php` — kolom + unique `(tenant_id, client_uuid)`
  - `app/Models/Transaction.php` — `client_uuid` di `$fillable`
  - `app/Services/TransactionService.php` — dedup by `client_uuid` di `checkout()`
  - `app/Http/Requests/StoreTransactionRequest.php` — validasi `client_uuid` → `nullable|uuid`
  - `resources/js/Pages/Cashier/POS.vue` — generate/kirim/reset UUID per checkout (normal + open bill)
  - `tests/Feature/Cashier/POSTest.php` — cakupan idempotensi

---

### [ADDITION] Edit Transaksi (Owner & Kasir) + Recalc Stok & Audit Trail
- **Tanggal:** 2026-07-14
- **Fase Terkait:** Phase EDIT-TX (`docs/phases-2/PHASE-EDIT-TX_Owner-Cashier-Transaction-Edit.md`)
- **Dampak:** Migration | Model | Service | Controller | Route | Frontend
- **Breaking Change:** Tidak (migrasi hanya menambah kolom/enum value)
- **Deskripsi:** Owner dapat mengedit transaksi `completed` kapan saja; kasir hanya untuk transaksi dalam shift laci kas miliknya yang masih terbuka (`created_at >= opened_at`, `closed_at = null`). Edit full (item, modifier, pembayaran) dengan stok dihitung ulang lewat **delta per varian** (satu `StockMovement` tipe `edit` per varian yang berubah), harga otoritatif dari DB, seluruhnya dalam satu `DB::transaction()` dengan `lockForUpdate()`. Setiap edit direkam di `transaction_edits` (before/after + alasan). UI berupa modal reusable yang membungkus `Components/Modal.vue` dan mereuse `ModifierModal`/`PaymentModal`.
- **Alasan:** Memberi jalan resmi koreksi salah input tanpa void+input ulang, sambil menjaga integritas stok, laporan, dan kepercayaan data lewat audit trail.
- **File Terdampak:**
  - `database/migrations/..._add_edit_type_to_stock_movements.php` — tambah enum value `edit`
  - `database/migrations/..._add_edit_columns_to_transactions.php` — `edited_at`, `edited_by`
  - `database/migrations/..._create_transaction_edits_table.php` — tabel audit before/after
  - `app/Models/{StockMovement,Transaction,TransactionEdit}.php` — konstanta, relasi `edits()`/`editor()`
  - `app/Services/TransactionEditService.php` — logika edit + guard `assertEditable()`
  - `app/Http/Requests/EditTransactionRequest.php`, `app/Http/Controllers/Cashier/TransactionEditController.php`
  - `routes/web.php` — `PUT /cashier/transactions/{transaction}` (`cashier.transactions.update`)
  - `app/Http/Controllers/Owner/ReportController.php`, `app/Http/Controllers/Cashier/POSController.php` — kirim katalog (deferred) + flag `can_edit`/riwayat edit
  - `resources/js/Components/TransactionEditModal.vue` + entry point di `Owner/Transactions/Detail.vue` & `Cashier/TransactionHistory.vue`
- **Catatan Migrasi:** Nilai enum `edit` juga ditambahkan ke migrasi create `stock_movements` agar konsisten pada driver SQLite (mengikuti pola penambahan `void`).

---

### [DECISION] SumoPod Jadi Provider AI Default (OpenAI-Compatible Gateway)
- **Tanggal:** 2026-07-11
- **Fase Terkait:** Phase-AI-2 (AI Engine)
- **Dampak:** Service | Config | Controller | Frontend
- **Breaking Change:** Tidak (default berubah `gemini` → `sumopod`; provider lama tetap didukung)
- **Deskripsi:** Menambah provider `sumopod` sebagai default. SumoPod adalah gateway AI OpenAI-compatible (persis OpenAI, hanya beda base URL `https://ai.sumopod.com/v1`), jadi `SumoPodProvider` cukup extend `OpenAiProvider` dan override endpoint chat completions. `OpenAiProvider` di-refactor agar endpoint & label provider bisa di-override lewat method `endpoint()`/`providerLabel()` (tanpa mengubah kontrak `AiProvider`). Satu API key SumoPod (`sk-...`) memberi akses ke banyak model (Anthropic, OpenAI, Gemini, DeepSeek, dll) via nama model.
- **Alasan:** Menyederhanakan setup BYOK & free-tier: satu penyedia + satu format key gaya OpenAI untuk banyak model, dengan budget limit di dashboard SumoPod. Tanpa dependency PHP baru — tetap via `Http` facade sesuai prinsip Phase-AI-2.
- **File Terdampak:**
  - `app/Services/Ai/SumoPodProvider.php` — provider baru (extends `OpenAiProvider`, override base URL ke `https://ai.sumopod.com/v1/chat/completions`)
  - `app/Services/Ai/OpenAiProvider.php` — endpoint & label di-ekstrak ke `endpoint()`/`providerLabel()`; props `protected`
  - `app/Services/Ai/AiProviderFactory.php` — `match` tambah cabang `sumopod`
  - `config/ai.php` — `default` → `sumopod`, tambah `models.sumopod`; `config/services.php` — `sumopod` key
  - `app/Http/Controllers/Owner/SettingsController.php` — validasi `ai_provider` tambah `sumopod`
  - `resources/js/Pages/Owner/Settings/Index.vue` — opsi provider "SumoPod" + label default
  - `.env.example` — `AI_DEFAULT_PROVIDER=sumopod`, `SUMOPOD_API_KEY`, `AI_SUMOPOD_MODEL`
  - `tests/Feature/Ai/AiProviderTest.php`, `tests/Feature/Ai/AiProviderFactoryTest.php` — test SumoPod (Http::fake + resolusi factory)
- **Catatan Migrasi:** Tidak ada migrasi. Isi `SUMOPOD_API_KEY` (untuk free tier: `AI_FREE_TIER_KEY` = key SumoPod) lalu `php artisan config:clear`. Detail penyedia di `docs/phases-2/PHASE-AI-2b_SumoPod-Provider.md`.

---

### [ADDITION] MCP Server: Data Bridge Read-Only untuk AI Client Milik Owner
- **Tanggal:** 2026-07-11
- **Fase Terkait:** Phase-AI-4 (MCP Server)
- **Dampak:** Route | Controller | Service | Config | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** MCP Server (`POST /mcp/business`) mengekspos data agregat tenant (penjualan, profit, menu) sebagai sumber data read-only untuk AI client milik owner (mis. Claude Desktop). Tiga tool: `get-sales-summary`, `get-profit`, `get-menu`. Akses via bearer token Sanctum yang dibuat/dicabut owner di Settings; endpoint dibatasi rate limit `mcp` (60/menit), owner-only, tenant-scoped otomatis, tanpa PII.
- **Alasan:** Pada MCP, LLM yang memanggil adalah client milik owner — app hanya menyediakan data dan tidak menanggung biaya LLM. Karena itu MCP **tidak** memakai kuota/free-tier AI aplikasi (beda dari AI-2/AI-3); cukup rate limit untuk cegah abuse. `ProductCatalogService` diekstrak agar query katalog dipakai bersama MCP & API consumer.
- **File Terdampak:**
  - `routes/ai.php` — `Mcp::web('/mcp/business', SapiBusinessServer::class)` + middleware `auth:sanctum`, `tenant.api`, `role:owner`, `throttle:mcp`
  - `app/Mcp/Servers/SapiBusinessServer.php`, `app/Mcp/Tools/*` — server + `BusinessDataTool` (base) + 3 tool
  - `app/Services/ProductCatalogService.php` — katalog produk bersama; `app/Http/Controllers/Api/V1/ApiProductController.php` — refactor memakai service
  - `app/Providers/AppServiceProvider.php` — RateLimiter `mcp`
  - `app/Http/Controllers/Owner/SettingsController.php`, `resources/js/Pages/Owner/Settings/Index.vue`, `app/Http/Middleware/HandleInertiaRequests.php` — generate/cabut token MCP (plaintext flash sekali)
- **Catatan Migrasi:** Tidak ada migrasi. Detail & backlog tools di `docs/phases-2/PHASE-AI-4_MCP-Server.md`.

---

### [DECISION] RunAiAnalysisJob Autentikasi sebagai Pemilik Analisis (Tenant Scoping di Queue)
- **Tanggal:** 2026-07-10
- **Fase Terkait:** Phase-AI-3 (AI Analysis)
- **Dampak:** Job | Service
- **Breaking Change:** Tidak
- **Deskripsi:** `RunAiAnalysisJob` memanggil `Auth::setUser($analysis->user)` sebelum membangun konteks, lalu `Auth::forgetGuards()` di blok `finally`. Ini menyimpang dari contoh kode awal di dokumen `PHASE-AI-3` yang tidak meng-set auth di dalam Job.
- **Alasan:** `AiContextService` dan `ProfitService` bergantung pada `TenantScope` global yang berbasis `auth()`. Karena queue job berjalan tanpa sesi HTTP, tanpa autentikasi eksplisit query konteks tidak ter-scope ke tenant yang benar (berisiko membaca/menggabungkan data lintas tenant). Meng-set user pemilik analisis memastikan seluruh agregasi ter-scope ke tenant tersebut; `forgetGuards()` mencegah kebocoran state auth antar job pada worker yang sama.
- **File Terdampak:**
  - `app/Jobs/RunAiAnalysisJob.php` — `Auth::setUser()` sebelum `buildContext()`, `Auth::forgetGuards()` di `finally`
  - `docs/phases-2/PHASE-AI-3_AI-Analysis.md` — contoh kode Job disinkronkan dengan implementasi
- **Catatan Migrasi:** Tidak ada migrasi. Perilaku hanya relevan saat worker queue memproses beberapa job dari tenant berbeda.

---

### [ADDITION] Public API Reference Page for Mobile POS
- **Tanggal:** 2026-05-29
- **Fase Terkait:** Cross-Phase (Phase-2 Mobile API / Public Docs)
- **Dampak:** View | Controller | Route
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan halaman dokumentasi publik di `/api-docs` untuk referensi endpoint Mobile POS, autentikasi Sanctum, contoh request/response JSON, dan batasan akses per role.
- **Alasan:** Mengurangi ketergantungan pada dokumentasi manual terpisah dan memberi sumber referensi tunggal yang bisa diakses langsung dari aplikasi saat integrasi client mobile berjalan.
- **File Terdampak:**
  - `app/Http/Controllers/Public/LandingController.php` — tambah action `docs()` untuk halaman dokumentasi
  - `routes/web.php` — route baru `/api-docs`
  - `resources/views/public/api-docs.blade.php` — halaman referensi API publik
- **Catatan Migrasi:** Tidak ada migrasi database. Pastikan aset frontend/Vite tersedia jika style global dipakai di halaman dokumentasi.

---

### [DECISION] API Consumer Dipindahkan ke Prefix `/api/v1`
- **Tanggal:** 2026-05-29
- **Fase Terkait:** Cross-Phase (Self Order / Mobile API)
- **Dampak:** Controller | Route | API Contract
- **Breaking Change:** Ya
- **Deskripsi:** Semua endpoint consumer yang sebelumnya berada langsung di bawah `/api/*` dipindahkan ke namespace versi pertama di `/api/v1/*`. Controller terkait juga dipindahkan dari `App\Http\Controllers\Api\...` ke `App\Http\Controllers\Api\V1\...` agar struktur implementasi mengikuti kontrak route versi.
- **Alasan:** Menyiapkan kompatibilitas jangka panjang untuk perubahan contract API tanpa mengganggu webhook eksternal atau endpoint versi lama yang nantinya perlu dipelihara paralel.
- **File Terdampak:**
  - `routes/api.php` — regroup route consumer di bawah prefix `v1`
  - `app/Http/Controllers/Api/V1/ApiProductController.php` — lokasi baru endpoint daftar produk
  - `app/Http/Controllers/Api/V1/ApiOrderController.php` — lokasi baru endpoint self-order dan fulfillment
  - `app/Http/Controllers/Api/V1/Mobile/MobileAuthController.php` — lokasi baru auth mobile
  - `app/Http/Controllers/Api/V1/Mobile/MobileTenantController.php` — lokasi baru profil tenant mobile
  - `app/Http/Controllers/Api/V1/Mobile/MobileCashDrawerController.php` — lokasi baru operasi cash drawer mobile
  - `app/Http/Controllers/Api/V1/Mobile/MobileTransactionController.php` — lokasi baru operasi transaksi mobile
  - `app/Http/Controllers/Api/ApiProductController.php` — controller lama dihapus
  - `app/Http/Controllers/Api/ApiOrderController.php` — controller lama dihapus
  - `app/Http/Controllers/Api/Mobile/*.php` — controller mobile lama dihapus
- **Catatan Migrasi:** Update semua consumer/client dari path lama ke path baru, misalnya `/api/mobile/...` menjadi `/api/v1/mobile/...`, `/api/products` menjadi `/api/v1/products`, dan `/api/orders` menjadi `/api/v1/orders`. Endpoint `POST /api/xendit/webhook` tetap non-versioned.

---

### [ADDITION] Owner Modifier Quick Settings, Cashier Account Menu, and UI Stability Fixes
- **Tanggal:** 2026-05-28
- **Fase Terkait:** Cross-Phase (Phase-3 POS / Phase-5 Dashboard)
- **Dampak:** Frontend | Controller | Route | View
- **Breaking Change:** Tidak
- **Deskripsi:** Penambahan dan perbaikan terfokus pada operasional harian:
  1. Owner kini bisa mengubah pengaturan modifier group (`is_required`, `is_multiple`) langsung dari halaman list tanpa masuk form edit terpisah.
  2. Detail modifier group diperluas dengan daftar produk yang menggunakan modifier dan ringkasan visual yang lebih jelas.
  3. Topbar kasir diperbarui dengan dropdown akun (nama, email, dan logout) agar navigasi lebih ringkas di layar sempit.
  4. Date picker dipindah ke `Teleport` dengan posisi `fixed` adaptif agar tidak terpotong container scroll dan tetap terlihat saat resize/scroll.
  5. Owner layout mendapat custom scrollbar pada sidebar dan area konten untuk konsistensi visual.
  6. Penambahan meta CSRF token pada layout app untuk kompatibilitas request client-side yang membutuhkan token dari DOM.
- **Alasan:** Mengurangi friction pada konfigurasi modifier, meningkatkan kejelasan konteks data owner, menstabilkan komponen input tanggal di berbagai layout, dan memperbaiki ergonomi navigasi kasir.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/ModifierController.php` — tambah endpoint update settings + preload relasi produk
  - `routes/web.php` — route patch untuk `modifiers.settings`
  - `resources/js/Pages/Owner/Modifiers/Index.vue` — quick toggle settings + section detail produk pemakai modifier
  - `resources/js/Components/CashierTopbar.vue` — dropdown akun + logout di menu profil
  - `resources/js/Components/DatePicker.vue` — teleport popup + kalkulasi posisi viewport-aware
  - `resources/js/Layouts/OwnerLayout.vue` — penerapan kelas scrollbar custom
  - `resources/css/app.css` — style scrollbar sidebar/main
  - `resources/views/app.blade.php` — meta `csrf-token`
- **Catatan Migrasi:** Tidak ada migrasi database. Jalankan `npm run build`/`npm run dev` untuk memverifikasi aset frontend setelah update komponen.

---

### [ADDITION] UI System Refresh, Cashier Flow Guard, and Shared Components
- **Tanggal:** 2026-05-28
- **Fase Terkait:** Cross-Phase (Phase-3 POS / Phase-5 Dashboard)
- **Dampak:** Frontend | Controller | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Pembaruan besar pada antarmuka dan pengalaman operasional:
  1. Penerapan design token semantik global (primary/success/warning/destructive) dan font aplikasi.
  2. Penambahan komponen reusable baru: `CashierTopbar`, `DatePicker`, dan composable `useFlash`.
  3. Refactor komponen shared dan halaman owner/cashier agar konsisten dengan sistem tema baru.
  4. Guard backend alur kas: owner tidak lagi diwajibkan membuka sesi kas dan tidak dapat mengelola cash drawer kasir.
  5. Penyegaran halaman login untuk UX lebih modern dan konsisten dengan tema aplikasi.
- **Alasan:** Menyatukan visual language aplikasi, mengurangi inkonsistensi UI lintas halaman, memperjelas pemisahan alur owner vs kasir, dan meningkatkan kecepatan operasional kasir.
- **File Terdampak:**
  - `resources/css/app.css` — token warna semantik, radius, mapping warna tema
  - `resources/views/app.blade.php` — preload font Plus Jakarta Sans + JetBrains Mono
  - `resources/js/Components/CashierTopbar.vue` — topbar kasir reusable
  - `resources/js/Components/DatePicker.vue` — date picker custom untuk filter laporan
  - `resources/js/composables/useFlash.js` — state notifikasi global
  - `resources/js/Components/*.vue` — migrasi style komponen shared ke token semantik
  - `resources/js/Pages/Cashier/*.vue` — perbaikan flow kasir, notifikasi, dan wording
  - `resources/js/Pages/Owner/**/*.vue` — harmonisasi UI dashboard/management owner
  - `resources/js/Pages/Auth/Login.vue` — redesign halaman login
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — pembatasan sesi kas untuk role kasir
  - `app/Http/Controllers/Cashier/POSController.php` — owner bypass kewajiban buka kas
- **Catatan Migrasi:** Tidak ada migrasi database. Disarankan jalankan `npm run build` untuk validasi aset frontend.

---

### [ADDITION] Mobile API Phase 2: Operasi Kasir Lengkap
- **Tanggal:** 2026-05-25
- **Fase Terkait:** Phase-2 (Mobile API Enhancement)
- **Dampak:** Controller | Route | API Contract
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan endpoint mobile untuk operasional kasir harian yang lebih lengkap, meliputi buka kas, tutup kas, ringkasan sesi kas, daftar transaksi, pembayaran open bill, dan void transaksi.
- **Alasan:** Melengkapi alur operasional kasir mobile end-to-end agar proses shift kas dan lifecycle transaksi dapat ditangani penuh dari mobile client.
- **File Terdampak:**
  - `app/Http/Controllers/Api/Mobile/MobileCashDrawerController.php` — tambah method `open`, `close`, dan `summary` dengan validasi serta perhitungan expected amount/difference.
  - `app/Http/Controllers/Api/Mobile/MobileTransactionController.php` — tambah method `index`, `pay`, dan `void` untuk list transaksi, pembayaran open bill, serta pembatalan transaksi.
  - `routes/api.php` — registrasi route baru untuk operasi kas drawer dan transaksi (group role cashier/owner dan owner-only void).
- **Catatan Migrasi:** Tidak ada migrasi database baru. Pastikan role middleware `role:cashier,owner` dan `role:owner` aktif serta payment method cash memiliki `type = cash` untuk kalkulasi tutup kas.

---

### [ADDITION] UX POS: Auto-Amount Non-Cash, Thermal Receipt, Open Bill Customer Name
- **Tanggal:** 2026-03-07
- **Fase Terkait:** Phase-3 POS / Phase-5 UX
- **Dampak:** Frontend | Controller | Request
- **Breaking Change:** Tidak
- **Deskripsi:** Tiga improvement UX pada alur kasir:
  1. **PaymentModal** — metode non-cash (QRIS/Transfer) kini otomatis mengisi nominal sesuai total transaksi tanpa perlu input manual. Metode cash tetap menggunakan input nominal + tombol denominasi cepat. Kembalian hanya muncul jika ada metode cash.
  2. **ReceiptModal** — desain ulang struk cetak menjadi format thermal 80mm standar, menampilkan nama perusahaan (tenant), info transaksi (no., tanggal, waktu, kasir, pelanggan), item + modifier, subtotal, total, pembayaran per metode, kembali, dan footer.
  3. **Open Bill** — tambah modal input nama pelanggan saat menekan "Open Bill". Nama pelanggan ditampilkan pada kartu open bill di sidebar dan tercatat di struk.
- **Alasan:** Meningkatkan kecepatan kasir (tidak perlu ketik nominal QRIS), struk lebih profesional dan siap cetak ke printer thermal, serta mempermudah identifikasi pesanan open bill per pelanggan/meja.
- **File Terdampak:**
  - `resources/js/Components/PaymentModal.vue` — smart cash vs non-cash input, `onMethodChange` auto-amount, kembalian conditional
  - `resources/js/Components/ReceiptModal.vue` — full redesign thermal receipt, prop `tenantName`, format mono-font, section header/info/items/total/payment/footer
  - `resources/js/Pages/Cashier/POS.vue` — prop `tenantName`, modal input nama pelanggan untuk open bill (`showOpenBillNameModal`, `openBillCustomerName`, `confirmSaveOpenBill`), tampilkan nama di kartu open bill
  - `app/Http/Controllers/Cashier/POSController.php` — pass `tenantName` ke Inertia, load relasi `user:id,name` pada `lastTransaction` sebelum flash
  - `app/Http/Requests/StoreTransactionRequest.php` — tambah rule `customer_name: nullable|string|max:100`
- **Catatan Migrasi:** Tidak perlu migrasi. Kolom `customer_name` sudah ada di tabel `transactions`.

---

### [ADDITION] Dashboard Owner: Badge Self Order pada Transaksi Terbaru
- **Tanggal:** 2026-03-07
- **Fase Terkait:** Phase-5 Dashboard
- **Dampak:** Frontend | Controller
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan kolom `source` pada query `recentTransactions` di DashboardController dan badge "Self Order" (bg-blue) di samping kode transaksi pada tabel Transaksi Terbaru di halaman Dashboard Owner.
- **Alasan:** Membedakan visually transaksi dari channel POS vs Self Order langsung dari dashboard.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/DashboardController.php` — tambah `'source'` di `get([...])`
  - `resources/js/Pages/Owner/Dashboard.vue` — wrap `tx.code` + `<span v-if="tx.source === 'self_order'>` dalam flex div

---


- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase
- **Dampak:** Dependency | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan package `barryvdh/laravel-ide-helper` (sebagai dependensi dev) untuk mengatasi issue property & magic method Laravel yang sering dianggap error oleh IDE/Editor (VS Code, PhpStorm).
- **Alasan:** Meningkatkan DX (Developer Experience) agar editor dapat mengenali model properti, facades, dan magic method Laravel lainnya dengan tepat.
- **File Terdampak:**
  - `composer.json` — Tambahan `barryvdh/laravel-ide-helper` di require-dev.
  - `.gitignore` — Ignore file hasil generate (`_ide_helper.php`, `.phpstorm.meta.php`, `_ide_helper_models.php`).
- **Catatan Migrasi:** Jika IDE masih complain, jalankan perintah `php artisan ide-helper:generate`, `php artisan ide-helper:meta`, dan `php artisan ide-helper:models -N`.

---

### [ADDITION] Integrasi Dependency Xendit SDK (Persiapan Payment Gateway)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase
- **Dampak:** Dependency | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan package `xendit/xendit-php` ke dependency aplikasi dan konfigurasi service `xendit` di `config/services.php` (`secret_key`, `webhook_token`).
- **Alasan:** Persiapan integrasi payment gateway Xendit untuk kebutuhan pembayaran digital dan webhook settlement.
- **File Terdampak:**
  - `composer.json` — tambah dependency `xendit/xendit-php`
  - `composer.lock` — lock file update setelah install package
  - `config/services.php` — tambah config `xendit.secret_key` dan `xendit.webhook_token`
- **Catatan Migrasi:** Tambahkan env `XENDIT_SECRET_KEY` dan `XENDIT_WEBHOOK_TOKEN` pada environment yang digunakan.

---

### [RECONCILE] Transaction Status Naming
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Cross-Phase (Phase-1 migration, Phase-3 logic)
- **Dampak:** Migration | Model | Service
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Status transaksi diubah dari v1.0 naming ke naming yang lebih jelas
- **Alasan:** `open/paid/cancelled` kurang deskriptif. `pending/completed/voided` lebih konsisten dengan industry standard dan lebih jelas untuk developer baru.
- **Perubahan:**
  | v1.0 (lama) | Final (baru) | Keterangan |
  |---|---|---|
  | `open` | `pending` | Transaksi dibuat, belum dibayar |
  | `paid` | `completed` | Pembayaran diterima, stok sudah dikurangi |
  | `cancelled` | `voided` | Transaksi dibatalkan |
- **File Terdampak:**
  - `database/migrations/xxxx_create_transactions_table.php` — ENUM values
  - `app/Models/Transaction.php` — status constants
  - `app/Services/TransactionService.php` — status transitions

---

### [RECONCILE] Stock Movement Type Naming
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Cross-Phase (Phase-1 migration, Phase-4 logic)
- **Dampak:** Migration | Model | Service
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Tipe stock movement diubah dari generic ke deskriptif
- **Alasan:** `in/out` terlalu generik, tidak langsung menjelaskan konteks bisnis. `sale/restock/adjustment` langsung menjelaskan alasan perubahan stok.
- **Perubahan:**
  | v1.0 (lama) | Final (baru) | Keterangan |
  |---|---|---|
  | `in` | `restock` | Barang masuk dari supplier/restock |
  | `out` | `sale` | Stok keluar karena transaksi penjualan |
  | `adjustment` | `adjustment` | Koreksi manual (positif/negatif) |
- **File Terdampak:**
  - `database/migrations/xxxx_create_stock_movements_table.php` — ENUM values
  - `app/Models/StockMovement.php` — type constants
  - `app/Services/StockService.php` — movement type logic

---

### [DECISION] Auth Method: Manual → Laravel Sanctum (SPA Mode)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Config | Middleware | Controller
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Mekanisme auth diubah dari full manual ke Laravel Sanctum SPA mode
- **Alasan:** Sanctum SPA mode tetap cookie-based (tidak perlu manage token), sudah terintegrasi dengan Laravel, dan kompatibel sempurna dengan Inertia.js. Lebih secure (CSRF protection built-in) tanpa overhead tambahan dibanding manual auth.
- **Implementasi:**
  - Install: `composer require laravel/sanctum` (sudah included di Laravel 12)
  - Config: `config/sanctum.php` — set stateful domains
  - Middleware: Sanctum middleware di `api` routes jika dibutuhkan nanti
  - Auth flow tetap session-based, Sanctum hanya menambah layer keamanan
- **File Terdampak:**
  - `config/sanctum.php` — konfigurasi
  - `app/Http/Controllers/Auth/AuthController.php` — auth logic
  - `bootstrap/app.php` — middleware registration

---

### [SCHEMA] Penambahan Tabel `cash_drawers`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation (migration), Phase-3 POS (logic)
- **Dampak:** Migration | Model | Controller | Service
- **Breaking Change:** Tidak (tabel baru)
- **Deskripsi:** Tabel baru untuk sesi kas per shift kasir, sesuai v1.1 Section 1
- **Detail:** Lihat `docs/phases/PHASE-1_Foundation.md` — Section Database

---

### [SCHEMA] Penambahan Kolom `expiry_date` di `product_variants`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation (migration), Phase-5 Badges (logic)
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** Kolom expiry_date untuk mendukung badge "Potensi Expired", sesuai v1.1 Section 2

---

### [SCHEMA] Penambahan `deleted_at` (SoftDeletes) di 6 Tabel
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** SoftDeletes ditambahkan ke: `categories`, `products`, `product_variants`, `modifier_groups`, `modifiers`, `payment_methods`. Sesuai v1.1 Section 7.
- **Alasan:** Menjaga integritas data historis transaksi. Record yang dihapus user tidak benar-benar hilang dari database.

---

### [DECISION] `expected_amount` Cash Drawer = Semua Payment Method
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Service
- **Breaking Change:** Tidak
- **Deskripsi:** `expected_amount` di `cash_drawers` dihitung dari **semua** metode pembayaran (cash + QRIS + transfer), bukan hanya cash.
- **Alasan:** Keputusan bisnis — owner ingin melihat total expected revenue di sesi tersebut, bukan hanya uang fisik.
- **Catatan:** Di summary tutup kas, tetap tampilkan rekap per metode pembayaran agar owner bisa reconcile masing-masing channel.

---

### [ADDITION] Tenant Isolation via Global Scope + Trait
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Model | Trait
- **Breaking Change:** Tidak
- **Deskripsi:** Implementasi `BelongsToTenant` trait + `TenantScope` global scope untuk auto-filter & auto-assign tenant_id. Sesuai v1.1 Section 6.

---

### [ADDITION] Image Upload Service dengan Konversi WEBP
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-2 Master Data CRUD
- **Dampak:** Service | Config | Dependency
- **Breaking Change:** Tidak
- **Deskripsi:** ImageService untuk upload & konversi gambar produk ke WEBP. Dependency: `intervention/image`. Sesuai v1.1 Section 8.

---

### [ADDITION] Flash `lastTransaction` via Inertia untuk Receipt Modal
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Middleware | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `flash.lastTransaction` di `HandleInertiaRequests` middleware agar data transaksi terakhir yang berhasil bisa dikirim ke frontend untuk ditampilkan di ReceiptModal (struk). Phase 3 doc tidak secara eksplisit mendefinisikan mekanisme passing data transaksi ke receipt.
- **Alasan:** Inertia redirect-back tidak bisa mengirim data object secara langsung. Flash session digunakan sebagai bridge untuk menampilkan struk setelah checkout sukses.
- **File Terdampak:**
  - `app/Http/Middleware/HandleInertiaRequests.php` — tambah `lastTransaction` di flash share
  - `app/Http/Controllers/Cashier/POSController.php` — `->with('lastTransaction', ...)` di store method

---

### [ADDITION] Redirect Tutup Kas ke Summary Page
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Route
- **Breaking Change:** Tidak
- **Deskripsi:** Setelah tutup kas, kasir langsung di-redirect ke halaman `CashDrawerSummary` (rekap sesi), bukan kembali ke halaman `CashDrawer.index`. Phase 3 doc menunjukkan redirect ke `cash-drawer.index`.
- **Alasan:** UX lebih baik — kasir langsung melihat rekap sesi lengkap (per payment method, selisih) setelah tutup kas.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `close()` redirect ke `cash-drawer.summary`

---

### [ADDITION] Transaction Count di Cash Drawer Summary
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `transactionCount` (jumlah transaksi completed dalam sesi) ke data yang dikirim ke `CashDrawerSummary.vue`. Tidak ada di Phase 3 doc.
- **Alasan:** Informasi tambahan yang berguna untuk rekap shift kasir.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `summary()` menghitung dan mengirim `transactionCount`

---

### [ADDITION] Dependency chart.js + vue-chartjs untuk Dashboard Chart
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `chart.js` dan `vue-chartjs` sebagai dependency npm untuk menampilkan bar chart trend pendapatan 7 hari di halaman Dashboard owner. Phase 5 doc menyebutkan chart sebagai "opsional, bisa pakai chart.js" tanpa mendefinisikan dependency secara eksplisit.
- **Alasan:** Dipilih atas permintaan user. Chart.js + vue-chartjs ringan dan memberikan visualisasi data yang interaktif.
- **File Terdampak:**
  - `package.json` — tambah dependency `chart.js`, `vue-chartjs`
  - `resources/js/Components/DailyChart.vue` — komponen chart baru

---

### [ADDITION] Navigasi Sidebar Owner Ditambah Menu Phase 5
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan 3 menu navigasi baru di sidebar OwnerLayout: Laporan Harian, Transaksi, Sesi Kas. Serta menambahkan 3 ikon SVG baru (report, receipt, cash) untuk navigasi tersebut. Phase 5 doc tidak secara eksplisit mendefinisikan perubahan pada layout/navigasi.
- **Alasan:** Halaman-halaman baru Phase 5 perlu bisa diakses via navigasi sidebar agar UX konsisten dengan halaman owner lainnya.
- **File Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue` — tambah navigation items + ikon SVG

---

### [HOTFIX] BadgeHelperService Dead Stock Query Bug
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (ditemukan saat testing)
- **Dampak:** Service
- **Breaking Change:** Tidak
- **Deskripsi:** Query dead stock badge menggunakan `transactionItems.created_at` yang tidak ada (tabel `transaction_items` memiliki `$timestamps = false`). Diperbaiki dengan query melalui relasi `transactionItems.transaction` dan filter berdasarkan `transactions.created_at` serta `transactions.status = completed`.
- **Alasan:** Bug — query selalu gagal karena kolom `created_at` tidak ada di tabel `transaction_items`, sehingga badge dead stock tidak pernah akurat.
- **File Terdampak:**
  - `app/Services/BadgeHelperService.php` — perbaikan `whereDoesntHave` query dari `transactionItems` ke `transactionItems.transaction`

---

### [HOTFIX] CategoryController Soft Delete Tidak Nullify product.category_id
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (ditemukan saat testing)
- **Dampak:** Controller
- **Breaking Change:** Tidak
- **Deskripsi:** Komentar di controller mengklaim `nullOnDelete` DB constraint akan handle nullify `category_id` produk saat kategori dihapus. Namun soft delete tidak memicu DB-level foreign key constraint. Diperbaiki dengan menambahkan manual `$category->products()->update(['category_id' => null])` sebelum soft delete.
- **Alasan:** Bug — produk tetap mereferensi kategori yang sudah di-soft-delete, menyebabkan data inkonsisten.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/CategoryController.php` — tambah manual nullify sebelum `$category->delete()`

---

### [SCHEMA] Penambahan 'void' di ENUM stock_movements.type (Create Migration)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (fix untuk SQLite test compatibility)
- **Dampak:** Migration
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `'void'` ke ENUM di migration create `stock_movements` agar SQLite CHECK constraint mengizinkan tipe void. Migration ALTER tetap ada untuk MySQL production yang sudah menjalankan migration create sebelumnya.
- **Alasan:** SQLite (test database) membuat CHECK constraint dari ENUM. Migration ALTER yang skip SQLite menyebabkan 'void' tidak dikenal di test environment.
- **File Terdampak:**
  - `database/migrations/2026_03_06_000015_create_stock_movements_table.php` — ENUM values ditambah `'void'`
  - `database/migrations/2026_03_06_100002_add_void_type_to_stock_movements.php` — tetap ada driver-aware skip untuk SQLite

---

### [ADDITION] HasFactory Trait pada Semua Model
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA
- **Dampak:** Model
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `HasFactory` trait ke semua model yang belum memilikinya: `Transaction`, `CashDrawer`, `StockMovement`, `Tenant`, `Category`, `Product`, `ProductVariant`, `ModifierGroup`, `Modifier`, `PaymentMethod`, `TransactionPayment`. Diperlukan agar `Model::factory()` dapat digunakan di test suite.
- **Alasan:** Prasyarat untuk factory-based testing di Pest PHP.
- **File Terdampak:**
  - `app/Models/Transaction.php` — tambah `use HasFactory`
  - `app/Models/CashDrawer.php` — tambah `use HasFactory`
  - `app/Models/StockMovement.php` — tambah `use HasFactory`
  - (dan 8 model lain yang sudah ditambahkan sebelumnya)

---

### [ADDITION] Fitur Open Bill (Simpan Pesanan Tanpa Bayar)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Service | Route | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Kasir kini bisa menyimpan pesanan sebagai "Open Bill" (status `pending`) tanpa langsung membayar. Stok dikurangi saat bill dibuat untuk mencegah overselling. Pembayaran dilakukan kemudian dari panel Open Bill di halaman POS. Phase 3 doc tidak mendefinisikan fitur open bill.
- **Alasan:** Permintaan user — di banyak skenario F&B, pelanggan pesan dulu dan bayar belakangan (makan di tempat, tab, dll). Stok tetap dikurangi di awal agar tidak terjual ganda.
- **File Terdampak:**
  - `app/Services/TransactionService.php` — `checkout()` menerima flag `is_open_bill`; method baru `payOpenBill()` untuk menyelesaikan pembayaran
  - `app/Http/Requests/StoreTransactionRequest.php` — validasi `payments` jadi nullable + field `is_open_bill`
  - `app/Http/Controllers/Cashier/POSController.php` — method `payOpenBill()`, load `openBills` untuk frontend
  - `routes/web.php` — route `POST /cashier/transactions/{transaction}/pay`
  - `resources/js/Pages/Cashier/POS.vue` — panel Open Bill, tombol "Open Bill", flow pembayaran pending

---

### [SCHEMA] Penambahan Kolom `notes` di `transaction_items`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** Kolom `notes` (text, nullable) ditambahkan ke tabel `transaction_items` untuk menyimpan catatan per item pesanan (contoh: "less sugar", "no ice"). Tidak ada di Phase 3 doc.
- **Alasan:** Permintaan user — support catatan khusus per item pesanan, umum di bisnis F&B.
- **File Terdampak:**
  - `database/migrations/2026_03_06_200001_add_notes_to_transaction_items_table.php` — migration baru
  - `app/Models/TransactionItem.php` — `notes` ditambahkan ke `$fillable`
- **Catatan Migrasi:** Jalankan `php artisan migrate` untuk menambah kolom.

---

### [ADDITION] Catatan (Notes) per Item di Halaman POS
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Frontend | Service
- **Breaking Change:** Tidak
- **Deskripsi:** Setiap item di cart kini bisa memiliki catatan individual. Item dengan produk & modifier sama tapi catatan berbeda ditampilkan sebagai baris terpisah di cart. Catatan ditampilkan di struk (ReceiptModal). Phase 3 doc tidak mendefinisikan fitur notes.
- **Alasan:** Permintaan user — catatan per item umum di POS F&B untuk instruksi khusus dapur/barista.
- **File Terdampak:**
  - `resources/js/Components/CartItem.vue` — toggle notes + input text per item
  - `resources/js/Pages/Cashier/POS.vue` — dedup cart mempertimbangkan notes, handler `updateCartNotes()`
  - `resources/js/Components/ReceiptModal.vue` — tampilkan notes item di struk
  - `app/Services/TransactionService.php` — simpan `notes` per item ke database

---

### [ADDITION] Halaman Riwayat Transaksi Kasir
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Route | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Halaman baru `Cashier/TransactionHistory` untuk kasir melihat riwayat transaksi mereka sendiri, dengan filter status dan tanggal, serta tombol cetak ulang struk. Phase 3 doc tidak mendefinisikan halaman history khusus untuk kasir.
- **Alasan:** Permintaan user — kasir perlu bisa melihat & mencetak ulang struk transaksi sebelumnya tanpa harus minta akses owner.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/POSController.php` — method `history()` dengan filter & pagination
  - `routes/web.php` — route `GET /cashier/transactions`
  - `resources/js/Pages/Cashier/TransactionHistory.vue` — halaman baru
  - `resources/js/Pages/Cashier/POS.vue` — link "Riwayat" di top bar

---

### [DECISION] Reversal: `expected_amount` Cash Drawer = Cash Only (Bukan Semua Payment)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Frontend
- **Breaking Change:** Ya (mengubah behavior kalkulasi expected_amount)
- **Deskripsi:** **Membalik keputusan sebelumnya.** `expected_amount` di `cash_drawers` kini dihitung hanya dari pembayaran tunai (cash) dikurangi kembalian, bukan dari semua metode pembayaran. Formula baru: `opening_amount + Σ(cash payments) - Σ(change given)`.
- **Alasan:** Permintaan user — `expected_amount` seharusnya merepresentasikan uang fisik yang diharapkan ada di laci kas. Pembayaran QRIS dan transfer tidak masuk ke laci kas fisik. Keputusan sebelumnya (`expected_amount = semua payment method`) dibatalkan.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `close()` query hanya `TransactionPayment` with `paymentMethod.type = 'cash'`, kurangi `change_amount`
  - `resources/js/Pages/Cashier/CashDrawerSummary.vue` — label diubah dari "Expected Amount" ke "Expected Cash (uang tunai di laci)"

---

### [ADDITION] Perbaikan Payment Modal (Lebar, Sticky Total, Denominasi, Format Angka)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Beberapa perbaikan UX pada PaymentModal: (1) Modal diperlebar dari `max-w-md` ke `max-w-lg`, (2) Total Belanja menjadi sticky headline yang selalu terlihat saat scroll, (3) Tombol denominasi: "Uang Pas" + "+20rb" + "+50rb" + "+100rb" yang kumulatif, (4) Input amount menggunakan `type="text" inputmode="numeric"` dengan auto-format ribuan (titik).
- **Alasan:** Permintaan user — modal terlalu kecil, total tidak terlihat saat scroll, dan kasir butuh shortcut denominasi untuk mempercepat proses pembayaran.
- **File Terdampak:**
  - `resources/js/Components/PaymentModal.vue` — semua perubahan di atas

---

### [ADDITION] Sidebar Owner Grouped dengan Dropdown Collapsible
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Navigasi sidebar OwnerLayout diubah dari flat list ke grouped sections: Dashboard (standalone), "Atur Menu" (Kategori, Produk, Stok, Modifier), "Keuangan" (Laporan Harian, Transaksi, Sesi Kas, Pembayaran). Setiap group memiliki dropdown collapsible dengan chevron animasi. Group label ter-highlight saat salah satu child-nya aktif. Juga memperbaiki issue sidebar yang memiliki empty space di bawah yang bisa di-scroll.
- **Alasan:** Permintaan user — navigasi flat terlalu panjang dan sulit dinavigasi. Grouping membantu organisasi menu berdasarkan domain bisnis. Perbaikan scroll menghilangkan empty space yang tidak perlu.
- **File Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue` — rewrite navigasi dari flat array ke `sidebarGroups` array, collapsible dropdown per group, `overflow-y-auto` hanya pada nav

---

### [SCHEMA] Penambahan Kolom Self-Order & Fulfillment di `transactions`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase (Self Order Feature)
- **Dampak:** Migration | Model | Service | Controller | Route | Config
- **Breaking Change:** Tidak (kolom baru, nullable/default)
- **Deskripsi:** Menambahkan 5 kolom baru ke tabel `transactions` untuk mendukung fitur Self Order via Telegram + Xendit payment dan sistem fulfillment tracking:
  - `source` (enum: pos, self_order) — asal pesanan
  - `order_type` (enum: dine_in, pickup) — tipe pesanan
  - `fulfillment_status` (enum nullable: waiting, preparing, ready, done) — status penyajian
  - `customer_name` (varchar 100, nullable) — nama customer terstruktur
  - `table_number` (varchar 10, nullable) — nomor meja free text
- **Alasan:** Mendukung multi-channel order (POS + Telegram) dengan tracking status penyajian per pesanan. Fulfillment dipisahkan dari payment status untuk fleksibilitas (bayar dulu / sajikan dulu).
- **File Terdampak:**
  - `database/migrations/2026_03_06_300001_add_selforder_and_fulfillment_to_transactions.php` — migration baru
  - `app/Models/Transaction.php` — constants baru (SOURCE_*, ORDER_TYPE_*, FULFILLMENT_*), $fillable, helper methods (isSelfOrder, hasFulfillmentTracking, advanceFulfillment)
  - `database/factories/TransactionFactory.php` — state methods baru (selfOrder, withFulfillment, pickup)
- **Catatan Migrasi:** Jalankan `php artisan migrate`

---

### [ADDITION] API Layer untuk Self Order (Sanctum + Xendit)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase (Self Order Feature)
- **Dampak:** Controller | Route | Config | Service
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan REST API layer untuk integrasi Self Order via n8n/Telegram:
  - `GET /api/products` — daftar produk aktif + variant tersedia (Sanctum auth)
  - `POST /api/orders` — buat self-order + generate Xendit Invoice (Sanctum auth)
  - `PATCH /api/orders/{transaction}/fulfillment` — advance fulfillment status (Sanctum auth)
  - `POST /api/xendit/webhook` — handle Xendit payment callback (token auth)
- **Alasan:** Dibutuhkan sebagai backend untuk n8n workflow yang menerima order dari Telegram bot.
- **File Terdampak:**
  - `routes/api.php` — file baru, semua API routes
  - `bootstrap/app.php` — registrasi api routes
  - `app/Http/Controllers/Api/ApiProductController.php` — controller baru
  - `app/Http/Controllers/Api/ApiOrderController.php` — controller baru
  - `app/Http/Controllers/Api/XenditWebhookController.php` — controller baru

---

### [DECISION] Self-Order: Stok Tidak Dikurangi Sebelum Bayar
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase (Self Order Feature)
- **Dampak:** Service
- **Breaking Change:** Tidak
- **Deskripsi:** Untuk self-order, stok **TIDAK** dikurangi saat order dibuat. Stok baru dikurangi setelah Xendit webhook mengkonfirmasi pembayaran (status PAID). Jika invoice expired (30 menit), transaksi langsung di-void tanpa perlu restore stok.
- **Alasan:** Mencegah order fiktif / tidak dibayar yang mengurangi stok. Berbeda dengan POS (stok langsung dikurangi) dan open bill POS (stok langsung dikurangi karena customer sudah di lokasi).
- **File Terdampak:**
  - `app/Services/TransactionService.php` — method baru: `createSelfOrder()` (tanpa deduct), `confirmSelfOrderPayment()` (deduct setelah bayar), `voidExpiredSelfOrder()` (void tanpa restore). Refactor `processItems()` sebagai shared method dengan flag `deductStock`.

---

### [DECISION] Fulfillment Tracking: Opsional, Terpisah dari Payment Status
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase (Self Order Feature)
- **Dampak:** Model | Service
- **Breaking Change:** Tidak
- **Deskripsi:** Fulfillment tracking (`waiting → preparing → ready → done`) terpisah dari payment status (`pending → completed → voided`). Fulfillment = `null` untuk POS bayar langsung (tidak perlu tracking), `waiting` untuk open bill dan self-order (setelah bayar). Kasir + Owner yang manage fulfillment (tanpa role kitchen baru). Customer diberitahu secara manual (panggil nama).
- **Alasan:** POS bayar langsung di counter tidak perlu tracking penyajian. Fulfillment hanya relevan untuk pesanan yang butuh waktu (open bill, self-order).

---

## Template Entry Kosong (Copy-Paste)

```markdown
### [TIPE] Judul Singkat
- **Tanggal:** YYYY-MM-DD
- **Fase Terkait:** Phase-X / Di Luar Fase / Cross-Phase
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config
- **Breaking Change:** Ya / Tidak
- **Deskripsi:** 
- **Alasan:** 
- **File Terdampak:**
  - `path/to/file` — deskripsi
- **Catatan Migrasi:** (opsional)
```
