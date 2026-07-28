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
            'plans' => Plan::orderBy('name')->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'base_price' => (float) $plan->base_price,
                'included_seats' => $plan->included_seats,
                'extra_seat_price' => (float) $plan->extra_seat_price,
                'is_active' => $plan->is_active,
            ]),
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
        $validated = $request->validate([
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
        ]);

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

    public function destroyRule(PricingRule $rule): RedirectResponse
    {
        // Aturan yang SUDAH berlaku tidak boleh dihapus: ia adalah dasar harga
        // periode yang sudah lewat, dan menghapusnya membuat penetapan harga
        // waktu itu tak bisa lagi dijelaskan. Untuk mengubah tarif, terbitkan
        // aturan baru dengan tanggal berlaku ke depan.
        if ($rule->effective_from->isPast()) {
            return back()->with('error', 'Aturan yang sudah berlaku tidak bisa dihapus. Terbitkan aturan baru dengan tanggal berlaku ke depan.');
        }

        $rule->loadMissing('conditions');

        PlatformAuditLog::record('pricing-rules.delete', $rule, [
            'label' => $rule->label,
            'price' => (float) $rule->price,
            'effective_from' => $rule->effective_from->toDateString(),
            'conditions' => $rule->conditionSummary(),
        ]);

        $rule->delete();

        return back()->with('success', 'Aturan yang belum berlaku dibatalkan.');
    }

    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'included_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'extra_seat_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $sebelum = [
            'base_price' => (float) $plan->base_price,
            'included_seats' => $plan->included_seats,
            'extra_seat_price' => (float) $plan->extra_seat_price,
        ];

        $plan->update($validated);

        // Nilai lama DAN baru sama-sama dicatat. Mencatat hanya nilai barunya
        // membuat pertanyaan "naik dari berapa?" tak terjawab justru saat
        // pertanyaan itu diajukan.
        PlatformAuditLog::record('plans.update', $plan, [
            'name' => $plan->name,
            'before' => $sebelum,
            'after' => [
                'base_price' => (float) $plan->base_price,
                'included_seats' => $plan->included_seats,
                'extra_seat_price' => (float) $plan->extra_seat_price,
            ],
        ]);

        return back()->with('success', 'Paket diperbarui. Tenant yang sedang berjalan tetap di tarif lamanya sampai periode berikutnya.');
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('plans', 'slug')],
            'base_price' => ['required', 'numeric', 'min:0'],
            'included_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'extra_seat_price' => ['required', 'numeric', 'min:0'],
        ]);

        $plan = Plan::create($validated + ['is_active' => true]);

        PlatformAuditLog::record('plans.create', $plan, [
            'name' => $plan->name,
            'base_price' => (float) $plan->base_price,
        ]);

        return back()->with('success', 'Paket baru dibuat.');
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
