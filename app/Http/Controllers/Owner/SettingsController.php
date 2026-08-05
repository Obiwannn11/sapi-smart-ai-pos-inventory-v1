<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\Ai\AiQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const MCP_TOKEN_NAME = 'mcp-client';

    public function index(): Response
    {
        $user = auth()->user();
        $tenant = $user->tenant;
        // Lewat AiQuota, bukan config: angka yang dibacakan ke owner dan angka
        // yang menolak permintaannya di antrean wajib berasal dari satu tempat.
        $quota = app(AiQuota::class);

        return Inertia::render('Owner/Settings/Index', [
            'tenant' => [
                'name' => $tenant->name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                // Jenis usaha diatur DI SINI, oleh pemiliknya sendiri.
                //
                // Dulu kolom ini sengaja tidak ada di halaman ini dan hanya bisa
                // diubah pemilik SaaS, dengan alasan ia dasar penetapan harga.
                // Alasannya benar soal akibatnya, keliru soal siapa yang berhak:
                // yang tahu jenis usahanya adalah pemilik toko, dan mengubah
                // keterangan usaha orang tanpa sepengetahuannya bukan kewenangan
                // penyedia layanan sekalipun angkanya ikut bergeser.
                //
                // Yang menjaga tagihan tetap bisa dijelaskan bukan larangan
                // mengedit, melainkan dua hal yang sudah ada: `effective_from`
                // pada aturan harga, dan `invoices.pricing_context` yang
                // membekukan keadaan tenant saat tagihan terbit. Tagihan yang
                // sudah keluar tidak berubah oleh suntingan hari ini.
                'business_type' => $tenant->business_type,
                // AI — TANPA membocorkan key
                'ai_provider' => $tenant->ai_provider,
                'ai_model' => $tenant->ai_model,
                'ai_key_set' => filled($tenant->ai_api_key),
            ],
            'businessTypes' => config('pricing-dimensions.business_type.options', []),
            'features' => [
                'kitchen_queue_enabled' => $tenant->kitchen_queue_enabled,
                'self_order_enabled' => $tenant->self_order_enabled,
                'ai_enabled' => $tenant->ai_enabled,
                // Bukan kapabilitas modul seperti tiga di atas, melainkan
                // ATURAN KERJA: menyalakannya menahan tombol bayar sampai tiap
                // saran dijawab. Dikelompokkan di sini karena tempatnya sama di
                // mata owner, tapi sengaja TIDAK masuk Tenant::hasFeature() —
                // ia tidak menggerbangi rute atau modul apa pun ([BL-025]).
                'upsell_mandatory' => $tenant->upsell_mandatory,
                // Bukan boolean seperti yang lain, dan bukan kapabilitas modul:
                // ini cara outlet mengenali pesanannya. Ikut di sini karena
                // tempatnya sama di mata owner ([BL-026]).
                'order_identity_mode' => $tenant->order_identity_mode,
            ],
            'orderIdentityModes' => Tenant::orderIdentityModes(),
            // Dipakai memperingatkan owner sebelum ia mematikan fitur yang
            // masih ada pekerjaan berjalan di baliknya.
            'featureWarnings' => [
                'active_self_orders' => Transaction::where('source', Transaction::SOURCE_SELF_ORDER)
                    ->whereNotNull('fulfillment_status')
                    ->where('fulfillment_status', '!=', Transaction::FULFILLMENT_DONE)
                    ->count(),
                'pending_analyses' => AiAnalysis::where('status', AiAnalysis::STATUS_PENDING)->count(),
            ],
            'aiFreeTier' => [
                'daily_limit' => $quota->dailyLimitFor($tenant),
                'remaining' => $quota->remainingFor($tenant),
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
            // `sometimes` + `required`, bukan `nullable`: sejak kolom ini punya
            // bawaan, tidak ada lagi keadaan "belum dijawab" yang sah, jadi
            // nilai KOSONG ditolak. Tapi field yang tidak dikirim sama sekali
            // berarti "jangan sentuh" — bukan "kosongkan". Bedanya penting di
            // endpoint yang menerima beberapa bagian form sekaligus: aturan
            // `required` polos akan membuat pemanggil yang tidak berkepentingan
            // dengan tipe usaha gagal menyimpan apa pun.
            'business_type' => ['sometimes', 'required', Rule::in(array_keys(config('pricing-dimensions.business_type.options', [])))],
            'ai_provider' => 'nullable|in:sumopod,gemini,openai,anthropic',
            'ai_api_key' => 'nullable|string|max:255',
            'ai_model' => 'nullable|string|max:100',
            'kitchen_queue_enabled' => 'boolean',
            'self_order_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'upsell_mandatory' => 'boolean',
            // `sometimes` dengan alasan yang sama seperti business_type di
            // atas: field yang tidak dikirim berarti "jangan sentuh", bukan
            // "kembalikan ke none".
            'order_identity_mode' => ['sometimes', 'required', Rule::in(array_keys(Tenant::orderIdentityModes()))],
        ]);

        // Jangan overwrite key jadi null kalau field dikosongkan tanpa maksud hapus.
        if (blank($validated['ai_api_key'] ?? null)) {
            unset($validated['ai_api_key']);
        }

        auth()->user()->tenant->update($validated);

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
