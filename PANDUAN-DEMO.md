# SAPI — Panduan Demo & Petunjuk Penggunaan

Berkas ini adalah panduan operasional untuk **menjalankan dan mendemokan** SAPI: apa produknya, kredensial akun, isi data contoh, urutan alur demo, dan batasan yang perlu diketahui sebelum bicara di depan penonton. Untuk penjelasan arsitektur yang lebih dalam, lihat [README.md](README.md).

Kalau Anda hanya punya waktu untuk satu bagian, baca [§1](#1-sapi-dalam-satu-halaman). Bagian itu sengaja ditulis lengkap sendiri — fitur utama, keunggulan, dan batasnya — supaya tidak perlu melompat ke mana-mana untuk bisa menjelaskan produk ini kepada orang lain.

> **Peringatan:** semua kredensial di berkas ini adalah **kredensial lingkungan lokal/demo**. Jangan pernah dipakai di server sungguhan. `PlatformUserSeeder` sendiri akan menolak berjalan dengan kata sandi bawaan di luar `local`/`testing` — lihat [§8](#8-batasan-yang-perlu-diketahui-sebelum-demo).

**Terakhir diperbarui:** 2026-08-01.

---

## Daftar Isi

1. [SAPI dalam Satu Halaman](#1-sapi-dalam-satu-halaman)
2. [Dua Lapis Autentikasi](#2-dua-lapis-autentikasi)
3. [Kredensial Demo](#3-kredensial-demo)
4. [Isi Data Tiap Tenant](#4-isi-data-tiap-tenant)
5. [Menyiapkan & Menyegarkan Data Demo](#5-menyiapkan--menyegarkan-data-demo)
6. [Kondisi Langganan & Penagihan](#6-kondisi-langganan--penagihan)
7. [Alur Demo yang Disarankan](#7-alur-demo-yang-disarankan)
8. [Batasan yang Perlu Diketahui Sebelum Demo](#8-batasan-yang-perlu-diketahui-sebelum-demo)
9. [Peta Halaman Lengkap](#9-peta-halaman-lengkap)

---

## 1. SAPI dalam Satu Halaman

**SAPI adalah aplikasi kasir dan stok multi-tenant untuk UMKM, dijual sebagai layanan berlangganan.** Satu pemasangan melayani banyak usaha sekaligus; tiap usaha hanya melihat datanya sendiri. Ada tiga jenis pengguna, dan masing-masing punya aplikasinya sendiri: **kasir** yang berjualan, **pemilik usaha** yang mengelola dan membaca laporan, dan **pemilik layanan (SaaS)** yang mengelola seluruh klien dari konsol terpisah.

### Fitur utama menurut siapa yang memakainya

| Untuk | Yang bisa dilakukan |
|---|---|
| **Kasir** | POS berkategori dengan varian & modifier · pembayaran tunai/QRIS/transfer, termasuk **split bill** · **tagihan terbuka (open bill)** yang tinggal di topbar dan bisa dilunasi dari halaman kasir mana pun · **identitas pesanan** (nama pelanggan / nomor meja / kode panggil otomatis, satu mode dipilih owner) · struk layar & **cetak thermal ESC/POS** · **sesi kas** buka–tutup dua langkah dengan selisih · **penjualan offline (PWA)** yang tersinkron saat jaringan kembali · **papan antrian dapur** untuk outlet ber-dapur · **saran jual** yang muncul di layar saat pelanggan masih berdiri di depan meja |
| **Pemilik usaha** | Dashboard omzet, tren, produk terlaris, dan ringkasan langganan · katalog produk–varian–kategori–modifier · **stok**: restock, penyesuaian, riwayat per varian, mutasi, badge stok menipis / mendekati kedaluwarsa / tak bergerak · laporan harian, laporan saran jual, riwayat transaksi, rekap kas · **edit & void transaksi** dengan stok terkoreksi dan jejak audit · **staf & role modul (RBAC)** · **Analisis AI** atas datanya sendiri · **token MCP** untuk menyambungkan AI client miliknya sendiri · pengaturan mode & fitur outlet · halaman langganan & tagihan |
| **Pemilik layanan (SaaS)** | Konsol terpisah di `/platform`: daftar klien dengan **rincian bertab** (Ikhtisar · Langganan · Tagihan · Kapabilitas · Omzet) · langganan & tagihan menyatu, verifikasi/tolak bukti bayar, atur batas pengguna beralasan · **paket** dan **aturan harga berkriteria** yang bisa diubah tanpa deploy · data omzet klien jalur Harga Adaptif · **jejak audit** · akun staf platform dengan modul terbatas |
| **Sistem lain** | API self-order (`POST /api/v1/orders`) untuk n8n/Telegram/QR meja · webhook Xendit · API mobile v1 lengkap (login, produk, transaksi, sesi kas) · **MCP server** read-only untuk AI client pemilik toko |

### Enam hal yang membedakannya

1. **Konsol SaaS-nya dirancang untuk tidak melihat terlalu banyak.** Daftar klien menampilkan **kelompok omzet, bukan angka rupiah persis**. Angka persis punya halamannya sendiri, hanya untuk klien yang sudah menyetujui jalur Harga Adaptif, dan **setiap kunjungan ke sana tercatat sebagai kejadian `sensitive` tanpa deduplikasi**. Ini keputusan produk, bukan keterbatasan teknis — dan paling meyakinkan bila diperagakan langsung (lihat Babak 4).
2. **Gerbangnya di server, bukan di menu.** Modul yang dicabut dari sebuah role bukan cuma hilang dari navigasi; mengetik URL-nya langsung tetap ditolak. Hal yang sama berlaku untuk kapabilitas outlet dan keadaan langganan — dan gerbangnya terpasang di **setiap** pintu: web, API self-order, job antrean, dan MCP.
3. **Harga bisa berubah tanpa deploy.** Aturan harga berupa baris berkriteria (`dimensi` + `operator` + `nilai`), bukan kolom tetap. Empat dimensi tersedia: omzet bulanan, jumlah transaksi/bulan, pengguna aktif, dan tipe usaha. Kategori harga baru cukup ditulis dari panel. Tiap tagihan **membekukan konteks harganya**, jadi tagihan lama tetap bisa dijelaskan setelah aturannya diganti.
4. **Kasirnya tetap hidup saat jaringan mati.** Katalog, modifier, dan indeks saran jual ikut ter-snapshot ke perangkat; penjualan masuk ke outbox lalu tersinkron dengan penjaga idempotensi (`client_uuid`) dan halaman **Koreksi Offline** untuk yang butuh keputusan manusia.
5. **AI-nya membaca data usaha itu sendiri, dua arah.** Owner bisa meminta analisis di dalam aplikasi (dijalankan sebagai job, berkuota harian), **atau** menerbitkan token MCP dan menyambungkan Claude Desktop miliknya sendiri ke datanya — memakai kuota AI miliknya, bukan kuota aplikasi.
6. **Sinyal stok berubah jadi penjualan.** Barang yang mendekati kedaluwarsa atau tak bergerak sebulan tidak berhenti sebagai badge di dashboard owner: ia muncul sebagai saran jual di layar kasir, bersama saran add-on dan naik-ukuran. Nasib tiap saran dicatat, dan laporannya memisahkan **conversion rate** dari **offer rate** supaya angkanya tidak bisa dikelabui.

### Model langganan, singkatnya

Masa coba **30 hari** → `grace` **hanya-baca 30 hari** → `suspended`. Di keadaan `grace` halaman tetap terbuka dan data lama tetap bisa diunduh; hanya permintaan yang **mengubah** data yang ditolak. Halaman `/langganan` dan logout **selalu** terbuka — menutup jalan keluar berarti tenant tak akan pernah bisa keluar, termasuk dengan membayar.

Dua jalur harga: **Harga Tetap** (tarif paket, tidak perlu membuka data penjualan) dan **Harga Adaptif** (tarif mengikuti kelompok omzet, dan karena itu menuntut persetujuan berversi untuk membuka angkanya). Perpindahan jalur dibatasi minimum tiga bulan sekali, persetujuannya bisa dicabut kapan saja, dan retensi data omzetnya 24 bulan.

### Yang sudah jadi vs yang belum

**Sudah jadi dan bisa diperagakan:** semua yang tertulis di tabel di atas. **Belum ada:** 2FA akun platform, penagihan otomatis di akhir masa coba (tagihan masih diterbitkan pemilik SaaS dari panel), payment gateway sungguhan, dan BYOK (kunci AI milik owner sendiri). Rinciannya di [§8](#8-batasan-yang-perlu-diketahui-sebelum-demo) — bacalah sebelum menjanjikan apa pun.

---

## 2. Dua Lapis Autentikasi

Sistem ini punya **dua tabel pengguna yang benar-benar terpisah**, bukan satu tabel dengan flag peran:

| Lapis | Tabel | Halaman masuk | Untuk siapa |
|---|---|---|---|
| **Tenant** | `users` | `/login` | Pemilik usaha & stafnya — POS, produk, stok, laporan, AI |
| **Platform** | `platform_users` | `/platform/login` | Pemilik SaaS — kelola klien, langganan, tagihan, paket & aturan harga, audit |

Akun platform **sengaja tidak punya `tenant_id`** dan tidak memakai spatie/permission; izinnya dikelola lewat tabel tersendiri `platform_user_modules`. Konsekuensi praktisnya saat demo: **jangan mencoba masuk `/platform` dengan `owner@sapi.test`** — tidak akan bisa, dan itu memang desainnya. Sebaliknya, tamu yang sesinya habis di area `/platform` diarahkan ke `platform.login`, bukan ke halaman masuk tenant.

Satu cacat pengalihan yang masih terbuka menyangkut arah sebaliknya — lihat `[BL-043]` di [§8](#satu-cacat-pengalihan-yang-bisa-muncul-saat-demo). Pakailah jendela peramban bersih untuk masuk ke `/platform`.

---

## 3. Kredensial Demo

Semua kata sandi di bawah: **`password`**

### Platform Console — `/platform/login`

| Email | Kata sandi | Keterangan |
|---|---|---|
| `platform@sapi.test` | `password` | `is_owner = true` → akses penuh semua modul platform |

Akun ini dibuat oleh `PlatformUserSeeder`, yang **tidak dipanggil dari `DatabaseSeeder`** — harus dijalankan tersendiri (lihat [§5](#5-menyiapkan--menyegarkan-data-demo)).

Kredensialnya bisa dialihkan lewat `.env`: `PLATFORM_ADMIN_EMAIL`, `PLATFORM_ADMIN_PASSWORD`, `PLATFORM_ADMIN_NAME`.

### Tenant 1 — Kopi Nusantara (`kopi-nusantara`)

| Email | Kata sandi | Peran | Role RBAC |
|---|---|---|---|
| `owner@sapi.test` | `password` | owner | semua (owner tidak dibatasi modul) |
| `kasir@sapi.test` | `password` | cashier | **"Kasir + Gudang"** → `pos`, `cash_drawer`, `stock` |
| `kasir2@sapi.test` | `password` | cashier | **"Kasir"** → `pos`, `cash_drawer` |
| `gudang@sapi.test` | ⚠️ tidak diketahui | cashier | "Kasir + Gudang" |

`kasir2@sapi.test` ditambahkan ke `DatabaseSeeder` pada 2026-08-01 — ia **hanya muncul setelah `migrate:fresh --seed`**, bukan dari menjalankan ulang seeder di atas basis data yang sudah terisi. Ia sengaja ada: dua kasir dengan role berbeda membuat pembatasan modul terlihat, dan membuat sesi kas per-orang punya arti.

`gudang@sapi.test` dibuat manual lewat UI **Staf** pada 2026-07-21, bukan dari seeder — kata sandinya tidak ada di kode mana pun. Kalau perlu dipakai demo, setel dulu:

```bash
php artisan tinker --execute 'App\Models\User::withoutGlobalScopes()->where("email","gudang@sapi.test")->first()->update(["password"=>bcrypt("password")]);'
```

### Tenant 2 — Kopi Story (`kopi-story`)

| Email | Kata sandi | Peran |
|---|---|---|
| `owner@kopistory.test` | `password` | owner |
| `kasir@kopistory.test` | `password` | cashier |

---

## 4. Isi Data Tiap Tenant

Angka di bawah adalah keadaan per **2026-08-01**, setelah penyegaran data.

| | **Kopi Nusantara** (t1) | **Kopi Story** (t2) |
|---|---|---|
| Karakter | Sampel kecil, untuk uji fitur | Studi kasus realistis (gaya kedai kopi susu kekinian) |
| Seeder | `DatabaseSeeder` + `DemoTransactionSeeder` | `CafeStudyCaseSeeder` |
| Produk / varian | 4 / 6 | 20 / 36 |
| Kategori | Kopi, Non-Kopi, Makanan | Kopi Susu, Coffee, Non-Coffee, Tea, Pastry & Snack |
| Transaksi | ±3.360 (sejak 12 Apr 2026) | ±4.475 (sejak 1 Jan 2026) |
| Metode bayar | Cash, QRIS, Transfer BCA | Cash, QRIS, Debit Card |
| Modifier | Temperature (wajib), Sugar Level, Add-ons | Sugar Level, Add-ons (+Boba) |
| Sesi kas | 1 sesi (masih terbuka — lihat catatan) | 213 sesi harian, lengkap dengan selisih |
| Role RBAC kustom | ✅ "Kasir + Gudang" & "Kasir" | — |
| Staf | 3–4 (tergantung apakah sudah `migrate:fresh`) | 2 (owner + kasir) |
| Mode identitas pesanan | `table` (nomor meja) | `none` |
| Jalur harga | **Harga Adaptif** (`subsidized`), sudah menyetujui dokumennya | **Harga Tetap** (`normal`) |

**Rekomendasi: pakai Kopi Story sebagai panggung utama.** Datanya membentang 7 bulan, omzet harian ditarget Rp1–2 juta (akhir pekan lebih tinggi), jam sibuk dimodelkan dengan bobot per jam, restock bulanan tercatat sebagai `StockMovement`, dan sesi kas hariannya sengaja disebar tiga kasus — **pas (±55%), minus (±23%), plus (±22%)** — supaya demo rekonsiliasi kas punya bahan nyata.

Pakai **Kopi Nusantara** untuk demo RBAC, identitas pesanan (mode nomor meja sudah menyala), dan jalur **Harga Adaptif** di halaman langganan.

Tiga catatan kecil yang mudah mengejutkan:

- **Kopi Nusantara punya satu sesi kas yang masih terbuka sejak 2026-07-27.** Riwayat transaksi kasir dibatasi ke sesi yang sedang berjalan, jadi kasir yang masuk akan melihat riwayat sejak tanggal itu dan angka rekonsiliasinya melebar. Tutup dulu sesi itu sebelum demo, atau pakai Kopi Story untuk babak kasir.
- **Dua hari di Kopi Nusantara isinya tipis** — 27 Juli (1 transaksi) dan 30 Juli (3 transaksi). Itu transaksi asli hasil uji coba lewat UI, dan seeder sengaja tidak menyentuh hari yang sudah ada isinya (lihat [§5](#5-menyiapkan--menyegarkan-data-demo)). Di grafik 7 hari terakhir keduanya tampak sebagai lekukan. Bila mengganggu, hapus transaksi kedua hari itu lalu jalankan ulang seeder-nya.
- **Croissant Plain di Kopi Nusantara** diberi `expiry_date` H+3 dari waktu seed, aslinya untuk memperlihatkan badge *near-expiry*. Kalau seed sudah lama, tanggal itu terlewat dan badge-nya tampil sebagai **kedaluwarsa**, bukan "mendekati".

---

## 5. Menyiapkan & Menyegarkan Data Demo

### Menjalankan aplikasi

```bash
composer run dev
```

Perintah ini menyalakan tiga proses sekaligus: `artisan serve`, `queue:listen`, dan `npm run dev`.

**Antrean wajib hidup.** Analisis AI berjalan sebagai job (`RunAiAnalysisJob`); tanpa worker, hasil analisis tidak akan pernah muncul dan demo tampak menggantung. Kalau `.env` baru diubah, **worker harus di-restart** — proses lama masih memegang konfigurasi lama.

Port di-pin **8001** lewat `.claude/launch.json`. Ini disengaja: `artisan serve` membaca `SERVER_PORT` dan akan kembali ke 8000 kalau dibiarkan otomatis, lalu bentrok dengan proyek lain di mesin yang sama.

### Menyegarkan data agar sampai hari ini

Ini langkah yang **paling mudah terlupakan dan paling terlihat**. Kedua seeder transaksi menyemai sampai `now()` pada saat dijalankan. Kalau seed terakhir sudah berminggu-minggu lalu, kartu "Omzet Hari Ini", "7 Hari Terakhir", dan sesi kas hari ini akan **kosong** — tepat di halaman pertama yang dilihat penonton.

Sejak **2026-08-01** kedua seeder transaksi **hanya mengisi hari yang benar-benar kosong**. Menjalankannya ulang menjelang demo menambal jarak sejak seed terakhir tanpa menggandakan omzet, dan tanpa membuang transaksi yang baru saja Anda buat lewat UI. Jadi cukup dua perintah ini, kapan pun, sesering apa pun:

```bash
php artisan db:seed --class=DemoTransactionSeeder --no-interaction
```

```bash
php artisan db:seed --class=CafeStudyCaseSeeder --no-interaction
```

Keduanya melaporkan berapa hari diisi dan berapa dilewati. `CafeStudyCaseSeeder` juga menambahkan restock bulanan hanya untuk bulan yang belum punya restock, dan membuat sesi kas hanya untuk hari yang penjualannya baru saja ia buat — hari yang sudah berisi tidak disentuh, supaya angka kasnya tetap cocok dengan penjualan tunai yang sudah tercatat di sana.

> Konsekuensi yang perlu diketahui: hari yang isinya **tipis** (misalnya satu transaksi hasil uji coba) tetap dianggap terisi dan tidak ditambal. Untuk data yang benar-benar rapi, jalurnya reset bersih di bawah.

### Reset bersih total

```bash
php artisan migrate:fresh --seed && php artisan db:seed --class=PlatformUserSeeder && php artisan db:seed --class=CafeStudyCaseSeeder && php artisan db:seed --class=DemoTransactionSeeder
```

Urutannya penting, dan `migrate:fresh` bukan pilihan gaya:

- `DatabaseSeeder` memakai `Tenant::create()`, bukan `firstOrCreate` — **tidak idempotent**. Menjalankannya di atas database yang sudah terisi akan gagal karena slug duplikat. Ini juga satu-satunya jalan mendapatkan `kasir2@sapi.test`.
- `PlatformUserSeeder` tidak ikut terpanggil oleh `--seed`; harus disebut sendiri.
- `DemoTransactionSeeder` bergantung pada produk & metode bayar milik `kopi-nusantara`, jadi harus setelah `DatabaseSeeder`.

### Ringkasan seeder

| Seeder | Isi | Aman dijalankan ulang? |
|---|---|---|
| `PermissionCatalogSeeder` | 7 permission modul tenant (global) | ✅ |
| `DatabaseSeeder` | Tenant Kopi Nusantara + owner + 2 kasir + menu + modifier + metode bayar + dua role contoh | ❌ gagal, harus `migrate:fresh` |
| `DemoTransactionSeeder` | Sampai 90 hari transaksi untuk Kopi Nusantara | ✅ hanya mengisi hari kosong |
| `CafeStudyCaseSeeder` | Tenant Kopi Story lengkap: menu, transaksi sejak 1 Jan 2026, restock bulanan, sesi kas harian | ✅ hanya mengisi hari & bulan kosong |
| `PlatformUserSeeder` | Akun pemilik platform | ✅ |
| `ProductionSeeder` | Tenant "default" + akun admin dari `ADMIN_EMAIL`/`ADMIN_INITIAL_PASSWORD` — **untuk produksi, bukan demo** | ✅ |

---

## 6. Kondisi Langganan & Penagihan

Kedua tenant demo berada di plan **"Dasar"** dengan `trial_ends_at = null`. Yang terakhir disengaja — demo dianggap sudah berlangganan supaya tidak kedaluwarsa sebulan setelah seed dijalankan dan merusak demo di kemudian hari.

| | Kopi Nusantara | Kopi Story |
|---|---|---|
| Jalur harga | **Harga Adaptif** (`subsidized`), persetujuan v1 aktif | **Harga Tetap** (`normal`) |
| Batas pengguna (`seats`) | 4 | 2 |
| Periode berjalan | 24 Jul – 24 Agu 2026 | 24 Jul – 24 Agu 2026 |

Plan "Dasar" bawaan bernilai `base_price = 0`, `included_seats = 1`, `extra_seat_price = 0`. Akibatnya **semua tagihan Rp0 dan tabel `invoices` kosong**. Paket **Premium 1/2/3** sudah punya angka nyata (Rp100k/150k/200k, seat tambahan Rp15.000/12.500/10.000), tapi belum ada jalur yang memindahkan tenant ke sana — itu `[BL-046]`. Kalau demo perlu memperagakan alur tagihan dan verifikasi bukti bayar, isi dulu harga paketnya dari `/platform/pricing-rules` (bagian **Paket**), lalu terbitkan tagihan dari tab **Tagihan** pada rincian tenant.

**Aturan harga jalur Harga Adaptif** sudah terisi empat kelompok, semuanya berdimensi omzet bulanan:

| Label | Rentang omzet bulanan | Harga |
|---|---|---|
| A | Rp0 – 2 juta | Rp10.000 |
| B | Rp2 – 5 juta | Rp25.000 |
| C | Rp5 – 15 juta | Rp50.000 |
| D | di atas Rp15 juta | Rp100.000 |

Aturan ini **berkriteria**, bukan kolom tetap: satu aturan bisa punya banyak syarat (`dimensi` + `operator` + `nilai`) dan sebuah `priority`. Selain omzet bulanan, tersedia dimensi **jumlah transaksi/bulan**, **pengguna aktif**, dan **tipe usaha**. Kelompok harga baru cukup ditulis dari panel — tanpa migration, tanpa deploy.

Kopi Nusantara **sudah berada di jalur Harga Adaptif dan sudah menyetujui dokumennya**, tapi tabel `tenant_monthly_metrics` masih kosong karena job bulanan (`subscriptions:compute-revenue`, tanggal 1 pukul 04:00) belum pernah berjalan untuknya. Untuk mengisi metriknya sebelum demo:

```bash
php artisan subscriptions:compute-revenue
```

Halaman `/langganan` milik tenant jalur Harga Tetap juga menampilkan **perkiraan tarif adaptif** — omzet bulan lalu miliknya sendiri, kelompok tarif yang ia masuki, dan perbandingan "tarif sekarang → perkiraan adaptif". Perkiraan itu dihitung saat itu juga dan **tidak pernah disimpan**: tidak ada baris `tenant_monthly_metrics` yang lahir karena pemilik toko membuka halamannya sendiri.

### Aturan yang mengikat dan sebaiknya disebutkan saat demo

- **Seat = pengguna aktif**, tetapi tagihan mengikuti `seat_high_water` (puncak dalam periode). Menonaktifkan staf di akhir bulan tidak menghemat biaya — ini disengaja. Konsekuensi yang belum terselesaikan: kursi **tidak pernah bisa turun** (`[BL-049]`).
- **Siklus hidup:** trial 30 hari → `grace` **hanya-baca** 30 hari → `suspended` (angkanya di `config/subscription.php`). Saat `suspended`, seluruh item sidebar owner selain **Langganan & Tagihan** dirender mati, dan pintasan Kasir di topbar disembunyikan — navigasinya jujur mengaku terkunci, bukan menyembunyikan diri.
- **Daftar tenant di panel platform menampilkan kelompok omzet, bukan angka rupiah persis.** Angka persis hanya ada di tab **Omzet** pada rincian tenant, dan setiap kunjungan tercatat sebagai kejadian `sensitive` tanpa deduplikasi. Membuka rincian tenant sendiri tercatat sebagai kejadian **rutin** (terdeduplikasi).
- **Mencabut persetujuan Harga Adaptif** menghapus metrik seketika, tapi tarifnya tetap berlaku sampai akhir periode.
- **Pindah jalur harga** dibatasi minimum tiga bulan sekali.
- **Retensi data omzet** 24 bulan.
- **Tab Kapabilitas di panel platform hanya membaca.** Pemilik SaaS bisa *mengetahui* fitur apa yang menyala di sebuah outlet, tapi tidak bisa menyalakan atau mematikannya — cara kerja usaha orang bukan milik penyedia layanannya. Begitu pula jenis usaha: editornya ada di Owner → Pengaturan, panel platform hanya membacanya.

### Perhatian saat demo

Kopi Nusantara punya **`seats = 4`**. Menambah staf di luar batas itu akan memicu alur upgrade seat — yang hari ini menerbitkan tagihan **Rp 0** dan tetap meminta bukti transfernya (`[BL-049]`). Itu bisa jadi bahan cerita yang jujur soal "harga belum ditetapkan", atau kejutan yang tidak enak. Tentukan dulu apakah Anda sengaja.

---

## 7. Alur Demo yang Disarankan

Urutan ini bergerak dari yang paling konkret ke yang paling abstrak, dan tidak pernah memerlukan mundur untuk menyiapkan data. Totalnya sekitar 25 menit.

### Babak 1 — Kasir (7 menit) · `kasir@kopistory.test`

1. Masuk sebagai kasir → langsung diminta **buka sesi kas** (kas awal Rp500.000).
2. Buka **POS**: pilih produk berkategori, pilih varian, terapkan modifier (Sugar Level, Add-ons berbayar seperti Extra Shot/Boba).
3. **Saran jual** muncul di strip atas keranjang — add-on yang paling sering menyertai item, barang tertekan (mendekati kedaluwarsa / tak bergerak sebulan), dan tawaran naik ukuran. Tunjukkan bahwa "Diterima" langsung memasukkan barangnya ke keranjang, dan × berarti **ditolak pelanggan**, bukan sekadar menutup.
4. Bayar tunai → perlihatkan perhitungan kembalian; ulangi dengan QRIS; lalu satu transaksi **split bill** dua metode.
5. Buat satu **tagihan terbuka**, lalu lunasi lewat **tombol ber-badge di topbar** — tekankan bahwa daftarnya ikut ke halaman kasir mana pun, tidak lagi terkubur di dalam keranjang.
6. **Edit transaksi** yang sudah selesai → tunjukkan stok ikut terkoreksi dan perubahannya tercatat.
7. **Tutup kas** dua langkah: ringkasan sistem lebih dulu, baru konfirmasi hitungan fisik. Masukkan angka yang sengaja berbeda untuk memperlihatkan selisih.

### Babak 2 — Mode & Fitur Outlet (3 menit) · `owner@sapi.test`

Bagian ini singkat tapi menjelaskan banyak: satu aplikasi, banyak bentuk usaha.

1. **Pengaturan → Mode & Fitur Outlet.** Tunjukkan tiga kapabilitas per outlet: **papan antrian dapur**, **self-order**, dan **AI**. Nyalakan papan antrian.
2. Tunjukkan **identitas pesanan**: satu mode dipilih owner — tidak dipakai / nama pelanggan / nomor meja / kode panggil otomatis. Kopi Nusantara memakai **nomor meja**; ganti ke **kode panggil**, lalu buat satu transaksi di POS dan perlihatkan nomornya tercetak di struk dan muncul di **papan `/cashier/queue`**.
3. Sebutkan bahwa **saran jual bisa diwajibkan** — tiap saran harus dijawab sebelum tombol BAYAR hidup — dan bahwa laporannya sengaja punya dua angka (`conversion_rate` dan `offer_rate`) supaya menyalakan mode wajib tidak bisa memoles statistiknya sendiri.

### Babak 3 — Pemilik Usaha (7 menit) · `owner@kopistory.test`

1. **Dashboard** — omzet, transaksi, produk terlaris, tren, plus **ringkasan langganan** dengan tagihan terbuka. (Pastikan data sudah disegarkan; lihat [§5](#5-menyiapkan--menyegarkan-data-demo).)
2. **Laporan Harian** dengan filter tanggal, dan **Rekap Kas** — di sini 213 sesi Kopi Story terbayar: pas, minus, dan plus lengkap dengan catatan alasannya.
3. **Saran Jual** (`/owner/reports/upsell`) — konversi per jenis saran.
4. **Stok** — restock, penyesuaian, riwayat per varian, dan mutasi stok. Tunjukkan bahwa restock bulanan Januari–hari ini semuanya terekam.
5. **Analisis AI** — kirim satu analisis, tunjukkan job diproses lalu hasilnya muncul. Batas **5 analisis/hari per tenant** (bawaan platform; paket berbayar bisa menaikkannya).
6. **Pengaturan → Token MCP** — jelaskan bahwa owner bisa menyambungkan Claude Desktop ke data bisnisnya sendiri, dan itu memakai kuota AI *milik owner*, bukan kuota aplikasi.
7. **Langganan & Tagihan** — halaman ini kini memakai cangkang yang sama seperti halaman owner lainnya, dengan warna per jalur harga (Masa Coba ungu, Harga Tetap biru, Harga Adaptif hijau) dan jejak persetujuan yang menyebut versi, tanggal, serta nama penyetujunya.

### Babak 4 — RBAC (3 menit) · `owner@sapi.test` (Kopi Nusantara)

1. **Role & Modul** — tunjukkan "Kasir + Gudang" (`pos`, `cash_drawer`, `stock`) berdampingan dengan "Kasir" (`pos`, `cash_drawer`).
2. Masuk sebagai `kasir2@sapi.test` di jendela lain → menu **Stok hilang dari navigasi**, dan mengetik `/owner/stock` langsung tetap **ditolak**. Poin pentingnya: gerbangnya di server, bukan cuma menyembunyikan menu.

> `kasir2@sapi.test` hanya ada setelah `migrate:fresh --seed` ([§3](#3-kredensial-demo)). Tanpa itu, tetapkan role "Kasir" ke `gudang@sapi.test` dari halaman **Staf** lebih dulu — hasilnya sama, cuma perlu satu langkah persiapan.

> Perhatikan arahnya: peragakan dengan modul `stock`, jangan dengan mencabut `pos`. Rute kasir sengaja tidak digerbang per modul — lihat [§8](#satu-lubang-rbac-yang-sengaja-dibiarkan).

### Babak 5 — Platform Console (7 menit) · `platform@sapi.test`

Gunakan **jendela peramban yang bersih** (lihat `[BL-043]` di §8).

1. **Dashboard** platform, lalu **Daftar Tenant** — daftarnya kini benar-benar daftar: nama, pemilik, status, dan tombol **Lihat detail**.
2. **Rincian tenant** — lima tab: **Ikhtisar · Langganan · Tagihan · Kapabilitas · Omzet**. Tekankan bahwa tab Kapabilitas **hanya membaca**.
3. **Langganan & Tagihan** — keadaan tiap tenant, seat, periode; ubah batas pengguna dan tunjukkan bahwa **alasannya wajib diisi**.
4. **Paket & Aturan Harga** — empat kelompok A–D berdimensi omzet, plus paket Dasar/Premium dengan batas per paket. Terbitkan satu aturan baru dengan tanggal berlaku ke depan untuk memperlihatkan bahwa harga bisa berubah tanpa deploy.
5. **Omzet** — buka tab Omzet satu tenant, lalu segera pindah ke **Log Audit** dan tunjukkan kunjungan tadi sudah tercatat sebagai `sensitive`, sementara membuka rincian tenant hanya tercatat sebagai kejadian rutin. **Ini demo paling meyakinkan di babak ini.**
6. **Kelola Akun Platform** — buat staf platform yang hanya diberi modul `tenants`, masuk sebagai dia, tunjukkan menu lain menghilang. Tunjukkan juga bahwa **manajemen akun platform sendiri tidak ada di daftar modul yang bisa diberikan** — dijaga penanda `is_owner`, supaya staf platform tak bisa mencentangkan modul sensitif untuk dirinya sendiri.

### Babak 6 — Isolasi Tenant (2 menit, penutup)

Masuk sebagai `owner@sapi.test` dan `owner@kopistory.test` bersebelahan: produk, transaksi, laporan, semuanya terpisah total. Sebutkan bahwa isolasi ini dijaga oleh berkas uji tersendiri (`tests/Feature/TenantIsolation`, `PlatformIsolationTest`), bukan hanya oleh kedisiplinan menulis query.

### Bahan tambahan bila ada waktu

- **`/dokumentasi`** — hub dokumentasi publik dua jalur: **Panduan Penggunaan** (untuk pemilik usaha & kasir) dan **Dokumentasi Developer**. Bagus untuk ditinggalkan sebagai tautan setelah demo.
- **`/api-docs`** — referensi API mobile.
- **Penjualan offline (PWA)** — matikan jaringan di DevTools, buat transaksi, nyalakan lagi, tunjukkan sinkronisasinya dan halaman **Koreksi Offline** milik owner.

---

## 8. Batasan yang Perlu Diketahui Sebelum Demo

Daftar ini ada supaya Anda tidak dikejutkan di depan penonton, dan supaya jawaban Anda jujur kalau ditanya.

### Satu cacat pengalihan yang bisa muncul saat demo

`[BL-043]` — **akun platform yang sesinya masih hidup lalu membuka `/platform/login` akan mendarat di landing publik `/`**, bukan di `/platform`. Terbukti langsung di peramban 2026-08-01. Gejala kedua yang bersyarat: bila peramban itu sebelumnya pernah menyentuh area tenant dalam keadaan keluar, login platform bisa berakhir di halaman masuk **tenant** — login-nya berhasil dan tercatat di audit, tapi layarnya salah.

**Penangkalnya saat demo: pakai jendela peramban bersih (atau penyamaran) untuk masuk ke `/platform`, dan jangan menekan Back ke halaman login setelah masuk.**

### Surel tidak benar-benar terkirim

`MAIL_MAILER=log`. Akun demo sudah `email_verified_at` terisi jadi bisa langsung masuk. Tapi:

- **Mendaftar akun baru saat demo** → tautan verifikasi hanya muncul di `storage/logs/laravel.log`, harus digali manual.
- **Kedua alur reset kata sandi** (tenant dan platform) tidak berfungsi hidup.

Kalau demo perlu menyentuh salah satu alur ini, siapkan pengirim surel sungguhan lebih dulu.

### Belum terpasang

| Hal | Status | ID backlog |
|---|---|---|
| Penagihan otomatis di akhir masa coba | Siklus hidup langganan **berpindah keadaan** (trial → grace → suspended) tapi **tidak pernah menerbitkan tagihan**. Satu-satunya cara tagihan bulanan lahir hari ini adalah pemilik SaaS mengetiknya dari panel. | `[BL-044]` |
| Tarif paket "Dasar" | `base_price = 0` — semua tagihan Rp0 dan `invoices` kosong. Premium sudah punya angka tapi belum ada jalur pindah paket ke sana. | `[BL-041]`, `[BL-046]` |
| Payment gateway | Belum ada. Alurnya: tenant unggah bukti transfer → pemilik SaaS verifikasi manual. Membayar belum memulihkan akses dengan sendirinya. | `[BL-045]` |
| Pita peringatan langganan di luar dashboard | Keadaan `grace`/`suspended` sudah ditegakkan di server, tapi peringatannya hanya di kartu dashboard owner. Kasir yang membuka POS tidak melihat apa-apa sampai simpanannya ditolak. | `[BL-045]` |
| 2FA akun platform | Belum ada. Sudah ada rate limit login, jejak audit, dan pemulihan kata sandi yang tidak membocorkan keberadaan akun — tapi tetap satu faktor. | `[BL-013]` |
| BYOK (owner memakai kunci AI sendiri) | Kolomnya sudah ada (`tenants.ai_provider`, `ai_api_key`, `ai_model`) tapi alurnya ditunda; kedua tenant memakai kunci bersama aplikasi. | — |
| Printer Bluetooth & jaminan transaksi offline penuh | Menabrak batas PWA, butuh lapisan native Android. | `[BL-016]` |
| Kursi bisa turun | `seat_high_water` hanya naik. Satu kasir yang pernah dipekerjakan sebulan terus menaikkan tarif jalur Adaptif selamanya. | `[BL-049]` |

**Papan antrian dapur SUDAH ADA** sejak 2026-07-29 (`/cashier/queue`) — catatan lama yang mengatakan "tidak ada halamannya" sudah tidak berlaku. Yang perlu diingat: papannya **mati secara bawaan** dan dinyalakan per outlet dari Pengaturan, dan **tidak aktif saat perangkat offline** — penjualan hasil sinkronisasi tidak menyusul masuk papan. Itu batas yang diputuskan sadar, bukan bug.

### Satu lubang RBAC yang sengaja dibiarkan

Rute kasir (`/cashier/*`) **tidak** digerbang per modul — hanya `role:cashier,owner`. Akibatnya kasir yang rolenya hanya berisi `stock` tetap bisa mengetik `/cashier/pos` di peramban dan berjualan.

Ini keputusan, bukan kelalaian: `pos` dan `cash_drawer` diperlakukan sebagai **penanda menu**, bukan gerbang rute, dan menutupnya akan mengubah perilaku staf yang sudah ada — termasuk staf yang dibuat lewat pilihan "Tanpa role (POS saja)" di form tambah staf.

Konsekuensi untuk demo: **peragakan RBAC dengan modul `stock`, jangan dengan mencabut `pos`.** Modul `stock`, `products`, `reports`, `payment_methods`, dan `ai_analysis` benar-benar digerbang di server — mengakses URL-nya langsung akan ditolak, bukan hanya menunya hilang. Arah sebaliknya tidak akan meyakinkan siapa pun yang mencoba mengetik URL.

### Penjadwal harus hidup

Kalau demo menyentuh siklus hidup langganan, perhitungan omzet, pemangkasan log audit, atau peringatan login gagal, `schedule:run` harus aktif. Jadwalnya: `platform:prune-audit-logs` (03:10 harian), `subscriptions:advance-lifecycle` (03:30 harian), `platform:alert-failed-logins` (tiap jam), `subscriptions:compute-revenue` (tanggal 1, 04:00), `subscriptions:prune-metrics` (tanggal 1, 04:30).

### Kunci AI

`AI_FREE_TIER_KEY` sudah terisi di `.env` dengan provider **SumoPod**, batas **5 analisis per hari per tenant** (bawaan platform di `config/ai.php`; paket berbayar bisa menaikkannya lewat `plans.limits.ai_daily`). Kalau kuota harian sudah terpakai saat mencoba-coba sebelum demo, analisis akan ditolak — jangan menghabiskannya saat gladi bersih.

---

## 9. Peta Halaman Lengkap

### Pemilik Usaha — `/owner/*`

Dashboard · Kategori · Produk (+ varian) · Stok (restock, penyesuaian, riwayat per varian, mutasi) · Modifier · Laporan Harian · **Saran Jual** · Transaksi (+ detail, void) · Sesi Kas · **Koreksi Offline** · Metode Pembayaran · **Analisis AI** · Staf · **Role & Modul (RBAC)** · Profil Usaha (mode & fitur outlet, identitas pesanan, jenis usaha, token MCP) · Langganan & Tagihan

### Kasir — `/cashier/*`

POS (+ saran jual, identitas pesanan, split bill) · **Antrian Dapur** (bila kapabilitasnya menyala) · Riwayat Transaksi · Edit Transaksi · Tagihan Terbuka (di topbar, berikut pelunasannya) · Sinkronisasi Offline (PWA) · Sesi Kas (buka, tutup, ringkasan)

### Langganan Tenant — `/langganan`

Status langganan & jalur harga · Perkiraan tarif adaptif · Tambah pengguna · Unggah bukti bayar · Persetujuan jalur harga (`/langganan/persetujuan`) · Cabut persetujuan Harga Adaptif

### Platform Console — `/platform`

Dashboard · Daftar Tenant → **rincian bertab** (Ikhtisar · Langganan · Tagihan · Kapabilitas · Omzet) · Langganan & Tagihan · Paket & Aturan Harga · Log Audit · Kelola Akun Platform

**Modul platform yang bisa diberikan ke staf:** `tenants`, `subscriptions`, `payments`, `pricing_rules` (sensitif), `revenue_data` (sensitif), `audit_logs` (sensitif).

**Modul tenant (RBAC untuk staf):** `pos`, `cash_drawer`, `products`, `stock`, `reports`, `payment_methods` (sensitif), `ai_analysis` (sensitif).

**Kapabilitas per outlet:** `kitchen_queue`, `self_order`, `ai`.

### Publik

`/` landing page · `/dokumentasi` hub dokumentasi dua jalur · `/api-docs` referensi API mobile · `/up` health check

### Antarmuka non-web

API self-order (`POST /api/v1/orders`, `POST /api/v1/upsell/suggestions`, `PATCH /api/v1/orders/{transaction}/fulfillment`) · API mobile v1 (`/api/v1/mobile/*`) · webhook Xendit · MCP server (token diterbitkan owner dari Pengaturan)

---

## Dokumentasi Terkait

- **Garis besar sistem & arsitektur:** [README.md](README.md)
- **Riwayat perubahan:** [docs/CHANGELOG.md](docs/CHANGELOG.md) — mulai dari `## Indeks Entri`, jangan dibaca utuh
- **Isu terbuka & hutang teknis:** [docs/BACKLOG.md](docs/BACKLOG.md)
- **Isu yang sudah selesai:** [docs/BACKLOG-ARCHIVE.md](docs/BACKLOG-ARCHIVE.md)
- **Dokumentasi teknis:** [docs/SAPI_Technical_Doc_v1.1.md](docs/SAPI_Technical_Doc_v1.1.md)
- **Audit keamanan:** [docs/SAPI-Security-Audit_v1.0.md](docs/SAPI-Security-Audit_v1.0.md)
- **Dokumen fase:** `docs/phases-1/`, `docs/phases-2/`
