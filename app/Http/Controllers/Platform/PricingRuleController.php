<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PricingRule;
use App\Models\PricingRuleCondition;
use App\Services\Pricing\DimensionRegistry;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aturan harga sebagai data: paket jalur normal, dan aturan bersyarat yang
 * menentukan tarif jalur subsidi.
 *
 * Sejak `[BL-015]` sebuah aturan tidak lagi terikat pada satu sumbu omzet. Ia
 * punya sekumpulan syarat bebas — kategori harga baru cukup ditulis sebagai
 * baris baru dari halaman ini, tanpa migration dan tanpa deploy. Yang tetap
 * tinggal di kode hanyalah daftar dimensi yang bisa dihitung aplikasi.
 *
 * Setiap perubahan tarif dicatat sebagai kejadian `sensitive` berikut nilai
 * lama dan barunya. Angka tarif yang berubah tanpa jejak adalah hal yang tidak
 * bisa dijelaskan kepada klien yang menanyakan kenapa tagihannya berbeda.
 *
 * Perubahan di sini TIDAK menyentuh tagihan yang sedang berjalan. Dua lapis
 * penjaganya: `pricing_rules.effective_from` menentukan sejak kapan aturan
 * berlaku, dan `subscriptions.price_locked` menyimpan harga yang benar-benar
 * disepakati tiap tenant.
 */
class PricingRuleController extends Controller
{
    public function __construct(
        private readonly DimensionRegistry $dimensions,
        private readonly PricingService $pricing,
    ) {}

    public function index(): Response
    {
        PlatformAuditLog::recordRoutine('pricing-rules.index');

        return Inertia::render('Platform/PricingRules/Index', [
            'plans' => Plan::orderBy('base_price')->orderBy('name')->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'base_price' => (float) $plan->base_price,
                'included_seats' => $plan->included_seats,
                'extra_seat_price' => (float) $plan->extra_seat_price,
                // `null` berarti paket ini tidak menyetel batasnya sendiri dan
                // mengikuti bawaan platform. Dikirim apa adanya, bukan sudah
                // diselesaikan jadi angka: panel perlu bisa MENAMPILKAN bedanya
                // supaya "10/hari karena paket ini" tidak tertukar dengan
                // "10/hari karena kebetulan itu bawaannya hari ini".
                'ai_daily_limit' => $plan->limit(Plan::LIMIT_AI_DAILY),
                'is_adaptive_fallback' => $plan->is_adaptive_fallback,
                'is_post_trial_target' => $plan->is_post_trial_target,
            ]),
            // Bawaan platform, untuk ditampilkan sebagai angka yang berlaku bila
            // paket tidak menyetel batasnya sendiri.
            'aiDailyDefault' => (int) config('ai.free_tier.daily_limit'),
            // Katalog dimensi menggerakkan form: pilihan dimensi, operator yang
            // masuk akal per tipe, dan nilai sah untuk dimensi beratribut. Panel
            // tidak menyalin daftar ini — ia menerimanya, sehingga dimensi baru
            // di config langsung muncul tanpa menyentuh Vue.
            'dimensions' => $this->dimensions->forPanel(),
            'rules' => PricingRule::query()
                ->with('conditions')
                ->orderByDesc('priority')
                ->orderByDesc('effective_from')
                ->orderBy('label')
                ->get()
                ->map(fn (PricingRule $rule) => [
                    'id' => $rule->id,
                    'label' => $rule->label,
                    'priority' => $rule->priority,
                    'price' => (float) $rule->price,
                    'effective_from' => $rule->effective_from->toDateString(),
                    'is_effective' => $rule->effective_from->isPast(),
                    'conditions' => $rule->conditionSummary(),
                ]),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->ruleValidationRules());

        $this->validateConditionShapes($validated['conditions']);

        $rule = $this->pricing->publishRule($validated, $validated['conditions']);

        PlatformAuditLog::record('pricing-rules.create', $rule, [
            'label' => $rule->label,
            'price' => (float) $rule->price,
            'priority' => $rule->priority,
            'effective_from' => $rule->effective_from->toDateString(),
            // Syaratnya ikut dicatat, bukan hanya tarifnya. "Aturan X seharga
            // sekian" tidak menjawab apa pun tanpa "berlaku untuk siapa" —
            // dan syaratnya bisa berubah lewat penerbitan revisi berikutnya.
            'conditions' => $rule->conditionSummary(),
        ]);

        return back()->with('success', "Aturan {$rule->label} berlaku mulai {$rule->effective_from->toDateString()}.");
    }

    /**
     * Sunting aturan yang BELUM berlaku, berikut seluruh syaratnya.
     *
     * Hanya yang belum berlaku, dan batas itu bukan kehati-hatian berlebih:
     * menyunting aturan yang sudah berlaku mengubah dasar harga periode yang
     * sudah ditagihkan tanpa meninggalkan versi sebelumnya di mana pun. Untuk
     * aturan yang sudah berlaku, yang benar adalah menerbitkan revisi berlabel
     * sama dengan tanggal berlaku ke depan — `matchContext()` memenangkan revisi
     * terbaru per label, sementara yang lama tetap bisa dibaca sebagai riwayat.
     *
     * Sebelum ini panel hanya punya "terbitkan" dan "batalkan", sehingga
     * memperbaiki satu angka salah ketik pada aturan yang belum berlaku pun
     * menuntut menghapusnya lalu mengetik ulang seluruh syaratnya.
     */
    public function updateRule(Request $request, PricingRule $rule): RedirectResponse
    {
        if ($rule->effective_from->isPast()) {
            return back()->with('error', 'Aturan yang sudah berlaku tidak bisa disunting. Terbitkan revisi dengan nama yang sama dan tanggal berlaku ke depan.');
        }

        $validated = $request->validate($this->ruleValidationRules());

        $this->validateConditionShapes($validated['conditions']);

        $rule->loadMissing('conditions');

        $sebelum = [
            'label' => $rule->label,
            'price' => (float) $rule->price,
            'priority' => $rule->priority,
            'effective_from' => $rule->effective_from->toDateString(),
            'conditions' => $rule->conditionSummary(),
        ];

        $rule = $this->pricing->reviseRule($rule, $validated, $validated['conditions']);

        // Nilai lama DAN baru, dengan alasan yang sama seperti pada paket:
        // "tarifnya diubah jadi sekian" tidak menjawab "dari berapa", dan
        // pertanyaan itu justru yang muncul saat angkanya dipersoalkan.
        PlatformAuditLog::record('pricing-rules.update', $rule, [
            'before' => $sebelum,
            'after' => [
                'label' => $rule->label,
                'price' => (float) $rule->price,
                'priority' => $rule->priority,
                'effective_from' => $rule->effective_from->toDateString(),
                'conditions' => $rule->conditionSummary(),
            ],
        ]);

        return back()->with('success', "Aturan {$rule->label} diperbarui.");
    }

    /**
     * Hentikan sebuah aturan.
     *
     * Dulu aturan yang sudah berlaku tidak bisa dihapus sama sekali. Alasannya
     * benar — ia dasar harga periode yang sudah lewat — tapi akibatnya pemilik
     * SaaS tidak punya cara apa pun menghentikan aturan yang telanjur salah
     * terbit, dan satu-satunya jalan keluarnya (menerbitkan pengganti berlabel
     * sama) tidak menolong bila yang diinginkan justru meniadakan kelompoknya.
     *
     * Yang menyelesaikannya adalah `SoftDeletes`: barisnya tetap ada bagi
     * `invoices.pricing_rule_id` yang menautnya, sehingga tagihan lama tetap
     * bisa dijelaskan, sementara setiap query penetapan harga berhenti
     * melihatnya sejak detik ini.
     *
     * Tenant yang tadinya cocok dengan aturan ini akan jatuh ke aturan lain yang
     * masih cocok; bila tak ada satu pun, ke paket penampung jalur adaptif —
     * lihat `PricingService::fallbackPlanFor()`. Tarif periode yang SEDANG
     * berjalan tidak berubah: `subscriptions.price_locked` sudah memegangnya.
     */
    public function destroyRule(PricingRule $rule): RedirectResponse
    {
        $rule->loadMissing('conditions');

        PlatformAuditLog::record('pricing-rules.delete', $rule, [
            'label' => $rule->label,
            'price' => (float) $rule->price,
            'effective_from' => $rule->effective_from->toDateString(),
            'was_effective' => $rule->effective_from->isPast(),
            'conditions' => $rule->conditionSummary(),
        ]);

        $sudahBerlaku = $rule->effective_from->isPast();

        $rule->delete();

        return back()->with('success', $sudahBerlaku
            ? "Aturan {$rule->label} dihentikan. Tenant yang tadinya masuk kelompok ini akan dinilai ulang pada periode berikutnya."
            : 'Aturan yang belum berlaku dibatalkan.');
    }

    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate($this->planValidationRules());

        $sebelum = $this->planSnapshot($plan);

        $plan->fill(Arr::except($validated, ['ai_daily_limit', 'is_adaptive_fallback', 'is_post_trial_target']));
        $plan->setLimit(Plan::LIMIT_AI_DAILY, $validated['ai_daily_limit']);
        $plan->save();

        $plan->setAdaptiveFallback($validated['is_adaptive_fallback']);
        $plan->setPostTrialTarget($validated['is_post_trial_target']);

        // Nilai lama DAN baru sama-sama dicatat. Mencatat hanya nilai barunya
        // membuat pertanyaan "naik dari berapa?" tak terjawab justru saat
        // pertanyaan itu diajukan.
        PlatformAuditLog::record('plans.update', $plan, [
            'name' => $plan->name,
            'before' => $sebelum,
            'after' => $this->planSnapshot($plan->fresh()),
        ]);

        return back()->with('success', 'Paket diperbarui. Tenant yang sedang berjalan tetap di tarif lamanya sampai periode berikutnya.');
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->planValidationRules() + [
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('plans', 'slug')],
        ]);

        $plan = Plan::create(Arr::except($validated, ['ai_daily_limit', 'is_adaptive_fallback', 'is_post_trial_target']) + ['is_active' => true]);
        $plan->setLimit(Plan::LIMIT_AI_DAILY, $validated['ai_daily_limit']);
        $plan->save();

        $plan->setAdaptiveFallback($validated['is_adaptive_fallback']);
        $plan->setPostTrialTarget($validated['is_post_trial_target']);

        PlatformAuditLog::record('plans.create', $plan, $this->planSnapshot($plan->fresh()) + [
            'name' => $plan->name,
        ]);

        return back()->with('success', 'Paket baru dibuat.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function planValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'included_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'extra_seat_price' => ['required', 'numeric', 'min:0'],
            // `present` + `nullable`: kosong berarti "ikut bawaan platform",
            // dan itu jawaban yang sah — tapi harus benar-benar dikirim.
            // Field yang hilang dari payload tidak boleh diam-diam menghapus
            // batas yang sudah disetel. Nol pun sah, artinya paket ini tidak
            // menyertakan AI sama sekali.
            'ai_daily_limit' => ['present', 'nullable', 'integer', 'min:0', 'max:1000'],
            'is_adaptive_fallback' => ['required', 'boolean'],
            'is_post_trial_target' => ['required', 'boolean'],
        ];
    }

    /**
     * Nilai paket yang layak muncul di jejak audit.
     *
     * Batas dan kedua peran ikut, bukan cuma angka rupiahnya: menaikkan kuota
     * AI sebuah paket menaikkan tagihan kunci bersama, menunjuk penampung baru
     * memindahkan tarif setiap tenant adaptif yang tak cocok aturan mana pun,
     * dan menunjuk tujuan pasca-gratis baru menentukan paket yang dihuni setiap
     * tenant yang masa gratisnya habis sesudahnya. Ketiganya keputusan berbiaya,
     * jadi ketiganya berjejak.
     *
     * @return array<string, mixed>
     */
    protected function planSnapshot(Plan $plan): array
    {
        return [
            'base_price' => (float) $plan->base_price,
            'included_seats' => $plan->included_seats,
            'extra_seat_price' => (float) $plan->extra_seat_price,
            'ai_daily_limit' => $plan->limit(Plan::LIMIT_AI_DAILY),
            'is_adaptive_fallback' => $plan->is_adaptive_fallback,
            'is_post_trial_target' => $plan->is_post_trial_target,
        ];
    }

    /**
     * Aturan validasi yang sama untuk penerbitan dan penyuntingan.
     *
     * Satu tempat karena keduanya menghasilkan baris yang sama persis; dua
     * salinan akan mulai berselisih pada aturan yang paling jarang disentuh,
     * dan yang lebih longgar di antaranya menjadi pintu masuk sebenarnya.
     *
     * @return array<string, mixed>
     */
    protected function ruleValidationRules(): array
    {
        return [
            'label' => ['required', 'string', 'max:20'],
            // Menang yang tertinggi. Aturan umum sebaiknya berprioritas rendah
            // supaya aturan yang lebih spesifik bisa mendahuluinya tanpa harus
            // menuliskan syarat penyangkal di aturan umumnya.
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            // Tidak boleh mundur ke masa lalu. Aturan yang berlaku surut akan
            // mengubah dasar harga periode yang sudah ditagihkan — persis hal
            // yang seluruh mekanisme ini dibangun untuk mencegahnya.
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
            // `present`, bukan `required`: aturan tanpa syarat itu sah — ia
            // tarif bawaan yang cocok untuk siapa pun — tapi ketiadaannya harus
            // disengaja, bukan akibat field yang lupa dikirim.
            'conditions' => ['present', 'array', 'max:10'],
            'conditions.*.dimension' => ['required', 'string', Rule::in($this->dimensions->names())],
            'conditions.*.operator' => ['required', 'string', Rule::in(PricingRuleCondition::allOperators())],
            'conditions.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Syarat yang bentuknya sah tapi isinya mustahil ditolak di sini.
     *
     * Dimensi dan operatornya sudah dipastikan ada di katalog oleh aturan
     * `Rule::in` di atas; yang belum diperiksa adalah apakah keduanya cocok
     * SATU SAMA LAIN. "Tipe usaha lebih besar dari kuliner" lolos tiap
     * pemeriksaan per-field namun tidak akan pernah cocok dengan tenant mana
     * pun — dan aturan yang diam-diam tak pernah berlaku jauh lebih merugikan
     * daripada aturan yang ditolak saat diketik.
     *
     * @param  array<int, array{dimension: string, operator: string, value: string}>  $conditions
     */
    protected function validateConditionShapes(array $conditions): void
    {
        /** @var Validator $validator */
        $validator = validator([], []);

        foreach ($conditions as $index => $condition) {
            $definition = $this->dimensions->definition($condition['dimension']);

            if ($definition === null) {
                continue;
            }

            $diizinkan = PricingRuleCondition::operatorsForType($definition['type']);

            if (! in_array($condition['operator'], $diizinkan, true)) {
                $validator->errors()->add(
                    "conditions.{$index}.operator",
                    "Operator ini tidak berlaku untuk dimensi {$definition['label']}.",
                );

                continue;
            }

            $this->validateConditionValue($validator, $index, $condition, $definition);
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * @param  array{dimension: string, operator: string, value: string}  $condition
     * @param  array<string, mixed>  $definition
     */
    protected function validateConditionValue(Validator $validator, int $index, array $condition, array $definition): void
    {
        $key = "conditions.{$index}.value";

        if ($definition['type'] === 'metric' && ! is_numeric($condition['value'])) {
            $validator->errors()->add($key, "Nilai untuk dimensi {$definition['label']} harus berupa angka.");

            return;
        }

        $options = $definition['options'] ?? null;

        if ($options === null) {
            return;
        }

        // Untuk `in`, tiap bagian daftarnya diperiksa sendiri — satu nilai
        // salah ketik di tengah daftar akan membuat sebagian tenant terlewat
        // tanpa pesan galat apa pun.
        $values = $condition['operator'] === PricingRuleCondition::OP_IN
            ? array_map('trim', explode(',', $condition['value']))
            : [$condition['value']];

        foreach ($values as $value) {
            if (! array_key_exists($value, $options)) {
                $validator->errors()->add($key, "Nilai '{$value}' bukan pilihan sah untuk dimensi {$definition['label']}.");

                return;
            }
        }
    }
}
