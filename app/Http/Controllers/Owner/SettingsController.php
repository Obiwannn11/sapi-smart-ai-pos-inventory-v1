<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const MCP_TOKEN_NAME = 'mcp-client';

    public function index(): Response
    {
        $user = auth()->user();
        $tenant = $user->tenant;
        $usedToday = AiUsage::whereDate('date', now()->toDateString())->value('count') ?? 0;
        $dailyLimit = (int) config('ai.free_tier.daily_limit');

        return Inertia::render('Owner/Settings/Index', [
            'tenant' => [
                'name' => $tenant->name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                // AI — TANPA membocorkan key
                'ai_provider' => $tenant->ai_provider,
                'ai_model' => $tenant->ai_model,
                'ai_key_set' => filled($tenant->ai_api_key),
            ],
            'aiFreeTier' => [
                'daily_limit' => $dailyLimit,
                'remaining' => max(0, $dailyLimit - $usedToday),
            ],
            'mcp' => [
                'token_set' => $user->tokens()->where('name', self::MCP_TOKEN_NAME)->exists(),
                'endpoint' => url('/mcp/business'),
            ],
        ]);
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

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'ai_provider' => 'nullable|in:sumopod,gemini,openai,anthropic',
            'ai_api_key' => 'nullable|string|max:255',
            'ai_model' => 'nullable|string|max:100',
        ]);

        // Jangan overwrite key jadi null kalau field dikosongkan tanpa maksud hapus.
        if (blank($validated['ai_api_key'] ?? null)) {
            unset($validated['ai_api_key']);
        }

        auth()->user()->tenant->update($validated);

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
