<?php

namespace App\Http\Controllers\Owner\Settings;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Integrasi & Kredensial — kunci API penyedia AI dan token akses MCP.
 *
 * Pecahan ketiga dari halaman "Profil Usaha" lama (`[BL-039]`), dan satu-satunya
 * yang memegang RAHASIA. Alasan utama pemecahan itu ada di sini: sampai
 * sekarang satu tombol simpan bisa menulis kunci API penyedia AI dalam
 * permintaan yang sama dengan mematikan modul dan mengganti dasar tarif.
 *
 * Sakelar `ai_enabled` sengaja TIDAK ikut ke sini meski berkerabat: ia
 * kapabilitas modul, tempatnya di Cara Kerja Sistem. Yang ikut ke sini hanya
 * BACAANNYA, supaya halaman ini bisa mengatakan terus terang bahwa kunci yang
 * tersimpan tidak akan dipakai selama modulnya mati.
 */
class IntegrationController extends Controller
{
    private const MCP_TOKEN_NAME = 'mcp-client';

    public function index(): Response
    {
        $user = auth()->user();
        $tenant = $user->tenant;
        // Lewat AiQuota, bukan config: angka yang dibacakan ke owner dan angka
        // yang menolak permintaannya di antrean wajib berasal dari satu tempat.
        $quota = app(AiQuota::class);

        return Inertia::render('Owner/Settings/Integrations', [
            'tenant' => [
                // AI — TANPA membocorkan key
                'ai_provider' => $tenant->ai_provider,
                'ai_model' => $tenant->ai_model,
                'ai_key_set' => filled($tenant->ai_api_key),
                // Hanya dibaca, tidak bisa diubah dari halaman ini.
                'ai_enabled' => $tenant->ai_enabled,
            ],
            // Versi LENGKAP dari blok yang sama yang muncul ringkas di AI
            // Analysis (`[BL-062]`). Di sini ia konteks untuk keputusan BYOK,
            // jadi angkanya dibedah — batas, terpakai, sisa, dan dari mana
            // batasnya datang; di sana ia peringatan sebelum bertindak.
            'aiQuota' => $quota->snapshotFor($tenant),
            'mcp' => [
                'token_set' => $user->tokens()->where('name', self::MCP_TOKEN_NAME)->exists(),
                'endpoint' => url('/mcp/business'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_provider' => 'nullable|in:sumopod,gemini,openai,anthropic',
            'ai_api_key' => 'nullable|string|max:255',
            'ai_model' => 'nullable|string|max:100',
        ]);

        // Jangan overwrite key jadi null kalau field dikosongkan tanpa maksud hapus.
        if (blank($validated['ai_api_key'] ?? null)) {
            unset($validated['ai_api_key']);
        }

        auth()->user()->tenant->update($validated);

        return back()->with('success', 'Integrasi & kredensial berhasil disimpan.');
    }

    /**
     * Buat (atau rotasi) token akses MCP milik owner. Plaintext hanya
     * di-flash sekali; tidak pernah disimpan/ditampilkan ulang.
     */
    public function generateMcpToken(): RedirectResponse
    {
        $user = auth()->user();
        $user->tokens()->where('name', self::MCP_TOKEN_NAME)->delete();

        $token = $user->createToken(self::MCP_TOKEN_NAME, ['mcp:use'])->plainTextToken;

        return back()
            ->with('mcpToken', $token)
            ->with('success', 'Token MCP dibuat. Salin sekarang — tidak akan ditampilkan lagi.');
    }

    public function revokeMcpToken(): RedirectResponse
    {
        auth()->user()->tokens()->where('name', self::MCP_TOKEN_NAME)->delete();

        return back()->with('success', 'Token MCP dicabut.');
    }
}
