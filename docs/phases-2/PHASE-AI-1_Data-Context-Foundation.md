# PHASE AI-1 — Fondasi Data Context ("Base Knowledge")

**Status:** Selesai (§4 MCP Server → lihat `PHASE-AI-4_MCP-Server.md`)  
**Estimasi:** Setelah Phase 1–5 selesai (butuh data transaksi, menu, & stok)  
**Dependency:** Phase 3 (Transaksi), Phase 4 (Stok), Phase 5 (Report/Badge) — direuse sebagai sumber data  
**Output:** `ProfitService` (turunkan profit) + `AiContextService` (rakit konteks agregat) — layer netral-provider yang jadi *base knowledge* AI  
**Dipakai oleh:** `PHASE-AI-2` (AI Engine) & `PHASE-AI-3` (AI Analysis)

> Bagian 1 dari 3. Lanjut ke `PHASE-AI-2_AI-Engine.md` setelah bagian ini hijau.

---

## Konteks

Fitur AI Analysis butuh AI bisa "membaca" data transaksi & menu tiap tenant sebagai *base knowledge*. Bagian ini membangun layer data-context **netral-provider** (tidak tahu soal LLM) yang meringkas data tenant jadi struktur agregat siap-pakai. Layer yang sama nanti diekspos sebagai MCP server (§4).

Temuan kode yang membentuk desain ini:
- **Profit tidak pernah disimpan.** Harus diturunkan: `SUM(transaction_items.subtotal) − SUM(qty × product_variants.cost_price)`. `cost_price` hanya ada di `product_variants` (tidak di-snapshot ke `transaction_items`).
- **Multi-tenant otomatis** via `App\Traits\BelongsToTenant` + `App\Models\Scopes\TenantScope`.
- **Reuse agregasi** dari `Owner/ReportController.php`, `Owner/DashboardController.php`, `Services/BadgeHelperService.php`.

### Prinsip WAJIB
- **Profit derivation** wajib `->withTrashed()` pada join `product_variants` (variant *soft-deletable*), filter `transactions.status = completed` + rentang tanggal. COGS memakai `cost_price` **saat ini** (bukan historis) — limitasi diketahui, tulis di PHPDoc.
- **Keamanan konteks:** output hanya **data agregat**. Jangan sertakan baris transaksi mentah, `customer_name`, atau `table_number` (hindari PII bocor ke LLM di Bagian 2).
- **Pint** setelah edit PHP: `vendor/bin/pint --dirty --format agent`. Setiap perubahan **diuji** (Pest).

---

## Daftar Isi
1. [ProfitService](#1-profitservice)
2. [AiContextService](#2-aicontextservice)
3. [Tests](#3-tests)
4. [Lanjutan: MCP Server](#4-lanjutan-mcp-server)
5. [Checklist](#5-checklist)

---

## 1. ProfitService

Karena profit tidak tersimpan, service ini menurunkannya. **Satu-satunya** sumber kebenaran profit di app.

**File:** `app/Services/ProfitService.php`

```php
<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProfitService
{
    /**
     * Ringkasan profit keseluruhan pada rentang tanggal.
     *
     * COGS diturunkan dari cost_price varian SAAT INI (bukan historis).
     * Join memakai withTrashed() karena product_variants soft-deletable.
     *
     * @return array{revenue: float, cogs: float, gross_profit: float, margin_pct: float}
     */
    public function overallProfit(Carbon $from, Carbon $to): array
    {
        $revenue = (float) Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');

        $cogs = (float) $this->cogsQuery($from, $to)->value('cogs');

        $grossProfit = $revenue - $cogs;
        $marginPct = $revenue > 0 ? round($grossProfit / $revenue * 100, 2) : 0.0;

        return [
            'revenue'      => $revenue,
            'cogs'         => $cogs,
            'gross_profit' => $grossProfit,
            'margin_pct'   => $marginPct,
        ];
    }

    /**
     * Profit per varian produk (untuk saran diskon).
     *
     * @return Collection<int, array{variant_name: string, qty: int, revenue: float, cogs: float, margin: float, margin_pct: float}>
     */
    public function profitByProduct(Carbon $from, Carbon $to): Collection
    {
        return TransactionItem::query()
            ->join('product_variants', 'transaction_items.product_variant_id', '=', 'product_variants.id')
            ->whereHas('transaction', function ($q) use ($from, $to) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereBetween('created_at', [$from, $to]);
            })
            ->selectRaw('transaction_items.variant_name')
            ->selectRaw('SUM(transaction_items.qty) as qty')
            ->selectRaw('SUM(transaction_items.subtotal) as revenue')
            ->selectRaw('SUM(transaction_items.qty * product_variants.cost_price) as cogs')
            ->groupBy('transaction_items.variant_name')
            ->orderByDesc('qty')
            ->get()
            ->map(function ($row) {
                $revenue = (float) $row->revenue;
                $cogs = (float) $row->cogs;
                $margin = $revenue - $cogs;

                return [
                    'variant_name' => $row->variant_name,
                    'qty'          => (int) $row->qty,
                    'revenue'      => $revenue,
                    'cogs'         => $cogs,
                    'margin'       => $margin,
                    'margin_pct'   => $revenue > 0 ? round($margin / $revenue * 100, 2) : 0.0,
                ];
            });
    }

    /**
     * Proyeksi sederhana: rata-rata gross profit harian × jumlah hari periode berikutnya.
     * Asumsi: tren linear, tanpa musiman. Indikasi, bukan forecast presisi.
     *
     * @return array{basis_days: int, avg_daily_profit: float, projected_next_period: float}
     */
    public function projection(Carbon $from, Carbon $to): array
    {
        $days = max(1, $from->diffInDays($to) + 1);
        $profit = $this->overallProfit($from, $to)['gross_profit'];
        $avgDaily = $profit / $days;

        return [
            'basis_days'            => $days,
            'avg_daily_profit'      => round($avgDaily, 2),
            'projected_next_period' => round($avgDaily * $days, 2),
        ];
    }

    /**
     * Query COGS dasar: SUM(qty × cost_price) via withTrashed variant.
     */
    private function cogsQuery(Carbon $from, Carbon $to)
    {
        return TransactionItem::query()
            ->join('product_variants', 'transaction_items.product_variant_id', '=', 'product_variants.id')
            ->whereHas('transaction', function ($q) use ($from, $to) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereBetween('created_at', [$from, $to]);
            })
            ->selectRaw('SUM(transaction_items.qty * product_variants.cost_price) as cogs');
    }
}
```

> **Tenant scoping:** `Transaction` & relasi `TransactionItem→transaction` sudah kena `TenantScope` global → otomatis ter-scope tenant login. Saat dipanggil dari Job (tanpa `auth()`) di `PHASE-AI-3`, konteks tenant di-handle di Job.

## 2. AiContextService

Merakit *context array* ringkas & deterministik yang jadi base knowledge LLM.

**File:** `app/Services/AiContextService.php`

```php
<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;

class AiContextService
{
    public function __construct(
        private ProfitService $profitService,
        private BadgeHelperService $badgeHelper,
    ) {}

    /**
     * Bangun konteks agregat untuk LLM. HANYA data agregat — tanpa PII.
     *
     * @return array<string, mixed>
     */
    public function buildContext(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        $completed = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to]);

        $revenue = (float) (clone $completed)->sum('total_amount');
        $count = (clone $completed)->count();

        $topProducts = TransactionItem::query()
            ->whereHas('transaction', function ($q) use ($from, $to) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereBetween('created_at', [$from, $to]);
            })
            ->selectRaw('variant_name, SUM(qty) as qty, SUM(subtotal) as revenue')
            ->groupBy('variant_name')
            ->orderByDesc('qty')
            ->take(10)
            ->get();

        $dailyTrend = (clone $completed)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return [
            'business' => [
                'name'   => $tenant->name,
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ],
            'sales' => [
                'revenue'           => $revenue,
                'transaction_count' => $count,
                'average_ticket'    => $count > 0 ? round($revenue / $count) : 0,
            ],
            'profit'         => $this->profitService->overallProfit($from, $to),
            'projection'     => $this->profitService->projection($from, $to),
            'profit_by_item' => $this->profitService->profitByProduct($from, $to),
            'top_products'   => $topProducts,
            'daily_trend'    => $dailyTrend,
            'inventory'      => $this->badgeHelper->generate($tenant), // low/out/dead/near-expiry
        ];
    }
}
```

## 3. Tests

**File:** `tests/Feature/ProfitServiceTest.php`, `tests/Feature/AiContextServiceTest.php`

- `ProfitService`: buat product + variant (`cost_price`/`price` diketahui) + transaksi via factory → assert `gross_profit` & `margin_pct`; **sertakan kasus variant di-soft-delete** tetap terhitung (`->withTrashed()`).
- `AiContextService`: assert struktur array & angka agregat sesuai data seed (revenue, `top_products`, `profit`).

Jalankan: `php artisan test --compact --filter="ProfitService|AiContextService"`

## 4. Lanjutan: MCP Server → **Selesai** (`PHASE-AI-4`)

Sudah diimplementasikan. Detail lengkap: **`PHASE-AI-4_MCP-Server.md`**.

- `laravel/mcp` **sudah terpasang** (bawaan) — tidak perlu `composer require`.
- Mengekspos `AiContextService` / `ProfitService` / `ProductCatalogService` sebagai MCP **tools** (`get-sales-summary`, `get-profit`, `get-menu`) di `POST /mcp/business`, ter-scope tenant lewat token Sanctum + middleware `role:owner`.
- **Tanpa logika baru** — reuse service yang sama.

## 5. Checklist

- [x] `ProfitService` — `overallProfit`, `profitByProduct`, `projection` (COGS varian trashed tetap terhitung via join, status completed)
- [x] `AiContextService` — `buildContext` mengembalikan agregat lengkap tanpa PII
- [x] Reuse pola query dari `ReportController`/`DashboardController`/`BadgeHelperService` (bukan duplikasi)
- [x] Test `ProfitService` (termasuk variant trashed) hijau
- [x] Test `AiContextService` hijau
- [x] `vendor/bin/pint --dirty --format agent` bersih
- [x] §4 MCP Server — Selesai (lihat `PHASE-AI-4_MCP-Server.md`)

### Test Manual
1. `php artisan tinker` → `app(App\Services\ProfitService::class)->overallProfit(now()->subDays(30), now())` → cek `gross_profit`/`margin_pct` masuk akal vs data.
2. `app(App\Services\AiContextService::class)->buildContext($tenant, $from, $to)` → cek array lengkap & tak ada field PII.
