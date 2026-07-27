<?php

namespace App\Services\Upsell;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\ProductVariant;
use App\Models\Scopes\TenantScope;
use App\Models\Transaction;
use App\Models\UpsellEvent;
use Illuminate\Support\Facades\Log;

/**
 * Menyimpan nasib saran yang benar-benar dilihat manusia.
 *
 * Dipanggil dari SATU titik — saat transaksi dibuat — sehingga empat sumber
 * (POS online, POS offline, open bill, self-order) memakai jalur tulis yang
 * sama dan pencatatannya ikut selamat melewati mode offline.
 *
 * Aturan utamanya: **tidak pernah menggagalkan penjualan**. Statistik upsell
 * lebih murah untuk hilang daripada transaksi, jadi payload event yang cacat
 * dibuang diam-diam (dengan log) alih-alih melempar exception.
 */
class UpsellEventRecorder
{
    /**
     * Batas jumlah event per transaksi. Sengaja longgar terhadap
     * `max_per_transaction` (saran bisa muncul, ditutup, lalu diganti saran
     * lain dalam satu keranjang), tapi tetap ada supaya payload tidak dipakai
     * menulis ribuan baris.
     */
    private const MAX_EVENTS_PER_TRANSACTION = 20;

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return int jumlah baris yang benar-benar tersimpan
     */
    public function record(Transaction $transaction, array $events, string $surface): int
    {
        if ($events === []) {
            return 0;
        }

        try {
            return $this->persist($transaction, $events, $surface);
        } catch (\Throwable $e) {
            // Penjualannya sudah sah; kegagalan mencatat statistik tidak boleh
            // menariknya kembali.
            Log::warning('Upsell event recording failed', [
                'transaction_id' => $transaction->id,
                'tenant_id' => $transaction->tenant_id,
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function persist(Transaction $transaction, array $events, string $surface): int
    {
        $tenantId = $transaction->tenant_id;

        $ownVariantIds = $this->ownVariantIds($tenantId, $events);
        $ownModifierIds = $this->ownModifierIds($tenantId, $events);

        $saved = 0;

        foreach (array_slice($events, 0, self::MAX_EVENTS_PER_TRANSACTION) as $event) {
            $type = $event['type'] ?? null;
            $status = $event['status'] ?? null;

            if (! in_array($type, UpsellEvent::types(), true)) {
                continue;
            }

            if (! in_array($status, [UpsellEvent::STATUS_ACCEPTED, UpsellEvent::STATUS_IGNORED], true)) {
                continue;
            }

            $reason = $event['reason'] ?? null;

            if (! in_array($reason, UpsellEvent::reasons(), true)) {
                $reason = null;
            }

            $label = trim((string) ($event['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            UpsellEvent::create([
                'tenant_id' => $tenantId,
                'transaction_id' => $transaction->id,
                'type' => $type,
                'surface' => $surface,
                'status' => $status,
                'reason' => $reason,
                // FK yang bukan milik tenant ini dinolkan, bukan membatalkan
                // barisnya: label sudah di-snapshot, jadi angka konversinya
                // tetap jujur sementara payload yang mengada-ada tidak pernah
                // menunjuk data tenant lain.
                'trigger_variant_id' => $this->keepIfOwned($event['trigger_variant_id'] ?? null, $ownVariantIds),
                'suggested_variant_id' => $this->keepIfOwned($event['suggested_variant_id'] ?? null, $ownVariantIds),
                'suggested_modifier_id' => $this->keepIfOwned($event['suggested_modifier_id'] ?? null, $ownModifierIds),
                'label' => mb_substr($label, 0, 255),
                // Saran yang diabaikan tidak pernah menambah omzet, apa pun
                // yang dikirim client.
                'extra_amount' => $status === UpsellEvent::STATUS_ACCEPTED
                    ? max(0, (float) ($event['extra_amount'] ?? 0))
                    : 0,
            ]);

            $saved++;
        }

        return $saved;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return list<int>
     */
    private function ownVariantIds(int $tenantId, array $events): array
    {
        $ids = $this->collectIds($events, ['trigger_variant_id', 'suggested_variant_id']);

        if ($ids === []) {
            return [];
        }

        return ProductVariant::withTrashed()
            ->whereIn('id', $ids)
            ->whereHas('product', fn ($query) => $query->withTrashed()
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return list<int>
     */
    private function ownModifierIds(int $tenantId, array $events): array
    {
        $ids = $this->collectIds($events, ['suggested_modifier_id']);

        if ($ids === []) {
            return [];
        }

        return Modifier::withTrashed()
            ->whereIn('id', $ids)
            ->whereIn('modifier_group_id', ModifierGroup::withTrashed()
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->select('id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  list<string>  $keys
     * @return list<int>
     */
    private function collectIds(array $events, array $keys): array
    {
        $ids = [];

        foreach ($events as $event) {
            foreach ($keys as $key) {
                if (! empty($event[$key]) && is_numeric($event[$key])) {
                    $ids[] = (int) $event[$key];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $owned
     */
    private function keepIfOwned(mixed $id, array $owned): ?int
    {
        if (empty($id) || ! is_numeric($id)) {
            return null;
        }

        return in_array((int) $id, $owned, true) ? (int) $id : null;
    }
}
