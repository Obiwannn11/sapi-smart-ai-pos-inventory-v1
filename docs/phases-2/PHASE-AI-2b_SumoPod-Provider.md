# PHASE AI-2b — SumoPod: Provider AI Default (OpenAI-Compatible)

**Status:** Selesai
**Fase Induk:** `PHASE-AI-2_AI-Engine` (Provider Layer & BYOK)
**Output:** Provider `sumopod` sebagai default, `SumoPodProvider` (extends `OpenAiProvider`), config & Settings UI
**Terkait:** Changelog `[DECISION] SumoPod Jadi Provider AI Default`

> Suplemen `PHASE-AI-2`. Menjadikan **SumoPod** sebagai provider AI default menggantikan Gemini. Tidak mengubah kontrak `AiProvider` maupun alur BYOK/free-tier.

---

## 1. Apa itu SumoPod

SumoPod adalah **PaaS/gateway AI** yang menyediakan API key untuk mengakses banyak model dari berbagai penyedia (Anthropic, OpenAI, Gemini, DeepSeek, Qwen, dll) lewat **satu key**. API-nya **OpenAI-compatible** — persis seperti OpenAI, hanya berbeda **base URL**.

| Aspek | Nilai |
|---|---|
| Base URL | `https://ai.sumopod.com/v1` |
| Endpoint chat | `https://ai.sumopod.com/v1/chat/completions` |
| Auth | Bearer token (header `Authorization: Bearer sk-...`) |
| Format key | `sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx` |
| Kompatibilitas | OpenAI SDK / tools (cukup ganti `baseURL`) |
| Buat key | Dashboard SumoPod → **AI → API Keys** (`https://sumopod.com/dashboard/ai/keys`) |

**Catatan keamanan:** set **budget limit** per key di dashboard untuk membatasi biaya, pakai key berbeda per proyek, dan jangan pernah membagikan key. Di aplikasi ini key tenant disimpan **terenkripsi** dan tak pernah dikirim balik ke frontend (lihat `PHASE-AI-2` §3).

---

## 2. Cara Kerja di Aplikasi

Karena OpenAI-compatible, `SumoPodProvider` cukup **extend `OpenAiProvider`** dan override endpoint. `OpenAiProvider` di-refactor supaya base URL dan label provider bisa di-override.

**File:** `app/Services/Ai/OpenAiProvider.php` (jadi base yang extensible)

```php
public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult
{
    $response = Http::withToken($this->apiKey)->timeout(60)->post($this->endpoint(), [
        'model' => $this->model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "DATA (JSON):\n".json_encode($context, JSON_UNESCAPED_UNICODE)."\n\nPERTANYAAN:\n".$userPrompt],
        ],
    ]);

    if ($response->failed()) {
        throw new RuntimeException($this->providerLabel().' API error: '.$response->status());
    }

    return new AiResult(
        text: $response->json('choices.0.message.content', ''),
        tokensUsed: $response->json('usage.total_tokens'),
    );
}

protected function endpoint(): string { return 'https://api.openai.com/v1/chat/completions'; }
protected function providerLabel(): string { return 'OpenAI'; }
```

**File:** `app/Services/Ai/SumoPodProvider.php` (baru)

```php
<?php

namespace App\Services\Ai;

class SumoPodProvider extends OpenAiProvider
{
    protected function endpoint(): string
    {
        return 'https://ai.sumopod.com/v1/chat/completions';
    }

    protected function providerLabel(): string
    {
        return 'SumoPod';
    }
}
```

**File:** `app/Services/Ai/AiProviderFactory.php` — tambah cabang `match`:

```php
return match ($provider) {
    'sumopod'   => new SumoPodProvider($key, $model),
    'gemini'    => new GeminiProvider($key, $model),
    'openai'    => new OpenAiProvider($key, $model),
    'anthropic' => new AnthropicProvider($key, $model),
    default     => throw new InvalidArgumentException("Provider AI tidak dikenal: {$provider}"),
};
```

---

## 3. Konfigurasi

**File:** `config/ai.php`

```php
'default' => env('AI_DEFAULT_PROVIDER', 'sumopod'),

'models' => [
    'sumopod'   => env('AI_SUMOPOD_MODEL', 'gpt-4o-mini'),
    'gemini'    => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
    'openai'    => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
    'anthropic' => env('AI_ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
],
```

**File:** `config/services.php` — `'sumopod' => ['key' => env('SUMOPOD_API_KEY')]`.

**File:** `.env.example` / `.env`

```dotenv
AI_DEFAULT_PROVIDER=sumopod
SUMOPOD_API_KEY=sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
AI_SUMOPOD_MODEL=gpt-4o-mini
# Free tier memakai provider = AI_DEFAULT_PROVIDER, jadi isi key SumoPod di sini
AI_FREE_TIER_KEY=
AI_FREE_TIER_DAILY_LIMIT=5
```

Setelah mengubah `.env`: `php artisan config:clear`.

**Validasi & UI:** `SettingsController::update` menerima `ai_provider` `in:sumopod,gemini,openai,anthropic`; dropdown provider di `Settings/Index.vue` menampilkan opsi **SumoPod** dan default "SumoPod gratis".

---

## 4. Model yang Tersedia

Satu key SumoPod mengakses banyak model — cukup isi **nama model** di field Model (BYOK) atau `AI_SUMOPOD_MODEL`. Pilihan umum (harga = input/1M token, indikatif, cek dashboard untuk yang terbaru):

| Model | Penyedia | Context | Input /1M |
|---|---|---|---|
| `gpt-4o-mini` | OpenAI | 128K | $0.15 |
| `gpt-4.1-mini` | OpenAI | 1M | $0.40 |
| `gpt-5-mini` | OpenAI | 272K | $0.25 |
| `claude-haiku-4-5` | Anthropic | 200K | $1.00 |
| `claude-sonnet-5` | Anthropic | 1M | $2.00 |
| `claude-opus-4-8` | Anthropic | 1M | $5.00 |
| `gemini/gemini-2.5-flash-lite` | Gemini | 1M | $0.10 |
| `gemini/gemini-2.5-flash` | Gemini | 1M | $0.30 |
| `deepseek-v4-flash` | DeepSeek | 1M | $0.14 |
| `qwen3.6-flash` | Alibaba | 1M | $0.25 |
| `text-embedding-3-small` | OpenAI | 8K | $0.02 |

> Daftar penuh & harga terkini ada di dashboard SumoPod. Default aplikasi `gpt-4o-mini` dipilih karena murah dan cukup untuk analisis agregat POS.

---

## 5. Tests

`tests/Feature/Ai/AiProviderTest.php` — `SumoPodProvider` dengan `Http::fake()`: assert request ke `https://ai.sumopod.com/v1/chat/completions`, header `Authorization: Bearer sk-...`, body model/messages, parsing `AiResult`, dan pesan error berlabel `SumoPod`.

`tests/Feature/Ai/AiProviderFactoryTest.php` — factory memilih `SumoPodProvider` sebagai default (tanpa provider tenant) dan untuk tenant BYOK `ai_provider='sumopod'`.

Jalankan: `php artisan test --compact --filter=Ai`.

---

## 6. Checklist

- [x] `SumoPodProvider` extends `OpenAiProvider`, override `endpoint()`/`providerLabel()`
- [x] `OpenAiProvider` di-refactor jadi base extensible (props `protected`)
- [x] `AiProviderFactory` cabang `sumopod`
- [x] `config/ai.php` default `sumopod` + `models.sumopod`; `config/services.php` key
- [x] Validasi `SettingsController` + dropdown Settings UI
- [x] `.env.example` (`AI_DEFAULT_PROVIDER`, `SUMOPOD_API_KEY`, `AI_SUMOPOD_MODEL`)
- [x] Test provider (`Http::fake`) + factory hijau
- [x] `vendor/bin/pint --dirty --format agent` bersih
- [x] Changelog `[DECISION]` dicatat
