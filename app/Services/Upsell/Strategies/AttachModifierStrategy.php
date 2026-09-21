<?php

namespace App\Services\Upsell\Strategies;

use App\Models\Modifier;
use App\Models\Product;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\UpsellEvent;
use App\Services\Upsell\Suggestion;
use App\Services\Upsell\SuggestionStrategy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Tambah topping / ukuran besar" — bentuk upsell paling umum di F&B, dan yang
 * paling murah dibangun di sini karena mekanismenya sudah berdiri penuh:
 * modifiers.extra_price, pivot product_modifier_groups, dan snapshot
 * transaction_item_modifiers semuanya sudah ada. Yang belum ada hanya yang
 * menyarankannya.
 *
 * Kandidat dibatasi dua hal:
 *
 *   - Modifier HARUS berasal dari grup yang benar-benar terpasang ke produk itu.
 *     Menyarankan modifier yang tidak bisa dipilih adalah cacat yang langsung
 *     terlihat kasir.
 *   - Grup WAJIB (is_required) dilewati. Kasir sudah dipaksa memilih di sana
 *     oleh ModifierModal, jadi menyarankannya hanya menambah bising.
 */
class AttachModifierStrategy implements SuggestionStrategy
{
    public function suggestFor(Tenant $tenant, Collection $sellableVariants): array
    {
        if ($sellableVariants->isEmpty()) {
            return [];
        }

        $allowed = $this->allowedModifiersByProduct($tenant);

        if ($allowed === []) {
            return [];
        }

        $support = $this->modifierSupportByVariant($tenant, $sellableVariants->pluck('id')->all());

        $perTrigger = (int) config('upsell.candidates_per_trigger', 2);
        $minSupport = (int) config('upsell.attach.min_support', 2);
        $fallback = (bool) config('upsell.attach.fallback_to_catalog', true);

        $suggestions = [];

        foreach ($sellableVariants as $variant) {
            $candidates = $allowed[$variant->product_id] ?? [];

            if ($candidates === []) {
                continue;
            }

            $ranked = $this->rankByHistory($candidates, $support[$variant->id] ?? [], $minSupport);

            // Tenant baru belum punya riwayat sama sekali. Menawarkan modifier
            // termurah yang tersedia bukan mengarang pola — dan ditandai
            // `catalog` supaya laporan bisa membedakannya dari saran berbasis data.
            if ($ranked === [] && $fallback) {
                $cheapest = collect($candidates)->sortBy(fn (Modifier $m) => (float) $m->extra_price)->first();

                if ($cheapest !== null) {
                    $ranked = [[$cheapest, UpsellEvent::REASON_CATALOG, 45.0]];
                }
            }

            $lines = [];

            foreach (array_slice($ranked, 0, $perTrigger) as [$modifier, $reason, $score]) {
                $lines[] = new Suggestion(
                    type: UpsellEvent::TYPE_ATTACH,
                    reason: $reason,
                    label: $modifier->name,
                    note: $reason === UpsellEvent::REASON_COOCCURRENCE
                        ? 'Sering ditambahkan pelanggan'
                        : 'Tersedia untuk item ini',
                    extraAmount: (float) $modifier->extra_price,
                    score: $score,
                    triggerVariantId: $variant->id,
                    suggestedModifierId: $modifier->id,
                );
            }

            if ($lines !== []) {
                $suggestions[$variant->id] = $lines;
            }
        }

        return $suggestions;
    }

    /**
     * Modifier yang SAH ditawarkan per produk: dari grup opsional yang terpasang
     * ke produk, dan benar-benar menambah harga.
     *
     * @return array<int, list<Modifier>>
     */
    private function allowedModifiersByProduct(Tenant $tenant): array
    {
        // TenantScope dilepas karena builder ini juga bisa dipanggil tanpa sesi
        // (mis. dari job); scoping-nya dilakukan eksplisit lewat tenant_id.
        // Scope soft-delete SENGAJA dipertahankan — produk terhapus bukan kandidat.
        $products = Product::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['modifierGroups' => function ($query) {
                $query->where('is_required', false)->with('modifiers');
            }])
            ->get(['id']);

        $map = [];

        foreach ($products as $product) {
            $modifiers = $product->modifierGroups
                ->flatMap(fn ($group) => $group->modifiers)
                ->filter(fn (Modifier $modifier) => (float) $modifier->extra_price > 0)
                ->values()
                ->all();

            if ($modifiers !== []) {
                $map[$product->id] = $modifiers;
            }
        }

        return $map;
    }

    /**
     * Berapa kali tiap modifier menyertai tiap varian dalam jendela riwayat.
     *
     * Satu query agregat untuk seluruh katalog — bukan satu query per varian,
     * karena ini dijalankan tiap kali POS dibuka.
     *
     * @param  list<int>  $variantIds
     * @return array<int, array<int, int>> [variant_id][modifier_id] => support
     */
    private function modifierSupportByVariant(Tenant $tenant, array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $since = now()->subDays((int) config('upsell.attach.window_days', 30));

        $rows = DB::table('transaction_item_modifiers as tim')
            ->join('transaction_items as ti', 'ti.id', '=', 'tim.transaction_item_id')
            ->join('transactions as t', 't.id', '=', 'ti.transaction_id')
            ->where('t.tenant_id', $tenant->id)
            ->where('t.status', Transaction::STATUS_COMPLETED)
            ->where('t.created_at', '>=', $since)
            ->whereIn('ti.product_variant_id', $variantIds)
            ->groupBy('ti.product_variant_id', 'tim.modifier_id')
            ->select([
                'ti.product_variant_id',
                'tim.modifier_id',
                DB::raw('COUNT(*) as support'),
            ])
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->product_variant_id][(int) $row->modifier_id] = (int) $row->support;
        }

        return $map;
    }

    /**
     * @param  list<Modifier>  $candidates
     * @param  array<int, int>  $support
     * @return list<array{0: Modifier, 1: string, 2: float}>
     */
    private function rankByHistory(array $candidates, array $support, int $minSupport): array
    {
        $ranked = [];

        foreach ($candidates as $modifier) {
            $count = $support[$modifier->id] ?? 0;

            if ($count < $minSupport) {
                continue;
            }

            // Bobot riwayat dibatasi supaya satu modifier dengan ratusan
            // kejadian tidak selamanya mengunci slot dan menutup yang lain.
            $ranked[] = [$modifier, UpsellEvent::REASON_COOCCURRENCE, 50.0 + min($count, 20)];
        }

        usort($ranked, fn (array $a, array $b) => $b[2] <=> $a[2]);

        return $ranked;
    }
}
