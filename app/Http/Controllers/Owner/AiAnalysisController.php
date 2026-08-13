<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Services\Ai\AiQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAnalysisController extends Controller
{
    public function __construct(protected AiQuota $quota) {}

    public function index(): Response
    {
        return Inertia::render('Owner/AiAnalysis/Index', [
            'analyses' => AiAnalysis::latest()->take(20)->get(),
            'aiQuota' => $this->quotaSnapshot(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:general,discount,profit_projection,custom',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'prompt' => 'nullable|string|max:1000',
        ]);

        // Ditolak DI SINI, bukan di antrean.
        //
        // `RunAiAnalysisJob` tetap memeriksa hal yang sama — ia harus, karena
        // job bisa mengantre lebih lama daripada jatah yang tersisa. Tapi
        // penolakan di sana lahir sebagai baris berstatus `failed` yang baru
        // terlihat beberapa detik setelah tombol ditekan, dan riwayat owner
        // terisi kegagalan yang sebetulnya bisa dicegah sebelum apa pun dibuat.
        // Tombolnya sudah dimatikan di layar; ini yang menjaganya tetap benar
        // saat permintaan datang tanpa lewat tombol itu.
        if ($rejection = $this->quotaRejection()) {
            return back()->with('error', $rejection);
        }

        $analysis = AiAnalysis::create([
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'status' => AiAnalysis::STATUS_PENDING,
            'params' => ['from' => $validated['from'], 'to' => $validated['to']],
            'prompt' => $validated['prompt'] ?? null,
        ]);

        RunAiAnalysisJob::dispatch($analysis->id);

        return back()->with('success', 'Analisis sedang diproses.');
    }

    public function show(AiAnalysis $aiAnalysis): Response
    {
        return Inertia::render('Owner/AiAnalysis/Index', [
            'analyses' => AiAnalysis::latest()->take(20)->get(),
            'active' => $aiAnalysis,
            // Ikut dikirim di sini juga: `show()` me-render komponen yang SAMA,
            // jadi melewatkannya membuat angkanya hilang begitu satu analisis
            // dibuka lewat tautannya (`[BL-062]`(a)).
            'aiQuota' => $this->quotaSnapshot(),
        ]);
    }

    /**
     * @return array{using_free_tier: bool, daily_limit: int, used: int, remaining: int, limit_source: string, plan_name: string|null}
     */
    protected function quotaSnapshot(): array
    {
        return $this->quota->snapshotFor(auth()->user()->tenant);
    }

    /**
     * Alasan permintaan ini tidak bisa dilayani, atau null bila jatahnya ada.
     *
     * Kalimatnya sejalan dengan yang dilontarkan `RunAiAnalysisJob::assertQuota()`
     * — dua permukaan, satu keadaan, jadi owner tidak menerima dua penjelasan
     * berbeda untuk penolakan yang sama.
     */
    protected function quotaRejection(): ?string
    {
        $snapshot = $this->quotaSnapshot();

        if (! $snapshot['using_free_tier']) {
            return null;
        }

        if ($snapshot['daily_limit'] <= 0) {
            return 'Paket ini tidak menyertakan analisis AI. Isi API key sendiri di Pengaturan, atau naikkan paket.';
        }

        if ($snapshot['remaining'] <= 0) {
            return "Kuota AI hari ini habis ({$snapshot['daily_limit']}/hari). Kuota berulang besok, atau isi API key sendiri di Pengaturan untuk pemakaian tanpa batas.";
        }

        return null;
    }
}
