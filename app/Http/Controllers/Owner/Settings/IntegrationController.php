<?php

namespace App\Http\Controllers\Owner\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ai\AiQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

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

    /**
     * Ability link konektor (`[BL-102]`). Link adalah token Sanctum biasa yang
     * hanya berlaku di rute konektor; rute bertoken lain menolaknya sejak
     * `[BL-112]`.
     */
    private const CONNECTOR_ABILITY = 'connector:read';

    /**
     * Rute data yang dibuka AI pengguna. Ditulis sebagai path, bukan nama rute:
     * aplikasi ini tidak memakai Ziggy, dan halaman menampilkan URL utuhnya.
     */
    private const CONNECTOR_PATH = '/api/v1/connector/summary';

    /**
     * Pilihan masa berlaku link dalam hari. Keputusan pemilik 2026-09-15: tidak
     * ada pilihan tanpa batas.
     *
     * @var list<int>
     */
    private const CONNECTOR_LIFETIMES = [7, 30];

    /**
     * Nama token yang dicabut kode lain BERDASARKAN NAMA. Link berlabel sama
     * akan ikut terhapus saat token MCP dibuat ulang atau kasir login.
     *
     * @var list<string>
     */
    private const RESERVED_TOKEN_NAMES = [self::MCP_TOKEN_NAME, 'mobile-app'];

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
            'connector' => [
                'endpoint' => url(self::CONNECTOR_PATH),
                'lifetimes' => self::CONNECTOR_LIFETIMES,
                'links' => $this->connectorLinks($user),
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

    /**
     * Terbitkan satu link konektor. URL utuhnya hanya di-flash sekali, sama
     * seperti token MCP: yang tersimpan di basis data hanya hash-nya.
     */
    public function generateConnectorLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:40', Rule::notIn(self::RESERVED_TOKEN_NAMES)],
            'lifetime_days' => ['required', 'integer', Rule::in(self::CONNECTOR_LIFETIMES)],
            // Keputusan pemilik 2026-09-15: bahayanya disampaikan lewat langkah,
            // bukan kalimat. Server ikut menuntutnya supaya centang di dialog
            // bukan satu-satunya pagar.
            'acknowledged' => ['accepted'],
        ], [
            'label.required' => 'Beri nama link ini, misalnya nama AI yang akan memakainya.',
            'label.not_in' => 'Nama ini dipakai sistem. Pilih nama lain.',
            'lifetime_days.in' => 'Pilih masa berlaku 7 atau 30 hari.',
            'acknowledged.accepted' => 'Centang pernyataan di atas untuk membuat link.',
        ]);

        $expiresAt = now()->addDays((int) $validated['lifetime_days']);

        $plainTextToken = $request->user()
            ->createToken($validated['label'], [self::CONNECTOR_ABILITY], $expiresAt)
            ->plainTextToken;

        return back()
            ->with('connectorLink', [
                'label' => $validated['label'],
                // `|` pada token Sanctum di-encode sejak awal: pengambil halaman
                // AI yang mengubah atau memotong karakter itu belum diuji.
                'url' => url(self::CONNECTOR_PATH).'?token='.rawurlencode($plainTextToken),
                'expires_at' => $expiresAt->toIso8601String(),
            ])
            ->with('success', 'Link dibuat. Salin sekarang, link tidak akan ditampilkan lagi.');
    }

    public function revokeConnectorLink(Request $request, int $token): RedirectResponse
    {
        // Diperiksa ability-nya, bukan cukup id: rute ini tidak boleh jadi jalan
        // mencabut token MCP atau token login kasir.
        $link = $request->user()->tokens()->whereKey($token)->first();

        if ($link === null || ! $this->isConnectorLink($link)) {
            abort(404);
        }

        $link->delete();

        return back()->with('success', "Link \"{$link->name}\" dicabut.");
    }

    /**
     * Link konektor milik owner yang masih berlaku, terbaru dulu.
     *
     * @return list<array{id: int, label: string, expires_at: string, last_used_at: string|null}>
     */
    private function connectorLinks(User $user): array
    {
        return $user->tokens()
            ->where('expires_at', '>', now())
            ->latest()
            ->get()
            ->filter(fn (PersonalAccessToken $token) => $this->isConnectorLink($token))
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'label' => $token->name,
                'expires_at' => $token->expires_at->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * `in_array`, bukan `$token->can()`: token `*` (login mobile) juga lolos
     * `can('connector:read')`, padahal ia bukan link yang diterbitkan di sini.
     */
    private function isConnectorLink(PersonalAccessToken $token): bool
    {
        return in_array(self::CONNECTOR_ABILITY, $token->abilities, true);
    }
}
