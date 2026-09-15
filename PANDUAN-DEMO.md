# SAPI — Panduan Demo & Petunjuk Penggunaan

Berkas ini adalah panduan operasional untuk **menjalankan dan mendemokan** SAPI: apa produknya, kredensial akun, isi data contoh, urutan alur demo, dan batasan yang perlu diketahui sebelum bicara di depan penonton. Untuk penjelasan arsitektur yang lebih dalam, lihat [README.md](README.md).

Kalau Anda hanya punya waktu untuk satu bagian, baca [§1](#1-sapi-dalam-satu-halaman). Kalau Anda sudah pernah memakai versi panduan sebelumnya (1 Agustus), baca [§0](#0-yang-berubah-sejak-1-agustus--bahan-bicara) lebih dulu, lalu **daftar periksa di [§5](#5-menyiapkan--menyegarkan-data-demo)** — data demo hari ini punya beberapa jebakan yang tidak ada sebulan lalu.

> **Peringatan:** semua kredensial di berkas ini adalah **kredensial lingkungan lokal/demo**. Jangan pernah dipakai di server sungguhan. `PlatformUserSeeder` sendiri akan menolak berjalan dengan kata sandi bawaan di luar `local`/`testing` — lihat [§8](#8-batasan-yang-perlu-diketahui-sebelum-demo).

**Terakhir diperbarui:** 2026-09-15 (sebelumnya 2026-08-01).

---

## Daftar Isi

0. [Yang Berubah Sejak 1 Agustus — Bahan Bicara](#0-yang-berubah-sejak-1-agustus--bahan-bicara)
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

## 0. Yang Berubah Sejak 1 Agustus — Bahan Bicara

Enam minggu, sekitar 110 entri changelog. Tidak semuanya layak disebut di depan penonton — sebagian besar perbaikan angka, teks, dan performa. Di bawah ini hanya yang **terlihat di layar** atau **mengubah jawaban atas pertanyaan penonton**, dikelompokkan menurut siapa yang merasakannya. Kolom kanan adalah satu kalimat yang bisa langsung diucapkan.

### Kasir

| Yang berubah | Kalimat untuk penonton |
|---|---|
| **Saran jual jadi satu kartu bergiliran di dasar kolom katalog**, menunjuk baris keranjang asalnya ("Dari Espresso - Double"). Yang terlanjur diterima bisa **Batalkan**. Mode wajib mengunci tombol Bayar dengan tulisan "Jawab N saran dulu". | "Kasir menawarkan satu hal pada satu waktu, dan keranjangnya tidak pernah tertutup tawaran." |
| **Baris keranjang bisa diubah** (varian & modifier tercentang duluan), hapus dan Kosongkan selalu minta konfirmasi. Tombol dibesarkan untuk layar sentuh, label muncul lewat tekan-tahan. | "Pelanggan berubah pikiran soal ukuran? Ubah barisnya, tidak perlu pesan ulang." |
| **Nominal tunai tidak terisi sendiri** — kasir mengetik uang yang diterima, atau menekan "Uang Pas" setelah menghitungnya. Tombol uang cepat berwarna seperti lembar rupiah. | "Angka pas baru muncul setelah uangnya benar-benar di tangan." |
| **Foto bukti QRIS/transfer** (bisa diwajibkan per toko), termasuk saat mengedit transaksi. | "Setiap pembayaran non-tunai punya fotonya sendiri." |
| **Pajak (exclusive/inclusive) dan biaya layanan** masuk keranjang, struk, dan laporan. | "Struknya bisa dijumlahkan ulang pelanggan: subtotal, biaya layanan, pajak, total." |
| **Tunda Bayar berumur 24 jam**; lewat itu jadi **kas negatif** yang hanya owner bisa bereskan. | "Tagihan terbuka tidak bisa menggantung selamanya." |
| **Sesi kas "buta"**: angka seharusnya di laci tersembunyi sampai kasir menghitung; membukanya tercatat. Tutup kas punya halaman sendiri. Sesi berumur 24 jam dan ditutup sistem bila lewat. | "Kasir menghitung dulu, baru melihat jawabannya — dan owner tahu kalau ada yang mengintip." |
| **Catat Uang Keluar / Setoran Masuk** dengan alasan wajib dan foto struk opsional; di atas Rp50.000 menunggu persetujuan owner. | "Beli galon dari laci? Tercatat, bukan jadi selisih misterius di akhir shift." |
| Tombol **layar penuh**, **logo usaha** di topbar & struk, keluar selalu ditanya dulu. | — |
| **Barang kedaluwarsa** diberi lencana di kartu produk, dan menjualnya menuntut alasan tertulis (baru 2026-09-15, lihat catatan di bawah). | "Barang basi tidak terjual diam-diam." |

### Pemilik usaha

| Yang berubah | Kalimat untuk penonton |
|---|---|
| **Beranda menjawab dua periode** — omzet & transaksi hari ini dan bulan ini, keempat kartunya bisa ditekan menuju laporannya. Rekap metode bayar punya sakelar Hari Ini / Bulan Ini. | "Setiap angka di beranda bisa ditanya 'dari mana?'." |
| **Penyelamat Stok**: kartu yang menyebut barang tertekan (hampir kedaluwarsa atau tak laku sebulan), modal yang tertahan, dan berapa yang **belum punya potongan otomatis**. Tab Bulan Ini menyebut omzet dari barang tertekan dan **modal hangus**. | "Aplikasi tidak cuma bilang barangnya hampir basi — ia bilang berapa rupiahnya dan apakah sudah ada yang mendorongnya keluar." |
| **Aturan Diskon** (baru): potongan otomatis yang makin dalam mendekati kedaluwarsa, dengan **lantai untung** dari harga modal. Hanya owner yang boleh menembusnya dari kasir, dengan alasan. | "Diskon tidak bisa menjual rugi tanpa ada manusia yang memutuskannya." |
| **Aturan Saran Jual** (baru): owner menulis targetnya sendiri ("beli nasi goreng, tawarkan teh"), dengan tab **Muncul di kasir hari ini** yang menyebut slot mana yang terisi dan kenapa sebuah aturan tidak tampil. | "Owner bisa melihat persis apa yang akan muncul di layar kasir sebelum kasirnya melihat." |
| **Laporan Saran Jual** jadi corong tiga tahap: Tampil → Dijawab kasir → Diterima, dipisah Otomatis vs Aturan Anda. | — |
| **Laporan Bulanan** (baru): dua angka, garis tren, rincian per tanggal, unduhan CSV. Produk terlaris kini dikelompokkan per produk (dulu tiga produk berbeda bisa bergabung jadi satu baris "Hot"). | — |
| **Stok** jadi satu baris per varian, disaring dan dipaginasi di server, dengan kartu status yang bisa ditekan (Habis, Kritis, Hampir Kedaluwarsa, Kedaluwarsa, Penyelamat Stok, …). Katalog Produk bisa dicari. | — |
| **Pengaturan pecah jadi tiga halaman**: Profil & Merek (termasuk logo), **Cara Kerja Sistem**, Integrasi & Kredensial. | — |
| **Analisis AI** diminta menunjuk angka, bukan menasihati; nama varian di hasilnya bisa diklik; sisa kuota terlihat di halaman yang membelanjakannya. | "Hasil AI-nya menyebut barang dan angka milik toko ini, dan barangnya bisa diklik." |
| **Staf** menjawab "orang ini bisa buka apa saja" dengan lencana modul. | — |
| **Link Data untuk AI** (baru 2026-09-15): owner membuat link berumur 7 atau 30 hari, menempelnya bersama prompt ke ChatGPT, Claude, atau Gemini, dan AI itu membaca ringkasan penjualan, profit, dan menu toko. Link bisa dicabut kapan saja dan menyebut kapan terakhir dibuka. Rincian di [§7 · Bahan: AI membaca data toko](#bahan-ai-membaca-data-toko--mcp-dan-link-data). | "Tanpa memasang apa pun, owner bisa bertanya ke AI favoritnya soal tokonya sendiri." |
| **Semua jam mengikuti jam toko (WITA)** — "hari ini" tidak lagi bergeser 8 jam. | — |

### Langganan & penagihan

| Yang berubah | Kalimat untuk penonton |
|---|---|
| **Masa gratis 2 bulan**, lalu tenant dipindah ke **Paid 1** (Rp100.000). Pilihan jalur disodorkan H-14, tagihan pertama terbit H-7. Halaman `/register` menyebut semuanya sebelum orang mendaftar. | "Tidak ada kejutan: lama gratisnya, paket sesudahnya, dan tanggal tagihannya tertulis sebelum tombol Daftar." |
| **Harga Adaptif = diskon dari Paid 1** menurut omzet bulan sebelumnya (90/75/50/25%), tertutup di atas Rp50 juta/bulan. Pengajuannya punya halaman sendiri dan dinilai seketika. | "Warung kecil membayar Rp10.000; begitu omzetnya di atas Rp50 juta, keringanannya selesai." |
| **Masa tenggang bertingkat**: hari 1–14 pemberitahuan halus, 15–19 peringatan mengganggu, 20–30 **hanya layar POS** yang dikunci — laporan dan ekspor tetap terbuka. | "Kasir tidak dimatikan di hari pertama telat bayar; warung yang tak bisa berjualan tak bisa membayar." |
| **Bayar tagihan dalam satu modal** (pilih kanal + kata sandi owner) lewat gateway tiruan, atau halaman instruksi bayar yang lunas sendiri setelah beberapa detik. Keduanya melewati webhook bertanda tangan yang sama dengan penyedia sungguhan nanti. | "Begini rasanya membayar. Uang sungguhannya menunggu penyedia pembayaran." |
| **Kursi tambahan & blok kuota AI** jadi komponen bulanan yang bisa dibeli dan dilepas; kursi yang dibeli di tengah periode ditagih per hari. | — |
| **Tenant yang ditangguhkan punya jalan pulang**: satu tombol yang menerbitkan satu tagihan pemulihan. | — |
| Halaman `/langganan` jadi tiga tab: **Ringkasan · Tagihan · Kapasitas**. | — |

### Pemilik layanan (konsol platform)

| Yang berubah | Kalimat untuk penonton |
|---|---|
| **2FA (TOTP) untuk akun platform**, dengan delapan kode pemulihan, di menu Keamanan Akun. | "Kata sandi yang benar saja tidak cukup untuk masuk ke data semua klien." |
| **Kuota AI jadi kebijakan berjangka** (termasuk promo bertanggal), dengan tombol mengembalikan jatah hari ini — pemakaian hanya terlihat sebagai angka gabungan, bukan per tenant. | — |
| **Pindah paket** dari rincian tenant (wajib beralasan); nominal tagihan manual yang menyimpang dari aturan **wajib beralasan dan alasannya dibaca tenant**. | "Setiap angka yang ditagihkan di luar aturan punya alasan yang dibaca kliennya." |
| **Membuka kunci pajak** tenant: operator membuka kuncinya selama 7 hari, sekali pakai, beralasan, tercatat `sensitive` — tapi tidak pernah mengubah setelan pajaknya sendiri. | "Penyedia layanan boleh membuka pintu, pemilik toko yang memutuskan." |
| Paket tujuan setelah masa gratis dan paket penampung Adaptif ditunjuk dari halaman **Aturan Harga**, tanpa deploy. | — |

### Halaman publik

`/harga` (baru) membacakan kedua jalur tarif dari data yang berlaku. Landing ditulis ulang dari kapabilitas yang nyata: testimoni dan paket karangan dicabut, bagian "Bagaimana Harga Anda Dihitung" dan tangkapan layar asli ditambahkan. Pendaftaran menanyakan **cara berjualan** (warung/kafe menetap, gerai acara & bazar, toko retail, jasa) dan menyalakan **paket setelan awal** yang terlihat dan bisa diubah sebelum menekan Daftar.

> **Catatan status 2026-09-15:** batch stok per tanggal kedaluwarsa (FEFO) dan gerbang penjualan barang kedaluwarsa (`[BL-111]`, `[BL-108]`) sudah tercatat di changelog hari ini, tetapi **saat panduan ini ditulis kodenya belum di-commit**. Pastikan perubahan itu sudah masuk dan `php artisan migrate` sudah dijalankan sebelum memperagakannya; kalau belum, lewati bagian itu.

---

## 1. SAPI dalam Satu Halaman

**SAPI adalah aplikasi kasir dan stok multi-tenant untuk UMKM, dijual sebagai layanan berlangganan.** Satu pemasangan melayani banyak usaha sekaligus; tiap usaha hanya melihat datanya sendiri. Ada tiga jenis pengguna, dan masing-masing punya aplikasinya sendiri: **kasir** yang berjualan, **pemilik usaha** yang mengelola dan membaca laporan, dan **pemilik layanan (SaaS)** yang mengelola seluruh klien dari konsol terpisah.

### Fitur utama menurut siapa yang memakainya

| Untuk | Yang bisa dilakukan |
|---|---|
| **Kasir** | POS berkategori dengan varian & modifier · **baris keranjang bisa diubah** · pembayaran tunai/QRIS/transfer, termasuk **split bill** dan **foto bukti non-tunai** · **pajak & biaya layanan** · **Tunda Bayar** yang tinggal di topbar dan berumur 24 jam · **identitas pesanan** (nama pelanggan / nomor meja / kode panggil) · struk layar & **cetak thermal ESC/POS** dengan logo usaha · **sesi kas buta** dengan catatan uang keluar/masuk laci · **penjualan offline (PWA)** · **papan antrian dapur** · **saran jual** satu kartu bergiliran · peringatan barang kedaluwarsa |
| **Pemilik usaha** | Beranda dua periode dengan **Penyelamat Stok** · katalog produk–varian–kategori–modifier yang bisa dicari · **stok** per varian dengan kartu status, restock, penyesuaian, riwayat, mutasi · **Aturan Diskon** dengan lantai untung · **Aturan Saran Jual** dan laporannya · laporan harian & **bulanan** (CSV) · riwayat transaksi, sesi kas, persetujuan uang keluar, pembereskan kas negatif · **edit & void transaksi** · **staf & role modul (RBAC)** · **Analisis AI** · **token MCP** · **Link Data untuk AI** · pengaturan tiga halaman (merek, cara kerja sistem, integrasi) · halaman langganan & tagihan |
| **Pemilik layanan (SaaS)** | Konsol terpisah di `/platform` dengan **2FA**: daftar klien dengan **rincian bertab** (Ikhtisar · Langganan · Tagihan · Kapabilitas · Omzet) · pindah paket & terbitkan tagihan beralasan · buka kunci pajak · **paket** dan **aturan harga berkriteria** yang bisa diubah tanpa deploy · **kebijakan kuota AI** · **jejak audit** · akun staf platform dengan modul terbatas |
| **Sistem lain** | API self-order (`POST /api/v1/orders`) untuk n8n/Telegram/QR meja · webhook pembayaran (gateway tiruan hari ini) · webhook Xendit · API mobile v1 (login, produk, transaksi, sesi kas) · **MCP server** read-only untuk AI client pemilik toko (3 tool) · **Link Data untuk AI** (`GET /api/v1/connector/summary`) untuk ChatGPT/Claude/Gemini tanpa pemasangan |

### Tujuh hal yang membedakannya

1. **Konsol SaaS-nya dirancang untuk tidak melihat terlalu banyak.** Daftar klien menampilkan **kelompok omzet, bukan angka rupiah persis**. Angka persis punya halamannya sendiri, hanya untuk klien yang menyetujui jalur Harga Adaptif, dan **setiap kunjungan ke sana tercatat sebagai kejadian `sensitive` tanpa deduplikasi**. Pemakaian kuota AI pun hanya terlihat sebagai angka gabungan. Diperagakan di Babak 5.
2. **Gerbangnya di server, bukan di menu.** Modul yang dicabut dari sebuah role bukan cuma hilang dari navigasi; mengetik URL-nya langsung tetap ditolak. Hal yang sama berlaku untuk kapabilitas outlet dan keadaan langganan — di web, API self-order, job antrean, dan MCP.
3. **Harga bisa berubah tanpa deploy, dan tiap tagihan bisa dijelaskan.** Aturan harga berupa baris berkriteria; tiap tagihan **membekukan konteks harganya** berikut rinciannya (paket, kursi, kuota AI, prorata). Nominal yang menyimpang dari aturan wajib beralasan, dan alasannya dibaca tenant.
4. **Kasirnya tetap hidup saat jaringan mati — dan saat telat bayar.** Katalog dan indeks saran jual ter-snapshot ke perangkat, antrean penjualan offline tersinkron lewat Background Sync. Masa tenggang baru mengunci layar POS di hari ke-20, bukan hari pertama.
5. **AI-nya membaca data usaha itu sendiri, dua arah.** Owner meminta analisis di dalam aplikasi (job antrean, berkuota), **atau** menyambungkan Claude Desktop miliknya lewat token MCP, **atau** menempel Link Data ke ChatGPT/Claude/Gemini — dua jalur terakhir memakai kuota AI miliknya sendiri.
6. **Sinyal stok berubah jadi rupiah dan penjualan.** Barang yang hampir kedaluwarsa atau tak bergerak sebulan diberi potongan otomatis (dengan lantai untung), didorong lewat saran di layar kasir, dan hasilnya dihitung — omzet dari barang tertekan dan modal yang hangus.
7. **Uang laci bisa dipertanggungjawabkan.** Kasir menghitung sebelum melihat angka seharusnya, uang yang keluar di tengah shift tercatat beralasan, dan tagihan terbuka yang terlupa berubah jadi kas negatif alih-alih menghilang.

### Model langganan, singkatnya

Masa gratis **2 bulan** di paket Free → dipindah ke **Paid 1** → prabayar bulanan. Telat bayar masuk **tenggang 30 hari bertingkat** (halus → intensif → POS terkunci mulai hari ke-20) → `suspended`. Membaca data lama **tidak pernah** dicabut di tahap mana pun. Halaman `/langganan` dan logout **selalu** terbuka.

Dua jalur harga: **Harga Tetap** (tarif paket, tidak membuka data penjualan) dan **Harga Adaptif** (diskon dari Paid 1 menurut omzet bulan sebelumnya, menuntut persetujuan berversi). Rincian di [§6](#6-kondisi-langganan--penagihan).

### Yang sudah jadi vs yang belum

**Sudah jadi dan bisa diperagakan:** semua yang tertulis di tabel di atas. **Belum ada:** payment gateway dengan uang sungguhan, printer Bluetooth (butuh lapisan native), multi-cabang, restock dari foto struk, dan bundling berdiskon. Rinciannya di [§8](#8-batasan-yang-perlu-diketahui-sebelum-demo) — bacalah sebelum menjanjikan apa pun.

---

## 2. Dua Lapis Autentikasi

Sistem ini punya **dua tabel pengguna yang benar-benar terpisah**, bukan satu tabel dengan flag peran:

| Lapis | Tabel | Halaman masuk | Untuk siapa |
|---|---|---|---|
| **Tenant** | `users` | `/login` | Pemilik usaha & stafnya — POS, produk, stok, laporan, AI |
| **Platform** | `platform_users` | `/platform/login` | Pemilik SaaS — kelola klien, langganan, tagihan, paket & aturan harga, kuota AI, audit |

Akun platform **sengaja tidak punya `tenant_id`** dan tidak memakai spatie/permission; izinnya dikelola lewat tabel `platform_user_modules`. **Jangan mencoba masuk `/platform` dengan `owner@sapi.test`** — tidak akan bisa, dan itu memang desainnya.

**Cacat pengalihan `[BL-043]` sudah diperbaiki (2026-08-05).** Akun platform yang membuka `/platform/login` dengan sesi masih hidup kini mendarat di `/platform`, dan sisa `url.intended` dari area tenant tidak lagi membajak login platform. Jendela peramban terpisah tetap disarankan — bukan karena bug, melainkan karena Anda akan masuk sebagai tenant dan platform bergantian.

**2FA platform:** akun yang sudah mendaftarkan authenticator masuk dalam **dua langkah** (kata sandi → kode 6 digit atau kode pemulihan). Pendaftarannya dari menu **Keamanan Akun**. Akun demo `platform@sapi.test` **belum** mendaftarkannya — lihat peringatan di [§3](#3-kredensial-demo).

Setiap tombol keluar — di kasir, owner, platform — sekarang meminta konfirmasi lebih dulu.

---

## 3. Kredensial Demo

Semua kata sandi di bawah: **`password`**

### Platform Console — `/platform/login`

| Email | Kata sandi | Keterangan |
|---|---|---|
| `platform@sapi.test` | `password` | `is_owner = true` → akses penuh; **2FA belum aktif** |

Akun ini dibuat oleh `PlatformUserSeeder`, yang **tidak dipanggil dari `DatabaseSeeder`**. Kredensialnya bisa dialihkan lewat `.env`: `PLATFORM_ADMIN_EMAIL`, `PLATFORM_ADMIN_PASSWORD`, `PLATFORM_ADMIN_NAME`.

> **Sebelum memperagakan 2FA:** mengaktifkannya di akun ini membuat setiap login berikutnya menuntut kode dari authenticator. Kalau ingin memperagakannya, lakukan pada **akun staf platform** yang dibuat saat demo, atau simpan delapan kode pemulihannya di tempat yang bisa Anda buka di depan penonton.

### Tenant 1 — Kopi Nusantara (`kopi-nusantara`)

| Email | Kata sandi | Peran | Role RBAC |
|---|---|---|---|
| `owner@sapi.test` | `password` | owner | semua (owner tidak dibatasi modul) |
| `kasir@sapi.test` | `password` | cashier | **"Kasir + Gudang"** → `pos`, `cash_drawer`, `stock` |
| `gudang@sapi.test` | ⚠️ tidak diketahui | cashier | "Kasir + Gudang" |
| `kasir2@sapi.test` | `password` | cashier | **"Kasir"** → `pos`, `cash_drawer` — **⚠️ tidak ada di basis data saat ini** |

`kasir2@sapi.test` ada di `DatabaseSeeder` tapi **hanya lahir setelah `migrate:fresh --seed`**, dan basis data lokal per 2026-09-15 belum pernah di-reset sejak itu. `gudang@sapi.test` dibuat manual lewat UI Staf, jadi kata sandinya tidak ada di kode mana pun. Kalau perlu dipakai (misalnya untuk Babak 4), setel dulu:

```bash
php artisan tinker --execute 'App\Models\User::withoutGlobalScopes()->where("email","gudang@sapi.test")->first()->update(["password"=>bcrypt("password")]);'
```

### Tenant 2 — Kopi Story (`kopi-story`)

| Email | Kata sandi | Peran |
|---|---|---|
| `owner@kopistory.test` | `password` | owner |
| `kasir@kopistory.test` | `password` | cashier |

### Tenant 3 — Squid Coffee & Eatery (`squid-coffee`) — baru

| Email | Kata sandi | Peran | Role RBAC |
|---|---|---|---|
| `owner@squid.id` | `password` | owner | semua |
| `kasir@squid.id` | `password` | cashier | **"Kasir"** → `pos`, `cash_drawer`, `stock` |

> **Nama dan alamatnya milik kafe sungguhan di Makassar; menu, harga, dan seluruh angka penjualannya rekonstruksi seeder.** Jangan memperkenalkannya sebagai klien atau sebagai data milik usaha itu. Sebut "tenant uji bergaya kafe 24 jam".

---

## 4. Isi Data Tiap Tenant

Angka di bawah adalah keadaan basis data lokal per **2026-09-15**.

| | **Kopi Nusantara** (t1) | **Kopi Story** (t2) | **Squid Coffee** (t5) |
|---|---|---|---|
| Karakter | Sampel kecil + data peraga | Studi kasus 8 bulan, kedai kopi susu | Kafe 24 jam ramai, 2 bulan |
| Seeder | `DatabaseSeeder` + `DemoTransactionSeeder` + `UpsellRuleShowcaseSeeder` | `CafeStudyCaseSeeder` | `SquidCoffeeSeeder` |
| Produk | 7 (4 asli + 3 produk peraga `DEMO-UPSELL-`) | 20 / 36 varian | 36 / 53 varian |
| Transaksi | ±4.030 (12 Apr – 14 Sep 2026) | ±5.190 (1 Jan – **4 Sep** 2026) | ±1.880 (15 Jul – 14 Sep 2026), omzet ±Rp50 jt lalu ±Rp70 jt per bulan |
| Aturan saran jual | 14 (13 aturan peraga) | — | 8 aturan owner sejak 1 Agu |
| Aturan diskon | 1 | — | — |
| Staf | 3 (owner, kasir, gudang) | 2 | 2 |
| Cara berjualan (`selling_style`) | — | — | Warung / kafe menetap |
| Identitas pesanan | **nama pelanggan** | tidak dipakai | nama pelanggan |
| Antrian dapur | mati | mati | **menyala** |
| Saran jual wajib dijawab | **menyala** | mati | mati |
| Foto bukti non-tunai | **wajib** | mati | mati |
| Pajak / biaya layanan | mati / mati | mati / mati | mati / mati |
| Laci kas terbuka | ⚠️ sejak 20 Agu (`kasir@sapi.test`) | — | ⚠️ sejak 14 Sep 00:00 (`kasir@squid.id`) |
| Paket & jalur | Paid 1 · **Harga Adaptif** · 6 kursi · lunas s.d. 24 Okt | Paid 1 · Harga Tetap · 6 kursi · ⚠️ periode **berakhir 24 Agu** | Paid 1 · Harga Tetap · 3 kursi · 15 Sep – 15 Okt |

Kalau belum pernah dilihat: identitas pesanan Kopi Nusantara kini **nama pelanggan**, bukan nomor meja seperti di panduan lama.

### Tenant mana untuk babak mana

- **Kopi Story** tetap panggung terbaik untuk **laporan jangka panjang dan rekap kas**: tujuh bulan lebih data, 213+ sesi kas harian dengan selisih pas/minus/plus. Tapi datanya berhenti 4 September dan periode langganannya sudah lewat — **tambal dulu datanya dan jauhi halaman `/langganan`-nya** (lihat [§5](#5-menyiapkan--menyegarkan-data-demo)).
- **Squid Coffee** untuk **layar kasir yang ramai dan Penyelamat Stok**: katalog 53 varian, aturan saran jual milik owner, antrian dapur menyala, dan omzet yang cukup besar untuk memperlihatkan **plafon Harga Adaptif** (Rp50 juta) di halaman pengajuannya.
- **Kopi Nusantara** untuk **RBAC, pengaturan, Aturan Saran Jual, dan Harga Adaptif yang sudah berjalan**. Aturan peraganya memunculkan kesepuluh keadaan di tab "Muncul di kasir hari ini" sekaligus.

### Catatan yang mudah mengejutkan

- **Produk peraga di Kopi Nusantara terlihat di grid kasir.** Dua dari tiga produk `DEMO-UPSELL-` tampil di katalog POS — satu berlencana "Habis", satu sudah kedaluwarsa. Mereka tidak pernah disarankan ke pelanggan, tapi penonton bisa bertanya. Cabut sebelum babak kasir kalau babak itu memakai Kopi Nusantara (perintahnya di §5).
- **Saran jual wajib + foto bukti wajib menyala di Kopi Nusantara.** Tombol Bayar terkunci sampai semua saran dijawab, dan pembayaran QRIS/transfer menuntut foto. Keduanya bagus diperagakan dengan sengaja, dan menjengkelkan bila tidak disadari. Laptop tanpa kamera: matikan foto bukti di **Cara Kerja Sistem**.
- **Squid Coffee tidak punya penjualan hari ini** — seedernya berhenti di 14 September dan tidak bisa dijalankan ulang. Kartu "Hari Ini" di berandanya kosong sampai Anda berjualan lewat kasir.
- **Hampir semua varian tidak punya tanggal kedaluwarsa.** Penyelamat Stok karena itu lebih banyak berisi barang tak laku sebulan daripada barang hampir basi. Itu cermin batasan `[BL-107]`, bukan data yang rusak.
- **Modal hangus bulanan baru tercatat sejak 8 September 2026** (`stock:record-expired`), dan kartunya menyebut tanggal itu terus terang.

---

## 5. Menyiapkan & Menyegarkan Data Demo

### Menjalankan aplikasi

```bash
composer run dev
```

Perintah ini menyalakan tiga proses sekaligus: `artisan serve`, `queue:listen`, dan `npm run dev`. **Penjadwal (`schedule:run`) tidak ikut** — dan untuk basis data lokal hari ini itu justru aman, lihat daftar periksa.

**Antrean wajib hidup.** Analisis AI berjalan sebagai job (`RunAiAnalysisJob`); tanpa worker, hasil analisis tidak akan pernah muncul. Kalau `.env` baru diubah, **worker harus di-restart**.

Port di-pin **8001** lewat `.claude/launch.json` supaya tidak bentrok dengan proyek lain di port 8000.

Aplikasi berjalan di **WITA** (`APP_TIMEZONE=Asia/Makassar`): "hari ini" di beranda, laporan, dan sesi kas dihitung dengan jam toko.

### Daftar periksa sebelum demo (basis data lokal saat ini)

1. **Migrasi.** Jalankan `php artisan migrate` bila ada migrasi baru — batch stok 2026-09-15 menambah dua tabel/kolom.
2. **Tambal data sampai hari ini** untuk Kopi Story dan Kopi Nusantara (perintah di bawah). Kopi Story kosong sejak 4 September.
3. **Tutup laci kas yang basi** di Kopi Nusantara (sejak 20 Agu) dan Squid Coffee (sejak 14 Sep). Paling cepat lewat perintah yang memang dijadwalkan untuk itu — ia menandai sesinya "Ditutup sistem" tanpa mengarang hitungan fisik:

   ```bash
   php artisan cash-drawers:expire
   ```

4. **Jangan menjalankan `schedule:run` atau `subscriptions:advance-lifecycle` di basis data ini tanpa sengaja.** Periode Kopi Story berakhir 24 Agustus dan statusnya masih `active` hanya karena siklus hidupnya belum pernah dijalankan sejak itu. Menurut `config/subscription.php` dan `Tenant::graceDay()`, begitu ia dipindah ke `grace`, hitungan tenggangnya dimulai dari 25 Agustus — hari ke-22 per 15 September, yaitu tahap **POS terkunci**. Kalau ingin memperagakan tangga tenggang, lakukan setelah reset bersih atau pada tenant lain.
5. **Cabut produk peraga** bila babak kasir memakai Kopi Nusantara:

   ```bash
   PERAGA=bersih php artisan db:seed --class=UpsellRuleShowcaseSeeder --no-interaction
   ```

   Di PowerShell: `$env:PERAGA='bersih'; php artisan db:seed --class=UpsellRuleShowcaseSeeder --no-interaction`. Menjalankannya tanpa `PERAGA` memasang peraganya kembali.
6. **Periksa kamera** atau matikan foto bukti di Kopi Nusantara (Cara Kerja Sistem).
7. **Kuota AI:** ketiga tenant di Paid 1 = **15 analisis/hari**. Jangan habiskan saat gladi bersih.
8. **2FA platform:** putuskan dulu apakah akan diperagakan dan dengan akun apa ([§3](#3-kredensial-demo)).

### Menyegarkan data agar sampai hari ini

Kedua seeder transaksi berikut **hanya mengisi hari yang belum punya penjualan selesai** (`completed`) — hari yang cuma berisi tagihan terbuka ikut ditambal sejak 2026-08-20. Aman dijalankan kapan pun, sesering apa pun:

```bash
php artisan db:seed --class=DemoTransactionSeeder --no-interaction
```

```bash
php artisan db:seed --class=CafeStudyCaseSeeder --no-interaction
```

`SquidCoffeeSeeder` **tidak** bisa dipakai menambal: ia menolak berjalan bila tenant `squid-coffee` sudah ada, dan hari penjualan terakhirnya dipatok 14 September 2026.

> Hari yang isinya **tipis** (misalnya satu transaksi hasil uji coba) tetap dianggap terisi dan tidak ditambal. Untuk data yang benar-benar rapi, jalurnya reset bersih di bawah.

### Reset bersih total

```bash
php artisan migrate:fresh --seed && php artisan db:seed --class=PlatformUserSeeder && php artisan db:seed --class=CafeStudyCaseSeeder && php artisan db:seed --class=DemoTransactionSeeder
```

Opsional sesudahnya: `SquidCoffeeSeeder` (tenant ketiga) dan `UpsellRuleShowcaseSeeder` (aturan peraga, dipasang ke tenant pertama kecuali `PERAGA_TENANT=<slug>` disetel).

Urutannya penting:

- `DatabaseSeeder` memakai `Tenant::create()` — **tidak idempotent**. Ini juga satu-satunya jalan mendapatkan `kasir2@sapi.test`. Tenantnya dimulai lewat `startTrial()` (masa gratis) dengan 5 kursi, jadi keadaan langganannya **tidak sama** dengan basis data lokal hari ini.
- `PlatformUserSeeder` tidak ikut terpanggil oleh `--seed`.
- `DemoTransactionSeeder` dan `UpsellRuleShowcaseSeeder` bergantung pada data `kopi-nusantara`, jadi harus setelah `DatabaseSeeder`.

### Ringkasan seeder

| Seeder | Isi | Aman dijalankan ulang? |
|---|---|---|
| `PermissionCatalogSeeder` | 7 permission modul tenant (global) | ✅ |
| `DatabaseSeeder` | Kopi Nusantara + owner + 2 kasir + menu + modifier + metode bayar + dua role contoh | ❌ gagal, harus `migrate:fresh` |
| `DemoTransactionSeeder` | Sampai 90 hari transaksi untuk Kopi Nusantara | ✅ hanya mengisi hari kosong |
| `CafeStudyCaseSeeder` | Kopi Story: menu, transaksi sejak 1 Jan 2026, restock bulanan, sesi kas harian | ✅ hanya mengisi hari & bulan kosong |
| `SquidCoffeeSeeder` | Squid Coffee: 36 produk/53 varian, 8 aturan saran jual, transaksi 15 Jul – 14 Sep 2026, stok diturunkan dari volume jual | ❌ menolak bila tenantnya sudah ada |
| `UpsellRuleShowcaseSeeder` | Aturan & produk peraga untuk sepuluh keadaan halaman Aturan Saran Jual (`PERAGA=bersih` mencabutnya) | ✅ mencabut yang lama dulu |
| `PlatformUserSeeder` | Akun pemilik platform | ✅ |
| `ProductionSeeder` | Tenant "default" + admin dari `ADMIN_EMAIL`/`ADMIN_INITIAL_PASSWORD` — **untuk produksi, bukan demo** | ✅ |

---

## 6. Kondisi Langganan & Penagihan

Hampir seluruh bagian ini berubah sejak 1 Agustus. Panduan lama menyebut plan "Dasar" Rp0 dan tabel `invoices` kosong — keduanya sudah tidak berlaku.

### Paket yang berlaku

| Paket | Harga/bulan | Kursi termasuk | Kursi tambahan | Analisis AI/hari |
|---|---|---|---|---|
| **Free** (masa gratis) | Rp0 | 2 | Rp20.000 | 5 |
| **Paid 1** | Rp100.000 | 3 | Rp15.000 | 15 |
| **Paid 2** | Rp150.000 | 5 | Rp12.500 | 30 |
| **Paid 3** | Rp200.000 | 10 | Rp10.000 | 60 |

**Kuota AI tambahan:** satu blok = +5 analisis/hari, **Rp15.000/bulan**, seragam antar paket, paling banyak 20 blok. Belum ada kebijakan promo kuota yang aktif.

**Kursi tambahan ditagih karena dibeli, bukan karena dipakai**, bulanan, dan bisa dilepas (pelepasan berlaku satu periode penuh ke depan). Kursi yang dibeli di tengah periode ditagih per hari sebagai komponen tagihan berikutnya.

### Siklus hidup

```
trial (Free, 2 bulan) ──H-14: pilihan jalur──H-7: pindah ke Paid 1 + tagihan terbit──> active
active ──periode lewat tanpa bayar──> grace (30 hari) ──> suspended
```

| Tahap tenggang | Hari | Yang terjadi |
|---|---|---|
| Halus | 1–14 | Pita pemberitahuan di shell owner **dan** kasir |
| Intensif | 15–19 | Modal peringatan (padam bila instruksi bayar sudah terbit, tapi hitungan harinya jalan terus) |
| Terkunci | 20–30 | **Layar POS** diganti halaman kunci di URL aslinya; laporan, riwayat, stok, dan ekspor tetap terbuka |
| `suspended` | > 30 | Sidebar owner mati selain **Langganan & Tagihan**; tombol **aktifkan kembali** menerbitkan satu tagihan pemulihan |

Model **prabayar**: tarif periode P dihitung dari omzet bulan sebelum P. Tagihan periode berikutnya terbit sendiri **7 hari** sebelum periode berjalan habis (tenant Adaptif berjangkar tanggal 1–7 menunggu ringkasan omzet bulannya lebih dulu). Tanggal tagih berjangkar pada tanggal daftar; bulan pendek hanya menjepit sementara.

### Harga Adaptif

Diskon dari Paid 1 menurut omzet bulan sebelumnya:

| Kelas | Omzet bulanan | Tarif | Diskon |
|---|---|---|---|
| A | Rp0 – 2 juta | Rp10.000 | 90% |
| B | Rp2 – 5 juta | Rp25.000 | 75% |
| C | Rp5 – 15 juta | Rp50.000 | 50% |
| D | Rp15 – 50 juta | **Rp75.000** (revisi 7 Agu; dulu Rp100.000 = diskon 0%) | 25% |
| — | di atas Rp50 juta | tidak berhak Adaptif → Paid 1 harga penuh | — |

- **Pengajuan punya halaman** (`/langganan/harga-adaptif`) dan dinilai seketika: layak / sudah aktif / masa jeda / di atas plafon. Persetujuan diberikan saat mengajukan, jadi omzet baru diukur setelah tenant memintanya.
- Tenant Adaptif yang omzetnya melewati plafon diberi tahu dulu, dipindah di akhir periode.
- Kursi tidak didiskon: tenant Adaptif di Paid 1 tetap membayar Rp15.000/kursi.
- Tenant Harga Tetap melihat **kelasnya sendiri** di `/langganan`, dengan pernyataan bahwa kelas itu ditentukan tanpa melihat penjualannya.

### Membayar

Driver pembayaran hari ini adalah **`fake`** (gateway tiruan). Ada dua cara memperagakan pembayaran, keduanya melunasi lewat **webhook bertanda tangan HMAC** yang sama dengan penyedia sungguhan nanti:

1. **Modal satu langkah** di tab **Tagihan** `/langganan`: pilih kanal, ketik **kata sandi owner** sebagai ganti aplikasi bank, ±2,4 detik proses, lunas. Paling cocok untuk ruang rapat.
2. **Halaman instruksi bayar** (`/langganan/tagihan/{invoice}/bayar`): nomor VA / QR terbit, "Memeriksa pembayaran…", lalu lunas sendiri setelah **8 detik** (`PAYMENT_FAKE_AUTO_SETTLE_SECONDS`).

Jalur lama — unggah bukti transfer lalu diverifikasi manual dari konsol platform — tetap ada. Tombol "Simulasikan pembayaran" sudah **dicabut**. Driver tiruan menolak di-resolve di produksi.

### Keadaan ketiga tenant saat ini

| | Kopi Nusantara | Kopi Story | Squid Coffee |
|---|---|---|---|
| Paket · jalur | Paid 1 · Harga Adaptif | Paid 1 · Harga Tetap | Paid 1 · Harga Tetap |
| Kursi | 6 (3 termasuk + 3 dibeli) | 6 (3 termasuk + 3 dibeli) | 3 |
| Periode berjalan | 24 Sep – 24 Okt 2026 | 24 Jul – 24 Agu 2026 ⚠️ | 15 Sep – 15 Okt 2026 |
| Tagihan | 3, semuanya lunas | 1 (Rp0, peninggalan lama) | — |

Untuk memperagakan **tagihan terbuka dan pembayaran**, jalur paling bersih: dari konsol platform, tab **Tagihan** pada rincian Squid Coffee atau Kopi Nusantara, terbitkan satu tagihan (nominal yang menyimpang dari aturan akan meminta alasan), lalu bayar dari sisi owner.

### Aturan yang mengikat dan sebaiknya disebutkan saat demo

- **Daftar tenant di panel platform menampilkan kelompok omzet, bukan angka rupiah persis.** Angka persis hanya di tab **Omzet**, dan setiap kunjungan tercatat `sensitive` tanpa deduplikasi. Membuka rincian tenant tercatat sebagai kejadian **rutin**.
- **Mencabut persetujuan Harga Adaptif** menghapus metrik seketika, tapi tarifnya tetap berlaku sampai akhir periode.
- **Pindah jalur harga** minimum tiga bulan sekali. **Retensi data omzet** 24 bulan.
- **Tab Kapabilitas di panel platform hanya membaca.** Pemilik SaaS bisa *mengetahui* fitur apa yang menyala, tidak bisa mengubahnya. Satu-satunya pengecualian yang disengaja adalah **membuka kunci pajak** — dan itu membuka kuncinya, bukan menulis setelannya.
- **Pindah paket dan tagihan manual yang menyimpang wajib beralasan**, tercatat di jejak audit, dan alasan tagihan dibaca tenant.

---

## 7. Alur Demo yang Disarankan

Urutan ini bergerak dari yang paling konkret ke yang paling abstrak. Totalnya sekitar 35 menit; Babak 0 dan bahan tambahan boleh dilewati.

### Babak 0 — Sebelum mendaftar (2 menit, opsional) · tanpa login

1. **Landing** → bagian "Bagaimana Harga Anda Dihitung", lalu **`/harga`**: kedua jalur tarif dibaca langsung dari aturan yang berlaku.
2. **`/register`**: tunjukkan bahwa masa gratis 2 bulan, paket sesudahnya, dan tanggal tagihan pertama tertulis sebelum tombol Daftar. Pilih cara berjualan **Gerai acara & bazar** dan perlihatkan paket setelannya berubah (kode panggil otomatis, antrian dapur menyala) — terlihat dan bisa diubah. **Jangan menekan Daftar** kecuali pengirim surel sudah disiapkan ([§8](#surel-tidak-benar-benar-terkirim)).

### Babak 1 — Kasir (8 menit) · `kasir@kopistory.test` atau `kasir@squid.id`

1. Masuk → **buka sesi kas** (kas awal Rp500.000). Tekan tombol **layar penuh** di topbar.
2. **POS**: pilih produk, varian, modifier. Masukkan dua barang, lalu **Ubah** salah satu baris (ganti ukuran) — tunjukkan pilihannya sudah tercentang dan jumlahnya tidak kembali ke 1.
3. **Kartu saran jual** muncul di dasar kolom katalog dan menyalakan baris keranjang asalnya. Tekan **Diterima**, lalu **Batalkan** untuk menunjukkan keranjang kembali seperti semula; tolak saran berikutnya. Tekankan: "Ditolak" berarti pelanggan menolak, dan laporannya membedakan itu dari saran yang tidak pernah dijawab.
4. **Bayar tunai**: kotak nominalnya kosong. Ketik uang yang diterima (atau tekan **Uang Pas** setelah menghitung), tunjukkan kembalian. Ulangi dengan QRIS, lalu satu transaksi **split bill** tunai + QRIS — sisa non-tunai jatuh sendiri.
5. **Tunda Bayar** satu pesanan, lalu lunasi lewat tombol **Tagihan Terbuka** di topbar. Sebutkan umurnya 24 jam, setelah itu jadi kas negatif yang hanya owner bisa bereskan.
6. **Edit transaksi** yang sudah selesai → stok ikut terkoreksi.
7. **Kas** → **Catat Uang Keluar** Rp20.000 ("beli es batu", langsung berlaku), lalu Rp75.000 (tertahan: di atas ambang Rp50.000, menunggu persetujuan owner).
8. **Tutup Kas**: masukkan hitungan fisik **tanpa** melihat angka seharusnya. Sebutkan bahwa tombol "Tampilkan uang seharusnya" ada, tapi setiap penekanannya dibaca owner. Masukkan angka yang sengaja berbeda untuk memperlihatkan selisih.

### Babak 2 — Cara Kerja Sistem (4 menit) · `owner@sapi.test`

Satu aplikasi, banyak bentuk usaha.

1. **Pengaturan → Cara Kerja Sistem → Paket Setelan Awal**: pilih paket lain dan tunjukkan layar memperlihatkan selisihnya sebelum diterapkan. Tidak ada yang diterapkan tanpa diminta.
2. **Mode & Fitur Outlet**: antrian dapur, pesan mandiri, analisis AI, foto bukti pembayaran. Nyalakan antrian dapur, ganti identitas pesanan ke **kode panggil**, buat satu transaksi di POS, dan tunjukkan nomornya di struk dan di **papan `/cashier/queue`**.
3. **Saran jual wajib dijawab** (menyala di Kopi Nusantara) dan **Jenis Saran Jual**: empat saklar per jenis. Sebutkan bahwa saklar darurat global milik pemilik SaaS selalu menang.
4. **Batas Untung Minimum** (10%) dan **Batas Uang Keluar Tanpa Persetujuan** (Rp50.000) — dua angka yang dipakai Aturan Diskon dan laci kas.
5. **Pajak & Biaya Layanan**: tunjukkan pilihan exclusive/inclusive dan pembagiannya di struk. **Hati-hati menyalakan pajak di tenant demo**: sebagian setelannya terkunci setelah dipakai dan hanya bisa dibuka lewat operator platform (Babak 5). Tarif tetap bisa diubah.
6. **Profil & Merek**: unggah logo → sidebar owner, topbar kasir, dan kepala struk berganti sekaligus.

### Babak 3 — Pemilik Usaha (10 menit) · `owner@squid.id` atau `owner@kopistory.test`

1. **Beranda** — omzet & transaksi hari ini dan bulan ini; tekan salah satu kartu untuk mendarat di laporannya. Sakelar Hari Ini / Bulan Ini pada rekap metode bayar.
2. **Penyelamat Stok** di beranda — barang tertekan, modal yang tertahan, dan **berapa yang belum punya potongan otomatis**. Tab **Bulan Ini**: omzet dari barang tertekan dan modal hangus. Tekan "Lihat di Stok" → daftar yang sama persis di halaman Stok.
3. **Aturan Diskon** — satu aturan potongan hampir kedaluwarsa; tunjukkan lantai untung di pratinjau dan chip "Tertahan lantai".
4. **Aturan Saran Jual** — tulis aturan "beli X, tawarkan Y", simpan, dan halaman melompat ke tab **Muncul di kasir hari ini**. (Di Kopi Nusantara, kolom "Di kasir hari ini" memperlihatkan kesepuluh keadaan: tampil di slot, tergeser, stok habis, kedaluwarsa, diwakili saran lain, dan seterusnya.)
5. **Saran Jual** (laporan) — corong Tampil → Dijawab kasir → Diterima, dipisah Otomatis vs Aturan Anda.
6. **Laporan Bulanan** — garis tren sebulan, tekan satu tanggal untuk rinciannya, unduh CSV. **Laporan Harian** dan **Rekap Kas** Kopi Story: sesi pas, minus, plus lengkap dengan alasannya.
7. **Sesi Kas** — setujui uang keluar Rp75.000 dari Babak 1, tunjukkan kolom **Angka Dibuka** dan label "Ditutup sistem". **Transaksi** — pembereskan kas negatif: catat pelunasan terlambat, atau hapuskan (hanya jalan kedua yang mengembalikan stok).
8. **AI Analysis** — meter kuota di atas tombol; kirim satu analisis dan tunjukkan hasilnya menyebut angka milik toko ini, dengan nama varian yang bisa diklik.
9. **Integrasi & Kredensial → Akses MCP** dan **Link Data untuk AI** — dua cara owner membawa data tokonya ke AI miliknya sendiri. Peragakan **Buat Link** (nama "ChatGPT", 30 hari, centang pernyataan bertanggal) → modal link tidak bisa ditutup sebelum link disalin → link muncul di daftar "Link aktif" dengan "belum pernah dibuka" → **Cabut**. Daftar fungsi yang bisa dipakai AI ada di [bahan AI membaca data toko](#bahan-ai-membaca-data-toko--mcp-dan-link-data).
10. **Langganan & Tagihan** — tiga tab. Di Kopi Nusantara: jalur Harga Adaptif dengan jejak persetujuannya. Di Squid Coffee: buka **Harga Adaptif** dan tunjukkan penilaian seketika terhadap plafon Rp50 juta. Tab **Kapasitas**: beli dan lepas kursi atau blok kuota AI. Bila ada tagihan terbuka, bayar lewat modal kata sandi.

### Babak 4 — RBAC (3 menit) · `owner@sapi.test` (Kopi Nusantara)

1. **Role** — "Kasir + Gudang" (`pos`, `cash_drawer`, `stock`) berdampingan dengan "Kasir" (`pos`, `cash_drawer`). **Staf** — lencana modul menjawab "orang ini bisa buka apa saja"; baris owner menyatakan aksesnya tidak berasal dari role.
2. Karena `kasir2@sapi.test` tidak ada di basis data saat ini, **tetapkan role "Kasir" ke `gudang@sapi.test`** dari halaman Staf (setel kata sandinya dulu, [§3](#3-kredensial-demo)).
3. Masuk sebagai `gudang@sapi.test` di jendela lain → menu **Stok hilang**, dan mengetik `/owner/stock` langsung tetap **ditolak**. Gerbangnya di server.

> Peragakan dengan modul `stock`, jangan dengan mencabut `pos`. Rute kasir sengaja tidak digerbang per modul — lihat [§8](#satu-lubang-rbac-yang-sengaja-dibiarkan).

### Babak 5 — Platform Console (8 menit) · `platform@sapi.test`

1. **Keamanan Akun** — tunjukkan pendaftaran authenticator dan delapan kode pemulihan. Aktifkan hanya bila sudah diputuskan di [§3](#3-kredensial-demo).
2. **Daftar Tenant** → **rincian** — lima tab: **Ikhtisar · Langganan · Tagihan · Kapabilitas · Omzet**. Kapabilitas hanya membaca, dan di sana pula keadaan pajak toko beserta tombol **buka kunci** (alasan minimal 10 karakter, jendela 7 hari, sekali pakai, tercatat `sensitive`).
3. **Tab Langganan** — pindahkan tenant ke Paid 2, alasan wajib. **Tab Tagihan** — terbitkan tagihan dengan nominal yang menyimpang dari aturan: alasannya ditanya, dan tenant membacanya di `/langganan`.
4. **Aturan Harga** — tangga A–D (D kini Rp75.000, plafon Rp50 juta), paket Free/Paid 1–3 berikut batas kursi & AI, serta penunjukan **paket tujuan setelah masa gratis**. Terbitkan satu revisi dengan tanggal berlaku ke depan: harga berubah tanpa deploy.
5. **Kuota AI** — kebijakan berjangka dengan tanggal akhir (promo), angka pemakaian yang hanya gabungan, dan tombol mengembalikan jatah hari ini yang berdiri terpisah dengan konfirmasinya sendiri.
6. **Omzet** — buka tab Omzet satu tenant, lalu segera ke **Jejak Audit**: kunjungan tadi tercatat `sensitive`, membuka rincian hanya kejadian rutin. **Demo paling meyakinkan di babak ini.**
7. **Akun Platform** — buat staf yang hanya diberi modul `tenants`, masuk sebagai dia, tunjukkan menu lain menghilang — dan bahwa manajemen akun platform tidak ada di daftar modul yang bisa diberikan.

### Babak 6 — Isolasi Tenant (2 menit, penutup)

Masuk sebagai `owner@sapi.test` dan `owner@kopistory.test` bersebelahan: produk, transaksi, laporan, logo, semuanya terpisah total. Isolasi ini dijaga berkas uji tersendiri (`tests/Feature/TenantIsolation`, `PlatformIsolationTest`), bukan hanya kedisiplinan menulis query.

### Bahan: AI membaca data toko — MCP dan Link Data

Keduanya ada di **Pengaturan → Integrasi & Kredensial**, hanya untuk **owner**, **hanya membaca**, dan hanya berisi **angka agregat tanpa data pelanggan**. Keduanya ditolak bila fitur analisis AI outlet dimatikan. Token dan link tersimpan sebagai hash; teks utuhnya tampil sekali saja.

| | **Akses MCP** | **Link Data untuk AI** (baru) |
|---|---|---|
| Untuk siapa | AI client yang mendukung MCP (mis. Claude Desktop), pemakaian rutin | Owner pemula: tempel di ChatGPT, Claude, atau Gemini, tanpa memasang apa pun |
| Alamat | `/mcp/business` + header `Authorization: Bearer <token>` | `GET /api/v1/connector/summary?token=…` |
| Kredensial | Satu token (`mcp:use`), tanpa kedaluwarsa, bisa dibuat ulang atau dicabut | Banyak link bernama (`connector:read`), **wajib berumur 7 atau 30 hari**, tiap link bisa dicabut dan menyebut kapan terakhir dibuka |
| Cara AI memakainya | AI **memilih tool** dan mengisi rentang tanggal sendiri | AI **membuka satu URL** dan membaca satu paket teks Markdown; tidak ada parameter |
| Batas laju | 60 permintaan/menit | 30 permintaan/menit |
| Token saling pakai? | Token MCP ditolak di link, link ditolak di MCP (`[BL-112]`) | |

#### Fungsi (tool) di MCP server "SAPI Business Data"

Hanya **tiga** tool. Ketiganya bertanda read-only. `from`/`to` opsional (format `YYYY-MM-DD`); bila kosong, rentangnya 30 hari terakhir sampai hari ini.

| Nama tool | Parameter | Isi yang dikembalikan |
|---|---|---|
| `get-sales-summary` | `from`, `to` | Penjualan pada rentang itu: omzet, jumlah transaksi, rata-rata nota, **10 produk terlaris** (per varian: jumlah & omzet), dan **tren harian**. |
| `get-profit` | `from`, `to` | Profit pada rentang itu: omzet (termasuk pajak), pendapatan bersih, pajak, biaya layanan, modal barang (COGS), laba kotor, margin %; **proyeksi** laba periode berikutnya dari rata-rata harian; dan **laba per produk/varian** (qty, pendapatan bersih, modal, margin). |
| `get-menu` | — | Menu aktif: produk beserta kategorinya dan tiap varian dengan **nama, harga, dan sisa stok**. |

**Yang tidak ada** — jawab jujur bila penonton bertanya: tidak ada tool untuk mengambil satu produk menurut ID, mencari produk, riwayat/mutasi stok, tanggal kedaluwarsa, daftar transaksi atau pelanggan, laporan kas, dan **tidak ada tool yang mengubah data** (membuat produk, restock, mengubah harga). Stok yang bisa dibaca AI hanya **sisa stok per varian** dari `get-menu`. Pertanyaan seperti "stok apa yang hampir habis?" tetap bisa dijawab AI dengan menyaring hasil `get-menu`.

#### Isi paket Link Data untuk AI

Link tidak punya fungsi yang dipilih; setiap kali dibuka ia mengembalikan paket yang sama, dihitung saat itu juga dari service yang sama dengan tool MCP:

| Bagian | Isi |
|---|---|
| Kepala | Nama usaha, waktu data diambil (jam toko), pernyataan bahwa angkanya rupiah dan agregat |
| Ringkasan penjualan | Lima periode: hari ini, 7 hari terakhir, 30 hari terakhir, bulan ini, bulan lalu — tiap periode berisi omzet, jumlah transaksi, rata-rata nota, laba kotor |
| Profit 30 hari terakhir | Omzet, pendapatan bersih, pajak, service charge, modal barang, laba kotor & margin |
| Produk terlaris 30 hari terakhir | Produk, varian, jumlah terjual, omzet |
| Laba per produk 30 hari terakhir | Pendapatan bersih, modal, laba, margin per produk; sisanya digabung jadi satu baris "lainnya" |
| Produk yang dijual | Menu aktif per kategori, tiap varian dengan harga dan **stok**; maksimal 100 produk, sisanya disebut jumlahnya |

Dibanding MCP, link **tidak punya rentang tanggal bebas, tren harian, dan proyeksi**. Bila link ditolak (kedaluwarsa, dicabut, fitur AI mati, langganan), AI menerima kalimat penolakan biasa, bukan halaman login.

#### Cara memperagakannya

1. `owner@squid.id` → **Integrasi & Kredensial → Link Data untuk AI → Buat Link**. Pakai **Squid Coffee**: laba data demo dua tenant lain terbaca minus (`[BL-113]`).
2. Modal menampilkan **prompt siap tempel**: AI diminta membuka link, menjawab hanya dari isinya, lalu membuktikan link terbaca dengan menyebut nama usaha dan daftar produk — dan mengaku bila link gagal dibuka, alih-alih menebak.
3. **Di mesin lokal, AI sungguhan tidak bisa membuka link `localhost`.** Buka URL link itu di tab browser untuk memperlihatkan paket teks yang akan dibaca AI. Peragaan di ChatGPT/Claude/Gemini hanya bisa dilakukan di server yang sudah di-deploy, dan belum pernah diuji di sana (`[BL-102]`).
4. Tutup dengan **Cabut**, lalu muat ulang tab URL tadi: aksesnya langsung hilang.

Contoh pertanyaan untuk penonton: "Produk apa yang paling laku tapi marginnya paling tipis?", "Bandingkan omzet bulan ini dengan bulan lalu", "Varian mana yang stoknya tinggal sedikit?".

### Bahan tambahan bila ada waktu

- **Penjualan offline (PWA)** — matikan jaringan di DevTools, buat transaksi, tutup tab POS, nyalakan jaringan: antrean terkirim lewat Background Sync (Chrome). Tunjukkan halaman **Koreksi Offline** milik owner.
- **Barang kedaluwarsa** (bila perubahan 2026-09-15 sudah masuk) — restock dengan dua tanggal berbeda, jual melebihi stok yang masih baik, dan tunjukkan dialog alasan wajib di kasir serta tabel "Terjual kedaluwarsa" di laporan.
- **Papan antrian dapur Squid Coffee** — sudah menyala, cukup buat pesanan dari kasirnya.
- **`/dokumentasi`** — hub dokumentasi dua jalur (Panduan Penggunaan & Dokumentasi Developer). **`/api-docs`** — referensi API mobile.

---

## 8. Batasan yang Perlu Diketahui Sebelum Demo

Daftar ini ada supaya Anda tidak dikejutkan di depan penonton, dan supaya jawaban Anda jujur kalau ditanya.

### Sudah tidak berlaku sejak panduan lama

Supaya tidak ada yang mengulang kalimat lama: **2FA platform sudah ada** (`[BL-013]`), **cacat pengalihan `[BL-043]` sudah diperbaiki**, **tagihan periode terbit otomatis** (`[BL-044]`), **pita langganan tampil di shell owner dan kasir** (`[BL-045]`), **tenant bisa dipindah paket** (`[BL-046]`), **tarif paket sudah nyata** (bukan Rp0), dan **kursi yang dibeli bisa dilepas**.

### Surel tidak benar-benar terkirim

`MAIL_MAILER=log`. Akun demo sudah `email_verified_at` terisi jadi bisa langsung masuk. Tapi:

- **Mendaftar akun baru saat demo** → tautan verifikasi hanya muncul di `storage/logs/laravel.log`.
- **Kedua alur reset kata sandi** (tenant dan platform) dan **peringatan login gagal** tidak terkirim hidup.

### Belum terpasang

| Hal | Status | ID backlog |
|---|---|---|
| Payment gateway dengan uang sungguhan | Hanya gateway tiruan (`PAYMENT_DRIVER=fake`) dan unggah bukti transfer manual. Integrasi Sumopod **terblokir: akunnya belum ada**. | `[BL-060]` |
| Printer Bluetooth | Menabrak batas PWA, butuh lapisan native Android. Cetak ESC/POS lewat jalur yang ada tetap jalan. | `[BL-016]` |
| Jaminan offline penuh | Background Sync dan penyimpanan persisten sudah dipasang **untuk Chrome Android**; di luar itu masih "sebisanya". | `[BL-016]` |
| Multi-cabang | Satu tenant = satu outlet di seluruh basis kode; sengaja ditahan. | `[BL-068]` |
| Restock dari foto struk | Belum ada — dan karena itu hampir semua varian tidak punya tanggal kedaluwarsa. | `[BL-107]`, `[BL-106]` |
| Bundling berdiskon | Belum ada wujudnya; menunggu keputusan bentuk. | `[BL-103]` |
| Link Data untuk AI, diuji di layanan AI sungguhan | UI dan endpoint **sudah jadi** (tahap 1–2). Belum pernah ditempel ke Claude/ChatGPT/Gemini: layanan AI tidak bisa menjangkau `localhost`, jadi uji hidupnya menunggu deploy. | `[BL-102]` |
| Gerai acara & tagihan terbuka | Paket "Gerai acara & bazar" tetap bisa membuka Tunda Bayar yang hampir pasti jadi kas negatif. | `[BL-104]` |
| Rekonsiliasi kas per laci | Tahap A dan Tahap B langkah 1 selesai (tiap penjualan membawa lacinya); langkah 2 ditunda. | `[BL-028]` |
| Studi kasus demo kedua (bazar/non-kafe) | Belum ada; semua tenant demo adalah kafe. | `[BL-036]` |
| Berkas tenant di object storage | Masih di disk server. | `[BL-076]` |
| Kunci AI milik owner (BYOK) | Kolomnya ada di **Integrasi & Kredensial** dan meter kuota mengenalinya, tapi belum pernah diuji hidup dengan kunci sungguhan; ketiga tenant memakai kunci bersama aplikasi. | — |

### Sisa kecil yang bisa terlihat

- **"Pendapatan per Metode Pembayaran" di layar tutup kas kasir masih bruto** — kembalian belum dikurangkan, berbeda dari beranda dan laporan yang sudah dibetulkan (`[BL-109]`). Angka "seharusnya di laci" di sebelahnya tetap benar.
- **Laporan Bulanan belum menampilkan biaya layanan** walau datanya sudah dikirim (`[BL-097]`).
- **Pembatas geser antara keranjang dan panel belum bisa digerakkan dengan papan tik.**

**Papan antrian dapur SUDAH ADA** sejak 2026-07-29 (`/cashier/queue`). Ia **mati secara bawaan** (kecuali paket setelan yang menyalakannya) dan **tidak aktif saat perangkat offline** — penjualan hasil sinkronisasi tidak menyusul masuk papan. Itu keputusan, bukan bug.

### Satu lubang RBAC yang sengaja dibiarkan

Rute kasir (`/cashier/*`) **tidak** digerbang per modul — hanya `role:cashier,owner`. Kasir yang rolenya hanya berisi `stock` tetap bisa mengetik `/cashier/pos` dan berjualan.

Ini keputusan: `pos` dan `cash_drawer` diperlakukan sebagai **penanda menu**, bukan gerbang rute, dan menutupnya akan mengubah perilaku staf yang sudah ada — termasuk staf "Tanpa role (POS saja)".

Konsekuensi untuk demo: **peragakan RBAC dengan modul `stock`, jangan dengan mencabut `pos`.** Modul `stock`, `products`, `reports`, `payment_methods`, dan `ai_analysis` benar-benar digerbang di server. Halaman owner-only (Aturan Saran Jual, Aturan Diskon, Koreksi Offline, Staf, Role, ketiga halaman Pengaturan) tidak bisa dibuka staf sama sekali.

### Penjadwal

Tanpa `schedule:run`, hal-hal berikut **tidak terjadi sendiri**: laci kas yang lewat 24 jam tidak ditutup sistem, Tunda Bayar yang lewat 24 jam tidak jadi kas negatif, barang basi tidak tercatat, tagihan tidak terbit, dan tenant tidak berpindah keadaan. Untuk demo, jalankan perintah satuannya dengan sengaja alih-alih menyalakan penjadwal — dan ingat peringatan Kopi Story di [§5](#5-menyiapkan--menyegarkan-data-demo).

| Perintah | Jadwal |
|---|---|
| `stock:record-expired` | harian 00:05 |
| `subscriptions:compute-revenue` | tanggal 1, 02:40 |
| `platform:prune-audit-logs` | harian 03:10 |
| `subscriptions:advance-lifecycle` | harian 03:30 (wajib sesudah `compute-revenue`) |
| `payment-proofs:prune-unclaimed` | harian 03:50 |
| `subscriptions:prune-metrics` | tanggal 1, 04:30 |
| `open-bills:expire` | tiap jam |
| `cash-drawers:expire` | tiap jam, menit ke-5 |
| `platform:alert-failed-logins` | tiap jam |

### Kunci AI

`AI_FREE_TIER_KEY` terisi di `.env` dengan provider **SumoPod** (`gpt-4o-mini`). Batas harian kini **mengikuti paket** (Free 5, Paid 1 15, Paid 2 30, Paid 3 60) ditambah blok yang dibeli dan promo yang sedang berlaku; bawaan platform di `config/ai.php` hanya dipakai bila paket tidak menyetelnya. Hanya analisis yang **berhasil** yang memotong kuota, dan jatah tidak menumpuk ke hari berikutnya.

---

## 9. Peta Halaman Lengkap

### Pemilik Usaha — `/owner/*` (urutan sidebar)

- **Beranda** · **Antrian Dapur** (bila menyala)
- **Atur Menu:** Kategori · Produk (pencarian, + varian) · Stok (kartu status, restock, penyesuaian, riwayat per varian, mutasi) · Modifier
- **Penjualan & Promosi:** Saran Jual (laporan) · Aturan Saran Jual · Aturan Diskon
- **Keuangan:** Laporan Harian · Laporan Bulanan (+ CSV) · Transaksi (+ detail, void, kas negatif) · Sesi Kas (+ persetujuan uang keluar) · Koreksi Offline · Pembayaran · AI Analysis
- **Tim & Akses:** Staf · Role
- **Pengaturan:** Profil & Merek (nama, alamat, telepon, jenis usaha, logo) · Cara Kerja Sistem (paket setelan awal, mode & fitur outlet, batas untung minimum, batas uang keluar, aturan kerja kasir, jenis saran jual, pajak, biaya layanan) · Integrasi & Kredensial (AI, token MCP, Link Data untuk AI) · Langganan & Tagihan

Identitas akun, **Buka Kasir**, dan **Keluar** ada di dropdown avatar topbar.

### Kasir — `/cashier/*`

POS (saran jual, identitas pesanan, split bill, foto bukti, harga khusus owner) · **Antrian Dapur** (bila menyala) · Riwayat Transaksi (+ Tagihan Terbuka) · Edit Transaksi · Tunda Bayar / Tagihan Terbuka (topbar) · Kas (buka sesi, catat uang keluar / setoran masuk) · Tutup Kas (halaman sendiri) · Rekap Kas · Sinkronisasi Offline (PWA)

### Langganan Tenant — `/langganan`

Tab **Ringkasan** (paket, isi paket, kelas harga / Harga Adaptif) · **Tagihan** (bayar lewat modal, unggah bukti) · **Kapasitas** (kursi & kuota AI, owner saja) · Pengajuan Harga Adaptif (`/langganan/harga-adaptif`) · Persetujuan (`/langganan/persetujuan`) · Instruksi bayar (`/langganan/tagihan/{invoice}/bayar`, `/langganan/pembayaran/{attempt}`) · Aktifkan kembali (saat `suspended`)

### Platform Console — `/platform` (urutan sidebar)

- **Beranda**
- **Klien:** Daftar Tenant → rincian bertab (Ikhtisar · Langganan · Tagihan · Kapabilitas + kunci pajak · Omzet)
- **Komersial:** Langganan & Tagihan · Aturan Harga (paket, tangga, paket tujuan) · Kuota AI
- **Sistem:** Jejak Audit · Akun Platform · Keamanan Akun (2FA)

**Modul platform yang bisa diberikan ke staf:** `tenants`, `subscriptions`, `payments`, `pricing_rules` (sensitif), `revenue_data` (sensitif), `audit_logs` (sensitif), `ai_quota` (sensitif). Keamanan Akun tidak bermodul — setiap akun platform bisa mengamankan akunnya sendiri.

**Modul tenant (RBAC untuk staf):** `pos`, `cash_drawer`, `products`, `stock`, `reports`, `payment_methods` (sensitif), `ai_analysis` (sensitif).

**Setelan outlet di paket setelan awal:** antrian dapur, pesan mandiri, analisis AI, foto bukti pembayaran, saran jual wajib dijawab, identitas pesanan. **Cara berjualan:** warung/kafe menetap, gerai acara & bazar, toko retail, jasa, belum yakin.

### Publik

`/` landing · `/harga` tarif kedua jalur · `/register` · `/dokumentasi` hub dua jalur · `/api-docs` referensi API mobile · `/up` health check

### Antarmuka non-web

API self-order (`POST /api/v1/orders`, `POST /api/v1/upsell/suggestions`, `PATCH /api/v1/orders/{transaction}/fulfillment`) · API mobile v1 (`/api/v1/mobile/*`) · webhook pembayaran (gateway tiruan) · webhook Xendit · MCP server `/mcp/business` (token dari Integrasi & Kredensial) · Link Data untuk AI `GET /api/v1/connector/summary?token=…`

---

## Dokumentasi Terkait

- **Garis besar sistem & arsitektur:** [README.md](README.md)
- **Riwayat perubahan:** [docs/CHANGELOG.md](docs/CHANGELOG.md) — mulai dari `## Indeks Entri`, jangan dibaca utuh
- **Isu terbuka & hutang teknis:** [docs/BACKLOG.md](docs/BACKLOG.md)
- **Isu yang sudah selesai:** [docs/BACKLOG-ARCHIVE.md](docs/BACKLOG-ARCHIVE.md)
- **Dokumentasi teknis:** [docs/SAPI_Technical_Doc_v1.1.md](docs/SAPI_Technical_Doc_v1.1.md)
- **Audit keamanan:** [docs/SAPI-Security-Audit_v1.0.md](docs/SAPI-Security-Audit_v1.0.md)
- **Dokumen fase:** `docs/phases-1/`, `docs/phases-2/`
