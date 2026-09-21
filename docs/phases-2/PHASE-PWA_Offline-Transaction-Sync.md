# PHASE PWA — Transaksi Offline + Sinkronisasi

**Status:** Selesai (Fase A–D) — 2026-07-16. Lihat `docs/CHANGELOG.md`.
**Estimasi:** Besar (4 sub-fase). Sub-fase A berdiri sendiri & berdampak cepat.
**Dependency:** PWA shell yang sudah ada (`public/sw.js`, `public/manifest.webmanifest`, registrasi di `resources/js/app.js`), `TransactionService::checkout`, `Cashier/POS.vue`
**Output:** Kasir tetap bisa menjual saat internet putus (tunai), transaksi antre lokal, lalu tersinkron otomatis saat online — dengan idempotensi & penanganan konflik stok
**Dipakai oleh:** Kasir (offline capture) + Owner (review konflik stok)

> Transaksi offline mengubah asumsi inti aplikasi: sekarang harga & stok tidak selalu bisa divalidasi ke server saat transaksi terjadi. Plan ini mendesain **capture-lokal → sync-idempoten → optimistic stock + review** agar penjualan tidak pernah hilang, tapi anomali tetap terlacak.

---

## Keputusan yang sudah dikunci

Dari klarifikasi dengan owner:

1. **Pembayaran offline = TUNAI saja.** Non-tunai (QRIS/transfer/e-wallet) butuh verifikasi gateway → **di-disable** saat offline.
2. **Konflik stok = OPTIMISTIC + tandai koreksi.** Saat sync, transaksi tetap tersimpan walau stok jadi minus; transaksi/variant bermasalah **ditandai** agar owner koreksi. Penjualan yang sudah terjadi fisik **tidak pernah ditolak**.

---

## Konteks & Temuan Kode

- **PWA shell sudah nyata, tapi bukan untuk transaksi.** `public/sw.js` cache-first untuk `/build/assets/`, network-first untuk navigasi dengan fallback `/offline.html`. Komentar di SW eksplisit: *request non-GET (POST/PUT) selalu ke jaringan* → checkout (`POST /cashier/transactions`) **tidak** ditangani offline. Registrasi hanya di production (`resources/js/app.js`).
- **Cart hanya di memory.** `Cashier/POS.vue` menyimpan cart di Vue `ref`; hanya `cartWidth` (preferensi UI) yang di-`localStorage`. Putus koneksi = cart hilang. **Belum ada IndexedDB.**
- **Checkout mengandalkan server untuk semua hal penting:** kode transaksi (`generateTransactionCode`), harga otoritatif (`$variant->price`), pengurangan stok atomik (`lockForUpdate` + `where stock >= qty`). Offline harus menyediakan pengganti untuk ketiganya.
- **`code` unik per `(tenant_id, code)`.** Aman untuk generate saat sync (bukan saat offline).
- **Tidak ada idempotency key.** Dua request identik bisa jadi dua transaksi. Offline-sync **wajib** menyelesaikan ini — dan sekaligus menutup celah double-input dari plan pertama.

### Prinsip WAJIB

- **Idempotensi berbasis `client_uuid`.** Client generate UUID per transaksi (offline maupun online). Server dedup by `client_uuid` → retry/dobel jadi no-op.
- **Sumber kebenaran waktu = `occurred_at` (client).** Transaksi offline dilaporkan pada tanggal terjadinya, bukan tanggal sync. Simpan terpisah dari `created_at`/`synced_at`.
- **Harga offline = snapshot lokal.** Yang dibayar pelanggan offline adalah harga di katalog lokal saat itu; server memakai harga yang dikirim untuk transaksi offline, **tapi mencatat** bila beda dari harga DB terkini (flag review).
- **Optimistic stock untuk sync offline.** Deduct boleh menembus nol; hasil minus → set `sync_status = needs_review`, jangan gagal.
- **Cash-only ditegakkan dua lapis** (UI menyembunyikan non-tunai offline + server menolak payment non-cash pada payload offline).
- **Payload offline tidak pernah dipercaya mentah.** Harga & total datang dari client → server wajib memverifikasi kepemilikan tenant (variant, payment method), tipe `cash`, dan kewajaran `occurred_at`. Lihat [3f. Validasi payload](#3f-validasi-payload-wajib).
- **Zero-refactor alur online.** Checkout online tetap lewat `TransactionService::checkout` apa adanya. Jalur offline adalah path terpisah yang bertemu di service yang sama saat sync.
- **Library IndexedDB = `idb`** (disetujui owner 2026-07-16, lihat [Keputusan Terbuka A](#a-library-indexeddb--disetujui-idb)). Dependency lain tetap butuh persetujuan.

---

## Arsitektur Ringkas

```
[POS.vue] --online--> POST /cashier/transactions --------> TransactionService::checkout()  (seperti sekarang)
    |
    |  offline (navigator.onLine=false / request gagal)
    v
[IndexedDB: outbox] --(event 'online' / interval / SW background-sync)--> POST /cashier/transactions/sync (batch)
                                                                                   |
                                                                                   v
                                                     TransactionService::commitOffline() per item
                                                       - dedup by client_uuid
                                                       - deduct stok optimistik (boleh minus → flag)
                                                       - occurred_at dari payload
```

Katalog (produk/variant/harga/modifier/metode bayar) di-cache ke IndexedDB saat POS dibuka online, supaya layar POS bisa render & menghitung total tanpa jaringan.

---

## Sub-fase (disarankan dikerjakan berurutan)

| Fase | Isi | Nilai | Risiko |
|---|---|---|---|
| **A. Idempotency foundation** | `client_uuid` di checkout online + dedup server | Menutup double-input **sekarang**, fondasi sync | Rendah |
| **B. Katalog offline (read-only)** | Cache katalog ke IndexedDB, POS render offline | POS tak blank saat offline | Sedang |
| **C. Offline capture + sync** | Outbox IndexedDB + endpoint sync + optimistic stock | Transaksi offline (tunai) | Tinggi |
| **D. Review konflik + laporan** | UI koreksi stok owner + `occurred_at` di laporan | Integritas data | Sedang |

> Fase A layak dirilis lebih dulu secara independen — ia juga menyelesaikan pertanyaan "anti double-input" tanpa menunggu seluruh PWA.

---

## Daftar Isi

1. [Fase A — Idempotency Foundation](#1-fase-a--idempotency-foundation)
2. [Fase B — Katalog Offline](#2-fase-b--katalog-offline)
3. [Fase C — Offline Capture + Sync](#3-fase-c--offline-capture--sync)
4. [Fase D — Review Konflik & Laporan](#4-fase-d--review-konflik--laporan)
5. [Tests](#5-tests)
6. [Checklist](#6-checklist)
7. [Keputusan Terbuka](#keputusan-terbuka)

---

## 1. Fase A — Idempotency Foundation

### Migrasi
```php
Schema::table('transactions', function (Blueprint $table) {
    $table->uuid('client_uuid')->nullable()->after('code');
    $table->unique(['tenant_id', 'client_uuid']);
});
```
Tambah `client_uuid` ke `$fillable` `Transaction`.

### Backend
- `POSController@store` + `TransactionService::checkout()` menerima `client_uuid` opsional.
- Sebelum membuat transaksi: `Transaction::where('tenant_id',$t)->where('client_uuid',$uuid)->first()` → kalau ada, **kembalikan yang lama** (idempoten), jangan buat baru.
- Validasi: `client_uuid` → `nullable|uuid`.

### Frontend (`POS.vue`)
- Generate `crypto.randomUUID()` sekali per sesi checkout (saat cart mulai diisi / saat tombol bayar ditekan pertama).
- Kirim di payload checkout. Reset UUID setelah sukses.
- Ini membuat retry jaringan (koneksi flaky) aman: request kedua dengan UUID sama tidak menggandakan transaksi.

> Setelah fase ini, guard `processing` di `POS.vue` (UI) + `client_uuid` (jaringan) = perlindungan double-input dua lapis.

---

## 2. Fase B — Katalog Offline

### Data yang di-cache (IndexedDB store `catalog`)
Saat `POS.vue` dimuat **online**, simpan snapshot: produk + variant (id, nama, harga, stok terakhir diketahui), modifier group + modifier (harga), metode pembayaran aktif (tandai mana yang `cash`). Sertakan `cached_at`.

### Service Worker
- Precache shell POS (`/cashier/pos` document + asset kritikal) agar halaman bisa dibuka saat offline (network-first sudah ada; tambah agar route POS punya fallback shell yang fungsional, bukan cuma `/offline.html`).
- Tetap: **jangan** cache/queue request non-GET di SW layer — antrean transaksi ditangani di layer aplikasi (IndexedDB outbox), bukan SW, supaya kontrol penuh atas idempotensi & konflik.

### Frontend
- Composable `useCatalogCache()` — baca dari server saat online (isi IndexedDB), fallback ke IndexedDB saat offline.
- POS render dari cache; tampilkan `cached_at` ("Katalog per 10:24") supaya kasir sadar data mungkin basi.
- Stok yang ditampilkan offline adalah **indikatif** (last-known) — beri tanda visual bahwa stok tidak real-time.

---

## 3. Fase C — Offline Capture + Sync

### 3a. Migrasi (kolom offline di `transactions`)
```php
Schema::table('transactions', function (Blueprint $table) {
    $table->string('channel')->default('online')->after('source');   // online | offline
    $table->timestamp('occurred_at')->nullable()->after('channel');  // waktu transaksi sebenarnya (client)
    $table->timestamp('synced_at')->nullable()->after('occurred_at');
    $table->string('sync_status')->nullable()->after('synced_at');    // null | needs_review
    $table->string('device_id', 64)->nullable()->after('sync_status');
});
```
Tambah semua ke `$fillable` + cast `occurred_at`/`synced_at` → `datetime`.

### 3b. IndexedDB outbox (frontend)
Store `outbox` berisi antrean transaksi offline:
```
{ client_uuid, occurred_at, device_id, items:[...snapshot harga...], payments:[{cash}], total_amount, status:'queued'|'syncing'|'failed', attempts }
```
- Composable `useOfflineQueue()`: `enqueue()`, `pending()`, `markSynced()`, `markFailed()`.
- `POS.vue`: saat submit, kalau `!navigator.onLine` **atau** POST gagal koneksi → `enqueue()` + tampilkan struk "Tersimpan offline". Cart di-clear seperti sukses normal.
- **Cash-only guard:** saat offline, sembunyikan/disable metode non-tunai; `enqueue()` menolak payment selain cash.

### 3c. Endpoint sync (backend)
```php
// routes/web.php (grup cashier)
Route::post('/transactions/sync', [POSController::class, 'sync'])->name('transactions.sync');
```
`POSController@sync` menerima **batch** `{ transactions: [ ... ] }`. Untuk tiap item panggil service, kumpulkan hasil per `client_uuid` (`synced` / `duplicate` / `error`), kembalikan JSON supaya client bisa menandai outbox.

### 3d. `TransactionService::commitOffline()` (FILE/ METHOD BARU)
Path terpisah dari `checkout()` — memakai harga & waktu dari payload, deduct optimistik.

```php
public function commitOffline(array $data, User $cashier): Transaction
{
    // 1. Idempoten: kalau client_uuid sudah ada → kembalikan yang lama
    $existing = Transaction::where('tenant_id', $cashier->tenant_id)
        ->where('client_uuid', $data['client_uuid'])->first();
    if ($existing) {
        return $existing; // duplicate → no-op
    }

    // 2. Cash-only guard — WAJIB scope ke tenant (jangan PaymentMethod::find() telanjang)
    foreach ($data['payments'] as $p) {
        $method = PaymentMethod::where('tenant_id', $cashier->tenant_id)
            ->find($p['payment_method_id']);
        if (! $method || $method->type !== 'cash') {
            throw new \Exception('Transaksi offline hanya menerima pembayaran tunai.');
        }
    }

    return DB::transaction(function () use ($data, $cashier) {
        $needsReview = false;
        $occurredAt  = Carbon::parse($data['occurred_at']);

        $transaction = Transaction::create([
            'tenant_id'    => $cashier->tenant_id,
            'user_id'      => $cashier->id,
            'code'         => $this->generateTransactionCodeFor($cashier->tenant_id, $occurredAt),
            'client_uuid'  => $data['client_uuid'],
            'status'       => Transaction::STATUS_COMPLETED,
            'channel'      => 'offline',
            'source'       => Transaction::SOURCE_POS,
            'occurred_at'  => $occurredAt,
            'synced_at'    => now(),
            'device_id'    => $data['device_id'] ?? null,
            'total_amount' => 0,
            'change_amount'=> 0,
        ]);

        $total = 0;
        foreach ($data['items'] as $line) {
            // WAJIB: scope variant ke tenant (whereHas product.tenant_id) — jangan find() telanjang
            $variant = ProductVariant::lockForUpdate()->find($line['variant_id']);
            // harga: pakai snapshot offline; flag kalau beda dari DB sekarang
            $unitPrice = $line['unit_price'];
            if ($variant && (float) $variant->price !== (float) $unitPrice) {
                $needsReview = true;
            }
            // OPTIMISTIC deduct — boleh menembus nol
            if ($variant) {
                // JANGAN pakai StockService::deduct() — ia menolak stok tak cukup
                // (WHERE stock >= qty), padahal offline HARUS boleh minus.
                //
                // JANGAN pula cek $variant->stock setelah decrement(): atribut in-memory
                // diturunkan dari nilai yang model tahu sebelumnya, bukan hasil baca ulang
                // DB — cek minus bisa meleset & flag needs_review jadi tidak akurat.
                // Baris sudah di-lock, jadi hitung eksplisit dari nilai ter-lock:
                $newStock = $variant->stock - $line['qty'];
                $variant->update(['stock' => $newStock]);
                if ($newStock < 0) {
                    $needsReview = true;
                }
                StockMovement::create([
                    'tenant_id' => $cashier->tenant_id,
                    'product_variant_id' => $variant->id,
                    'type' => StockMovement::TYPE_SALE,
                    'qty' => -$line['qty'],
                    'notes' => "Penjualan offline (sync) #{$transaction->id}",
                    'reference_id' => $transaction->id,
                ]);
            } else {
                $needsReview = true; // variant hilang/terhapus sejak offline
            }
            // ... buat transaction_item snapshot (variant_name, unit_price, subtotal, notes) + modifiers ...
            $total += /* subtotal */;
        }

        $paid = collect($data['payments'])->sum('amount');
        $transaction->update([
            'total_amount'  => $total,
            'change_amount' => max(0, $paid - $total),
            'sync_status'   => $needsReview ? 'needs_review' : null,
        ]);
        foreach ($data['payments'] as $p) {
            $transaction->payments()->create([
                'payment_method_id' => $p['payment_method_id'],
                'amount' => $p['amount'],
            ]);
        }

        return $transaction->load(['items.modifiers', 'payments']);
    });
}
```

> `generateTransactionCodeFor($tenantId, $occurredAt)` = varian `generateTransactionCode` yang memakai tanggal `occurred_at` (bukan `now()`) supaya `code` konsisten dengan hari transaksi sebenarnya.

### 3e. Pemicu sync (frontend)
- Listener `window.addEventListener('online', flush)` + interval ringan saat online + tombol manual "Sync sekarang".
- (Opsional lanjutan) SW **Background Sync API** (`sync` event) untuk flush walau tab tertutup — flag sebagai peningkatan, bukan MVP.
- Saat flush: POST batch, tandai outbox per hasil (`synced` → hapus, `duplicate` → hapus, `error` → `attempts++`, retry dengan backoff).

### 3f. Validasi payload (WAJIB)

> **Ini acceptance criteria Fase C, bukan opsional.** Berbeda dari checkout online, transaksi offline mengirim **harga & total dari client**. Device yang di-tamper atau kasir nakal bisa mengirim `unit_price: 0`. Ini satu-satunya bagian plan yang risikonya menyentuh integritas keuangan, bukan sekadar UX — jadi harus lahir bersama `commitOffline()`, tidak boleh ditunda.

Endpoint `sync` + `commitOffline()` wajib memenuhi semuanya:

- **Tenant scoping.** `variant` dan `payment_method` **wajib** dipastikan milik `$cashier->tenant_id`. `ProductVariant::find()` / `PaymentMethod::find()` telanjang = kebocoran lintas-tenant (device bisa kirim `variant_id` milik tenant lain).
- **Cash-only di server.** `payment_method->type === 'cash'`, bukan sekadar disembunyikan di UI.
- **`total_amount` tidak pernah dipercaya mentah.** Server **menghitung ulang** total dari item (`qty × unit_price` + modifier) — nilai `total_amount` dari client hanya boleh dipakai untuk *cross-check*; kalau beda → `needs_review`.
- **Kewajaran `occurred_at`.** Tolak yang di masa depan (mis. > 5 menit dari `now()`, toleransi clock skew device) dan yang terlalu lampau (mis. > 30 hari). Clock device bisa salah atau sengaja diputar.
- **Batas `unit_price`.** Harga snapshot offline dipakai, tapi selisih ekstrem dari harga DB (mis. `unit_price` = 0 saat harga DB > 0) → `needs_review`, jangan diam-diam diterima.
- **Kuantitas wajar.** `qty > 0` dan berbatas atas — cegah payload absurd yang membuat stok minus ekstrem.

Semua pelanggaran di atas: yang bersifat **penipuan struktural** (tenant salah, non-cash, `occurred_at` masa depan) → **tolak item** (error per-item, tidak merusak batch lain). Yang bersifat **anomali data wajar** (harga beda, stok minus, variant terhapus) → **terima + `needs_review`**, karena penjualan fisik sudah terjadi.

---

## 4. Fase D — Review Konflik & Laporan

- **Halaman "Koreksi Stok" (owner):** daftar transaksi `sync_status = needs_review` + variant yang jadi minus. Owner lihat dan lakukan `StockService::adjust()` untuk koreksi, lalu tandai selesai (`sync_status = null`). Reuse pola `Owner/StockController`.
- **Badge dashboard:** tambah badge "Perlu Koreksi (sync)" via `BadgeHelperService` (pola sama dengan badge expired) supaya owner tahu ada anomali.
- **Laporan pakai `occurred_at`.** `Owner\ReportController` (daily/transactions) harus mengelompokkan berdasarkan `occurred_at` bila ada (fallback `created_at`) supaya penjualan offline masuk ke hari yang benar. **Tinjau tiap query laporan** yang memakai `created_at`.
- **Tanda channel** di history: badge "Offline" pada transaksi hasil sync, plus info "terjadi {occurred_at}, tersinkron {synced_at}".
- **Laci kas:** transaksi offline tunai harus masuk rekap laci sesi mana? Karena tak ada `cash_drawer_id`, rekap berbasis waktu memakai `occurred_at`. Catat sebagai keterbatasan (lihat Keputusan Terbuka).

---

## 5. Tests (Pest)

**File:** `tests/Feature/OfflineSyncTest.php`

- **Idempotensi (Fase A & C):** POST `client_uuid` sama dua kali → hanya **satu** transaksi; yang kedua mengembalikan yang sama.
- **Cash-only:** payload offline dengan metode non-tunai → ditolak.
- **Optimistic stock:** qty > stok → transaksi **tetap tersimpan**, stok jadi minus, `sync_status = needs_review`.
- **Akurasi flag minus:** stok 3, qty 5 → stok akhir tepat `-2` **dan** `sync_status = needs_review` (mengikat fix cek-stok di §3d — cegah regresi ke `decrement()` + baca atribut in-memory).
- **Harga berubah:** `unit_price` offline ≠ harga DB sekarang → transaksi tersimpan + `needs_review`.
- **`occurred_at` dipertahankan:** transaksi offline kemarin → `code` & pengelompokan laporan pakai tanggal kemarin.
- **Batch sync:** campuran (baru + duplikat + error) → response per `client_uuid` benar; yang valid tersimpan, error tidak merusak yang lain (per-item try/catch, bukan satu transaksi besar yang rollback semua).
- **Variant terhapus sejak offline** → transaksi tetap tersimpan + `needs_review`.
- **Laporan** menghitung transaksi offline pada hari `occurred_at`.

**Validasi payload (§3f) — wajib hijau:**

- **Tenant scoping variant:** payload dengan `variant_id` milik tenant lain → **ditolak**, tidak menyentuh stok tenant manapun.
- **Tenant scoping payment method:** `payment_method_id` milik tenant lain (walau bertipe `cash`) → **ditolak**.
- **Total dihitung ulang:** payload `total_amount` dipalsukan (mis. 1000 padahal item = 50000) → transaksi tersimpan dengan total hasil **hitung server** (50000), bukan angka client.
- **Harga nol:** `unit_price: 0` padahal harga DB > 0 → tersimpan + `needs_review` (tidak diam-diam diterima).
- **`occurred_at` masa depan:** timestamp +1 jam → **ditolak**.
- **`occurred_at` terlalu lampau:** timestamp −60 hari → **ditolak**.
- **Qty tidak wajar:** `qty: 0` atau negatif → **ditolak**.

Jalankan: `php artisan test --compact --filter="OfflineSync"`

---

## 6. Checklist

**Fase A** — ✅ selesai (2026-07-14)
- [x] Migrasi `client_uuid` + unique `(tenant_id, client_uuid)`
- [x] `checkout()` idempoten by `client_uuid` + validasi
- [x] `POS.vue` kirim `crypto.randomUUID()` per checkout
- [x] Test idempotensi hijau

**Fase B** — ✅ selesai (2026-07-16)
- [x] `npm install idb` (disetujui — [Keputusan A](#a-library-indexeddb--disetujui-idb)) → `idb@8.0.3`
- [x] IndexedDB store `catalog` + `useCatalogCache()` (`services/offlineDb.js` mendeklarasikan `catalog` **dan** `outbox` di skema v1 sekaligus — Fase C tak perlu bump versi)
- [x] SW: shell POS fungsional saat offline (`PAGE_CACHE`, `CACHE_VERSION` → v2)
- [x] POS render dari cache + indikator `cached_at`/stok indikatif
- [x] **Tambahan:** `PAGE_CACHE` dibersihkan saat logout (`CLEAR_PRIVATE_CACHES` + `offlineSession.js`) — HTML ter-autentikasi tidak boleh tertinggal di till bersama; outbox sengaja TIDAK ikut dibersihkan

**Fase C** — ✅ selesai (2026-07-16)
- [x] Migrasi kolom offline (`channel`, `occurred_at`, `synced_at`, `sync_status`, `device_id`) + index `(tenant_id, sync_status)` & `(tenant_id, occurred_at)`
  - ✅ `channel` = kolom `string` baru; enum `source` tidak disentuh (migrasi hijau di SQLite)
- [x] IndexedDB `outbox` + `useOfflineQueue()`
- [x] `POS.vue`: fallback enqueue saat offline + cash-only guard (UI + server)
- [x] Endpoint `transactions.sync` (batch) + `commitOffline()` (optimistic + flag)
- [x] **Validasi payload §3f lengkap** — acceptance criteria, terpenuhi & ada testnya
- [x] Cek stok minus pakai `$newStock` dari nilai ter-lock (diikat test "stok 3, qty 5 → tepat -2")
- [x] `generateTransactionCodeFor(occurred_at)`
- [x] Pemicu sync (online event + interval 60s + tombol "Sync sekarang")

**Fase D** — ✅ selesai (2026-07-16)
- [x] Halaman "Koreksi Stok" owner (`Owner/OfflineReview/Index.vue`) + badge dashboard (`needs_review`)
- [x] Laporan pakai `occurred_at` — scope `whereEffectiveDate`/`whereEffectiveBetween`/`whereEffectiveFrom` + `effectiveDateSql()`; diterapkan di `ReportController`, `DashboardController`, `ProfitService`, `AiContextService`
- [x] Badge channel "Offline" + "Perlu koreksi" di history
- [x] Tests hijau — 30 test di `OfflineSyncTest`
- [x] `vendor/bin/pint --dirty --format agent` bersih + entry `docs/CHANGELOG.md`

### Penyimpangan dari plan (disengaja, saat implementasi)

1. **Variant tidak dikenal → ditolak, bukan disimpan+flag.** Draf §3d menyimpan transaksi dengan `product_variant_id = null` bila variant hilang. Ternyata kolom itu `NOT NULL`, dan lebih penting: `ProductVariant` memakai **SoftDeletes**, jadi produk yang "terhapus" barisnya masih ada. Perilaku final mengikuti taksonomi §3f: produk ter-soft-delete milik tenant sendiri → **terima + `needs_review` + tetap tertaut**; variant tenant lain / tidak ada → **tolak** (penipuan struktural).
2. **Validasi batch tidak pakai `Rule::exists`.** `SyncOfflineTransactionsRequest` sengaja hanya memvalidasi *bentuk*. Kalau kepemilikan divalidasi di FormRequest, satu payload beracun akan mem-422-kan seluruh batch dan memacetkan antrean selamanya. Kepemilikan divalidasi per item di service.
3. **Status `syncing` tidak dipersistensi.** Plan §3b menyebut `status: 'queued'|'syncing'|'failed'`. Menandai in-flight di disk berisiko membuat baris terjebak selamanya bila tab mati di tengah flush; dipakai lock in-memory + idempotensi `client_uuid`.
4. **Outbox menyimpan `cashier_id`.** Tidak ada di plan. Server mengatribusikan penjualan ke user yang **login saat sync**, jadi tanpa ini kasir B yang flush antrean kasir A akan mencuri penjualan A ke laporan shift-nya. Flush hanya mengirim baris milik kasir yang sedang login.
5. **Open bill & pembayaran tagihan terbuka diblokir saat offline.** Keduanya memutasi baris yang hidup di server dan bisa disentuh till lain — mengantre lokal berisiko double-settle.

---

## Keputusan Terbuka

| # | Topik | Status |
|---|---|---|
| A | Library IndexedDB | ✅ **Ditutup** — `idb` (2026-07-16) |
| B | Identitas device & laci kas | 🟡 Terbuka — MVP pakai pencocokan waktu |
| C | Background Sync API | 🟡 Terbuka — di luar MVP, peningkatan |
| D | Batas aman optimistic stock | 🟡 Terbuka — di luar scope, pantau frekuensi minus |
| E | Keamanan payload offline | ✅ **Ditutup** — diangkat jadi AC Fase C ([§3f](#3f-validasi-payload-wajib)) |

B/C/D tidak memblokir implementasi — semuanya punya jalan MVP yang sudah disepakati.

### A. Library IndexedDB — DISETUJUI: `idb`

**Status:** ✅ Diputuskan 2026-07-16 (owner). **Fase B/C tidak lagi terblokir.**

**Keputusan:** pakai **`idb`** (~1–2 KB gzip, wrapper Promise tipis di atas IndexedDB native).

| Opsi | Bundle | Verdict |
|---|---|---|
| Raw IndexedDB | 0 KB | Ditolak — API event/callback verbose, canggung di composable async, rawan bug transaksi IDB auto-commit saat ada `await` di tengah. Hemat 1 dependency tapi menambah utang teknis yang tak sepadan. |
| **`idb`** | ~1–2 KB | **Dipilih** — async/await bikin `useOfflineQueue()`/`useCatalogCache()` bersih; tanpa transitive deps; stabil & luas dipakai. |
| Dexie | ~25 KB | Ditolak — query builder berlebihan untuk dua store CRUD sederhana (`outbox`, `catalog`). Akan jadi dependency terberat kedua setelah `chart.js`. |

**Alasan:** kebutuhan kita hanya CRUD sederhana (simpan antrean, ambil yang `queued`, hapus yang tersinkron) — bukan query relasional. Ongkos bundle `idb` dapat diabaikan dan konsisten dengan `package.json` project yang ramping (5 runtime deps).

**Catatan implementasi:** SW project ini ditulis tangan (`public/sw.js`, bukan Workbox), jadi `idb` berdiri sendiri murni di layer aplikasi — konsisten dengan prinsip bahwa antrean transaksi ditangani di IndexedDB outbox, **bukan** di layer SW.

**Install saat Fase B dimulai**, bukan sekarang — hindari dependency menganggur di `package.json`:
```bash
npm install idb
```

### B. Identitas device & laci kas offline
Transaksi offline butuh `device_id` (generate & simpan di `localStorage` per perangkat). Keterkaitan ke sesi laci kas sulit karena tak ada `cash_drawer_id` di `transactions`; MVP memakai pencocokan waktu (`occurred_at`). Penautan eksplisit transaksi↔laci = fitur terpisah.

### C. Background Sync API
Flush saat tab tertutup butuh SW Background Sync (dukungan browser terbatas, terutama iOS Safari). MVP cukup flush saat tab aktif + online. Background sync = peningkatan.

### D. Batas aman optimistic stock
Optimistic + review dipilih (keputusan owner). Kalau ke depan minus sering terjadi, pertimbangkan **kuota stok per device** sebelum offline (pesimistik) — jauh lebih kompleks (perlu reservasi & pembagian kuota antar perangkat), sengaja **di luar scope** sekarang.

### E. Keamanan payload offline — DIANGKAT jadi acceptance criteria

**Status:** ✅ Ditutup 2026-07-16. Bukan lagi keputusan terbuka — sudah jadi requirement wajib Fase C.

Awalnya dicatat sebagai catatan kaki. Karena ini satu-satunya bagian plan yang risikonya menyentuh **integritas keuangan** (harga & total datang dari client), ia dipindahkan ke [3f. Validasi payload (WAJIB)](#3f-validasi-payload-wajib) sebagai acceptance criteria Fase C dengan test yang mengikat. Tidak ada keputusan owner yang tertunda di sini.
