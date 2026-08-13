<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AiQuotaPolicy;
use App\Models\PlatformAuditLog;
use App\Services\Ai\AiQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kuota AI sebagai kebijakan yang bisa diatur, bukan angka di `.env`
 * (`[BL-047]`(c)).
 *
 * Dua hal berbeda diatur dari sini, dan bedanya sengaja terlihat sampai ke
 * tombolnya:
 *
 *   - KEBIJAKAN — bawaan platform dan promo berjangka waktu. Mengubah jatah
 *     mulai kapan sampai kapan.
 *   - RESET — mengembalikan hitungan yang SUDAH terpakai hari ini kepada semua
 *     tenant. Tidak mengubah jatah siapa pun, hanya membelanjakannya ulang.
 *
 * `[BL-047]`(d) meminta keduanya tidak pernah jadi satu tombol, dan alasannya
 * praktis: yang pertama bisa dibatalkan dengan menyunting barisnya, yang kedua
 * tidak bisa dibatalkan sama sekali.
 *
 * Tiap perubahan tercatat sebagai kejadian `sensitive`. Kuota bersama adalah
 * tagihan kunci bersama, jadi angka yang naik tanpa jejak adalah tagihan yang
 * naik tanpa penjelasan.
 *
 * Angka pemakaian yang ditampilkan halaman ini sengaja hanya berupa jumlah
 * gabungan — lihat `AiQuota::usageTodaySummary()` untuk batas privasinya.
 */
class AiQuotaController extends Controller
{
    public function __construct(private readonly AiQuota $quota) {}

    public function index(): Response
    {
        PlatformAuditLog::recordRoutine('ai-quota.index');

        $policies = AiQuotaPolicy::query()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $baseline = AiQuotaPolicy::winnerFor(AiQuotaPolicy::MODE_BASELINE);
        $bonus = AiQuotaPolicy::winnerFor(AiQuotaPolicy::MODE_BONUS);

        return Inertia::render('Platform/AiQuota/Index', [
            'policies' => $policies->map(fn (AiQuotaPolicy $policy) => $policy->summary() + [
                'id' => $policy->id,
                'is_effective' => $policy->isEffective(),
                'has_ended' => $policy->hasEnded(),
            ]),
            // Angka yang BENAR-BENAR berlaku hari ini, dirakit di server. Panel
            // tidak menghitungnya ulang dari daftar kebijakan: aturan "yang
            // terbaru berlaku menang" yang ditulis dua kali akan berselisih
            // pada hari pertama dua kebijakan tumpang tindih, dan yang salah
            // justru layar yang dipakai memutuskan.
            'effective' => [
                'baseline' => $baseline?->daily_limit,
                'baseline_label' => $baseline?->label,
                'bonus' => $bonus?->daily_limit ?? 0,
                'bonus_label' => $bonus?->label,
            ],
            // Lapis terakhir yang berlaku saat tak ada kebijakan bawaan sama
            // sekali. Ditampilkan supaya "5" tidak terbaca sebagai angka yang
            // seseorang tetapkan padahal ia bawaan berkas config.
            'configDefault' => (int) config('ai.free_tier.daily_limit'),
            'usageToday' => $this->quota->usageTodaySummary(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());

        $policy = AiQuotaPolicy::create($validated);

        PlatformAuditLog::record('ai-quota.create', $policy, $policy->summary());

        return back()->with('success', "Kebijakan {$policy->label} tersimpan.");
    }

    /**
     * Sunting kebijakan, termasuk yang SUDAH berlaku.
     *
     * Berbeda dari `pricing_rules`, yang melarang penyuntingan begitu sebuah
     * aturan berlaku. Larangan di sana melindungi dasar harga periode yang sudah
     * ditagihkan; di sini tidak ada yang setara — kuota dinilai langsung setiap
     * kali analisis diminta, tidak pernah surut, dan tidak pernah masuk tagihan
     * siapa pun.
     *
     * Yang justru berbahaya adalah kebalikannya: satu-satunya cara menghentikan
     * promo yang telanjur kebablasan adalah memajukan tanggal akhirnya, dan
     * melarang suntingan berarti membiarkannya berjalan sampai tanggal yang
     * sudah telanjur salah — dengan tagihan kunci bersama yang ikut berjalan.
     */
    public function update(Request $request, AiQuotaPolicy $aiQuotaPolicy): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());

        $sebelum = $aiQuotaPolicy->summary();

        $aiQuotaPolicy->update($validated);

        // Nilai lama DAN baru, karena pertanyaan yang muncul saat kuota
        // dipersoalkan selalu "naik dari berapa", bukan "sekarang berapa".
        PlatformAuditLog::record('ai-quota.update', $aiQuotaPolicy, [
            'before' => $sebelum,
            'after' => $aiQuotaPolicy->fresh()->summary(),
        ]);

        return back()->with('success', "Kebijakan {$aiQuotaPolicy->label} diperbarui.");
    }

    /**
     * Hentikan kebijakan seketika.
     *
     * `SoftDeletes`, dengan alasan yang sama seperti `pricing_rules`: pertanyaan
     * "kenapa bulan lalu kuota kami 20?" datang setelah kebijakannya dicabut,
     * bukan sebelumnya.
     */
    public function destroy(AiQuotaPolicy $aiQuotaPolicy): RedirectResponse
    {
        PlatformAuditLog::record('ai-quota.delete', $aiQuotaPolicy, $aiQuotaPolicy->summary() + [
            'was_effective' => $aiQuotaPolicy->isEffective(),
        ]);

        $aiQuotaPolicy->delete();

        return back()->with('success', 'Kebijakan dihentikan. Jatah kembali mengikuti lapis di bawahnya mulai permintaan berikutnya.');
    }

    /**
     * Kembalikan jatah yang sudah terpakai hari ini untuk seluruh tenant.
     *
     * Tidak bisa dibatalkan dan tidak menyimpan angka sebelumnya per tenant —
     * yang tercatat di jejak audit hanyalah berapa tenant yang tersentuh, karena
     * rincian per tenant bukan hal yang boleh dilihat panel platform.
     */
    public function resetUsage(): RedirectResponse
    {
        $tenants = $this->quota->resetUsageToday();

        PlatformAuditLog::record('ai-quota.reset', null, [
            'date' => now()->toDateString(),
            'tenants_affected' => $tenants,
        ]);

        return back()->with('success', $tenants === 0
            ? 'Belum ada pemakaian hari ini — tidak ada yang perlu dikembalikan.'
            : "Kuota hari ini dikembalikan untuk {$tenants} tenant.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validationRules(): array
    {
        return [
            'label' => ['required', 'string', 'max:60'],
            'mode' => ['required', 'string', Rule::in(AiQuotaPolicy::allModes())],
            // Nol sah untuk `baseline` — artinya platform tidak memberi jatah
            // sama sekali kepada paket yang tak menetapkan batasnya sendiri.
            // Untuk `bonus` nol tidak dilarang di sini karena artinya jelas
            // (promo tanpa isi) dan panel sudah menuntun angkanya; yang perlu
            // dijaga justru batas atasnya, agar salah ketik satu digit tidak
            // menjadi tagihan kunci bersama yang tak terduga.
            'daily_limit' => ['required', 'integer', 'min:0', 'max:1000'],
            // Tanggal mundur diizinkan, tidak seperti `pricing_rules`: kuota
            // dinilai langsung dan tidak pernah berlaku surut, jadi kebijakan
            // bertanggal kemarin hanya berarti "berlaku sekarang".
            'effective_from' => ['required', 'date'],
            'effective_until' => ['present', 'nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
