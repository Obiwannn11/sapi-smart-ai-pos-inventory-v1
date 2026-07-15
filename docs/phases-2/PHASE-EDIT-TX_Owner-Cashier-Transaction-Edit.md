# PHASE EDIT-TX — Edit Transaksi (Owner & Kasir) + Recalc Stok

**Status:** ✅ Diterapkan (2026-07-14) — modal reuse `Components/Modal.vue`; katalog dikirim via deferred prop
**Estimasi:** Setelah alur POS + void sekarang stabil
**Dependency:** `TransactionService` (checkout/void), `StockService` (deduct/restore), `Owner\ReportController` (history), `Cashier\POSController` (void)
**Output:** Owner bisa mengedit transaksi (item + pembayaran) kapan saja; kasir bisa mengedit transaksi **dalam shift laci kas yang masih terbuka**; stok & total dihitung ulang otomatis dan tercatat di audit trail. UI edit berupa **modal** yang mereuse `Components/Modal.vue`.
**Dipakai oleh:** Owner (tanpa batas waktu) + Kasir (hanya transaksi dalam sesi `CashDrawer` miliknya yang belum ditutup)

> Tujuan utama: memberi jalan resmi untuk **memperbaiki salah input** tanpa harus void + input ulang, sambil menjaga integritas stok dan laporan.

---

## Keputusan yang sudah dikunci

Dari klarifikasi dengan owner:

1. **Cakupan edit = FULL** — bisa tambah/hapus item, ubah qty, ubah modifier, **dan** ubah pembayaran. Total & stok dihitung ulang penuh.
2. **Hak akses & batas waktu:**
   - **Owner** → boleh edit transaksi **kapan saja**.
   - **Kasir** → hanya boleh edit transaksi **dalam shift laci kas miliknya yang masih terbuka** — yaitu ada `CashDrawer` milik kasir dengan `closed_at = null` dan `transaction.created_at >= drawer.opened_at`. Kalau kasir belum buka laci atau sudah tutup shift → tidak bisa edit. Ini lebih ketat & lebih aman daripada sekadar "hari ini": edit pembayaran hanya bisa menggeser laci yang **belum** direkap.
   - **Anti-manipulasi (dikunci):** begitu kasir **menutup shift**, transaksi shift itu **terkunci** dari edit kasir — walau masih hari yang sama. Koreksi setelah tutup shift harus lewat owner. Alasan: setelah laci direkap & disetor, edit oleh kasir yang sama membuka celah manipulasi (mengubah pembayaran/qty untuk menutupi selisih kas). Tutup shift = titik pertanggungjawaban yang tidak boleh diedit sendiri.
3. **Cakupan UI = MODAL reusable.** Antarmuka edit muncul sebagai **modal** yang mereuse `resources/js/Components/Modal.vue` (lebar `max-w-2xl`), bukan halaman penuh. Dipanggil dari halaman detail transaksi (owner) & history kasir.
4. **Audit trail wajib** (ditetapkan di plan ini) — karena edit menyentuh uang & stok, setiap edit direkam: siapa, kapan, before/after, alasan. Tanpa ini fitur berbahaya untuk kepercayaan data.

---

## Konteks & Temuan Kode

Temuan yang membentuk desain ini:

- **Void sudah punya pola yang mau ditiru.** `TransactionService::void()` (baris ~267): guard `status = completed`, guard `created_at->isToday()` untuk MVP, lalu `StockService::restore()` per item dalam `DB::transaction()`. Edit adalah generalisasi dari ini — tetapi batas waktu kasir untuk edit dinaikkan presisinya jadi **shift laci kas**, bukan sekadar hari kalender.
- **Konsep "shift" = `CashDrawer`.** Tabel `cash_drawers` (`2026_03_06_000003_...`) menyimpan `user_id`, `opened_at`, `closed_at`. `CashDrawer::isOpen()` = `closed_at` null. Rekap laci (`CashDrawerController`) sudah memakai window **`created_at >= opened_at`** (dan `<= closed_at ?? now()`) untuk mengumpulkan transaksi milik sesi — persis window yang dipakai guard edit kasir. Cek role pakai helper `User::isOwner()` / `User::isCashier()` (`app/Models/User.php:30-38`), jangan hardcode `role === 'owner'`.
- **Deduct/restore stok sudah atomik.** `StockService::deduct()` pakai `where('stock','>=',$qty)` (anti-negatif) + catat `StockMovement`. `restore()` increment + catat. Tipe gerakan: `sale`, `restock`, `adjustment`, `void` (lihat `app/Models/StockMovement.php`). **Belum ada tipe `edit`** → perlu ditambah.
- **Item & modifier disimpan sebagai SNAPSHOT.** `processItems()` menyimpan `variant_name`, `unit_price`, `subtotal`, `notes` + modifier snapshot. Edit harus membangun ulang snapshot ini dengan logika yang sama (harga otoritatif dari DB, bukan dari client).
- **Route sudah ada polanya.** Void owner: `POST /owner/transactions/{transaction}/void` (`routes/web.php:130`). History: `owner.transactions.show` (`Owner\ReportController@transactionDetail`) & `cashier.transactions.index` (`POSController@history`).
- **`transactions` tidak punya `cash_drawer_id`.** Rekonsiliasi laci kas berbasis rentang waktu, bukan relasi. **Implikasi penting:** mengedit **pembayaran** transaksi akan mengubah angka laci kas pada window waktu yang mencakup transaksi itu. Inilah alasan kasir dibatasi ke **shift yang masih terbuka** (laci belum direkap → aman digeser), sementara owner (yang bisa menyentuh laci sudah tutup) harus diberi peringatan saat edit transaksi lampau.
- **`code` unik per `(tenant_id, code)`** (`2026_03_06_100001_...`). Edit **tidak** mengubah `code` — transaksi tetap entitas yang sama.
- **Status enum:** `pending | completed | voided`. Edit hanya berlaku untuk `completed`. (Open-bill `pending` & self-order pending punya alurnya sendiri — lihat [Keputusan Terbuka](#keputusan-terbuka).)

### Prinsip WAJIB

- **Satu sumber logika.** Bangun `edit()` di dalam `TransactionService` (atau `TransactionEditService` yang memanggil `StockService`), sama seperti checkout/void — controller tetap tipis.
- **Harga otoritatif dari DB**, persis seperti `processItems()`. Client tidak boleh menentukan `unit_price`.
- **Semua dalam satu `DB::transaction()`** dengan `lockForUpdate()` pada variant → tidak ada race dengan checkout paralel.
- **Delta stok, bukan void-lalu-jual.** Hitung selisih qty per variant supaya `StockMovement` bersih (satu baris tipe `edit` per variant yang berubah), bukan pasangan void+sale yang mengotori histori.
- **Idempotensi & anti-negatif** tetap dijaga: kalau edit menaikkan qty melebihi stok tersisa → tolak dengan pesan jelas.
- **Migrasi hanya menambah** (kolom/enum value) → aman dari jebakan "modifying column must include all attributes".
- **Pint** setelah edit PHP (`vendor/bin/pint --dirty --format agent`) + **tiap perubahan diuji** (Pest).

---

## Daftar Isi

1. [Migrasi](#1-migrasi)
2. [Backend — Service](#2-backend--service)
3. [Backend — Controller & Routes](#3-backend--controller--routes)
4. [Frontend](#4-frontend)
5. [Tests](#5-tests)
6. [Checklist](#6-checklist)
7. [Keputusan Terbuka](#keputusan-terbuka)

---

## 1. Migrasi

### 1a. Tambah tipe `edit` ke `stock_movements`

Kolom `type` di `stock_movements` adalah enum. **Wajib sertakan semua nilai lama** saat mengubah enum (jebakan Laravel 12).

```php
// database/migrations/xxxx_add_edit_type_to_stock_movements.php
Schema::table('stock_movements', function (Blueprint $table) {
    $table->enum('type', ['sale', 'restock', 'adjustment', 'void', 'edit'])->change();
});
```

Tambah konstanta di model:

```php
// app/Models/StockMovement.php
const TYPE_EDIT = 'edit';
```

### 1b. Kolom jejak edit di `transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->timestamp('edited_at')->nullable()->after('change_amount');
    $table->foreignId('edited_by')->nullable()->after('edited_at')->constrained('users');
});
```

Tambah ke `$fillable` `Transaction` + cast `edited_at` → `datetime`.

### 1c. Tabel audit `transaction_edits`

Rekam snapshot **sebelum** & **sesudah** tiap edit, plus alasan.

```php
Schema::create('transaction_edits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users');   // yang mengedit
    $table->string('reason')->nullable();                  // alasan edit (opsional tapi didorong)
    $table->json('before');                                // snapshot items+payments+total sebelum
    $table->json('after');                                 // snapshot sesudah
    $table->timestamps();
});
```

Model `TransactionEdit` (BelongsToTenant) + relasi `Transaction::edits(): HasMany`.

---

## 2. Backend — Service

### 2a. `TransactionEditService` (FILE BARU)

Alur inti: **kunci → validasi → hitung delta stok → rebuild items → rebuild payments → recompute total → audit.**

**File:** `app/Services/TransactionEditService.php`

```php
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
     * @param array{items: array, payments: array, notes?: ?string, reason?: ?string} $data
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

            // 2. Validasi + peta qty baru dari payload (lock semua variant terlibat)
            $newQty = [];
            foreach ($data['items'] as $line) {
                $newQty[$line['variant_id']] =
                    ($newQty[$line['variant_id']] ?? 0) + $line['qty'];
            }

            // 3. Terapkan DELTA stok per variant (union old ∪ new)
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
                    // qty turun → kembalikan stok (variant trashed tetap dicatat, skip increment fisik bila perlu)
                    if ($variant && ! $variant->trashed()) {
                        $variant->increment('stock', abs($delta));
                    }
                }

                StockMovement::create([
                    'tenant_id'          => $transaction->tenant_id,
                    'product_variant_id' => $variantId,
                    'type'               => StockMovement::TYPE_EDIT,
                    'qty'                => -$delta, // penjualan naik → stok turun (qty negatif)
                    'notes'              => "Edit transaksi #{$transaction->id}",
                    'reference_id'       => $transaction->id,
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
                    'amount'            => $payment['amount'],
                    'reference_code'    => $payment['reference_code'] ?? null,
                ]);
                $totalPaid += $payment['amount'];
            }
            if ($totalPaid < $totalAmount) {
                throw new \Exception('Total pembayaran kurang dari total transaksi setelah edit.');
            }

            // 6. Update header
            $transaction->update([
                'total_amount'  => $totalAmount,
                'change_amount' => max(0, $totalPaid - $totalAmount),
                'notes'         => $data['notes'] ?? $transaction->notes,
                'edited_at'     => now(),
                'edited_by'     => $editor->id,
            ]);

            // 7. Audit (after)
            $transaction->refresh()->load(['items.modifiers', 'payments']);
            $transaction->edits()->create([
                'tenant_id' => $transaction->tenant_id,
                'user_id'   => $editor->id,
                'reason'    => $data['reason'] ?? null,
                'before'    => $before,
                'after'     => $this->snapshot($transaction),
            ]);

            return $transaction->load(['items.modifiers', 'payments.paymentMethod']);
        });
    }

    /** Guard hak akses + status + batas waktu (shift laci untuk kasir). */
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
     */
    private function rebuildItems(Transaction $transaction, array $items): float
    {
        $total = 0;
        foreach ($items as $line) {
            $variant = ProductVariant::withTrashed()->find($line['variant_id']);
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
                'variant_name'       => $variant->name,
                'qty'                => $line['qty'],
                'unit_price'         => $unitPrice,
                'subtotal'           => $subtotal,
                'notes'              => $line['notes'] ?? null,
            ]);
            foreach ($resolved as $m) {
                $txItem->modifiers()->create([
                    'modifier_id'   => $m['id'],
                    'modifier_name' => $m['name'],
                    'extra_price'   => $m['extra_price'],
                ]);
            }
            $total += $subtotal;
        }
        return $total;
    }

    /** Snapshot ringkas untuk audit before/after. */
    private function snapshot(Transaction $transaction): array
    {
        return [
            'total_amount'  => (string) $transaction->total_amount,
            'change_amount' => (string) $transaction->change_amount,
            'items'         => $transaction->items->map(fn ($i) => [
                'variant_id' => $i->product_variant_id,
                'name'       => $i->variant_name,
                'qty'        => $i->qty,
                'unit_price' => (string) $i->unit_price,
                'subtotal'   => (string) $i->subtotal,
                'modifiers'  => $i->modifiers->map(fn ($m) => [
                    'name' => $m->modifier_name, 'extra_price' => (string) $m->extra_price,
                ])->all(),
            ])->all(),
            'payments'      => $transaction->payments->map(fn ($p) => [
                'payment_method_id' => $p->payment_method_id,
                'amount'            => (string) $p->amount,
            ])->all(),
        ];
    }
}
```

> **Catatan stok saat qty turun & variant sudah dihapus (soft-deleted):** stok fisik tidak dinaikkan (barang sudah tidak dijual), tapi `StockMovement` tipe `edit` tetap dicatat agar histori jujur — mengikuti pola `void()` yang menangani `withTrashed()`.

---

## 3. Backend — Controller & Routes

### 3a. Controller

Karena UI adalah **modal**, tidak ada halaman `edit` terpisah — data katalog untuk modal ikut dikirim oleh controller yang sudah merender halaman detail/history (lihat §4). Controller edit hanya butuh **satu** endpoint: `update`. Taruh di controller baru `TransactionEditController` di grup `cashier.` (role `cashier,owner`); gerbang batas waktu/shift ditegakkan di service.

```php
// app/Http/Controllers/Cashier/TransactionEditController.php (baru)
public function update(EditTransactionRequest $request, Transaction $transaction, TransactionEditService $service): RedirectResponse
{
    try {
        $service->edit($transaction, $request->validated(), $request->user());

        return back()->with('success', 'Transaksi berhasil diperbarui.');
    } catch (\Exception $e) {
        return back()->with('error', $e->getMessage());
    }
}
```

> `back()` cukup karena modal dibuka di atas halaman detail/history; setelah sukses Inertia me-refresh props halaman itu (transaksi + riwayat edit) tanpa pindah rute.

**Data katalog untuk modal.** Halaman yang memuat tombol Edit (detail transaksi owner & history kasir) harus ikut mengirim `products` (variant + modifier) dan `paymentMethods` aktif sebagai props — sama seperti `POSController@index` — supaya modal bisa langsung dipakai tanpa request kedua. Untuk daftar transaksi yang panjang, kirim katalog sekali di level halaman (bukan per-baris) dan gunakan **deferred prop** Inertia v2 agar payload awal ringan.

### 3b. Form Request

`EditTransactionRequest` — validasi struktur sama dengan checkout: `items.*.variant_id` (exists), `items.*.qty` (min 1), `items.*.modifiers.*.id` (exists), `payments.*.payment_method_id` (exists), `payments.*.amount` (numeric min 0), `reason` (nullable string). **Wajib ada minimal 1 item.**

### 3c. Routes

```php
// dalam grup cashier (role:cashier,owner) — batas waktu/shift kasir ditegakkan di service
// Hanya satu endpoint: modal submit ke sini. Tidak perlu route GET /edit (bukan halaman).
Route::put('/transactions/{transaction}', [TransactionEditController::class, 'update'])->name('transactions.update');
```

---

## 4. Frontend — Modal (reuse `Components/Modal.vue`)

**Prinsip: WAJIB reuse komponen yang sudah ada, jangan buat halaman baru.** UI edit adalah modal yang dibungkus `Modal.vue` (`resources/js/Components/Modal.vue`) — komponen reusable dengan slot `header`/default/`footer`, prop `show`, `title`, `maxWidth`, dan emit `close`. Sudah menangani teleport, backdrop, ESC, dan lock scroll — jangan tulis ulang.

### `Cashier/TransactionEditModal.vue` (FILE BARU)

Komponen ini **hanya isi modal**; pembungkusnya `Modal.vue`.

```vue
<!-- Kerangka -->
<Modal :show="show" title="Edit Transaksi" max-width="max-w-2xl" @close="$emit('close')">
  <!-- body: keranjang + item picker + pembayaran -->
  <template #footer>
    <!-- tombol Batal + Simpan (:disabled saat processing) -->
  </template>
</Modal>
```

Isi & perilaku:

- **Reuse sub-komponen yang sudah ada, jangan bikin baru:**
  - Keranjang/qty/modifier: pinjam pola dari `Cashier/POS.vue`; pemilihan modifier reuse `Components/ModifierModal.vue`; input pembayaran reuse `Components/PaymentModal.vue` (atau bagiannya). Cek `PaymentModal`/`ModifierModal` dulu sebelum menulis markup baru (konvensi CLAUDE.md: reuse dulu).
- **Props:** `show`, `transaction` (dengan `items.modifiers` + `payments`), `products` (katalog), `paymentMethods`. **Emit:** `close`, dan andalkan refresh props Inertia setelah submit.
- **Pre-fill:** keranjang dari `transaction.items` (termasuk modifier & notes), pembayaran dari `transaction.payments`.
- **Submit:** `useForm` Inertia → `router.put(route('cashier.transactions.update', transaction.id))`. `processing` dari useForm untuk **guard double-submit** (tombol `:disabled="form.processing"`), sejalan pola `POS.vue`.
- **Peringatan transaksi lampau (owner):** kalau transaksi bukan bagian shift/hari berjalan (`isPastRecord` dari prop) tampilkan banner di dalam modal: *"Mengedit transaksi lampau akan mengubah laporan & rekap laci kas periode itu."* Untuk kasir, tombol Edit memang tak muncul di luar shiftnya (lihat entry point), jadi banner ini praktis khusus owner.
- **Field alasan** (`reason`) opsional tapi didorong — dikirim ke audit `transaction_edits`.
- **Ringkasan perubahan** sebelum simpan: tampilkan delta ("Kopi Susu 2 → 3", "Total Rp X → Rp Y") supaya kasir sadar dampaknya.
- **Error handling:** flash `error` dari backend (mis. stok kurang, di luar shift) ditampilkan di dalam modal; modal tetap terbuka agar bisa dikoreksi.

### Entry point & riwayat (edit halaman yang sudah ada)

- **Tombol "Edit"** di halaman detail transaksi owner (`Owner/.../TransactionDetail`) & history kasir (`POSController@history` view). Klik → set `showEditModal = true`, lempar transaksi terpilih ke `TransactionEditModal`. Tidak ada navigasi halaman.
- **Visibilitas tombol:** owner → selalu. Kasir → hanya bila transaksi masuk shift lacinya yang terbuka (kirim flag `canEdit` per transaksi dari controller memakai window `opened_at`, agar tombol tak muncul untuk transaksi di luar shift). Backend tetap jadi otoritas final (service `assertEditable`).
- **Riwayat edit:** tampilkan daftar dari `transaction_edits` di halaman detail: "Diedit oleh {user} pada {waktu} — {reason}".

---

## 5. Tests (Pest)

**File:** `tests/Feature/TransactionEditTest.php`, `tests/Unit/TransactionEditServiceTest.php`

Kasus wajib:

- **Naik qty** → stok turun sebesar delta; `StockMovement` tipe `edit` qty negatif tercatat; total ter-update.
- **Turun qty** → stok naik sebesar delta; movement `edit` qty positif.
- **Tambah item baru** → stok item itu berkurang; **hapus item** → stok item itu kembali.
- **Ganti pembayaran** (mis. cash → QRIS, atau ubah nominal) → payments lama terhapus, baru tersimpan, `change_amount` benar.
- **Tolak** kalau edit menaikkan qty melebihi stok tersisa (pesan jelas, tidak ada perubahan tersimpan — rollback).
- **Shift kasir:** kasir dengan laci terbuka **bisa** edit transaksi yang dibuat setelah `opened_at`; **tidak bisa** edit transaksi yang dibuat sebelum laci dibuka (di luar shift); **tidak bisa** edit sama sekali kalau tidak punya laci terbuka. **Owner bisa** dalam semua kasus itu.
- **Tidak bisa edit** transaksi `voided`/`pending`.
- **Tenant scoping:** user tenant lain → 403.
- **Audit:** setiap edit membuat 1 baris `transaction_edits` dengan `before`/`after` yang benar + `edited_by`/`edited_at` terisi.
- **Atomic:** kalau pembayaran kurang dari total → seluruh edit rollback (stok tidak berubah).

Jalankan: `php artisan test --compact --filter="TransactionEdit"`

---

## 6. Checklist

- [ ] Migrasi enum `stock_movements.type` + `TYPE_EDIT` (sertakan semua nilai lama)
- [ ] Migrasi `transactions.edited_at` + `edited_by`
- [ ] Migrasi + model `transaction_edits` (audit before/after) + relasi `Transaction::edits()`
- [ ] `TransactionEditService::edit()` + `assertEditable()` (guard shift laci via `CashDrawer`, pakai `isOwner()`) dalam satu `DB::transaction()`
- [ ] `EditTransactionRequest` + `TransactionEditController@update` (satu endpoint)
- [ ] Route `transactions.update` saja (grup cashier; tak ada GET `/edit` karena modal)
- [ ] `Cashier/TransactionEditModal.vue` **membungkus `Components/Modal.vue`** (reuse; jangan buat halaman) + reuse `ModifierModal`/`PaymentModal`
- [ ] Tombol Edit + `showEditModal` di detail transaksi owner & history kasir (flag `canEdit` per transaksi via window shift) + banner transaksi lampau untuk owner
- [ ] Tampilkan riwayat edit di detail transaksi
- [ ] Tests hijau (`TransactionEdit`)
- [ ] `vendor/bin/pint --dirty --format agent` bersih
- [ ] Entry di `docs/CHANGELOG.md` saat sudah diterapkan

### Urutan kerja disarankan
Migrasi (1) → Service + **test unit dulu** (2 & 5) → Controller/Request/Routes (3) → Frontend (4).

---

## Keputusan Terbuka

### A. Open-bill `pending` & self-order
Plan ini menyasar transaksi `completed`. Open-bill yang masih `pending` (belum bayar) idealnya "edit item" lewat alur open-bill-nya sendiri (belum ada — bisa jadi fase terpisah). Self-order `pending` (menunggu bayar Xendit) **jangan** diberi edit manual untuk hindari bentrok webhook. → **Rekomendasi:** batasi fitur ke `completed` + `source = pos` dulu.

### B. Batas waktu owner (opsional pengetatan)
Owner "kapan saja" sesuai permintaan. Kalau nanti terasa berisiko untuk laporan bulanan yang sudah tutup buku, bisa ditambah kunci lunak: edit transaksi > N hari butuh konfirmasi ekstra / dicatat khusus. Tidak diimplementasi sekarang.

### C. Dampak ke laci kas & laporan
Karena `transactions` tak terhubung `cash_drawer_id`, edit pembayaran menggeser rekap laci kas pada window waktu transaksi. Sudah dimitigasi dengan (a) **kasir dibatasi ke shift lacinya yang masih terbuka** — laci belum direkap, jadi geseran ikut terhitung wajar saat tutup shift; (b) banner peringatan untuk owner (yang bisa menyentuh laci yang sudah tutup). Kalau butuh presisi mutlak, pertimbangkan menautkan transaksi ke sesi laci kas (`cash_drawer_id`) — **di luar scope** dokumen ini.

### D. Sinergi dengan anti-double-input
Fitur edit ini melengkapi guard double-submit di `POS.vue`: kalau terlanjur ada dobel/salah input, edit memberi jalan koreksi resmi tanpa void+input ulang. Idempotency-key tingkat jaringan dibahas di plan PWA (`PHASE-PWA_Offline-Transaction-Sync.md`).
