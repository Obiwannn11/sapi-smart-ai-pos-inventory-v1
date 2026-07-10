# PHASE QUEUE — Antrian Dapur (Mode Street-Food / Bazar)

**Status:** Rencana — belum dikerjakan
**Estimasi:** Setelah Self-Order (fulfillment fields sudah ada) stabil
**Dependency:** `SAPI-SelfOrder-Implementation-Plan.md` — reuse kolom `fulfillment_status`, `source`, `order_type`, `customer_name`, `table_number` + helper `Transaction::advanceFulfillment()`
**Output:** Papan antrian dapur (KDS) untuk operator merangkap kasir + masak ("si superman"), dengan prioritas naik/turun, plus fondasi **Tenant Capability Flags**
**Dipakai oleh:** Kasir + Owner (tanpa role baru)

> Fitur ini menambah **lini operasi** baru (kasir kaki lima / tenant bazar) tanpa refactor. Prinsipnya: **aktifkan** kolom fulfillment yang sudah tertanam dari fitur Self-Order, beri wajah (papan operator), tambah nomor antrian + prioritas.

---

## Konteks

Skema saat ini berorientasi kasir cafe. Untuk UMKM kaki lima, satu orang sering merangkap **masak + kasir**, sehingga butuh melacak pesanan mana yang masuk duluan dan mana yang perlu didahulukan dimasak.

Temuan kode yang membentuk desain ini:

- **Fondasi antrian sudah ada.** Tabel `transactions` punya `fulfillment_status` enum(`waiting`→`preparing`→`ready`→`done`, nullable), plus `source` (`pos`/`self_order`), `order_type` (`dine_in`/`pickup`), `customer_name`, `table_number`. Lihat migration `2026_03_06_300001_add_selforder_and_fulfillment_to_transactions.php`.
- **Advance sudah ada** di `app/Models/Transaction.php` → `advanceFulfillment()` (waiting → preparing → ready → done).
- **Endpoint advance sudah ada tapi hanya di API Sanctum:** `ApiOrderController@updateFulfillment` (`PATCH /api/v1/orders/{transaction}/fulfillment`). Belum ada di web route Inertia dan belum ada UI-nya.
- **Order POS langsung-bayar belum masuk antrian.** Di `TransactionService@checkout` (baris ~41): `fulfillment_status` di-set `null` untuk POS biasa; hanya open-bill & self-order (setelah bayar) yang jadi `waiting`.
- **Komponen modal reusable sudah ada:** `resources/js/Components/ConfirmDialog.vue` dan `Modal.vue` → dipakai ulang untuk konfirmasi naik/turun prioritas (jangan bikin baru).
- **Settings sudah pola tenant-update:** `Owner/SettingsController` meng-`update()` kolom di `tenants` → tempat natural menaruh toggle mode.

### Keputusan yang sudah dikunci (diskusi sebelumnya)

1. **Aktivasi mode = toggle di Settings** (per tenant/outlet), bukan role user baru.
2. **Papan antrian menggabungkan semua sumber** — order POS (kasir) + self-order (QR/Telegram) tampil di satu papan.
3. **Prioritas = tombol naik/turun**, dengan **modal konfirmasi di setiap aksi** (reuse `ConfirmDialog.vue`).

### Prinsip WAJIB

- **Zero-refactor pada mode cafe.** Semua perilaku baru digerbang di balik flag `kitchen_queue_enabled`. Flag mati → alur cafe sekarang 0 berubah.
- **Satu sumber logika fulfillment.** Pindahkan logika advance ke `FulfillmentService`; web + API + mobile memanggil service yang sama (hindari duplikasi seperti yang sekarang ada di `ApiOrderController`).
- **Tenant scoping.** Semua query antrian ter-scope tenant otomatis via `BelongsToTenant`/`TenantScope`. Endpoint tetap cek `tenant_id` eksplisit (pola yang sudah dipakai di `POSController`).
- **Migrasi hanya menambah kolom** (bukan mengubah) → aman dari jebakan "modifying column must include all attributes".
- **Pint** setelah edit PHP: `vendor/bin/pint --dirty --format agent`. Setiap perubahan **diuji** (Pest).

---

## Daftar Isi

0. [Fondasi — Tenant Capability Flags](#0-fondasi--tenant-capability-flags)
1. [Migrasi](#1-migrasi)
2. [Backend](#2-backend)
3. [Frontend](#3-frontend)
4. [Routes & Settings](#4-routes--settings)
5. [Tests](#5-tests)
6. [Checklist](#6-checklist)
7. [Keputusan Terbuka](#7-keputusan-terbuka)

---

## 0. Fondasi — Tenant Capability Flags

Antrian dapur, self-order, dan gating AI sama-sama butuh jawaban: **fitur mana yang aktif untuk tenant ini?** Daripada tiga solusi ad-hoc, buat **satu fondasi feature-flag** yang dipakai bertiga.

### Rekomendasi: hybrid (preset saat daftar + toggle runtime)

- `business_type` (dipilih saat registrasi) → **preset** yang mengisi nilai awal flag + positioning paket.
- Kapabilitas sebenarnya = **boolean flags** yang bisa di-toggle di Settings.
- Akses digerbang lewat **satu helper** supaya konsisten di route, nav, dan API.

> Perbandingan lengkap toggle vs registration ada di [Bagian 7 Keputusan Terbuka](#7-keputusan-terbuka). Bagian ini menuliskan bentuk hybrid yang direkomendasikan; kalau nanti diputuskan "toggle murni", cukup buang kolom `business_type`.

### Helper di model `Tenant`

```php
// app/Models/Tenant.php — tambahan

/** @var list<string> */
protected $fillable = [
    'name', 'slug', 'logo', 'address', 'phone',
    'ai_provider', 'ai_api_key', 'ai_model',
    'business_type', 'kitchen_queue_enabled', 'self_order_enabled', 'ai_enabled', // ← baru
];

protected function casts(): array
{
    return [
        'ai_api_key'            => 'encrypted',
        'kitchen_queue_enabled' => 'boolean',
        'self_order_enabled'    => 'boolean',
        'ai_enabled'            => 'boolean',
    ];
}

/**
 * Apakah kapabilitas tertentu aktif untuk tenant ini.
 * Satu pintu gerbang — dipakai route, menu nav, controller, & API.
 */
public function hasFeature(string $feature): bool
{
    return (bool) match ($feature) {
        'kitchen_queue' => $this->kitchen_queue_enabled,
        'self_order'    => $this->self_order_enabled,
        'ai'            => $this->ai_enabled,
        default         => false,
    };
}
```

### Gerbang akses (middleware ringan)

Satu middleware `feature:<name>` untuk melindungi route (mis. `feature:kitchen_queue`, `feature:ai`). Untuk menu nav, share flag ke Inertia via `HandleInertiaRequests` (`auth.tenant.features`) supaya sidebar hanya render menu yang aktif.

```php
// app/Http/Middleware/EnsureTenantFeature.php (baru)
public function handle(Request $request, Closure $next, string $feature): Response
{
    if (! $request->user()?->tenant?->hasFeature($feature)) {
        abort(403, 'Fitur ini tidak aktif untuk outlet Anda.');
    }
    return $next($request);
}
```

---

## 1. Migrasi

Dua migration terpisah agar niatnya jelas (flags vs kolom antrian).

### 1a. Flags di `tenants`

```php
Schema::table('tenants', function (Blueprint $table) {
    $table->string('business_type')->default('cafe')->after('phone'); // cafe | street_food
    $table->boolean('kitchen_queue_enabled')->default(false)->after('business_type');
    $table->boolean('self_order_enabled')->default(false)->after('kitchen_queue_enabled');
    $table->boolean('ai_enabled')->default(true)->after('self_order_enabled'); // default true = jaga perilaku sekarang
});
```

> **Default sengaja menjaga status quo:** `ai_enabled = true` supaya tenant yang sudah pakai AI tidak tiba-tiba mati. `self_order`/`kitchen_queue` default `false` karena fitur baru — di-opt-in.

### 1b. Kolom antrian di `transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->unsignedInteger('queue_number')->nullable()->after('fulfillment_status'); // nomor antrian harian
    $table->unsignedBigInteger('sort_index')->nullable()->after('queue_number');       // kunci urutan manual
    $table->timestamp('preparing_at')->nullable()->after('sort_index');                // opsional: timer & analitik
    $table->timestamp('ready_at')->nullable()->after('preparing_at');
});
```

- `queue_number` — nomor pendek untuk dipanggil ("nomor 12!"), reset **harian per tenant**. Beda dari `code` (TRX-YYYYMMDD-XXX) yang kepanjangan.
- `sort_index` — kunci urutan. Diisi awal dari `created_at` epoch (mis. `now()->valueOf()`) supaya tiap kartu unik (anti-tie). Tombol naik/turun **menukar** nilai ini dengan kartu tetangga.
- `preparing_at` / `ready_at` — opsional tapi murah: dasar timer "sudah nunggu berapa lama" + bahan analitik rata-rata waktu masak untuk AI nanti.

Jangan lupa tambah keempatnya ke `$fillable` `Transaction`, dan cast `preparing_at`/`ready_at` ke `datetime`.

---

## 2. Backend

### 2a. `FulfillmentService` (FILE BARU) — satu sumber logika

Pindahkan logika advance dari `ApiOrderController@updateFulfillment` ke sini; controller (web/API/mobile) jadi tipis dan konsisten.

**File:** `app/Services/FulfillmentService.php`

```php
<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class FulfillmentService
{
    /** Status yang dianggap "aktif" di papan antrian. */
    private const ACTIVE = [
        Transaction::FULFILLMENT_WAITING,
        Transaction::FULFILLMENT_PREPARING,
        Transaction::FULFILLMENT_READY,
    ];

    /**
     * Majukan satu step: waiting → preparing → ready → done.
     * Set timestamp preparing_at/ready_at untuk timer & analitik.
     */
    public function advance(Transaction $transaction): Transaction
    {
        if (! $transaction->hasFulfillmentTracking()) {
            throw new \Exception('Transaksi ini tidak punya fulfillment tracking.');
        }
        if ($transaction->fulfillment_status === Transaction::FULFILLMENT_DONE) {
            throw new \Exception('Pesanan sudah selesai.');
        }

        $before = $transaction->fulfillment_status;
        $transaction->advanceFulfillment();

        // Stempel waktu transisi (idempoten via isDirty guard sederhana)
        if ($before === Transaction::FULFILLMENT_WAITING && ! $transaction->preparing_at) {
            $transaction->update(['preparing_at' => now()]);
        }
        if ($transaction->fulfillment_status === Transaction::FULFILLMENT_READY && ! $transaction->ready_at) {
            $transaction->update(['ready_at' => now()]);
        }

        return $transaction->refresh();
    }

    /** Naikkan prioritas: tukar sort_index dengan tetangga di ATAS (index lebih kecil). */
    public function moveUp(Transaction $transaction): Transaction
    {
        return $this->swapWithNeighbor($transaction, direction: 'up');
    }

    /** Turunkan prioritas: tukar sort_index dengan tetangga di BAWAH (index lebih besar). */
    public function moveDown(Transaction $transaction): Transaction
    {
        return $this->swapWithNeighbor($transaction, direction: 'down');
    }

    /**
     * Tukar sort_index dengan kartu tetangga terdekat pada papan aktif tenant yang sama.
     * Papan diurut sort_index ASC (kecil = atas = didahulukan).
     */
    private function swapWithNeighbor(Transaction $transaction, string $direction): Transaction
    {
        return DB::transaction(function () use ($transaction, $direction) {
            $base = Transaction::where('tenant_id', $transaction->tenant_id)
                ->whereIn('fulfillment_status', self::ACTIVE)
                ->lockForUpdate();

            $neighbor = $direction === 'up'
                ? (clone $base)->where('sort_index', '<', $transaction->sort_index)->orderByDesc('sort_index')->first()
                : (clone $base)->where('sort_index', '>', $transaction->sort_index)->orderBy('sort_index')->first();

            if (! $neighbor) {
                return $transaction; // sudah paling atas/bawah — no-op
            }

            [$a, $b] = [$transaction->sort_index, $neighbor->sort_index];
            $transaction->update(['sort_index' => $b]);
            $neighbor->update(['sort_index' => $a]);

            return $transaction->refresh();
        });
    }
}
```

> Refactor `ApiOrderController@updateFulfillment` agar memanggil `FulfillmentService::advance()` — buang logika duplikat.

### 2b. `TransactionService@checkout` — order POS masuk antrian saat mode aktif

Ubah penentuan `fulfillment_status` + isi `queue_number` & `sort_index` **hanya** kalau tenant mengaktifkan mode.

```php
$queueMode = $user->tenant->hasFeature('kitchen_queue');

// POS langsung-bayar: kalau mode antrian → masuk 'waiting'; kalau tidak → null (perilaku lama)
$fulfillmentStatus = ($isOpenBill || $queueMode)
    ? Transaction::FULFILLMENT_WAITING
    : null;

$transaction = Transaction::create([
    // ... field lain tetap ...
    'fulfillment_status' => $fulfillmentStatus,
    'queue_number'       => $fulfillmentStatus ? $this->generateQueueNumber($tenantId) : null,
    'sort_index'         => $fulfillmentStatus ? now()->valueOf() : null,
]);
```

Generator nomor antrian harian (pola sama dengan `generateTransactionCode`):

```php
/** Nomor antrian harian per tenant, reset tiap hari. */
private function generateQueueNumber(int $tenantId): int
{
    $today = now()->startOfDay();

    $last = Transaction::where('tenant_id', $tenantId)
        ->whereNotNull('queue_number')
        ->where('created_at', '>=', $today)
        ->lockForUpdate()
        ->max('queue_number');

    return (int) $last + 1;
}
```

> **Catatan self-order:** `confirmSelfOrderPayment()` juga set `fulfillment_status = waiting` — tambahkan `queue_number` + `sort_index` di titik itu juga supaya order QR ikut nongol di papan yang sama.

### 2c. `Cashier\QueueController` (FILE BARU)

```php
<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\FulfillmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    public function __construct(private FulfillmentService $fulfillment) {}

    /** Papan antrian aktif — gabung POS + self-order, urut sort_index ASC. */
    public function index(): Response
    {
        $queue = Transaction::whereIn('fulfillment_status', [
                Transaction::FULFILLMENT_WAITING,
                Transaction::FULFILLMENT_PREPARING,
                Transaction::FULFILLMENT_READY,
            ])
            ->with(['items.modifiers'])
            ->orderBy('sort_index') // kecil = didahulukan
            ->get();

        return Inertia::render('Cashier/Queue', ['queue' => $queue]);
    }

    public function advance(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTenant($transaction);
        try {
            $this->fulfillment->advance($transaction);
            return back();
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function moveUp(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTenant($transaction);
        $this->fulfillment->moveUp($transaction);
        return back();
    }

    public function moveDown(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTenant($transaction);
        $this->fulfillment->moveDown($transaction);
        return back();
    }

    private function authorizeTenant(Transaction $transaction): void
    {
        if ($transaction->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }
    }
}
```

---

## 3. Frontend

### `Cashier/Queue.vue` (FILE BARU) — papan KDS

- **Layout:** kartu besar touch-friendly (operator lagi masak, tangan repot). `queue_number` menonjol, badge sumber (Kasir / Self-Order), daftar item + `notes` + modifier, timer "menunggu Xm".
- **Aksi status:** satu tombol besar per kartu: **Mulai masak → Siap → Selesai** → POST ke `cashier.queue.advance`.
- **Prioritas naik/turun:** tombol ▲ / ▼ per kartu. **Setiap klik buka `ConfirmDialog.vue`** (reuse): "Dahulukan pesanan #12?" / "Turunkan pesanan #12?". Konfirmasi → POST `cashier.queue.move-up` / `move-down`.
- **Real-time:** Inertia v2 polling ~5–10 dtk: `router.reload({ only: ['queue'] })` via `setInterval` di `onMounted` (bersihkan di `onUnmounted`). Upgrade ke Laravel Reverb/websocket = lanjutan, bukan sekarang.
- **Nav:** menu "Antrian" hanya render kalau `features.kitchen_queue` true (dari shared Inertia props).

Pola form/aksi mengikuti halaman kasir yang sudah ada (`useForm` + `router.post`), lihat `Cashier/POS.vue` sebagai acuan.

---

## 4. Routes & Settings

### Web routes (grup `cashier.`, digerbang `feature:kitchen_queue`)

```php
Route::middleware('feature:kitchen_queue')->group(function () {
    Route::get('/queue', [QueueController::class, 'index'])->name('queue');
    Route::post('/queue/{transaction}/advance', [QueueController::class, 'advance'])->name('queue.advance');
    Route::post('/queue/{transaction}/move-up', [QueueController::class, 'moveUp'])->name('queue.move-up');
    Route::post('/queue/{transaction}/move-down', [QueueController::class, 'moveDown'])->name('queue.move-down');
});
```

### Settings (owner)

- `SettingsController@index`: kirim `business_type`, `kitchen_queue_enabled`, `self_order_enabled`, `ai_enabled` ke view.
- `SettingsController@update`: validasi `boolean` untuk tiap flag + `in:cafe,street_food` untuk `business_type`.
- `Owner/Settings/Index.vue`: section "Mode Outlet" dengan toggle untuk tiap flag (pakai `Checkbox.vue` yang sudah ada). Beri deskripsi singkat tiap fitur.

---

## 5. Tests (Pest)

**File:** `tests/Feature/KitchenQueueTest.php`, `tests/Unit/FulfillmentServiceTest.php`

- Checkout saat `kitchen_queue_enabled` → `fulfillment_status = waiting`, `queue_number` terisi & increment, `sort_index` terisi.
- Checkout saat flag mati → `fulfillment_status = null` (perilaku cafe tak berubah).
- `queue_number` **reset harian** (order kemarin vs hari ini mulai dari 1).
- `FulfillmentService::advance` transisi benar + stempel `preparing_at`/`ready_at`, dan mentok di `done`.
- `moveUp`/`moveDown` menukar `sort_index`; aman di batas (kartu teratas `moveUp` = no-op).
- `QueueController@index` hanya menampilkan order tenant sendiri, gabung `source` POS + self_order.
- Route `feature:kitchen_queue` menolak (403) tenant yang flag-nya mati.

Jalankan: `php artisan test --compact --filter="KitchenQueue|Fulfillment"`

---

## 6. Checklist

- [ ] Migrasi flags `tenants` (business_type + 3 boolean) — default menjaga status quo
- [ ] Migrasi kolom antrian `transactions` (queue_number, sort_index, preparing_at, ready_at)
- [ ] `Tenant::hasFeature()` + fillable/casts + middleware `feature:<name>`
- [ ] `FulfillmentService` (advance + moveUp/moveDown) — refactor `ApiOrderController` agar reuse
- [ ] `TransactionService@checkout` + `confirmSelfOrderPayment` mengisi queue_number/sort_index saat mode aktif
- [ ] `Cashier\QueueController` + web routes (digerbang flag)
- [ ] `Cashier/Queue.vue` (polling + ConfirmDialog reuse) + menu nav bersyarat
- [ ] Settings: toggle mode + Vue section
- [ ] Tests hijau (`KitchenQueue|Fulfillment`)
- [ ] `vendor/bin/pint --dirty --format agent` bersih

### Urutan kerja disarankan
Fase 0–1 (fondasi + migrasi) → 2 (service + checkout) → **5 test backend dulu** (buktikan logika sebelum UI) → 3 → 4.

---

## 7. Keputusan Terbuka

### A. Model aktivasi fitur — toggle vs registration

Belum dikunci final. Ringkasan trade-off:

| Aspek | Toggle di Settings | Ditentukan saat daftar (`business_type`) |
|---|---|---|
| **User — fleksibilitas** | ✅ Aktif/nonaktif situasional (self-order cuma pas bazar) | ❌ Kaku; ganti model usaha = ganti tipe |
| **User — onboarding** | ❌ Bisa bingung fitur mana dinyalakan | ✅ Terarah sejak awal |
| **User — hemat kuota AI** | ✅ Matikan saat tak perlu | ⚠️ Biasanya paket = semua on/off |
| **Bisnis — paket jual** | ⚠️ Kurang tegas | ✅ Enak buat pricing/marketing |
| **Dev — implementasi** | ✅ Flags + gate, konsisten Settings | ⚠️ Enum + seeding + factory |
| **Dev — migrasi antar-mode** | ✅ Cukup flip flag | ❌ Butuh fitur "ganti tipe" + edge case |
| **Dev — kill-switch** | ✅ Bisa tanpa deploy | ❌ Tidak situasional |
| **Dev — ruang state/test** | ⚠️ Kombinasi flag banyak, wajib disiplin test | ✅ Matriks tipe terbatas |

**Rekomendasi: hybrid** — `business_type` sebagai **preset** saat daftar yang mengisi flag awal, tapi kapabilitas tetap **boolean flags** yang bisa di-toggle di Settings. Onboarding terarah + fleksibel runtime, implementasi tetap "flags + preset". Kalau nanti pilih "toggle murni", cukup buang kolom `business_type` dari Bagian 0/Bagian 1.

### B. Cakupan "cabang"
Skema sekarang **1 tenant = 1 outlet**. "Per cabang" = "per tenant". Multi-cabang di bawah 1 owner (tabel `branches`) adalah fitur terpisah yang lebih besar — **di luar scope** dokumen ini.

### C. Gating AI
`ai_enabled` (flag boleh/tidak) + kuota harian existing (`ai_usages` + `config('ai.free_tier.daily_limit')`) = dua lapis pembatas. Gerbang route AI dengan `feature:ai`.

### D. Real-time
MVP pakai **polling** (Inertia v2). Kalau butuh instan (papan tak boleh telat), upgrade ke **Laravel Reverb** (broadcasting) — butuh infra websocket, dijadwalkan sebagai lanjutan.
