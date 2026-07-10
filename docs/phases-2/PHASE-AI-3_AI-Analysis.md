# PHASE AI-3 — Fitur AI Analysis (UI, Async Job, Tipe Analisis)

**Status:** Selesai  
**Estimasi:** Setelah `PHASE-AI-1` & `PHASE-AI-2` selesai  
**Dependency:** `PHASE-AI-1` (`AiContextService`) + `PHASE-AI-2` (`AiProviderFactory`, kuota, BYOK)  
**Output:** Halaman Owner AI Analysis — jalankan analisis AI asinkron & tampilkan hasil (saran diskon, perhitungan & proyeksi profit, insight)

> Bagian 3 dari 3 (terakhir). Butuh Bagian 1 & 2 hijau.

---

## Konteks

Halaman Owner yang menjalankan analisis AI secara **asinkron** (queued job) dan menampilkan hasilnya dengan polling. Ini menyatukan fondasi data (Bagian 1) dan engine LLM (Bagian 2) jadi fitur yang dilihat user.

- **Queue `database` sudah aktif**, `composer run dev` menjalankan `queue:listen` → ini **Job async pertama** di app.
- **Multi-tenant otomatis** via `BelongsToTenant`; route-model binding `{aiAnalysis}` otomatis ter-scope tenant.

### Prinsip WAJIB
- **Eksekusi async + polling** (Inertia v2) — jangan panggil LLM sinkron di request (berisiko timeout 5–20 dtk).
- **Kuota di-enforce di server** (di Job), bukan hanya UI.
- Konteks dikirim ke LLM **hanya agregat** (sudah dijamin `AiContextService`).
- **Pint** setelah edit PHP. Setiap perubahan **diuji** (`Queue::fake()` + `Http::fake()`).

---

## Overview Endpoint

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/owner/ai-analysis` | Daftar analisis + form (tipe + periode) |
| `POST` | `/owner/ai-analysis` | Buat analisis (`pending`), dispatch job |
| `GET` | `/owner/ai-analysis/{aiAnalysis}` | Ambil satu analisis untuk polling status+hasil |

---

## Daftar Isi
1. [Model & Migration ai_analyses](#1-model--migration-ai_analyses)
2. [RunAiAnalysisJob](#2-runaianalysisjob)
3. [AiAnalysisController & Routes](#3-aianalysiscontroller--routes)
4. [Vue Page & Sidebar](#4-vue-page--sidebar)
5. [Tipe Analisis](#5-tipe-analisis)
6. [Tests](#6-tests)
7. [Checklist](#7-checklist)

---

## 1. Model & Migration ai_analyses

**File:** `database/migrations/xxxx_create_ai_analyses_table.php`

```php
Schema::create('ai_analyses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('type');       // general|discount|profit_projection|custom
    $table->string('status');     // pending|processing|completed|failed
    $table->json('params')->nullable();     // rentang tanggal, dll
    $table->text('prompt')->nullable();
    $table->longText('result')->nullable();
    $table->text('error')->nullable();
    $table->unsignedInteger('tokens_used')->nullable();
    $table->timestamps();
});
```

**File:** `app/Models/AiAnalysis.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    use BelongsToTenant;

    public const TYPE_GENERAL = 'general';
    public const TYPE_DISCOUNT = 'discount';
    public const TYPE_PROFIT_PROJECTION = 'profit_projection';
    public const TYPE_CUSTOM = 'custom';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = ['tenant_id', 'user_id', 'type', 'status', 'params', 'prompt', 'result', 'error', 'tokens_used'];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

## 2. RunAiAnalysisJob

Job async pertama di app. Menyatukan konteks (Bagian 1) + provider & kuota (Bagian 2).

**File:** `app/Jobs/RunAiAnalysisJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Models\AiUsage;
use App\Models\Tenant;
use App\Services\Ai\AiProviderFactory;
use App\Services\AiContextService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class RunAiAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 10;

    public function __construct(public int $analysisId) {}

    public function handle(AiContextService $context, AiProviderFactory $factory): void
    {
        // withoutGlobalScopes: Job jalan tanpa auth(), scope tenant manual via relasi.
        $analysis = AiAnalysis::withoutGlobalScopes()->findOrFail($this->analysisId);
        $tenant = $analysis->tenant;

        $analysis->update(['status' => AiAnalysis::STATUS_PROCESSING]);

        // AiContextService & ProfitService memakai TenantScope berbasis auth().
        // Job tak punya sesi, jadi autentikasi sebagai pemilik analisis agar seluruh
        // query konteks ter-scope ke tenant yang benar.
        Auth::setUser($analysis->user);

        try {
            $usingFreeTier = $factory->isUsingFreeTier($tenant);
            if ($usingFreeTier) {
                $this->assertQuota($tenant);
            }

            $from = Carbon::parse($analysis->params['from']);
            $to = Carbon::parse($analysis->params['to'])->endOfDay();

            $data = $context->buildContext($tenant, $from, $to);
            [$system, $user] = $this->prompts($analysis->type, $analysis->prompt);

            $result = $factory->for($tenant)->generate($system, $data, $user);

            $analysis->update([
                'status'      => AiAnalysis::STATUS_COMPLETED,
                'result'      => $result->text,
                'tokens_used' => $result->tokensUsed,
            ]);

            if ($usingFreeTier) {
                $this->incrementUsage($tenant);
            }
        } catch (\Throwable $e) {
            $analysis->update([
                'status' => AiAnalysis::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);
        } finally {
            // Cegah kebocoran state auth ke job berikutnya pada worker yang sama.
            Auth::forgetGuards();
        }
    }

    private function assertQuota(Tenant $tenant): void
    {
        $used = AiUsage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('date', now())
            ->value('count') ?? 0;

        if ($used >= (int) config('ai.free_tier.daily_limit')) {
            throw new RuntimeException('Kuota harian free tier habis. Isi API key sendiri di Pengaturan untuk pemakaian tanpa batas.');
        }
    }

    private function incrementUsage(Tenant $tenant): void
    {
        $usage = AiUsage::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'date' => now()->toDateString()],
            ['count' => 0],
        );
        $usage->increment('count');
    }

    /**
     * @return array{0: string, 1: string} [systemPrompt, userPrompt]
     */
    private function prompts(string $type, ?string $custom): array
    {
        $system = 'Kamu analis bisnis F&B. Berdasarkan DATA agregat berikut, beri jawaban ringkas, actionable, dalam Bahasa Indonesia, dengan angka konkret. Jangan mengarang data di luar yang diberikan.';

        $user = match ($type) {
            AiAnalysis::TYPE_DISCOUNT => 'Item mana yang sebaiknya didiskon dan berapa besarannya? Pertimbangkan margin per item, produk terlaris, dan dead stock.',
            AiAnalysis::TYPE_PROFIT_PROJECTION => 'Jelaskan profit aktual periode ini, proyeksi periode berikutnya, dan rekomendasi menaikkan profit.',
            AiAnalysis::TYPE_CUSTOM => $custom ?: 'Beri insight bisnis dari data ini.',
            default => 'Beri insight bisnis umum, anomali, dan rekomendasi dari data ini.',
        };

        return [$system, $user];
    }
}
```

## 3. AiAnalysisController & Routes

**File:** `app/Http/Controllers/Owner/AiAnalysisController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAnalysisController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Owner/AiAnalysis/Index', [
            'analyses' => AiAnalysis::latest()->take(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type'   => 'required|in:general,discount,profit_projection,custom',
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'prompt' => 'nullable|string|max:1000',
        ]);

        $analysis = AiAnalysis::create([
            'user_id' => auth()->id(),
            'type'    => $validated['type'],
            'status'  => AiAnalysis::STATUS_PENDING,
            'params'  => ['from' => $validated['from'], 'to' => $validated['to']],
            'prompt'  => $validated['prompt'] ?? null,
        ]);

        RunAiAnalysisJob::dispatch($analysis->id);

        return back()->with('success', 'Analisis sedang diproses.');
    }

    public function show(AiAnalysis $aiAnalysis): Response
    {
        return Inertia::render('Owner/AiAnalysis/Index', [
            'analyses' => AiAnalysis::latest()->take(20)->get(),
            'active'   => $aiAnalysis,
        ]);
    }
}
```

**File:** `routes/web.php` (dalam group `owner.`)

```php
Route::get('ai-analysis', [\App\Http\Controllers\Owner\AiAnalysisController::class, 'index'])
    ->name('ai-analysis.index');
Route::post('ai-analysis', [\App\Http\Controllers\Owner\AiAnalysisController::class, 'store'])
    ->name('ai-analysis.store');
Route::get('ai-analysis/{aiAnalysis}', [\App\Http\Controllers\Owner\AiAnalysisController::class, 'show'])
    ->name('ai-analysis.show');
```

> **Route-model binding + tenant:** `AiAnalysis` pakai `BelongsToTenant` → `{aiAnalysis}` otomatis ter-scope tenant login (404 bila lintas tenant). Aman tanpa cek manual.

## 4. Vue Page & Sidebar

**File:** `resources/js/Pages/Owner/AiAnalysis/Index.vue` (`OwnerLayout`)

**Layout:**

```
┌────────────────────────────────────────────────────────────┐
│  AI Analysis — Kopi Nusantara                              │
├────────────────────────────────────────────────────────────┤
│  ┌─ Buat Analisis ─────────────────────────────────────┐   │
│  │ Tipe: [Saran Diskon ▾]  Periode: [DatePicker–DatePicker] │
│  │ (Custom) Pertanyaan: [________________]  [Analisa ▶] │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─ Hasil ─────────────────────────────────────────────┐   │
│  │ processing → skeleton berpulsa + "Memproses…"        │   │
│  │ completed  → render markdown hasil + MetricCard       │   │
│  │ failed     → pesan error                             │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─ Riwayat Analisis ──────────────────────────────────┐   │
│  │ Tipe | Periode | Status | Waktu        [Lihat →]     │   │
│  └──────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────┘
```

**Komponen reuse:** `DatePicker`, `MetricCard`, `DailyChart` (untuk `daily_trend`), `Button`, `SelectDropdown`.

**Polling (Inertia v2):** saat ada analisis `pending`/`processing`, reload prop `active` berkala sampai `completed`/`failed`:

```js
import { router } from '@inertiajs/vue3'

let timer = null
watch(() => props.active?.status, (status) => {
  if (status === 'pending' || status === 'processing') {
    timer = setTimeout(() => {
      router.reload({ only: ['active', 'analyses'] })
    }, 3000)
  } else if (timer) {
    clearTimeout(timer)
  }
}, { immediate: true })
onUnmounted(() => timer && clearTimeout(timer))
```

**Sidebar** — tambah item nav di `sidebarGroups` (`resources/js/Layouts/OwnerLayout.vue`), grup "Keuangan":

```js
{ label: 'AI Analysis', href: '/owner/ai-analysis', icon: SparklesIcon }
```

## 5. Tipe Analisis

`RunAiAnalysisJob::prompts()` memilih user prompt sesuai `type`; semua memakai `context` sama dari `AiContextService`.

| Tipe | Fokus | Data kunci dari konteks |
|---|---|---|
| `discount` | Sarankan item didiskon & besarannya | `profit_by_item` (margin), `top_products`, `inventory` (dead stock) |
| `profit_projection` | Profit aktual + proyeksi + rekomendasi | `profit`, `projection`, `daily_trend` |
| `general` | Insight bisnis umum & anomali | seluruh konteks |
| `custom` | Jawab `prompt` bebas owner | seluruh konteks + `analysis->prompt` |

## 6. Tests

**File:** `tests/Feature/Owner/AiAnalysisControllerTest.php`, `tests/Feature/Ai/RunAiAnalysisJobTest.php`

- Controller: hanya owner (role + tenant); `store` membuat record `pending` + men-dispatch `RunAiAnalysisJob` (`Queue::fake()`); `show` lintas-tenant → 404.
- Job (`Http::fake()`): sukses → record `completed`, `tokens_used` terisi, `AiUsage` bertambah untuk free tier; provider error → `failed` + `error`; kuota habis → `failed` dengan pesan kuota; BYOK bypass kuota.

Jalankan: `php artisan test --compact --filter="AiAnalysis|RunAiAnalysisJob"`

## 7. Checklist

- [x] Migration + model `AiAnalysis` (enum konstanta, `BelongsToTenant`, cast `params`)
- [x] `RunAiAnalysisJob` — build konteks, panggil provider, simpan hasil/error, cek & catat kuota
- [x] `AiAnalysisController` (`index`/`store`/`show`) + 3 route owner
- [x] `Owner/AiAnalysis/Index.vue` — form, polling Inertia v2, skeleton, render hasil
- [x] Sidebar nav item di `OwnerLayout.vue`
- [x] 4 tipe analisis (discount, profit_projection, general, custom) berfungsi
- [x] Test controller (`Queue::fake`) & job (`Http::fake`) hijau
- [x] `vendor/bin/pint --dirty --format agent` bersih

### Test Manual
1. `composer run dev` (worker jalan), login owner.
2. AI Analysis → "Saran Diskon", periode 30 hari → submit → status `pending → processing → completed`, hasil tampil.
3. "Proyeksi Profit" → angka profit & proyeksi konsisten dengan `PHASE-AI-1` / laporan.
4. Free tier: jalankan melebihi `daily_limit` → `failed` pesan kuota; isi BYOK key → bisa lagi tanpa batas.
5. Buka `GET /owner/ai-analysis/{id}` milik tenant lain → 404.
