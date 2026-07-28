# PHASE QUEUE — Antrian Dapur (Papan Operator)

**Status:** Rencana — belum dikerjakan
**Ditulis ulang:** 2026-07-28 (lihat [Riwayat Revisi](#riwayat-revisi))
**Dependency:** Fondasi **Tenant Capability Flags** (lihat [Bagian 1](#1-prasyarat--capability-flags)) — harus berdiri lebih dulu
**Output:** Papan antrian dapur untuk operator yang merangkap kasir + masak, digerbang flag `kitchen_queue_enabled`
**Dipakai oleh:** Kasir + Owner (tanpa role baru)

> Fitur ini memberi **wajah** pada kolom fulfillment yang sudah tertanam sejak Self-Order, lalu menambahkan nomor antrian dan prioritas. Ia menambah lini operasi baru **tanpa** mengubah alur kasir cafe yang sudah berjalan.

---

## Riwayat Revisi

> Bagian ini sengaja dipertahankan di dokumen. Versi pertama ditulis dalam bentuk instruksi siap-salin, dan sebagian isinya berubah dari "benar" jadi "merusak" bukan karena salah sejak awal, melainkan karena sistem di sekitarnya bergerak. Menuliskan apa yang berubah mencegah orang berikutnya menghidupkan kembali keputusan yang sudah gugur.

**Versi 2026-07 (asli).** Ditulis tak lama setelah fondasi self-order selesai. Isinya benar untuk kode saat itu.

**Versi 2026-07-28 (dokumen ini).** Ditulis ulang setelah peninjauan teknis yang tercatat di `[BL-019]` pada `docs/BACKLOG.md`. Tiga pekerjaan besar mendarat di antara kedua versi dan menggeser tanah tempat versi pertama berdiri: **PHASE SAAS** membawa siklus hidup langganan berikut gerbang hanya-baca, **`[BL-015]`** menempati `tenants.business_type` sebagai dimensi harga, dan **PHASE PWA** menjadikan penjualan offline jalur tulis kedua yang setara.

Yang berubah, dan kenapa:

| Versi asli | Sekarang | Alasan |
|---|---|---|
| Bagian 0 memuat fondasi capability flags | Flags jadi **fase tersendiri**, dokumen ini konsumennya | Tiga fitur mengantre di fondasi yang sama; `PHASE-FEATURE-FLAGS` sudah berdiri sendiri sambil menunjuk balik ke sini — ketergantungan melingkar |
| Kolom `business_type` (`cafe`/`street_food`) | **Dicabut sepenuhnya** | Kolomnya sudah dipakai penetapan harga dan dibekukan ke `invoices.pricing_context`; toggle "Mode Outlet" akan diam-diam mengubah dasar tagihan |
| Prioritas = ▲/▼ + modal tiap tekan | **Dahulukan** satu-tap sebagai aksi utama | Mendahulukan dari posisi 10 butuh 9 tap + 9 konfirmasi, oleh orang yang tangannya sedang kotor |
| `sort_index` dari `now()` | Dari `effectiveDate()` | `now()` saat sinkronisasi melempar penjualan offline ke dasar papan padahal datang paling awal |
| Nomor antrian di-generate inline di checkout | Di balik seam `QueueNumberAllocator` | Fase offline akan menukar strateginya; penukaran harus jadi penggantian satu kelas |
| Papan tidak mengenal status bayar | **BELUM BAYAR** jadi syarat penerimaan | Operator tunggal bisa menandai "Selesai" atas pesanan yang belum ditagih |
| — | Migrasi **backfill** open bill lama | Temuan baru: timbunan `waiting` yang selama ini tak terlihat (lihat [2c](#2c-migrasi-backfill--membersihkan-timbunan-yang-belum-terlihat)) |

**Catatan pemeliharaan:** `docs/phases-2/PHASE-FEATURE-FLAGS_Capabilities-Sync.md` masih menunjuk *"PHASE-QUEUE Bagian 0"* sebagai tempat fondasi flag. Rujukan itu **sudah tidak berlaku** sejak dokumen ini ditulis ulang, dan dokumen tersebut perlu diperbarui di sesi tersendiri. Blok alias di Bagian 2-nya juga salah fakta — lihat catatan di [Bagian 1](#1-prasyarat--capability-flags).

---

## Konteks

Skema aplikasi ini berorientasi kasir cafe: pesanan dibayar, struk dicetak, selesai. Untuk warung ber-dapur dan pedagang kaki lima, satu orang sering merangkap **masak + kasir**, sehingga ia butuh tahu pesanan mana yang masuk duluan dan mana yang perlu didahulukan.

### Yang SUDAH ada di kode — jangan dibangun ulang

Fondasi antriannya sudah tertanam sejak Self-Order, dan ini yang membuat fase ini kecil:

- **Kolom fulfillment sudah ada.** `transactions` punya `fulfillment_status` enum (`waiting`→`preparing`→`ready`→`done`, nullable), plus `source` (`pos`/`self_order`), `order_type` (`dine_in`/`pickup`), `customer_name`, `table_number` — lihat `database/migrations/2026_03_06_300001_add_selforder_and_fulfillment_to_transactions.php`.
- **Transisi status sudah ada** di `Transaction::advanceFulfillment()` (`app/Models/Transaction.php:167`).
- **Endpoint advance sudah ada, tapi HANYA di API Sanctum:** `ApiOrderController@updateFulfillment` (`app/Http/Controllers/Api/V1/ApiOrderController.php:113`), `PATCH /api/v1/orders/{transaction}/fulfillment`. Belum ada padanan web, belum ada UI.
- **Tanggal penjualan sebenarnya sudah punya konvensi.** `Transaction::effectiveDate()` dan scope `whereEffectiveDate()` (`app/Models/Transaction.php:116`) memakai `occurred_at` bila ada, jatuh ke `created_at` bila tidak. **Semua** perhitungan bertanggal di fase ini wajib lewat sini.
- **Komponen modal reusable sudah ada:** `resources/js/Components/ConfirmDialog.vue` dan `Modal.vue`.
- **Permukaan kasir sudah punya polanya:** `resources/js/Pages/Cashier/POS.vue` memakai `CashierTopbar.vue` (bukan `OwnerLayout`), `useForm`/`router.post`, dan `useFlash`.

### Keputusan yang DIKUNCI (pemilik, 2026-07-28)

Tiga keputusan berikut menentukan bentuk seluruh dokumen dan **tidak dibuka ulang** di sini:

1. **Versi pertama ONLINE saja; offline jadi fase lanjutan.** Sasaran akhirnya tetap kaki lima/bazar, tapi papan versi pertama dibangun untuk outlet yang koneksinya wajar.
2. **Nilai inti papan = urutan pesanan.** Pengelompokan pekerjaan lintas pesanan (batching) di luar lingkup.
3. **Papan menampung pesanan belum lunas dan menandainya.** Open bill ikut masuk papan dengan penanda **BELUM BAYAR**, dan transisi ke `done` menuntut konfirmasi bila belum lunas.

### Yang SENGAJA di luar lingkup

Ditulis eksplisit supaya tidak terbaca sebagai kelalaian, dan supaya tidak dilaporkan sebagai bug:

- **Mode antrian tidak aktif saat perangkat offline.** Kasir kembali ke alur biasa, dan transaksi hasil sinkronisasi **tidak menyusul masuk papan**. Ini konsekuensi langsung Keputusan 1, dan **wajib disampaikan di UI** — lihat [4c](#4c-keadaan-offline--dinyatakan-bukan-didiamkan).
- **Pengelompokan pekerjaan (batching).** Tiga es teh di tiga pesanan berbeda tetap tampil sebagai tiga baris terpisah. Ini arah lanjutan yang layak, bukan kekurangan versi ini.
- **Fulfillment parsial** (sebagian item siap, sebagian belum). Satu pesanan = satu status.
- **Real-time websocket.** Versi pertama memakai polling; Reverb menyusul bila polling terbukti kurang.
- **Multi-cabang.** Skema sekarang 1 tenant = 1 outlet. "Per cabang" = "per tenant".

### Prinsip WAJIB

- **Zero-refactor pada mode cafe.** Semua perilaku baru digerbang `kitchen_queue_enabled`. Flag mati → alur cafe nol berubah. Satu pengecualian yang disengaja dan diuji: [3b](#3b-transactionservice--siapa-yang-masuk-papan).
- **Satu sumber logika fulfillment.** `FulfillmentService` jadi satu-satunya tempat; web, API, dan mobile memanggilnya. Duplikasi di `ApiOrderController` dibereskan sekalian.
- **Semua yang bertanggal lewat `effectiveDate()`.** Bukan `created_at` mentah. Gratis sekarang, wajib saat fase offline dibuka.
- **Tenant scoping ganda.** Query papan ter-scope otomatis lewat `BelongsToTenant`, dan endpoint tetap memeriksa `tenant_id` eksplisit — pola yang sudah dipakai `POSController`.
- **Migrasi hanya menambah kolom** (kecuali satu migrasi data yang memang bertugas membersihkan).
- **Pint** setelah edit PHP: `vendor/bin/pint --dirty --format agent`. Setiap perubahan **diuji** (Pest).

### Penjaga anti-kunci — harga dari memilih "bertahap"

Empat hal berikut hampir tak berbiaya hari ini karena online dan offline identik untuk transaksi biasa, tapi mahal sekali kalau baru disadari saat fase offline dibuka. **Jangan dilewati dengan alasan "nanti saja".**

1. **`queue_number` bukan identitas kartu.** Identitas tetap `id`/`code`; nomor antrian hanya label tampilan. Perangkat offline tidak akan bisa menjamin nomor unik lintas perangkat.
2. **Pengalokasian nomor di balik seam tersendiri** (`QueueNumberAllocator`), bukan ditanam di `TransactionService@checkout`.
3. **`sort_index` diturunkan dari `effectiveDate()`**, bukan `now()`.
4. **Batas hari pada papan juga memakai `effectiveDate()`.**

---

## Daftar Isi

1. [Prasyarat — Capability Flags](#1-prasyarat--capability-flags)
2. [Migrasi](#2-migrasi)
3. [Backend](#3-backend)
4. [Frontend](#4-frontend)
5. [Routes, Settings & Gerbang Langganan](#5-routes-settings--gerbang-langganan)
6. [Tests](#6-tests)
7. [Checklist](#7-checklist)
8. [Keputusan Terbuka](#8-keputusan-terbuka)

---

## 1. Prasyarat — Capability Flags

Fondasi flag **bukan milik dokumen ini** dan harus berdiri lebih dulu sebagai fase tersendiri. Yang dibutuhkan fase QUEUE hanya kontraknya:

```php
// app/Models/Tenant.php
$tenant->hasFeature('kitchen_queue'): bool
```

…berikut middleware web `feature:<name>` yang menjawab `403` bila flag mati, dan flag ikut dibagikan ke Inertia agar menu bisa bersyarat.

**Yang HARUS dikoreksi saat fase flags dikerjakan** (kesalahan ini ada di `PHASE-FEATURE-FLAGS` Bagian 2 dan akan merusak kalau disalin mentah):

- `tenant` dan `tenant.api` **adalah grup middleware, bukan alias** — lihat komentar panjang di `bootstrap/app.php:23` yang menjelaskan kenapa. Jangan didaftarkan ulang sebagai alias.
- Blok `$middleware->alias([...])` yang ada di dokumen tersebut **menghilangkan** `permission`, `permission.api`, `platform.can`, dan `platform.owner` yang sudah terdaftar. Menyalinnya apa adanya mematikan gating RBAC dan panel platform sekaligus. Tambahkan `feature` ke daftar yang sudah ada, jangan menggantinya.

**Tidak ada kolom `business_type` di fase ini.** Kolom bernama sama sudah dipakai penetapan harga (`config/pricing-dimensions.php:83`) dengan nilai `kuliner|retail|jasa|lainnya` dan dibekukan ke `invoices.pricing_context` setiap tagihan terbit. Aktivasi mode antrian **murni toggle di Settings**.

---

## 2. Migrasi

### 2a. Kolom antrian di `transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->unsignedInteger('queue_number')->nullable()->after('fulfillment_status');
    $table->unsignedBigInteger('sort_index')->nullable()->after('queue_number');
    $table->timestamp('preparing_at')->nullable()->after('sort_index');
    $table->timestamp('ready_at')->nullable()->after('preparing_at');
});
```

- **`queue_number`** — nomor pendek untuk dipanggil ("nomor 12!"), reset harian per tenant. `code` (TRX-YYYYMMDD-XXX) terlalu panjang untuk diteriakkan. **Bukan identitas** — hanya label.
- **`sort_index`** — kunci urutan papan. Diisi dari `effectiveDate()->getTimestampMs()`; tombol prioritas mengubah nilai ini.
- **`preparing_at` / `ready_at`** — dasar timer "sudah menunggu berapa lama", sekaligus bahan analitik rata-rata waktu masak untuk AI nanti. Murah, jadi diambil sekarang.

Tambahkan keempatnya ke `$fillable` `Transaction`, dan `preparing_at`/`ready_at` ke `casts()` sebagai `datetime`.

### 2b. Indeks papan

Migrasi terpisah supaya niatnya jelas:

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->index(['tenant_id', 'fulfillment_status', 'sort_index'], 'transactions_queue_board_index');
});
```

Query papan menyaring `tenant_id` + `fulfillment_status` lalu mengurutkan `sort_index` — persis bentuk indeks ini. Tanpa indeks, papan yang di-poll tiap 5 detik oleh setiap kasir aktif menjadi pemindaian tabel berulang pada tabel yang justru paling cepat tumbuh.

### 2c. Migrasi backfill — membersihkan timbunan yang belum terlihat

> **Ini bukan migrasi struktur, dan ia tidak bisa dibatalkan.** Tulis `down()` sebagai no-op berkomentar, jangan pura-pura reversibel.

**Masalahnya.** Open bill **sudah** memperoleh `fulfillment_status = waiting` sejak fitur itu ada (`TransactionService.php:60`), sementara `payOpenBill()` (`:264`) hanya mengubah `status` jadi `completed` dan **tidak pernah menyentuh `fulfillment_status`**. Artinya setiap open bill yang pernah dibuat — termasuk yang lunas dan selesai berbulan-bulan lalu — sampai detik ini masih berbunyi `waiting` di basis data. Selama ini tidak terlihat semata karena belum ada permukaan yang menampilkannya.

Begitu papan menyala, semuanya muncul serentak sebagai pesanan aktif. Batas hari saja **tidak cukup**: sebagian jatuh di hari yang sama dengan pengaktifan, dan yang lolos tetap salah — hanya tidak terlihat.

```php
// 1. Sudah tuntas: dibatalkan, atau open bill POS yang sudah lunas. Tidak ada
//    lagi yang perlu dimasak, berapa pun umurnya.
DB::table('transactions')
    ->whereNotNull('fulfillment_status')
    ->where('fulfillment_status', '!=', 'done')
    ->where(function ($q) {
        $q->where('status', 'voided')
          ->orWhere(fn ($s) => $s->where('status', 'completed')->where('source', 'pos'));
    })
    ->update(['fulfillment_status' => null]);

// 2. Apa pun yang lebih tua dari hari pemasangan. Papannya belum pernah ada,
//    jadi tak mungkin ada pesanan yang benar-benar masih menunggu dimasak —
//    termasuk self-order lunas yang tak pernah ada yang memajukannya.
//    Baris HARI INI sengaja disisakan: bisa jadi pesanan yang sungguh sedang
//    berjalan saat rilis dipasang.
DB::table('transactions')
    ->whereNotNull('fulfillment_status')
    ->where('fulfillment_status', '!=', 'done')
    ->where('created_at', '<', now()->startOfDay())
    ->update(['fulfillment_status' => null]);
```

Sumber timbunannya ditutup di [3b](#3b-transactionservice--siapa-yang-masuk-papan) supaya tidak menumpuk lagi.

---

## 3. Backend

### 3a. `QueueNumberAllocator` (FILE BARU) — seam untuk fase offline

**File:** `app/Services/Queue/QueueNumberAllocator.php` (interface) + `DailySequenceAllocator.php`

```php
interface QueueNumberAllocator
{
    /** Nomor antrian untuk satu tenant pada hari penjualan tertentu. */
    public function allocate(int $tenantId, Carbon $occurredAt): int;
}
```

```php
class DailySequenceAllocator implements QueueNumberAllocator
{
    public function allocate(int $tenantId, Carbon $occurredAt): int
    {
        $last = Transaction::where('tenant_id', $tenantId)
            ->whereNotNull('queue_number')
            ->whereEffectiveDate($occurredAt)   // scope yang sudah ada — bukan created_at
            ->lockForUpdate()
            ->max('queue_number');

        return (int) $last + 1;
    }
}
```

Ikat di `AppServiceProvider`. **Kenapa seam, bukan method privat:** fase offline akan menukar strateginya — blok nomor per perangkat, atau awalan per perangkat (`A-12` / `B-12`) — dan penukaran itu harus jadi penggantian satu kelas, bukan bedah ulang `checkout()`.

**Batas yang disadari:** `whereEffectiveDate()` memakai `whereRaw` atas `COALESCE(...)`, sehingga `lockForUpdate` di sini tidak bisa bersandar pada indeks dengan rapi. Untuk satu kasir itu tidak terasa. Untuk ledakan self-order QR bersamaan, dua pesanan bisa memperoleh nomor sama. **Inilah alasan kedua nomor antrian tidak boleh jadi identitas** — tabrakan label merepotkan, tabrakan identitas merusak. Skema yang lebih kuat (baris penghitung per tenant per hari) masuk di balik seam yang sama bila terbukti perlu.

### 3b. `TransactionService` — siapa yang masuk papan

```php
$queueMode = $user->tenant->hasFeature('kitchen_queue');

// Mode antrian mati → null, persis perilaku cafe hari ini.
// Mode antrian hidup → open bill DAN POS langsung-bayar sama-sama masuk papan.
$fulfillmentStatus = $queueMode ? Transaction::FULFILLMENT_WAITING : null;

$occurredAt = $transaction->effectiveDate();   // dipanggil setelah create

'queue_number' => $fulfillmentStatus ? $this->allocator->allocate($tenantId, $occurredAt) : null,
'sort_index'   => $fulfillmentStatus ? $occurredAt->getTimestampMs() : null,
```

> **Perubahan perilaku yang disengaja dan wajib diuji.** Versi sekarang berbunyi `$isOpenBill ? WAITING : null`, sehingga open bill memperoleh `waiting` **bahkan saat tidak ada papan yang akan mengerjakannya** — itulah sumber timbunan yang dibersihkan [2c](#2c-migrasi-backfill--membersihkan-timbunan-yang-belum-terlihat). Mengganti syaratnya jadi `$queueMode` menutup sumbernya, bukan cuma menyapu akibatnya. Perubahan ini **tidak terlihat** oleh tenant mode cafe karena tak ada satu pun permukaan yang merender nilai tersebut — satu-satunya pembacanya adalah `ApiOrderController@updateFulfillment`, yang memang jalur self-order. Karena tetap sebuah perubahan perilaku, ia dikunci dengan test tersendiri.
>
> Konsekuensi yang menyenangkan: **`payOpenBill()` tidak perlu disentuh sama sekali.** Saat mode antrian hidup, pelunasan memang tidak boleh mengubah status masak — memasak dan membayar dua hal berbeda.

**`confirmSelfOrderPayment()`** juga mengisi `queue_number` + `sort_index` saat menyetel `waiting`, dengan syarat flag yang sama. Tanpa ini pesanan QR tidak akan pernah punya nomor.

### 3c. `Transaction::void()` — bersihkan papan

```php
$transaction->update([
    'status' => Transaction::STATUS_VOIDED,
    // Tanpa ini, pesanan yang dibatalkan tetap tampil sebagai kartu aktif dan
    // akan dimasak. Query papan JUGA mengecualikan voided — dua lapis, karena
    // satu lapis akan bocor lewat jalur pembatalan yang belum ada hari ini.
    'fulfillment_status' => null,
]);
```

### 3d. `FulfillmentService` (FILE BARU) — satu sumber logika

**File:** `app/Services/FulfillmentService.php`

```php
class FulfillmentService
{
    /** Status yang dianggap "aktif" di papan. */
    private const ACTIVE = [
        Transaction::FULFILLMENT_WAITING,
        Transaction::FULFILLMENT_PREPARING,
        Transaction::FULFILLMENT_READY,
    ];

    /**
     * Majukan satu langkah: waiting → preparing → ready → done.
     *
     * $expectedFrom WAJIB dan bukan basa-basi. Papan di-poll tiap 5–10 detik
     * dan bisa dibuka dua orang sekaligus, jadi kartu basi bukan kemungkinan
     * melainkan keadaan normal. Tanpa pemeriksaan ini, satu tap pada kartu
     * basi melompati satu status diam-diam.
     */
    public function advance(Transaction $transaction, string $expectedFrom): Transaction
    {
        return DB::transaction(function () use ($transaction, $expectedFrom) {
            $transaction->refresh();

            if (! $transaction->hasFulfillmentTracking()) {
                throw new \Exception('Transaksi ini tidak punya fulfillment tracking.');
            }

            if ($transaction->fulfillment_status !== $expectedFrom) {
                throw new \Exception(
                    'Status pesanan sudah berubah (sekarang: '.$transaction->fulfillment_status.'). Papan disegarkan.'
                );
            }

            $next = match ($expectedFrom) {
                Transaction::FULFILLMENT_WAITING => Transaction::FULFILLMENT_PREPARING,
                Transaction::FULFILLMENT_PREPARING => Transaction::FULFILLMENT_READY,
                Transaction::FULFILLMENT_READY => Transaction::FULFILLMENT_DONE,
                default => throw new \Exception('Pesanan sudah selesai.'),
            };

            // Satu tulisan, bukan tiga. Stempel waktu ikut di sini.
            $transaction->update(array_filter([
                'fulfillment_status' => $next,
                'preparing_at' => $next === Transaction::FULFILLMENT_PREPARING && ! $transaction->preparing_at ? now() : null,
                'ready_at' => $next === Transaction::FULFILLMENT_READY && ! $transaction->ready_at ? now() : null,
            ]));

            return $transaction;
        });
    }

    /**
     * Dahulukan — aksi prioritas UTAMA. Lompat ke puncak papan sekali tekan.
     *
     * Menukar tetangga satu per satu berarti 9 tap untuk memindahkan kartu ke-10
     * ke puncak, oleh orang yang sedang memasak. Niat sebenarnya hampir selalu
     * "kerjakan yang ini duluan", bukan "geser satu".
     */
    public function moveToTop(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            $min = $this->activeBoard($transaction->tenant_id)->lockForUpdate()->min('sort_index');

            $transaction->update(['sort_index' => (int) $min - 1]);

            return $transaction;
        });
    }

    public function moveUp(Transaction $transaction): Transaction
    {
        return $this->swapWithNeighbor($transaction, 'up');
    }

    public function moveDown(Transaction $transaction): Transaction
    {
        return $this->swapWithNeighbor($transaction, 'down');
    }

    /**
     * Tukar posisi dengan kartu tetangga terdekat.
     *
     * Urutan papan adalah (sort_index, id) — `id` bukan hiasan. sort_index
     * lahir dari cap waktu milidetik, dan dua self-order bisa jatuh di
     * milidetik yang sama; tanpa pemecah seri, perbandingan strict akan
     * melewati kartu kembar dan tombolnya terasa rusak.
     */
    private function swapWithNeighbor(Transaction $transaction, string $direction): Transaction
    {
        return DB::transaction(function () use ($transaction, $direction) {
            $transaction->refresh();   // jangan percaya sort_index in-memory
            [$si, $id] = [$transaction->sort_index, $transaction->id];

            $neighbor = $this->activeBoard($transaction->tenant_id)
                ->lockForUpdate()
                ->when($direction === 'up',
                    fn ($q) => $q->where(fn ($w) => $w->where('sort_index', '<', $si)
                        ->orWhere(fn ($t) => $t->where('sort_index', $si)->where('id', '<', $id)))
                        ->orderByDesc('sort_index')->orderByDesc('id'),
                    fn ($q) => $q->where(fn ($w) => $w->where('sort_index', '>', $si)
                        ->orWhere(fn ($t) => $t->where('sort_index', $si)->where('id', '>', $id)))
                        ->orderBy('sort_index')->orderBy('id'),
                )
                ->first();

            if (! $neighbor) {
                return $transaction;   // sudah di ujung — no-op, bukan error
            }

            $transaction->update(['sort_index' => $neighbor->sort_index]);
            $neighbor->update(['sort_index' => $si]);

            return $transaction->refresh();
        });
    }

    /**
     * Himpunan kartu yang hidup di papan hari ini.
     *
     * Tiga saringan, masing-masing menutup lubang berbeda:
     * - status voided dikecualikan (lapis kedua setelah void() menolkan fulfillment)
     * - batas hari lewat effectiveDate(), bukan created_at
     * - hanya status aktif
     */
    private function activeBoard(int $tenantId): Builder
    {
        return Transaction::where('tenant_id', $tenantId)
            ->whereIn('fulfillment_status', self::ACTIVE)
            ->where('status', '!=', Transaction::STATUS_VOIDED)
            ->whereEffectiveDate(now());
    }
}
```

> Refactor `ApiOrderController@updateFulfillment` agar memanggil `FulfillmentService::advance()`. Karena `$expectedFrom` kini wajib, endpoint API ikut menerimanya di body — perubahan kontrak yang perlu disampaikan ke konsumen n8n/mobile, dan alasannya sama persis: klien manapun bisa memegang status basi.

**Catatan `ready` dan pengurutan.** `ready` ikut di `ACTIVE` supaya kartunya tetap terlihat sampai diserahkan, tapi **tombol prioritas tidak dirender pada kartu `ready`**: mengurutkan ulang sesuatu yang sudah matang tidak bermakna. Ini keputusan UI, ditegakkan di [4a](#4a-cashierqueuevue-file-baru--papan-operator).

### 3e. `Cashier\QueueController` (FILE BARU)

```php
class QueueController extends Controller
{
    public function __construct(private FulfillmentService $fulfillment) {}

    public function index(): Response
    {
        $queue = Transaction::whereIn('fulfillment_status', [
                Transaction::FULFILLMENT_WAITING,
                Transaction::FULFILLMENT_PREPARING,
                Transaction::FULFILLMENT_READY,
            ])
            ->where('status', '!=', Transaction::STATUS_VOIDED)
            ->whereEffectiveDate(now())
            ->with(['items.modifiers'])
            ->orderBy('sort_index')
            ->orderBy('id')
            ->get();

        return Inertia::render('Cashier/Queue', [
            'queue' => QueueCardResource::collection($queue),
        ]);
    }

    public function advance(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeTenant($transaction);

        $validated = $request->validate([
            'expected_from' => 'required|in:waiting,preparing,ready',
        ]);

        try {
            $this->fulfillment->advance($transaction, $validated['expected_from']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    // moveToTop / moveUp / moveDown — pola sama, tanpa validasi tambahan.

    private function authorizeTenant(Transaction $transaction): void
    {
        if ($transaction->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }
    }
}
```

**`QueueCardResource` (FILE BARU).** Jangan kirim model `Transaction` mentah ke Inertia — ia membawa `total_amount`, `user_id`, dan seluruh kolom sinkronisasi offline ke layar yang tidak membutuhkannya. Papan hanya perlu: `id`, `queue_number`, `code`, `fulfillment_status`, `source`, `order_type`, `customer_name`, `table_number`, `notes`, `is_paid`, `waiting_since`, dan daftar item + modifier + catatan per item. Ini juga sejalan dengan konvensi API Resource yang sudah dipakai di sisi platform.

`is_paid` diturunkan dari `status === completed` — inilah yang menyalakan penanda **BELUM BAYAR**.

---

## 4. Frontend

### 4a. `Cashier/Queue.vue` (FILE BARU) — papan operator

Ikuti pola `Cashier/POS.vue`: `CashierTopbar.vue` di atas (**bukan** `OwnerLayout`), `useFlash` untuk pesan, `router.post` untuk aksi.

**Kartu.** Besar dan touch-friendly — operator sedang memasak. Isinya:
- `queue_number` sebagai elemen paling menonjol
- badge sumber (**Kasir** / **Self-Order**) dan `order_type`
- **badge BELUM BAYAR** yang mencolok bila `!is_paid` — Keputusan 3
- daftar item + `notes` per item + modifier
- timer "menunggu 7m" dihitung dari `waiting_since`

**Aksi status.** Satu tombol besar per kartu, berlabel sesuai langkah berikutnya: **Mulai masak → Siap → Selesai**. Tombol mengirim `expected_from` berisi status yang sedang dirender kartu itu — inilah yang membuat proteksi di [3d](#3d-fulfillmentservice-file-baru--satu-sumber-logika) bekerja.

> **Transisi ke `Selesai` pada kartu yang belum lunas WAJIB lewat `ConfirmDialog`** dengan nominal yang harus ditagih tertulis di dalamnya. Ini syarat penerimaan, bukan penyempurnaan: operator tunggal yang menandai selesai tanpa menagih adalah kebocoran uang yang tidak meninggalkan jejak.

**Prioritas.**
- **Dahulukan** — aksi utama, satu tap, satu `ConfirmDialog` ("Dahulukan pesanan #12?"). Ini yang dipakai sehari-hari.
- **▲ / ▼** — penghalus, **tanpa modal**. Aksinya kecil, sering, dan mudah dibatalkan dengan menekan arah sebaliknya; modal di sini hanya menghukum pemakainya.
- Tidak dirender sama sekali pada kartu `ready`.

**Penyegaran.** Polling Inertia v2: `router.reload({ only: ['queue'] })` tiap 5–10 detik di `onMounted`, dibersihkan di `onUnmounted`. Karena setiap aksi mengirim `expected_from`, kartu basi gagal dengan pesan yang jelas alih-alih merusak status.

### 4b. Nomor antrian harus sampai ke pelanggan

Seluruh guna `queue_number` bertumpu pada nomor itu bisa dipanggil. Tanpa langkah ini papan hanya jadi catatan internal:

- **`ReceiptModal.vue`** menampilkan `queue_number` besar-besar di kepala struk **bila ada** (mode cafe tidak punya nilainya, jadi tidak berubah).
- Jalur cetak termal (`escpos.js`) ikut memuatnya — cukup satu baris berukuran besar, tidak perlu perubahan protokol.
- **Self-order:** `ApiOrderController@store` hari ini hanya mengembalikan `transaction_code`. Nomor antrian baru lahir saat pembayaran dikonfirmasi, jadi ia disertakan pada respons konfirmasi/webhook agar n8n bisa menyampaikannya ke pelanggan.

### 4c. Keadaan offline — dinyatakan, bukan didiamkan

Konsekuensi Keputusan 1 harus terlihat di layar, bukan hanya tertulis di dokumen. Saat `useOnlineStatus` melaporkan perangkat offline:

- Halaman papan menampilkan pita jelas: *"Sedang offline — pesanan baru tidak masuk papan sampai koneksi kembali."*
- Kasir tetap bisa berjualan seperti biasa lewat jalur offline yang sudah ada.

Tanpa pita ini, orang pertama yang mengalaminya akan melaporkannya sebagai bug — dan mereka tidak akan salah menduga.

### 4d. Menu navigasi

- **Sidebar owner** (`OwnerLayout.vue:91`): tambahkan item ke `sidebarGroups`. Pola item saat ini memakai `perm` (modul spatie) atau `ownerOnly`; tambahkan kunci ketiga `feature`, dan perluas `canShowItem()` agar item disembunyikan bila flag tenant mati. Gerbang rute tetap sumber kebenarannya — sidebar hanya cermin, persis seperti komentar yang sudah ada di baris 88–90 dan 132.
- **Permukaan kasir** (`CashierTopbar.vue`): tautan "Antrian" mengikuti pola tautan yang sudah ada di komponen itu, dirender bersyarat flag yang sama.

---

## 5. Routes, Settings & Gerbang Langganan

### 5a. Web routes

Masuk ke grup kasir yang sudah ada di `routes/web.php:191` (`['auth','tenant','role:cashier,owner']`, prefix `cashier`, nama `cashier.`):

```php
Route::middleware('feature:kitchen_queue')->group(function () {
    Route::get('/queue', [QueueController::class, 'index'])->name('queue');
    Route::post('/queue/{transaction}/advance', [QueueController::class, 'advance'])->name('queue.advance');
    Route::post('/queue/{transaction}/move-to-top', [QueueController::class, 'moveToTop'])->name('queue.move-to-top');
    Route::post('/queue/{transaction}/move-up', [QueueController::class, 'moveUp'])->name('queue.move-up');
    Route::post('/queue/{transaction}/move-down', [QueueController::class, 'moveDown'])->name('queue.move-down');
});
```

### 5b. Gerbang langganan — papan tidak boleh membeku di masa tenggang

`EnsureSubscriptionActive` (bagian dari grup `tenant`) menolak **semua** method non-safe saat status `grace`, dan seluruh aksi papan adalah POST. Tanpa penyesuaian, tenant yang langganannya lewat jatuh tempo tidak bisa memajukan pesanan **yang uangnya sudah diterima**.

```php
// app/Http/Middleware/EnsureSubscriptionActive.php
private const ALWAYS_ALLOWED = [
    'logout',
    'billing.*',
    // Menyelesaikan pesanan yang sudah diterima bukan "layanan baru". Papan
    // dapur yang membeku menyandera pelanggan yang sudah membayar — persis
    // yang ditolak docblock kelas ini. Tak satu pun rute di bawah ini
    // menciptakan penjualan baru.
    'cashier.queue.*',
];
```

Perlu diperhatikan: rute `cashier.queue` (halaman papan) bernama tanpa akhiran, sehingga pola `cashier.queue.*` **tidak** mencakupnya — dan itu tidak masalah karena `GET` sudah lolos sebagai method aman.

### 5c. Settings (owner)

`Owner/SettingsController` hari ini mengirim `tenant` berisi profil + konfigurasi AI, dan `update()` memvalidasi `address`, `phone`, `ai_*`. Tambahkan:

```php
// index()
'features' => ['kitchen_queue_enabled' => $tenant->kitchen_queue_enabled],

// update()
'kitchen_queue_enabled' => 'boolean',
```

`Owner/Settings/Index.vue` mendapat section **"Mode Outlet"** dengan `Checkbox.vue` (sudah ada) plus deskripsi singkat: apa yang berubah saat dinyalakan, dan bahwa mode ini tidak aktif saat perangkat offline.

---

## 6. Tests (Pest)

**File:** `tests/Feature/KitchenQueueTest.php`, `tests/Unit/FulfillmentServiceTest.php`

**Gerbang & isolasi**
- Route `queue.*` menolak `403` untuk tenant yang flagnya mati; lolos saat hidup.
- Papan hanya menampilkan pesanan tenant sendiri, dan menggabungkan `source` POS + self_order.

**Masuk papan**
- Checkout saat flag hidup → `waiting`, `queue_number` terisi & bertambah, `sort_index` terisi.
- Checkout saat flag mati → `fulfillment_status` **null**, termasuk **untuk open bill** (mengunci perubahan perilaku [3b](#3b-transactionservice--siapa-yang-masuk-papan)).
- `confirmSelfOrderPayment()` mengisi nomor + `sort_index` saat flag hidup.
- `queue_number` reset harian: pesanan kemarin vs hari ini mulai dari 1 lagi.
- Transaksi offline (`commitOffline`) **tidak** masuk papan — mengunci batas lingkup Keputusan 1 supaya tidak "diperbaiki" tanpa sengaja.

**Transisi**
- `advance()` menaikkan status dengan benar dan menstempel `preparing_at`/`ready_at`.
- `advance()` dengan `expected_from` yang **tidak cocok** ditolak dan status tidak berubah — inti proteksi balapan.
- `advance()` dari `done` ditolak.

**Urutan**
- `moveToTop` menaruh kartu di puncak dalam satu panggilan.
- `moveUp`/`moveDown` menukar posisi; aman di batas (kartu teratas → no-op, bukan error).
- Dua kartu dengan `sort_index` identik tetap bisa saling melewati (mengunci pemecah seri `id`).

**Kebersihan papan**
- `void()` mengeluarkan pesanan dari papan.
- Pesanan hari kemarin tidak muncul di papan hari ini.
- Migrasi backfill menolkan open bill lunas lama, dan **tidak** menyentuh pesanan aktif hari ini.

**Pembayaran**
- Kartu open bill terbaca `is_paid = false`; setelah `payOpenBill()` jadi `true` **tanpa** mengubah status masak.

Jalankan: `php artisan test --compact --filter="KitchenQueue|Fulfillment"`

---

## 7. Checklist

**Prasyarat**
- [ ] Fase capability flags selesai — `Tenant::hasFeature()`, middleware `feature:`, flag terbagi ke Inertia
- [ ] `PHASE-FEATURE-FLAGS` diperbarui: rujukan "Bagian 0" dan blok alias yang salah fakta

**Migrasi**
- [ ] Kolom antrian di `transactions` + `$fillable` + `casts()`
- [ ] Indeks papan `transactions_queue_board_index`
- [ ] Migrasi backfill open bill lama (`down()` no-op berkomentar)

**Backend**
- [ ] `QueueNumberAllocator` + `DailySequenceAllocator` + binding
- [ ] `TransactionService@checkout` memakai `$queueMode` (bukan `$isOpenBill`) + `effectiveDate()`
- [ ] `confirmSelfOrderPayment()` mengisi nomor + `sort_index`
- [ ] `void()` menolkan `fulfillment_status`
- [ ] `FulfillmentService` — `advance($expectedFrom)`, `moveToTop`, `moveUp`, `moveDown`
- [ ] `ApiOrderController@updateFulfillment` memanggil service (kontrak API menerima `expected_from`)
- [ ] `QueueController` + `QueueCardResource`

**Frontend**
- [ ] `Cashier/Queue.vue` — kartu, BELUM BAYAR, Dahulukan, polling
- [ ] `ConfirmDialog` pada Dahulukan & pada Selesai-belum-lunas
- [ ] Pita keadaan offline
- [ ] `queue_number` di `ReceiptModal.vue` + `escpos.js` + respons self-order
- [ ] Menu bersyarat di `OwnerLayout.vue` & `CashierTopbar.vue`

**Routes & Settings**
- [ ] Routes digerbang `feature:kitchen_queue`
- [ ] `cashier.queue.*` masuk `ALWAYS_ALLOWED`
- [ ] Toggle di `SettingsController` + `Owner/Settings/Index.vue`

**Penutup**
- [ ] Tests hijau (`KitchenQueue|Fulfillment`)
- [ ] `vendor/bin/pint --dirty --format agent` bersih
- [ ] Entri `[ADDITION]` di `docs/CHANGELOG.md`, `[BL-019]` dipindahkan ke Riwayat Selesai

### Urutan kerja disarankan
Prasyarat flags → migrasi (2) → backend (3) → **test backend dulu** (6) → frontend (4) → routes & settings (5).
Membuktikan logika sebelum ada UI membuat kegagalan terbaca sebagai kegagalan logika, bukan kegagalan tombol.

---

## 8. Keputusan Terbuka

Tiga pertanyaan besar sudah dikunci di [Konteks](#keputusan-yang-dikunci-pemilik-2026-07-28). Yang tersisa kecil-kecil, dan boleh diputuskan saat pengerjaan:

### A. Pembatalan langsung dari papan
Hari ini satu-satunya cara mengeluarkan pesanan salah-input adalah menekan "Selesai" tiga kali — yang mencatatnya sebagai **terlayani** dan mengotori data. Void sudah ada tapi terbatas: hanya transaksi `completed` dan hanya hari ini (`TransactionService:304`), sehingga open bill yang belum lunas tidak tercakup.
**Rekomendasi:** tambahkan aksi *Batalkan* di papan yang memanggil void untuk transaksi lunas, dan untuk open bill belum lunas cukup menolkan `fulfillment_status` dengan alasan tercatat. Kalau ditunda, tulis batasnya di UI supaya operator tidak mengarang jalan sendiri.

### B. Berapa lama kartu `ready` bertahan
Kartu yang sudah siap tapi tak kunjung diambil akan menumpuk sampai tengah malam. Pilihannya: dibiarkan (paling jujur), atau otomatis `done` setelah sekian jam (rapi, tapi mengaku melayani sesuatu yang belum tentu diserahkan).
**Rekomendasi:** dibiarkan pada versi pertama, dan amati di lapangan. `ready_at` sudah dicatat, jadi datanya tersedia untuk memutuskan nanti.

### C. Nomor antrian di mode cafe
Sekarang nomor hanya lahir saat mode antrian hidup. Cafe yang memakai open bill mungkin juga ingin nomor meja/panggil.
**Rekomendasi:** jangan sekarang. Menyalakan nomor untuk cafe berarti menyentuh alur yang prinsip pertama dokumen ini janjikan takkan berubah.

### D. Real-time
Polling cukup untuk satu papan di satu outlet. Bila papan mulai dibuka di dua perangkat sekaligus dan keterlambatan 5 detik terasa, naikkan ke **Laravel Reverb**. Proteksi `expected_from` membuat kenaikan ini aman dilakukan belakangan — kartu basi sudah ditolak dengan benar sejak versi pertama.
