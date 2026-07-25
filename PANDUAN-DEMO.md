# SAPI — Panduan Demo & Petunjuk Penggunaan

Berkas ini adalah panduan operasional untuk **menjalankan dan mendemokan** SAPI: kredensial akun, isi data contoh, urutan alur demo, dan batasan yang perlu diketahui sebelum bicara di depan penonton. Untuk penjelasan garis besar sistem dan arsitekturnya, lihat [README.md](README.md).

> **Peringatan:** semua kredensial di berkas ini adalah **kredensial lingkungan lokal/demo**. Jangan pernah dipakai di server sungguhan. `PlatformUserSeeder` sendiri akan menolak berjalan dengan kata sandi bawaan di luar `local`/`testing` — lihat [§7](#7-batasan-yang-perlu-diketahui-sebelum-demo).

---

## Daftar Isi

1. [Dua Lapis Autentikasi](#1-dua-lapis-autentikasi)
2. [Kredensial Demo](#2-kredensial-demo)
3. [Isi Data Tiap Tenant](#3-isi-data-tiap-tenant)
4. [Menyiapkan & Me-reset Data Demo](#4-menyiapkan--me-reset-data-demo)
5. [Kondisi Langganan & Penagihan](#5-kondisi-langganan--penagihan)
6. [Alur Demo yang Disarankan](#6-alur-demo-yang-disarankan)
7. [Batasan yang Perlu Diketahui Sebelum Demo](#7-batasan-yang-perlu-diketahui-sebelum-demo)
8. [Peta Halaman Lengkap](#8-peta-halaman-lengkap)

---

## 1. Dua Lapis Autentikasi

Sistem ini punya **dua tabel pengguna yang benar-benar terpisah**, bukan satu tabel dengan flag peran:

| Lapis | Tabel | Halaman masuk | Untuk siapa |
|---|---|---|---|
| **Tenant** | `users` | `/login` | Pemilik usaha & stafnya — POS, produk, stok, laporan, AI |
| **Platform** | `platform_users` | `/platform/login` | Pemilik SaaS — kelola klien, langganan, tagihan, aturan harga, audit |

Akun platform **sengaja tidak punya `tenant_id`** dan tidak memakai spatie/permission; izinnya dikelola lewat tabel tersendiri `platform_user_modules`. Konsekuensi praktisnya saat demo: **jangan mencoba masuk `/platform` dengan `owner@sapi.test`** — tidak akan bisa, dan itu memang desainnya. Sebaliknya, tamu yang sesinya habis di area `/platform` diarahkan ke `platform.login`, bukan ke halaman masuk tenant.

---

## 2. Kredensial Demo

Semua kata sandi di bawah: **`password`**

### Platform Console — `/platform/login`

| Email | Kata sandi | Keterangan |
|---|---|---|
| `platform@sapi.test` | `password` | `is_owner = true` → akses penuh semua modul platform |

Akun ini dibuat oleh `PlatformUserSeeder`, yang **tidak dipanggil dari `DatabaseSeeder`** — harus dijalankan tersendiri (lihat [§4](#4-menyiapkan--me-reset-data-demo)).

Kredensialnya bisa dialihkan lewat `.env`: `PLATFORM_ADMIN_EMAIL`, `PLATFORM_ADMIN_PASSWORD`, `PLATFORM_ADMIN_NAME`.

### Tenant 1 — Kopi Nusantara (`kopi-nusantara`)

| Email | Kata sandi | Peran | Modul RBAC |
|---|---|---|---|
| `owner@sapi.test` | `password` | owner | semua (owner tidak dibatasi modul) |
| `kasir@sapi.test` | `password` | cashier | role **"Kasir + Gudang"** → `pos`, `cash_drawer`, `stock` |
| `gudang@sapi.test` | ⚠️ tidak diketahui | cashier | role "Kasir + Gudang" |

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

## 3. Isi Data Tiap Tenant

| | **Kopi Nusantara** (t1) | **Kopi Story** (t2) |
|---|---|---|
| Karakter | Sampel kecil, untuk uji fitur | Studi kasus realistis (gaya kedai kopi susu kekinian) |
| Seeder | `DatabaseSeeder` + `DemoTransactionSeeder` | `CafeStudyCaseSeeder` |
| Produk / varian | 4 / 6 | 20 / 36 |
| Kategori | Kopi, Non-Kopi, Makanan | Kopi Susu, Coffee, Non-Coffee, Tea, Pastry & Snack |
| Transaksi | ±1.600 (90 hari) | ±3.900 (sejak 1 Jan 2026) |
| Metode bayar | Cash, QRIS, Transfer BCA | Cash, QRIS, Debit Card |
| Modifier | Temperature (wajib), Sugar Level, Add-ons | Sugar Level, Add-ons (+Boba) |
| Sesi kas | **tidak ada** | satu sesi per hari, lengkap dengan selisih |
| Role RBAC kustom | ✅ "Kasir + Gudang" | — |
| Staf | 3 (owner + 2 kasir) | 2 (owner + kasir) |

**Rekomendasi: pakai Kopi Story sebagai panggung utama.** Datanya membentang 7 bulan, omzet harian ditarget Rp1–2 juta (akhir pekan lebih tinggi), jam sibuk dimodelkan dengan bobot per jam, restock bulanan tercatat sebagai `StockMovement`, dan sesi kas hariannya sengaja disebar tiga kasus — **pas (±55%), minus (±23%), plus (±22%)** — supaya demo rekonsiliasi kas punya bahan nyata.

Pakai **Kopi Nusantara** untuk demo RBAC: hanya tenant ini yang punya role kustom dan staf ketiga.

Satu catatan kecil: Croissant Plain di Kopi Nusantara diberi `expiry_date` H+3 dari waktu seed, aslinya untuk memperlihatkan badge *near-expiry*. Kalau seed sudah lama, tanggal itu terlewat dan badge-nya tampil sebagai **kedaluwarsa**, bukan "mendekati".

---

## 4. Menyiapkan & Me-reset Data Demo

### Menjalankan aplikasi

```bash
composer run dev
```

Perintah ini menyalakan tiga proses sekaligus: `artisan serve`, `queue:listen`, dan `npm run dev`.

**Antrean wajib hidup.** Analisis AI berjalan sebagai job (`RunAiAnalysisJob`); tanpa worker, hasil analisis tidak akan pernah muncul dan demo tampak menggantung. Kalau `.env` baru diubah, **worker harus di-restart** — proses lama masih memegang konfigurasi lama.

Port di-pin **8001** lewat `.claude/launch.json`. Ini disengaja: `artisan serve` membaca `SERVER_PORT` dan akan kembali ke 8000 kalau dibiarkan otomatis, lalu bentrok dengan proyek lain di mesin yang sama.

### Menyegarkan data agar sampai hari ini

Ini langkah yang **paling mudah terlupakan dan paling terlihat**. Kedua seeder transaksi menyemai sampai `now()` pada saat dijalankan. Kalau seed terakhir sudah berminggu-minggu lalu, kartu "Omzet Hari Ini", "7 Hari Terakhir", dan sesi kas hari ini akan **kosong** — tepat di halaman pertama yang dilihat penonton.

Kopi Story — **idempotent**, data transaksional di-reset lebih dulu jadi aman dijalankan berkali-kali:

```bash
php artisan db:seed --class=CafeStudyCaseSeeder
```

Kopi Nusantara — ⚠️ **menambah, tidak me-reset**. Menjalankannya dua kali tanpa `migrate:fresh` membuat transaksi menumpuk dan total omzet jadi ganda:

```bash
php artisan db:seed --class=DemoTransactionSeeder
```

### Reset bersih total

```bash
php artisan migrate:fresh --seed && php artisan db:seed --class=PlatformUserSeeder && php artisan db:seed --class=CafeStudyCaseSeeder && php artisan db:seed --class=DemoTransactionSeeder
```

Urutannya penting, dan `migrate:fresh` bukan pilihan gaya:

- `DatabaseSeeder` memakai `Tenant::create()`, bukan `firstOrCreate` — **tidak idempotent**. Menjalankannya di atas database yang sudah terisi akan gagal karena slug duplikat.
- `PlatformUserSeeder` tidak ikut terpanggil oleh `--seed`; harus disebut sendiri.
- `DemoTransactionSeeder` bergantung pada produk & metode bayar milik `kopi-nusantara`, jadi harus setelah `DatabaseSeeder`.

### Ringkasan seeder

| Seeder | Isi | Idempotent? |
|---|---|---|
| `PermissionCatalogSeeder` | 7 permission modul tenant (global) | ✅ |
| `DatabaseSeeder` | Tenant Kopi Nusantara + 2 user + menu + modifier + metode bayar + role contoh | ❌ |
| `DemoTransactionSeeder` | 90 hari transaksi untuk Kopi Nusantara | ❌ (menumpuk) |
| `CafeStudyCaseSeeder` | Tenant Kopi Story lengkap: menu, transaksi sejak 1 Jan 2026, restock bulanan, sesi kas harian | ✅ |
| `PlatformUserSeeder` | Akun pemilik platform | ✅ |
| `ProductionSeeder` | Tenant "default" + akun admin dari `ADMIN_EMAIL`/`ADMIN_INITIAL_PASSWORD` — **untuk produksi, bukan demo** | ✅ |

---

## 5. Kondisi Langganan & Penagihan

Kedua tenant demo: plan **"Dasar"**, jalur harga **normal**, `trial_ends_at = null`. Yang terakhir disengaja — demo dianggap sudah berlangganan supaya tidak kedaluwarsa sebulan setelah seed dijalankan dan merusak demo di kemudian hari.

Plan "Dasar" bawaan bernilai `base_price = 0`, `included_seats = 1`, `extra_seat_price = 0`. Akibatnya **semua tagihan Rp0 dan tabel `invoices` kosong**. Kalau demo perlu memperagakan alur tagihan dan verifikasi bukti bayar, harga plan harus diisi dulu dari `/platform/pricing-rules` (bagian Plan).

**Aturan harga jalur subsidi** sudah terisi empat bracket:

| Label | Rentang omzet bulanan | Harga |
|---|---|---|
| A | Rp0 – 2 juta | Rp10.000 |
| B | Rp2 – 5 juta | Rp25.000 |
| C | Rp5 – 15 juta | Rp50.000 |
| D | di atas Rp15 juta | Rp100.000 |

Belum ada tenant di jalur subsidi, jadi belum ada metrik omzet terkumpul. Untuk mendemokan `/platform/revenue/{tenant}`, tenant harus memberi persetujuan lebih dulu di `/langganan/persetujuan`, lalu omzetnya dihitung job bulanan (`subscriptions:compute-revenue`, tanggal 1 pukul 04:00).

### Aturan yang mengikat dan sebaiknya disebutkan saat demo

- **Seat = pengguna aktif**, tetapi tagihan mengikuti `seat_high_water` (puncak dalam periode). Menonaktifkan staf di akhir bulan tidak menghemat biaya — ini disengaja.
- **Siklus hidup:** trial 30 hari → `grace` **hanya-baca** 30 hari → `suspended` (angkanya di `config/subscription.php`). Di keadaan `grace`, halaman tetap terbuka dan data lama tetap bisa diunduh; hanya permintaan yang mengubah data yang ditolak. Halaman `/langganan` dan logout **selalu** terbuka di keadaan apa pun — menutup jalan keluar berarti tenant tak akan pernah bisa keluar, termasuk dengan membayar.
- **Daftar tenant di panel platform menampilkan bracket omzet, bukan angka rupiah persis.** Angka persis hanya ada di `/platform/revenue/{tenant}`, dan setiap kunjungan tercatat sebagai kejadian `sensitive` tanpa deduplikasi.
- **Mencabut persetujuan subsidi** menghapus metrik seketika, tapi tarif subsidi tetap berlaku sampai akhir periode.
- **Pindah jalur harga** dibatasi minimum tiga bulan sekali.
- **Retensi data omzet** 24 bulan.

### Perhatian saat demo

Kopi Nusantara punya **3 staf aktif dengan `seats = 3`**. Menambah staf keempat saat demo akan memicu alur upgrade seat. Itu bisa jadi demo yang bagus — atau kejutan yang tidak enak. Tentukan dulu apakah Anda sengaja.

---

## 6. Alur Demo yang Disarankan

Urutan ini bergerak dari yang paling konkret ke yang paling abstrak, dan tidak pernah memerlukan mundur untuk menyiapkan data.

### Babak 1 — Kasir (5 menit) · `kasir@kopistory.test`

1. Masuk sebagai kasir → langsung diminta **buka sesi kas** (kas awal Rp500.000).
2. Buka **POS**: pilih produk berkategori, pilih varian, terapkan modifier (Sugar Level, Add-ons berbayar seperti Extra Shot/Boba).
3. Bayar tunai → perlihatkan perhitungan kembalian; ulangi dengan QRIS.
4. Buat satu **open bill**, lalu lunasi dari **Riwayat Transaksi**.
5. **Edit transaksi** yang sudah selesai → tunjukkan stok ikut terkoreksi dan perubahannya tercatat.
6. **Tutup kas** dua langkah: ringkasan sistem lebih dulu, baru konfirmasi hitungan fisik. Masukkan angka yang sengaja berbeda untuk memperlihatkan selisih.

### Babak 2 — Pemilik Usaha (7 menit) · `owner@kopistory.test`

1. **Dashboard** — omzet, transaksi, produk terlaris, tren. (Pastikan data sudah disegarkan; lihat [§4](#4-menyiapkan--me-reset-data-demo).)
2. **Laporan Harian** dengan filter tanggal, dan **Rekap Kas** — di sini 192 sesi Kopi Story terbayar: pas, minus, dan plus lengkap dengan catatan alasannya.
3. **Stok** — restock, penyesuaian, riwayat per varian, dan mutasi stok. Tunjukkan bahwa restock bulanan Januari–hari ini semuanya terekam.
4. **Analisis AI** — kirim satu analisis, tunjukkan job diproses lalu hasilnya muncul. Batas 5 analisis/hari per tenant.
5. **Pengaturan → Token MCP** — jelaskan bahwa owner bisa menyambungkan Claude Desktop ke data bisnisnya sendiri, dan itu memakai kuota AI *milik owner*, bukan kuota aplikasi.

### Babak 3 — RBAC (3 menit) · `owner@sapi.test` (pindah ke Kopi Nusantara)

1. **Role & Modul** — tunjukkan role "Kasir + Gudang" dengan `pos`, `cash_drawer`, `stock`.
2. Buat role baru **tanpa** `stock` (misalnya hanya `pos` + `cash_drawer`), tetapkan ke seorang staf.
3. Masuk sebagai staf itu di jendela lain → menu **Stok hilang dari navigasi**, dan mengetik `/owner/stock` langsung tetap **ditolak**. Poin pentingnya: gerbangnya di server, bukan cuma menyembunyikan menu.

> Perhatikan arahnya: cabut `stock`, jangan cabut `pos`. Rute kasir sengaja tidak digerbang per modul — lihat [§7](#satu-lubang-rbac-yang-sengaja-dibiarkan).

### Babak 4 — Platform Console (7 menit) · `platform@sapi.test`

1. **Dashboard** platform, lalu **Daftar Tenant** — tekankan bahwa yang tampil **bracket**, bukan angka omzet persis. Ini keputusan privasi, bukan keterbatasan.
2. **Langganan** — keadaan tiap tenant, seat, periode.
3. **Aturan Harga** — empat bracket subsidi A–D.
4. **Data Omzet Subsidi** — buka satu tenant, lalu segera pindah ke **Log Audit** dan tunjukkan kunjungan tadi sudah tercatat sebagai `sensitive`. Ini demo paling meyakinkan di babak ini.
5. **Kelola Akun Platform** — buat staf platform yang hanya diberi modul `tenants`, masuk sebagai dia, tunjukkan menu lain menghilang. Tunjukkan juga bahwa **manajemen akun platform sendiri tidak ada di daftar modul yang bisa diberikan** — dijaga penanda `is_owner`, supaya staf platform tak bisa mencentangkan modul sensitif untuk dirinya sendiri.

### Babak 5 — Isolasi Tenant (2 menit, penutup)

Masuk sebagai `owner@sapi.test` dan `owner@kopistory.test` bersebelahan: produk, transaksi, laporan, semuanya terpisah total. Sebutkan bahwa isolasi ini dijaga oleh berkas uji tersendiri (`tests/Feature/TenantIsolation`, `PlatformIsolationTest`), bukan hanya oleh kedisiplinan menulis query.

---

## 7. Batasan yang Perlu Diketahui Sebelum Demo

Daftar ini ada supaya Anda tidak dikejutkan di depan penonton, dan supaya jawaban Anda jujur kalau ditanya.

### Surel tidak benar-benar terkirim

`MAIL_MAILER=log`. Akun demo sudah `email_verified_at` terisi jadi bisa langsung masuk. Tapi:

- **Mendaftar akun baru saat demo** → tautan verifikasi hanya muncul di `storage/logs/laravel.log`, harus digali manual.
- **Kedua alur reset kata sandi** (tenant dan platform) tidak berfungsi hidup.

Kalau demo perlu menyentuh salah satu alur ini, siapkan pengirim surel sungguhan lebih dulu.

### Belum terpasang

| Hal | Status | ID backlog |
|---|---|---|
| 2FA akun platform | Belum ada. Sudah ada rate limit login, jejak audit, dan pemulihan kata sandi yang tidak membocorkan keberadaan akun — tapi tetap satu faktor. **Satu-satunya isu yang masih terbuka.** | `[BL-013]` |
| Layar antrean dapur / kitchen display | **Tidak ada halamannya.** Yang ada hanya API (`POST /api/v1/orders`, `PATCH /api/v1/orders/{transaction}/fulfillment`). `PHASE-QUEUE` dan `SAPI-SelfOrder-Implementation-Plan.md` masih dokumen rencana. | — |
| BYOK (owner memakai kunci AI sendiri) | Kolomnya sudah ada (`tenants.ai_provider`, `ai_api_key`, `ai_model`) tapi alurnya ditunda; kedua tenant memakai kunci bersama aplikasi. | — |

**Jangan menjanjikan layar dapur di demo.**

### Satu lubang RBAC yang sengaja dibiarkan

Rute kasir (`/cashier/*`) **tidak** digerbang per modul — hanya `role:cashier,owner`. Akibatnya kasir yang rolenya hanya berisi `stock` tetap bisa mengetik `/cashier/pos` di peramban dan berjualan.

Ini keputusan, bukan kelalaian: `pos` dan `cash_drawer` diperlakukan sebagai **penanda menu**, bukan gerbang rute, dan menutupnya akan mengubah perilaku staf yang sudah ada — termasuk staf yang dibuat lewat pilihan "Tanpa role (POS saja)" di form tambah staf.

Konsekuensi untuk demo: **peragakan RBAC dengan mencabut modul `stock`, jangan dengan mencabut `pos`.** Modul `stock`, `products`, `reports`, `payment_methods`, dan `ai_analysis` benar-benar digerbang di server — mengakses URL-nya langsung akan ditolak, bukan hanya menunya hilang. Arah sebaliknya tidak akan meyakinkan siapa pun yang mencoba mengetik URL.

### Penjadwal harus hidup

Kalau demo menyentuh siklus hidup langganan, perhitungan omzet, pemangkasan log audit, atau peringatan login gagal, `schedule:run` harus aktif. Jadwalnya: `platform:prune-audit-logs` (03:10 harian), `subscriptions:advance-lifecycle` (03:30 harian), `platform:alert-failed-logins` (tiap jam), `subscriptions:compute-revenue` (tanggal 1, 04:00), `subscriptions:prune-metrics` (tanggal 1, 04:30).

### Kunci AI

`AI_FREE_TIER_KEY` sudah terisi di `.env` dengan provider **SumoPod**, batas **5 analisis per hari per tenant**. Kalau kuota harian sudah terpakai saat mencoba-coba sebelum demo, analisis akan ditolak — jangan menghabiskannya saat gladi bersih.

---

## 8. Peta Halaman Lengkap

### Pemilik Usaha — `/owner/*`

Dashboard · Produk (+ varian) · Kategori · Modifier · Metode Pembayaran · Stok (restock, penyesuaian, riwayat per varian, mutasi) · Laporan Harian · Transaksi (+ detail, void) · Rekap Kas · Staf · **Role & Modul (RBAC)** · Pengaturan (+ token MCP) · **Analisis AI** · Review Transaksi Offline

### Kasir — `/cashier/*`

POS · Riwayat Transaksi · Edit Transaksi · Bayar Open Bill · Sinkronisasi Offline (PWA) · Sesi Kas (buka, tutup, ringkasan)

### Langganan Tenant — `/langganan`

Status langganan · Tambah pengguna · Unggah bukti bayar · Persetujuan jalur subsidi · Cabut subsidi

### Platform Console — `/platform`

Dashboard · Daftar Tenant · Langganan · Tagihan (+ verifikasi/tolak bukti) · Data Omzet Subsidi per tenant · Aturan Harga · Log Audit · Kelola Akun Platform

**Modul platform yang bisa diberikan ke staf:** `tenants`, `subscriptions`, `payments`, `pricing_rules` (sensitif), `revenue_data` (sensitif), `audit_logs` (sensitif).

**Modul tenant (RBAC untuk staf):** `pos`, `cash_drawer`, `products`, `stock`, `reports`, `payment_methods` (sensitif), `ai_analysis` (sensitif).

### Publik

`/` landing page · `/api-docs` referensi API mobile · `/up` health check

---

## Dokumentasi Terkait

- **Garis besar sistem & arsitektur:** [README.md](README.md)
- **Riwayat perubahan:** [docs/CHANGELOG.md](docs/CHANGELOG.md)
- **Isu terbuka & hutang teknis:** [docs/BACKLOG.md](docs/BACKLOG.md)
- **Dokumentasi teknis:** [docs/SAPI_Technical_Doc_v1.1.md](docs/SAPI_Technical_Doc_v1.1.md)
- **Audit keamanan:** [docs/SAPI-Security-Audit_v1.0.md](docs/SAPI-Security-Audit_v1.0.md)
- **Dokumen fase:** `docs/phases-1/`, `docs/phases-2/`
