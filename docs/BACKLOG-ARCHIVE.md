# SAPI — Arsip Backlog Selesai

**Format:** Arsip entri `docs/BACKLOG.md` yang statusnya sudah `Selesai`. Dipisahkan dari file utama pada 2026-07-31 karena bagian ini memakan lebih dari separuh isi file padahal hampir tidak pernah perlu dibaca saat bekerja.

> **Cara membaca file ini:** jangan dibaca utuh. Indeks ringkas seluruh entri di sini ada di `docs/BACKLOG.md` bagian "Riwayat Selesai (Arsip)" — cari ID-nya di sana lebih dulu. Bila isi lengkap sebuah entri memang dibutuhkan, cari headingnya (`grep -n '^### \[BL-0xx\]' docs/BACKLOG-ARCHIVE.md`) lalu baca potongan itu saja.
>
> Entri baru **tidak** ditulis di sini. Entri ditulis di `docs/BACKLOG.md`, dan dipindahkan ke sini setelah statusnya `Selesai`.

---

## Daftar Entri

### [BL-042] Daftar Tenant Memajang Kolom Informasi, Bukan Jalan Masuk ke Rincian — dan Tautan yang Ada Bisa Berujung 403
- **Ditemukan:** 2026-08-01
- **Sumber:** Permintaan pemilik — "pada halaman daftar tenant, ganti informasi kolom yang terdaftar menjadi action button untuk melihat detail... di dalam detail barulah kita bisa liat informasi detailnya... bisa menggunakan konsep nav tab"
- **Status:** Selesai (2026-08-01) — lihat `[ADDITION] Rincian Tenant Bertab, dan Daftarnya Kembali Jadi Daftar (BL-042)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/Pages/Platform/Tenants/Index.vue:15-21` — lima kolom (`name`, `owner`, `business_type`, `users`, `registered`) tanpa satu pun kolom aksi
  - `resources/js/Pages/Platform/Tenants/Index.vue:46-51` — satu-satunya jalan ke rincian adalah nama tenant yang diam-diam menaut ke `/platform/subscriptions/{id}`; tidak ada penanda visual bahwa ia bisa diklik
  - `routes/web.php:288-291` vs `routes/web.php:299-308` — daftarnya digerbang `platform.can:tenants`, rincian yang ditautnya digerbang `platform.can:subscriptions,payments`
  - `app/Http/Middleware/EnsurePlatformModule.php:29-34` — gerbangnya `abort(403)`; staf yang hanya dipegangi modul `tenants` akan menabrak halaman error saat menekan nama tenant
  - `resources/js/Pages/Platform/Subscriptions/Show.vue:187-479` — halaman rincian sudah ada, tapi satu gulungan panjang: ringkasan → akun → langganan → riwayat tagihan → omzet
  - `app/Services/Platform/AccountOverview.php:39-54` — payload rincian tidak memuat kapabilitas kasir tenant sama sekali
  - `app/Models/Tenant.php:94-102` — `hasFeature()` sudah menjadi satu-satunya pintu untuk `kitchen_queue`, `self_order`, `ai`; nilainya tidak pernah dikirim ke panel platform
- **Deskripsi:**
  Halaman `/platform/tenants` menjawab pertanyaan "siapa saja klien kita" dengan memipihkan tiap tenant jadi lima kolom, lalu berhenti di situ. Rincian yang diminta sebenarnya **sebagian besar sudah dibangun** — `Platform/Subscriptions/Show.vue` sudah menampilkan paket, tarif berjalan (`price_locked`), batas pengguna beserta asal-usulnya, periode, label jalur harga (`Harga Tetap` / `Harga Adaptif` lewat `PRICING_TRACK` di `resources/js/support/platform.js:71-81`), kelompok harga bracket, dan seluruh riwayat tagihan dengan nominalnya. Masalahnya ada tiga:

  **(1) Jalan masuknya tidak terlihat dan tidak selalu sah.** Rinciannya hidup di bawah modul *Langganan & Tagihan*, bukan di bawah *Daftar Tenant*. Bagi pemilik SaaS yang memegang semua modul ini tidak terasa, tapi seluruh gerbang modul di panel ini dibuat justru supaya staf bisa diberi sebagian — dan staf yang hanya diberi `tenants` mendapat halaman yang setiap barisnya menaut ke 403.

  **(2) Rinciannya satu gulungan.** Empat urusan yang berbeda umur dan berbeda kepekaan (identitas akun, langganan, riwayat tagihan, omzet) berbaris vertikal tanpa pemisah. Riwayat tagihan tidak dipotong (`AccountOverview.php:127-136` sengaja mengambil seluruhnya), jadi tenant berumur setahun mendorong panel omzet keluar layar.

  **(3) "Benefit sistem kasir" memang belum pernah ada datanya.** Yang ditanyakan pemilik — fitur apa saja yang menyala untuk kasir tenant ini — tidak bisa dijawab halaman mana pun sekarang. `kitchen_queue_enabled`, `self_order_enabled`, dan `ai_enabled` tersimpan di tabel `tenants` dan dipakai di seluruh sisi tenant, tapi tidak pernah ikut di payload platform. Ini satu-satunya bagian permintaan yang benar-benar butuh data baru; sisanya soal penataan.
- **Usulan Perbaikan:**
  **(a)** Pindahkan kepemilikan rincian ke *Daftar Tenant*: rute `GET /platform/tenants/{tenant}` di bawah `platform.can:tenants`, dirakit dari `AccountOverview` yang sudah ada (ia sudah menyaring isi per modul penglihatnya, jadi staf ber-`tenants`-saja akan menerima `subscription`/`invoices` bernilai `null` — bukan 403). Alamat lama `/platform/subscriptions/{tenant}` tetap hidup, mengikuti pola pengalihan yang sudah dipakai `routes/web.php:354`.
  **(b)** Di daftarnya, sisakan kolom yang benar-benar membedakan satu baris dari yang lain (nama + slug, pemilik, status/tanda) dan ganti sisanya dengan satu kolom aksi **Lihat detail** di ujung kanan. Jenis usaha, jumlah akun, dan tanggal terdaftar pindah ke dalam rincian.
  **(c)** Beri rinciannya nav tab — usul: **Ikhtisar** (identitas, status, pemilik, jenis usaha, jumlah akun) · **Langganan** (paket, jalur harga tetap/adaptif beserta penjelasannya, kelompok harga, batas pengguna) · **Tagihan** (riwayat + nominal + tindakan verifikasi) · **Kapabilitas** (fitur kasir yang menyala) · **Omzet** (tetap di balik tautan beraudit tersendiri, tab-nya hanya mengantar ke sana). Tab yang modulnya tidak dipegang penglihatnya **tidak dirender sama sekali**, sejalan dengan prinsip `AccountOverview`: yang tidak boleh dilihat tidak ikut terkirim.
  **(d)** Tambahkan kapabilitas ke `AccountOverview::tenantPayload()` dengan membaca lewat `Tenant::hasFeature()`, bukan menyentuh kolomnya langsung — supaya menambah fitur baru kelak tetap satu tempat. **Keputusan pemilik 2026-08-01: tab ini HANYA MEMBACA.** Tidak ada tombol menyalakan/mematikan fitur kasir dari sisi platform, mengikuti alasan yang sama seperti pencabutan kuasa ubah tipe usaha di `[BL-015]`: mengubah cara kerja usaha orang tanpa sepengetahuannya bukan kewenangan penyedia layanan. Yang diberikan halaman ini adalah kuasa MENGETAHUI — pemilik SaaS tetap perlu tahu fitur apa yang menyala saat menjawab keluhan atau menjelaskan tarif.
  **(e)** Perlu diperhatikan saat mengerjakan: `PlatformArchTest` melarang controller platform mengimpor model operasional tenant, dan tab Kapabilitas tidak melanggarnya selama nilainya dibaca dari `Tenant` sendiri.
- **Koreksi saat dikerjakan:**
  Butir (a) menulis rute rincian digerbang `platform.can:tenants` saja — **itu keliru**, dan keliruan yang sama persis dengan yang sedang diperbaiki, hanya terbalik arahnya. Daftar langganan juga bermuara ke halaman ini, jadi menyempitkan gerbangnya ke satu modul akan membuat pemegang modul tagihan menabrak 403 di tautan tenant-nya. Yang dipakai: `platform.can:tenants,subscriptions,payments` ("salah satu cukup"), dengan isi tetap disaring per modul di `AccountOverview`. Karena itu pula `can.tenants` ikut dikirim — tombol "kembali" harus menunjuk daftar yang pembacanya memang berhak membukanya.
  Rute omzet ikut pindah (`/platform/tenants/{tenant}/revenue`) meski butir (a) hanya menyebut rincian akun: ia merender komponen yang sama dan sekarang mendarat di tab Omzet, jadi meninggalkannya di bawah `/subscriptions` hanya akan menyisakan satu alamat yang tidak lagi menggambarkan isinya. Keduanya mengalihkan dari alamat lama.

### [BL-040] Halaman Langganan & Tagihan Tidak Punya Pintu Masuk dari Dashboard
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "sampai bisa melihat tagihan dan payment akun yang terhubung itu masuk kategori apa, pembayaran selanjutnya, berapa mau di bayar, kapan membayar, apakah status subsidi dll, langsung dalam owner dashboard"
- **Status:** Selesai (2026-07-31) — lihat `[ADDITION] Pintu Masuk Langganan & Ringkasan Tagihan di Dashboard (BL-040)` di `docs/CHANGELOG.md`
- **Prioritas:** High (halamannya sudah jadi; yang hilang cuma jalan menuju ke sana)
- **Area Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue:100-138` — daftar navigasi lengkap; **tidak ada** entri langganan/tagihan
  - `resources/js/Pages/Owner/Dashboard.vue` — tidak menyebut langganan sama sekali
  - `app/Http/Controllers/Billing/SubscriptionController.php:36-97` — sudah menyiapkan nama paket, harga, jalur harga, seat terpakai, akhir periode, tanggal suspend, status subsidi berikut bracket-nya, dan 12 tagihan terakhir dengan jatuh tempo serta status buktinya
- **Deskripsi:**
  Hampir seluruh yang diminta pemilik **sudah dirender** di `/langganan` — kategori jalur, nominal, jatuh tempo, status subsidi, unggah bukti bayar. Yang tidak ada adalah cara menemukannya: pencarian `/langganan` di seluruh `resources/js` hanya menemukan rujukan dari dalam halaman Billing itu sendiri. Praktis, owner baru sampai ke sana kalau mengetik URL-nya atau kalau langganannya sudah bermasalah dan middleware melemparnya ke sana — persis kebalikan dari yang diinginkan.
- **Usulan Perbaikan:**
  Tambahkan entri "Langganan & Tagihan" di grup "Pengaturan" pada `OwnerLayout` (`ownerOnly: true`). Di dashboard, tambahkan satu kartu ringkas — jalur harga, tagihan berjalan beserta nominal dan jatuh temponya — yang menaut ke halaman penuh. Munculkan kartu itu lebih menonjol saat status tenant `grace`.
- **Koreksi saat dikerjakan:**
  Usulan di atas menulis "`ownerOnly: true`, sejalan dengan `role:owner` di rutenya" — **itu keliru**. Rute `/langganan` justru sengaja TIDAK digerbang `role:owner`; ia terbuka untuk semua pengguna tenant supaya kasir yang terlempar ke sana saat tenant ditangguhkan tidak mendarat di 403. Yang `ownerOnly` hanya pintu masuk tetapnya, dengan alasan yang berbeda: tagihan adalah urusan owner dengan penyedia layanan, bukan bagian dari pekerjaan kasir.

### [BL-025] Saran Jual Belum Bisa Diwajibkan, dan Tombol Bayar Nonaktif Tanpa Penjelasan
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "pada menu saran, buatkan setup untuk bisa diatur apakah wajib dilakukan penawaran atau bisa di abaikan (di setting pada menu Owner), tidak akan bisa bayar sampai penawaran itu diselesaikan … serta aktifkan component alert pemberitahuan error nya mengapa bayar button disabled"
- **Status:** Selesai (2026-07-31) — lihat `[ADDITION] Penawaran Wajib Diselesaikan & Status "Ditolak" Terpisah (BL-025)` di `docs/CHANGELOG.md`
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
