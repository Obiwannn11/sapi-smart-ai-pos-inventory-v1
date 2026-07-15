<?php

namespace App\Services;

use App\Models\CashDrawer;
use App\Models\Modifier;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionEditService
{
    public function __construct(private StockService $stockService) {}

    /**
     * Edit penuh transaksi completed: item, modifier, pembayaran.
     * Stok dihitung ulang lewat DELTA per variant.
     *
     * @param  array{items: array<int, array{variant_id: int, qty: int, notes?: ?string, modifiers?: array<int, array{id: int}>}>, payments: array<int, array{payment_method_id: int, amount: float, reference_code?: ?string}>, notes?: ?string, reason?: ?string}  $data
     */
    public function edit(Transaction $transaction, array $data, User $editor): Transaction
    {
        $this->assertEditable($transaction, $editor);

        return DB::transaction(function () use ($transaction, $data, $editor) {
            $transaction->load(['items.modifiers', 'payments']);

            // 0. Snapshot BEFORE untuk audit
            $before = $this->snapshot($transaction);

            // 1. Peta qty lama per variant
            $oldQty = [];
            foreach ($transaction->items as $item) {
                $oldQty[$item->product_variant_id] =
                    ($oldQty[$item->product_variant_id] ?? 0) + $item->qty;
            }

            // 2. Peta qty baru dari payload
            $newQty = [];
            foreach ($data['items'] as $line) {
                $newQty[$line['variant_id']] =
                    ($newQty[$line['variant_id']] ?? 0) + $line['qty'];
            }

            // 3. Terapkan DELTA stok per variant (union old ∪ new), lock tiap variant
            $variantIds = array_unique(array_merge(array_keys($oldQty), array_keys($newQty)));
            foreach ($variantIds as $variantId) {
                $variant = ProductVariant::withTrashed()->lockForUpdate()->find($variantId);
                $delta = ($newQty[$variantId] ?? 0) - ($oldQty[$variantId] ?? 0);

                if ($delta === 0) {
                    continue;
                }

                if ($delta > 0) {
                    // butuh stok tambahan → cek cukup (variant aktif saja)
                    if (! $variant || $variant->trashed() || $variant->stock < $delta) {
                        $name = $variant?->name ?? 'produk';
                        $have = $variant?->stock ?? 0;
                        throw new \Exception("Stok {$name} tidak cukup untuk edit. Tersedia: {$have}, butuh tambahan: {$delta}");
                    }
                    $variant->decrement('stock', $delta);
                } else {
                    // qty turun → kembalikan stok (variant trashed: skip increment fisik, tetap catat movement)
                    if ($variant && ! $variant->trashed()) {
                        $variant->increment('stock', abs($delta));
                    }
                }

                StockMovement::create([
                    'tenant_id' => $transaction->tenant_id,
                    'product_variant_id' => $variantId,
                    'type' => StockMovement::TYPE_EDIT,
                    'qty' => -$delta, // penjualan naik → stok turun (qty negatif)
                    'notes' => "Edit transaksi #{$transaction->id}",
                    'reference_id' => $transaction->id,
                ]);
            }

            // 4. Rebuild items + modifiers (hapus lama, buat snapshot baru dari DB)
            $transaction->items()->each(fn ($i) => $i->modifiers()->delete());
            $transaction->items()->delete();
            $totalAmount = $this->rebuildItems($transaction, $data['items']);

            // 5. Rebuild payments + recompute change
            $transaction->payments()->delete();
            $totalPaid = 0;
            foreach ($data['payments'] as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference_code' => $payment['reference_code'] ?? null,
                ]);
                $totalPaid += $payment['amount'];
            }
            if ($totalPaid < $totalAmount) {
                throw new \Exception('Total pembayaran kurang dari total transaksi setelah edit.');
            }

            // 6. Update header
            $transaction->update([
                'total_amount' => $totalAmount,
                'change_amount' => max(0, $totalPaid - $totalAmount),
                'notes' => $data['notes'] ?? $transaction->notes,
                'edited_at' => now(),
                'edited_by' => $editor->id,
            ]);

            // 7. Audit (after)
            $transaction->refresh()->load(['items.modifiers', 'payments']);
            $transaction->edits()->create([
                'tenant_id' => $transaction->tenant_id,
                'user_id' => $editor->id,
                'reason' => $data['reason'] ?? null,
                'before' => $before,
                'after' => $this->snapshot($transaction),
            ]);

            return $transaction->load(['items.modifiers', 'payments.paymentMethod']);
        });
    }

    /**
     * Guard hak akses + status + batas waktu (shift laci untuk kasir).
     */
    private function assertEditable(Transaction $transaction, User $editor): void
    {
        if ($transaction->tenant_id !== $editor->tenant_id) {
            throw new \Exception('Transaksi bukan milik outlet Anda.');
        }
        if ($transaction->status !== Transaction::STATUS_COMPLETED) {
            throw new \Exception('Hanya transaksi yang sudah selesai (completed) yang bisa diedit.');
        }

        // Owner: bebas kapan saja.
        if ($editor->isOwner()) {
            return;
        }

        // Kasir: hanya transaksi dalam shift laci kas MILIKNYA yang masih terbuka.
        // Window sama persis dengan rekap laci: created_at >= opened_at, drawer belum ditutup.
        $openDrawer = CashDrawer::where('user_id', $editor->id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        if (! $openDrawer) {
            throw new \Exception('Buka shift laci kas dulu untuk bisa mengedit transaksi.');
        }
        if ($transaction->created_at < $openDrawer->opened_at) {
            throw new \Exception('Kasir hanya bisa mengedit transaksi dalam shift laci yang sedang berjalan.');
        }
    }

    /**
     * Bangun ulang items + modifier snapshot (harga dari DB — meniru processItems()).
     * TIDAK menyentuh stok di sini (delta sudah diproses di step 3).
     *
     * @param  array<int, array{variant_id: int, qty: int, notes?: ?string, modifiers?: array<int, array{id: int}>}>  $items
     */
    private function rebuildItems(Transaction $transaction, array $items): float
    {
        $total = 0;
        foreach ($items as $line) {
            $variant = ProductVariant::withTrashed()->findOrFail($line['variant_id']);
            $unitPrice = $variant->price;
            $subtotal = $unitPrice * $line['qty'];

            $resolved = [];
            $modifierTotal = 0;
            foreach ($line['modifiers'] ?? [] as $mod) {
                $dbMod = Modifier::findOrFail($mod['id']);
                $resolved[] = ['id' => $dbMod->id, 'name' => $dbMod->name, 'extra_price' => $dbMod->extra_price];
                $modifierTotal += $dbMod->extra_price;
            }
            $subtotal += $modifierTotal * $line['qty'];

            $txItem = $transaction->items()->create([
                'product_variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => $line['qty'],
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'notes' => $line['notes'] ?? null,
            ]);
            foreach ($resolved as $m) {
                $txItem->modifiers()->create([
                    'modifier_id' => $m['id'],
                    'modifier_name' => $m['name'],
                    'extra_price' => $m['extra_price'],
                ]);
            }
            $total += $subtotal;
        }

        return $total;
    }

    /**
     * Snapshot ringkas untuk audit before/after.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Transaction $transaction): array
    {
        return [
            'total_amount' => (string) $transaction->total_amount,
            'change_amount' => (string) $transaction->change_amount,
            'items' => $transaction->items->map(fn ($i) => [
                'variant_id' => $i->product_variant_id,
                'name' => $i->variant_name,
                'qty' => $i->qty,
                'unit_price' => (string) $i->unit_price,
                'subtotal' => (string) $i->subtotal,
                'modifiers' => $i->modifiers->map(fn ($m) => [
                    'name' => $m->modifier_name, 'extra_price' => (string) $m->extra_price,
                ])->all(),
            ])->all(),
            'payments' => $transaction->payments->map(fn ($p) => [
                'payment_method_id' => $p->payment_method_id,
                'amount' => (string) $p->amount,
            ])->all(),
        ];
    }
}
