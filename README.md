# SAPI — Smart AI POS & Inventory

SAPI adalah aplikasi **Point of Sale dan manajemen inventori multi-tenant** untuk usaha kuliner skala kecil–menengah, dibangun di atas Laravel 12 + Inertia + Vue 3. Selain sisi operasional (kasir, stok, laporan), SAPI juga menyediakan **panel pemilik SaaS** untuk mengelola klien dan langganan, serta **analisis bisnis berbasis AI** untuk pemilik usaha.

> 📘 **Mencari kredensial demo, isi data contoh, atau urutan alur demo?** Semuanya ada di [PANDUAN-DEMO.md](PANDUAN-DEMO.md). Berkas ini fokus pada penjelasan garis besar sistem.

---

## Daftar Isi

- [Gambaran Umum](#gambaran-umum)
- [Stack Teknologi](#stack-teknologi)
- [Arsitektur](#arsitektur)
- [Fitur](#fitur)
- [Model Bisnis & Langganan](#model-bisnis--langganan)
- [Kontrol Akses](#kontrol-akses)
- [API](#api)
- [MCP Server](#mcp-server)
- [Analisis AI](#analisis-ai)
- [Instalasi](#instalasi)
- [Pengujian](#pengujian)
- [Struktur Direktori](#struktur-direktori)
- [Dokumentasi](#dokumentasi)

---

## Gambaran Umum

SAPI melayani **tiga jenis pengguna** dengan kebutuhan yang berbeda:

| Pengguna | Area | Kebutuhan utama |
|---|---|---|
| **Kasir** | `/cashier/*` | Cepat, tahan gangguan jaringan, sesi kas yang bisa dipertanggungjawabkan |
| **Pemilik usaha** | `/owner/*`, `/langganan` | Kendali menu & stok, laporan yang bisa dipercaya, wawasan bisnis, atur staf |
| **Pemilik SaaS** | `/platform/*` | Kelola klien & langganan tanpa mengintip data operasional mereka |

Pembagian ini bukan sekadar navigasi — ia tercermin di lapisan autentikasi, gerbang izin, dan bahkan keputusan tentang **data apa yang boleh dilihat siapa**.

---

## Stack Teknologi

| Lapis | Teknologi |
|---|---|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | Inertia.js v2 + Vue 3, Vite, Tailwind CSS v4 |
| Database | MySQL / MariaDB |
| Autentikasi API | Laravel Sanctum |
| Izin (tenant) | spatie/laravel-permission (fitur *teams*, di-scope per tenant) |
| Antrean & cache | driver `database` |
| Pengujian | Pest 3 (452 uji dalam 55 berkas) |
| AI | SumoPod (bawaan), OpenAI, Gemini, Anthropic |
| MCP | laravel/mcp |
| Offline | Service worker + `manifest.webmanifest` (PWA) |

---

## Arsitektur

### Dua lapis autentikasi yang terpisah

Ini keputusan struktural paling penting dalam sistem ini:

```
users              → /login           → tenant (pemilik usaha & stafnya)
platform_users     → /platform/login  → pemilik SaaS
```

Akun platform **sengaja tidak punya `tenant_id`** dan tidak memakai spatie/permission. Alasannya bukan preferensi: pivot `model_has_roles` menuntut `tenant_id` non-null karena kolom itu bagian dari primary key, sementara akun platform tidak berada di dalam tenant mana pun. Izinnya karena itu dikelola lewat tabel tersendiri, `platform_user_modules`.

### Isolasi tenant

Data bisnis (produk, transaksi, stok, sesi kas, dst.) di-scope otomatis lewat trait `BelongsToTenant` + `TenantScope` berbasis `auth()->user()->tenant_id`.

Tiga model **sengaja tidak** memakai trait itu: `Subscription`, `Invoice`, dan `TenantMonthlyMetric`. Ketiganya dibaca dari panel platform, dan akun platform bernilai `tenant_id = null` — kalau di-scope, hasilnya justru selalu kosong tepat di tempat yang membutuhkannya. Isolasi keduanya dijaga uji tersendiri: `tests/Feature/TenantIsolation/` dan `PlatformIsolationTest`.

### Middleware bertenant sebagai grup, bukan alias

Grup `tenant` dan `tenant.api` menggabungkan tiga gerbang yang tak boleh terpisah:

```
EnsureTenant → EnsureEmailVerified → EnsureSubscriptionActive
```

Urutannya disengaja: akun yang alamatnya belum terbukti tidak perlu sampai ke pertanyaan apakah langganannya masih berlaku. Dijadikan grup, bukan alias, agar tidak ada grup rute baru yang lupa menyertakan salah satunya.

---

## Fitur

### Kasir

- **POS** dengan kategori, varian, dan modifier (wajib/opsional, pilihan tunggal/banyak, add-on berbayar)
- **Open bill** — simpan sebagai pending, lunasi kemudian
- **Multi metode bayar** — tunai (dengan perhitungan kembalian), QRIS statis, transfer bank
- **Edit transaksi** yang sudah selesai, dengan **koreksi stok otomatis** dan jejak perubahan (`transaction_edits`)
- **Sesi kas** — buka kas, tutup kas dua langkah (ringkasan sistem lebih dulu, baru konfirmasi hitungan fisik), selisih terhitung otomatis
- **Mode offline (PWA)** — transaksi tetap bisa dibuat saat jaringan mati, lalu disinkronkan. Transaksi yang bermasalah saat sinkronisasi masuk ke **Review Offline** milik owner, bukan ditelan diam-diam.
- **Void transaksi** (owner-only)

### Pemilik Usaha

- **Dashboard** — omzet, jumlah transaksi, rata-rata nota, produk terlaris, tren
- **Master data** — produk, varian, kategori, group modifier, metode pembayaran
- **Stok** — restock, penyesuaian, riwayat per varian, mutasi stok lengkap, badge stok rendah & mendekati kedaluwarsa
- **Laporan** — laporan harian dengan filter tanggal, riwayat transaksi + detail, rekap sesi kas
- **Profit** — perhitungan margin berbasis `cost_price` per varian, termasuk proyeksi periode berikutnya
- **Staf & RBAC** — kelola staf, susun role dengan modul terpilih (lihat [Kontrol Akses](#kontrol-akses))
- **Analisis AI** — lihat [Analisis AI](#analisis-ai)
- **Token MCP** — sambungkan AI client milik sendiri ke data bisnis (lihat [MCP Server](#mcp-server))

### Pemilik SaaS (Platform Console)

- **Daftar tenant** — menampilkan **bracket omzet, bukan angka rupiah persis**. Angka persis hanya ada di halaman omzet per tenant, dan setiap kunjungan tercatat sebagai kejadian `sensitive` tanpa deduplikasi.
- **Langganan** — keadaan, seat, periode tiap klien
- **Tagihan** — verifikasi atau tolak bukti pembayaran
- **Aturan harga** — plan dan bracket harga jalur subsidi
- **Log audit** — dengan retensi dan pemangkasan terjadwal
- **Kelola akun platform** — pemberian modul per staf, dijaga penanda `is_owner`
- **Peringatan otomatis** — surel ke pemilik saat percobaan masuk gagal menumpuk; penandaan pendaftaran berulang dari IP yang sama

---

## Model Bisnis & Langganan

Langganan berjalan **dua jalur**: harga normal dan **harga subsidi untuk UMKM** yang mengikuti omzet.

### Siklus hidup

```
trial (2 bulan) ──┐
                  ├──> grace (bertingkat, 30 hari) ──> suspended
active (periode) ─┘
```

Angka-angkanya kebijakan komersial, jadi tinggal di `config/subscription.php` (`trial_months`, `grace_days`, `grace_intensive_from_day`, `grace_readonly_from_day`), bukan sebagai konstanta di kode.

Masa gratisnya dihitung dalam **bulan**, bukan hari, karena jangkar tanggal tagih diambil dari **tanggal daftar**: yang mendaftar tanggal 7 ditagih tiap tanggal 7. Menghitungnya dalam hari menggeser jangkarnya sekali dan permanen.

Di keadaan `grace`, halaman tetap terbuka dan data lama tetap bisa dibuka serta diunduh — hanya permintaan yang **mengubah** data yang ditolak. Menyandera data pelanggan bukan alat penagihan yang sah; menahan layanan baru adalah. Halaman `/langganan` dan logout **selalu** terbuka di keadaan apa pun, karena menutup jalan keluar berarti tenant tak akan pernah bisa keluar dari keadaan itu — termasuk dengan membayar.

### Prabayar, dan harganya dari bulan lalu

**Bayar dulu, baru pakai.** Tagihan sebuah periode terbit **sebelum** periode itu dimulai (`invoice_lead_days`, H-7) dan dilunasi di awal, bukan ditagih di belakang atas pemakaian yang sudah lewat. Itu sekaligus menjelaskan kenapa tarifnya tidak bisa diambil dari bulan yang sedang ditagih: pada saat tagihannya terbit, bulan itu belum terjadi.

Karena itu aturannya satu baris, dan berlaku untuk seluruh jalur harga:

> **Tarif periode P dihitung dari omzet bulan sebelum P** — bukan dari omzet P, dan bukan dari omzet saat tagihannya dibayar.

Contoh yang menjadi acuan (keputusan pemilik 2026-08-19, `[BL-056]`):

| | |
|---|---|
| Daftar | Juli |
| Bulan 1–2 | Juli & Agustus — **gratis**, tidak ada tagihan |
| Bulan 3 | September — tagihan pertama, tarifnya dari **omzet Agustus** |
| Bulan 4 | Oktober — tarifnya dari omzet September |

Dua akibat yang sengaja dinyatakan, karena keduanya pernah ditanyakan:

- **Pengajuan Harga Adaptif berlaku untuk periode berikutnya, bukan periode yang sedang ditagih.** Tagihan yang sudah terbit tidak pernah dihitung ulang; nominal dan `pricing_context`-nya membeku di saat terbit (`Invoice::amount`). Tenant yang mengajukan di tengah tenggat tetap melunasi tagihan yang ada, dan keringanannya muncul di tagihan berikutnya. Alternatifnya — pengajuan yang memotong tunggakan berjalan — akan menjadikan pengajuan sebagai jalan keluar dari tagihan mana pun.
- **Omzet yang dinilai adalah omzet periode yang tertunggak, bukan bulan berjalan.** `pricingAsOf()` memakai awal bulan periode tagihan dan `current_period_end` membeku selama tenant belum membayar, jadi tenant yang menunggak dari Agustus tidak diperiksa dengan omzet Oktober.

Penghitung omzetnya sendiri (`subscriptions:compute-revenue`) berjalan **tanggal 1 pukul 04:00** atas bulan yang baru saja tutup — lihat [Tugas terjadwal](#tugas-terjadwal). Urutan "hitung dulu, tagih kemudian" itu bagian dari aturan di atas, bukan detail penjadwalan yang bebas digeser.

> ⚠️ **Aturan di atas belum sepenuhnya ditegakkan kode hari ini.** Penerbit tagihan berjalan 03:30 sementara penghitung omzet 04:00, dan tagihan terbit H-7 — sehingga tenant yang jangkar tagihnya jatuh di **tanggal 1–8** ditagih dari omzet **dua** bulan sebelumnya, bukan satu. Perinciannya, dampaknya, dan pilihan perbaikannya ada di `[BL-080]` (`docs/BACKLOG.md`). Jangan membaca bagian ini sebagai gambaran perilaku yang berjalan sampai entri itu ditutup.

### Seat

Seat dihitung dari **pengguna aktif**, tetapi yang ditagih adalah `purchased_extra_seats` — seat yang benar-benar **dibeli**, bukan puncak pemakaian. Melepas seat berlaku di akhir periode, bukan seketika: kursinya masih boleh dipakai sampai tanggal itu, dan tenant diberi tahu tanggalnya.

`seat_high_water` masih ada tapi perannya sudah bukan penagihan — ia hanya dimensi penetapan harga (`active_seats`), dan hari ini belum dipakai satu pun aturan harga.

### Jalur subsidi

Tenant yang memilih jalur subsidi memberi **persetujuan eksplisit** agar omzet bulanannya dihitung, lalu tarifnya mengikuti bracket harga yang berlaku. Aturan yang mengikat:

- Mencabut persetujuan **menghapus metrik seketika**, tapi tarif subsidi tetap berlaku sampai akhir periode.
- Pindah jalur harga dibatasi **minimum tiga bulan sekali**.
- Retensi data omzet **24 bulan**, dipangkas otomatis.
- Job penghitung omzet menyaring **dua** hal sekaligus: jalur harga tenant **dan** persetujuan yang masih aktif.

Dokumen persetujuannya terpisah per jalur (`resources/consents/`) dan **tidak pernah disunting di tempat** — versi baru berarti berkas baru plus kenaikan nomor versi di config. Menyunting teks yang sudah disetujui akan membuat catatan persetujuan seseorang menunjuk ke kalimat yang tidak pernah mereka baca.

### Tugas terjadwal

| Perintah | Jadwal |
|---|---|
| `platform:prune-audit-logs` | harian, 03:10 |
| `subscriptions:advance-lifecycle` | harian, 03:30 |
| `platform:alert-failed-logins` | tiap jam |
| `subscriptions:compute-revenue` | tanggal 1, 04:00 |
| `subscriptions:prune-metrics` | tanggal 1, 04:30 |

`schedule:run` harus aktif di produksi agar semua ini berjalan.

---

## Kontrol Akses

Ada **tiga mekanisme berbeda**, masing-masing untuk pertanyaan yang berbeda:

| Mekanisme | Alias | Pertanyaan yang dijawab |
|---|---|---|
| `EnsureRole` | `role` | Apakah dia owner atau kasir? |
| spatie permission | `permission`, `permission.api` | Apakah role-nya diberi modul ini? |
| `EnsurePlatformModule` / `EnsurePlatformOwner` | `platform.can`, `platform.owner` | Apakah akun platform ini boleh membuka modul ini? |

Perhatikan bahwa alias `role` **bukan** milik spatie — ia gerbang enum owner/kasir milik aplikasi. Gerbang modul memakai `permission:`.

### Modul tenant

`pos` · `cash_drawer` · `products` · `stock` · `reports` · `payment_methods` (sensitif) · `ai_analysis` (sensitif)

Owner tidak dibatasi modul. Untuk staf, owner menyusun role sendiri dari daftar ini di halaman **Role & Modul**. Modul bertanda sensitif tidak dicentang otomatis di UI.

### Modul platform

`tenants` · `subscriptions` · `payments` · `pricing_rules` (sensitif) · `revenue_data` (sensitif) · `audit_logs` (sensitif)

`tenants` (melihat daftar klien) sengaja dipisah dari `revenue_data` (melihat omzet klien jalur subsidi) — keduanya kewenangan yang berbeda.

**Manajemen akun platform sendiri tidak ada di daftar ini.** Ia dijaga penanda `is_owner`, bukan modul yang bisa diberikan — kalau grantable, staf platform bisa mencentangkan `revenue_data` untuk dirinya sendiri.

Katalog modul ada di `config/rbac.php` (tenant) dan `config/platform-rbac.php` (platform), masing-masing sebagai satu sumber kebenaran untuk gerbang route, factory, share Inertia, dan filter navigasi.

---

## API

Prefix endpoint consumer adalah `/api/v1`. Autentikasi memakai bearer token Sanctum.

### Mobile POS

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/mobile/login` |
| `POST` | `/api/v1/mobile/logout` |
| `GET` | `/api/v1/mobile/tenant/profile` |
| `GET` | `/api/v1/mobile/products` |
| `GET` | `/api/v1/mobile/cash-drawer/status` |
| `POST` | `/api/v1/mobile/cash-drawer/open` |
| `POST` | `/api/v1/mobile/cash-drawer/close` |
| `GET` | `/api/v1/mobile/cash-drawer/{cashDrawer}/summary` |
| `POST` | `/api/v1/mobile/transactions` |
| `GET` | `/api/v1/mobile/transactions` |
| `POST` | `/api/v1/mobile/transactions/{transaction}/pay` |
| `GET` | `/api/v1/mobile/transactions/{transaction}/receipt` |
| `POST` | `/api/v1/mobile/transactions/{transaction}/void` (owner-only) |

### Katalog & pesanan

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/products` |
| `POST` | `/api/v1/orders` |
| `PATCH` | `/api/v1/orders/{transaction}/fulfillment` |

### Non-versioned

`POST /api/xendit/webhook` — sengaja tidak diversikan agar callback eksternal tidak perlu diubah setiap kali versi API naik.

Referensi lengkap tersedia di halaman **`/api-docs`** dan di [docs/phases-2/API-DOCS-Mobile.md](docs/phases-2/API-DOCS-Mobile.md).

### Izin di Mobile API

Payload login dan `/api/v1/mobile/tenant/profile` menyertakan `permissions` (owner → `['*']`, staf → daftar modul), dihitung `User::modulePermissions()` — satu sumber untuk web dan mobile. Endpoint profil ikut memuatnya agar aplikasi bisa menyegarkan izin tanpa login ulang: token mobile bertahan berminggu-minggu, sementara owner bisa mengubah role kapan saja.

Middleware `permission.api` tersedia dan teruji, tapi **belum menggerbang endpoint apa pun** — semua endpoint mobile yang ada saat ini adalah POS, laci kas, dan riwayat kasir, yang di web pun sengaja hanya digerbang `role:cashier,owner`. `pos` dan `cash_drawer` diputuskan tetap menjadi **penanda menu**, bukan gerbang rute, di kedua sisi. Rinciannya di `[BL-003]` pada [docs/BACKLOG.md](docs/BACKLOG.md).

---

## MCP Server

MCP Server memberi **AI client milik owner** (misalnya Claude Desktop) akses **read-only** ke data bisnis tenantnya. Bedanya dengan fitur Analisis AI internal: di sini LLM yang memanggil adalah client milik owner, jadi **tidak memakai kuota AI aplikasi** — aplikasi hanya berperan sebagai sumber data.

- **Endpoint:** `POST {APP_URL}/mcp/business`
- **Auth:** bearer token Sanctum, **owner-only**
- **Isolasi:** ter-scope otomatis ke tenant pemilik token; hanya data agregat, tanpa data pelanggan
- **Rate limit:** 60 request/menit per pengguna

### Tool yang tersedia

| Tool | Fungsi | Argumen |
|---|---|---|
| `get-sales-summary` | Omzet, jumlah transaksi, rata-rata nota, produk terlaris, tren harian | `from`, `to` (opsional, `YYYY-MM-DD`) |
| `get-profit` | Profit, margin, proyeksi periode berikutnya, margin per item | `from`, `to` (opsional, `YYYY-MM-DD`) |
| `get-menu` | Produk aktif + varian (harga, sisa stok) | — |

Tanpa `from`/`to`, rentang bawaannya 30 hari terakhir.

### Cara memakai

1. Masuk sebagai **owner**, buka **Pengaturan**.
2. Di bagian **Akses MCP (AI Client)**, klik **Generate Token**, lalu **salin token** — hanya ditampilkan sekali.
3. Konfigurasikan AI client. Contoh untuk Claude Desktop (`claude_desktop_config.json`), lewat jembatan `mcp-remote`:

   ```json
   {
     "mcpServers": {
       "sapi-business": {
         "command": "npx",
         "args": [
           "mcp-remote",
           "https://APP_URL/mcp/business",
           "--header",
           "Authorization: Bearer TOKEN_ANDA"
         ]
       }
     }
   }
   ```

4. Mulai bertanya, misalnya "Berapa profit bulan ini?" — client akan memanggil tool yang sesuai.
5. Rotasi atau cabut token kapan saja dari Pengaturan. Token yang dicabut langsung tidak berlaku.

Untuk debug, MCP Inspector bawaan bisa dipakai (sertakan header `Authorization: Bearer <token owner>` saat menyambung):

```bash
php artisan mcp:inspector mcp/business
```

---

## Analisis AI

Pemilik usaha bisa meminta analisis bisnis atas datanya sendiri. Analisis dijalankan sebagai **job antrean** (`RunAiAnalysisJob`) — jadi **queue worker harus hidup**, kalau tidak hasilnya tidak akan pernah muncul.

Provider yang didukung: **SumoPod** (bawaan), OpenAI, Gemini, Anthropic — dipilih lewat `AiProviderFactory`.

Model kuota:

- **Free tier** — memakai kunci bersama milik aplikasi (`AI_FREE_TIER_KEY`), dibatasi `AI_FREE_TIER_DAILY_LIMIT` (bawaan 5) analisis per hari per tenant, dilacak di tabel `ai_usages`.
- **BYOK** — kolom `tenants.ai_provider`, `ai_api_key`, `ai_model` sudah tersedia untuk owner memakai kunci sendiri; alur UI-nya masih ditunda.

Konteks data yang dikirim ke model disusun `AiContextService` — agregat penjualan, profit, dan stok, tanpa data pelanggan.

---

## Instalasi

### Prasyarat

PHP 8.3, Composer, Node.js, MySQL/MariaDB.

### Langkah

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Sesuaikan `.env` untuk koneksi database dan, kalau fitur AI dipakai, kunci provider.

### Menjalankan (development)

```bash
composer run dev
```

Perintah ini menyalakan `artisan serve`, `queue:listen`, dan `npm run dev` sekaligus. **Queue worker wajib hidup** untuk Analisis AI; kalau `.env` diubah, worker perlu di-restart karena proses lama masih memegang konfigurasi lama.

### Data awal

- **Demo / lokal:** lihat [PANDUAN-DEMO.md](PANDUAN-DEMO.md) — di sana ada rincian tiap seeder, mana yang idempotent, dan urutan yang benar.
- **Produksi:** `php artisan db:seed --class=ProductionSeeder` (membaca `ADMIN_EMAIL` dan `ADMIN_INITIAL_PASSWORD`), lalu `php artisan db:seed --class=PlatformUserSeeder` (membaca `PLATFORM_ADMIN_*`).

### Prasyarat produksi yang mudah terlupakan

- **Pengirim surel sungguhan.** Dengan `MAIL_MAILER=log`, verifikasi surel dan **kedua** alur reset kata sandi tidak berfungsi.
- **`schedule:run` aktif**, agar siklus hidup langganan, perhitungan omzet, dan pemangkasan log berjalan.
- **Queue worker berjalan sebagai layanan**, bukan hanya `queue:listen` di terminal.
- Akun platform **belum punya 2FA** (`[BL-013]`) — pertimbangkan pembatasan akses di lapisan lain.

---

## Pengujian

```bash
php artisan test --compact
```

Filter satu berkas atau satu nama:

```bash
php artisan test --compact --filter=TransactionEdit
```

Suite-nya (452 uji dalam 55 berkas Pest) mencakup, di antaranya:

- `tests/Feature/TenantIsolation/` — kebocoran data antar tenant
- `tests/Feature/Platform/` — isolasi panel platform + arch test yang menjaga global scope tidak dibuang
- `tests/Feature/Authorization/` — gerbang role & modul
- `tests/Feature/Subscription/` — siklus hidup, seat, jalur harga
- `tests/Feature/Security/` — rate limit, penjagaan pendaftaran
- `tests/Feature/OfflineSyncTest.php` — sinkronisasi transaksi offline
- `tests/Feature/Mcp/`, `tests/Feature/Ai/` — MCP server & analisis AI

Format kode PHP:

```bash
vendor/bin/pint
```

---

## Struktur Direktori

```
app/
├── Console/Commands/     Perintah terjadwal (langganan, omzet, pemangkasan, peringatan)
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/       API consumer (mobile, katalog, pesanan)
│   │   ├── Auth/         Masuk, daftar, verifikasi surel, reset kata sandi
│   │   ├── Billing/      Langganan, persetujuan subsidi, upgrade seat
│   │   ├── Cashier/      POS, sesi kas, edit transaksi
│   │   ├── Owner/        Master data, stok, laporan, staf, role, AI, pengaturan
│   │   ├── Platform/     Panel pemilik SaaS
│   │   └── Public/       Landing page, referensi API
│   └── Middleware/       Gerbang tenant, verifikasi, langganan, modul
├── Jobs/                 Analisis AI, perhitungan omzet bulanan
├── Models/               Termasuk Scopes/TenantScope
└── Services/
    ├── Ai/               Abstraksi provider (SumoPod, OpenAI, Gemini, Anthropic)
    └── ...               Transaksi, stok, profit, langganan, harga, consent, badge

resources/js/Pages/       Halaman Inertia: Auth, Billing, Cashier, Owner, Platform, Errors
config/rbac.php           Katalog modul tenant
config/platform-rbac.php  Katalog modul platform
docs/                     CHANGELOG, BACKLOG, dokumen teknis, dokumen fase
```

---

## Dokumentasi

| Berkas | Isi |
|---|---|
| [PANDUAN-DEMO.md](PANDUAN-DEMO.md) | Kredensial demo, isi data contoh, alur demo, batasan sebelum presentasi |
| [docs/CHANGELOG.md](docs/CHANGELOG.md) | Riwayat perubahan dan keputusan arsitektur |
| [docs/BACKLOG.md](docs/BACKLOG.md) | Isu terbuka dan hutang teknis yang dilacak |
| [docs/SAPI_Technical_Doc_v1.1.md](docs/SAPI_Technical_Doc_v1.1.md) | Dokumentasi teknis |
| [docs/SAPI-Security-Audit_v1.0.md](docs/SAPI-Security-Audit_v1.0.md) | Audit keamanan |
| [docs/phases-2/API-DOCS-Mobile.md](docs/phases-2/API-DOCS-Mobile.md) | Referensi API mobile |
| `docs/phases-1/`, `docs/phases-2/` | Dokumen perencanaan per fase |
| `/api-docs` | Referensi API publik (halaman aplikasi) |

---

## Lisensi

Project ini mengikuti lisensi yang digunakan pada repository ini.
