# SAPI — Backlog & Technical Debt

**Format:** Catatan isu/temuan yang **belum diperbaiki** (bug kecil, hutang teknis, rapikan format, dll) yang perlu di-track supaya tidak hilang, tapi belum layak masuk `CHANGELOG.md` karena perubahan belum diterapkan di kode.

> Begitu sebuah entry benar-benar diperbaiki: tambahkan entry baru di `docs/CHANGELOG.md` (format `[HOTFIX]`/`[ADDITION]` sesuai konvensi di sana), lalu ubah `Status` entry ini jadi `Selesai` dan pindahkan ke bagian "Riwayat Selesai" di bawah.

---

## Cara Menggunakan File Ini

### Kapan Harus Dicatat
- Bug kecil/kosmetik yang ditemukan saat testing (manual, blackbox, code review) tapi tidak diperbaiki di sesi yang sama
- Hutang teknis (technical debt) yang disadari saat implementasi tapi sengaja ditunda
- Ketidaksesuaian dokumentasi vs kode yang belum diluruskan
- Ide perbaikan/refactor yang belum disetujui/diprioritaskan

### Format Entry

```markdown
### [ID] Judul Singkat
- **Ditemukan:** YYYY-MM-DD
- **Sumber:** Dari mana isu ini ditemukan (mis. live blackbox testing AI Analysis)
- **Status:** Open / In Progress / Selesai
- **Prioritas:** Low / Medium / High
- **Area Terdampak:**
  - `path/to/file.ext:line` — deskripsi singkat
- **Deskripsi:** Penjelasan masalah & cara reproduksi
- **Dugaan Penyebab:** (opsional) analisis root cause
- **Usulan Perbaikan:** (opsional) pendekatan fix yang disarankan
```

### Status
| Status | Keterangan |
|---|---|
| `Open` | Ditemukan, belum ada yang mengerjakan |
| `In Progress` | Sedang dikerjakan |
| `Selesai` | Sudah diperbaiki — lihat entry terkait di `CHANGELOG.md` |

---

## Daftar Isu (Open / In Progress)

> **Catatan pemilik 2026-07-31** — `[BL-021]`–`[BL-028]` berasal dari satu daftar catatan yang sama dan sudah diverifikasi terhadap kode. Tiga di antaranya (`[BL-021]`, `[BL-022]`, `[BL-028]`) menyangkut **angka uang yang tercatat salah**, jadi didahulukan; sisanya UX. Urutan pengerjaan yang disarankan: ~~`[BL-028]` Tahap A~~ → ~~`[BL-021]`+`[BL-022]`~~ → ~~`[BL-027]`~~ → ~~`[BL-023]`~~ → `[BL-025]` → `[BL-026]`, dengan `[BL-024]` menunggu perincian dari pemilik dan `[BL-028]` Tahap B ditunda sampai ada kasir kedua. Yang dicoret sudah selesai 2026-07-31.

### [BL-030] Tanggal Jatuh Tempo Langganan Ikut Meluber di Bulan Pendek
- **Ditemukan:** 2026-07-31
- **Sumber:** Sisa penyisiran `[BL-029]` — sengaja TIDAK ikut diperbaiki di sana karena menyangkut semantik penagihan, bukan sekadar salah turunan periode
- **Status:** Open
- **Prioritas:** Medium — bukan salah bulan seperti `[BL-029]`, tapi tanggal tagih yang bergeser maju dan **tidak pernah kembali**
- **Area Terdampak:**
  - `app/Http/Controllers/Platform/InvoiceController.php:211` — `current_period_end = $periodStart->copy()->addMonth()`
  - `database/migrations/2026_07_24_181638_add_subscription_columns_to_tenants_table.php:66` — pola yang sama saat backfill
  - `database/factories/SubscriptionFactory.php:32,34` — `now()->addMonth()`
  - `app/Services/SubscriptionService.php:127` & `:137` — jarak minimum pindah jalur, `subMonths()`/`addMonths()`
- **Deskripsi:** `addMonth()` berarti "tanggal yang sama bulan depan, meluber bila tidak ada". Langganan yang periodenya mulai 31 Januari berakhir **3 Maret**, bukan 28/29 Februari — periode 31 hari yang ditagih sebagai satu bulan. Karena periode berikutnya dihitung dari tanggal akhir yang sudah meleset, pergeserannya **menumpuk**: tiap kali melewati bulan pendek, tanggal tagih maju beberapa hari dan tidak pernah balik.
  Untuk `SubscriptionService`, akibatnya lebih ringan — jarak minimum 3 bulan bisa jadi 3 bulan + beberapa hari — tapi sumbernya persis sama.
- **Kenapa tidak diborong ke `[BL-029]`:** `[BL-029]` memperbaiki turunan periode yang **jelas salah** (bulan berjalan dihitung sebagai bulan lalu) — tidak ada pilihan produk di sana. Yang ini menuntut keputusan: langganan mulai 31 Januari jatuh tempo **28 Februari** (`addMonthNoOverflow`, tanggal tagih tetap di akhir bulan) atau **1 Maret**? Keduanya bisa dibela, dan pilihannya menentukan berapa yang ditagih. Mengubahnya diam-diam sambil membetulkan bug lain akan menggeser tanggal tagih pelanggan tanpa ada yang memutuskan.
- **Usulan Perbaikan:**
  1. Putuskan lebih dulu semantik jatuh temponya, lalu tuliskan di `CHANGELOG.md` sebagai `[DECISION]` — ini aturan bisnis, bukan detail implementasi.
  2. Setelah itu ganti ke `addMonthNoOverflow()` (atau turunkan dari tanggal mulai berlangganan, bukan dari akhir periode sebelumnya, supaya pergeseran tidak menumpuk).
  3. Langganan yang sudah berjalan butuh keputusan tersendiri: dibiarkan pada tanggalnya sekarang, atau diluruskan sekali. Jangan diam-diam.

### [BL-028] Rekonsiliasi Kas Tidak Memperhitungkan Penjualan Tunai, dan Angka Server Tidak Per-Laci
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "review dan mau diperbaiki konsep kas uang dalam menu kasir, bukan dari uang modal, tapi dari uang dari bertipe cash yang diterima harusnya include juga"
- **Status:** In Progress — **Tahap A selesai 2026-07-31** (lihat `[HOTFIX]` di `CHANGELOG.md`); Tahap B ditunda
- **Prioritas:** High — angka yang dipakai kasir untuk mempertanggungjawabkan uang fisik salah, dan salahnya sebesar seluruh penjualan tunai shift itu
- **Area Terdampak:**
  - `resources/js/Pages/Cashier/CashDrawer.vue:19` — `selisih = closingAmount − opening_amount`
  - `resources/js/Pages/Cashier/CashDrawer.vue:235-260` — ringkasan tutup kas hanya menampilkan "Modal awal" dan "Uang fisik aktual"
  - `app/Http/Controllers/Cashier/CashDrawerController.php:82` — `expectedCashFromPayments` di-scope ke `tenant_id`, **bukan** ke laci/kasir
  - `app/Http/Controllers/Cashier/CashDrawerController.php:87` & `:95` — jendela waktu memakai `created_at`, bukan tanggal efektif penjualan
  - `app/Http/Controllers/Cashier/CashDrawerController.php:93` — `totalChangeGiven` punya masalah scope yang sama
  - `app/Http/Controllers/Cashier/CashDrawerController.php:129-145` — `summary()` mengulang pola query yang sama, jadi cacatnya ikut tersalin
  - `app/Models/Transaction.php:151` — `scopeWhereEffectiveBetween()` sudah ada tapi belum dipakai di sini
  - `database/migrations/..._create_transactions_table.php` — tidak ada kolom `cash_drawer_id` (lihat Tahap B — **bukan** prasyarat)
- **Deskripsi — tiga cacat, hanya yang pertama terlihat dari layar:**
  1. **Lapis UI (yang dilaporkan).** Ringkasan sebelum tutup kas menghitung selisih hanya dari modal awal. Kasir yang membuka kas Rp 200.000 lalu menjual Rp 500.000 tunai akan melihat **"Selisih +500.000"** seolah lacinya kelebihan uang setengah juta. Angka penjualan tunai tidak muncul di mana pun sebelum tombol tutup ditekan.
  2. **Scope per-laci.** `close()` sebenarnya sudah memakai rumus yang benar — `modal + tunai masuk − kembalian` — tapi query-nya menyaring `tenant_id` dan rentang waktu saja. **Dua kasir yang shift bersamaan akan sama-sama menghitung seluruh uang tunai toko sebagai milik lacinya.**
  3. **Jendela waktu memakai `created_at`, bukan tanggal efektif.** Penjualan offline yang tersinkron belakangan ber-`created_at` waktu sync, sehingga uang yang masuk laci kemarin dihitung ke laci hari ini. Pelajaran ini **sudah tertulis** di `TransactionService.php:28-33` untuk papan antrian ("`now()` saat sync akan melemparkannya ke dasar papan") tapi belum diterapkan ke rekonsiliasi kas. `[BL-016]` akan memperbesar justru jalur ini.
- **Dugaan Penyebab:** rentang waktu dipakai sebagai pengganti kepemilikan laci. Itu cukup selama hanya ada satu kasir per outlet — asumsi yang tidak pernah ditulis, dan patah begitu staf kedua ditambahkan lewat modul Staff yang sudah ada.
- **Keadaan data per 2026-07-31 (hasil query, dasar pemecahan tahap di bawah):** 207 sesi kas / 2 tenant / 2 kasir — **1 kasir per tenant**, sehingga **0 sesi tumpang-tindih** dan **0 transaksi yang jatuh ke laci kasir lain**. Cacat #2 masih **laten**: belum merusak angka mana pun, tapi aktif begitu kasir kedua ditambahkan. Cacat #3 juga belum menggigit (**0 transaksi** ber-`occurred_at` beda tanggal dari `created_at`). Cacat #1 sebaliknya dialami **setiap kali salah satu dari 207 sesi ditutup**.

#### Tahap A — SELESAI 2026-07-31 (tanpa migrasi)
> **Koreksi terhadap catatan awal entri ini:** `cash_drawer_id` sempat ditulis sebagai "prasyarat". Itu keliru. `transactions.user_id` sudah ada, dan penyaring `transactions.user_id = drawer.user_id` + jendela sesi sudah memisahkan laci dengan benar — termasuk saat kasir kedua masuk. Query verifikasi atas 207 sesi menghasilkan angka identik dengan data sekarang. Jadi seluruh Tahap A berjalan **tanpa migrasi, tanpa backfill, tanpa risiko data**.

1. **Satu sumber perhitungan.** Pindahkan rumus ke service tersendiri (mis. `CashDrawerReconciliation`) yang dipakai **bersama** oleh preview di UI, `close()`, dan `summary()`. Preview yang menghitung sendiri adalah cara termudah membuat dua angka berbeda untuk hal yang sama.
2. **Ganti scope `tenant_id` → `user_id` + jendela sesi.** Memperbaiki cacat #2 selagi masih laten.
3. **Ganti `created_at` → `Transaction::scopeWhereEffectiveBetween()`.** Memperbaiki cacat #3; scope-nya sudah ada dan sudah teruji dipakai di tempat lain, jadi ini penggantian pemanggilan, bukan logika baru.
4. **Tampilkan rinciannya sebelum kasir menekan tutup:** modal awal + tunai diterima − kembalian diberikan = **seharusnya di laci**, baru dibandingkan dengan uang fisik. Non-tunai ditampilkan terpisah dan **ditandai jelas "tidak masuk laci"** — supaya kasir tidak mencari uang QRIS di dalam laci.
5. **Test:** dua kasir dengan sesi tumpang-tindih (menjaga #2 tidak kembali), dan satu penjualan ber-`occurred_at` sebelum sesi dibuka (menjaga #3).

#### Tahap B — kolom `cash_drawer_id`, ditunda
1. Tambah `cash_drawer_id` pada `transactions`, diisi saat checkout dari laci aktif kasir. Membuat kepemilikan laci **eksplisit** alih-alih diturunkan dari `user_id` + waktu.
2. Backfill data lama dari rentang `opened_at`–`closed_at` per user. Transaksi yang jatuh di luar sesi mana pun dibiarkan `null` — jangan dipaksa masuk laci terdekat.
- **Kapan dikerjakan:** saat kasir kedua benar-benar ditambahkan, **atau** saat satu user bisa membuka lebih dari satu sesi dalam sehari (di situ jendela waktu mulai ambigu dan `user_id` tidak lagi cukup). Ditunda karena backfill di atas data yang belum pernah salah adalah risiko tanpa imbalan.
- **Catatan:** `summary()` memakai pola query yang sama dan **harus ikut diperbaiki di Tahap A**. Kalau hanya `close()` yang dibetulkan, rekap sesi akan menampilkan angka berbeda dari angka yang barusan dipakai menutup kas — lebih membingungkan daripada keadaan sekarang.

### [BL-026] Identitas Pesanan (Nama / No. Meja / Kode Panggil) Belum Bisa Diisi dari Kasir
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "buatkan menu untuk menambahkan sistem nama, sistem nomor meja, atau sistem kode (misal mau pakai untuk pemanggilan)"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Models/Transaction.php:59-60` — `queue_number`, `customer_name`, `table_number` **sudah ada** dan fillable
  - `app/Services/TransactionService.php:106-107` & `:191-192` — checkout & self-order sudah menulis keduanya
  - `app/Services/TransactionService.php:85-92` — `queue_number` hanya dialokasikan bila fitur `kitchen_queue` aktif
  - `app/Http/Requests/StoreTransactionRequest.php:66` — hanya `customer_name` yang divalidasi; **`table_number` tidak ada**, jadi mustahil dikirim dari POS
  - `resources/js/Pages/Cashier/POS.vue:1004-1046` — modal nama pelanggan hanya muncul di jalur "Tunda Bayar"
  - `app/Http/Resources/QueueCardResource.php:30-36` — papan antrian sudah menampilkan ketiganya
- **Yang SUDAH ada (jangan dibangun ulang):** kolomnya, alokator nomor harian (`DailySequenceAllocator`), dan tampilannya di papan antrian sudah berdiri. Jalur API self-order & mobile sudah mengirim `customer_name` + `table_number`. **Yang hilang persis satu lapis: cara kasir mengisinya.**
- **Deskripsi:** Di POS, identitas hanya bisa diberikan lewat modal "Tunda Bayar", dan hanya berupa nama bebas. Transaksi bayar-langsung tidak bisa diberi identitas apa pun — padahal justru pesanan inilah yang perlu dipanggil saat siap. `table_number` bahkan tidak lolos validasi request, jadi tidak ada jalan mengirimnya dari kasir sekalipun UI-nya dibuat.
- **Usulan Perbaikan:**
  1. Setting `order_identity_mode` di Owner: `none` / `name` / `table` / `code`. Satu mode aktif per outlet — warung yang memakai ketiganya sekaligus akan mengisi nol dari tiga.
  2. **Satu modal identitas dipakai kedua jalur** (bayar langsung & tunda bayar), bukan hanya open bill. Bentuk input mengikuti mode: teks bebas untuk nama, papan angka untuk meja, dan untuk `code` cukup tampilkan nomor yang dialokasikan sistem.
  3. Tambahkan `table_number` ke `StoreTransactionRequest`.
  4. **Lepaskan `queue_number` dari syarat `kitchen_queue`.** Nomor panggil dan papan dapur adalah dua kebutuhan berbeda — warung yang hanya ingin memanggil pelanggan tidak seharusnya dipaksa menyalakan papan dapur.
  5. Identitas harus ikut tercetak di struk dan tampil di riwayat; identitas yang hanya hidup di layar kasir tidak menolong siapa pun saat pesanan siap.

### [BL-025] Saran Jual Belum Bisa Diwajibkan, dan Tombol Bayar Nonaktif Tanpa Penjelasan
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "pada menu saran, buatkan setup untuk bisa diatur apakah wajib dilakukan penawaran atau bisa di abaikan (di setting pada menu Owner), tidak akan bisa bayar sampai penawaran itu diselesaikan … serta aktifkan component alert pemberitahuan error nya mengapa bayar button disabled"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/composables/useUpsell.js:129` — `dismiss()` bebas tanpa syarat apa pun
  - `resources/js/Components/UpsellStrip.vue:94` — label tombol masih "Tawarkan"
  - `resources/js/Components/UpsellStrip.vue:100` — tombol × bermakna "tutup saran", bukan "pelanggan menolak"
  - `resources/js/Pages/Cashier/POS.vue:952` — BAYAR hanya nonaktif karena keranjang kosong / `processing`
  - `resources/js/Components/PaymentModal.vue:317` — tombol Bayar di modal nonaktif tanpa keterangan penyebab
  - `app/Http/Controllers/Owner/SettingsController.php:41-46` — daftar `features` hanya `kitchen_queue`, `self_order`, `ai`
- **Deskripsi:** Fiturnya **belum ada sama sekali** — ini penambahan, bukan perbaikan. Saat ini saran bisa ditutup begitu saja dan checkout tetap jalan, sehingga owner tidak punya cara memastikan penawaran benar-benar disampaikan. Terpisah dari itu, tombol bayar yang nonaktif tidak pernah menjelaskan sebabnya, baik di POS maupun di modal pembayaran.
- **Usulan Perbaikan:**
  1. Kolom `upsell_mandatory` di `tenants` + toggle di Settings Owner, mengikuti pola `features` yang sudah ada.
  2. `useUpsell` mengekspos `unresolved` — saran yang belum di-*accept* maupun ditolak. BAYAR nonaktif selama `mandatory && unresolved.length > 0`.
  3. **Komponen alert di atas tombol** yang menyebut saran mana yang menahan, bukan sekadar tombol kelabu. Berlaku juga untuk sebab lain (keranjang kosong, sedang memproses) — alasan tombol mati seharusnya selalu terbaca.
  4. Ganti makna dan label kedua tombol: × = **"Ditolak pelanggan"**, tombol utama = **"Ditawarkan"**. Keduanya sah menyelesaikan saran; yang dilarang hanya melewatinya tanpa keputusan.
  5. **Catat `rejected` terpisah dari `ignored`.** Kalau penolakan yang disengaja dicampur dengan saran yang cuma lewat, angka konversi jadi tidak berarti — persis kekhawatiran yang sudah ditulis di docblock `useUpsell`.
- **Catatan:** penegakan wajib ini **hanya di sisi klien** sudah memadai — ini disiplin kerja, bukan batas keamanan. Jangan sampai kewajiban menawarkan berubah jadi alasan server menolak penjualan yang sah (prinsip yang sama sudah dipakai untuk `upsell_events` di `StoreTransactionRequest`).

### [BL-024] "Perbaikan Alur Belanja Produk" — Belum Bisa Diverifikasi, Menunggu Perincian
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "perbaikan alur dari proses belanja produk"
- **Status:** Open (menunggu perincian pemilik)
- **Prioritas:** Low — sampai gejalanya jelas, tidak ada yang bisa dikerjakan
- **Area Terdampak (dugaan):**
  - `resources/js/Pages/Cashier/POS.vue:227-245` — `selectProduct()`: varian tunggal tanpa modifier langsung masuk keranjang, selain itu buka modal
  - `resources/js/Pages/Cashier/POS.vue:261-281` — `addToCart()` menolak **diam-diam** lewat flash bila stok kurang
  - `resources/js/Pages/Cashier/POS.vue:194-210` — pencarian hanya cocok pada nama produk & varian
- **Deskripsi:** Dicatat supaya tidak hilang, tapi **sengaja tidak ditebak isinya**. Penelusuran kode tidak menemukan cacat yang jelas pada alur ini, jadi menuliskan "usulan perbaikan" sekarang berarti mengarang masalah lalu memperbaikinya. Yang dibutuhkan: bagian mana yang terasa salah — jumlah klik untuk satu item, pencarian, pemindaian barcode, ubah qty, atau lainnya.
- **Catatan:** satu hal yang **terlihat** saat verifikasi dan mungkin bagian dari yang dimaksud: penolakan karena stok kurang hanya muncul sebagai flash sesaat, sementara kartu produk tetap terlihat bisa diklik. Kalau ini yang dimaksud, entri ini bisa dipersempit ke sana.

### [BL-018] Diskon Dinamis Barang Mendekati Habis/Kedaluwarsa dengan Penjaga Margin
- **Ditemukan:** 2026-07-25
- **Sumber:** Saran yang diterima setelah sesi pitching — diskon yang "harganya ditentukan dan menyesuaikan sembari tetap untung, melihat harga modal dan harga jual"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `database/migrations/2026_03_06_000011_create_transactions_table.php` & `..._000013_create_transaction_items_table.php` — **tak ada satu pun kolom diskon** di kedua tabel
  - `app/Services/TransactionService.php:353` — checkout selalu memakai `$variant->price`; harga dari klien diabaikan
  - `app/Services/TransactionService.php:596` — sinkronisasi offline menandai `needs_review` bila harga bayar ≠ harga katalog
  - `app/Services/TransactionEditService.php:168` — pengeditan transaksi **menghitung ulang** dari `$variant->price`
  - `app/Http/Controllers/Owner/OfflineReviewController.php:95` — selisih harga ditampilkan ke owner sebagai anomali
  - `app/Services/ProfitService.php` — margin per varian, sumber angka penjaga untung
  - `resources/js/composables/useCatalogCache.js` — katalog offline dipanen dari props Inertia
- **Yang SUDAH ada (jangan dibangun ulang):**
  **Angka untuk menjaga untung sudah lengkap dan tidak perlu diturunkan dari mana pun.** `product_variants` menyimpan `cost_price` dan `price` berdampingan, plus `expiry_date` dan `stock` — jadi lantai harga tiap varian bisa dihitung langsung, tanpa tabel baru. `ProfitService::profitByProduct()` bahkan sudah menghitung margin per varian, dan docblock-nya sudah menyebut "untuk saran diskon".
  Sisi sarannya juga sudah jalan: `AiAnalysis::TYPE_DISCOUNT` sudah bertanya *"item mana yang sebaiknya didiskon dan berapa besarannya"* dengan margin per item sebagai konteks. Yang dihasilkannya **teks nasihat untuk owner**, bukan harga yang benar-benar berlaku di kasir.
- **Deskripsi masalahnya — penghalang utamanya bukan rumus harga:**
  Menghitung "diskon berapa persen supaya tetap untung" justru bagian yang mudah; `cost_price` dan `price` sudah tersedia. **Yang belum ada adalah tempat sah untuk menaruh harga diskonnya.** Seluruh sistem hari ini memperlakukan `product_variants.price` sebagai satu-satunya kebenaran harga:
  - Checkout menimpa harga apa pun dari klien dengan harga katalog (`TransactionService:335`). Diskon yang diketik kasir hilang tanpa jejak.
  - Menurunkan `price` varian sebagai "cara mendiskon" membuat potongan itu **tak bisa dibedakan dari perubahan harga permanen** — laporan tak akan pernah bisa menjawab "berapa yang kita korbankan untuk menghabiskan stok bulan ini".
  - `TransactionEditService:168` menghitung ulang dari `$variant->price`. Transaksi yang terjual diskon lalu diedit karena alasan lain akan **diam-diam naik kembali ke harga katalog** — riwayat berubah sendiri, persis jenis kesalahan yang paling sulit disadari.
  - Penjualan offline berharga diskon akan menabrak `amountsMatch()` (`TransactionService:569`) dan membanjiri `needs_review` — sinyal yang dibangun untuk menangkap anomali sungguhan jadi berisik lalu berhenti dipercaya.
- **Usulan Perbaikan:**
  1. **Diskon harus jadi entitas tersendiri, bukan pengubah `price`.** Minimal: aturan diskon (varian/kriteria, besaran, alasan, masa berlaku) + kolom pada `transaction_items` yang mencatat harga asli, potongan, dan aturan mana yang dipakai. Dengan begitu harga katalog tetap utuh, laporan bisa memisahkan omzet normal dari omzet diskon, dan tiap potongan bisa dipertanggungjawabkan.
  2. **Lantai harga dihitung dari `cost_price`, dan ambang untungnya milik owner — bukan konstanta.** Bentuknya `cost_price × (1 + margin minimum)`; sistem tak pernah **menyarankan** apalagi menerapkan sendiri harga di bawah lantai itu.

     **KEPUTUSAN PEMILIK (2026-07-29) — lantai boleh ditembus manual, tapi wajib berasalan.** Menjual rugi tipis tetap lebih baik daripada barang dibuang total, jadi jalannya dibuka. Yang dikunci adalah **caranya**: penembusan lantai selalu tindakan manusia yang disengaja dan tercatat, tidak pernah hasil rumus. Konsekuensi yang harus ikut dibangun, jangan ditambal belakangan:
     - **Alasan wajib diisi dan disimpan bersama barisnya**, bukan di log terpisah yang bisa dipangkas retensi. Alasan menempel pada baris penjualannya supaya masih terbaca saat laporan dibuka berbulan-bulan kemudian.
     - **Simpan juga siapa yang menyetujui dan lantai yang berlaku saat itu.** Tanpa nilai lantainya ikut dibekukan, "seberapa dalam tembusnya" tak bisa dihitung ulang di kemudian hari — persoalan yang sama dengan `cost_price` historis di Catatan paling bawah.
     - **Rumus dan saran otomatis tetap berhenti di lantai.** Yang berubah hanya bahwa manusia boleh melanjutkan; mesin tidak. Kalau penjaga ini dilonggarkan juga, seluruh gunanya lantai hilang.
     - **Laporan harus bisa memisahkan penjualan di bawah lantai dari diskon biasa.** Ini justru angka yang paling ingin dilihat owner — berapa yang benar-benar dikorbankan, bukan sekadar berapa yang didiskon.
     - **Wewenangnya milik owner saja** (keputusan pemilik 2026-07-29). Kasir tidak bisa menembus lantai sama sekali — bukan "bisa tapi dicatat", melainkan tidak tersedia. Dipilih karena menaikkan izin belakangan jauh lebih mudah daripada menariknya kembali dari kasir yang sudah terbiasa. Ditegakkan lewat permission RBAC yang sudah ada, dan **wajib dijaga di sisi server**, bukan sekadar menyembunyikan tombolnya di UI.
     - Konsekuensi operasional yang harus punya jawaban di UI, karena akan terjadi tiap hari: **kasir yang menemukan barang hampir kedaluwarsa saat owner tidak di tempat.** Diskon sampai lantai tetap jalan seperti biasa; yang di bawah lantai butuh owner. Jangan sampai kasir menemui jalan buntu tanpa penjelasan — tunjukkan batasnya apa adanya.
  3. **Pendalaman diskon mengikuti waktu, bukan sekali tetap.** "Menyesuaikan" pada permintaannya berarti potongan membesar seiring `expiry_date` mendekat, lalu berhenti di lantai margin. Untuk `dead_stock` pemicunya bukan tanggal melainkan lama tak terjual.
  4. **Barang `expired` tidak masuk skema ini sama sekali.** Diskon hanya untuk `near_expiry`. Ini batas keamanan pangan, bukan pilihan bisnis, jadi harus dijaga di lapisan aturan diskonnya — bukan diserahkan pada kedisiplinan kasir.
  5. **Pembulatan harus membulat ke ATAS.** Rp 18.750 jadi Rp 19.000, bukan Rp 18.500. Pembulatan ke bawah bisa menembus lantai margin yang baru saja susah payah dihitung — pembulatan yang salah arah membuat seluruh penjaga untungnya sia-sia.
  6. **Mulai dari "sarankan lalu owner menyetujui", bukan otomatis.** Harga yang turun sendiri secara keliru adalah uang yang keluar dan sukar ditarik kembali; begitu owner percaya sarannya, otomatisasi bisa menyusul sebagai pilihan.
  7. **Tiga jalur yang wajib ikut diperbarui bersama, dan ketiganya mudah terlewat:** pengeditan transaksi (jangan hitung ulang ke harga katalog), sinkronisasi offline (harga diskon yang sah bukan anomali), dan katalog offline di `useCatalogCache` — snapshot dipanen dari props saat perangkat masih online, jadi **diskon bermasa-berlaku bisa kedaluwarsa di dalam snapshot tanpa diketahui perangkatnya**. Perangkat yang seharian offline akan menjual dengan diskon yang sudah berakhir semalam. Bersinggungan langsung dengan `[BL-016]`.
- **Catatan:**
  - **Jangan tertukar dengan `[BL-015]`.** Tabel `pricing_rules` yang sudah ada adalah harga **langganan SaaS** yang dibayar tenant ke pemilik platform — sama sekali bukan harga jual produk ke pelanggan tenant. Pakai penamaan yang tidak menyerempet supaya keduanya tak pernah tercampur.
  - **Sejak `[BL-017]` selesai (2026-07-27), entri ini adalah satu-satunya penghalang bundling berdiskon.** Mesin saran, permukaan kasir, permukaan self-order, dan pencatatan konversinya sudah berdiri; `pressed_stock` menawarkan barang tertekan pada harga katalog karena belum ada tempat sah untuk harga di bawahnya. Begitu entri ini selesai, yang tersisa hanyalah menyambungkan potongan harga ke kandidat yang sudah ada — dan lantai margin di poin 2 **wajib** ikut ditegakkan di sisi saran, bukan hanya di sisi harga.
  - **Satu keterbatasan yang sudah tercatat jadi jauh lebih tajam di sini:** `ProfitService` memakai `cost_price` **saat ini**, bukan biaya historis saat transaksi (lihat docblock-nya). Selama COGS hanya dipakai untuk laporan, itu ketidaktepatan yang bisa ditolerir. Begitu `cost_price` jadi dasar klaim "diskon ini tetap untung", perubahan harga modal di kemudian hari akan **mengubah klaim atas penjualan yang sudah lewat**. Simpan `cost_price` yang berlaku saat itu bersama barisnya.

### [BL-016] Printer Bluetooth & Jaminan Transaksi Offline Menabrak Batas PWA — Butuh Lapisan Native (Android)
- **Ditemukan:** 2026-07-25
- **Sumber:** Laporan pemilik — "printer bluetooth tidak dapat berhubungan dengan aplikasi jika masih PWA, perlu memang layer atau teknis sendiri untuk konek bluetooth, dan juga sekalian dalam offline transaksi"
- **Status:** Open
- **Prioritas:** High untuk bagian printer (cetak struk adalah fungsi inti kasir, dan hambatannya bukan bug yang bisa ditambal), Medium untuk bagian offline (sudah jalan, yang kurang jaminannya)
- **LINGKUP — Android saja** (keputusan pemilik 2026-07-26): lapisan native ini dibangun **hanya untuk Android**. iOS **tidak masuk lingkup pengerjaan**, bukan karena terlewat melainkan karena kebutuhan intinya memang tidak bisa dipenuhi di sana — lihat [Catatan Khusus iOS](#c-catatan-khusus-ios--di-luar-lingkup-dan-alasannya). Setiap poin di bawah dibaca dalam konteks Android kecuali disebut lain.
- **Area Terdampak:**
  - `resources/js/services/printerTransport.js:41` — `BluetoothTransport` berdiri di atas Web Bluetooth, yang hanya bicara BLE/GATT
  - `resources/js/services/printerTransport.js:84` — `"Tidak menemukan karakteristik tulis pada printer ini."`, pesan yang paling mungkin muncul pada printer yang bermasalah
  - `resources/js/composables/useThermalPrinter.js:101` — `// No cached device handle → require pairing again.`
  - `resources/js/Components/ReceiptModal.vue:50` — cadangannya `window.print()`, yaitu dialog cetak peramban
  - `resources/js/composables/useOfflineQueue.js:121` — `flush()` hanya berjalan selama halaman POS terbuka
  - `public/sw.js` — tidak ada pendaftaran Background Sync; SW hanya mengurus cache aset & navigasi
  - `resources/js/services/offlineDb.js` — antrean penjualan hidup di IndexedDB, dan `navigator.storage.persist()` tidak pernah dipanggil di mana pun

#### A. Printer — ini tembok API, bukan bug yang bisa diperbaiki di kode kita
1. **Web Bluetooth hanya bisa BLE (GATT). Bluetooth Classic (SPP/RFCOMM) mustahil dibuka dari peramban mana pun.** Mayoritas printer termal 58mm murah yang beredar di Indonesia justru Classic SPP: ia terpasang normal di setelan Bluetooth Android dan mencetak dari aplikasi native apa pun, tapi di pemilih perangkat peramban ia tidak muncul — atau muncul lalu gagal karena tak punya karakteristik tulis. Persis gejala yang dilaporkan. Tidak ada versi kode yang bisa menembus ini; API-nya memang tidak menyediakan soket RFCOMM.
2. **Bahkan pada printer BLE + Chrome Android, pasangannya tidak bertahan.** Handle perangkat tidak ikut selamat melewati reload (`navigator.bluetooth.getDevices()` masih terbatas/di balik flag), sehingga kasir harus mengulang pemilih perangkat tiap kali aplikasi dimuat ulang — sudah tercatat sebagai keterbatasan sadar di `useThermalPrinter.js:101`.
3. **Cadangan `window.print()` tidak menyelamatkan keadaan.** Ia mencetak ke printer sistem lewat dialog peramban; printer termal Bluetooth 58mm umumnya tidak terdaftar sebagai printer sistem Android, dan hasilnya pun bukan ESC/POS melainkan render halaman.

#### A1. KEBUTUHAN YANG DIKUNCI — lapisan native wajib bicara Bluetooth Classic (SPP), bukan BLE
> Keputusan pemilik (2026-07-26): **sasarannya printer termal umum yang beredar di pasaran**, bukan printer BLE kelas atas — dan pengerjaannya **difokuskan ke Android saja**. Ini menutup kedua keputusan terbuka yang sebelumnya tercatat di entri ini, dan mengubah statusnya dari "pilihan teknis" jadi **syarat penerimaan**.

1. **Kelas perangkat yang harus jalan:** printer termal 58mm/80mm kelas warung — RPP02/RPP02N, Panda PRJ-58, Zjiang ZJ-5802, Goojprt PT-210, Eppos, dan sekerabatnya. Hampir semuanya **Bluetooth Classic SPP saja**; sebagian model baru dual-mode, tapi lapisan native **tidak boleh menggantungkan diri** pada itu. Ukuran keberhasilannya sederhana: **printer yang sudah ter-pairing di setelan Bluetooth Android harus bisa dicetak dari aplikasi, tanpa pemilih perangkat tambahan.**
2. **Yang secara teknis dibutuhkan di sisi native (Android):** soket RFCOMM ke UUID SPP baku `00001101-0000-1000-8000-00805F9B34FB`, dibuka terhadap perangkat yang sudah ter-*bond* di sistem — jadi **daftar printer diambil dari perangkat ter-pairing OS**, bukan dari hasil pemindaian. Izin runtime `BLUETOOTH_CONNECT` wajib untuk Android 12+ (API 31), dengan `BLUETOOTH`/`BLUETOOTH_ADMIN` sebagai jalur lama untuk perangkat di bawahnya.
3. **`escpos.js` tidak perlu disentuh sama sekali.** Byte ESC/POS-nya identik; yang berbeda hanya salurannya. Lapisan native cukup jadi implementasi keempat di belakang antarmuka `Transport` yang sudah dijelaskan di `printerTransport.js:7-14` — `requestDevice()` berubah makna jadi "pilih dari perangkat ter-pairing", `write()` menulis ke soket RFCOMM. Chunking 180-byte khas BLE di `printerTransport.js:90` **tidak berlaku** untuk SPP dan sebaiknya tidak diwariskan; SPP menerima aliran panjang, meski jeda antar-blok tetap perlu supaya buffer printer murah tidak jebol.
4. **Syarat ini hanya bisa dipenuhi di Android** — alasannya di [Catatan Khusus iOS](#c-catatan-khusus-ios--di-luar-lingkup-dan-alasannya), dan itulah dasar keputusan melingkupkan pekerjaan ini ke Android saja.
5. **Dampak ke pilihan opsi:** syarat ini menggugurkan **opsi 1 (TWA)** sepenuhnya dan menyisakan **opsi 2 (Capacitor + plugin SPP)** atau **opsi 3 (native Android)**. Opsi 4 (jembatan cetak lokal) tetap sah, tapi jembatannya sendiri jadi aplikasi Android yang harus bicara SPP — jadi pekerjaannya tidak berkurang, hanya berpindah tempat.

#### B. Offline — sudah bekerja, yang belum ada adalah jaminannya
Transaksi offline **bukan** fitur yang belum jadi: PHASE PWA Fase A–D sudah selesai 2026-07-16, dan idempotensi `client_uuid` serta optimistic stock sudah berdiri. Yang menabrak batas PWA adalah dua janji yang tidak bisa diberikan peramban:
1. **Sinkronisasi hanya berjalan selama halaman POS terbuka.** Tidak ada Background Sync yang didaftarkan di `sw.js`. Kasir yang menutup tab saat tutup toko meninggalkan penjualan menggantung sampai ada orang yang membuka POS lagi.
2. **IndexedDB bisa diusir peramban.** "Hapus data penjelajahan" atau tekanan penyimpanan akan membuang antrean tanpa jejak — dan justru antrean inilah satu-satunya data di aplikasi ini yang **belum punya salinan di server**.
- **Dua perbaikan murah yang tidak menunggu lapisan native** dan sebaiknya dikerjakan lebih dulu, karena keduanya mengecilkan kerugian bila lapisan native ditunda: panggil `navigator.storage.persist()` saat POS dibuka, dan daftarkan Background Sync di `sw.js`. Keduanya berjalan di Chrome Android — jadi sejalan dengan lingkup Android — dan tidak menutup lubangnya, hanya memperkecil jendela kehilangan penjualan.
- **Berbeda dari bagian printer, bagian ini tidak menuntut native untuk berfungsi**; native hanya menaikkan jaminannya (sinkronisasi latar yang sesungguhnya dan penyimpanan yang tidak bisa diusir peramban). Karena itu ia bisa menyusul, sementara printer tidak bisa.

#### C. Catatan Khusus iOS — di luar lingkup, dan alasannya
> Ditulis terpisah supaya keputusan "Android saja" tidak terbaca sebagai kelalaian, dan supaya tidak ada yang membuka ulang perdebatan ini enam bulan lagi tanpa tahu apa yang sudah diperiksa.

1. **Cetak termal Bluetooth ke printer pasaran mustahil di iOS — bukan sulit, tapi mustahil.** Dua pintunya tertutup sekaligus: Web Bluetooth tidak ada di WebKit (dan seluruh peramban iOS wajib memakai WebKit, jadi Chrome di iPhone pun tidak menolong), sementara Bluetooth Classic/SPP dikunci Apple di balik program sertifikasi **MFi** — hanya aksesori bersertifikat yang boleh diajak bicara lewat *External Accessory framework*. Printer RPP02/Panda/Zjiang kelas warung tidak ada yang bersertifikat MFi. Artinya **bahkan aplikasi iOS native pun tidak bisa mencetak ke printer yang sama**, sehingga membangun versi iOS tidak akan mengubah hasilnya sedikit pun.
2. **Konsekuensinya: iOS tidak akan pernah dilayani oleh lapisan yang sama.** Kalau suatu saat ada permintaan nyata dari pengguna iPhone, jalannya bukan "port aplikasi Android-nya", melainkan **mengganti kelas printernya** — printer Wi-Fi/LAN (cetak lewat soket TCP 9100, yang justru tidak butuh native sama sekali dan bisa dari PWA), atau menempatkan satu perangkat Android murah sebagai jembatan cetak bersama di kasir. Perbedaannya ongkos perangkat keras, bukan ongkos rekayasa.
3. **Bagian offline nasibnya lebih ringan tapi tetap lebih lemah di iOS:** Background Sync tidak ada padanannya di WebKit, dan kebijakan penghapusan penyimpanan Safari lebih agresif. Jadi PWA di iPhone tetap **bisa** berjualan offline lewat jalur yang sudah ada hari ini, hanya saja antreannya lebih rawan dan sinkronisasinya sepenuhnya bergantung pada seseorang membuka POS lagi.
4. **Yang tetap berlaku untuk pengguna iOS hari ini:** PWA-nya jalan penuh untuk semua fungsi lain — POS, laporan, kelola stok. Yang absen hanya cetak struk termal. Sampaikan ini apa adanya saat pitching; menjanjikan "nanti ada versi iOS-nya" adalah janji yang tidak bisa ditepati untuk bagian printer.

#### Usulan Perbaikan — pilihan lapisan (semuanya dibaca sebagai Android)
1. **TWA (Trusted Web Activity) — TIDAK menyelesaikan masalah printer.** Perlu ditulis di sini supaya tidak dipilih karena salah paham: isinya tetap dijalankan mesin Chrome, jadi batas BLE-only ikut terbawa utuh. Gunanya hanya distribusi lewat Play Store.
2. **Capacitor — langkah terkecil yang benar-benar menyelesaikan printer. INI YANG DIPILIH.** Seluruh frontend Vue/Inertia yang ada dipertahankan, ditambah jembatan native untuk SPP. Rencana pengerjaannya lengkap di [Bagian D](#d-rencana-implementasi-opsi-2--capacitor), termasuk keputusan "menunjuk URL server, bukan dibundel" beserta alasannya.
3. **Aplikasi Android native yang memakan mobile API yang sudah ada.** Paling mahal, hasilnya paling utuh: SPP betulan, sinkronisasi latar betulan, dan penyimpanan yang tidak bisa diusir peramban. Fondasinya sudah lumayan — API mobile terdokumentasi di `docs/phases-2/API-DOCS-Mobile.md` dan permissions RBAC-nya sudah dikirim di `[BL-003]`.
4. **Jembatan cetak lokal** (aplikasi kecil di perangkat kasir yang di-POST oleh PWA). Mempertahankan PWA sepenuhnya dan masuk akal untuk kasir desktop, tapi tetap menuntut pemasangan sesuatu — jadi ongkos "pasang aplikasi" tidak benar-benar hilang, hanya berpindah.
- **Kode Web Bluetooth/USB yang ada jangan dibuang.** Ia tetap benar untuk printer BLE dan untuk kasir desktop ber-USB. Lapisan native seharusnya jadi jalur tambahan di belakang antarmuka `Transport` yang sudah ada, bukan penggantinya.
- **Tidak ada lagi keputusan terbuka.** Dua pertanyaan yang sempat menggantung sudah dijawab pemilik 2026-07-26: kelas printernya **Classic SPP pasaran** (A1) dan lingkupnya **Android saja** (C). Yang tersisa murni pilihan rekayasa antara **opsi 2 dan opsi 3**, dan itu bisa diputuskan tim sendiri saat pengerjaan dimulai.
- **Ambang keberhasilan untuk menutup entri ini:** satu printer termal pasaran yang sudah ter-pairing di setelan Android berhasil mencetak struk transaksi nyata dari aplikasi, tanpa pemilih perangkat tambahan, dan tetap bisa mencetak setelah aplikasi ditutup lalu dibuka kembali — poin terakhir inilah yang hari ini gagal di PWA.

#### D. Rencana Implementasi Opsi 2 — Capacitor
> Ditulis 2026-07-27 setelah pembahasan pilihan lapisan. **Ini rencana, belum dikerjakan** — status entri tetap `Open`. Begitu dikerjakan, catat hasilnya di `docs/CHANGELOG.md` dan pindahkan entri ini ke Riwayat Selesai.

##### D0. Kenapa Capacitor, dan kenapa bukan dua tetangganya
Capacitor (`capacitorjs.com`, buatan tim Ionic — **bukan** Ionic Framework, komponen UI-nya tidak dipakai sama sekali) adalah WebView Android plus jembatan JS↔Kotlin yang sudah jadi. Perlu ditegaskan supaya tidak salah baca di kemudian hari: **Capacitor tidak menggantikan Kotlin.** Kode SPP-nya tetap ditulis dengan Kotlin; Capacitor hanya menyediakan tempat menaruhnya dan jalur memanggilnya dari Vue.

- **Versus WebView polos + `@JavascriptInterface`** (pola yang paling sering disarankan orang, dan secara arsitektur identik): `@JavascriptInterface` bersifat **sinkron** — thread JS membeku sampai Kotlin selesai. `socket.connect()` ke printer murah makan 2–5 detik, artinya POS ikut membeku selama itu tiap kali menyambung. Menyiasatinya menuntut protokol callback buatan sendiri (Kotlin balas seketika, kerja berat ke coroutine, hasil dikirim balik lewat `evaluateJavascript()`, ditambah registry promise di sisi JS). Capacitor sudah promise-based sejak awal, jadi seluruh lapisan itu tidak perlu ditulis — **dan inilah satu-satunya alasan terkuat memilihnya**, bukan soal lintas-platform.
- **Versus opsi 3 (native penuh):** hasil cetaknya **persis sama** — sama-sama soket RFCOMM dari Kotlin. Bedanya opsi 3 menuntut POS, laporan, dan kelola stok ditulis ulang di Kotlin/Compose. Ongkos itu hanya terbayar kalau yang dikejar adalah Bagian B (sinkronisasi latar sungguhan), yang prioritasnya Medium, bukan printer yang High.
- **Lintas-platform bukan alasannya.** Capacitor mendukung iOS, dan biasanya itu daya jual utamanya. Di sini **tidak berlaku** — lihat Bagian C. Jangan sampai enam bulan lagi ada yang menambahkan target iOS "karena Capacitor kan bisa".

##### D1. Keputusan yang sudah diambil di muka
1. **Cangkang menunjuk `server.url`, aset TIDAK dibundel.** Aplikasi ini Inertia + Blade: dokumen awal dirender server dan tiap navigasi bicara ke server. Membundel aset berarti membangun app-shell dan mengubah cara Inertia di-boot — pekerjaan berminggu-minggu yang **tidak ada hubungannya dengan printer**. Dengan `server.url`, `sw.js` dan IndexedDB yang ada hari ini jalan apa adanya.
   **Konsekuensi yang harus diterima sadar-sadar:** cold start saat perangkat offline bergantung sepenuhnya pada service worker mencegat navigasi. Kalau gagal, kasir dapat halaman error, bukan POS. Ini yang diuji di Tahap 1 dan **boleh membatalkan seluruh pendekatan** kalau tidak bisa diandalkan.
2. **Dependency baru** — `@capacitor/core`, `@capacitor/cli`, `@capacitor/android` di `package.json`, plus folder `android/` (proyek Gradle sungguhan, ikut di-commit, bukan kotak hitam). Ini perubahan dependency, jadi butuh persetujuan pemilik sebelum dieksekusi; persetujuan atas *rencana* ini belum sama dengan persetujuan menambah paketnya.
3. **`escpos.js` tidak disentuh satu baris pun.** Byte ESC/POS-nya identik; yang berganti hanya salurannya.
4. **`BluetoothTransport` dan `UsbTransport` tetap tinggal.** Keduanya masih benar untuk printer BLE dan kasir desktop ber-USB, dan jalur peramban harus tetap hidup untuk pengguna yang tidak memasang APK.

##### D2. Tahapan pengerjaan
Urutannya disusun supaya **yang paling mungkin menggagalkan rencana diuji paling awal**, selagi belum ada Kotlin yang ditulis.

**Tahap 1 — Cangkang kosong, tanpa printer sama sekali.** Setup Capacitor, `capacitor.config.json` menunjuk URL server, build APK debug, pasang di HP. Yang dibuktikan di sini: login + sesi (cookie di WebView), navigasi Inertia, **service worker aktif**, transaksi offline masuk antrean lalu tersinkron saat online kembali, dan tombol back Android tidak membuang kasir keluar aplikasi di tengah transaksi. Perlu diverifikasi juga apakah `SESSION_DOMAIN`/`SANCTUM_STATEFUL_DOMAINS` butuh penyesuaian, dan server **wajib HTTPS** (jangan andalkan `cleartext`, itu untuk pengembangan saja).
*Gerbang keputusan:* kalau POS tidak bisa dibuka saat cold start offline dan tidak bisa diperbaiki dari `sw.js`, hentikan — pertimbangkan ulang opsi bundel atau opsi 3.

**Tahap 2 — Plugin Kotlin SPP + transport keempat.** Baru di sini Kotlin ditulis.
- `android/.../PrinterPlugin.kt` — `@CapacitorPlugin(name = "Printer")` dengan empat `@PluginMethod`: `listPaired()`, `connect(address)`, `write(base64)`, `disconnect()`.
- `resources/js/services/printerTransport.js` — kelas `NativeSppTransport` sebagai implementasi keempat di belakang interface `Transport` yang sudah ada di baris 7–14. `createTransport()` (baris 182) menerima jenis `'native-spp'`.
- `resources/js/composables/useThermalPrinter.js` — `config.transport` bertambah nilainya; deteksi ketersediaan lewat `Capacitor.isNativePlatform()`, dan pada perangkat native jalur ini jadi **pilihan bawaan**.
- `resources/js/Components/PrinterSetupModal.vue` — **butuh pemilih perangkat sendiri.** Tidak ada lagi chooser bawaan peramban; daftar printer datang dari `listPaired()` dan ditampilkan sebagai daftar Vue biasa. Ini perubahan UI terbesar di seluruh rencana.
- Izin di `AndroidManifest.xml`: `BLUETOOTH_CONNECT` (runtime, API 31+) dengan `BLUETOOTH`/`BLUETOOTH_ADMIN` `maxSdkVersion="30"` untuk perangkat lama.

**Tahap 3 — Ketahanan.** Tahap 2 membuatnya mencetak sekali; tahap ini membuatnya mencetak setiap hari. Isinya sepenuhnya daftar jebakan di D3.

**Tahap 4 — Distribusi.** Keystore rilis (**hilang = tidak bisa update selamanya**, simpan di luar repo), `minSdkVersion` (usulan 26 — di bawah itu perangkatnya sudah tak relevan), dan jalur pemasangan: APK langsung dulu untuk uji lapangan, Play Store menyusul.
*Keuntungan yang lahir dari keputusan D1.1 dan layak diingat:* karena isinya dimuat dari server, **perbaikan web tetap sampai ke kasir tanpa update APK**. Rilis toko hanya perlu saat kode Kotlin-nya berubah — dan itu jarang.

##### D3. Jebakan yang sudah diketahui — jangan tunggu ketemu di lapangan
1. **`createRfcommSocketToServiceRecord()` gagal di sebagian printer murah** karena SDP lookup-nya cacat. Siapkan cadangan refleksi `createRfcommSocket(1)` sejak awal. Ini bukan kasus langka.
2. **`adapter.cancelDiscovery()` wajib dipanggil sebelum `connect()`.** Discovery yang masih jalan merusak koneksi RFCOMM, dan gejalanya menyesatkan: kadang berhasil, kadang tidak.
3. **Chunk 180 byte di `printerTransport.js:90` JANGAN diwariskan.** Itu batas MTU khas BLE dan tidak berlaku di SPP. Tapi buffer printer warung tetap kecil — tulis per ~2KB dengan jeda pendek, jangan sekali tembak.
4. **Soket mati diam-diam** saat printer tidur atau aplikasi lama di background. Butuh deteksi + sambung ulang otomatis; kasir tidak boleh disuguhi pesan error untuk keadaan yang bisa dipulihkan sendiri.
5. **Daftar printer diambil dari `bondedDevices`, bukan hasil scan.** Kasir pairing sekali lewat Setelan Bluetooth Android seperti biasa. Ini **fitur, bukan keterbatasan** — justru inilah yang menghapus keharusan mengulang pemilih perangkat tiap reload, keterbatasan yang tercatat di `useThermalPrinter.js:101`.
6. **Jembatan native adalah permukaan serang baru.** Karena WebView memuat URL jarak jauh, kunci plugin hanya untuk origin milik sendiri. Capacitor mengunci ini secara bawaan lewat `server.allowNavigation` — jangan dilonggarkan tanpa alasan.

##### D4. Cerita pengujian — dan lubangnya, supaya tidak mengagetkan
- **Bisa diuji otomatis di suite yang ada:** `NativeSppTransport` (jembatannya di-mock), pemilihan transport di `useThermalPrinter`, dan penjaga bahwa `escpos.js` tetap menghasilkan byte yang sama.
- **Tidak bisa:** `PrinterPlugin.kt`. Suite Pest tidak menjangkau Gradle, dan menambah rangkaian uji instrumentasi Android adalah pekerjaan tersendiri yang tidak sebanding untuk ~100 baris Kotlin. **Bagian ini diverifikasi manual** terhadap ambang keberhasilan entri ini, pada printer fisik sungguhan.
- Catat ini apa adanya sejak sekarang supaya tidak terbaca sebagai kelalaian saat review.

##### D5. Yang TIDAK diselesaikan Capacitor
- **Bagian B.1 (Background Sync) tetap terbuka.** Service worker jalan di WebView Android, tapi Background Sync API tidak tersedia di sana. Sinkronisasi tetap menuntut aplikasi dibuka. Menutupnya betul-betul butuh `WorkManager` di sisi native — pekerjaan tersendiri, dan bisa menyusul di atas cangkang yang sama.
- **Bagian B.2 (IndexedDB diusir) justru membaik, meski tidak sengaja.** Penyimpanan WebView tinggal di direktori data aplikasi, jadi "Hapus data penjelajahan" di Chrome **tidak lagi menyentuh antrean penjualan**. Yang tersisa hanya "Hapus data aplikasi" dan uninstall — dua tindakan yang jauh lebih disengaja. Ini alasan tambahan yang layak dihitung saat menimbang ongkosnya.
- **Dua perbaikan murah di Bagian B tetap dikerjakan lebih dulu** dan tidak menunggu rencana ini: `navigator.storage.persist()` dan pendaftaran Background Sync. Keduanya berguna untuk pengguna PWA yang tidak memasang APK, dan pengguna itu tidak akan pernah hilang.

### [BL-013] Akun Platform Belum Punya 2FA
- **Ditemukan:** 2026-07-22 (dipisahkan dari `[BL-010]` yang sudah ditutup)
- **Sumber:** Poin ketiga `[BL-010]`, sejak awal ditandai "catat sebagai target, jangan dikerjakan sekarang"
- **Status:** Open
- **Prioritas:** Low (target jangka menengah — bukan penghalang operasional)
- **Area Terdampak:**
  - `app/Models/PlatformUser.php` — tanpa `email_verified_at`, tanpa kolom rahasia TOTP
  - `app/Http/Controllers/Platform/AuthController.php` — alur masuk masih satu langkah
- **Deskripsi:**
  Satu akun platform memegang data administratif seluruh klien; satu faktor terasa tipis untuk kewenangan sebesar itu. Sekarang lapisannya sudah lebih baik daripada saat dicatat pertama kali — ada throttle (`[BL-007]`), jejak audit yang bisa dibaca (`[BL-009]`), dan pemulihan kata sandi yang tidak membocorkan keberadaan akun (`[BL-010]`) — tapi tetap: siapa pun yang memegang kata sandinya langsung masuk.
- **Usulan Perbaikan:**
  TOTP (aplikasi authenticator) lebih tepat daripada OTP surel di sini, karena surel justru jalur pemulihan kata sandinya — kalau kotak masuk jebol, dua-duanya jebol sekaligus. Sertakan kode pemulihan sekali-pakai, dan catat pengaktifan/penonaktifannya sebagai kejadian `sensitive`.

---

## Riwayat Selesai

### [BL-023] Tagihan Terbuka Menumpang di Dalam Keranjang, Belum Punya Tempat Sendiri
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "buatkan topbar terpisah untuk pending pembayaran atau open bill, bukan dalam input keranjang"
- **Status:** Selesai (2026-07-31) — lihat `[ADDITION] Tagihan Terbuka Pindah ke Topbar Kasir (BL-023)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/Pages/Cashier/POS.vue:862-905` — panel "Tagihan Terbuka" berada **di dalam area scroll item keranjang**
  - `app/Http/Controllers/Cashier/POSController.php:60-63` — `openBills` dikirim sebagai props POS saja
  - `resources/js/Components/CashierTopbar.vue:45-57` — `navItems` (tempat tombolnya seharusnya berada)
- **Deskripsi:** Daftar tagihan terbuka ikut tergeser saat keranjang panjang, dan bercampur dengan barang yang sedang diinput — dua hal yang tidak berhubungan berbagi satu ruang gulir. Akibat praktisnya: tagihan yang menunggu dibayar bisa hilang dari pandangan justru saat kasir paling sibuk. Selain itu daftarnya **hanya ada di halaman POS**; kasir yang sedang membuka Riwayat atau Kas tidak melihat ada tagihan menggantung.
- **Usulan Perbaikan:**
  1. Jadikan tombol + badge jumlah di `CashierTopbar` (bersebelahan dengan "Antrian"/"Riwayat"), isinya dibuka sebagai panel/drawer tersendiri.
  2. Pindahkan `openBills` dari props POS ke shared data (`HandleInertiaRequests`) supaya topbar bisa membacanya di **semua** halaman kasir. Kirim ringkasannya saja (kode, nama, total, jumlah) — daftar item lengkap cukup dimuat saat panel dibuka.
  3. Hapus panel lama dari badan keranjang di perubahan yang sama; dua tempat untuk hal yang sama akan berbeda isinya cepat atau lambat.

### [BL-029] Satu Test Subsidi Selalu Gagal di Tanggal 29–31 (Carbon Month Overflow)
- **Ditemukan:** 2026-07-31
- **Sumber:** Menjalankan suite penuh saat mengerjakan `[BL-028]`; diverifikasi pre-existing dengan men-`stash` seluruh perubahan sesi itu
- **Status:** Selesai (2026-07-31) — lihat `[HOTFIX] Turunan Periode Bulanan Meluber di Bulan Pendek (BL-029)` di `docs/CHANGELOG.md`. Penyisiran yang disebut di Catatan **sudah dilakukan** dan menemukan tiga bug produksi (job penghitung omzet, prune retensi, laporan command); sisanya yang menyangkut jatuh tempo langganan dipisah jadi `[BL-030]`.
- **Prioritas:** Low untuk dampak produksi (**nol** — kode aplikasi tidak terlibat), Medium untuk kepercayaan pada suite: ia merah di tiap tanggal 31 tanpa ada yang rusak
- **Area Terdampak:**
  - `tests/Feature/Subscription/SubsidyTrackTest.php:131-135` — `foreach ([1, 2, 3] as $bulanLalu) ... now()->subMonths($bulanLalu)->format('Y-m')`
  - `database/factories/TenantMonthlyMetricFactory.php:23` — `now()->subMonth()` bawaannya punya jebakan yang sama
- **Deskripsi:** `mencabut consent menghapus ringkasan omzet seketika` gagal dengan `UNIQUE constraint failed: tenant_monthly_metrics.tenant_id, tenant_monthly_metrics.period`. Penyebabnya bukan aplikasi melainkan `Carbon::subMonths()` yang **meluber** saat tanggal asalnya tidak ada di bulan tujuan. Diverifikasi pada 2026-07-31:

  | offset | tanggal hasil | period |
  |---|---|---|
  | `subMonths(1)` | 2026-07-01 | `2026-07` |
  | `subMonths(2)` | 2026-05-31 | `2026-05` |
  | `subMonths(3)` | 2026-05-01 | `2026-05` |

  April tidak punya tanggal 31, jadi `subMonths(3)` meluber ke 1 Mei — bertabrakan dengan `subMonths(2)`. Test bermaksud membuat tiga periode berbeda, tapi membuat dua baris untuk `2026-05`.
- **Dugaan Penyebab:** `subMonths()` dipakai seolah artinya "bulan ke-N sebelum ini", padahal artinya "tanggal yang sama N bulan lalu, meluber bila tak ada". Perbedaannya tak pernah terlihat 28 hari sebulan.
- **Usulan Perbaikan:** pakai `now()->startOfMonth()->subMonths($n)` (atau `subMonthsNoOverflow()`) di test **dan** di factory. `startOfMonth()` lebih disukai karena menyatakan maksudnya — yang dibandingkan memang bulannya, bukan tanggalnya.
- **Catatan:**
  - **Kode produksi tidak terkena.** `ComputeTenantMonthlyRevenue` menerima satu `$period` eksplisit dan tidak pernah menurunkan beberapa periode dari satu `now()`. Cacatnya murni di data uji.
  - Layak disisir sekali: pemakaian `subMonths`/`subMonth`/`addMonths` lain yang menurunkan **beberapa** periode dari satu titik waktu punya jebakan yang sama.

### [BL-027] Riwayat Kasir Menampilkan Seluruh Riwayat, Bukan Hari & Sesi Berjalan
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "pada riwayat kasir, munculkan data hari ini saja dan di session dia saja"
- **Status:** Selesai (2026-07-31) — lihat `[HOTFIX] Riwayat Kasir Dibatasi ke Sesi Kas Berjalan (BL-027)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Http/Controllers/Cashier/POSController.php:195` — sudah menyaring `user_id = Auth::id()`
  - `app/Http/Controllers/Cashier/POSController.php:205` — filter tanggal **opsional**, tanpa nilai bawaan
  - `app/Http/Controllers/Cashier/POSController.php:216` — `$openDrawer` sudah diambil di method yang sama, tapi hanya dipakai menentukan hak edit
  - `resources/js/Pages/Cashier/TransactionHistory.vue:26` — `dateFilter` bawaan `''`
- **Deskripsi:** Bagian "sesi dia saja" **sudah setengah terpenuhi** — query sudah per-`user_id`, jadi kasir tidak melihat transaksi rekannya. Yang belum: tanpa filter tanggal, halaman ini membuka **seluruh riwayat sejak akun dibuat**, dan tidak ada batas sesi laci sama sekali. Selain bising, ini juga memperlebar permukaan data yang terlihat dari mesin kasir yang dipakai bergantian.
- **Usulan Perbaikan:**
  1. Bawaannya dibatasi ke **sesi laci terbuka milik user** (`created_at >= $openDrawer->opened_at`). Objeknya sudah tersedia di method yang sama — tinggal dipakai sebagai batas query, bukan hanya penentu hak edit.
  2. Bila tidak ada sesi terbuka (owner, atau kasir yang lacinya sudah ditutup), jatuh ke `whereDate(today())`.
  3. Filter tanggal manual tetap ada, tapi **hanya untuk owner**. Kasir yang bisa menyetel tanggal sendiri membuat pembatasan di poin 1 jadi sekadar hiasan.
  4. Beri label di UI bahwa yang tampil adalah sesi berjalan — daftar yang diam-diam terpotong lebih buruk daripada daftar panjang.

### [BL-022] Checkout Memvalidasi Cukup-Bayar Memakai Harga Kiriman Klien, Bukan Harga DB
- **Ditemukan:** 2026-07-31
- **Sumber:** Temuan tambahan saat memverifikasi catatan pemilik tentang split bill
- **Status:** Selesai (2026-07-31) — lihat `[HOTFIX] Split Bill` di `docs/CHANGELOG.md`
- **Prioritas:** High — jalurnya sempit, tapi akibatnya transaksi kurang bayar tercatat `completed`
- **Area Terdampak:**
  - `app/Http/Requests/StoreTransactionRequest.php:118-131` — `$totalBelanja` dijumlahkan dari `items.*.unit_price` **kiriman klien**
  - `app/Services/TransactionService.php:119-123` — total sebenarnya dihitung ulang dari harga DB
  - `app/Services/TransactionService.php:141-142` — `changeAmount` memakai total DB, tapi **tidak ada penjagaan kurang bayar**
  - `app/Services/TransactionService.php:319` — `payOpenBill()` **sudah** punya penjagaan itu
- **Deskripsi:** Validasi "total bayar ≥ total belanja" berjalan di `FormRequest`, memakai harga yang dikirim klien. `TransactionService::checkout()` kemudian menghitung ulang total dari harga DB — dan bila hasilnya lebih besar dari yang dibayar, transaksi **tetap diselesaikan** sebagai `completed` dengan `change_amount = 0`. Tidak ada satu pun exception yang dilempar.
  Jalur nyatanya bukan serangan, melainkan katalog basi: perangkat yang memakai snapshot offline (`useCatalogCache`) bisa mengirim harga lama setelah owner menaikkan harga. Kasir melihat "lunas", server mencatat kurang bayar.
- **Dugaan Penyebab:** penjagaan diletakkan di lapisan validasi request, di mana harga DB belum tersedia. `payOpenBill()` menjaganya di service — **pola yang benar sudah ada di file yang sama**, hanya belum diterapkan ke `checkout()`.
- **Usulan Perbaikan:** pindahkan penjagaan cukup-bayar ke `checkout()`, tepat setelah `$totalAmount` diketahui dari DB (setelah baris 119) dan sebelum pembayaran disimpan — meniru `payOpenBill():319`. Validasi di `FormRequest` boleh tetap ada sebagai penyaring awal yang murah, tapi bukan lagi satu-satunya. Sertakan selisihnya di pesan error supaya kasir tahu harganya berubah, bukan sekadar "gagal".
- **Catatan:** bersinggungan dengan `[BL-016]` (jaminan offline) dan `[BL-018]` (harga diskon yang sah berbeda dari harga katalog). Perbaikan di sini **jangan** dibuat sebagai "harga klien harus persis sama dengan DB" — itu akan menabrak diskon begitu `[BL-018]` dikerjakan. Yang dijaga cukup: **yang dibayar tidak boleh kurang dari yang ditagih.**

### [BL-021] Split Bill: Nominal Non-Tunai Jadi Basi & Tombol Uang Cepat Mati Diam-Diam
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "fitur split bill akan refresh state nominal setiap inputan data baru", "split bill membuat fitur menu bayaran cash dll itu otomatis harus di input manual", "perbaikan flow saat melakukan split bill"
- **Status:** Selesai (2026-07-31) — lihat `[HOTFIX] Split Bill` di `docs/CHANGELOG.md`
- **Prioritas:** High — nominal per metode pembayaran tercatat salah, dan salahnya menjalar ke rekap kas & laporan
- **Area Terdampak:**
  - `resources/js/Components/PaymentModal.vue:55-66` — `onMethodChange()` mengisi nominal non-tunai **sekali saja**
  - `resources/js/Components/PaymentModal.vue:124-129` — `payExact()` dijaga `payments.length === 1`
  - `resources/js/Components/PaymentModal.vue:229` — tombol denominasi hanya dirender untuk `idx === 0`
  - `resources/js/Components/PaymentModal.vue:132-139` — `quickCash()` selalu menyasar `payments[0]`
  - `resources/js/Components/PaymentModal.vue:96-98` — `change` dihitung dari seluruh pembayaran, tanpa memisahkan porsi tunai
  - `resources/js/Pages/Cashier/POS.vue:973-988` — komponen yang sama dipakai POS **dan** pembayaran open bill
  - `tests/Feature/Cashier/` — **tidak ada satu pun test untuk jalur split**
- **Deskripsi — tiga cacat yang saling memperburuk:**
  1. **Nominal non-tunai tidak pernah dihitung ulang.** `onMethodChange()` mengisi `total − Σ baris lain` pada saat metode dipilih, lalu angka itu dibekukan. Reproduksi: total 100.000 → baris 1 Cash diisi 50.000 → baris 2 QRIS terisi otomatis 50.000 → kasir mengoreksi baris 1 jadi 60.000 → **QRIS tetap 50.000**. Total bayar terbaca 110.000 dan modal menawarkan "kembalian 10.000" — padahal QRIS tidak mengenal kembalian. Yang tersimpan di `transaction_payments` salah per metode, sehingga rekap per metode di `CashDrawerController::summary()` dan expected kas ikut salah. Inilah yang dilaporkan sebagai "state nominal ter-refresh".
  2. **Tombol uang cepat mati diam-diam.** Tombol "Uang Pas / +20rb / +50rb / +100rb" tetap **terlihat** setelah baris kedua ditambahkan (dirender untuk `idx === 0`), tetapi `payExact()` menolak bekerja karena `payments.length !== 1`. Kasir menekan tombol yang tidak melakukan apa-apa, tanpa pesan apa pun. Baris tunai kedua tidak punya tombol cepat sama sekali — inilah "cash jadi harus diinput manual".
  3. **Kembalian dihitung dari seluruh pembayaran.** `change = Σ semua − total`, sehingga kelebihan yang berasal dari baris non-tunai ikut ditawarkan sebagai uang kembali dari laci.
- **Dugaan Penyebab:** modal ini jelas dirancang untuk kasus satu baris pembayaran, lalu split ditambahkan belakangan sebagai tombol "+ Split Pembayaran" tanpa meninjau ulang asumsi "baris pertama = satu-satunya baris". Tiga penjaga yang tersisa (`idx === 0`, `length === 1`, pengisian satu arah) adalah sisa asumsi itu.
- **Usulan Perbaikan:**
  1. **Nominal non-tunai jadi turunan, bukan nilai yang disimpan.** Hitung sebagai `computed`: `total − Σ baris lain`, dievaluasi ulang tiap perubahan. Bila ada lebih dari satu baris non-tunai, kunci semuanya kecuali satu baris penyeimbang — dua baris yang sama-sama "otomatis" tidak punya jawaban tunggal.
  2. **Tombol cepat mengikuti baris yang sedang aktif,** bukan `idx === 0`. Hapus penjagaan `length === 1` di `payExact()`, dan tambahkan tombol **"Sisa"** per baris tunai yang mengisi tepat kekurangannya — ini yang menghapus keharusan mengetik manual.
  3. **Kembalian hanya dari porsi tunai:** `change = max(0, Σ tunai − (total − Σ non-tunai))`.
  4. **Tulis test-nya lebih dulu.** Jalur ini menyentuh uang dan sama sekali belum punya test: tunai+QRIS, koreksi baris setelah baris lain terisi, kurang bayar, dan pembayaran open bill (komponen yang sama, jalur server berbeda).
- **Catatan:** `PaymentModal` dipakai **dua kali** di `POS.vue` — checkout biasa dan pelunasan open bill. Perbaikan apa pun otomatis berlaku untuk keduanya, jadi keduanya wajib ikut diuji; jangan sampai open bill baru ketahuan rusak setelah dipakai.

### [BL-019] Rencana Antrian Dapur (PHASE-QUEUE) Perlu Ditinjau Ulang & Ditulis Ulang Sebelum Diimplementasi
- **Ditemukan:** 2026-07-28
- **Sumber:** Peninjauan teknis `docs/phases-2/PHASE-QUEUE_Kitchen-Order-Queue.md` terhadap keadaan kode, dilakukan **sebelum** implementasi dimulai
- **Status:** Selesai (2026-07-29) — lihat `[ADDITION] Papan Antrian Dapur (BL-019)` dan `[ADDITION] Fondasi Capability Flags & Gerbang Tersinkron` di `docs/CHANGELOG.md`
- **Prioritas:** High untuk peninjauannya
- **Jalannya:** Tiga langkah persis seperti yang disyaratkan entri ini — tinjau tujuan → tulis ulang dokumen → baru kerjakan. Penulisan ulang kedua dokumen selesai 2026-07-28; implementasinya 2026-07-29.
- **Perbaikan:**
  Ternyata **dua fase**, bukan satu. Baris pertama checklist `PHASE-QUEUE` adalah fondasi capability flags, dan fondasi itu belum ada sama sekali di kode — tidak ada `hasFeature()`, tidak ada kolom flag, tidak ada middleware `feature:`. Atas keputusan pemilik, `PHASE-FEATURE-FLAGS` dikerjakan **penuh** lebih dulu (termasuk gerbang self-order, AI, MCP, dan Settings-nya), baru papan antrian di atasnya.
- **Seluruh penghalang keras di bagian A entri ini tertutup:**
  1. **`business_type` dicabut sepenuhnya** — mode antrian murni toggle Settings (`kitchen_queue_enabled`), tidak menyentuh dimensi harga sama sekali.
  2. **Transaksi offline tidak masuk papan** — bukan lagi kelalaian melainkan batas lingkup yang dinyatakan, dikunci test, dan ditampilkan sebagai pita di UI serta di deskripsi toggle.
  3. **Void membersihkan papan** — dua lapis: `void()` menolkan `fulfillment_status`, dan query papan tetap mengecualikan `STATUS_VOIDED`.
  4. **Masa tenggang tidak lagi membekukan dapur** — `cashier.queue.*` masuk `ALWAYS_ALLOWED`, dengan test yang memastikan pengecualian itu tidak melebar jadi pintu belakang untuk transaksi baru.
- **Sepuluh koreksi di bagian B dikerjakan apa adanya:** `expected_from` wajib, `id` sebagai pemecah seri, `refresh()` sebelum tukar posisi, satu `update()` alih-alih tiga, batas hari lewat `effectiveDate()`, dan blok alias `PHASE-FEATURE-FLAGS` yang salah fakta sudah dikoreksi sebelum disalin — `tenant`/`tenant.api` tetap grup, dan lima alias yang sudah ada tidak terhapus.
- **Bagian C — yang dikerjakan dan yang tidak:** poin 11 (*Dahulukan* satu-tap), 12 (BELUM BAYAR + konfirmasi), 13 (nomor sampai ke pelanggan lewat struk, cetak termal, dan respons webhook), 15 (`ready` tidak bisa diurutkan ulang), dan 18 (migrasi backfill + sumbernya ditutup) **selesai**. Poin 14 (batal dari papan), 16 (batching), dan 17 (fulfillment parsial) **sengaja di luar lingkup** sesuai Keputusan 2 pemilik.
- **Temuan baru saat pengerjaan — bukan di entri asli.** `TenantFactory` mewarisi default kolom, sehingga `self_order_enabled` bawaannya `false` di test. Lima test permukaan self-order yang sudah ada karenanya gagal, dan dua di antaranya sebelumnya lulus **karena alasan yang salah**. Semuanya kini menyalakan flag secara eksplisit, yang sekaligus mendokumentasikan ketergantungannya.
- **Yang BELUM diverifikasi:** papan belum dibuka di peramban — basis data pengembangan (MySQL) tidak berjalan saat pengerjaan. Backend tertutup 35 test dan `npm run build` membuktikan Vue-nya terkompilasi, tapi tampilan kartunya belum pernah dilihat manusia.
- **Catatan penutup atas ketegangan dengan `[BL-020]`:** entri ini dan BL-020 mendorong `EnsureSubscriptionActive` ke dua arah berlawanan. BL-020 diselesaikan lebih dulu tanpa menyentuh middleware itu sama sekali, sehingga keputusan `ALWAYS_ALLOWED` benar-benar tinggal di sini — dan masing-masing arah punya testnya sendiri, syarat yang diminta kedua entri.

### [BL-020] Grup API Self-Order Tidak Melewati Gerbang Langganan
- **Ditemukan:** 2026-07-28
- **Sumber:** Terlihat saat menulis ulang `PHASE-FEATURE-FLAGS` — memetakan middleware tiap permukaan
- **Status:** Selesai (2026-07-29) — lihat `[HOTFIX] Gerbang Langganan di Jalur Self-Order (BL-020)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **TERBUKTI NYATA.** Entri ini ditulis dengan peringatan "belum diverifikasi, buktikan dengan test lebih dulu". Testnya ditulis lebih dulu (`SelfOrderSubscriptionGateTest`) dan **tiga dari lima ekspektasi awalnya gagal**, persis seperti yang diduga:
  - `suspended` → `POST /api/v1/orders` menjawab **422** (validasi controller), bukan 403 — permintaan lolos gerbang
  - `grace` → `POST /api/v1/orders` menjawab **422** — sama, padahal `grace` hanya-baca
  - `suspended` → `GET /api/v1/products` menjawab **200** — katalog tetap terbuka
- **Perbaikan:**
  Usulan 2 dijalankan apa adanya: gerbang langganan **dipisahkan** dari verifikasi surel lewat alias baru `subscription`, bukan dengan menempelkan grup `tenant.api`. Token mesin kini tunduk pada siklus hidup langganan tanpa dituntut membuktikan alamat surel yang memang tidak dimilikinya — dan ada test yang menjaga batas itu, supaya tidak ada yang "merapikan"-nya jadi `tenant.api` di kemudian hari.
- **Usulan 3 dikerjakan dan hasilnya bersih:** `php artisan route:list --path=api` menunjukkan seluruh grup mobile sudah memakai `tenant.api`. Self-order satu-satunya yang bocor.
- **Keputusan yang tidak ada di entri asli — rute fulfillment sengaja DI LUAR gerbang.** `PATCH /orders/{id}/fulfillment` ikut di grup yang sama, tapi memajukan status pesanan menyelesaikan kewajiban yang uangnya sudah diterima, bukan membuka layanan baru. Menggerbangnya akan menghentikan dapur di tengah antrean pada hari langganan lewat jatuh tempo — persis yang diperingatkan poin 4 `[BL-019]`. Rutenya dipisah di `routes/api.php`, **bukan** dengan menambah pengecualian ke `ALWAYS_ALLOWED`.
- **Catatan penutup atas ketegangan dengan `[BL-019]`:** entri ini memperingatkan bahwa keduanya mendorong gerbang yang sama ke dua arah berbeda dan "sebaiknya tidak dikerjakan bersamaan tanpa test yang memisahkannya". Syarat itu dipenuhi — ada test untuk masing-masing arah — dan `EnsureSubscriptionActive` **tidak disentuh sama sekali**, sehingga kebijakan antrean seutuhnya tetap milik `[BL-019]` yang masih terbuka.

### [BL-017] Peringatan Stok Belum Berkembang Jadi Upsell (FITUR BESAR)
- **Ditemukan:** 2026-07-25
- **Sumber:** Saran yang diterima setelah sesi pitching — "sudah ada peringatan stok dll, kalau bisa kembangkan fiturnya jadi upsell"
- **Status:** Selesai (2026-07-27) — lihat `[ADDITION] Saran Jual dari Sinyal Stok (BL-017)` di `docs/CHANGELOG.md` dan `docs/phases-2/PHASE-UPSELL_Stock-Signal-To-Upsell.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Enam usulan di entri ini dikerjakan apa adanya, dengan satu bagian sengaja ditahan.
  **Usulan 1 dijalankan penuh:** `BadgeHelperService` **tidak disentuh sama sekali** — dashboard owner tetap persis seperti sebelumnya. Mesin rekomendasi berdiri terpisah di `app/Services/Upsell/`, dengan penjaga kandidat di satu tempat (`SellableVariantQuery`) dan tiga strategi di baliknya.
  **Usulan 2 dibedakan sungguhan:** `attach` (ko-okurensi modifier), `pressed_stock` (barang tertekan), dan `upsize` (naik ukuran) punya kandidat, alasan, dan skornya masing-masing.
  **Usulan 3, 4 diuji, bukan sekadar dipatuhi:** varian `expired`, stok nol, dan produk nonaktif tidak pernah lolos jadi kandidat, dan jumlah saran dibatasi `upsell.max_per_transaction` (default 2).
  **Usulan 5 dikerjakan PERTAMA, bukan terakhir** — tabel `upsell_events` lahir sebelum permukaannya, tepat karena periode awal tidak bisa ditambal belakangan.
  **Usulan 6 diambil sebagai permukaan kedua**, bukan pengganti POS: `POST /api/v1/upsell/suggestions` plus `upsell_events` opsional di `POST /api/v1/orders`.
- **KOREKSI atas usulan 2.** Kalimat "perlu penanda urutan varian yang belum ada di `product_variants`" ternyata keliru. `price` **sudah** mendefinisikan urutan yang dimaksud "naik ukuran", dan menambah kolom urutan yang harus diisi manual justru akan membuat fiturnya mati diam-diam pada tenant yang tidak pernah mengisinya. Tidak ada kolom baru di `product_variants`.
- **Keputusan yang tidak ada di entri asli — saran dihitung di server, dipilih di client.** Indeks saran ikut props POS (bukan endpoint per perubahan keranjang) supaya ia ikut ter-snapshot `useCatalogCache` dan **tetap hidup saat perangkat offline**. Endpoint per-keranjang akan mati justru di warung yang sinyalnya paling buruk — properti offline yang sudah dibayar mahal di PHASE PWA tidak boleh dirusak fitur baru.
- **Yang DITAHAN, sesuai catatan penutup entri ini:** bundling **berdiskon**. `pressed_stock` hari ini menawarkan barang tertekan pada **harga katalog, tanpa potongan** — itu yang bisa dilakukan tanpa tempat sah untuk harga di bawah katalog. Versi berdiskonnya menunggu `[BL-018]`; `upsell_events.extra_amount` dan `reason` sudah disiapkan supaya tinggal disambung.
- **Batas yang diterima sadar:** event ikut payload checkout, jadi saran pada keranjang yang **dibatalkan** tidak tercatat. Yang diukur adalah "dari saran yang muncul pada transaksi yang jadi, berapa yang diambil". Endpoint terpisah bisa menghitung keranjang batal, tapi tidak akan selamat melewati mode offline.

### [BL-015] Dimensi Penetapan Harga Masih Terbatas Omzet & Seat
- **Ditemukan:** 2026-07-25
- **Sumber:** Permintaan pemilik SaaS — "harga akan beda-beda berdasarkan omzet **dan berbagai kategori lainnya**, dan nilainya harus bisa diubah dari panel platform, bukan tertanam di kode/data bawaan"
- **Status:** Selesai (2026-07-27) — lihat `[SCHEMA] Dimensi Harga Bebas — Aturan Berkriteria (BL-015)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Ditempuh lewat **usulan 1**: `min_revenue`/`max_revenue` dibuang, dan aturan kini punya banyak syarat (`dimensi`, `operator`, `nilai`) plus `priority`. Bracket lama **dipindahkan** oleh migration, bukan disemai ulang dari config — menyemai ulang akan membuang tarif yang sudah disunting pemilik SaaS lewat panel sejak Tahap D.
  **Usulan 2 terbukti benar dan dijalankan apa adanya:** katalog dimensi tinggal di `config/pricing-dimensions.php` karena tiap dimensi butuh sumber angkanya. Yang bebas sepenuhnya adalah ambang dan tarifnya.
  **Usulan 3 tidak jadi dipisah.** Rencananya dimensi atribut digarap belakangan, tapi pemilik memilih **tipe usaha** ikut di jalur pertama — jadi kolom `tenants.business_type`, pertanyaan di form pendaftaran, dan pengubahnya di panel dikerjakan sekaligus.
- **Empat penjaga usulan 4 tetap utuh dan masih diuji.** Saran "simpan jejak aturan yang menang" di poin yang sama **ikut dikerjakan**, dan ternyata bukan sekadar pelengkap: dengan banyak syarat per aturan, "aturan mana yang berlaku waktu itu" tak lagi bisa direka ulang dari satu angka omzet. `invoices.pricing_rule_id` + `pricing_context` menjawabnya.
- **Batas privasi usulan 5 ditegakkan di kode, bukan di kedisiplinan panel:** `DimensionRegistry` jadi satu-satunya pintu ke nilai dimensi dan menolak menghitung yang consent-nya tidak aktif — sehingga aturan berdimensi omzet tak pernah cocok untuk tenant jalur normal.
- **KOREKSI atas usulan 5.** Kalimat "teks consent naik versi" di sana keliru bila dibaca sebagai syarat pemakaian data. Memakai pemeriksaan versi-terkini sebagai gerbang berarti satu kali menaikkan versi teks akan memadamkan dimensi itu bagi **seluruh** tenant sekaligus — harga mereka berubah diam-diam pada hari revisi terbit, tanpa satu pun dari mereka melakukan apa-apa. Gerbangnya adalah persetujuan yang **masih aktif**; kenaikan versi tetap memicu permintaan menyetujui ulang, tapi itu urusan UI, bukan urusan kelayakan data. Kenaikan versi tetap wajib untuk dimensi yang benar-benar membuka data baru.
- **Keputusan pemilik SaaS (2026-07-27)** yang menjawab catatan penutup entri ini: dimensi jalur pertama adalah **volume transaksi**, **jumlah pengguna aktif**, dan **tipe usaha**. Dua yang pertama nol biaya privasi — `transaction_count` sudah dikumpulkan dan sudah tercakup consent subsidi yang berlaku, dan seat hanya `count()` di `users`.
- **Temuan yang mengubah bentuk pekerjaan ini:** mesin harga ternyata **tidak pernah menentukan tagihan siapa pun**. `bracketFor()` hanya dipanggil tiga permukaan tampilan; nominal tagihan diketik tangan di `InvoiceController`. Karena itu menambah dimensi saja tidak akan terasa, dan atas keputusan pemilik hasilnya kini **mengisi otomatis** kolom nominal — tetap bisa diketik ulang, dan aturannya hanya dicatat bila nominal yang terbit benar-benar sama dengan tarif aturannya.
- **Yang TIDAK dikerjakan (sengaja):** dimensi **wilayah**, **jumlah outlet**, dan **durasi langganan**. Outlet belum punya tabelnya; durasi menuntut keputusan siklus penagihan tersendiri karena periode satu bulan tertanam di `current_period_end` dan beberapa tempat lain.

### [BL-003] Mobile API — Permissions RBAC di Auth & Gating Endpoint
- **Ditemukan:** 2026-07-21
- **Sumber:** Keputusan C plan RBAC (`docs/phases-2/PHASE-RBAC_Module-Access-Control.md`, Bagian 8)
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Permissions RBAC di Mobile API (BL-003)` di `docs/CHANGELOG.md`
- **Prioritas:** Low
- **Perbaikan:**
  Bagian pertama dikerjakan penuh: `permissions` kini ada di payload login **dan** di `/mobile/tenant/profile` — yang kedua supaya aplikasi bisa menyegarkan izin tanpa login ulang, sebab token mobile bertahan berminggu-minggu sementara owner bisa mengubah role kapan saja. Perhitungannya dipindah ke `User::modulePermissions()`, satu sumber untuk web dan mobile.
- **Bagian kedua ("gerbang endpoint per modul") ternyata belum punya sasaran.** Tidak satu pun endpoint mobile yang ada saat ini punya padanan bergerbang modul di web: semuanya POS, laci kas, dan riwayat kasir, yang di web sengaja hanya digerbang `role:cashier,owner`. Middleware `permission.api` tetap dibuat dan diuji, siap untuk endpoint bermodul berikutnya.
- **Keputusan pemilik SaaS (2026-07-25):** `pos` dan `cash_drawer` tetap **penanda menu**, bukan gerbang rute — di web maupun mobile. Menggerbangnya di mobile saja akan mengunci staf "Tanpa role (POS saja)" sekaligus membuat orang yang sama ditolak aplikasi tapi diterima peramban.
- **Lubang yang tersingkap dan sengaja dibiarkan:** kasir ber-role "Gudang" (hanya `stock`) masih bisa mengetik `/cashier/pos` di peramban dan berjualan. Menutupnya mengubah perilaku staf yang sudah ada, jadi diputuskan tidak sekarang.

### [BL-004] Pesan Validasi Masih Bahasa Inggris di UI Berbahasa Indonesia
- **Ditemukan:** 2026-07-21
- **Sumber:** Validasi live blackbox fitur RBAC — form "Tambah Staf", email duplikat + kata sandi < 8 karakter
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Pesan Validasi Berbahasa Indonesia (BL-004)` di `docs/CHANGELOG.md`
- **Prioritas:** Low
- **Perbaikan:**
  Ditempuh lewat **usulan 1** (locale global), bukan usulan 2 (`messages()` per FormRequest): sekali kerja untuk semua form, dan form baru ikut terjemahan dengan sendirinya. `lang/id/{validation,auth,passwords,pagination}.php` lengkap, `APP_LOCALE=id`, dengan fallback tetap `en` supaya kunci yang terlewat muncul sebagai kalimat Inggris — bukan sebagai `validation.required`.
- **Usulan 3 (`attributes`) ikut dikerjakan, dan ternyata bagian terpentingnya.** Tanpa daftar nama kolom, hasilnya berbunyi "Kolom business_name wajib diisi" — separuh jadi, dan justru lebih janggal daripada tidak diterjemahkan sama sekali. Daftarnya disusun dari kolom yang benar-benar divalidasi aplikasi ini.
- **Dua permukaan yang dicatat di entri ini sudah diverifikasi langsung:** form tambah staf (skenario asli backlog) lewat test, dan form login lewat peramban — keduanya kini menyebut "Kolom Email" dan "Kolom Kata Sandi".
- **Efek samping yang menguntungkan:** nama bulan pada tanggal yang dirender `translatedFormat()` ikut berbahasa Indonesia.

### [BL-014] Pendaftaran Berulang Demi Trial Gratis Baru
- **Ditemukan:** 2026-07-25
- **Sumber:** Ditunda sadar-sadar saat mengerjakan PHASE SAAS Tahap B
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Peringatan Login Gagal & Verifikasi Email` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Dua lapis. **Verifikasi surel wajib** untuk owner yang mendaftar sendiri — inilah penghalang utamanya, karena akun tanpa alamat yang benar-benar bisa dibuka tidak berguna untuk apa pun. Staf buatan owner dikecualikan (owner yang menjaminnya, dan kasir warung kecil kerap tak punya surel sendiri). **Penandaan pendaftaran berulang** dari satu IP sebagai lapis kedua, untuk pola yang lolos dari lapis pertama.
- **Keputusan yang layak diingat:** ditandai, **bukan diblokir**. Satu IP publik bisa dipakai bersama satu kompleks pertokoan — memblokir otomatis akan menjegal warung sebelah yang tidak melakukan kesalahan apa pun.
- **Yang TIDAK dikerjakan (sengaja):** verifikasi nomor telepon. Untuk UMKM Indonesia nomor jauh lebih mahal dibuat berulang daripada alamat surel, jadi ia sinyal yang lebih kuat — tapi formulir pendaftaran belum meminta nomor sama sekali, dan memintanya adalah perubahan alur tersendiri berikut kebutuhan penyedia SMS.

### [BL-011] Belum Ada Peringatan Saat Percobaan Masuk Gagal Menumpuk
- **Ditemukan:** 2026-07-22
- **Sumber:** Sisa usulan `[BL-007]`, dengan fondasi dari `[BL-009]`
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Peringatan Login Gagal & Verifikasi Email` di `docs/CHANGELOG.md`
- **Prioritas:** Low
- **Perbaikan:**
  Perintah terjadwal per jam yang menyaring `login.failed` dari jejak audit. Dua pola dengan ambang sendiri sesuai usulan aslinya: banyak kegagalan pada **satu alamat** (penebakan kata sandi) versus banyak alamat berbeda dari **satu IP** (penebakan akun, yang lolos dari kunci per-email justru karena tiap alamat dicoba sedikit). Peringatan sejenis dijeda supaya satu serangan semalaman tidak menghasilkan satu surel tiap jam.
- **KOREKSI atas isi entri ini:** poin 2 usulan aslinya menyebut *"tentukan kanalnya dulu — proyek belum punya satu pun"* dan menyarankan Telegram lewat n8n. **Itu sudah tidak benar sejak `[BL-010]`/`[BL-012]`**: surel sudah jadi kanal yang bekerja lewat notifikasi pemulihan kata sandi. Jadi tidak ada kanal baru yang perlu dibangun.
- **Cakupan yang perlu disadari:** hanya percobaan masuk ke **panel platform**. Login tenant yang gagal belum dicatat di mana pun karena sisi tenant tidak punya tabel audit sendiri — membuatnya adalah pekerjaan tersendiri yang belum ada di backlog.

### [BL-005] Platform Console — Panel Pemilik SaaS (Privacy-Preserving)
- **Ditemukan:** 2026-07-21
- **Sumber:** Permintaan pemilik SaaS — satu panel untuk melihat tenant, langganan, dan pembayaran **tanpa** melihat data operasional klien
- **Status:** Selesai (Tahap A 2026-07-21/22, Tahap B–D 2026-07-25) — lihat dua entri `[ADDITION] PHASE SAAS` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Empat tahap. **A:** guard `platform` terpisah + RBAC modul sendiri (spatie terbukti mustahil dipakai di sini) + daftar tenant read-only + jejak audit. **B:** model langganan, trial, masa tenggang hanya-baca, batas seat, consent jalur normal, panel pembayaran manual. **C:** jalur subsidi berikut job penghitung omzet, consent sendiri, dan tampilan bracket. **D:** aturan harga sebagai data dengan grandfathering.
- **Penjaga privasi yang benar-benar berdiri:** `PlatformArchTest` (menjaga impor), `PlatformIsolationTest` (menjaga payload HTTP), resource berbasis daftar putih, dan satu-satunya pintu ke data penjualan berupa job terjadwal di luar namespace `Platform`.
- **Turunan yang lahir dari pengerjaan ini:** `[BL-007]` s/d `[BL-014]`.

### [BL-006] Sistem Langganan Dua Jalur — Harga Normal & Subsidi UMKM
- **Ditemukan:** 2026-07-21
- **Sumber:** Permintaan pemilik SaaS — subscription normal **plus** skema bantu UMKM berbasis omzet dan jumlah pengguna
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Jalur Subsidi & Aturan Harga Dinamis` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Dua jalur hidup berdampingan. **Jalur normal** tidak membuka satu pun angka penjualan. **Jalur subsidi** opt-in: omzet dihitung otomatis dari `transactions` ke tabel ringkasan, dan harga mengikuti bracket. Seat dihitung dari pengguna aktif, dengan `seat_high_water` sebagai dasar tagihan agar toggling tidak menghemat biaya. Seluruh tarif di-CRUD dari platform console.
- **Ketegangan dengan `[BL-005]` diselesaikan seperti yang disyaratkan di sana:** pembukaan data (1) sukarela, (2) terbatas pada omzet & cacah transaksi — tanpa laba/margin yang memang tidak pernah dihitung, (3) persetujuannya tercatat berikut versi teksnya, dan (4) bisa dicabut kapan saja, dengan data terhapus seketika sementara harga baru kembali normal di akhir periode.
- **Penyempurnaan atas keputusan awal:** daftar tenant menampilkan **bracket**, bukan angka persis. Angka rupiahnya dibuka di halaman tersendiri yang tiap kunjungannya tercatat — sehingga janji di dokumen consent klien bisa dibuktikan.

### [BL-012] Pengguna Tenant Juga Belum Punya Pemulihan Kata Sandi
- **Ditemukan:** 2026-07-22
- **Sumber:** Terlihat saat mengerjakan `[BL-010]` — `php artisan route:list` menunjukkan tidak ada satu pun rute password reset di seluruh aplikasi
- **Status:** Selesai (2026-07-22) — lihat `[ADDITION] Pemulihan Kata Sandi Pengguna Tenant (BL-012)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium (menimpa setiap owner dan staf tenant, jauh lebih banyak daripada sisi platform)
- **Deskripsi:** Owner tenant yang lupa kata sandi tidak punya jalan keluar sama sekali. Staf masih bisa ditolong owner lewat manajemen staf, tapi owner tidak bisa ditolong siapa pun selain lewat akses database langsung.
- **Perbaikan:**
  Empat rute + controller + dua halaman, mengikuti pola `Platform\PasswordResetController`. Ternyata **tidak perlu migration**: tabel `password_reset_tokens` sudah ada sejak migrasi bawaan Laravel, broker `users` sudah terkonfigurasi, `User` sudah memakai trait `Notifiable`, dan `users.email` unik global sehingga tak ada tabrakan antar tenant. Yang benar-benar hilang hanya rute, controller, dan halamannya.
  Disertai: balasan seragam agar tidak jadi alat menyisir alamat, limiter `password-reset` **terpisah** dari milik platform, notifikasi berbahasa Indonesia, dan pencatatan permintaan ke log aplikasi (sisi tenant tak punya tabel audit sendiri, dan membuatnya akan jadi perluasan lingkup).

### [BL-010] Akun Platform Belum Punya Pemulihan Kata Sandi, 2FA, & Variabel Env
- **Ditemukan:** 2026-07-21
- **Sumber:** Review sisa pekerjaan setelah Platform Console Tahap A
- **Status:** Selesai sebagian (2026-07-22) — lihat `[ADDITION] Pemulihan Kata Sandi Akun Platform (BL-010)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  1. **Pemulihan kata sandi platform** — broker `platform_users` dengan **tabel token sendiri** (`platform_password_reset_tokens`), notifikasi sendiri agar tautannya mengarah ke `/platform/reset-password` (bawaan Laravel memakai rute tenant), empat rute, dan dua halaman. Balasan permintaan **selalu seragam** agar halaman ini tidak jadi alat memeriksa keberadaan akun. Endpoint-nya di-throttle, dan permintaan serta penyelesaiannya dicatat sebagai kejadian `sensitive`.
  2. **`.env.example`** kini memuat `PLATFORM_ADMIN_NAME` / `_EMAIL` / `_PASSWORD` beserta peringatannya, dan **seeder gagal keras** di luar `APP_ENV` lokal bila kata sandinya masih bawaan.
- **Yang TIDAK dikerjakan (sengaja, sesuai catatan awal entri ini):** **2FA**. Tetap jadi target, dicatat ulang di bawah supaya tidak hilang bersama entri yang sudah ditutup.
- **Turunan yang lahir dari pengerjaan ini:** `[BL-012]` — ternyata pengguna **tenant** juga tak punya pemulihan kata sandi sama sekali.

### [BL-009] Audit Log Platform: Tercatat tapi Belum Bisa Dibaca, Berisik, & Tanpa Retensi
- **Ditemukan:** 2026-07-21
- **Sumber:** Review sisa pekerjaan setelah Platform Console Tahap A
- **Status:** Selesai (2026-07-22) — lihat `[ADDITION] Halaman & Retensi Jejak Audit (BL-009)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Deskripsi:** Jejak audit sudah terisi benar sejak Tahap A, tapi (1) tidak ada halaman untuk membacanya, (2) setiap kali daftar tenant dibuka tercatat satu baris sehingga kejadian penting tenggelam, dan (3) tabelnya tumbuh selamanya tanpa pembersihan.
- **Perbaikan:**
  1. **Garis rutin vs sensitif diputuskan** dan dituliskan sebagai kolom `severity` + penjelasan di `config/platform-audit.php`:
     - `routine` — akses **baca** yang wajar berulang (buka daftar, login berhasil, logout). Tetap dicatat, tapi dideduplikasi per aktor+aksi+subjek dalam jendela 15 menit.
     - `sensitive` — semua yang **mengubah keadaan**, semua yang membuka **data bisnis klien** (kelak omset jalur subsidi), dan **kejadian keamanan** (login gagal). Selalu dicatat.

     Pedoman untuk kejadian baru ikut ditulis di config: *"kalau klien menuntut pertanggungjawaban, apakah baris ini yang akan saya tunjukkan?"* Kalau ya, ia sensitif.
  2. **Dideduplikasi, bukan dibuang.** Menghapus pencatatan akses rutin akan membuat pertanyaan "siapa membuka daftar klien saya minggu lalu?" tak terjawab — padahal itu justru pertanyaan yang ingin dijawab audit log. Deduplikasi menyelesaikan kebisingan tanpa kehilangan jawabannya.
  3. **Halaman `/platform/audit-logs`** (read-only, digerbang `platform.can:audit_logs`) dengan filter aksi, derajat, dan rentang tanggal. Modul `audit_logs` diubah jadi `available => true`.
  4. **Retensi berbeda per derajat** — rutin 90 hari, sensitif 730 hari — dijalankan perintah `platform:prune-audit-logs` (punya `--dry-run`) yang dijadwalkan harian pukul 03:10.
- **Catatan:** usulan lama "beri peringatan bila `login.failed` melewati ambang" tetap belum dikerjakan. Sekarang jauh lebih mudah karena `login.failed` sudah bertanda `sensitive` dan bisa disaring, tapi itu ranah **pemantauan/notifikasi** — dicatat ulang sebagai `[BL-011]`.

### [BL-008] Panel Platform: Modul Tanpa Halaman & Belum Ada UI Kelola Akun
- **Ditemukan:** 2026-07-21
- **Sumber:** Review sisa pekerjaan setelah Platform Console Tahap A
- **Status:** Selesai (2026-07-21) — lihat `[ADDITION] Manajemen Akun Platform (BL-008)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Deskripsi:** Tiga hal: (1) lima dari enam modul di katalog belum punya halaman sehingga mencentangnya tidak berefek apa pun; (2) tidak ada UI untuk membuat akun platform maupun mencentang modulnya — padahal pola itulah yang diminta, dan bagian ini tidak terjadwal di Tahap B–D mana pun; (3) arch test resource masih berbasis nama kelas sehingga resource platform kedua tidak akan terjaga.
- **Perbaikan:**
  1. **Halaman `/platform/users`** — daftar akun, tambah akun, ubah nama/email/kata sandi, centang modul, hapus. Mengikuti pola `Owner/RoleController`.
  2. **Dijaga penanda `is_owner`, bukan modul grantable.** Ini keputusan keamanan, bukan sekadar gaya: kalau "kelola akun" jadi modul yang bisa dicentang, staf platform yang memegangnya dapat mencentangkan `revenue_data` untuk dirinya sendiri — dan pemisahan modul sensitif jadi tak ada artinya. Mengikuti pola sisi tenant, di mana manajemen staf/role dijaga `role:owner`.
  3. **Modul tanpa halaman ditandai `available => false`** di `config/platform-rbac.php`: checkbox-nya nonaktif, diberi keterangan "Halamannya belum ada", dan ditolak di validasi. Cukup ubah flag saat halamannya jadi.
  4. **Resource dipindah** ke `App\Http\Resources\Platform\TenantResource`, dan arch test kini menjaga **namespace** — resource platform berikutnya ikut terjaga tanpa harus ingat mendaftarkannya.
  5. Migration mengisi mundur `is_owner` untuk akun pertama dan membersihkan baris modul miliknya, agar pemasangan yang sudah menjalankan seeder Tahap A tidak berakhir tanpa satu pun pemilik.
- **Sisa yang memang belum dikerjakan (bukan terlewat):** halaman untuk lima modul lain menyusul di Tahap B–D sesuai rencana.

### [BL-007] Endpoint Login Tanpa Rate Limit (Web Tenant, Platform, & Mobile API)
- **Ditemukan:** 2026-07-21
- **Sumber:** Review sisa pekerjaan setelah Platform Console Tahap A — diverifikasi lewat `php artisan route:list -v`
- **Status:** Selesai (2026-07-21) — lihat `[HOTFIX] Rate Limit Endpoint Login (BL-007)` di `docs/CHANGELOG.md`
- **Prioritas:** High (satu-satunya entri keamanan yang bisa dieksploitasi dari luar tanpa akun)
- **Area Terdampak:**
  - `POST /login` — tidak ada `throttle`
  - `POST /platform/login` — tidak ada `throttle`
  - `POST /api/v1/mobile/login` — **koreksi:** ternyata **sudah** punya `throttle:5,1`, tapi terkunci per IP saja
- **Deskripsi:**
  Tidak ada satu pun endpoint login **web** yang dibatasi laju percobaannya, sehingga tebak-kata-sandi otomatis tidak menemui hambatan apa pun. Bukan regresi dari Tahap A — kondisi yang sama sudah berlaku untuk login tenant sejak awal, dan baru terlihat saat memeriksa middleware panel platform. Yang menaikkan prioritasnya adalah taruhannya: satu akun platform yang jebol membuka data administratif seluruh klien sekaligus.
- **Penyebab:** Rute login web tak pernah diberi middleware `throttle`. Endpoint mobile sudah diberi, tapi memakai bentuk `throttle:5,1` yang mengunci per IP.
- **Perbaikan:**
  Tiga limiter bernama di `AppServiceProvider`, semuanya berkunci **email + IP** (email dinormalkan lowercase agar ubah kapitalisasi tidak memberi jatah baru):
  - `login` — 5/menit, dipasang di `POST /login`
  - `platform-login` — 5/menit per email+IP **plus** 20/jam per IP, dipasang di `POST /platform/login`
  - `mobile-login` — 5/menit, menggantikan `throttle:5,1` di `POST /api/v1/mobile/login`

  Langit-langit per jam per IP **sengaja hanya di panel platform**: ia menahan penebakan lintas-email yang lolos dari kunci email+IP, dan aman di sana karena penggunanya segelintir. Menerapkannya di login tenant justru berbahaya — satu warung dengan banyak kasir di balik satu IP publik bisa saling mengunci.

  Balasan saat terblokir dikembalikan sebagai **error validasi berbahasa Indonesia** di bawah kolom email (lewat `Limit::response()`), bukan halaman 429 generik — alur login memakai Inertia, dan pesan di tempat jauh lebih berguna. Endpoint mobile tetap 429 JSON karena konsumennya aplikasi.

  8 test di `tests/Feature/Security/LoginThrottleTest.php`, termasuk penjaga bahwa kunci per-email tidak membuat satu akun mengunci akun lain dan bahwa langit-langit per-IP platform tidak merembet ke login tenant. Suite penuh 242 hijau.
- **Sisa yang tidak dikerjakan (sengaja):** usulan "beri peringatan bila `login.failed` melewati ambang" belum dibuat — itu soal pemantauan, bukan penahanan, dan lebih cocok digarap bersama halaman audit log di `[BL-009]`.

### [BL-002] Dua Test Gagal (Pre-existing) di Suite
- **Ditemukan:** 2026-07-16
- **Sumber:** Menjalankan suite lengkap saat mengerjakan PWA Fase B/C/D. Diverifikasi **bukan** regresi: keduanya juga gagal pada working tree bersih (`git stash` lalu run ulang).
- **Status:** Selesai (2026-07-21) — lihat `[HOTFIX] Ekspektasi Dua Test Usang (BL-002)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium (menutupi sinyal CI — dua kegagalan tetap membuat suite merah, sehingga regresi baru mudah terlewat)
- **Area Terdampak:**
  - `tests/Feature/ExampleTest.php:6` — `the application returns a successful response`
  - `tests/Feature/Auth/AuthTest.php:78` — `owner can access cashier routes`
- **Deskripsi:**
  1. `ExampleTest` meng-assert `GET /` mengembalikan `302`, tetapi kini mengembalikan `200`. Kemungkinan test bawaan yang tidak pernah disesuaikan setelah halaman root berubah.
  2. `AuthTest > owner can access cashier routes` meng-assert `GET /cashier/cash-drawer` mengembalikan `200` untuk owner, tetapi mengembalikan `302`. Kemungkinan owner ikut diarahkan oleh guard laci kas, atau ekspektasi test sudah usang terhadap perilaku sekarang.
- **Penyebab:** Kedua **ekspektasi test** yang usang, bukan bug aplikasi. `/` kini me-render landing page (200) lewat `LandingController@index`. `/cashier/cash-drawer` **sengaja** mengalihkan owner ke POS (`CashDrawerController@index` baris 23–25: "Sesi kas hanya untuk kasir; owner diarahkan ke POS") → owner dapat 302.
- **Perbaikan:** `ExampleTest` di-assert `200` (sesuai nama test "successful response"). `AuthTest > owner can access cashier routes` diarahkan ke `/cashier/pos` — rute kasir yang benar-benar ter-render untuk owner (owner melewati gerbang role `role:cashier,owner`), sambil mempertahankan niat test. Suite penuh hijau (206 passed).

### [BL-001] Penomoran Ordered List Hasil AI Selalu "1." (Tidak Increment)
- **Ditemukan:** 2026-07-11
- **Sumber:** Live blackbox testing fitur AI Analysis (uji tipe Insight Umum, Saran Diskon, dll)
- **Status:** Selesai (2026-07-15) — lihat `[HOTFIX] Penomoran Ordered List Hasil AI (BL-001)` di `docs/CHANGELOG.md`
- **Prioritas:** Low (kosmetik, tidak mengubah data/keputusan bisnis)
- **Area Terdampak:**
  - `resources/js/Pages/Owner/AiAnalysis/Index.vue` — fungsi `renderMarkdown()`
- **Deskripsi:**
  Saat hasil AI berisi ordered list dengan baris kosong di antara tiap item (pola umum output LLM, misalnya SumoPod/OpenAI-compatible provider), setiap item dirender sebagai `<ol>` terpisah alih-alih satu list gabungan. Akibatnya browser menomori ulang tiap item mulai dari "1." — hasil tampilan jadi "1. 1. 1." bukan "1. 2. 3.". Teramati langsung pada hasil analisis tipe "Insight Umum" tenant Kopi Story (analysis ID 2, periode 12 Jun–11 Jul 2026).
- **Penyebab:**
  Fungsi `closeList()` dipanggil setiap kali menemui baris kosong (`trimmed === ''`), menutup `<ol>`/`<ul>` yang sedang terbuka. Karena LLM sering menyisipkan baris kosong antar item list untuk keterbacaan, tiap item berakhir jadi list tunggal baru bukan lanjutan list sebelumnya.
- **Perbaikan:**
  Saat menemui baris kosong, renderer mengintip baris non-kosong berikutnya (`nextLineContinuesList()`); bila masih match pola list dengan `listType` sama, list dibiarkan terbuka (baris kosong = pemisah item), selain itu ditutup seperti semula. Diverifikasi untuk kasus: list dengan spasi antar item, list diikuti paragraf, dan list campuran ordered+unordered.
