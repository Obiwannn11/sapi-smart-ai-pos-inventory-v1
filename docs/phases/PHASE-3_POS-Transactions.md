# PHASE 3 — POS, Checkout & Cash Drawer

**Status:** Belum dimulai  
**Estimasi:** Setelah Phase 2 selesai  
**Dependency:** Phase 1 (foundation), Phase 2 (master data CRUD — produk, variant, modifier, payment method harus sudah bisa dikelola)  
**Output:** Full POS interface, checkout flow, payment processing, cash drawer buka/tutup, transaction code generation

---

## Daftar Isi
1. [Overview Flow Checkout](#1-overview-flow-checkout)
2. [TransactionService](#2-transactionservice)
3. [Form Request](#3-form-request)
4. [Controllers](#4-controllers)
5. [Cash Drawer Logic](#5-cash-drawer-logic)
6. [Vue Pages & Components (POS)](#6-vue-pages--components)
7. [Routes](#7-routes)
8. [Checklist Phase 3](#8-checklist)

---

## 1. Overview Flow Checkout

```
┌─────────────────────────────────────────────────────┐
│ Kasir buka aplikasi → Cek sesi kas (CashDrawer)     │
│                                                      │
│ ┌─ Belum ada sesi terbuka?                           │
│ │  → Tampilkan form "Buka Kas" (input opening_amount)│
│ │  → Setelah buka kas → redirect ke POS              │
│ └────────────────────────────────────────────────────│
│                                                      │
│ ┌─ Sesi kas sudah terbuka?                           │
│ │  → Langsung ke POS interface                       │
│ └────────────────────────────────────────────────────│
│                                                      │
│ POS Interface:                                       │
│ 1. Kasir pilih produk → pilih variant                │
│ 2. Jika ada modifier group → popup pilih modifier    │
│ 3. Item masuk ke cart (qty, harga, modifier)         │
│ 4. Kasir klik "Bayar" → Payment Modal muncul        │
│ 5. Pilih metode bayar + input nominal                │
│ 6. Submit → TransactionService.checkout()            │
│    - Buat transaksi (status: pending)                │
│    - Simpan items + modifiers (SNAPSHOT)             │
│    - Simpan payments                                 │
│    - Kurangi stok + catat stock_movements            │
│    - Update status → completed                       │
│    - Generate transaction code                       │
│ 7. Tampilkan struk / summary                         │
└─────────────────────────────────────────────────────┘
```

### Status Transition

```
[pending] ──── bayar berhasil ────→ [completed]
    │
    └──── dibatalkan ────→ [voided]
```

**Catatan:**
- Saat transaksi `voided`, stok yang sudah berkurang harus **dikembalikan** (reverse stock movement)
- Void hanya bisa dilakukan oleh **owner**, dan hanya untuk transaksi di hari yang sama (MVP constraint)

---

## 2. TransactionService

**File:** `app/Services/TransactionService.php`

```php
<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Proses checkout — atomic transaction.
     */
    public function checkout(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $tenantId = auth()->user()->tenant_id;

            // 1. Generate kode transaksi
            $code = $this->generateTransactionCode($tenantId);

            // 2. Hitung total
            $totalAmount = $this->calculateTotal($data['items']);

            // 3. Hitung kembalian
            $totalPaid = collect($data['payments'])->sum('amount');
            $changeAmount = max(0, $totalPaid - $totalAmount);

            // 4. Buat transaksi
            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'code' => $code,
                'status' => Transaction::STATUS_PENDING,
                'total_amount' => $totalAmount,
                'change_amount' => $changeAmount,
                'notes' => $data['notes'] ?? null,
            ]);

            // 5. Simpan items + modifiers (SNAPSHOT)
            foreach ($data['items'] as $item) {
                // Lock row variant untuk mencegah race condition
                $variant = ProductVariant::lockForUpdate()->find($item['variant_id']);

                if (!$variant || $variant->stock < $item['qty']) {
                    throw new \Exception(
                        "Stok {$variant?->name ?? 'produk'} tidak cukup. " .
                        "Tersedia: {$variant?->stock ?? 0}, diminta: {$item['qty']}"
                    );
                }

                $subtotal = ($item['unit_price'] * $item['qty']);

                // Hitung total modifier extra price per item
                $modifierTotal = 0;
                if (!empty($item['modifiers'])) {
                    $modifierTotal = collect($item['modifiers'])->sum('extra_price') * $item['qty'];
                }
                $subtotal += $modifierTotal;

                $txItem = $transaction->items()->create([
                    'product_variant_id' => $variant->id,
                    'variant_name' => $item['variant_name'],      // SNAPSHOT
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],          // SNAPSHOT
                    'subtotal' => $subtotal,
                ]);

                // Simpan modifier snapshots
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $modifier) {
                        $txItem->modifiers()->create([
                            'modifier_id' => $modifier['id'],
                            'modifier_name' => $modifier['name'],     // SNAPSHOT
                            'extra_price' => $modifier['extra_price'], // SNAPSHOT
                        ]);
                    }
                }

                // 6. Kurangi stok
                $this->stockService->deduct($variant, $item['qty'], $transaction->id);
            }

            // 7. Simpan pembayaran
            foreach ($data['payments'] as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference_code' => $payment['reference_code'] ?? null,
                ]);
            }

            // 8. Update status
            $transaction->update(['status' => Transaction::STATUS_COMPLETED]);

            return $transaction->load(['items.modifiers', 'payments.paymentMethod']);
        });
    }

    /**
     * Void transaksi — kembalikan stok.
     */
    public function void(Transaction $transaction): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_COMPLETED) {
            throw new \Exception('Hanya transaksi completed yang bisa di-void.');
        }

        // MVP: hanya bisa void transaksi hari ini
        if (!$transaction->created_at->isToday()) {
            throw new \Exception('Hanya bisa void transaksi hari ini.');
        }

        return DB::transaction(function () use ($transaction) {
            // Kembalikan stok
            foreach ($transaction->items as $item) {
                $this->stockService->restore($item->variant, $item->qty, $transaction->id);
            }

            $transaction->update(['status' => Transaction::STATUS_VOIDED]);

            return $transaction->fresh();
        });
    }

    /**
     * Generate kode transaksi: TRX-YYYYMMDD-XXX
     */
    private function generateTransactionCode(int $tenantId): string
    {
        $today = now()->format('Ymd');

        $lastTransaction = Transaction::where('tenant_id', $tenantId)
            ->where('code', 'like', "TRX-{$today}-%")
            ->lockForUpdate()
            ->orderByDesc('code')
            ->first();

        if ($lastTransaction) {
            $lastNumber = (int) Str::afterLast($lastTransaction->code, '-');
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf("TRX-%s-%03d", $today, $nextNumber);
    }

    /**
     * Hitung total dari items (harga variant + modifier extra).
     */
    private function calculateTotal(array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            $itemTotal = $item['unit_price'] * $item['qty'];

            if (!empty($item['modifiers'])) {
                $modifierExtra = collect($item['modifiers'])->sum('extra_price');
                $itemTotal += $modifierExtra * $item['qty'];
            }

            $total += $itemTotal;
        }

        return $total;
    }
}
```

---

## 3. Form Request

### 3.1 `StoreTransactionRequest`

**File:** `app/Http/Requests/StoreTransactionRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Items
            'items'                        => 'required|array|min:1',
            'items.*.variant_id'           => 'required|exists:product_variants,id',
            'items.*.variant_name'         => 'required|string|max:255',
            'items.*.qty'                  => 'required|integer|min:1',
            'items.*.unit_price'           => 'required|numeric|min:0',
            'items.*.modifiers'            => 'nullable|array',
            'items.*.modifiers.*.id'       => 'required|exists:modifiers,id',
            'items.*.modifiers.*.name'     => 'required|string|max:255',
            'items.*.modifiers.*.extra_price' => 'required|numeric|min:0',

            // Payments
            'payments'                        => 'required|array|min:1',
            'payments.*.payment_method_id'    => 'required|exists:payment_methods,id',
            'payments.*.amount'               => 'required|numeric|min:0',
            'payments.*.reference_code'       => 'nullable|string|max:255',

            // Notes
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal 1 item harus ada di transaksi.',
            'payments.required' => 'Metode pembayaran harus dipilih.',
        ];
    }

    /**
     * Validasi tambahan: total bayar >= total belanja.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $totalBelanja = 0;
            foreach ($this->input('items', []) as $item) {
                $itemTotal = ($item['unit_price'] ?? 0) * ($item['qty'] ?? 0);
                if (!empty($item['modifiers'])) {
                    $modifierExtra = collect($item['modifiers'])->sum('extra_price');
                    $itemTotal += $modifierExtra * ($item['qty'] ?? 0);
                }
                $totalBelanja += $itemTotal;
            }

            $totalBayar = collect($this->input('payments', []))->sum('amount');

            if ($totalBayar < $totalBelanja) {
                $validator->errors()->add('payments', 'Total pembayaran kurang dari total belanja.');
            }
        });
    }
}
```

### 3.2 `OpenCashDrawerRequest`

**File:** `app/Http/Requests/OpenCashDrawerRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashDrawerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_amount' => 'required|numeric|min:0',
        ];
    }
}
```

### 3.3 `CloseCashDrawerRequest`

**File:** `app/Http/Requests/CloseCashDrawerRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashDrawerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
```

---

## 4. Controllers

### 4.1 `POSController`

**File:** `app/Http/Controllers/Cashier/POSController.php`

```php
<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\CashDrawer;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class POSController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    public function index(): Response|RedirectResponse
    {
        // Cek apakah kasir sudah buka kas
        $openDrawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (!$openDrawer) {
            return redirect()->route('cashier.cash-drawer.index');
        }

        // Load data untuk POS
        $categories = Category::select('id', 'name')->get();

        $products = Product::where('is_active', true)
            ->with([
                'variants' => fn($q) => $q->where('stock', '>', 0)->select('id', 'product_id', 'name', 'price', 'stock'),
                'modifierGroups.modifiers:id,modifier_group_id,name,extra_price',
                'category:id,name',
            ])
            ->get();

        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        return Inertia::render('Cashier/POS', [
            'categories' => $categories,
            'products' => $products,
            'paymentMethods' => $paymentMethods,
            'cashDrawer' => $openDrawer,
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $transaction = $this->transactionService->checkout($request->validated());

            return back()->with('success', "Transaksi {$transaction->code} berhasil!");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Void transaksi (hanya owner).
     */
    public function void(Transaction $transaction): RedirectResponse
    {
        try {
            $this->transactionService->void($transaction);

            return back()->with('success', "Transaksi {$transaction->code} berhasil di-void.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

### 4.2 `CashDrawerController`

**File:** `app/Http/Controllers/Cashier/CashDrawerController.php`

```php
<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashDrawerRequest;
use App\Http\Requests\OpenCashDrawerRequest;
use App\Models\CashDrawer;
use App\Models\TransactionPayment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CashDrawerController extends Controller
{
    /**
     * Halaman cash drawer — form buka kas atau summary sesi aktif.
     */
    public function index(): Response
    {
        $openDrawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        return Inertia::render('Cashier/CashDrawer', [
            'openDrawer' => $openDrawer,
        ]);
    }

    /**
     * Buka kas baru.
     */
    public function open(OpenCashDrawerRequest $request): RedirectResponse
    {
        // Validasi: tidak boleh ada sesi terbuka
        $existingOpen = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->exists();

        if ($existingOpen) {
            return back()->with('error', 'Anda masih memiliki sesi kas yang terbuka.');
        }

        CashDrawer::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'opening_amount' => $request->validated('opening_amount'),
            'opened_at' => now(),
        ]);

        return redirect()->route('cashier.pos')->with('success', 'Kas berhasil dibuka.');
    }

    /**
     * Tutup kas.
     */
    public function close(CloseCashDrawerRequest $request): RedirectResponse
    {
        $drawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->firstOrFail();

        // Hitung expected_amount dari SEMUA payment methods (sesuai kesepakatan)
        $expectedFromTransactions = TransactionPayment::whereHas('transaction', function ($q) use ($drawer) {
            $q->where('tenant_id', $drawer->tenant_id)
                ->where('status', 'completed')
                ->where('created_at', '>=', $drawer->opened_at)
                ->where('created_at', '<=', now());
        })->sum('amount');

        $expectedAmount = $drawer->opening_amount + $expectedFromTransactions;
        $closingAmount = $request->validated('closing_amount');

        $drawer->update([
            'closing_amount' => $closingAmount,
            'expected_amount' => $expectedAmount,
            'difference' => $closingAmount - $expectedAmount,
            'notes' => $request->validated('notes'),
            'closed_at' => now(),
        ]);

        return redirect()->route('cashier.cash-drawer.index')
            ->with('success', 'Kas berhasil ditutup.');
    }

    /**
     * Rekap sesi kas (detail per metode pembayaran).
     */
    public function summary(CashDrawer $cashDrawer): Response
    {
        // Rekap per payment method
        $paymentSummary = TransactionPayment::query()
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->join('payment_methods', 'transaction_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereHas('transaction', function ($q) use ($cashDrawer) {
                $q->where('tenant_id', $cashDrawer->tenant_id)
                    ->where('status', 'completed')
                    ->where('created_at', '>=', $cashDrawer->opened_at)
                    ->where('created_at', '<=', $cashDrawer->closed_at ?? now());
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get();

        return Inertia::render('Cashier/CashDrawerSummary', [
            'cashDrawer' => $cashDrawer,
            'paymentSummary' => $paymentSummary,
        ]);
    }
}
```

---

## 5. Cash Drawer Logic

### 5.1 Flow Buka Kas

```
Kasir masuk POS
  → Cek: ada CashDrawer where user_id = kasir AND closed_at IS NULL?
  → TIDAK: redirect ke halaman CashDrawer → form buka kas
  → YA: langsung ke POS

Buka Kas:
  1. Validasi: tidak ada sesi terbuka (constraint 1 sesi per kasir)
  2. Create CashDrawer record (opening_amount, opened_at = now)
  3. Redirect ke POS
```

### 5.2 Flow Tutup Kas

```
Kasir klik "Tutup Kas"
  1. Input closing_amount (hitung manual uang fisik di laci)
  2. Input notes (opsional)
  3. Sistem hitung expected_amount:
     = opening_amount + SUM(semua transaction_payments.amount)
       dimana transaksi status=completed
       AND created_at BETWEEN opened_at AND now()
  4. difference = closing_amount - expected_amount
     - Positif: uang lebih
     - Negatif: uang kurang
     - 0: pas
  5. Update CashDrawer: closing_amount, expected_amount, difference, notes, closed_at
```

### 5.3 Batasan

- Satu kasir hanya boleh punya 1 sesi terbuka
- Kasir harus buka kas sebelum bisa transaksi di POS
- Tutup kas menampilkan summary lengkap (rekap per payment method)
- Owner bisa melihat riwayat semua sesi kas (di Phase 5 — Dashboard)

---

## 6. Vue Pages & Components

### 6.1 Struktur File

```
resources/js/Pages/
└── Cashier/
    ├── POS.vue                 ← Halaman POS utama
    ├── CashDrawer.vue          ← Buka/tutup kas
    └── CashDrawerSummary.vue   ← Rekap setelah tutup kas

resources/js/Components/
├── ModifierModal.vue           ← Popup pilih modifier saat tambah ke cart
├── CartItem.vue                ← Komponen item di cart
├── PaymentModal.vue            ← Modal bayar (pilih payment method + nominal)
├── ReceiptModal.vue            ← Struk/summary setelah checkout berhasil
└── ProductCard.vue             ← Card produk di POS grid
```

### 6.2 Panduan UI per Page

#### `Cashier/POS.vue` — Halaman Utama POS

**Layout: Split screen (atau responsive)**

```
┌────────────────────────────────────┬──────────────────────┐
│         DAFTAR PRODUK              │       CART            │
│                                    │                       │
│  [Filter kategori]                 │  Item 1   qty  harga │
│  ┌────┐ ┌────┐ ┌────┐             │  Item 2   qty  harga │
│  │Prod│ │Prod│ │Prod│             │  Item 3   qty  harga │
│  │ 1  │ │ 2  │ │ 3  │             │  └ modifier +5000    │
│  └────┘ └────┘ └────┘             │                       │
│  ┌────┐ ┌────┐                     │  ─────────────────── │
│  │Prod│ │Prod│                     │  Total: Rp XXX.XXX   │
│  │ 4  │ │ 5  │                     │                       │
│  └────┘ └────┘                     │  [🗑 Kosongkan Cart]  │
│                                    │  [💰 BAYAR]          │
│  Info kasir | Sesi kas #123        │                       │
│  [📦 Tutup Kas]                    │                       │
└────────────────────────────────────┴──────────────────────┘
```

**Interaksi:**
1. Klik produk → jika punya modifier groups → ModifierModal muncul
2. Setelah pilih modifier (atau langsung jika tanpa modifier) → tambah ke cart
3. Cart menampilkan: nama variant, qty (±), harga, modifier list, subtotal
4. Tombol "Bayar" → PaymentModal muncul
5. Setelah checkout sukses → ReceiptModal (opsional cetak struk)

#### `Cashier/CashDrawer.vue`

- **Belum ada sesi terbuka:** Form input opening_amount + tombol "Buka Kas"
- **Sesi sudah terbuka:** Info sesi (opened_at, opening_amount) + tombol "Tutup Kas"
  - Klik "Tutup Kas" → form input closing_amount + notes → submit

#### `Cashier/CashDrawerSummary.vue`

- Setelah tutup kas, tampilkan:
  - Waktu buka & tutup
  - Opening amount
  - Rekap per payment method (Cash: Rp X, QRIS: Rp Y, Transfer: Rp Z)
  - Expected amount
  - Closing amount
  - Difference (highlight merah jika negatif, hijau jika 0 atau positif)
  - Notes
  - Tombol "Kembali ke Login" atau "Buka Sesi Baru"

### 6.3 `ModifierModal.vue` — Spesifikasi Detail

```
Props:
  - product: { name, modifierGroups: [...] }

State:
  - selectedModifiers: {}  // key: group_id, value: modifier_id atau [modifier_ids]

Logic:
  - Jika group.is_required = true → wajib pilih minimal 1
  - Jika group.is_multiple = true → bisa pilih lebih dari 1 (checkbox)
  - Jika group.is_multiple = false → hanya pilih 1 (radio)

Emit:
  - @confirm → { variant_id, variant_name, unit_price, qty: 1, modifiers: [...] }
```

### 6.4 `PaymentModal.vue` — Spesifikasi Detail

```
Props:
  - totalAmount: number
  - paymentMethods: [...]

State:
  - payments: [{ payment_method_id, amount, reference_code }]

Logic:
  - Bisa split payment (tambah baris pembayaran)
  - Validasi: total bayar >= total belanja
  - Jika payment method type = 'qris_static' atau 'bank_transfer' → tampilkan input reference_code
  - Hitung kembalian secara real-time

Emit:
  - @confirm → { payments: [...] }
```

---

## 7. Routes

Tambahkan ke `routes/web.php` di dalam group cashier:

```php
// --- Cashier Routes (owner juga bisa akses) ---
Route::middleware(['auth', 'tenant', 'role:cashier,owner'])
    ->prefix('cashier')
    ->name('cashier.')
    ->group(function () {
        // POS
        Route::get('/pos', [\App\Http\Controllers\Cashier\POSController::class, 'index'])
            ->name('pos');
        Route::post('/transactions', [\App\Http\Controllers\Cashier\POSController::class, 'store'])
            ->name('transactions.store');

        // Cash Drawer
        Route::get('/cash-drawer', [\App\Http\Controllers\Cashier\CashDrawerController::class, 'index'])
            ->name('cash-drawer.index');
        Route::post('/cash-drawer/open', [\App\Http\Controllers\Cashier\CashDrawerController::class, 'open'])
            ->name('cash-drawer.open');
        Route::post('/cash-drawer/close', [\App\Http\Controllers\Cashier\CashDrawerController::class, 'close'])
            ->name('cash-drawer.close');
        Route::get('/cash-drawer/{cashDrawer}/summary', [\App\Http\Controllers\Cashier\CashDrawerController::class, 'summary'])
            ->name('cash-drawer.summary');
    });

// --- Void Transaction (owner only) ---
Route::middleware(['auth', 'tenant', 'role:owner'])
    ->post('/owner/transactions/{transaction}/void', [\App\Http\Controllers\Cashier\POSController::class, 'void'])
    ->name('owner.transactions.void');
```

---

## 8. Checklist Phase 3

- [ ] `TransactionService` — checkout flow lengkap dengan DB::transaction()
- [ ] `TransactionService` — void flow lengkap dengan reverse stock
- [ ] `generateTransactionCode()` menghasilkan kode unik `TRX-YYYYMMDD-XXX`
- [ ] `lockForUpdate()` berfungsi pada stok dan transaction code
- [ ] `StoreTransactionRequest` — validasi items, payments, total bayar >= total belanja
- [ ] `POSController` — redirect ke cash drawer jika belum buka kas
- [ ] `CashDrawerController` — buka kas dengan constraint 1 sesi per kasir
- [ ] `CashDrawerController` — tutup kas dengan kalkulasi expected_amount (semua payment methods)
- [ ] `CashDrawerController` — summary rekap per metode pembayaran
- [ ] `POS.vue` — grid produk + filter kategori + cart
- [ ] `ModifierModal.vue` — required/optional + single/multiple selection
- [ ] `PaymentModal.vue` — split payment + validasi + kembalian
- [ ] Snapshot harga dan nama tersimpan di transaction_items dan transaction_item_modifiers
- [ ] Void transaksi mengembalikan stok
- [ ] Void hanya bisa owner + hanya transaksi hari ini

### Commands untuk Verifikasi

```bash
php artisan route:list --path=cashier
php artisan route:list --path=owner/transactions
```

Test manual:
1. Login kasir → diminta buka kas → input opening_amount
2. Setelah buka kas → POS tampil → tambah item ke cart → bayar → transaksi berhasil
3. Cek database: transaction, transaction_items, transaction_payments, stock_movements terisi
4. Cek stok variant berkurang sesuai qty
5. Tutup kas → expected_amount benar → difference tampil
6. Login owner → void transaksi → stok kembali
