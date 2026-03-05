# PHASE 5 — Dashboard, Laporan & Badge Helper

**Status:** Belum dimulai  
**Estimasi:** Setelah Phase 3 & 4 selesai (butuh data transaksi & stok untuk bisa berfungsi)  
**Dependency:** Phase 1–4 (semua fitur operasional harus sudah jalan)  
**Output:** Dashboard owner, laporan penjualan harian, badge alert, riwayat sesi kas, riwayat transaksi

---

## Daftar Isi
1. [Overview Halaman Owner](#1-overview-halaman-owner)
2. [DashboardController](#2-dashboardcontroller)
3. [ReportController](#3-reportcontroller)
4. [BadgeHelperService](#4-badgehelperservice)
5. [Vue Pages](#5-vue-pages)
6. [Routes](#6-routes)
7. [Checklist Phase 5](#7-checklist)

---

## 1. Overview Halaman Owner

| Halaman | Endpoint | Deskripsi |
|---|---|---|
| Dashboard | `GET /owner/dashboard` | Summary harian + badges |
| Laporan Harian | `GET /owner/reports/daily` | Detail penjualan per hari |
| Riwayat Transaksi | `GET /owner/transactions` | List semua transaksi + detail |
| Riwayat Cash Drawer | `GET /owner/cash-drawers` | List semua sesi kas |

---

## 2. DashboardController

**File:** `app/Http/Controllers/Owner/DashboardController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\BadgeHelperService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private BadgeHelperService $badgeHelper
    ) {}

    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;
        $today = now()->toDateString();

        // --- Metrics Hari Ini ---
        $todayTransactions = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', $today);

        $todayRevenue = (clone $todayTransactions)->sum('total_amount');
        $todayCount = (clone $todayTransactions)->count();
        $todayAverage = $todayCount > 0 ? $todayRevenue / $todayCount : 0;

        // Pendapatan per metode pembayaran hari ini
        $todayByPaymentMethod = TransactionPayment::query()
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->join('payment_methods', 'transaction_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereHas('transaction', function ($q) use ($today) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereDate('created_at', $today);
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get();

        // --- Metrics Minggu Ini ---
        $weekStart = now()->startOfWeek()->toDateString();
        $weekRevenue = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $weekStart)
            ->sum('total_amount');

        // --- Trend 7 hari ---
        $dailyTrend = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        // --- Badges ---
        $badges = $this->badgeHelper->generate($tenant);

        // --- Transaksi Terbaru ---
        $recentTransactions = Transaction::with('user:id,name')
            ->where('status', Transaction::STATUS_COMPLETED)
            ->latest()
            ->take(5)
            ->get(['id', 'code', 'total_amount', 'user_id', 'created_at']);

        return Inertia::render('Owner/Dashboard', [
            'metrics' => [
                'today_revenue' => $todayRevenue,
                'today_count' => $todayCount,
                'today_average' => round($todayAverage),
                'week_revenue' => $weekRevenue,
                'today_by_payment_method' => $todayByPaymentMethod,
            ],
            'dailyTrend' => $dailyTrend,
            'badges' => $badges,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
```

---

## 3. ReportController

**File:** `app/Http/Controllers/Owner/ReportController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CashDrawer;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Laporan penjualan harian.
     */
    public function daily(Request $request): Response
    {
        $date = $request->input('date', now()->toDateString());

        // Summary transaksi
        $transactions = Transaction::with(['user:id,name', 'items', 'payments.paymentMethod'])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', $date)
            ->latest()
            ->get();

        $totalRevenue = $transactions->sum('total_amount');
        $totalTransactions = $transactions->count();
        $voidedCount = Transaction::where('status', Transaction::STATUS_VOIDED)
            ->whereDate('created_at', $date)
            ->count();

        // Rekap per metode pembayaran
        $paymentSummary = TransactionPayment::query()
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->join('payment_methods', 'transaction_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereHas('transaction', function ($q) use ($date) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereDate('created_at', $date);
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get();

        // Produk terlaris hari itu
        $topProducts = TransactionItem::query()
            ->selectRaw('variant_name, SUM(qty) as total_qty, SUM(subtotal) as total_revenue')
            ->whereHas('transaction', function ($q) use ($date) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereDate('created_at', $date);
            })
            ->groupBy('variant_name')
            ->orderByDesc('total_qty')
            ->take(10)
            ->get();

        return Inertia::render('Owner/Reports/Daily', [
            'date' => $date,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'voided_count' => $voidedCount,
            ],
            'transactions' => $transactions,
            'paymentSummary' => $paymentSummary,
            'topProducts' => $topProducts,
        ]);
    }

    /**
     * Riwayat transaksi (semua).
     */
    public function transactions(Request $request): Response
    {
        $query = Transaction::with(['user:id,name', 'payments.paymentMethod'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $transactions = $query->paginate(25);

        return Inertia::render('Owner/Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['status', 'from', 'to']),
        ]);
    }

    /**
     * Detail transaksi.
     */
    public function transactionDetail(Transaction $transaction): Response
    {
        $transaction->load([
            'user:id,name',
            'items.modifiers',
            'items.variant:id,name',
            'payments.paymentMethod',
        ]);

        return Inertia::render('Owner/Transactions/Detail', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Riwayat sesi kas.
     */
    public function cashDrawers(Request $request): Response
    {
        $cashDrawers = CashDrawer::with('user:id,name')
            ->latest('opened_at')
            ->paginate(25);

        return Inertia::render('Owner/CashDrawers/Index', [
            'cashDrawers' => $cashDrawers,
        ]);
    }
}
```

---

## 4. BadgeHelperService

**File:** `app/Services/BadgeHelperService.php`

Implementasi yang sudah diperbaiki sesuai v1.1 (query melalui relasi product untuk tenant scoping):

```php
<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Tenant;

class BadgeHelperService
{
    /**
     * Generate semua badges untuk tenant.
     *
     * @return array Array of badge objects
     */
    public function generate(Tenant $tenant): array
    {
        $badges = [];

        // Scope helper: semua variant milik tenant ini
        // ProductVariant tidak punya tenant_id langsung → query via product
        $variantScope = ProductVariant::whereHas('product', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id);
        });

        // --- Badge 1: Stok Kritis (≤ 5, belum habis) ---
        $lowStock = (clone $variantScope)
            ->where('stock', '<=', 5)
            ->where('stock', '>', 0)
            ->with('product:id,name')
            ->get();

        if ($lowStock->count() > 0) {
            $badges[] = [
                'type' => 'low_stock',
                'severity' => 'warning',       // kuning
                'title' => 'Stok Kritis',
                'count' => $lowStock->count(),
                'message' => "{$lowStock->count()} varian mendekati habis",
                'items' => $lowStock->map(fn($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => $v->stock,
                ])->toArray(),
            ];
        }

        // --- Badge 2: Stok Habis ---
        $outOfStock = (clone $variantScope)
            ->where('stock', '<=', 0)
            ->with('product:id,name')
            ->get();

        if ($outOfStock->count() > 0) {
            $badges[] = [
                'type' => 'out_of_stock',
                'severity' => 'danger',        // merah
                'title' => 'Stok Habis',
                'count' => $outOfStock->count(),
                'message' => "{$outOfStock->count()} varian kehabisan stok",
                'items' => $outOfStock->map(fn($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => 0,
                ])->toArray(),
            ];
        }

        // --- Badge 3: Dead Stock (0 penjualan dalam 30 hari, stok > 0) ---
        $deadStock = (clone $variantScope)
            ->where('stock', '>', 0)
            ->whereDoesntHave('transactionItems', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })
            ->with('product:id,name')
            ->get();

        if ($deadStock->count() > 0) {
            $badges[] = [
                'type' => 'dead_stock',
                'severity' => 'info',          // biru
                'title' => 'Dead Stock',
                'count' => $deadStock->count(),
                'message' => "{$deadStock->count()} varian tidak terjual 30 hari terakhir",
                'items' => $deadStock->map(fn($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => $v->stock,
                ])->toArray(),
            ];
        }

        // --- Badge 4: Potensi Expired (expiry_date ≤ 7 hari ke depan) ---
        $nearExpiry = (clone $variantScope)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(7))
            ->where('stock', '>', 0)
            ->with('product:id,name')
            ->get();

        if ($nearExpiry->count() > 0) {
            $badges[] = [
                'type' => 'near_expiry',
                'severity' => 'warning',       // oranye
                'title' => 'Potensi Expired',
                'count' => $nearExpiry->count(),
                'message' => "{$nearExpiry->count()} varian mendekati kedaluwarsa",
                'items' => $nearExpiry->map(fn($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => $v->stock,
                    'expiry_date' => $v->expiry_date->format('Y-m-d'),
                ])->toArray(),
            ];
        }

        return $badges;
    }
}
```

---

## 5. Vue Pages

### 5.1 Struktur File

```
resources/js/Pages/
└── Owner/
    ├── Dashboard.vue               ← Dashboard utama
    ├── Reports/
    │   └── Daily.vue               ← Laporan harian
    ├── Transactions/
    │   ├── Index.vue               ← Riwayat transaksi
    │   └── Detail.vue              ← Detail transaksi + tombol void
    └── CashDrawers/
        └── Index.vue               ← Riwayat sesi kas

resources/js/Components/
├── BadgeCard.vue                   ← Komponen badge alert (reusable)
├── MetricCard.vue                  ← Komponen metric angka (reusable)
└── DailyChart.vue                  ← Chart trend 7 hari (opsional, bisa pakai chart.js)
```

### 5.2 `Owner/Dashboard.vue`

**Layout:**

```
┌──────────────────────────────────────────────────────────────────┐
│  SAPI Dashboard — Kopi Nusantara                    [📋 Logout] │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌────────────┐│
│  │  Pendapatan │ │  Transaksi  │ │Rata-rata/Trx│ │  Minggu Ini ││
│  │  Hari Ini   │ │  Hari Ini   │ │  Hari Ini   │ │             ││
│  │ Rp 1.250.000│ │     23      │ │  Rp 54.347  │ │ Rp 8.500.000│
│  └─────────────┘ └─────────────┘ └─────────────┘ └────────────┘│
│                                                                   │
│  ┌─ Rekap per Metode Pembayaran ────────────────────────────────┐│
│  │ Cash: Rp 750.000 | QRIS: Rp 350.000 | Transfer: Rp 150.000 ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                   │
│  ┌─ Badges / Alert ────────────────────────────────────────────┐ │
│  │ 🔴 Stok Habis (3)    │ 🟡 Stok Kritis (5)                  │ │
│  │ 🟠 Near Expiry (1)   │ 🔵 Dead Stock (2)                   │ │
│  └──────────────────────────────────────────────────────────────┘│
│                                                                   │
│  ┌─ Trend 7 Hari ──────────────────────────────────────────────┐ │
│  │ [Bar/Line chart pendapatan per hari]                         │ │
│  └──────────────────────────────────────────────────────────────┘│
│                                                                   │
│  ┌─ Transaksi Terbaru ─────────────────────────────────────────┐ │
│  │ TRX-20260306-023 | Rp 65.000 | Kasir Demo | 14:30          │ │
│  │ TRX-20260306-022 | Rp 28.000 | Kasir Demo | 14:15          │ │
│  │ ...                                       [Lihat Semua →]  │ │
│  └──────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────┘
```

### 5.3 `BadgeCard.vue` — Komponen

```
Props:
  - type: string ('low_stock'|'out_of_stock'|'dead_stock'|'near_expiry')
  - severity: string ('danger'|'warning'|'info')
  - title: string
  - count: number
  - message: string
  - items: array

Tampilan:
  - Card berwarna sesuai severity
  - Title + count badge
  - Expandable: klik untuk lihat list items
  - Setiap item bisa di-klik untuk navigate ke halaman stok / produk
```

### 5.4 `Owner/Reports/Daily.vue`

- **Input tanggal** (date picker) → default hari ini
- **Summary cards:** Total revenue, total transaksi, voided count
- **Rekap per metode pembayaran**
- **Top 10 produk terlaris** (tabel: variant name, qty terjual, revenue)
- **List transaksi hari itu** (expandable detail per transaksi)

### 5.5 `Owner/Transactions/Index.vue`

- **Filters:** Status (all/completed/voided), date range (from-to)
- **Tabel:** Kode, Status, Total, Kasir, Waktu, Actions
- **Actions per row:** Lihat Detail
- **Pagination** (25 per page)

### 5.6 `Owner/Transactions/Detail.vue`

- Info transaksi: kode, status, kasir, waktu
- Tabel items: nama variant, qty, harga satuan, modifiers, subtotal
- Tabel payments: metode, nominal, reference code
- Total + kembalian
- **Tombol Void** (hanya tampil jika status = completed + hari ini)
  - Konfirmasi dialog sebelum void

### 5.7 `Owner/CashDrawers/Index.vue`

- Tabel: Kasir, Opened At, Closed At, Opening, Expected, Closing, Difference, Status
- **Status:** "Open" (badge hijau) jika closed_at = null, "Closed" jika sudah ditutup
- **Difference:** Highlight merah jika negatif, hijau jika ≥ 0
- Klik row → detail summary (reuse component dari Phase 3 CashDrawerSummary)

---

## 6. Routes

Tambahkan ke `routes/web.php` di dalam group owner:

```php
// Dashboard
Route::get('dashboard', [\App\Http\Controllers\Owner\DashboardController::class, 'index'])
    ->name('dashboard');

// Reports
Route::get('reports/daily', [\App\Http\Controllers\Owner\ReportController::class, 'daily'])
    ->name('reports.daily');

// Transaction History
Route::get('transactions', [\App\Http\Controllers\Owner\ReportController::class, 'transactions'])
    ->name('transactions.index');
Route::get('transactions/{transaction}', [\App\Http\Controllers\Owner\ReportController::class, 'transactionDetail'])
    ->name('transactions.show');

// Cash Drawer History
Route::get('cash-drawers', [\App\Http\Controllers\Owner\ReportController::class, 'cashDrawers'])
    ->name('cash-drawers.index');
```

---

## 7. Checklist Phase 5

- [ ] `BadgeHelperService` — semua 4 badge berfungsi (low stock, out of stock, dead stock, near expiry)
- [ ] `BadgeHelperService` — query via relasi product (bukan langsung tenant_id)
- [ ] `DashboardController` — metrics hari ini benar (revenue, count, average)
- [ ] `DashboardController` — rekap per payment method benar
- [ ] `DashboardController` — trend 7 hari benar
- [ ] `DashboardController` — badges muncul sesuai kondisi data
- [ ] `ReportController` — laporan harian benar dengan date filter
- [ ] `ReportController` — top produk terlaris benar
- [ ] `ReportController` — riwayat transaksi + filter + pagination
- [ ] `ReportController` — detail transaksi tampilkan items + modifiers + payments
- [ ] `ReportController` — riwayat sesi kas lengkap
- [ ] Void transaksi dari halaman detail berfungsi (owner only, hari ini saja)
- [ ] `Dashboard.vue` menampilkan semua metrics + badges + recent transactions
- [ ] `Daily.vue` — date picker + summary + top products + transaction list
- [ ] `Transactions/Index.vue` — filter + pagination benar
- [ ] `CashDrawers/Index.vue` — semua sesi kas tampil

### Test Manual

1. Buat beberapa transaksi → cek dashboard revenue benar
2. Buat produk dengan stok ≤ 5 → badge "Stok Kritis" muncul
3. Buat produk dengan stok 0 → badge "Stok Habis" muncul
4. Set expiry_date ≤ 7 hari ke depan → badge "Near Expiry" muncul
5. Produk ada stok tapi tidak ada penjualan 30 hari → badge "Dead Stock" muncul
6. Laporan harian tanggal tertentu → data benar
7. Void transaksi dari detail → status berubah, stok kembali
8. Riwayat sesi kas → expected, closing, difference tampil benar
