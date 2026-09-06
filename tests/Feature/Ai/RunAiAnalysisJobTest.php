<?php

use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Models\AiUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\AiQuota;
use App\Services\AiContextService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'ai.default' => 'gemini',
        'ai.free_tier.key' => 'shared-free-key',
        'ai.free_tier.daily_limit' => 5,
    ]);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

/**
 * Helper: buat analisis pending untuk tenant utama lalu jalankan job sinkron.
 */
function runAnalysis(array $overrides = []): AiAnalysis
{
    $analysis = AiAnalysis::create(array_merge([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'type' => AiAnalysis::TYPE_GENERAL,
        'status' => AiAnalysis::STATUS_PENDING,
        'params' => ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()],
    ], $overrides));

    (new RunAiAnalysisJob($analysis->id))->handle(
        app(AiContextService::class),
        app(AiProviderFactory::class),
    );

    return $analysis->fresh();
}

function fakeGeminiSuccess(): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Hasil analisis AI.']]]]],
            'usageMetadata' => ['totalTokenCount' => 321],
        ]),
    ]);
}

test('successful run completes the analysis and records free tier usage', function () {
    fakeGeminiSuccess();

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_COMPLETED)
        ->and($analysis->result)->toBe('Hasil analisis AI.')
        ->and($analysis->tokens_used)->toBe(321);

    $usage = AiUsage::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->whereDate('date', now())
        ->value('count');

    expect($usage)->toBe(1);
});

test('provider error marks the analysis as failed', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_FAILED)
        ->and($analysis->error)->toContain('Gemini API error');
});

test('exhausted free tier quota fails with a quota message and does not call the provider', function () {
    Http::fake();

    AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 5,
    ]);

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_FAILED)
        ->and($analysis->error)->toContain('Kuota AI hari ini habis');

    Http::assertNothingSent();
});

// --- Kuota per paket ---
// Batasnya bukan lagi satu angka untuk semua: paket yang menyetel `ai_daily`
// mendahului bawaan config. Tanpa test ini, kolom batas paket bisa terisi rapi
// di panel tanpa satu pun permintaan benar-benar dijatah olehnya.

test('kuota mengikuti batas paket, bukan bawaan platform', function () {
    fakeGeminiSuccess();

    $plan = Plan::factory()->create(['limits' => ['ai_daily' => 10]]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]);

    // Di atas bawaan config (5), di bawah batas paket (10).
    AiUsage::create(['tenant_id' => $this->tenant->id, 'date' => now()->toDateString(), 'count' => 7]);

    expect(runAnalysis()->status)->toBe(AiAnalysis::STATUS_COMPLETED);
});

test('analisis kedua di hari yang sama tetap berjalan dan menaikkan hitungannya', function () {
    fakeGeminiSuccess();

    runAnalysis();
    $kedua = runAnalysis();

    // Sebelumnya gagal di sini: hitungan hari ini dicari dengan kunci tanggal
    // apa adanya, padahal kolomnya tersimpan sebagai datetime — barisnya tak
    // ketemu, lalu penyisipan keduanya ditolak indeks unik.
    expect($kedua->status)->toBe(AiAnalysis::STATUS_COMPLETED)
        ->and(AiUsage::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->value('count'))->toBe(2);
});

test('paket tanpa jatah AI menolak permintaan tanpa memanggil provider', function () {
    Http::fake();

    $plan = Plan::factory()->create(['limits' => ['ai_daily' => 0]]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]);

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_FAILED)
        ->and($analysis->error)->toContain('tidak menyertakan analisis AI');

    Http::assertNothingSent();
});

test('angka yang dibacakan ke owner sama dengan angka yang menjatah antreannya', function () {
    $plan = Plan::factory()->create(['limits' => ['ai_daily' => 10]]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]);
    AiUsage::create(['tenant_id' => $this->tenant->id, 'date' => now()->toDateString(), 'count' => 4]);

    $quota = app(AiQuota::class);

    expect($quota->dailyLimitFor($this->tenant->fresh()))->toBe(10)
        ->and($quota->remainingFor($this->tenant->fresh()))->toBe(6);
});

test('BYOK tenant bypasses quota and does not increment usage', function () {
    fakeGeminiSuccess();

    $this->tenant->update(['ai_provider' => 'gemini', 'ai_api_key' => 'byok-key']);

    // Already at the free tier limit — BYOK must still run.
    AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 5,
    ]);

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_COMPLETED);

    $usage = AiUsage::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->whereDate('date', now())
        ->value('count');

    expect($usage)->toBe(5);
});

// --- Bentuk prompt ---
// Versi pertama hanya meminta jawaban "ringkas, actionable, dengan angka
// konkret", dan yang kembali adalah nasihat yang benar untuk kafe mana pun:
// "perbaiki layanan dan atmosfer", "diversifikasi menu". Larangannya kini
// ditulis eksplisit, dan tiap tipe analisis meminta susunan bagiannya sendiri
// — hal yang tidak akan ketahuan hilang tanpa dipatok di sini, karena jawaban
// yang buruk tetap terlihat seperti jawaban.

/**
 * Teks prompt yang benar-benar dikirim ke provider pada permintaan terakhir.
 */
function sentPrompt(): string
{
    $text = '';

    Http::assertSent(function ($request) use (&$text) {
        $text = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return true;
    });

    return $text;
}

test('prompt melarang saran umum dan mewajibkan dasar angka di tiap rekomendasi', function () {
    fakeGeminiSuccess();

    runAnalysis();

    expect(sentPrompt())
        ->toContain('DILARANG memberi saran yang bisa ditempel ke toko mana pun')
        ->toContain('tingkatkan pelayanan')
        ->toContain('diversifikasi menu')
        ->toContain('perkiraan dampaknya dalam rupiah atau persen')
        ->toContain('Belum bisa dijawab dari data:')
        // `others` bukan nama produk — tanpa kalimat ini model pernah
        // menyebutnya sebagai barang yang bisa didiskon.
        ->toContain('ia bukan produk bernama "others"')
        // Penomoran yang berulang "1." tidak cuma soal renderer.
        ->toContain('Nomori berurutan');
});

test('tiap tipe analisis meminta susunan bagiannya sendiri', function () {
    fakeGeminiSuccess();

    runAnalysis(['type' => AiAnalysis::TYPE_GENERAL]);
    expect(sentPrompt())->toContain('## Yang Menyimpang')->toContain('## Tindakan');

    runAnalysis(['type' => AiAnalysis::TYPE_DISCOUNT]);
    expect(sentPrompt())
        ->toContain('## Kandidat Diskon')
        ->toContain('## Titik Impas')
        ->toContain('jatuh di bawah 0%');

    runAnalysis(['type' => AiAnalysis::TYPE_PROFIT_PROJECTION]);
    expect(sentPrompt())
        ->toContain('## Profit Periode Ini')
        ->toContain('## Pendorong & Penghambat');
});

test('pertanyaan sendiri dibawa apa adanya tapi tetap dipagari datanya', function () {
    fakeGeminiSuccess();

    runAnalysis([
        'type' => AiAnalysis::TYPE_CUSTOM,
        'prompt' => 'Menu apa yang paling menguntungkan?',
    ]);

    expect(sentPrompt())
        ->toContain('PERTANYAAN: Menu apa yang paling menguntungkan?')
        ->toContain('jangan diganti saran umum');
});

test('kalimat pajak hanya ikut untuk tenant yang memungut', function () {
    fakeGeminiSuccess();

    runAnalysis();
    expect(sentPrompt())->not->toContain('PAJAK:');

    $this->tenant->update(['tax_enabled' => true]);

    runAnalysis();
    expect(sentPrompt())->toContain('PAJAK:')
        ->toContain('jangan dari `revenue`');
});
