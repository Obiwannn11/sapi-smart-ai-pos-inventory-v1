# PHASE UPSELL — Memutar Arah Sinyal Stok Jadi Saran Jual

**Status:** Selesai (2026-07-27) — Tahap A–E dikerjakan; Tahap F (bundling berdiskon) sengaja ditahan
**Menutup:** `[BL-017] Peringatan Stok Belum Berkembang Jadi Upsell`
**Dependency:** `[BL-018]` **hanya** untuk bundling berdiskon (Tahap F). Tiga jenis saran lain tidak menyentuh harga sama sekali, jadi tidak menunggu apa pun.
**Output:** Mesin rekomendasi ter-scope tenant + permukaan kasir (POS) + permukaan self-order (API) + pencatatan diterima/ditolak sejak hari pertama + laporan konversi untuk owner
**Dipakai oleh:** Kasir (POS), pelanggan (self-order lewat n8n), Owner (laporan)

> Fiturnya bukan "bangun upsell dari nol". Enam sinyal stok sudah dihitung `BadgeHelperService`, mekanisme add-on sudah berdiri penuh lewat `ModifierGroup`/`Modifier`, dan data ko-okurensi sudah lengkap di `transaction_items`. Yang dikerjakan fase ini adalah **memutar arahnya**: dari laporan yang dibaca owner entah kapan, jadi saran yang muncul di layar kasir saat pelanggan masih berdiri di depan meja.

---

## Konteks

Temuan kode yang membentuk desain ini:

- **`BadgeHelperService::generate()` mengembalikan array siap-tampil** (`title`, `message`, `severity`, `items`) — bentuk yang terikat dashboard owner. Ia tidak bisa dinilai per varian dan tidak menerima konteks keranjang. Jadi ia **tidak diubah**; upsell memakai jalur sendiri dan dashboard tetap seperti sekarang.
- **Add-on tidak butuh tabel baru.** `modifiers.extra_price` + pivot `product_modifier_groups` + snapshot `transaction_item_modifiers` sudah lengkap. Yang belum ada hanya yang **menyarankannya**.
- **Ko-okurensi bisa diturunkan tanpa pencatatan baru.** `transaction_items` tahu transaksi dan variannya; `transaction_item_modifiers` tahu modifier per baris.
- **POS hidup dari props, bukan endpoint.** `POSController@index` mengirim katalog sebagai props Inertia, lalu `useCatalogCache` memanennya ke IndexedDB. Konsekuensinya menentukan desain di bawah (§2).
- **`product_variants` punya `price` dan `cost_price` berdampingan** → "naik ukuran" bisa diurutkan dari harga, tanpa kolom urutan baru.

### Keputusan yang dikunci di fase ini

1. **Saran dihitung di server, dipilih di client.** Kandidat (query ko-okurensi, filter stok, filter kedaluwarsa) mahal dan butuh DB → server. Penyaringan terhadap isi keranjang murah dan berubah tiap klik → client. Ini juga satu-satunya bentuk yang **hidup saat offline**, karena indeksnya ikut masuk snapshot katalog.
2. **Tanpa pop-up.** Saran muncul sebagai strip tipis di atas tombol BAYAR. Kasir yang diberi tiga pop-up tiap penjualan akan menutup semuanya tanpa membaca (`[BL-017]` poin 4).
3. **Maksimal 2 saran per transaksi**, dapat dikonfigurasi.
4. **Barang `expired` tidak pernah jadi kandidat, dalam bentuk apa pun.** Ini batas keamanan pangan, ditegakkan di lapisan kandidat — bukan diserahkan pada kedisiplinan kasir.
5. **Pencatatan diterima/ditolak sejak commit pertama.** Tanpa itu pertanyaan "apakah upsell-nya menaikkan penjualan atau hanya memperlambat antrean" tak akan pernah terjawab, dan menambal pencatatan belakangan berarti kehilangan periode awal justru saat datanya paling dibutuhkan.
6. **Tidak ada diskon di fase ini.** Bundling barang tertekan ditawarkan **tanpa potongan harga** — item `near_expiry`/`dead_stock` disodorkan pada harga katalog. Versi berdiskonnya menunggu `[BL-018]`, karena hari ini sistem belum punya tempat sah untuk menaruh harga di bawah katalog.

### Prinsip WAJIB

- **Jangan sentuh `BadgeHelperService`.** Dashboard owner tidak boleh berubah perilakunya.
- **Jangan sentuh `product_variants.price`.** Fase ini nol perubahan harga.
- **Tenant scoping eksplisit.** `ProductVariant` tidak memakai `BelongsToTenant`; setiap query kandidat menyaring lewat `product.tenant_id`, sama seperti `BadgeHelperService`.
- **Nama yang tidak menyerempet `pricing_rules`.** Tabel itu harga langganan SaaS, bukan harga jual. Semua yang baru berawalan `upsell_`.
- **Pint** setelah edit PHP, dan setiap perubahan diuji dengan Pest.

---

## Daftar Isi

1. [Tahap A — Skema & Pencatatan](#tahap-a--skema--pencatatan)
2. [Tahap B — Mesin Rekomendasi](#tahap-b--mesin-rekomendasi)
3. [Tahap C — Permukaan Kasir (POS)](#tahap-c--permukaan-kasir-pos)
4. [Tahap D — Permukaan Self-Order](#tahap-d--permukaan-self-order)
5. [Tahap E — Laporan Konversi](#tahap-e--laporan-konversi)
6. [Tahap F — Bundling Berdiskon (DITAHAN)](#tahap-f--bundling-berdiskon-ditahan)
7. [Urutan Eksekusi](#urutan-eksekusi)
8. [Tests](#tests)
9. [Batas yang Diketahui](#batas-yang-diketahui)

---

## Tahap A — Skema & Pencatatan

Dikerjakan **pertama**, bukan terakhir. Alasannya ada di `[BL-017]` poin 5: begitu fiturnya dipakai tanpa pencatatan, periode awal hilang selamanya.

### Tabel `upsell_events`

Satu baris = satu saran yang **benar-benar dilihat manusia**, beserta nasibnya.

| Kolom | Tipe | Catatan |
|---|---|---|
| `tenant_id` | FK | index bersama `created_at` |
| `transaction_id` | FK nullable | `nullOnDelete` — event tetap berguna untuk statistik meski transaksinya dihapus |
| `type` | string | `attach` / `pressed_stock` / `upsize` |
| `surface` | string | `pos` / `self_order` |
| `status` | string | `accepted` / `ignored` |
| `reason` | string nullable | `cooccurrence` / `catalog` / `near_expiry` / `dead_stock` / `price_step` |
| `trigger_variant_id` | FK nullable | varian di keranjang yang memicu saran |
| `suggested_variant_id` | FK nullable | untuk `pressed_stock` & `upsize` |
| `suggested_modifier_id` | FK nullable | untuk `attach` |
| `label` | string | snapshot teks yang dilihat kasir — bertahan meski produknya dihapus |
| `extra_amount` | decimal(12,2) | tambahan omzet bila diterima, 0 bila ditolak |

**Kenapa `nullOnDelete` dan bukan `cascadeOnDelete`:** pertanyaan yang akan ditanyakan di pitching berikutnya adalah "berapa persen saran yang diterima". Menghapus transaksi tidak boleh diam-diam memperbaiki angka itu.

**Kenapa `label` di-snapshot:** sama alasannya dengan `transaction_items.variant_name`. Laporan tiga bulan lagi harus bisa menyebut apa yang ditawarkan, meski varian atau modifier-nya sudah lama dihapus.

### Kapan barisnya ditulis

Event ikut **payload checkout**, bukan endpoint terpisah. Satu jalur tulis untuk empat sumber (POS online, POS offline, open bill, self-order), dan otomatis selamat melewati mode offline — sesuatu yang endpoint terpisah tidak bisa janjikan.

Konsekuensi yang diterima sadar: saran pada keranjang yang **dibatalkan** tidak pernah tercatat. Yang diukur adalah "dari saran yang muncul pada transaksi yang jadi, berapa yang diambil" — bukan "berapa keranjang yang batal setelah lihat saran". Batas ini ditulis di §9.

---

## Tahap B — Mesin Rekomendasi

`app/Services/Upsell/` — terpisah dari `BadgeHelperService`, dengan tiga strategi di balik satu antarmuka.

```
Upsell/
├── Suggestion.php               DTO readonly — satu saran, siap di-JSON-kan
├── SuggestionStrategy.php       interface
├── UpsellIndexBuilder.php       merakit indeks per tenant
├── UpsellEventRecorder.php      menyimpan nasib saran saat checkout
└── Strategies/
    ├── AttachModifierStrategy.php
    ├── PressedStockStrategy.php
    └── UpsizeVariantStrategy.php
```

### Tiga jenis saran — dan kenapa dibedakan sungguhan

Ketiganya menjawab pertanyaan berbeda, jadi tiap strategi punya kandidat, alasan, dan skornya sendiri.

**1. `attach` — add-on yang paling sering menyertai.**
Sumbernya `transaction_item_modifiers` × `transaction_items` dalam 30 hari, dihitung per varian pemicu, disaring `min_support` (default 2 kejadian). Modifier wajib berasal dari grup yang benar-benar terpasang ke produk itu — menyarankan modifier yang tidak bisa dipilih adalah cacat yang langsung terlihat.
Bila riwayatnya belum ada (tenant baru), jatuh ke modifier termurah dari grup opsional produk tersebut, ditandai `reason: catalog` — bukan mengarang, hanya menawarkan yang tersedia.

**2. `pressed_stock` — barang tertekan.**
Varian ber-`near_expiry` (≤ 7 hari) atau `dead_stock` (nol penjualan 30 hari, stok > 0). Ini satu-satunya jenis yang **tidak butuh pemicu** — ia relevan begitu keranjang tidak kosong, jadi tempatnya di `cart_level`, bukan `by_variant`.
Urgensi masuk skor: makin dekat kedaluwarsa makin tinggi. `dead_stock` selalu di bawah `near_expiry` — barang yang tidak laku merugikan pelan-pelan, barang yang mau kedaluwarsa merugikan hari Kamis.

**3. `upsize` — naik ukuran/varian.**
Varian lain dari **produk yang sama** dengan harga lebih tinggi, diambil yang **selisihnya paling kecil**. Menawarkan lompatan dari Small ke Jumbo hampir selalu ditolak dan membuat kasir berhenti membaca strip-nya.
`max_price_gap_ratio` (default 0.6) memotong lompatan yang terlalu jauh. **Tidak ada kolom urutan varian baru** — `price` sudah mendefinisikan urutan yang dimaksud, dan menambah kolom yang harus diisi manual berarti fitur ini mati diam-diam pada tenant yang tidak mengisinya.

### Penjaga kandidat — satu tempat, bukan tersebar

Setiap strategi mewarisi `sellable()`:

- `stock > 0` — menyarankan barang kosong adalah cacat yang langsung terlihat pelanggan
- `expiry_date` NULL **atau** ≥ hari ini — `expired` tidak boleh masuk dalam bentuk apa pun
- `product.is_active = true`
- `product.tenant_id = $tenant->id` — scoping eksplisit, karena `ProductVariant` tidak ber-`BelongsToTenant`

### Bentuk indeks yang dikirim ke client

```php
[
  'by_variant' => [ '12' => [Suggestion, …] ],   // attach + upsize, dipicu isi keranjang
  'cart_level' => [ Suggestion, … ],             // pressed_stock
  'max_per_transaction' => 2,
  'generated_at' => '2026-07-27T09:00:00Z',
]
```

Skor dihitung di server dan ikut di tiap saran, supaya client hanya perlu menyortir — tidak perlu tahu apa pun tentang kedaluwarsa, ko-okurensi, atau margin.

---

## Tahap C — Permukaan Kasir (POS)

**Kenapa indeks ikut props, bukan endpoint per perubahan keranjang:**
POS harus tetap bekerja offline — itu properti yang sudah dibayar mahal di PHASE PWA dan tidak boleh dirusak fitur baru. Endpoint per-keranjang berarti saran mati begitu sinyal hilang, tepat di warung yang sinyalnya paling buruk. Ikut props berarti indeks ikut ter-snapshot `useCatalogCache` dan hidup di mode offline dengan sendirinya, tanpa kode tambahan.

Harganya: indeks berumur sama dengan katalognya. Untuk saran (bukan harga) ini pertukaran yang benar — saran basi paling buruk hanya menjadi saran yang tidak relevan, dan stok tetap diverifikasi ulang saat masuk keranjang.

- `resources/js/composables/useUpsell.js` — mencocokkan keranjang dengan indeks, membuang yang sudah ada di keranjang, membuang yang stoknya tidak cukup lagi, menyortir per skor, memotong di `max_per_transaction`, dan mengingat mana yang sudah ditolak kasir.
- `resources/js/Components/UpsellStrip.vue` — strip tipis di atas total. Tiap saran: label, alasan singkat, harga tambahan, tombol tambah, tombol tutup.
- Saran yang **ditolak tidak muncul lagi** selama keranjang itu hidup. Saran yang muncul kembali setelah ditutup adalah cara tercepat membuat kasir membenci fitur ini.

---

## Tahap D — Permukaan Self-Order

`[BL-017]` poin 6 benar: ini permukaan yang lebih mudah. Tak ada kasir yang harus mengucapkan tawaran, tak ada antrean yang melambat, dan penerimaan/penolakan terekam dengan sendirinya.

- `GET /api/v1/upsell/suggestions?variant_ids[]=…` — saran untuk keranjang yang sedang disusun pelanggan (dipakai n8n).
- `POST /api/v1/orders` menerima `upsell_events` opsional, dicatat lewat recorder yang sama.

---

## Tahap E — Laporan Konversi

`/owner/reports/upsell` (modul `reports`) — menjawab persis pertanyaan yang akan datang di pitching berikutnya:

- Berapa saran muncul, berapa diterima, berapa persen
- Tambahan omzet dari saran yang diterima
- Rincian per jenis (`attach` / `pressed_stock` / `upsize`) — supaya jenis yang tidak pernah diterima bisa dimatikan lewat config, bukan ditebak
- Rincian per permukaan (kasir vs self-order) — hipotesis `[BL-017]` poin 6 bisa diuji dengan angka

---

## Tahap F — Bundling Berdiskon (DITAHAN)

Ditahan sampai `[BL-018]` memberi tempat sah untuk harga di bawah katalog. Alasannya bukan urutan pengerjaan, melainkan hal yang tidak bisa dilakukan hari ini:

- `TransactionService::processItems()` menimpa harga apa pun dari client dengan `$variant->price`. Bundling berdiskon yang dikirim client akan hilang tanpa jejak.
- Menurunkan `price` varian sebagai "cara mendiskon" membuat potongan tak bisa dibedakan dari perubahan harga permanen.
- Penjualan offline berharga diskon akan menabrak `amountsMatch()` dan membanjiri `needs_review`.

Yang sudah disiapkan fase ini agar Tahap F tinggal menyambung: `upsell_events.extra_amount` sudah memisahkan tambahan omzet dari total transaksi, dan `reason` sudah membedakan `near_expiry` dari `dead_stock` — dua hal yang dibutuhkan begitu ada potongan harga untuk dipertanggungjawabkan.

---

## Urutan Eksekusi

| # | Langkah | Kenapa urutannya begini |
|---|---|---|
| 1 | Migration + model + factory `UpsellEvent` | Pencatatan lebih dulu — periode awal tidak bisa ditambal belakangan |
| 2 | `config/upsell.php` | Ambang & batas jadi knob, bukan konstanta tersebar |
| 3 | DTO + interface + tiga strategi | Inti yang bisa diuji tanpa HTTP |
| 4 | `UpsellIndexBuilder` + `UpsellEventRecorder` | Perakit & penulis |
| 5 | Props POS + validasi request + hook `TransactionService` | Jalur online & offline sekaligus, karena keduanya lewat request yang sama |
| 6 | `useUpsell` + `UpsellStrip` + wiring POS.vue | Permukaan kasir |
| 7 | Snapshot katalog + outbox membawa event | Menjaga janji offline |
| 8 | Endpoint API + `ApiOrderController` | Permukaan kedua |
| 9 | Laporan owner + nav | Menutup lingkaran pengukuran |
| 10 | Pest + Pint + CHANGELOG + BACKLOG | — |

---

## Tests

`tests/Feature/Upsell/`

**`UpsellSuggestionTest.php`** — mesin kandidatnya:
- varian `expired` tidak pernah muncul, meski stoknya banyak
- varian stok 0 tidak pernah muncul
- produk nonaktif tidak pernah muncul
- `attach` mengambil modifier yang paling sering menyertai varian pemicu
- `attach` hanya menawarkan modifier dari grup yang terpasang ke produk itu
- `upsize` mengambil varian **termurah di atas** harga pemicu, bukan yang termahal
- `pressed_stock` mendahulukan `near_expiry` di atas `dead_stock`
- indeks tidak pernah bocor lintas tenant

**`UpsellEventTest.php`** — pencatatannya:
- checkout online menyimpan event diterima **dan** ditolak
- `extra_amount` tercatat pada yang diterima, 0 pada yang ditolak
- sync offline menyimpan event yang ikut di outbox
- event yang merujuk varian tenant lain diabaikan tanpa menggagalkan transaksi
- self-order menyimpan event
- laporan owner menghitung konversi dengan benar

---

## Batas yang Diketahui

Ditulis di sini supaya tidak ditemukan sebagai kejutan:

1. **Keranjang batal tidak terhitung.** Event ikut checkout, jadi penyebutnya adalah "saran pada transaksi yang jadi". Untuk mengukur keranjang batal dibutuhkan endpoint terpisah yang tidak selamat melewati mode offline — sengaja tidak diambil.
2. **Indeks seumur katalog.** Perangkat yang seharian offline memakai saran pagi tadi. Untuk saran ini dapat diterima; untuk **harga** ini tidak — dan itulah salah satu alasan Tahap F ditahan (bersinggungan dengan `[BL-016]`).
3. **Ko-okurensi hanya modifier, belum antar-produk.** "Sering dibeli bersama" antar produk berbeda bisa diturunkan dari `transaction_items` tanpa tabel baru, tapi butuh perhitungan terjadwal supaya tidak dihitung tiap kali POS dibuka. Kandidat lanjutan, bukan kekurangan fase ini.
4. **Saran tidak sadar margin.** `pressed_stock` mendorong barang tertekan tanpa melihat `cost_price`. Selama tidak ada potongan harga, ini tidak berbahaya. Begitu Tahap F masuk, lantai margin `[BL-018]` wajib ikut dipatuhi di sini.
