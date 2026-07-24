<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PricingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aturan harga sebagai data: paket jalur normal, dan bracket omzet jalur
 * subsidi.
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
            'rules' => PricingRule::orderBy('min_revenue')
                ->orderByDesc('effective_from')
                ->get()
                ->map(fn (PricingRule $rule) => [
                    'id' => $rule->id,
                    'label' => $rule->label,
                    'min_revenue' => (float) $rule->min_revenue,
                    'max_revenue' => $rule->max_revenue === null ? null : (float) $rule->max_revenue,
                    'price' => (float) $rule->price,
                    'effective_from' => $rule->effective_from->toDateString(),
                    'is_effective' => $rule->effective_from->isPast(),
                ]),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:20'],
            'min_revenue' => ['required', 'numeric', 'min:0'],
            'max_revenue' => ['nullable', 'numeric', 'gt:min_revenue'],
            'price' => ['required', 'numeric', 'min:0'],
            // Tidak boleh mundur ke masa lalu. Aturan yang berlaku surut akan
            // mengubah dasar harga periode yang sudah ditagihkan — persis hal
            // yang seluruh mekanisme ini dibangun untuk mencegahnya.
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $rule = PricingRule::create($validated);

        PlatformAuditLog::record('pricing-rules.create', $rule, [
            'label' => $rule->label,
            'price' => (float) $rule->price,
            'effective_from' => $rule->effective_from->toDateString(),
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

        PlatformAuditLog::record('pricing-rules.delete', $rule, [
            'label' => $rule->label,
            'price' => (float) $rule->price,
            'effective_from' => $rule->effective_from->toDateString(),
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
}
