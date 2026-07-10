# PHASE FEATURE-FLAGS — Capability Gating (Self-Order & AI) Tersinkron

**Status:** Rencana — belum dikerjakan
**Estimasi:** Bareng / setelah `PHASE-QUEUE` (berbagi fondasi flag yang sama)
**Dependency:** `PHASE-QUEUE_Kitchen-Order-Queue.md` (Bagian 0 — Tenant Capability Flags), `SAPI-SelfOrder-Implementation-Plan.md`, `PHASE-AI-1/2/3`
**Output:** Gating fitur **Self-Order** & **AI** yang konsisten di **4 permukaan**: App utama (web), API, AI job, dan MCP — semua bersumber dari satu `Tenant::hasFeature()`
**Dipakai oleh:** Owner (kontrol via Settings), semua consumer (kasir, n8n/Telegram, mobile, MCP client)

> Tujuan dokumen ini: saat sebuah fitur di-toggle on/off untuk satu tenant, **semua jalur masuk ikut patuh** — tidak ada yang bocor. Bukan menambah fitur baru, tapi memasang **gerbang** di tiap permukaan yang memanggil satu sumber kebenaran.

---

## Konteks — kenapa butuh gerbang di banyak tempat

Satu fitur bisa diakses lewat pintu yang mekanismenya beda-beda. Kalau gating cuma dipasang di satu pintu, pintu lain tetap bocor. Peta pintu masuk saat ini:

| Fitur | Pintu masuk yang sudah ada di kode | Mekanisme auth |
|---|---|---|
| **Self-Order** | `POST /api/v1/orders` (`ApiOrderController@store`), `PATCH /api/v1/orders/{tx}/fulfillment` | `auth:sanctum` (token n8n) |
| **AI Analysis** | Web: `Owner/AiAnalysisController@store` → `RunAiAnalysisJob` | `auth` + `tenant` + `role:owner` |
| **AI (eksekusi)** | `RunAiAnalysisJob@handle` (queue worker, **tanpa** middleware) | `Auth::setUser()` manual |
| **MCP** | `app/Mcp/Servers/SapiBusinessServer.php` + `GetSalesSummaryTool`/`GetProfitTool`/`GetMenuTool` (scaffold, tools belum diregistrasi/di-route) | Rencana: Sanctum token per tenant |

**Temuan yang membentuk desain:**
- Middleware alias yang ada: `tenant`, `tenant.api`, `role` (lihat `bootstrap/app.php`). Belum ada alias untuk feature-gate → perlu tambah `feature` (web) & `feature.api` (API, respons JSON).
- Respons berbeda per permukaan: **web** → redirect + flash; **API/MCP** → JSON. Jadi gerbang web dan API tidak bisa satu kelas yang sama.
- `RunAiAnalysisJob` **tak lewat HTTP** → gating middleware tak berlaku. Wajib cek `hasFeature('ai')` **di dalam job** (defense-in-depth), bukan cuma saat dispatch.
- Kuota AI sudah ada (`AiUsage` + `config('ai.free_tier.daily_limit')`, dicek di `RunAiAnalysisJob@assertQuota`). Flag `ai_enabled` adalah lapis **kedua** yang berbeda maksud: kuota = "berapa banyak", flag = "boleh atau tidak".
- MCP server masih kerangka (`$tools = []`, tool body `//`). Gating dipasang **saat** MCP diaktifkan — dokumen ini menetapkan kontraknya lebih dulu supaya tak lupa.

### Prinsip WAJIB

- **Satu sumber kebenaran:** hanya `Tenant::hasFeature()` yang memutuskan. Tidak ada pengecekan `->ai_enabled` tersebar manual di controller.
- **Gerbang di setiap permukaan** memanggil sumber yang sama. Tambah permukaan baru = tambah gerbang, bukan tambah logika.
- **Defense-in-depth untuk jalur tanpa middleware** (job, MCP tool): cek ulang di dalam eksekusi.
- **Respons sesuai permukaan:** web redirect, API/MCP JSON dengan pesan jelas (mis. agar n8n bisa membalas customer).
- **Pint** setelah edit PHP; setiap perubahan **diuji** (Pest).

---

## Bagian 1 — Fondasi (ringkas, detail di PHASE-QUEUE Bagian 0)

Kolom & helper diperkenalkan di `PHASE-QUEUE` Bagian 0. Recap yang relevan di sini:

```php
// app/Models/Tenant.php
public function hasFeature(string $feature): bool
{
    return (bool) match ($feature) {
        'kitchen_queue' => $this->kitchen_queue_enabled,
        'self_order'    => $this->self_order_enabled,
        'ai'            => $this->ai_enabled,
        default         => false,
    };
}
```

Flag di tabel `tenants`: `self_order_enabled` (default `false`), `ai_enabled` (default `true`, jaga status quo). Opsional `mcp_enabled` bila mau memisah "AI in-app" dari "MCP eksternal" (lihat Bagian 7).

---

## Bagian 2 — Dua kelas middleware feature-gate

Karena respons web ≠ API, buat dua middleware tipis yang memanggil helper sama.

### 2a. Web — `feature` (redirect/abort)

```php
// app/Http/Middleware/EnsureTenantFeature.php
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

### 2b. API — `feature.api` (JSON 403, pesan bisa dibaca n8n/mobile)

```php
// app/Http/Middleware/EnsureTenantFeatureApi.php
class EnsureTenantFeatureApi
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $request->user()?->tenant?->hasFeature($feature)) {
            return response()->json([
                'success' => false,
                'code'    => 'feature_disabled',
                'message' => "Fitur '{$feature}' sedang tidak aktif untuk outlet ini.",
            ], 403);
        }
        return $next($request);
    }
}
```

Daftarkan alias di `bootstrap/app.php`:

```php
$middleware->alias([
    'tenant'      => \App\Http\Middleware\EnsureTenant::class,
    'tenant.api'  => \App\Http\Middleware\EnsureTenantApi::class,
    'role'        => \App\Http\Middleware\EnsureRole::class,
    'feature'     => \App\Http\Middleware\EnsureTenantFeature::class,     // ← baru
    'feature.api' => \App\Http\Middleware\EnsureTenantFeatureApi::class,  // ← baru
]);
```

---

## Bagian 3 — Gating Self-Order (semua permukaan)

### 3a. API (pintu utama self-order)

`routes/api.php` — bungkus grup order dengan `feature.api:self_order`:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/products', [ApiProductController::class, 'index']);

    Route::middleware('feature.api:self_order')->group(function () {   // ← gerbang
        Route::post('/orders', [ApiOrderController::class, 'store'])->middleware('throttle:60,1');
        Route::patch('/orders/{transaction}/fulfillment', [ApiOrderController::class, 'updateFulfillment']);
    });
});
```

> **Catatan `/products`:** dibiarkan di luar gerbang karena dipakai n8n untuk konteks & juga endpoint mobile. Kalau mau menutup katalog saat self-order mati, pindahkan ke dalam grup.

### 3b. n8n / Telegram

n8n mengakses lewat API di atas → otomatis kena gerbang. Yang perlu: **workflow n8n menangani 403 `feature_disabled`** dan membalas customer ("Maaf, pemesanan online sedang tidak aktif"). Tidak ada perubahan Laravel selain pesan JSON yang jelas (sudah di 2b).

### 3c. App utama (web)

- **Dashboard badge "Self Order"** tetap tampil untuk order historis (jangan disembunyikan oleh flag — itu data masa lalu).
- **Settings**: toggle `self_order_enabled` (Bagian 6).
- Tidak ada halaman POS self-order di web, jadi tak ada route web yang perlu digerbang.

### 3d. MCP

Tidak relevan untuk self-order (MCP mengekspos data bisnis/analitik, bukan pembuatan order). N/A.

---

## Bagian 4 — Gating AI (semua permukaan)

### 4a. App utama (web) — dua lapis

Route + guard saat dispatch:

```php
// routes/web.php — grup owner
Route::middleware('feature:ai')->group(function () {   // ← gerbang route + nav
    Route::get('ai-analysis', [AiAnalysisController::class, 'index'])->name('ai-analysis.index');
    Route::post('ai-analysis', [AiAnalysisController::class, 'store'])->name('ai-analysis.store');
    Route::get('ai-analysis/{aiAnalysis}', [AiAnalysisController::class, 'show'])->name('ai-analysis.show');
});
```

Nav menu "AI Analysis" hanya render bila `features.ai` true (shared via `HandleInertiaRequests`).

### 4b. AI job — defense-in-depth (WAJIB)

`RunAiAnalysisJob@handle` jalan tanpa middleware. Cek flag sebelum generate, karena job bisa saja sudah ter-antre lalu flag dimatikan:

```php
// app/Jobs/RunAiAnalysisJob.php — di dalam handle(), setelah $tenant didapat
if (! $tenant->hasFeature('ai')) {
    $analysis->update([
        'status' => AiAnalysis::STATUS_FAILED,
        'error'  => 'Fitur AI tidak aktif untuk outlet ini.',
    ]);
    return; // jangan panggil provider / jangan pakai kuota
}
```

Kuota (`assertQuota`) tetap jalan **setelah** cek flag — urutannya: flag dulu (boleh?), baru kuota (berapa?).

### 4c. MCP — gerbang saat diaktifkan

Saat `SapiBusinessServer` diregistrasi & tool diimplement (rujuk `PHASE-AI-1` Bagian 4), pasang gerbang **berbasis tenant dari token Sanctum**. Dua opsi:

1. **Middleware server** (disarankan) — satu tempat, semua tool ikut:
   ```php
   // Saat MCP di-route (routes/ai.php): terapkan middleware yang menolak
   // bila token->tokenable->tenant->hasFeature('ai') (atau 'mcp') false.
   ```
2. **Per-tool guard** di `handle()` — fallback bila per-tool butuh flag berbeda.

Karena MCP membaca data agregat yang sama dengan AI Analysis (`AiContextService`/`ProfitService`), default gerbang MCP = flag **`ai`**. Kalau ingin kontrol terpisah ("AI in-app boleh, MCP eksternal tidak"), pakai flag khusus `mcp_enabled` (Bagian 7).

---

## Bagian 5 — Matriks Sinkronisasi (acuan utama)

Inilah kontrak "sekali toggle, semua patuh". Saat menambah flag baru, isi baris baru & sentuh **semua** kolom yang ✅.

| Fitur / Flag | App utama (web) | API | AI job / worker | MCP |
|---|---|---|---|---|
| **self_order** (`self_order_enabled`) | Settings toggle; badge historis tetap tampil | ✅ `feature.api:self_order` di grup `/orders*` | — | — |
| **ai** (`ai_enabled`) | ✅ `feature:ai` di route `ai-analysis.*` + nav bersyarat | (mobile: gate bila ada endpoint AI) | ✅ guard di `RunAiAnalysisJob@handle` | ✅ gerbang server/tool (default flag `ai`) |
| **kitchen_queue** (`kitchen_queue_enabled`) | ✅ `feature:kitchen_queue` di route `queue.*` + nav | (bila ada endpoint queue mobile) | — | — |
| **(baru...)** | | | | |

**Sumber tunggal:** semua sel ✅ memanggil `Tenant::hasFeature()`. Tidak ada logika duplikat.

---

## Bagian 6 — Settings (owner)

`Owner/SettingsController`:

```php
// index(): kirim flag ke view
'features' => [
    'business_type'         => $tenant->business_type,
    'kitchen_queue_enabled' => $tenant->kitchen_queue_enabled,
    'self_order_enabled'    => $tenant->self_order_enabled,
    'ai_enabled'            => $tenant->ai_enabled,
],

// update(): validasi
'business_type'         => 'required|in:cafe,street_food',
'kitchen_queue_enabled' => 'boolean',
'self_order_enabled'    => 'boolean',
'ai_enabled'            => 'boolean',
```

`Owner/Settings/Index.vue`: section "Mode & Fitur Outlet" dengan `Checkbox.vue` (sudah ada) per flag + deskripsi singkat. Untuk `self_order`, tampilkan peringatan bila dimatikan saat masih ada order self-order aktif.

---

## Bagian 7 — Keputusan Terbuka

### A. Satu flag `ai` untuk in-app + MCP, atau pisah `mcp_enabled`?
- **Satu flag `ai`** (default): simpel, konsisten — kalau AI mati, MCP juga mati. Cukup untuk mayoritas kasus.
- **Pisah `mcp_enabled`**: kontrol lebih halus (mis. owner pakai AI Analysis in-app tapi tidak mengizinkan MCP client eksternal). Tambah 1 boolean + 1 baris di matriks. Pilih ini hanya bila memang butuh membedakan consumer eksternal.

**Rekomendasi:** mulai dengan satu flag `ai`; naikkan ke `mcp_enabled` saat MCP benar-benar dibuka ke pihak ketiga.

### B. Endpoint `/api/v1/products`
Saat ini di luar gerbang self-order (dipakai mobile + n8n). Putuskan: tetap terbuka, atau ikut ditutup saat `self_order` mati. Rekomendasi: **tetap terbuka** (katalog ≠ pemesanan).

### C. Response code untuk fitur mati
Dipakai `403 feature_disabled`. Alternatif `503` (service unavailable) bila ingin menandakan "sementara". Rekomendasi tetap `403` (ini soal izin fitur, bukan gangguan).

---

## Bagian 8 — Tests (Pest)

**File:** `tests/Feature/FeatureGatingTest.php`

- **Self-order API:** tenant `self_order_enabled=false` → `POST /api/v1/orders` balas `403 feature_disabled` (JSON, bukan redirect). Tenant `true` → lolos.
- **AI web:** owner tenant `ai_enabled=false` → route `ai-analysis.store` `403`; nav tidak render menu AI. Tenant `true` → job ter-dispatch.
- **AI job:** dispatch job lalu set `ai_enabled=false` sebelum worker jalan → `AiAnalysis` jadi `failed` dengan pesan flag, provider **tidak** dipanggil (mock), kuota **tidak** bertambah.
- **AI job kuota vs flag:** flag on + kuota habis → gagal karena kuota; flag off → gagal karena flag (urutan benar).
- **Isolasi tenant:** flag tenant A tidak memengaruhi tenant B.
- **(MCP, saat aktif):** tool call dengan token tenant `ai=false` → ditolak.

Jalankan: `php artisan test --compact --filter="FeatureGating"`

---

## Bagian 9 — Checklist

- [ ] Middleware `feature` (web) + `feature.api` (JSON) + daftarkan alias di `bootstrap/app.php`
- [ ] `feature.api:self_order` membungkus `/api/v1/orders*`
- [ ] `feature:ai` membungkus route `ai-analysis.*` + nav bersyarat
- [ ] Guard `hasFeature('ai')` di dalam `RunAiAnalysisJob@handle` (sebelum kuota & provider)
- [ ] Kontrak gerbang MCP didokumentasikan di `SapiBusinessServer` (komentar) — dipasang saat MCP di-route
- [ ] Settings: kirim + validasi + toggle `self_order_enabled`, `ai_enabled` (+ `kitchen_queue_enabled`, `business_type`)
- [ ] Peringatan UI saat mematikan self-order dengan order aktif
- [ ] Matriks Sinkronisasi (Bagian 5) diperbarui bila ada flag/permukaan baru
- [ ] Tests `FeatureGating` hijau
- [ ] `vendor/bin/pint --dirty --format agent` bersih

### Aturan emas
Menambah **flag baru** = tambah 1 baris di **Matriks Sinkronisasi (Bagian 5)**, lalu pasang gerbang di **setiap kolom ✅**. Menambah **permukaan baru** (mis. GraphQL, webhook keluar) = tambah 1 kolom & audit semua flag. Selama semua gerbang memanggil `Tenant::hasFeature()`, sinkronisasi terjaga by design.
