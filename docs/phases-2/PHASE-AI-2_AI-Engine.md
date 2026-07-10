# PHASE AI-2 — AI Engine: Provider Layer, BYOK, Free Tier & Kuota

**Status:** Selesai  
**Estimasi:** Setelah `PHASE-AI-1` selesai  
**Dependency:** `PHASE-AI-1` (butuh `AiContextService` sebagai input konteks)  
**Output:** Lapisan pemanggil LLM multi-provider (Gemini/OpenAI/Anthropic via `Http`), penyimpanan BYOK per-tenant, free tier berkuota, section AI di Settings  
**Dipakai oleh:** `PHASE-AI-3` (AI Analysis)

> Bagian 2 dari 3. Butuh Bagian 1 hijau. Lanjut ke `PHASE-AI-3_AI-Analysis.md` setelah bagian ini hijau.

---

## Konteks

Lapisan yang benar-benar memanggil LLM, mengelola kredensial (**BYOK** vs **free tier**), dan membatasi pemakaian free tier. Belum ada apa pun soal AI/LLM di app; `config/services.php` hanya berisi Xendit. Pola rahasia: `.env` → `config/services.php`.

### Keputusan
- **Provider: Gemini (free) + BYOK multi** — Gemini / OpenAI / Anthropic.
- **Free tier: shared key milik app + kuota** per-tenant per-hari; BYOK melepas kuota.

### Prinsip WAJIB
- **Tanpa dependency PHP baru** — semua panggilan LLM lewat **`Http` facade** Laravel (bukan SDK).
- **Keamanan API key:** cast `encrypted`, taruh di `$hidden`, **tak pernah** dikembalikan ke frontend (kirim boolean `ai_key_set` saja).
- **Kuota di-enforce di server** (Job di `PHASE-AI-3`), bukan hanya UI.
- **Keamanan konteks:** konteks dari `AiContextService` sudah agregat (tanpa PII) — jangan tambahkan data mentah saat menyusun prompt.
- **Pint** setelah edit PHP. Setiap perubahan **diuji** (Pest, `Http::fake()`).

---

## Daftar Isi
1. [Provider Layer & AiResult](#1-provider-layer--airesult)
2. [AiProviderFactory & Config](#2-aiproviderfactory--config)
3. [Penyimpanan BYOK di Tenant](#3-penyimpanan-byok-di-tenant)
4. [Free Tier & Kuota (AiUsage)](#4-free-tier--kuota-aiusage)
5. [Settings UI](#5-settings-ui)
6. [Tests](#6-tests)
7. [Checklist](#7-checklist)

---

## 1. Provider Layer & AiResult

Kontrak netral + implementasi per provider (semua via `Http`, tanpa SDK).

**File:** `app/Services/Ai/AiResult.php`

```php
<?php

namespace App\Services\Ai;

class AiResult
{
    public function __construct(
        public string $text,
        public ?int $tokensUsed = null,
    ) {}
}
```

**File:** `app/Services/Ai/AiProvider.php`

```php
<?php

namespace App\Services\Ai;

interface AiProvider
{
    /**
     * @param  array<string, mixed>  $context  Data agregat (bukan PII) dari AiContextService
     */
    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult;
}
```

**File:** `app/Services/Ai/GeminiProvider.php`

```php
<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AiProvider
{
    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult
    {
        $prompt = $systemPrompt
            . "\n\nDATA (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE)
            . "\n\nPERTANYAAN:\n" . $userPrompt;

        $response = Http::timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: ' . $response->status());
        }

        return new AiResult(
            text: $response->json('candidates.0.content.parts.0.text', ''),
            tokensUsed: $response->json('usageMetadata.totalTokenCount'),
        );
    }
}
```

**File:** `app/Services/Ai/OpenAiProvider.php`

```php
<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProvider
{
    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "DATA (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n\nPERTANYAAN:\n" . $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error: ' . $response->status());
        }

        return new AiResult(
            text: $response->json('choices.0.message.content', ''),
            tokensUsed: $response->json('usage.total_tokens'),
        );
    }
}
```

**File:** `app/Services/Ai/AnthropicProvider.php`

```php
<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicProvider implements AiProvider
{
    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->model,
            'max_tokens' => 2048,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => "DATA (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n\nPERTANYAAN:\n" . $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: ' . $response->status());
        }

        $usage = $response->json('usage', []);

        return new AiResult(
            text: $response->json('content.0.text', ''),
            tokensUsed: ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
        );
    }
}
```

## 2. AiProviderFactory & Config

**File:** `app/Services/Ai/AiProviderFactory.php`

```php
<?php

namespace App\Services\Ai;

use App\Models\Tenant;
use InvalidArgumentException;

class AiProviderFactory
{
    /**
     * Pilih provider + resolusi kredensial: BYOK tenant, fallback ke shared free key.
     */
    public function for(Tenant $tenant): AiProvider
    {
        $provider = $tenant->ai_provider ?: config('ai.default');
        $key = $tenant->ai_api_key ?: config('ai.free_tier.key');
        $model = $tenant->ai_model ?: config("ai.models.{$provider}");

        if (! $key) {
            throw new InvalidArgumentException('Belum ada API key AI (BYOK maupun free tier).');
        }

        return match ($provider) {
            'gemini'    => new GeminiProvider($key, $model),
            'openai'    => new OpenAiProvider($key, $model),
            'anthropic' => new AnthropicProvider($key, $model),
            default     => throw new InvalidArgumentException("Provider AI tidak dikenal: {$provider}"),
        };
    }

    public function isUsingFreeTier(Tenant $tenant): bool
    {
        return empty($tenant->ai_api_key);
    }
}
```

**File:** `config/services.php` (tambah blok, pola sama seperti `xendit`)

```php
'gemini'    => ['key' => env('GEMINI_API_KEY')],
'openai'    => ['key' => env('OPENAI_API_KEY')],
'anthropic' => ['key' => env('ANTHROPIC_API_KEY')],
```

**File:** `config/ai.php` (baru)

```php
<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'gemini'),

    'models' => [
        'gemini'    => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
        'openai'    => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
        'anthropic' => env('AI_ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    'free_tier' => [
        'key'         => env('AI_FREE_TIER_KEY'),   // shared Gemini key milik app
        'daily_limit' => env('AI_FREE_TIER_DAILY_LIMIT', 5),
    ],
];
```

**File:** `.env.example` — tambahkan `GEMINI_API_KEY=`, `OPENAI_API_KEY=`, `ANTHROPIC_API_KEY=`, `AI_DEFAULT_PROVIDER=gemini`, `AI_FREE_TIER_KEY=`, `AI_FREE_TIER_DAILY_LIMIT=5`.

## 3. Penyimpanan BYOK di Tenant

**File:** `database/migrations/xxxx_add_ai_settings_to_tenants_table.php`

```php
Schema::table('tenants', function (Blueprint $table) {
    $table->string('ai_provider')->nullable()->after('phone');
    $table->text('ai_api_key')->nullable()->after('ai_provider');   // disimpan terenkripsi
    $table->string('ai_model')->nullable()->after('ai_api_key');
});
```

**File:** `app/Models/Tenant.php` (ubah)

```php
protected $fillable = ['name', 'slug', 'logo', 'address', 'phone', 'ai_provider', 'ai_api_key', 'ai_model'];

protected $hidden = ['ai_api_key'];

protected function casts(): array
{
    return [
        'ai_api_key' => 'encrypted',
    ];
}
```

## 4. Free Tier & Kuota (AiUsage)

**File:** `database/migrations/xxxx_create_ai_usages_table.php`

```php
Schema::create('ai_usages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->unsignedInteger('count')->default(0);
    $table->timestamps();
    $table->unique(['tenant_id', 'date']);
});
```

**File:** `app/Models/AiUsage.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'date', 'count'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
```

**Enforcement** (dipakai di Job `PHASE-AI-3`): tenant free-tier (`ai_api_key` kosong) dengan `count >= config('ai.free_tier.daily_limit')` hari ini → **tolak** dengan pesan *"Kuota harian free tier habis. Isi API key sendiri di Pengaturan untuk pemakaian tanpa batas."* BYOK **bypass** total.

## 5. Settings UI

**File:** `app/Http/Controllers/Owner/SettingsController.php` (perluas method existing)

```php
public function index(): Response
{
    $tenant = auth()->user()->tenant;
    $today = now()->toDateString();
    $usedToday = \App\Models\AiUsage::where('date', $today)->value('count') ?? 0;

    return Inertia::render('Owner/Settings/Index', [
        'tenant' => [
            'name'    => $tenant->name,
            'address' => $tenant->address,
            'phone'   => $tenant->phone,
            // AI — TANPA membocorkan key
            'ai_provider' => $tenant->ai_provider,
            'ai_model'    => $tenant->ai_model,
            'ai_key_set'  => filled($tenant->ai_api_key),
        ],
        'aiFreeTier' => [
            'daily_limit' => (int) config('ai.free_tier.daily_limit'),
            'remaining'   => max(0, (int) config('ai.free_tier.daily_limit') - $usedToday),
        ],
    ]);
}

public function update(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'address'     => 'nullable|string|max:500',
        'phone'       => 'nullable|string|max:50',
        'ai_provider' => 'nullable|in:gemini,openai,anthropic',
        'ai_api_key'  => 'nullable|string|max:255',
        'ai_model'    => 'nullable|string|max:100',
    ]);

    // Jangan overwrite key jadi null kalau field dikosongkan tanpa maksud hapus.
    if (blank($validated['ai_api_key'] ?? null)) {
        unset($validated['ai_api_key']);
    }

    auth()->user()->tenant->update($validated);

    return back()->with('success', 'Pengaturan berhasil disimpan.');
}
```

**File:** `resources/js/Pages/Owner/Settings/Index.vue` — tambah section "AI Analysis":
- Dropdown provider (gemini/openai/anthropic).
- Input API key `type="password"`, placeholder `"•••• tersimpan"` bila `ai_key_set`.
- Input model (opsional).
- Info sisa kuota free tier (`aiFreeTier.remaining` / `daily_limit`) bila key belum diisi.
- Submit via `form.patch('/owner/settings', { preserveScroll: true })`, tampilkan `form.errors`.

## 6. Tests

**File:** `tests/Feature/Ai/AiProviderTest.php`, `tests/Feature/Ai/AiProviderFactoryTest.php`, `tests/Feature/Owner/SettingsAiTest.php`

- Provider (`GeminiProvider` dll) dengan **`Http::fake()`** → assert URL/body request & parsing `AiResult` (`text`, `tokensUsed`).
- `AiProviderFactory`: pilih BYOK vs free key dengan benar; provider tak dikenal → exception; tanpa key sama sekali → exception.
- Settings: `ai_api_key` tersimpan **terenkripsi** (`Tenant::first()->getRawOriginal('ai_api_key') !== plaintext`) & **tak muncul** di props Inertia (hanya `ai_key_set`).

Jalankan: `php artisan test --compact --filter=Ai`

## 7. Checklist

- [x] `AiProvider` interface + `AiResult` DTO
- [x] `GeminiProvider`, `OpenAiProvider`, `AnthropicProvider` (via `Http`, tanpa SDK)
- [x] `AiProviderFactory` — resolusi BYOK vs free tier + `isUsingFreeTier`
- [x] `config/ai.php` + blok `config/services.php` + `.env.example`
- [x] Migration `ai_*` di `tenants` + `Tenant` (`$fillable`/`$hidden`/`casts` encrypted)
- [x] `AiUsage` + migration `ai_usages`
- [x] Settings UI section "AI Analysis" — key tak pernah bocor ke frontend
- [x] Test provider (`Http::fake`), factory, enkripsi key hijau (24 test)
- [x] `vendor/bin/pint --dirty --format agent` bersih

### Test Manual
1. Isi `AI_FREE_TIER_KEY` (key Gemini gratis) di `.env` → `php artisan config:clear`.
2. Settings → biarkan key kosong → sisa kuota tampil. Isi API key → `ai_key_set=true`, kuota disembunyikan.
3. `php artisan tinker` → `Tenant::first()->getRawOriginal('ai_api_key')` → nilai terenkripsi (bukan plaintext).
