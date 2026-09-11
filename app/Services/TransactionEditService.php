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
    public function __construct(
        private StockService $stockService,
        private PaymentProofService $paymentProofs,
        private TaxCalculator $tax,
    ) {}

    /**
     * Edit penuh transaksi completed: item, modifier, pembayaran.
     * Stok dihitung ulang lewat DELTA per variant.
     *
     * @param  array{items: array<int, array{variant_id: int, qty: int, notes?: ?string, modifiers?: array<int, array{id: int}>}>, payments: array<int, array{payment_method_id: int, amount: float, reference_code?: ?string, proof_token?: ?string}>, notes?: ?string, reason?: ?string}  $data
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
            //
            // Harga berdiskon DISELAMATKAN menyeberangi pembangunan ulang ini
            // ([BL-018] poin 7). Baris item dihapus lalu dibuat lagi dari
            // kiriman client, dan client edit hanya mengirim varian dan qty —
            // jadi tanpa penyelamatan di bawah, transaksi yang terjual
            // berdiskon lalu diedit karena alasan lain akan DIAM-DIAM NAIK
            // KEMBALI ke harga katalog. Riwayat berubah sendiri, tanpa galat,
            // tanpa jejak: persis jenis kesalahan yang paling sulit disadari.
            $priceMemory = $transaction->items
                ->filter(fn ($item) => (float) $item->discount_amount > 0)
                ->keyBy('product_variant_id');

            $transaction->items()->each(fn ($i) => $i->modifiers()->delete());
            $transaction->items()->delete();
            $baseAmount = $this->rebuildItems($transaction, $data['items'], $priceMemory);

            // Pajak dihitung ulang dari konteks yang DIBEKUKAN pada transaksi
            // ini, bukan dari setelan tenant hari ini ([BL-065]). Mengoreksi
            // qty penjualan bulan lalu tidak boleh diam-diam memungutnya
            // ulang dengan tarif yang baru berlaku minggu ini — dan tarif
            // memang berubah: PPN pernah naik 10% → 11%.
            $taxColumns = $this->tax->columnsFor(
                $baseAmount,
                $this->tax->contextOf($transaction),
                // Alasan yang sama persis berlaku untuk biaya layanan
                // ([BL-097]): `serviceContextOf`, BUKAN `serviceContextFor`.
                // Pemilik toko boleh menaikkan atau mematikannya kapan pun
                // — ia tidak terkunci — jadi justru di sinilah tarif hari
                // ini paling sering berbeda dari tarif hari penjualannya.
                $this->tax->serviceContextOf($transaction),
            );

            $totalAmount = $taxColumns['total_amount'];

            // 5. Rebuild payments + recompute change
            //
            // Foto bukti bayar DIPERTAHANKAN menyeberangi pembangunan ulang
            // ini ([BL-075]). Baris pembayaran dihapus lalu dibuat lagi dari
            // kiriman client, dan client edit tidak mengirim ulang bukti yang
            // sudah ada — jadi tanpa penyelamatan di bawah, mengoreksi qty pada
            // penjualan QRIS akan MENGHAPUS buktinya sebagai efek samping.
            // Itu persis jenis kesalahan yang paling sulit disadari: tidak ada
            // pesan galat, tidak ada yang gagal, buktinya hanya tidak ada lagi
            // saat perselisihan datang berbulan-bulan kemudian.
            //
            // Dicocokkan lewat `payment_method_id` karena itulah satu-satunya
            // identitas yang bertahan — baris lamanya sendiri sudah dihapus.
            // Metode yang dicabut dari transaksi kehilangan buktinya, dan
            // berkasnya ikut dihapus supaya tidak jadi yatim di disk.
            $survivingProofs = $transaction->payments
                ->whereNotNull('proof_path')
                ->pluck('proof_path', 'payment_method_id');

            $transaction->payments()->delete();
            $totalPaid = 0;
            $claimedProofs = [];
            foreach ($data['payments'] as $payment) {
                $methodId = $payment['payment_method_id'];

                // Foto BARU menang atas yang lama, dan yang lama tidak ikut
                // diklaim — jadi berkasnya dibuang bersama yatim lainnya di
                // bawah. Tanpa itu, memotret ulang bukti yang buram akan
                // meninggalkan versi buramnya selamanya di disk.
                $freshProof = $this->paymentProofs->claim($payment['proof_token'] ?? null, $transaction->tenant_id);
                $proofPath = $freshProof ?? ($survivingProofs[$methodId] ?? null);

                if ($freshProof === null && $proofPath !== null) {
                    $claimedProofs[] = $proofPath;
                }

                $transaction->payments()->create([
                    'payment_method_id' => $methodId,
                    'amount' => $payment['amount'],
                    'reference_code' => $payment['reference_code'] ?? null,
                    'proof_path' => $proofPath,
                ]);
                $totalPaid += $payment['amount'];
            }

            foreach ($survivingProofs->diff($claimedProofs) as $orphan) {
                $this->paymentProofs->files()->delete($orphan);
            }
            if ($totalPaid < $totalAmount) {
                throw new \Exception('Total pembayaran kurang dari total transaksi setelah edit.');
            }

            // 6. Update header
            $transaction->update($taxColumns + [
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
    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\TransactionItem>  $priceMemory
     *                                                                                         Baris berdiskon SEBELUM edit, dipetakan per varian. Lihat alasannya di
     *                                                                                         pemanggilnya.
     */
    private function rebuildItems(Transaction $transaction, array $items, $priceMemory = null): float
    {
        $total = 0;
        $priceMemory ??= collect();

        foreach ($items as $line) {
            $variant = ProductVariant::withTrashed()->findOrFail($line['variant_id']);

            $remembered = $priceMemory->get($variant->id);

            // Harga yang diingat menang atas harga katalog. Yang TIDAK ikut
            // diingat: qty dan subtotal — keduanya memang sedang diedit.
            $unitPrice = $remembered?->unit_price ?? $variant->price;
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
                // Seluruh konteks potongannya ikut, termasuk siapa yang
                // menyetujui penembusan lantai. Menyimpan harganya tapi
                // membuang persetujuannya akan membuat baris itu terlihat
                // seperti diskon biasa di laporan.
                'original_unit_price' => $remembered?->original_unit_price,
                'discount_amount' => $remembered?->discount_amount ?? 0,
                'discount_rule_id' => $remembered?->discount_rule_id,
                'discount_reason' => $remembered?->discount_reason,
                'cost_price_at_sale' => $remembered?->cost_price_at_sale,
                'margin_floor_at_sale' => $remembered?->margin_floor_at_sale,
                'below_floor_approved_by' => $remembered?->below_floor_approved_by,
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
            // Subtotal dan pajak ikut dicatat, bukan cuma totalnya: kalau
            // suatu hari sebuah edit menggeser pembagian antara pendapatan
            // toko dan pajak terutang, jejaknya harus bisa dibaca dari sini.
            'subtotal_amount' => (string) $transaction->subtotal_amount,
            'tax_amount' => (string) $transaction->tax_amount,
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
