# SAPI — Backlog & Technical Debt

**Format:** Catatan isu/temuan yang **belum diperbaiki** (bug kecil, hutang teknis, rapikan format, dll) yang perlu di-track supaya tidak hilang, tapi belum layak masuk `CHANGELOG.md` karena perubahan belum diterapkan di kode.

> Begitu sebuah entry benar-benar diperbaiki: tambahkan entry baru di `docs/CHANGELOG.md` (format `[HOTFIX]`/`[ADDITION]` sesuai konvensi di sana), ubah `Status` entry ini jadi `Selesai`, lalu **pindahkan isi lengkapnya ke `docs/BACKLOG-ARCHIVE.md`** dan sisakan satu baris di tabel "Riwayat Selesai (Arsip)" di bawah.

---

## Cara Membaca File Ini

File ini hanya berisi isu yang **masih terbuka**; yang sudah selesai ada di `docs/BACKLOG-ARCHIVE.md`. Untuk mencari satu isu, jangan baca file utuh — cari headingnya lebih dulu:

```bash
grep -n '^### \[BL-' docs/BACKLOG.md docs/BACKLOG-ARCHIVE.md
```

lalu baca hanya potongan barisnya. Status entri yang sudah selesai bisa dijawab dari tabel di bagian "Riwayat Selesai (Arsip)" tanpa membuka arsipnya.

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

> **Catatan pemilik 2026-07-31** — `[BL-021]`–`[BL-028]` berasal dari satu daftar catatan yang sama dan sudah diverifikasi terhadap kode. Tiga di antaranya (`[BL-021]`, `[BL-022]`, `[BL-028]`) menyangkut **angka uang yang tercatat salah**, jadi didahulukan; sisanya UX. Urutan pengerjaan yang disarankan: ~~`[BL-028]` Tahap A~~ → ~~`[BL-021]`+`[BL-022]`~~ → ~~`[BL-027]`~~ → ~~`[BL-023]`~~ → ~~`[BL-025]`~~ → `[BL-026]`, dengan `[BL-024]` menunggu perincian dari pemilik dan `[BL-028]` Tahap B ditunda sampai ada kasir kedua. Yang dicoret sudah selesai 2026-07-31.

> **Catatan pemilik 2026-07-31 (kedua)** — `[BL-032]`–`[BL-041]` berasal dari catatan review demo "SAPI — ASpire" dan sudah **diverifikasi terhadap kode**; tiap entri menyebut berkas dan barisnya. Dua hal dari catatan itu sengaja TIDAK jadi entri karena ternyata sudah benar: (1) pemisahan login platform vs login tenant sudah utuh — guard `platform` sendiri (`config/auth.php:50`), broker reset kata sandi sendiri (`config/auth.php:120`), dan `redirectGuestsTo` yang memilah tujuan berdasarkan prefiks URL (`bootstrap/app.php:80`); (2) "kelas harga sesuai omzet" sudah ada mesinnya (`pricing_rules` + bracket A–D), yang belum ada hanya angkanya — itu masuk `[BL-041]`.

### [BL-032] Landing Page Tidak Lagi Menggambarkan Produk yang Sudah Jadi
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "landing perbaiki menyesuaikan sekarang", "perbaiki gambar2 di landing page"
- **Status:** Open
- **Prioritas:** High (ini permukaan pertama yang dilihat calon klien)
- **Area Terdampak:**
  - `resources/views/public/landing.blade.php:686-745` — bagian harga menuliskan dua paket "Core POS Rp 149k" dan "Smart SAPI Rp 299k" yang **tidak ada di sistem**
  - `resources/views/public/landing.blade.php:132,180,716,742,1013` — seluruh CTA ("Daftar Gratis", "Pilih Paket", "Mulai Sekarang") menunjuk `/login`, padahal `/register` ada dan itulah tujuan yang dimaksud
  - `public/avatar_andi.png`, `public/avatar_budi.png`, `public/avatar_santi.png` — masing-masing ±600 KB untuk dirender `w-12 h-12` (48 px); tiga berkas ini saja ±1,8 MB, lebih berat dari seluruh tangkapan layar digabung
  - `public/Dashboard-owner.png`, `POS-Interface.png`, `Reports-Daily.png`, `Stock-Management.png`, `Product-List.png` — tertanggal 25 Mei, sebelum topbar tagihan terbuka, papan antrian, dan saran jual ada
- **Deskripsi:**
  Dihitung dari isi berkasnya, halaman ini tidak menyebut satu pun dari yang sudah dikirim sejak Mei: "open bill" 0 kali, "antrian" 0, "pesan mandiri/self-order" 0, "MCP" 0, "subsidi" 0, "modifier" 0, "sesi kas" 0. Yang tersisa hanya "offline" (1) dan "saran jual" (1). Sementara harga yang dipajang bukan sekadar usang — angkanya tidak pernah ada: paket bawaan sistem satu-satunya bernama `Dasar` dengan `base_price = 0` (`database/migrations/2026_07_24_181634_create_plans_table.php:38-47`), dan jalur subsidi memakai bracket Rp 10k–100k (`config/subscription.php:85-90`). Calon klien yang membaca "Rp 299k/bulan" lalu mendaftar akan mendapati tagihan yang sama sekali lain.
- **Usulan Perbaikan:**
  Tiga hal terpisah, boleh dikerjakan bertahap: (1) tulis ulang daftar fitur dari kapabilitas yang benar-benar ada; (2) tarik harga dari `pricing_rules`/`plans` alih-alih menuliskannya di HTML — lihat `[BL-041]`; (3) ambil ulang tangkapan layar dari seeder demo, dan turunkan avatar ke ±10 KB (WebP 96 px) karena ukuran sekarang tidak punya pembenaran apa pun.

### [BL-033] Tiga Permukaan Publik Belum Satu Keluarga — Desain, Aset, dan Wordmark
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "dokumentasi publik dan dokumentasi teknis seperti ai dan api kasih konsisten", "konsep logo SAPI Pos seperti di halaman dokumentasi menarik"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/views/public/landing.blade.php:20` dan `resources/views/public/api-docs.blade.php:17-28` — keduanya memuat `https://cdn.tailwindcss.com` di atas `@vite(['resources/css/app.css'])`; aplikasi ini memakai Tailwind v4 lewat Vite, jadi CDN itu build kedua yang dimuat dari luar
  - `resources/views/public/docs/layout.blade.php:24-38` — palet oklch disalin ulang; komentar di berkasnya sendiri mengakui itu salinan dari `/api-docs`
  - `resources/views/public/docs/layout.blade.php:181-183` — wordmark `SAPI POS` (`<b>SAPI</b><span>POS</span>`)
  - `resources/views/public/landing.blade.php:118,1026` — hanya teks "SAPI", tanpa "POS"
  - `public/sapi-logo.png` — ada di repo, **tidak dirujuk satu berkas pun** di `resources/`
- **Deskripsi:**
  Ada tiga permukaan publik (`/`, `/api-docs`, `/dokumentasi`) dengan tiga sumber gaya berbeda dan dua identitas merek berbeda. `/dokumentasi` sudah rapi — sidebar per jalur, dua jalur pembaca yang dipisah sengaja (`config/docs.php`) — sedangkan referensi API mobile berdiri sendiri di luar hub itu; `DocsController` menjelaskan alasannya (komponen kartu endpoint tidak terwakili markdown), jadi memindahkannya bukan yang diminta. Yang diminta adalah **terasa satu produk**: satu topbar, satu palet, satu wordmark.
- **Usulan Perbaikan:**
  Jadikan wordmark `SAPI POS` versi dokumentasi sebagai identitas resmi dan pakai di ketiganya (plus favicon/PWA icon). Angkat palet oklch yang sudah ada ke satu partial Blade yang di-`@include` bertiga, atau ke `resources/css/app.css` agar ikut Vite. Buang `cdn.tailwindcss.com` dari landing dan api-docs — utilitas yang dipakai sudah tersedia dari build Vite; ini sekaligus menghapus dua permintaan pihak ketiga yang memblokir render.

### [BL-034] Pendaftaran Belum Menentukan Paket/Fitur — Tipe Usaha Hanya Dipakai Harga
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "perbaiki dan kasih jelas alur pendaftaran, ... pakai paket kategori, misal bazar maka aktif itu cuma kasir dan stock biasa, kalau cafe maka akan aktif open bill dll"
- **Status:** Open
- **Prioritas:** High
- **Area Terdampak:**
  - `app/Http/Controllers/Auth/AuthController.php:35-47` — pendaftaran hanya meminta `business_name`, `business_type`, `name`, `email`, `password`
  - `config/pricing-dimensions.php:83-95` — pilihan `business_type` hanya `kuliner`/`retail`/`jasa`/`lainnya`, dan resolvernya (`BusinessTypeResolver`) hanya dipakai mencocokkan aturan harga
  - `app/Models/Tenant.php:94-102` — `hasFeature()` membaca `kitchen_queue_enabled`, `self_order_enabled`, `ai_enabled`; tidak satu pun disentuh saat registrasi
  - `app/Http/Controllers/Owner/SettingsController.php:122-125` — satu-satunya tempat ketiganya bisa dinyalakan, setelah tenant terlanjur jadi
  - `app/Models/Tenant.php:60-68` — `$attributes` memasang bawaan ketiganya: `kitchen_queue` mati, `self_order` mati, `ai` menyala. Inilah keadaan yang diterima **setiap** tenant baru hari ini, apa pun jenis usahanya
- **Deskripsi:**
  Tipe usaha sudah ditanyakan di formulir daftar, tapi jawabannya tidak mengubah apa pun selain penetapan harga. Warung bazar dan kafe mendarat di aplikasi yang persis sama: antrian dapur mati, pesan mandiri mati, AI mati — lalu harus menemukan sendiri "Profil Usaha" untuk menyalakannya. Akibatnya pengguna baru menilai produk dari keadaan paling kosongnya.
- **Usulan Perbaikan:**
  Setelah nama usaha diisi, tampilkan pilihan **preset kategori** yang menyetel `*_enabled` sekaligus (mis. `kafe` → antrian dapur + open bill; `bazar` → kasir + stok saja; `retail` → stok + varian), dengan daftar centang yang tetap bisa diubah manual sebelum lanjut. Presetnya sebaiknya tinggal di config sebagai peta `business_type → daftar fitur`, bukan `if` di controller, supaya menambah kategori tidak berarti menyentuh alur pendaftaran.
- **Koreksi 2026-08-01 — batas yang dulu ditulis di sini sudah dicabut.** Entri ini semula menutup dengan "`business_type` sengaja tidak bisa diedit dari Settings, jadi preset fitur boleh diubah belakangan tapi tipe usahanya tidak". **Itu tidak berlaku lagi:** sejak `[DECISION] Jenis Usaha Berpindah ke Pemilik Toko` (2026-07-31, `docs/CHANGELOG.md`), jenis usaha justru diubah dari Owner → Pengaturan (`SettingsController.php:118`, `sometimes|required`), dan panel platform hanya membacanya. Yang menjaga tagihan tetap bisa dijelaskan bukan larangan mengedit, melainkan `effective_from` pada aturan harga + `invoices.pricing_context` yang membekukan keadaan tenant saat tagihan terbit.
  Konsekuensinya untuk entri ini: **keduanya kini bisa diedit belakangan**, jadi presetnya harus dibaca sebagai *nilai awal*, bukan sebagai ikatan. Yang perlu dijaga: mengubah jenis usaha dari Pengaturan **tidak boleh menerapkan ulang preset fiturnya diam-diam** — owner yang sudah mematikan antrian dapur tidak boleh mendapatkannya kembali hanya karena ia membetulkan jenis usahanya. Preset berlaku sekali, saat pendaftaran.
- **Bergantung pada `[BL-035]`** untuk isi preset `bazar`; preset lain (`kafe`, `retail`, `jasa`) sudah bisa disusun dari kapabilitas yang ada sekarang.

### [BL-035] "Mode Bazar" Belum Ada Wujudnya di Kode — Perlu Definisi Lebih Dulu
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "mode bazar atau tenant khusus untuk jualan di cfd, event, dll"
- **Status:** Open — **butuh keputusan pemilik sebelum bisa diimplementasikan**
- **Prioritas:** Medium
- **Area Terdampak:**
  - Seluruh repo — pencarian `bazar|bazaar` hanya menemukan **satu** kecocokan: `docs/phases-2/PHASE-QUEUE_Kitchen-Order-Queue.md:56`, dan itu pun menyebutnya sebagai sasaran akhir, bukan fitur ("Sasaran akhirnya tetap kaki lima/bazar")
  - `app/Models/Tenant.php:80-88` — tempat kapabilitas baru akan bergabung bila mode ini jadi capability flag
- **Deskripsi:**
  Tidak ada kolom, flag, konfigurasi, maupun rencana tertulis untuk mode bazar. Yang belum terjawab bukan soal teknis melainkan soal produk: **apa yang berubah** saat mode ini aktif? Kandidat yang masuk akal dari catatan pemilik — tagihan terbuka dimatikan (jualan selalu lunas di tempat), nomor antrian ditonjolkan, stok disederhanakan jadi hitungan terpakai/sisa per hari acara, dan penekanan pada operasi offline. Ada juga pertanyaan yang bersinggungan dengan `[BL-031]`: kalau tidak ada open bill, umur tagihan tidak perlu diputuskan untuk mode ini.
- **Usulan Perbaikan:**
  Putuskan dulu daftar perbedaannya, lalu wujudkan sebagai capability flag lewat `Tenant::hasFeature()` seperti `kitchen_queue` — bukan sebagai `business_type` baru, karena `business_type` milik penetapan harga dan membeku per tagihan. Sesudah itu barulah `[BL-034]` bisa menawarkan preset "bazar" yang berarti sesuatu, dan `[BL-036]` bisa memperagakannya.

### [BL-036] Belum Ada Studi Kasus Demo Kedua — Semua Peragaan Bertumpu pada Satu Kafe
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "tambahkan suatu toko usaha baru, bergerak di bidang makanan bazar kayak chicken shi lin atau ayam potong kripsi, ... dia ada antrian, dan mode bazar"
- **Status:** Open — **menunggu `[BL-035]`**
- **Prioritas:** Low (nilainya untuk demo & pengujian, bukan untuk klien yang sudah jalan)
- **Area Terdampak:**
  - `database/seeders/CafeStudyCaseSeeder.php:76` — satu-satunya tenant demo, "Kopi Story", dengan katalog kopi/pastry
  - `database/seeders/DatabaseSeeder.php` — hanya memanggil `PermissionCatalogSeeder`
- **Deskripsi:**
  Setiap peragaan, tangkapan layar landing, dan pengujian manual memakai tenant kafe yang sama. Bentuk usaha yang justru jadi sasaran utama — gerai makanan cepat saji di acara, dengan antrian panjang dan katalog pendek bersaus — belum pernah dicoba di aplikasi ini. Perbedaannya bukan kosmetik: katalog pendek dengan banyak modifier saus, transaksi cepat beruntun, dan nomor antrian sebagai penanda utama akan menekan bagian sistem yang berbeda dari katalog kafe yang panjang.
- **Usulan Perbaikan:**
  Seeder studi kasus kedua dengan pola yang sama seperti `CafeStudyCaseSeeder` (tenant + owner + kasir + katalog + modifier + transaksi contoh): katalog ±8 produk gorengan/ayam, satu grup modifier saus wajib pilih satu, antrian dapur menyala, dan bila `[BL-035]` sudah diputuskan, mode bazar aktif. Berguna sekaligus sebagai bahan tangkapan layar baru untuk `[BL-032]`.

### [BL-037] Perpindahan Halaman Hanya Ditandai Progress Bar — Belum Ada Skeleton
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "render skeleton, hilangkan progress bar yang mengganggu dan keliatan aplikasi lambat loading, terutama di kasir dan owner dashboard"
- **Status:** Open
- **Prioritas:** Medium (tidak ada yang rusak, tapi ini yang membuat aplikasi terasa lambat)
- **Area Terdampak:**
  - `resources/js/app.js:16-18` — `progress: { color: 'var(--primary)' }`, bar bawaan Inertia dan satu-satunya penanda perpindahan halaman
  - `app/Http/Controllers/Cashier/POSController.php:30,62` — `index()` merender seluruh katalog, metode bayar, dan tagihan terbuka secara eager; `Inertia::defer()` hanya dipakai di `history()` (baris 257, 264)
  - `app/Http/Controllers/Owner/DashboardController.php:75` — metrik, tren harian, badge, dan transaksi terakhir semuanya eager dalam satu respons
  - Hanya dua berkas di seluruh `resources/js` yang punya kerangka pemuatan: `Components/TransactionEditModal.vue` dan `Pages/Owner/AiAnalysis/Index.vue`
- **Deskripsi:**
  Karena semua prop dihitung sebelum respons dikirim, halaman tidak muncul sama sekali sampai kueri paling lambat selesai — dan selama itu satu-satunya umpan balik adalah garis tipis di puncak layar. Dua halaman terberatnya justru yang paling sering dibuka. Pedoman Inertia v2 di `CLAUDE.md` sudah menyebut pasangan yang benar untuk ini: deferred props disertai kerangka beranimasi.
- **Usulan Perbaikan:**
  Pindahkan bagian yang tidak dibutuhkan pada cat pertama ke `Inertia::defer()` — di POS: katalog produk dan metode pembayaran; di dashboard: tren harian, badge, dan transaksi terakhir (metrik hari ini tetap eager karena itulah isi utama layarnya). Bungkus tiap bagian dengan `<WhenVisible>`/`<Deferred>` dan kerangka `animate-pulse` yang **menempati ruang yang sama** dengan isi aslinya, supaya tidak ada lompatan tata letak saat data tiba. Baru setelah itu progress bar layak dikecilkan atau dimatikan — mematikannya lebih dulu justru menghilangkan satu-satunya penanda yang ada sekarang.

### [BL-038] Halaman Staf Tidak Menunjukkan Modul Efektif Per Orang
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "dalam konsep tim dan akses, staf dan role konsepnya dia langsung melihat kalau orang ini (email ini) memiliki akses ke module module berikut"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/Pages/Owner/Staff/Index.vue:236-243` — tiap baris hanya menampilkan surel dan satu lencana berisi **nama role**
  - `resources/js/Pages/Owner/Roles/Index.vue:205-211` — daftar modul ada, tapi di halaman Role, terpisah dari orangnya
  - `app/Models/User.php:85-96` — `modulePermissions()` sudah menghitung daftar itu dan sudah dibagikan ke Inertia; datanya ada, tinggal ditampilkan
  - `app/Providers/AppServiceProvider.php:87-89` — `Gate::before` membuat owner lolos semua gerbang, dan `modulePermissions()` mengembalikan `['*']` untuknya
- **Deskripsi:**
  Untuk menjawab "si A ini bisa buka apa saja?", pemilik harus membaca nama role di halaman Staf, lalu pindah ke halaman Role, lalu mencocokkan sendiri. Padahal jawabannya sudah dihitung server. Satu hal lagi yang tidak pernah terlihat di UI mana pun: owner bukan pemegang role dengan modul terbanyak, melainkan **melewati pemeriksaan sepenuhnya** — jadi mencabut modul dari role owner tidak akan berpengaruh apa-apa, dan tidak ada yang memberi tahu itu.
- **Usulan Perbaikan:**
  Kirim `modulePermissions()` tiap anggota ke `Owner/Staff/Index` dan tampilkan lencana modulnya di bawah surel, bukan nama role saja (nama role tetap ada sebagai asal-usulnya). Untuk owner, tampilkan satu lencana "Akses penuh — melewati seluruh pemeriksaan" alih-alih memuntahkan seluruh daftar modul, sesuai maksud `['*']` yang sudah ditulis di `User::modulePermissions()`.

### [BL-039] "Profil Usaha" Mencampur Merek, Aturan Kerja, Kapabilitas, dan Kredensial
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "pada profile usaha dan setting usaha, pisahkan bersifat brand usaha, cara kerja sistem, sampai ke api key dll settingannya pisahkan semua"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/Pages/Owner/Settings/Index.vue:125,149,229,314` — satu halaman memuat "Jenis Usaha", "Mode & Fitur Outlet", "AI Analysis" (termasuk kolom API key), dan "Akses MCP (AI Client)", ditambah nama/alamat/telepon usaha di atasnya
  - `app/Http/Controllers/Owner/SettingsController.php:19-81` — satu `index()` mengumpulkan identitas tenant, jenis usaha, kredensial AI, kapabilitas modul, aturan upsell, kuota AI, dan status token MCP
  - `app/Http/Controllers/Owner/SettingsController.php:106-136` — satu `update()` memvalidasi kesepuluh-sepuluhnya sekaligus
  - `resources/js/Layouts/OwnerLayout.vue:135-146` — grup "Pengaturan" berisi dua tautan: "Profil Usaha" dan "Langganan & Tagihan" (`[BL-040]`)
- **Deskripsi:**
  **Lima** hal dengan tingkat risiko yang jauh berbeda berbagi satu formulir dan satu tombol simpan: identitas usaha (aman, sering diubah), **jenis usaha** (dasar penetapan harga — lihat di bawah), aturan kerja seperti `upsell_mandatory` (mengubah perilaku kasir), kapabilitas modul (mematikannya bisa menggantung pesanan berjalan — itu sebabnya `featureWarnings` ada), dan kredensial (API key penyedia AI, token MCP). Menamainya "Profil Usaha" pun menyesatkan: sebagian besar isinya bukan profil.
- **Pemutakhiran 2026-08-01 — kasusnya bertambah kuat, bukan berkurang.** Saat entri ini ditulis, halaman ini memuat empat urusan. Sejak `[DECISION] Jenis Usaha Berpindah ke Pemilik Toko` (2026-07-31) ia memuat lima: **`business_type` pindah ke sini**, dan itu satu-satunya kolom di halaman ini yang ikut menentukan **berapa tagihan bulan depan** lewat `invoices.pricing_context`. Sekarang satu tombol simpan bisa menulis identitas, dasar tagihan, aturan kerja, kapabilitas, dan kredensial dalam satu permintaan.
  Kabar baiknya: validasinya sudah disiapkan untuk dipecah. `business_type` sengaja memakai `sometimes|required` (`SettingsController.php:111-118`) supaya form yang hanya mengirim sebagian field tetap bisa menyimpan — jadi memecah halamannya **tidak** menuntut menulis ulang aturan validasinya, hanya memisahkan endpoint-nya.
- **Usulan Perbaikan:**
  Pecah jadi beberapa halaman di bawah grup "Pengaturan": **Profil & Merek** (nama, alamat, telepon, logo struk, **jenis usaha**), **Cara Kerja Sistem** (kapabilitas modul + `upsell_mandatory`, tetap dengan peringatan pekerjaan berjalan), dan **Integrasi & Kredensial** (penyedia AI + key, token MCP, kelak webhook). Endpoint `update` sebaiknya ikut dipecah supaya satu formulir tidak lagi bisa menulis kredensial dan kapabilitas dalam satu permintaan. Pintu masuk ke tagihan sudah berdiri sendiri di grup yang sama lewat `[BL-040]` (selesai 2026-07-31), jadi pemecahan ini tinggal menata tiga halaman sisanya.
  **Jenis usaha ditaruh di Profil & Merek, bukan di halaman tersendiri** — ia memang keterangan usaha, dan memisahkannya hanya akan membuat orang mencarinya di tempat yang salah. Yang wajib ikut: satu kalimat di sebelah kolomnya yang menyatakan terus terang bahwa mengubahnya ikut mengubah dasar tarif bulan berikutnya, dan bahwa tagihan yang sudah terbit tidak berubah. Kolom yang menggerakkan uang tidak boleh terlihat sama tak berbahayanya dengan kolom nomor telepon.
  **Dampak ke `[BL-034]`:** halaman "Cara Kerja Sistem" inilah tempat preset fitur pendaftaran nanti bisa ditinjau ulang owner. Mengerjakan entri ini lebih dulu membuat `[BL-034]` punya tempat mendarat.

### [BL-041] Tarif Belum Ditetapkan, dan Halaman Harga Publik Belum Dinamis
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "Pemberian Kelas, dan sesuai Omset / Sesuaikan Tarif kembali", "berikan halaman pricing dan dynamic, perjelas batasan batasan dari owner yang membayar full, subsidi dan lain lain"
- **Status:** Open — **sebagian butuh keputusan pemilik (angka tarif)**
- **Prioritas:** High
- **Area Terdampak:**
  - `database/migrations/2026_07_24_181634_create_plans_table.php:38-47` — paket `dasar`: `base_price = 0`, `included_seats = 1`, `extra_seat_price = 0`
  - `config/subscription.php:85-90` — benih bracket A–D: Rp 10k / 25k / 50k / 100k
  - `resources/views/public/landing.blade.php:686-745` — yang dipajang publik: Rp 149k dan Rp 299k
  - `app/Http/Controllers/Billing/SubscriptionController.php:68-70` — `bracket` diisi **hanya** bila `isSubsidized()`; tenant jalur Harga Tetap tidak pernah melihat kelas apa pun
  - `routes/web.php:390-398` — rute publik hanya `/`, `/api-docs`, `/dokumentasi`; tidak ada halaman harga tersendiri
- **Kosakata (pemutakhiran 2026-08-01):** sejak `[DECISION] Jenis Usaha Berpindah ke Pemilik Toko` (2026-07-31), istilah yang dibaca orang adalah **Harga Tetap** (dulu "normal") dan **Harga Adaptif** (dulu "subsidi"). Nilai di basis data **tidak** ikut berganti — `pricing_track` tetap `normal`/`subsidized`, begitu pula `TenantConsent::TYPE_SUBSIDIZED`. Entri ini memakai istilah barunya untuk hal yang dilihat pengguna dan nama kolom aslinya untuk hal yang menyentuh kode.
- **Deskripsi:**
  Mesin "kelas sesuai omzet" yang diminta sebenarnya **sudah ada dan sudah berjalan**: `pricing_rules` bisa di-CRUD dari `/platform/pricing-rules`, dimensinya bebas (omzet, jumlah transaksi, seat aktif, tipe usaha), dan bracket A–D sudah tertanam sejak migrasi. Tiga hal yang belum ada: (1) **angkanya** — jalur Harga Tetap memakai paket ber-`base_price` 0, jadi tenant yang membayar penuh secara harfiah tertagih nol; (2) **kelas untuk jalur Harga Tetap** — bracket hanya dihitung untuk tenant Harga Adaptif, sehingga tenant bayar-penuh tidak punya penjelasan mengapa tarifnya sekian; (3) **halaman harga publik** yang membacakan aturan yang berlaku, bukan HTML yang ditulis tangan.
- **Keadaan data per 2026-08-01 (hasil query, bukan dugaan):** 2 tenant, keduanya paket `Dasar` dengan `base_price = 0.00`, `extra_seat_price = 0.00`, dan `price_locked = 0.00`; keduanya di jalur `normal`. Tabel `invoices` **kosong — belum pernah ada satu tagihan pun terbit**. `pricing_rules` berisi 4 baris: bracket A–D bawaan migrasi, yang hanya terpakai di jalur Harga Adaptif, sementara **0 tenant** ada di jalur itu. Artinya seluruh mesin penagihan berdiri lengkap dan belum pernah menagih siapa pun — dan itu bukan keadaan yang bisa dilihat dari layar mana pun.
- **Jendela yang akan tertutup:** selama `invoices` masih kosong, `[BL-030]` (tanggal jatuh tempo yang meluber di bulan pendek) bisa diperbaiki tanpa migrasi, tanpa backfill, dan tanpa menggeser tanggal tagih siapa pun. Begitu butir (a) di bawah dijalankan dan tagihan pertama terbit, jendela itu tertutup. **Kerjakan `[BL-030]` lebih dulu, bukan sesudahnya.**
- **Usulan Perbaikan:**
  Berurutan, karena masing-masing bergantung pada yang sebelumnya. **(a)** Pemilik menetapkan tarif jalur Harga Tetap (base + harga seat tambahan) dan meninjau ulang bracket Harga Adaptif — ini keputusan bisnis, bukan pekerjaan kode. **(b)** Perluas `bracket` di `SubscriptionController` agar juga terisi untuk jalur Harga Tetap memakai dimensi yang tidak butuh consent (seat aktif, tipe usaha), sehingga tiap owner bisa melihat kelasnya sendiri tanpa membuka data penjualan yang tidak pernah ia setujui — batas privasi di `config/pricing-dimensions.php` harus tetap dihormati. **(c)** Baru kemudian buat `/harga` yang merender aturan berlaku dari `PricingService`, dengan tabel perbandingan tegas antara jalur Harga Tetap dan Harga Adaptif: apa yang dibuka, apa yang dibatasi, dan syarat berpindah jalur (`track_switch_minimum_months` = 3). Landing menaut ke sana, menggantikan bagian harga yang sekarang (`[BL-032]`).
  **Dua tenant yang sudah ada memegang `price_locked = 0.00`.** Grandfathering yang sudah dibangun (`InvoiceController::verify()` mengunci harga dari nominal yang benar-benar dibayar) akan mempertahankan angka nol itu apa adanya — benar sebagai mekanisme, tapi keduanya adalah tenant demo/awal, bukan kesepakatan yang perlu dihormati selamanya. Putuskan sekalian di butir (a): dibiarkan nol, atau dinaikkan ke tarif baru dengan pemberitahuan. Jangan diam-diam.

### [BL-031] Umur Tagihan Terbuka Belum Pernah Diputuskan — Sesi Kas, Per Hari, atau Sampai Dilunasi?
- **Ditemukan:** 2026-07-31
- **Sumber:** Pertanyaan pemilik saat `[BL-023]` selesai — "apakah tagihan atau open bill itu hidup berdasarkan waktu hidup kas / shift kasir atau per hari atau sampai diselesaikan"
- **Status:** Open — **menunggu keputusan pemilik**, bukan menunggu implementasi
- **Prioritas:** Medium sekarang, naik jadi High begitu ada outlet yang benar-benar memakai Tunda Bayar setiap hari
- **Area Terdampak:**
  - `app/Http/Middleware/HandleInertiaRequests.php` — `openBillsFor()` menyaring `user_id` + `status pending`, **tanpa batas waktu apa pun**
  - `app/Services/TransactionService.php:119` — `processItems(..., deductStock: true)` juga untuk open bill; **stok berkurang saat tagihan dibuat**, bukan saat dilunasi
  - `app/Services/TransactionService.php:85-92` — mode antrian menyala → open bill ikut `fulfillment_status = waiting` dan masuk papan dapur
  - `app/Services/TransactionService.php:308-320` — `voidExpiredSelfOrder()` hanya berlaku untuk **self-order**; open bill POS tidak punya padanannya
  - `routes/console.php` — tidak ada satu pun tugas terjadwal yang menyentuh transaksi `pending`
  - `app/Http/Controllers/Cashier/POSController.php` — `canEditTransaction()` mensyaratkan transaksi berada dalam sesi laci terbuka
- **Keadaan sekarang (bukan keputusan, melainkan bawaan yang tak pernah dipilih):** tagihan terbuka hidup **sampai dilunasi, selamanya**, dan terikat pada `user_id` — bukan pada laci, bukan pada hari. Menutup kas tidak menyentuhnya; berganti hari tidak menyentuhnya. Tidak ada kedaluwarsa, tidak ada pembersihan, tidak ada peringatan.
- **Kenapa ini bukan sekadar soal tampilan:**
  1. **Stok sudah berkurang sejak tagihan dibuat.** Tagihan yang ditinggalkan menyandera stok tanpa batas waktu, dan tidak ada apa pun yang menagih kembali. Ini konsekuensi paling mahal dan yang paling tidak terlihat.
  2. **`[BL-028]` sudah menetapkan uang mengikuti laci yang MELUNASI.** Tagihan yang dibuat shift pagi lalu dilunasi shift malam menaruh uangnya di laci malam — itu benar, dan tidak perlu diubah. Tapi artinya "tagihan milik shift mana" dan "uangnya milik laci mana" memang dua hal berbeda, dan keduanya harus dinyatakan, bukan disimpulkan.
  3. **Kasir tidak bisa mengedit tagihan dari shift sebelumnya** (`canEditTransaction`), tapi tetap **melihat** dan **bisa melunasinya**. Kombinasi yang belum pernah diperiksa: boleh menerima uangnya, tidak boleh membetulkan isinya.
  4. **Mode antrian menaruh open bill di papan dapur.** Tagihan yang hidup berhari-hari akan menumpuk di papan — persis jenis timbunan yang pernah dibersihkan migrasi `backfill_stale_fulfillment_status`, dan sumbernya belum tertutup untuk kasus ini.
  5. **Berbeda dari self-order, yang PUNYA jalur kedaluwarsa.** Asimetri ini tidak pernah diputuskan; ia hanya belum sempat ditulis.
- **Tiga pilihan, dan konsekuensinya masing-masing:**
  | Pilihan | Cocok untuk | Yang harus ikut dibangun |
  |---|---|---|
  | **Sampai dilunasi** (perilaku sekarang) | warung dengan pelanggan langganan yang menitipkan tagihan lintas hari | pengingat umur tagihan, batas jumlah/nilai, dan cara owner menutup paksa — tanpa itu stok tersandera diam-diam |
  | **Per hari** | mayoritas warung makan; tagihan meja tidak masuk akal menyeberang hari | tugas terjadwal yang membatalkan (memulihkan stok!) atau menandai tagihan semalam, plus laporan apa yang dibatalkan |
  | **Per sesi kas** | outlet ber-shift yang serah terima kas | tagihan harus **dioper** saat tutup kas: dilunasi, dibatalkan, atau dipindahkan ke kasir berikutnya — dan tutup kas jadi tidak boleh berjalan sampai tak ada yang menggantung |
- **Usulan Perbaikan:**
  1. **Putuskan dulu, catat di `CHANGELOG.md` sebagai `[DECISION]`.** Ini aturan operasional, bukan detail teknis — pilihannya menentukan apakah stok bisa tersandera semalaman.
  2. Apa pun pilihannya, **pemulihan stok wajib ikut** pada jalur pembatalan mana pun. Membatalkan tagihan tanpa mengembalikan stok menukar satu masalah dengan masalah yang lebih sulit dilihat.
  3. Kalau jatuhnya "per sesi kas", sambungkan ke `[BL-028]`: tutup kas adalah tempat paling wajar untuk memaksa keputusan atas tagihan yang menggantung.
  4. Pertimbangkan menyatakan ini di UI apa pun keputusannya — kasir yang menekan "Tunda Bayar" berhak tahu tagihannya bertahan sampai kapan.
- **Catatan:** per 2026-07-31 database **tidak punya satu pun transaksi `pending`**, jadi keputusan ini masih bisa diambil tanpa memigrasikan apa pun. Jendela itu akan tertutup begitu fitur Tunda Bayar benar-benar dipakai.

### [BL-030] Tanggal Jatuh Tempo Langganan Ikut Meluber di Bulan Pendek
- **Ditemukan:** 2026-07-31
- **Sumber:** Sisa penyisiran `[BL-029]` — sengaja TIDAK ikut diperbaiki di sana karena menyangkut semantik penagihan, bukan sekadar salah turunan periode
- **Status:** Open
- **Prioritas:** Medium — bukan salah bulan seperti `[BL-029]`, tapi tanggal tagih yang bergeser maju dan **tidak pernah kembali**
- **Area Terdampak:**
  - `app/Http/Controllers/Platform/InvoiceController.php:202` — `$periodStart = now()->startOfDay()`, yaitu **tanggal bukti bayar diverifikasi**, bukan awal bulan. Inilah yang membuat tanggal 29/30/31 bisa jadi titik mulai periode sama sekali
  - `app/Http/Controllers/Platform/InvoiceController.php:211` — `current_period_end = $periodStart->copy()->addMonth()`
  - `database/migrations/2026_07_24_181638_add_subscription_columns_to_tenants_table.php:66` — pola yang sama saat backfill
  - `database/factories/SubscriptionFactory.php:32,34` — `now()->addMonth()`
  - `app/Services/SubscriptionService.php:150` & `:160` — jarak minimum pindah jalur, `subMonths()`/`addMonths()`
  - **Jangan tertukar:** `InvoiceController::periodStart()` (`:169-172`) memang memakai `startOfMonth()` dan **tidak** bermasalah — ia titik waktu penetapan harga, bukan awal periode langganan. Dua hal berbeda di berkas yang sama
- **Deskripsi:** `addMonth()` berarti "tanggal yang sama bulan depan, meluber bila tidak ada". Langganan yang periodenya mulai 31 Januari berakhir **3 Maret**, bukan 28/29 Februari — periode 31 hari yang ditagih sebagai satu bulan. Karena periode berikutnya dihitung dari tanggal akhir yang sudah meleset, pergeserannya **menumpuk**: tiap kali melewati bulan pendek, tanggal tagih maju beberapa hari dan tidak pernah balik.
  Untuk `SubscriptionService`, akibatnya lebih ringan — jarak minimum 3 bulan bisa jadi 3 bulan + beberapa hari — tapi sumbernya persis sama.
- **Kenapa tidak diborong ke `[BL-029]`:** `[BL-029]` memperbaiki turunan periode yang **jelas salah** (bulan berjalan dihitung sebagai bulan lalu) — tidak ada pilihan produk di sana. Yang ini menuntut keputusan: langganan mulai 31 Januari jatuh tempo **28 Februari** (`addMonthNoOverflow`, tanggal tagih tetap di akhir bulan) atau **1 Maret**? Keduanya bisa dibela, dan pilihannya menentukan berapa yang ditagih. Mengubahnya diam-diam sambil membetulkan bug lain akan menggeser tanggal tagih pelanggan tanpa ada yang memutuskan.
- **Kerjakan sebelum `[BL-041]`, bukan sesudahnya (dicatat 2026-08-01).** Prioritas entri ini tertulis Medium, dan itu masih benar soal besarnya kerusakan — tapi **ongkos memperbaikinya sedang berada di titik terendah dan akan naik**. Hasil query per 2026-08-01: tabel `invoices` **kosong** (belum pernah ada tagihan terbit), dan kedua langganan yang ada berperiode `2026-07-24` → `2026-08-24`, yaitu tanggal 24 — tanggal yang ada di setiap bulan, jadi **belum ada satu pun tanggal tagih yang sudah meleset**.
  Artinya hari ini perbaikannya murni penggantian pemanggilan: tanpa migrasi, tanpa backfill, tanpa satu pun tanggal tagih pelanggan yang bergeser. Begitu `[BL-041]` butir (a) dijalankan — tarif ditetapkan dan tagihan pertama terbit — setiap verifikasi pembayaran yang jatuh pada tanggal 29/30/31 mulai menanam pergeseran yang menumpuk, dan poin 3 di bawah berubah dari "tidak ada yang perlu diluruskan" menjadi pekerjaan data sungguhan. Rujukan baliknya ada di `[BL-041]` bagian **Jendela yang akan tertutup**.
- **Usulan Perbaikan:**
  1. Putuskan lebih dulu semantik jatuh temponya, lalu tuliskan di `CHANGELOG.md` sebagai `[DECISION]` — ini aturan bisnis, bukan detail implementasi.
  2. Setelah itu ganti ke `addMonthNoOverflow()` (atau turunkan dari tanggal mulai berlangganan, bukan dari akhir periode sebelumnya, supaya pergeseran tidak menumpuk).
  3. Langganan yang sudah berjalan butuh keputusan tersendiri: dibiarkan pada tanggalnya sekarang, atau diluruskan sekali. Jangan diam-diam. — **Per 2026-08-01 poin ini kosong biayanya:** dua langganan yang ada berperiode tanggal 24 dan belum pernah melewati bulan pendek, jadi tidak ada yang perlu diluruskan. Poin ini tetap ditulis karena ia akan hidup kembali begitu entri ini ditunda melewati tagihan pertama.

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

## Riwayat Selesai (Arsip)

Isi lengkap entri yang sudah selesai dipindahkan ke **`docs/BACKLOG-ARCHIVE.md`** (2026-07-31). Tabel di bawah adalah indeksnya — cukup untuk menjawab "sudah selesai belum?" dan "entri CHANGELOG mana yang menutupnya?" tanpa membuka arsipnya sama sekali.

| ID | Judul | Selesai | Entri penutup di `docs/CHANGELOG.md` |
|---|---|---|---|
| `BL-026` | Identitas pesanan (nama / no. meja / kode panggil) belum bisa diisi dari kasir | 2026-08-01 | `[ADDITION] Identitas Pesanan Bisa Diisi dari Kasir, & Nomor Panggil Lepas dari Papan Dapur (BL-026)` |
| `BL-042` | Daftar tenant memajang kolom informasi, bukan jalan masuk ke rincian | 2026-08-01 | `[ADDITION] Rincian Tenant Bertab, dan Daftarnya Kembali Jadi Daftar (BL-042)` |
| `BL-040` | Halaman langganan & tagihan tidak punya pintu masuk dari dashboard | 2026-07-31 | `[ADDITION] Pintu Masuk Langganan & Ringkasan Tagihan di Dashboard (BL-040)` |
| `BL-001` | Penomoran ordered list hasil AI selalu "1." | 2026-07-15 | `[HOTFIX] Penomoran Ordered List Hasil AI (BL-001)` |
| `BL-002` | Dua test gagal (pre-existing) di suite | 2026-07-21 | `[HOTFIX] Ekspektasi Dua Test Usang (BL-002)` |
| `BL-003` | Mobile API — permissions RBAC di auth & gating endpoint | 2026-07-25 | `[ADDITION] Permissions RBAC di Mobile API (BL-003)` |
| `BL-004` | Pesan validasi masih bahasa Inggris di UI berbahasa Indonesia | 2026-07-25 | `[ADDITION] Pesan Validasi Berbahasa Indonesia (BL-004)` |
| `BL-005` | Platform Console — panel pemilik SaaS (privacy-preserving) | 2026-07-22 (Tahap A), 2026-07-25 (Tahap B–D) | dua entri `PHASE SAAS` |
| `BL-006` | Sistem langganan dua jalur — harga normal & subsidi UMKM | 2026-07-25 | `[ADDITION] Jalur Subsidi & Aturan Harga Dinamis` |
| `BL-007` | Endpoint login tanpa rate limit | 2026-07-21 | `[HOTFIX] Rate Limit Endpoint Login (BL-007)` |
| `BL-008` | Panel platform: modul tanpa halaman & belum ada UI kelola akun | 2026-07-21 | `[ADDITION] Manajemen Akun Platform (BL-008)` |
| `BL-009` | Audit log platform belum bisa dibaca, berisik, tanpa retensi | 2026-07-22 | `[ADDITION] Halaman & Retensi Jejak Audit (BL-009)` |
| `BL-010` | Akun platform: pemulihan kata sandi, 2FA, & variabel env | 2026-07-22 — **sebagian**, 2FA dipisah jadi `[BL-013]` | `[ADDITION] Pemulihan Kata Sandi Akun Platform (BL-010)` |
| `BL-011` | Belum ada peringatan saat percobaan masuk gagal menumpuk | 2026-07-25 | `[ADDITION] Peringatan Login Gagal & Verifikasi Email` |
| `BL-012` | Pengguna tenant belum punya pemulihan kata sandi | 2026-07-22 | `[ADDITION] Pemulihan Kata Sandi Pengguna Tenant (BL-012)` |
| `BL-014` | Pendaftaran berulang demi trial gratis baru | 2026-07-25 | `[ADDITION] Peringatan Login Gagal & Verifikasi Email` |
| `BL-015` | Dimensi penetapan harga masih terbatas omzet & seat | 2026-07-27 | `[SCHEMA] Dimensi Harga Bebas — Aturan Berkriteria (BL-015)` |
| `BL-017` | Peringatan stok belum berkembang jadi upsell | 2026-07-27 | `[ADDITION] Saran Jual dari Sinyal Stok (BL-017)` |
| `BL-019` | Rencana antrian dapur perlu ditinjau & ditulis ulang | 2026-07-29 | `[ADDITION] Papan Antrian Dapur (BL-019)` + `[ADDITION] Fondasi Capability Flags` |
| `BL-020` | Grup API self-order tidak melewati gerbang langganan | 2026-07-29 | `[HOTFIX] Gerbang Langganan di Jalur Self-Order (BL-020)` |
| `BL-021` | Split bill: nominal non-tunai basi & tombol uang cepat mati diam-diam | 2026-07-31 | `[HOTFIX] Split Bill ... (BL-021, BL-022)` |
| `BL-022` | Checkout memvalidasi cukup-bayar memakai harga kiriman klien | 2026-07-31 | `[HOTFIX] Split Bill ... (BL-021, BL-022)` |
| `BL-025` | Saran jual belum bisa diwajibkan, tombol bayar nonaktif tanpa penjelasan | 2026-07-31 | `[ADDITION] Penawaran Wajib Diselesaikan & Status "Ditolak" Terpisah (BL-025)` |
| `BL-023` | Tagihan terbuka menumpang di dalam keranjang | 2026-07-31 | `[ADDITION] Tagihan Terbuka Pindah ke Topbar Kasir (BL-023)` |
| `BL-027` | Riwayat kasir menampilkan seluruh riwayat, bukan sesi berjalan | 2026-07-31 | `[HOTFIX] Riwayat Kasir Dibatasi ke Sesi Kas Berjalan (BL-027)` |
| `BL-029` | Satu test subsidi selalu gagal di tanggal 29–31 (month overflow) | 2026-07-31 | `[HOTFIX] Turunan Periode Bulanan Meluber di Bulan Pendek (BL-029)` |