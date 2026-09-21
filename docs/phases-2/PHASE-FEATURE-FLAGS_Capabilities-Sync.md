# PHASE FEATURE-FLAGS — Fondasi Capability Flags & Gerbang Tersinkron

**Status:** ✅ Selesai 2026-07-29 — lihat `[ADDITION] Fondasi Capability Flags & Gerbang Tersinkron` di `docs/CHANGELOG.md`
**Ditulis ulang:** 2026-07-28 (lihat [Riwayat Revisi](#riwayat-revisi))
**Dependency:** Tidak ada. **Fase ini fondasi**, dan `PHASE-QUEUE` bergantung padanya — bukan sebaliknya.
**Output:** Satu sumber kebenaran `Tenant::hasFeature()`, dua middleware gerbang, dan gerbang terpasang di **setiap** permukaan yang sudah ada
**Dipakai oleh:** Owner (kontrol via Settings), semua consumer (kasir, n8n/Telegram, mobile, MCP client)

> Tujuan dokumen ini: saat sebuah fitur di-toggle untuk satu tenant, **semua pintu masuk ikut patuh** — tidak ada yang bocor. Ia tidak menambah fitur; ia memasang gerbang di tiap permukaan, semuanya memanggil satu helper.

---

## Riwayat Revisi

**Versi 2026-07 (asli).** Ditulis sebagai pelengkap `PHASE-QUEUE`, menitipkan fondasi flag ke Bagian 0 dokumen tersebut.

**Versi 2026-07-28 (dokumen ini).** Ditulis ulang bersamaan dengan penulisan ulang `PHASE-QUEUE` (lihat `[BL-019]` di `docs/BACKLOG.md`). Empat hal berubah, dan tiga di antaranya karena isi versi lama **sudah tidak cocok dengan kode**:

| Versi asli | Sekarang | Alasan |
|---|---|---|
| Fondasi flag menumpang "PHASE-QUEUE Bagian 0" | **Fondasi pindah ke sini**, jadi Bagian 1 | Tiga fitur mengantre di fondasi yang sama; rujukan silang dua arah membuat keduanya tak bisa dibaca sendiri |
| Blok alias memuat `tenant`, `tenant.api`, `role` | **Dikoreksi total** | `tenant`/`tenant.api` adalah **grup**, bukan alias, dan blok lama menghapus 4 alias yang sudah terdaftar |
| MCP disebut "masih kerangka, tool belum diregistrasi" | **MCP sudah hidup penuh** | `routes/ai.php` sudah me-route `Mcp::web()`, tiga tool sudah terdaftar, token MCP sudah bisa diterbitkan dari Settings — gerbangnya dibutuhkan **sekarang**, bukan nanti |
| Validasi Settings memuat `business_type` | **Dicabut** | Kolom itu milik penetapan harga (`[BL-015]`); menoggle-nya dari Settings mengubah dasar tagihan |

Dua hal yang **tidak** ada di versi lama dan ditambahkan di sini: perangkap `$attributes` pada model `Tenant` ([1c](#1c-jebakan-attributes--wajib-dibaca)), dan keharusan **backfill** `self_order_enabled` ([1a](#1a-migrasi)).

---

## Konteks — peta pintu masuk

Satu fitur bisa dicapai lewat pintu yang mekanisme autentikasinya berbeda-beda. Gerbang di satu pintu tidak menutup pintu lain. Peta yang **terverifikasi terhadap kode hari ini**:

| Fitur | Pintu masuk | Berkas | Auth |
|---|---|---|---|
| **Self-Order** | `POST /api/v1/orders`, `PATCH /api/v1/orders/{tx}/fulfillment`, `POST /api/v1/upsell/suggestions` | `routes/api.php:31-38` | `auth:sanctum` **saja** |
| **AI Analysis** | `ai-analysis.index/store/show` | `routes/web.php:146-153` | `auth` + `tenant` + `permission:ai_analysis` |
| **AI (eksekusi)** | `RunAiAnalysisJob@handle` | `app/Jobs/RunAiAnalysisJob.php:26` | **tanpa middleware** — worker |
| **MCP** | `Mcp::web('/mcp/business')` | `routes/ai.php:14` | `auth:sanctum` + `tenant.api` + `role:owner` + `throttle:mcp` |
| **Kitchen Queue** | `cashier.queue.*` | belum ada — lihat `PHASE-QUEUE` | `auth` + `tenant` + `role:cashier,owner` |
| **Mobile POS** | `/api/v1/mobile/*` | `routes/api.php:50-71` | `auth:sanctum` + `tenant.api` (+`role`) |

### Temuan yang membentuk desain

- **Alias yang sudah terdaftar** di `bootstrap/app.php:47` ada lima: `role`, `permission`, `permission.api`, `platform.can`, `platform.owner`. Sementara `tenant` dan `tenant.api` sengaja dibuat **grup** (`:31` dan `:37`) — komentar di `:23` menjelaskan kenapa: gerbang langganan harus ikut di setiap rute bertenant, dan menuliskannya satu per satu berarti grup berikutnya pasti melupakannya. **Jangan daftarkan keduanya sebagai alias.**
- **Respons berbeda per permukaan.** Web butuh redirect/abort; API, MCP, dan mobile butuh JSON yang bisa dibaca mesin. Jadi butuh dua kelas middleware, bukan satu.
- **`RunAiAnalysisJob` tak lewat HTTP**, jadi middleware apa pun tak berlaku. Flag wajib dicek **di dalam** job.
- **MCP sudah beroperasi**, bukan rencana. `SapiBusinessServer` mendaftarkan `GetSalesSummaryTool`, `GetProfitTool`, `GetMenuTool`; `SettingsController` sudah bisa menerbitkan dan mencabut token `mcp-client`. Artinya hari ini **tidak ada satu pun cara mematikan akses MCP selain mencabut tokennya**.
- **Kuota AI sudah ada** (`assertQuota`, `RunAiAnalysisJob:72`). Flag adalah lapis **berbeda maksud**: kuota menjawab "berapa banyak", flag menjawab "boleh atau tidak". Urutannya: flag dulu, kuota kemudian.
- **Grup self-order tidak memakai `tenant.api`** — hanya `auth:sanctum`. Konsekuensinya dicatat sebagai temuan di [Bagian 10](#10-keputusan-terbuka), karena ia menyentuh gerbang lain di luar lingkup dokumen ini.

### Prinsip WAJIB

- **Satu sumber kebenaran:** hanya `Tenant::hasFeature()` yang memutuskan. Tidak ada `->ai_enabled` tersebar manual di controller.
- **Gagal tertutup.** Nama fitur yang tidak dikenal menjawab `false`, bukan `true`. Salah ketik harus menutup pintu, bukan membukanya.
- **Defense-in-depth untuk jalur tanpa middleware** (job, tool MCP): cek ulang saat eksekusi.
- **Default menjaga status quo.** Fitur yang sudah berjalan hari ini tidak boleh mati pada hari rilis — lihat [1a](#1a-migrasi).
- **Respons sesuai permukaan:** web abort, API/MCP/mobile JSON berkode, supaya n8n bisa membalas pelanggan dengan kalimat yang benar.
- **Pint** setelah edit PHP; setiap perubahan **diuji** (Pest).

---

## Daftar Isi

1. [Fondasi](#1-fondasi)
2. [Dua middleware gerbang](#2-dua-middleware-gerbang)
3. [Gating Self-Order](#3-gating-self-order)
4. [Gating AI](#4-gating-ai)
5. [Gating Kitchen Queue](#5-gating-kitchen-queue)
6. [Matriks Sinkronisasi](#6-matriks-sinkronisasi)
7. [Settings](#7-settings-owner)
8. [Tests](#8-tests-pest)
9. [Checklist](#9-checklist)
10. [Keputusan Terbuka](#10-keputusan-terbuka)

---

## 1. Fondasi

### 1a. Migrasi

```php
public function up(): void
{
    Schema::table('tenants', function (Blueprint $table) {
        $table->boolean('kitchen_queue_enabled')->default(false)->after('business_type');
        $table->boolean('self_order_enabled')->default(false)->after('kitchen_queue_enabled');
        $table->boolean('ai_enabled')->default(true)->after('self_order_enabled');
    });

    // Backfill WAJIB — bukan kerapian, tapi penjaga status quo.
    //
    // Self-order BUKAN fitur baru: ia sudah terpasang dan mungkin sedang
    // dipakai lewat n8n/Telegram. Membiarkannya `false` untuk baris yang sudah
    // ada berarti pada hari rilis setiap integrasi yang hidup menerima 403 dan
    // n8n mulai memberi tahu pelanggan bahwa pemesanan ditutup.
    //
    // Risikonya tidak setara: keliru `false` mematikan yang sedang bekerja;
    // keliru `true` tidak mengubah apa pun bagi tenant yang memang tak pernah
    // memakainya — tak ada UI yang berubah, endpointnya sekadar tak dipanggil.
    // Default `false` tetap berlaku untuk tenant yang mendaftar SETELAH ini.
    DB::table('tenants')->update(['self_order_enabled' => true]);
}
```

- **`ai_enabled` default `true`** dengan alasan yang sama: AI sudah berjalan hari ini.
- **`kitchen_queue_enabled` default `false`** tanpa backfill — belum ada apa pun yang memakainya.

### 1b. Model `Tenant`

```php
protected $fillable = [
    'name', 'slug', 'business_type', 'logo', 'address', 'phone', 'status', 'pricing_track',
    'signup_ip', 'flagged_at', 'flag_reason',
    'ai_provider', 'ai_api_key', 'ai_model',
    'kitchen_queue_enabled', 'self_order_enabled', 'ai_enabled',   // ← baru
];

protected function casts(): array
{
    return [
        'ai_api_key' => 'encrypted',
        'flagged_at' => 'datetime',
        'kitchen_queue_enabled' => 'boolean',   // ← baru
        'self_order_enabled' => 'boolean',      // ← baru
        'ai_enabled' => 'boolean',              // ← baru
    ];
}

/**
 * Apakah kapabilitas ini aktif untuk tenant tersebut.
 *
 * Satu-satunya pintu. Route, menu nav, controller, job, dan tool MCP semuanya
 * bertanya ke sini — sehingga menambah permukaan tidak pernah berarti menambah
 * logika, hanya menambah pemanggil.
 *
 * `default => false` disengaja: nama fitur yang salah ketik harus MENUTUP
 * pintu, bukan membukanya diam-diam.
 */
public function hasFeature(string $feature): bool
{
    return (bool) match ($feature) {
        'kitchen_queue' => $this->kitchen_queue_enabled,
        'self_order' => $this->self_order_enabled,
        'ai' => $this->ai_enabled,
        default => false,
    };
}
```

### 1c. Jebakan `$attributes` — WAJIB dibaca

Model `Tenant` punya blok `$attributes` (`app/Models/Tenant.php:45`) yang **mengembarkan default kolom dari migrasi**, dengan docblock yang menjelaskan sebabnya: tanpa itu, `canWrite()` pada tenant yang baru dibuat menjawab dari `null` — dan menjawab salah.

Persoalan yang sama persis berlaku untuk ketiga flag ini. `hasFeature()` yang dipanggil atas instance yang baru saja `create()` tanpa membaca ulang dari basis data akan menerima `null` dan menjawab `false` — termasuk untuk `ai_enabled` yang seharusnya `true`. Gejalanya paling sering muncul di test dan di alur pendaftaran.

```php
protected $attributes = [
    'status' => self::STATUS_TRIAL,
    'pricing_track' => Subscription::TRACK_NORMAL,
    'kitchen_queue_enabled' => false,   // ← baru
    'self_order_enabled' => false,      // ← baru
    'ai_enabled' => true,               // ← baru
];
```

---

## 2. Dua middleware gerbang

### 2a. Web — `feature`

```php
// app/Http/Middleware/EnsureTenantFeature.php (BARU)
class EnsureTenantFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $request->user()?->tenant?->hasFeature($feature)) {
            abort(403, 'Fitur ini tidak aktif untuk outlet Anda.');
        }

        return $next($request);
    }
}
```

### 2b. API / MCP / Mobile — `feature.api`

```php
// app/Http/Middleware/EnsureTenantFeatureApi.php (BARU)
class EnsureTenantFeatureApi
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $request->user()?->tenant?->hasFeature($feature)) {
            return response()->json([
                'success' => false,
                'code' => 'feature_disabled',
                'message' => "Fitur '{$feature}' sedang tidak aktif untuk outlet ini.",
            ], 403);
        }

        return $next($request);
    }
}
```

`code` yang stabil lebih penting daripada `message`: n8n mencocokkan kode, bukan kalimat, sehingga teksnya bisa diperbaiki tanpa merusak workflow.

### 2c. Pendaftaran alias — HATI-HATI

> Versi lama dokumen ini keliru di sini, dan kekeliruannya merusak. Blok di bawah adalah daftar **lengkap** setelah penambahan; salin utuh, jangan tulis ulang sebagian.

```php
// bootstrap/app.php — di dalam withMiddleware()
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureRole::class,
    'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
    'permission.api' => \App\Http\Middleware\EnsureModuleApi::class,
    'platform.can' => \App\Http\Middleware\EnsurePlatformModule::class,
    'platform.owner' => \App\Http\Middleware\EnsurePlatformOwner::class,
    'feature' => \App\Http\Middleware\EnsureTenantFeature::class,          // ← baru
    'feature.api' => \App\Http\Middleware\EnsureTenantFeatureApi::class,   // ← baru
]);
```

**`tenant` dan `tenant.api` TIDAK masuk daftar ini.** Keduanya grup middleware yang didefinisikan di atasnya (`:31`, `:37`) dan sengaja demikian. Mendaftarkannya ulang sebagai alias menciptakan dua hal bernama sama dengan isi berbeda — persis jenis kekacauan yang komentar di `:23` berusaha cegah.

---

## 3. Gating Self-Order

Bungkus grup Sanctum di `routes/api.php:31`. Perhatikan `/upsell/suggestions` — endpoint ini lahir setelah `[BL-017]` dan tidak ada di versi lama dokumen ini:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/products', [ApiProductController::class, 'index']);

    Route::middleware('feature.api:self_order')->group(function () {   // ← gerbang
        Route::post('/upsell/suggestions', [ApiUpsellController::class, 'suggestions'])
            ->middleware('throttle:120,1');
        Route::post('/orders', [ApiOrderController::class, 'store'])
            ->middleware('throttle:60,1');
        Route::patch('/orders/{transaction}/fulfillment', [ApiOrderController::class, 'updateFulfillment']);
    });
});
```

- **`/upsell/suggestions` ikut digerbang.** Ia hanya berguna untuk permukaan self-order, dan saat pemesanan mati ia tetap akan membocorkan barang mana yang sedang tertekan stoknya kepada pemegang token — informasi bisnis tanpa guna yang sepadan.
- **`/products` tetap di luar gerbang.** Katalog bukan pemesanan, dan endpoint yang sama dipakai jalur mobile.
- **n8n / Telegram** otomatis patuh karena melewati API di atas. Yang perlu dikerjakan di sisi sana: menangani `403 feature_disabled` dan membalas pelanggan ("Maaf, pemesanan online sedang tidak aktif") — bukan menampilkan galat mentah.
- **Web:** badge "Self Order" di dashboard **tetap tampil** meski flag mati. Itu data masa lalu; menyembunyikan riwayat karena fitur dimatikan hari ini adalah menghapus sejarah, bukan menggerbang fitur.

---

## 4. Gating AI

### 4a. Web — komposisi dengan `permission` yang sudah ada

Rute AI sudah digerbang `permission:ai_analysis` (`routes/web.php:146`). Flag **melengkapi**, tidak menggantikan: `permission` menjawab *"pengguna ini boleh?"*, `feature` menjawab *"outlet ini punya kapabilitasnya?"* — dua pertanyaan berbeda yang keduanya harus dijawab ya.

```php
// routes/web.php:146 — urutannya disengaja
Route::middleware(['feature:ai', 'permission:ai_analysis'])->group(function () {
    // ...tiga rute ai-analysis tetap apa adanya
});
```

Flag ditaruh **lebih dulu** supaya tenant yang fiturnya mati menerima "fitur tidak aktif untuk outlet Anda", bukan "Anda tidak punya izin" — pesan kedua akan mengirim owner memeriksa pengaturan Role, tempat yang salah sama sekali.

Menu "AI Analysis" di `OwnerLayout.vue:114` sudah bergantung `perm: 'ai_analysis'`; ia perlu syarat kedua dari flag tenant.

### 4b. Job — defense-in-depth (WAJIB)

`RunAiAnalysisJob@handle` berjalan tanpa middleware, dan job bisa sudah mengantre saat flag dimatikan. Cek **sebelum** kuota dan **sebelum** provider dipanggil:

```php
// app/Jobs/RunAiAnalysisJob.php — setelah $tenant didapat (:30), sebelum assertQuota (:42)
if (! $tenant->hasFeature('ai')) {
    $analysis->update([
        'status' => AiAnalysis::STATUS_FAILED,
        'error' => 'Fitur AI tidak aktif untuk outlet ini.',
    ]);

    return;   // provider tidak dipanggil, kuota tidak terpakai
}
```

Urutannya penting dan mudah terbalik: flag dulu (boleh?), kuota kemudian (berapa?). Terbalik berarti tenant yang fiturnya mati tetap menghabiskan jatah hariannya.

### 4c. MCP — dibutuhkan SEKARANG, bukan nanti

> Ini koreksi terbesar atas versi lama, yang menyebut MCP "masih kerangka" dan menunda gerbangnya. MCP sudah hidup: `routes/ai.php:14` me-route servernya, tiga tool terdaftar di `SapiBusinessServer`, dan `SettingsController` sudah menerbitkan token `mcp-client`. **Hari ini satu-satunya cara menghentikan akses MCP adalah mencabut tokennya** — tidak ada saklar.

Gerbangnya satu baris, karena middleware-nya sudah berupa daftar:

```php
// routes/ai.php:15
Mcp::web('/mcp/business', SapiBusinessServer::class)
    ->middleware(['auth:sanctum', 'tenant.api', 'feature.api:ai', 'role:owner', 'throttle:mcp']);
```

Ditaruh sebelum `role:owner` dengan alasan sama seperti [4a](#4a-web--komposisi-dengan-permission-yang-sudah-ada): sebab penolakan yang benar lebih berguna daripada sebab yang kebetulan diperiksa duluan.

Gerbang di tingkat server sudah cukup — ketiga tool membaca agregat yang sama (`AiContextService`/`ProfitService`) dan tak ada yang butuh flag berbeda. Guard per-tool baru diperlukan bila suatu saat ada tool dengan kelayakan berbeda.

---

## 5. Gating Kitchen Queue

Rutenya belum ada; detailnya milik `PHASE-QUEUE` Bagian 5. Yang dikontrakkan di sini hanya bentuk gerbangnya:

```php
Route::middleware('feature:kitchen_queue')->group(function () {
    // cashier.queue.* — lihat PHASE-QUEUE
});
```

Menu "Antrian" di `OwnerLayout.vue` dan `CashierTopbar.vue` dirender bersyarat flag yang sama.

### Membagikan flag ke Inertia

Menu bersyarat butuh flag di sisi klien. Tambahkan ke `HandleInertiaRequests@share`, di dalam blok `auth`:

```php
'tenant' => $user instanceof User && $user->tenant ? [
    'features' => fn () => [
        'kitchen_queue' => $user->tenant->hasFeature('kitchen_queue'),
        'self_order' => $user->tenant->hasFeature('self_order'),
        'ai' => $user->tenant->hasFeature('ai'),
    ],
] : null,
```

Tiga hal yang menempel pada berkas itu dan gampang terlewat:
1. **Pakai closure**, mengikuti pola `permissions` di `:37`. Komentar di `:32-34` menjelaskan sebabnya: `share()` dipanggil di awal middleware global, sebelum `EnsureTenant` menyetel team-id spatie.
2. **`$user` bisa `PlatformUser`**, yang tidak punya `tenant_id` maupun `tenant`. Pemeriksaan `instanceof` wajib — lihat komentar `:19-20`.
3. **Kunci terpisah, bukan menumpang `auth.user`** — alasan yang sama dengan `platformUser` di `:45`.

**Sidebar hanyalah cermin.** Gerbang rute tetap sumber kebenarannya; menyembunyikan menu bukan pengamanan.

---

## 6. Matriks Sinkronisasi

Kontrak "sekali toggle, semua patuh". Menambah flag = menambah baris lalu menyentuh **semua** sel ✅.

| Flag | Web | API self-order | Mobile | AI job | MCP |
|---|---|---|---|---|---|
| **`self_order_enabled`** | Settings; badge historis tetap tampil | ✅ `feature.api:self_order` di `/orders*` + `/upsell/suggestions` | — | — | — |
| **`ai_enabled`** | ✅ `feature:ai` + `permission:ai_analysis` di `ai-analysis.*`; nav bersyarat | — | (bila kelak ada endpoint AI mobile) | ✅ guard di `handle()` | ✅ `feature.api:ai` di `routes/ai.php` |
| **`kitchen_queue_enabled`** | ✅ `feature:kitchen_queue` di `cashier.queue.*`; nav bersyarat | — | (bila kelak ada papan di mobile) | — | — |

**Aturan emas.** Flag baru = 1 baris di sini, lalu gerbang di setiap kolom ✅. Permukaan baru (GraphQL, webhook keluar, papan mobile) = 1 kolom baru, lalu audit semua flag. Selama setiap sel memanggil `Tenant::hasFeature()`, sinkronisasi terjaga *by design* — bukan oleh kedisiplinan.

---

## 7. Settings (owner)

`Owner/SettingsController@index` hari ini mengirim `tenant` (profil + konfigurasi AI), `aiFreeTier`, dan `mcp`. Tambahkan satu kunci:

```php
'features' => [
    'kitchen_queue_enabled' => $tenant->kitchen_queue_enabled,
    'self_order_enabled' => $tenant->self_order_enabled,
    'ai_enabled' => $tenant->ai_enabled,
],
```

```php
// update() — bergabung dengan aturan yang sudah ada
'kitchen_queue_enabled' => 'boolean',
'self_order_enabled' => 'boolean',
'ai_enabled' => 'boolean',
```

**Tidak ada `business_type` di sini.** Kolom itu milik penetapan harga (`config/pricing-dimensions.php:83`, nilai `kuliner|retail|jasa|lainnya`) dan dibekukan ke `invoices.pricing_context` tiap tagihan terbit. Mengeditnya dari Settings mengubah dasar harga langganan — pengubahnya ada di panel platform, dan memang seharusnya di sana.

`Owner/Settings/Index.vue` mendapat section **"Mode & Fitur Outlet"** memakai `Checkbox.vue` (sudah ada), satu baris per flag dengan deskripsi singkat. Dua peringatan yang layak dirender:
- Mematikan **self-order** saat masih ada pesanan self-order aktif.
- Mematikan **AI** saat masih ada analisis berstatus `pending` — job yang sudah mengantre akan gagal dengan pesan flag ([4b](#4b-job--defense-in-depth-wajib)), dan itu perilaku benar yang lebih baik diketahui di muka.

---

## 8. Tests (Pest)

**File:** `tests/Feature/FeatureGatingTest.php`

**Fondasi**
- `hasFeature()` menjawab `false` untuk nama fitur yang tidak dikenal (gagal tertutup).
- Tenant yang baru dibuat lewat factory **tanpa membaca ulang dari DB** menjawab `ai_enabled = true` — mengunci [1c](#1c-jebakan-attributes--wajib-dibaca).
- Migrasi mem-backfill `self_order_enabled = true` untuk baris lama, sementara tenant baru tetap `false`.

**Self-order**
- `self_order_enabled = false` → `POST /api/v1/orders` menjawab `403` **JSON** berkode `feature_disabled` (bukan redirect).
- Endpoint yang sama lolos saat flag hidup.
- `POST /api/v1/upsell/suggestions` ikut tertutup; `GET /api/v1/products` **tetap terbuka**.

**AI**
- Owner ber-`ai_enabled = false` → `ai-analysis.store` `403`, dan pesannya soal fitur, bukan soal izin.
- Punya `permission:ai_analysis` tapi flag mati → tetap ditolak (kedua gerbang independen).
- Dispatch job lalu matikan flag sebelum worker jalan → `AiAnalysis` jadi `failed` berpesan flag, provider **tidak** dipanggil (mock), kuota **tidak** bertambah.
- Flag hidup + kuota habis → gagal karena kuota. Flag mati + kuota tersedia → gagal karena flag. Mengunci urutannya.

**MCP**
- Token tenant ber-`ai_enabled = false` → permintaan ke `/mcp/business` ditolak `403` sebelum tool mana pun berjalan.

**Isolasi**
- Flag tenant A tidak memengaruhi tenant B.

Jalankan: `php artisan test --compact --filter=FeatureGating`

---

## 9. Checklist

**Fondasi**
- [x] Migrasi tiga boolean + **backfill `self_order_enabled = true`**
- [x] `Tenant`: `$fillable`, `casts()`, `hasFeature()`, dan **`$attributes`**
- [x] `EnsureTenantFeature` + `EnsureTenantFeatureApi`
- [x] Alias ditambahkan ke daftar yang sudah ada — `tenant`/`tenant.api` **tidak** disentuh

**Gerbang**
- [x] `feature.api:self_order` membungkus `/orders*` + `/upsell/suggestions`
- [x] `feature:ai` mendahului `permission:ai_analysis` di `ai-analysis.*`
- [x] Guard `hasFeature('ai')` di `RunAiAnalysisJob@handle`, sebelum kuota & provider
- [x] `feature.api:ai` di `routes/ai.php` (**MCP sudah hidup — jangan ditunda**)
- [x] Flag dibagikan lewat `HandleInertiaRequests` (closure + penjaga `instanceof`)
- [x] Nav bersyarat di `OwnerLayout.vue`

**Settings**
- [x] `index()` mengirim `features`, `update()` memvalidasi tiga boolean — **tanpa `business_type`**
- [x] Section "Mode & Fitur Outlet" + dua peringatan

**Penutup**
- [x] Tests `FeatureGating` hijau
- [x] `vendor/bin/pint --dirty --format agent` bersih
- [x] Matriks [Bagian 6](#6-matriks-sinkronisasi) diperbarui bila ada yang berubah saat pengerjaan
- [x] Entri `[ADDITION]` di `docs/CHANGELOG.md`

### Urutan kerja disarankan
Fondasi (1) → middleware (2) → **test fondasi dulu** → gerbang per permukaan (3–5) → Settings (7).
Fase ini prasyarat `PHASE-QUEUE`; Bagian 5 baru bisa ditutup setelah rute antrian ada.

---

## 10. Keputusan Terbuka

### A. Satu flag `ai`, atau pisahkan `mcp_enabled`?
Sekarang MCP ikut flag `ai`: AI mati, MCP ikut mati. Sederhana dan konsisten. Pemisahan baru berguna bila owner ingin memakai AI in-app sambil menolak klien MCP eksternal — kebutuhan yang belum terbukti.
**Rekomendasi:** satu flag `ai` dulu. Naikkan ke `mcp_enabled` saat MCP benar-benar dibuka ke pihak ketiga, bukan sebelum.

### B. Grup self-order tidak memakai `tenant.api` — apakah disengaja? ✅ TERJAWAB
**Temuan baru saat penulisan ulang ini, dan ia melampaui lingkup dokumen.** Grup di `routes/api.php:31` hanya memakai `auth:sanctum`, sementara grup mobile (`:50`) memakai `tenant.api`. Karena `tenant.api` yang membawa `EnsureSubscriptionActive`, konsekuensinya: **tenant yang `suspended` atau `grace` tampaknya masih bisa membuat self-order lewat API**, padahal jalur web-nya sudah tertutup.

**Ditutup 2026-07-29 sebagai `[BL-020]`**, persis lewat jalur yang direkomendasikan: dicatat sebagai entri backlog tersendiri, dibuktikan dengan test lebih dulu, dan **ternyata benar bocor** — `POST /api/v1/orders` pada tenant `suspended` menjawab 422 dari validasi controller, bukan 403.

Perbaikannya memakai alias `subscription` yang baru, **bukan** menempelkan `tenant.api` — dugaan bahwa grup itu akan menyeret `EnsureEmailVerified` secara tidak masuk akal untuk token mesin terbukti tepat, dan sekarang ada test yang menjaga batas itu. Rute `PATCH /orders/{id}/fulfillment` sengaja ditinggalkan di luar gerbang langganan; alasannya di `docs/CHANGELOG.md`.

### C. Kode respons untuk fitur mati
Dipakai `403 feature_disabled`. Alternatif `503` bila ingin memberi kesan "sementara".
**Rekomendasi:** tetap `403` — ini soal izin fitur, bukan gangguan layanan.

### D. Riwayat perubahan flag
Menyalakan/mematikan fitur tidak tercatat di mana pun. Untuk tenant itu mungkin berlebihan, tapi pertanyaan "sejak kapan self-order mati?" akan muncul cepat saat ada keluhan pelanggan.
**Rekomendasi:** belum sekarang. Bila kelak dibutuhkan, jejaknya milik sisi tenant — dan sisi tenant belum punya tabel audit sendiri (batas yang sudah tercatat di `[BL-011]`).
