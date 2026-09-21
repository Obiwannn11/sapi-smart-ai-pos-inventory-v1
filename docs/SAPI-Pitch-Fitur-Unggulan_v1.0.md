# SAPI — Fitur Unggulan: Penjelasan Produk, Cara Kerja & Peta Kode

**Versi:** 1.0
**Tanggal:** 1 Agustus 2026
**Tujuan dokumen:** Bahan pitching internal/eksternal yang menjelaskan lima fitur unggulan SAPI (Upsell Cerdas, Dynamic Pricing, RBAC/ABAC, AI Analysis, Subscription & Billing) — apa masalah bisnis yang diselesaikan, bagaimana cara kerjanya, dan **di mana persisnya logika itu hidup di kode**, supaya Anda tidak perlu membuka & menelusuri file satu per satu.

> Catatan pembacaan: nomor baris (`file.php:12-34`) merujuk pada kondisi kode per tanggal dokumen ini dibuat. Kode terus berkembang — gunakan nomor baris sebagai titik awal pencarian, bukan koordinat yang dijamin abadi. Cek dulu di `docs/CHANGELOG.md` (bagian *Indeks Entri*) atau `docs/BACKLOG.md` bila sebuah perilaku terasa berbeda dari yang tertulis di sini.

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Upsell Cerdas Berbasis Sinyal Stok](#2-upsell-cerdas-berbasis-sinyal-stok)
3. [Dynamic Pricing — Mesin Harga Berlangganan SaaS](#3-dynamic-pricing--mesin-harga-berlangganan-saas)
4. [RBAC/ABAC — Kontrol Akses Modular](#4-rbacabac--kontrol-akses-modular)
5. [AI Analysis — Insight Bisnis Berbasis AI](#5-ai-analysis--insight-bisnis-berbasis-ai)
6. [Subscription & Billing — Siklus Hidup Langganan Multi-Tenant](#6-subscription--billing--siklus-hidup-langganan-multi-tenant)
7. [Bagaimana Kelima Fitur Ini Saling Terkait](#7-bagaimana-kelima-fitur-ini-saling-terkait)

---

## 1. Ringkasan Eksekutif

SAPI bukan sekadar aplikasi kasir. Lima kemampuan di bawah ini adalah yang membedakan SAPI dari POS generik — masing-masing dirancang untuk **menaikkan omzet tenant** (Upsell), **menjaga margin bisnis SAPI sendiri sebagai SaaS** (Dynamic Pricing, Subscription & Billing), **mengurangi risiko operasional saat pemilik toko mendelegasikan akses** (RBAC/ABAC), dan **memberi pemilik toko kecerdasan bisnis tanpa perlu analis data** (AI Analysis).

| # | Fitur | Untuk siapa | Nilai jual utama |
|---|---|---|---|
| 2 | Upsell Cerdas | Kasir & pemilik tenant | Menambah nilai transaksi tanpa diskon, sambil membersihkan stok mendekati kedaluwarsa/mati |
| 3 | Dynamic Pricing | SAPI (platform) | Harga langganan yang adil dan bisa dibuktikan, tanpa mengintip data operasional tenant |
| 4 | RBAC/ABAC | Pemilik tenant | Delegasi akses ke staf tanpa mempertaruhkan seluruh sistem |
| 5 | AI Analysis | Pemilik tenant | Rekomendasi bisnis berbasis data nyata, bukan template generik |
| 6 | Subscription & Billing | SAPI (platform) & tenant | Siklus tagihan otomatis yang tetap manusiawi (tidak langsung diputus) |

---

## 2. Upsell Cerdas Berbasis Sinyal Stok

### 2.1 Masalah bisnis yang diselesaikan

Setiap POS mencatat data stok — level persediaan, tanggal kedaluwarsa, kombinasi produk yang sering dibeli bersama. Di kebanyakan sistem, data ini hanya berhenti di dashboard laporan yang dilihat pemilik seminggu sekali. **SAPI menyalurkan sinyal itu ke titik yang paling menentukan: meja kasir, saat pelanggan masih berdiri di depan kasir.** Hasilnya: stok yang nyaris kedaluwarsa terjual duluan, kasir punya alasan konkret untuk menawarkan upsize/tambahan, dan setiap tawaran (diterima maupun ditolak) tercatat untuk dianalisis.

Rancangan lengkap: [`docs/phases-2/PHASE-UPSELL_Stock-Signal-To-Upsell.md`](../docs/phases-2/PHASE-UPSELL_Stock-Signal-To-Upsell.md).

### 2.2 Cara kerja

Tiga strategi saran berjalan independen, masing-masing menjawab pertanyaan berbeda:

| Strategi | Pertanyaan yang dijawab | Logika inti |
|---|---|---|
| **Pressed Stock** (`PressedStockStrategy`) | "Produk apa yang harus segera terjual?" | Kandidat = mendekati kedaluwarsa **atau** tidak laku (`dead_stock_days`). Skor near-expiry selalu menang atas dead-stock — barang kedaluwarsa punya tenggat pasti, barang mati hanya rugi pelan-pelan. |
| **Upsize Variant** (`UpsizeVariantStrategy`) | "Varian lebih besar apa yang masuk akal ditawarkan?" | Cari varian produk sama dengan selisih harga **terkecil** di atasnya, dibatasi `max_price_gap_ratio` (default 60%) — sengaja menghindari lompatan "Kecil → Jumbo" karena permintaan besar cenderung ditolak dan kasir berhenti membaca saran. |
| **Attach Modifier** (`AttachModifierStrategy`) | "Tambahan apa yang biasanya dibeli bersama ini?" | Rangking berdasarkan riwayat co-occurrence 30 hari terakhir; kalau tenant baru tanpa riwayat, fallback ke modifier termurah yang tersedia (bertanda `reason: catalog`). |

Ketiganya melewati filter bersama `SellableVariantQuery` — stok > 0, belum kedaluwarsa, produk aktif — sehingga **produk kedaluwarsa tidak pernah disarankan** (aturan keras keamanan pangan).

Semua saran dirangkai per tenant oleh `UpsellIndexBuilder`, dikirim ke halaman kasir sebagai bagian dari data awal (tidak dihitung ulang tiap perubahan keranjang — supaya tetap jalan saat offline), lalu difilter ulang di sisi klien (`useUpsell.js`) agar saran yang sudah ditolak/di keranjang tidak muncul lagi. Kasir melihatnya sebagai **strip tipis di atas tombol bayar** — bukan pop-up yang mengganggu — maksimum 2 saran per transaksi.

Setiap kemunculan saran dicatat lewat satu jalur tunggal (`UpsellEventRecorder`) yang dipakai baik oleh POS online, POS offline (sinkron belakangan), maupun self-order pelanggan — sehingga data konversi selalu lengkap apa pun kanal transaksinya. Pencatatan ini didesain **tidak pernah menggagalkan transaksi** — kalau logging gagal, transaksi tetap jalan.

Owner bisa mengaktifkan mode **wajib dijawab**: kasir harus menerima atau menolak setiap saran sebelum bisa checkout (bukan hanya diabaikan) — berguna untuk toko yang serius mengejar clearing stok. Default-nya opsional.

### 2.3 Peta kode

| Lapisan | File |
|---|---|
| Strategi saran | `app/Services/Upsell/Strategies/PressedStockStrategy.php`, `UpsizeVariantStrategy.php`, `AttachModifierStrategy.php` |
| Antarmuka strategi | `app/Services/Upsell/SuggestionStrategy.php`, `app/Services/Upsell/CartLevelStrategy.php` |
| Filter kandidat bersama | `app/Services/Upsell/SellableVariantQuery.php` |
| Perakit indeks saran per tenant | `app/Services/Upsell/UpsellIndexBuilder.php` |
| Pencatat event (shown/accepted/rejected) | `app/Services/Upsell/UpsellEventRecorder.php`, model `app/Models/UpsellEvent.php` |
| Konfigurasi (threshold, on/off per strategi) | `config/upsell.php` |
| API self-order | `app/Http/Controllers/Api/V1/ApiUpsellController.php` |
| Kontroler kasir (injeksi indeks ke halaman POS) | `app/Http/Controllers/Cashier/POSController.php` |
| Laporan owner (conversion rate, extra revenue) | `app/Http/Controllers/Owner/ReportController.php` (method `upsell()`) |
| UI kasir — logika filter & terapkan saran | `resources/js/composables/useUpsell.js`, `resources/js/Pages/Cashier/POS.vue` |
| UI kasir — strip saran | `resources/js/Components/UpsellStrip.vue` |
| UI owner — laporan upsell | `resources/js/Pages/Owner/Reports/Upsell.vue` |
| Toggle wajib per tenant | `app/Models/Tenant.php` (kolom `upsell_mandatory`), diatur via `app/Http/Controllers/Owner/SettingsController.php` |

### 2.4 Poin jual

- Menaikkan nilai transaksi **tanpa mengandalkan diskon** (fase diskon otomatis sengaja belum diaktifkan — lihat `[BL-018]` di `docs/BACKLOG.md`).
- Membantu clearing stok kedaluwarsa/mati secara proaktif, bukan reaktif setelah rugi.
- Setiap saran terukur: dua metrik terpisah yang sengaja dipisah — **conversion rate** (diterima/ditampilkan, mengukur seberapa relevan sarannya) vs **offer rate** (diterima/ditawarkan, mengukur kepatuhan kasir menawarkan) — sehingga owner tahu persis apakah masalahnya ada di kualitas saran atau di kedisiplinan staf.
- Tetap berfungsi saat koneksi offline (POS offline-first).

---

## 3. Dynamic Pricing — Mesin Harga Berlangganan SaaS

> Catatan penting: fitur ini adalah **harga langganan SAPI ke tenant** (model bisnis SaaS-nya sendiri), bukan mesin harga produk/menu di toko tenant.

### 3.1 Masalah bisnis yang diselesaikan

SAPI perlu menagih tenant secara adil — tenant kecil idealnya tidak membayar setarif dengan tenant besar — **tanpa mengintip data operasional tenant** kecuali tenant tersebut secara sadar menyetujuinya demi mendapat harga lebih murah. Dokumen rancangan (`docs/phases-2/PHASE-SAAS_Platform-Console-Subscription.md`) menegaskan prinsip ini sebagai fondasi seluruh desain.

Dua jalur harga disediakan:
- **Harga Tetap** — tarif flat sesuai paket, tidak butuh data apa pun dari tenant.
- **Harga Adaptif (subsidized)** — tarif dihitung dari omzet tenant sendiri (dihitung otomatis dari transaksi, **bukan self-report** — "tidak bisa dicurangi"), sebagai ganti tenant mengizinkan datanya dilihat platform untuk keperluan itu, lewat dokumen consent eksplisit.

Prinsip **grandfathering** dikunci sejak awal: mengubah satu angka di aturan harga **tidak boleh** mengubah tagihan tenant yang sudah berjalan secara instan — hanya berlaku untuk periode berikutnya.

### 3.2 Cara kerja

**Mesin resolusi harga** (`PricingService::resolveFor()`) menerima sebuah tenant + tanggal, lalu:

1. Menerjemahkan tenant menjadi konteks nilai per-dimensi (`DimensionRegistry`) — misalnya `monthly_revenue`, `transaction_count`, `active_seats`, `business_type`.
2. Mencari `PricingRule` yang **sedang berlaku** pada tanggal itu (`effective_from` ≤ tanggal — inilah gerbang grandfathering), mengambil revisi terbaru per label, lalu memilih berdasarkan prioritas tertinggi.
3. Sebuah rule cocok hanya jika **semua** kondisinya terpenuhi (AND saja, sengaja tidak mendukung OR — "OR selalu bisa dinyatakan sebagai dua rule terpisah").
4. Jika tidak ada rule yang cocok, jatuh ke plan fallback — tenant Harga Tetap jatuh ke plan langganannya sendiri; tenant Harga Adaptif jatuh ke plan yang ditandai `is_adaptive_fallback` (bukan diam-diam menjadi Rp 0).
5. **Fail-closed by design**: dimensi yang datanya tidak tersedia (misalnya consent belum aktif) selalu dianggap **gagal**, bukan lolos — mencegah aturan yang salah cocok akibat data kosong.

**Grandfathering ditegakkan bukan di aturan harga, tapi di titik pembayaran:** saat sebuah invoice diverifikasi lunas, harga yang benar-benar dibayar itulah yang dikunci ke `subscriptions.price_locked` — bukan dibaca ulang dari tabel tarif. Jadi perubahan tarif besok tidak pernah mengubah kesepakatan hari ini. Aturan yang sudah berlaku juga tidak bisa diedit langsung di tempat — hanya bisa dipublikasikan sebagai revisi baru bertanggal depan.

**Pencabutan consent subsidi** diperlakukan hati-hati: begitu tenant mencabut izin, **pengumpulan data metrik berhenti seketika** (baris metrik dihapus), tapi **tarif subsidi tetap berlaku sampai akhir periode berjalan** — persis seperti yang dijanjikan di dokumen consent. Sistem baru mengembalikan tenant ke tarif normal lewat command terjadwal setelah periode itu selesai.

**Estimasi sebelum commit:** `SubsidyEstimator` memberi tenant Harga Tetap sebuah pratinjau — "kalau Anda pindah ke Harga Adaptif, kira-kira segini tarifnya" — dihitung dari bulan lalu yang sudah tutup buku (bulan berjalan sengaja diabaikan karena angkanya masih bisa berubah). Hasil estimasi ini **tidak pernah dipakai untuk tagihan sungguhan**, murni pratinjau.

### 3.3 Peta kode

| Lapisan | File |
|---|---|
| Mesin resolusi harga (inti algoritma) | `app/Services/PricingService.php` |
| Model aturan harga & kondisi | `app/Models/PricingRule.php`, `app/Models/PricingRuleCondition.php` |
| Katalog dimensi yang bisa dijadikan syarat | `config/pricing-dimensions.php`, `app/Services/Pricing/DimensionRegistry.php` |
| Estimasi pratinjau harga adaptif | `app/Services/Pricing/SubsidyEstimator.php` |
| CRUD aturan harga oleh admin platform | `app/Http/Controllers/Platform/PricingRuleController.php` |
| UI admin platform | `resources/js/Pages/Platform/PricingRules/Index.vue` |
| Model paket & fallback adaptif | `app/Models/Plan.php` |
| Halaman tagihan tenant | `app/Http/Controllers/Billing/SubscriptionController.php`, `resources/js/Pages/Billing/Show.vue` |
| Pembuatan & penguncian invoice (titik grandfathering) | `app/Http/Controllers/Platform/InvoiceController.php` (method `verify()`) |
| Alur consent & pencabutan subsidi | `app/Http/Controllers/Billing/ConsentController.php`, `app/Services/ConsentService.php`, `app/Services/SubscriptionService.php` (`switchToSubsidized()`, `scheduleTrackRevert()`) |

### 3.4 Poin jual

- Tenant kecil dan tenant besar membayar tarif yang mencerminkan skala bisnis mereka, bukan tarif seragam yang timpang.
- Setiap harga yang pernah disepakati **terbukti dan tidak bisa berubah sepihak** — dasar kepercayaan untuk kontrak SaaS jangka panjang.
- Privasi tenant dihormati secara default: data operasional hanya terlihat platform kalau tenant sendiri yang membuka akses, dan itu pun bisa dicabut kapan saja.
- Sistem bisa berevolusi (menambah dimensi harga baru, mengubah bracket) tanpa perlu deploy kode — cukup lewat panel admin.

---

## 4. RBAC/ABAC — Kontrol Akses Modular

### 4.1 Masalah bisnis yang diselesaikan

Pemilik toko sering butuh mempekerjakan lebih dari satu kasir dengan tingkat kepercayaan berbeda — "Kasir 1 cukup pegang POS saja, Kasir 2 boleh pegang POS + Inventori." Tanpa kontrol granular, pilihannya cuma dua ekstrem: beri akses penuh (berisiko) atau akses sangat terbatas (staf jadi bergantung terus ke pemilik). SAPI menyediakan **RBAC berbasis modul** — pemilik merakit sendiri peran-peran kustom per toko, mencentang modul mana saja yang boleh dibuka oleh peran itu.

Rancangan lengkap: `docs/phases-2/PHASE-RBAC_Module-Access-Control.md`.

### 4.2 Cara kerja

**Satu sumber kebenaran** untuk seluruh katalog modul: `config/rbac.php` — berisi 7 modul (POS, Sesi Kas, Produk, Stok/Inventori, Laporan, Metode Pembayaran, AI Analysis), masing-masing punya label dan penanda **sensitive**. Dua modul ditandai sensitive (Metode Pembayaran, AI Analysis) — modul sensitive tetap bisa diberikan ke staf, tapi **defaultnya tidak tercentang** saat peran baru dibuat, memaksa pemilik mengambil keputusan sadar untuk membukanya.

Fondasi teknisnya adalah `spatie/laravel-permission` dengan fitur **teams**, di mana "team" dipetakan ke `tenant_id`. Artinya: **permission (nama modul) bersifat global** — hanya 7 nama yang pernah ada di sistem — sementara **role (peran) bersifat per-tenant** — "Kasir" milik Toko A adalah baris database yang sepenuhnya terpisah dari "Kasir" milik Toko B, meski namanya sama. Ini mencegah kebocoran hak akses lintas tenant di level struktur data, bukan hanya di level query.

Pemilik toko (`owner`) adalah **super-admin dalam tenant-nya** — satu baris `Gate::before` membuat setiap pemeriksaan izin otomatis lolos untuknya, tanpa perlu peran eksplisit apa pun. Ini memastikan pemilik tidak pernah bisa mengunci dirinya sendiri keluar dari sistemnya sendiri.

Di sisi frontend, sidebar owner menyaring menu sesuai `permissions` milik user yang sedang login, dan staf yang hanya punya akses POS mendapat tampilan kasir yang ramping — tapi staf yang diberi modul tambahan (mis. Inventori) mendapat pintu masuk "Kelola Toko" di topbar kasir mereka. **Penting:** penyaringan sidebar ini bersifat kosmetik/UX, bukan lapisan keamanan sesungguhnya — penegakan yang sebenarnya ada di middleware/gate sisi server, sehingga mengetik URL modul yang tidak diizinkan tetap akan ditolak.

**Sisi platform SAPI (staf internal SAPI) punya sistem akses yang sepenuhnya terpisah**, bukan reuse dari RBAC tenant — karena secara struktural akun platform tidak punya `tenant_id`, sementara mekanisme "team" milik spatie mewajibkan kolom itu terisi. SAPI membuat tabel modul akses platform sendiri (`platform_user_modules`), dengan katalog modul berbeda (Tenants, Subscriptions, Payments, Pricing Rules, Revenue Data, Audit Logs). Kewenangan "pemilik platform" (`is_owner`) sengaja **tidak dijadikan modul yang bisa dicentang** — kalau bisa, staf platform yang kebetulan diberi wewenang itu bisa mencentang izin sensitif untuk dirinya sendiri.

**Elemen ABAC (berbasis atribut, bukan sekadar peran):**
- Penanda `sensitive` pada modul mengubah perilaku default (unchecked-by-default) — sebuah aturan berbasis atribut modul, bukan berbasis siapa penggunanya.
- Setiap kali staf platform membuka data omzet tenant dalam rupiah persis, **sebuah baris audit log tersendiri tercatat setiap kali** (tanpa deduplikasi) — berbeda dari akses rutin lain yang dedup — sehingga akses ke data paling sensitif selalu bisa dipertanggungjawabkan satu-satu.
- Isolasi data per baris (`TenantScope`) memastikan bahkan dengan permission yang sama, seorang user tidak pernah bisa mengambil baris data milik tenant lain — ini kontrol berbasis atribut kepemilikan data, berjalan independen dari lapisan role/modul.

### 4.3 Peta kode

| Lapisan | File |
|---|---|
| Katalog modul tenant (sumber kebenaran) | `config/rbac.php` |
| Seeder permission global | `database/seeders/Concerns/` (cek `PermissionCatalogSeeder`) |
| Kontrol izin efektif user | `app/Models/User.php` (method `modulePermissions()`, `isOwner()`) |
| Manajemen peran kustom oleh owner | `app/Http/Controllers/Owner/RoleController.php` |
| Bypass super-admin | `app/Providers/AppServiceProvider.php` (`Gate::before`) |
| Penetapan konteks tenant/team saat request | `app/Http/Middleware/EnsureTenant.php`, `EnsureTenantApi.php` |
| Pembagian data izin ke frontend (harus lazy!) | `app/Http/Middleware/HandleInertiaRequests.php` |
| Penyaringan menu sisi owner | `resources/js/Layouts/OwnerLayout.vue` |
| Menu tambahan sisi kasir | `resources/js/Components/CashierTopbar.vue` |
| Katalog modul & flag `is_owner` sisi platform | `config/platform-rbac.php`, `app/Models/PlatformUserModule.php`, `app/Models/PlatformUser.php` |
| Gerbang akses platform | `app/Http/Middleware/EnsurePlatformModule.php`, `EnsurePlatformOwner.php` |
| Audit log sensitif (tanpa dedup) | `app/Models/PlatformAuditLog.php`, dipakai di `app/Http/Controllers/Platform/RevenueController.php` |
| Isolasi data per baris | `app/Models/Scopes/TenantScope.php` |

### 4.4 Poin jual

- Pemilik toko bisa mendelegasikan operasional tanpa mendelegasikan risiko — granularitas sampai level modul, bukan cuma "admin vs staf".
- Struktur data mencegah kebocoran lintas-tenant secara arsitektural, bukan hanya lewat pengecekan aplikasi yang bisa lupa ditulis.
- Modul sensitif (pembayaran, AI) punya friksi tambahan by design — mengurangi risiko staf mendapat akses berbahaya "tanpa sadar".
- Setiap akses ke data paling sensitif (omzet tenant) bisa diaudit satu per satu — penting untuk kepercayaan tenant terhadap platform.

---

## 5. AI Analysis — Insight Bisnis Berbasis AI

### 5.1 Masalah bisnis yang diselesaikan

Pemilik UMKM umumnya tidak punya waktu atau kemampuan analisis data untuk membaca pola dari ratusan/ribuan transaksi. AI Analysis mengubah data transaksi mentah milik tenant sendiri menjadi rekomendasi bisnis dalam bahasa manusia — tanpa tenant perlu tahu apa itu query SQL. Ini memberi SAPI nilai jual "asisten bisnis", bukan sekadar alat pencatat transaksi.

### 5.2 Cara kerja

Owner memilih salah satu dari empat jenis analisis:

| Jenis | Menjawab pertanyaan |
|---|---|
| **general** | Insight bisnis umum, anomali, dan rekomendasi (default) |
| **discount** | Produk mana yang layak didiskon, berapa besar — mempertimbangkan margin per item, produk laris, dan stok mati |
| **profit_projection** | Profit aktual periode ini + proyeksi periode depan + rekomendasi menaikkan profit |
| **custom** | Pertanyaan bebas milik owner sendiri |

Setelah owner submit, permintaan **diproses secara asynchronous lewat job antrian** (bukan menunggu di layar) — statusnya berjalan `pending → processing → completed/failed`, dan UI otomatis polling setiap 3 detik untuk menampilkan progres, lengkap dengan skeleton loading dan pesan error yang jelas kalau gagal.

Job itu (`RunAiAnalysisJob`) melakukan:
1. Cek dulu apakah tenant punya fitur AI aktif — gagal cepat kalau tidak.
2. Merakit **konteks data nyata** milik tenant: omzet & jumlah transaksi periode terpilih, 10 produk terlaris, tren harian, perhitungan profit & proyeksi, serta badge kondisi stok (rendah, dsb). **Sengaja tidak menyertakan data pribadi pelanggan** (nama, nomor meja) — hanya angka agregat bisnis.
3. Memanggil provider AI yang aktif untuk tenant tersebut, mengirim konteks itu sebagai bahan.
4. Menyimpan hasil teks + jumlah token yang terpakai; kalau gagal, status ditandai gagal beserta pesan errornya — transaksi/data tenant tidak pernah ikut rusak akibat kegagalan AI.

**Provider AI bisa diganti-ganti** (SumoPod, Gemini, OpenAI, Anthropic) lewat arsitektur provider yang seragam — tenant bisa memakai kunci API gratis bawaan SAPI (kuota harian terbatas) atau memasukkan **kunci API miliknya sendiri (BYOK — Bring Your Own Key)** untuk pemakaian tanpa batas kuota. Kunci BYOK disimpan **terenkripsi** di database dan tidak pernah ditampilkan mentah kembali ke UI — hanya status "sudah diset atau belum".

**Kuota harian** untuk kunci gratis bersama diatur oleh satu kelas tunggal yang dipakai baik untuk penegakan (menolak request kalau kuota habis) maupun untuk menampilkan sisa kuota ke owner — sehingga angka yang ditampilkan **selalu sama** dengan angka yang menentukan boleh/tidaknya request jalan. Kuota bisa berbeda per paket langganan (plan tertentu bisa dapat kuota lebih besar, atau nol jika paketnya tidak menyertakan AI).

### 5.3 Peta kode

| Lapisan | File |
|---|---|
| Kontroler & alur request/tampil hasil | `app/Http/Controllers/Owner/AiAnalysisController.php` |
| Job async utama (jantung fitur ini) | `app/Jobs/RunAiAnalysisJob.php` |
| Perakit konteks data bisnis untuk prompt | `app/Services/AiContextService.php` |
| Model hasil analisis | `app/Models/AiAnalysis.php` |
| Abstraksi & pabrik provider AI | `app/Services/Ai/AiProviderFactory.php`, `app/Services/Ai/AiProvider.php` |
| Implementasi provider | `app/Services/Ai/OpenAiProvider.php`, `SumoPodProvider.php`, `GeminiProvider.php`, `AnthropicProvider.php` |
| Sistem kuota (single source of truth) | `app/Services/Ai/AiQuota.php` |
| Pencatat pemakaian harian | `app/Models/AiUsage.php` |
| Konfigurasi provider default & kuota | `config/ai.php` |
| Pengaturan BYOK sisi owner | `app/Http/Controllers/Owner/SettingsController.php`, `resources/js/Pages/Owner/Settings/Index.vue` |
| UI owner (form, lifecycle, hasil) | `resources/js/Pages/Owner/AiAnalysis/Index.vue` |
| Gerbang akses (fitur + izin RBAC) | `routes/web.php` (middleware `feature:ai` + `permission:ai_analysis`) |

### 5.4 Poin jual

- Bukan chatbot generik — jawaban selalu berbasis data transaksi nyata milik tenant, bisa diverifikasi ke database.
- Model bisnis fleksibel: gratis dengan kuota untuk tenant kecil, BYOK tanpa batas untuk tenant yang serius pakai AI intensif — kuota bisa jadi pengungkit upsell paket lebih tinggi.
- Terisolasi penuh per tenant (percobaan lintas-tenant otomatis mendapat 404, bukan bocor data) dan bisa dibatasi lewat RBAC sehingga owner tetap yang memutuskan siapa staf yang boleh memakainya (modul ini ditandai sensitive & default nonaktif).
- Kegagalan provider AI tidak pernah mengganggu operasional POS — sepenuhnya terisolasi di job terpisah.

---

## 6. Subscription & Billing — Siklus Hidup Langganan Multi-Tenant

### 6.1 Masalah bisnis yang diselesaikan

SaaS butuh cara otomatis untuk menagih ratusan tenant tanpa staf manual mengecek satu-satu, **tapi tetap manusiawi** — tenant yang telat bayar tidak boleh tiba-tiba kehilangan akses ke datanya sendiri. SAPI merancang mesin siklus langganan bertingkat: `trial → grace → suspended`, dengan aturan tegas soal apa yang tetap bisa diakses di tiap tahap.

### 6.2 Cara kerja

**Mesin status:** setiap tenant berjalan lewat `trial` (masa coba, default 30 hari) → jika periode berakhir tanpa pembayaran, otomatis masuk `grace` (30 hari, **read-only** — semua data tetap bisa dibaca/diekspor, hanya operasi tulis yang diblokir) → jika grace juga terlewati, baru `suspended` (akses diblokir penuh). **Halaman tagihan (`/langganan`) dan logout selalu bisa diakses di status apa pun** — supaya tenant yang suspended tetap punya jalan untuk membayar dan keluar, tidak terjebak.

Perpindahan status dijalankan oleh **command terjadwal** yang berjalan otomatis (bukan dicek real-time saat login), sehingga performa aplikasi tidak terbebani logika tanggal di setiap request.

**Model penagihan seat (jumlah user aktif):** tagihan mengikuti **titik puncak** jumlah user aktif dalam satu periode (`seat_high_water`), bukan jumlah aktif di hari penagihan — ini sengaja dirancang supaya menonaktifkan staf sehari sebelum tanggal tagih tidak bisa dipakai untuk menghemat biaya. Titik puncak ini di-reset setiap kali invoice bulanan baru diverifikasi lunas, memulai periode baru dengan hitungan segar.

**Panel admin platform (SAPI internal):** staf platform bisa melihat status langganan seluruh tenant, mengatur ulang jumlah seat secara manual (dengan alasan wajib dicatat untuk audit), membuat & memverifikasi invoice, dan — hanya untuk tenant yang menyetujui — melihat data omzet dalam rupiah persis di halaman terpisah yang setiap kunjungannya tercatat sebagai akses sensitif.

**Grandfathering ditegakkan di titik verifikasi invoice:** begitu sebuah invoice diverifikasi lunas, jumlah yang **benar-benar dibayar** itulah yang dikunci sebagai tarif periode berikutnya (`price_locked`) — bukan dibaca ulang dari tabel tarif saat itu juga. Ini menyambung langsung ke mekanisme Dynamic Pricing di bagian 3.

**Isolasi data khusus untuk model billing:** model-model terkait langganan (`Subscription`, `Invoice`, metrik omzet bulanan) **sengaja tidak** memakai filter tenant otomatis yang dipakai model operasional lain — karena akun staf platform tidak punya `tenant_id`, filter otomatis itu justru akan membuat data selalu kosong tepat di panel yang membutuhkannya. Sebagai gantinya, isolasi ditegakkan lewat kombinasi test arsitektur, kelas Resource/DTO khusus, dan filter eksplisit di satu-satunya job yang menghitung omzet.

### 6.3 Peta kode

| Lapisan | File |
|---|---|
| Konfigurasi durasi trial/grace | `config/subscription.php` |
| Model paket & langganan | `app/Models/Plan.php`, `app/Models/Subscription.php` |
| Logika bisnis siklus (switch track, revert, dsb) | `app/Services/SubscriptionService.php` |
| Command terjadwal penggerak status | `app/Console/Commands/AdvanceSubscriptionLifecycle.php` |
| Middleware penegak status (grace/suspended + halaman terkecualikan) | `app/Http/Middleware/EnsureSubscriptionActive.php` |
| Halaman tagihan tenant | `app/Http/Controllers/Billing/SubscriptionController.php`, `resources/js/Pages/Billing/Show.vue` |
| Panel admin platform — daftar & kelola langganan tenant | `app/Http/Controllers/Platform/SubscriptionController.php`, `resources/js/Pages/Platform/Tenants/Show.vue` |
| Pembuatan, verifikasi, penolakan invoice | `app/Http/Controllers/Platform/InvoiceController.php` |
| Halaman omzet rupiah persis (sensitif) | `app/Http/Controllers/Platform/RevenueController.php` |
| Perakit data ringkas tenant untuk panel platform | `app/Services/Platform/AccountOverview.php` |

### 6.4 Poin jual

- Kebijakan "tidak langsung diputus" (trial → grace → suspended) menjaga kepercayaan tenant — sesuai realita UMKM yang kadang telat bayar tapi tetap butuh data mereka.
- Model seat anti-akal-akalan (`seat_high_water`) memastikan pendapatan SAPI tidak bocor lewat toggling staf.
- Setiap harga yang pernah dibayar terkunci dan tidak berubah sepihak — dasar kepercayaan kontraktual jangka panjang (terhubung ke Dynamic Pricing).
- Panel platform memberi tim internal SAPI visibilitas penuh untuk operasional (invoice, seat, revenue) tanpa mengorbankan privasi tenant yang belum menyetujui keterbukaan data.

---

## 7. Bagaimana Kelima Fitur Ini Saling Terkait

```
Tenant beroperasi di POS
        │
        ├─ Upsell Cerdas ─── menaikkan omzet tenant per transaksi
        │                     (data transaksi yang sama juga jadi bahan AI Analysis)
        │
        ├─ AI Analysis ────── mengubah data transaksi jadi rekomendasi bisnis
        │
        ├─ RBAC/ABAC ──────── mengatur siapa di tenant yang boleh sentuh modul mana
        │                     (termasuk siapa boleh pakai AI Analysis / lihat pembayaran)
        │
        └─ Omzet tenant (jika consent Harga Adaptif aktif)
                │
                ▼
        Dynamic Pricing ──── menghitung tarif langganan tenant ke SAPI
                │
                ▼
        Subscription & Billing ── menagih, mengunci harga (grandfathering),
                                    menjaga akses tetap manusiawi
```

Semua lima fitur berjalan di atas fondasi arsitektur multi-tenant yang sama (`TenantScope`, middleware `EnsureTenant`) dan panel operasional platform (`/platform/*`) yang didokumentasikan penuh di `docs/phases-2/PHASE-SAAS_Platform-Console-Subscription.md` dan `docs/phases-2/PHASE-RBAC_Module-Access-Control.md`.

Untuk detail keputusan produk yang mengikat (mis. definisi seat, kebijakan grace period, aturan pindah jalur harga), lihat entri terkait di `docs/CHANGELOG.md` (cari lewat tabel *Indeks Entri*) dan item terbuka di `docs/BACKLOG.md`.
