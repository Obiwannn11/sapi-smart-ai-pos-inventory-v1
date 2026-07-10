# PHASE AI-4 — MCP Server (Data Bridge Read-Only)

**Status:** Selesai  
**Dependency:** `PHASE-AI-1` (`ProfitService`, `AiContextService`) + katalog produk  
**Output:** MCP server `POST /mcp/business` yang mengekspos data agregat tenant (penjualan, profit, menu) sebagai sumber data **read-only** untuk AI client milik owner (mis. Claude Desktop)

> Lanjutan dari `PHASE-AI-1` §4. `laravel/mcp` **sudah terpasang** — tidak perlu `composer require`.

---

## Konteks

**MCP Server ≠ AI Analysis engine.** Di `PHASE-AI-2`/`AI-3`, **app** yang memanggil LLM (atas biaya app, karena itu berkuota). Di `AI-4`, LLM yang memanggil adalah **client milik owner** — app hanya menyediakan data. Konsekuensi:

- Tidak ada biaya LLM di sisi app → **tidak ada kuota/free-tier**. Cukup rate limit anti-abuse.
- **Reuse penuh** service `AI-1` (`ProfitService`, `AiContextService`) + `ProductCatalogService`. Tanpa logika bisnis baru.

### Prinsip WAJIB
- Hanya **data agregat, tanpa PII** (dijamin `AiContextService`).
- **Owner-only**, tenant-scoped otomatis via `TenantScope` (berbasis `auth()`).
- **Read-only** — semua tool memakai atribut `#[IsReadOnly]`.
- **Pint** + **Pest** tiap perubahan.

---

## Arsitektur

```
app/Mcp/Servers/SapiBusinessServer.php     # metadata + daftar 3 tool
app/Mcp/Tools/BusinessDataTool.php         # abstract: guard owner + tenant + periode + response
app/Mcp/Tools/GetSalesSummaryTool.php      # AiContextService (slice sales)
app/Mcp/Tools/GetProfitTool.php            # ProfitService
app/Mcp/Tools/GetMenuTool.php              # ProductCatalogService
app/Services/ProductCatalogService.php     # katalog bersama (MCP + API consumer)
routes/ai.php                              # Mcp::web(...) + middleware
app/Providers/AppServiceProvider.php       # RateLimiter 'mcp'
app/Http/Controllers/Owner/SettingsController.php  # generate/cabut token MCP
resources/js/Pages/Owner/Settings/Index.vue        # UI token MCP
```

---

## 1. ProductCatalogService (shared)

Query katalog produk aktif diekstrak dari `ApiProductController` menjadi service bersama.

- `activeMenu(bool $inStockOnly = false)` — produk aktif + varian + kategori, ter-scope tenant.
- `ApiProductController` memanggil `activeMenu(inStockOnly: true)` (perilaku lama dipertahankan). `GetMenuTool` memakai default (menu penuh + harga + stok).

## 2. Server & Tools

- **`SapiBusinessServer`** — `#[Name]`/`#[Version]`/`#[Instructions]` + daftar 3 tool. Tanpa resource/prompt (v1).
- **`BusinessDataTool`** (abstract, basis semua tool):
  - Guard **owner** → non-owner dapat `Response::error`.
  - `Auth::setUser($request->user())` untuk mengaktifkan `TenantScope` (berbasis `auth()`) — konsisten di HTTP maupun unit test.
  - `period()` — validasi & default rentang tanggal (30 hari terakhir); `periodSchema()` — skema input `from`/`to`.
  - Bungkus hasil `data()` subclass sebagai `Response::structured()`.
- **Tools** (nama auto-derived, hyphenated):

| Tool | Reuse | Data |
|---|---|---|
| `get-sales-summary` | `AiContextService::buildContext` (slice) | sales, top_products, daily_trend |
| `get-profit` | `ProfitService` | profit, projection, profit_by_item |
| `get-menu` | `ProductCatalogService::activeMenu` | produk aktif + varian |

## 3. Route & Auth

```php
Mcp::web('/mcp/business', SapiBusinessServer::class)
    ->middleware(['auth:sanctum', 'tenant.api', 'role:owner', 'throttle:mcp']);
```
- Reuse middleware existing: `tenant.api` (`EnsureTenantApi`), `role:owner` (`EnsureRole`).
- RateLimiter `mcp` di `AppServiceProvider::boot` — 60 req/menit per user (fallback IP).

## 4. Token akses (Settings)

Owner mint/rotasi/cabut token Sanctum `mcp-client` (ability `mcp:use`) dari halaman Settings.
- Plaintext token **di-flash sekali** (via `flash.mcpToken`), tak pernah disimpan/ditampilkan ulang.
- Props hanya mengirim `mcp.token_set` (boolean) + `mcp.endpoint` (URL) — bukan token.

## 5. Tests

- `tests/Feature/ProductCatalogServiceTest.php` — default vs in-stock, produk nonaktif, scoping tenant.
- `tests/Feature/Mcp/BusinessToolsTest.php` — tiap tool (owner), guard non-owner, validasi periode, scoping lintas-tenant.
- `tests/Feature/Mcp/McpEndpointTest.php` — endpoint tolak request tanpa auth (401).
- `tests/Feature/Owner/SettingsMcpTest.php` — generate (flash sekali) + rotasi + cabut + props aman + non-owner ditolak.

Jalankan: `php artisan test --compact --filter="ProductCatalogService|BusinessTools|McpEndpoint|SettingsMcp"`

## 6. Checklist

- [x] `ProductCatalogService` + refactor `ApiProductController`
- [x] `BusinessDataTool` (base) → `GetSalesSummaryTool`, `GetProfitTool`, `GetMenuTool`
- [x] `SapiBusinessServer` + metadata
- [x] `routes/ai.php` + middleware owner + RateLimiter `mcp`
- [x] Token MCP di Settings (generate/rotate/cabut, tampil sekali)
- [x] Tests (tool, endpoint auth, scoping, validasi, token) hijau
- [x] `vendor/bin/pint --dirty --format agent` bersih

---

## Backlog Tools/Fitur (belum dikerjakan)

1. `get-inventory-alerts` — stok menipis/habis/dead/near-expiry (reuse `BadgeHelperService`).
2. `get-business-context` — full `AiContextService::buildContext()` sekali panggil.
3. `get-payment-breakdown` — revenue per metode pembayaran.
4. `get-cash-summary` — rekap sesi kas / shift (owner).
5. `compare-periods` — delta periode-vs-periode.
6. **MCP Prompts** — template siap-pakai (mis. `weekly-review`, `discount-advice`) yang reuse prompt dari `RunAiAnalysisJob`.
7. **MCP Resources** — profil bisnis / menu sebagai resource statis.
8. **Write tools** (jauh ke depan) — mis. set diskon/harga; butuh mutasi + otorisasi ketat; **di luar** cakupan read-only v1.

## Test Manual (MCP Inspector)

1. Settings → owner **Generate Token** → salin plaintext token.
2. `php artisan mcp:inspector mcp/business` → sambungkan dengan header `Authorization: Bearer <token>`.
3. Panggil `get-sales-summary` / `get-profit` (opsional `from`/`to`) & `get-menu` → cek data agregat sesuai laporan.
4. Non-owner / tanpa token → ditolak.
