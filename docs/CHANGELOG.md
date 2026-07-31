# SAPI — Changelog & Revision Log

**Format:** Setiap perubahan yang dilakukan **di luar fase/phase** yang sudah direncanakan, atau perubahan keputusan arsitektur, WAJIB dicatat di file ini.

---

## Cara Membaca File Ini

File ini bersifat append-only dan akan terus membesar. **Jangan pernah membacanya utuh.** Pintu masuknya adalah [Indeks Entri](#indeks-entri) di bawah: cari entri yang relevan di sana, lalu baca hanya potongan entrinya.

Bila indeks terasa kurang, headingnya bisa didaftar langsung dari filenya (dan daftar ini tidak pernah basi):

```bash
grep -n '^### ' docs/CHANGELOG.md
```

---

## Cara Menggunakan File Ini

### Kapan Harus Dicatat
- Perubahan skema database yang tidak ada di dokumen fase
- Perubahan keputusan arsitektur (auth method, status naming, dll)
- Hotfix atau patch di luar fase yang sedang berjalan
- Penambahan dependency baru yang tidak direncanakan
- Perubahan API contract / endpoint
- Bug fix kritis yang mengubah behavior

### Format Entry

```markdown
### [TIPE] Judul Singkat
- **Tanggal:** YYYY-MM-DD
- **Fase Terkait:** Phase-X / Di Luar Fase / Cross-Phase
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config
- **Breaking Change:** Ya / Tidak
- **Deskripsi:** Penjelasan singkat perubahan
- **Alasan:** Kenapa perubahan ini dilakukan
- **File Terdampak:**
  - `path/to/file.php` — deskripsi perubahan
- **Catatan Migrasi:** (jika ada) Instruksi khusus untuk apply perubahan
```

Entri baru ditulis di paling atas bagian [Revision History](#revision-history), **dan wajib disertai satu baris baru di paling atas [Indeks Entri](#indeks-entri)**. Entri tanpa baris indeks tidak akan ditemukan.

### Tipe Entry
| Tag | Keterangan |
|---|---|
| `[RECONCILE]` | Rekonsiliasi perbedaan antara dokumen v1.0 ↔ v1.1 ↔ diskusi |
| `[HOTFIX]` | Perbaikan mendesak di luar fase |
| `[DECISION]` | Perubahan keputusan arsitektur/teknis |
| `[ADDITION]` | Penambahan fitur/komponen yang tidak direncanakan |
| `[SCHEMA]` | Perubahan skema database |
| `[DEPRECATE]` | Fitur/approach yang ditinggalkan |

---

## Indeks Entri

Satu baris per entri, urut dari terbaru — sama dengan urutan isinya di bawah. Kolom **Judul** ditulis persis seperti headingnya supaya bisa langsung dicari (`grep -n 'Judul' docs/CHANGELOG.md`).

| Tanggal | Tipe | Area | Judul |
|---|---|---|---|
| 2026-08-01 | ADDITION | Platform | Rincian Tenant Bertab, dan Daftarnya Kembali Jadi Daftar (BL-042) |
| 2026-07-31 | DECISION | Platform | Jenis Usaha Berpindah ke Pemilik Toko, dan "Subsidi" Jadi "Harga Adaptif" |
| 2026-07-31 | ADDITION | Platform | Konsol Platform: Langganan & Tagihan Menyatu, Batas Pengguna Bisa Diatur |
| 2026-07-31 | ADDITION | Langganan | Pintu Masuk Langganan & Ringkasan Tagihan di Dashboard (BL-040) |
| 2026-07-31 | ADDITION | Kasir | Penawaran Wajib Diselesaikan & Status "Ditolak" Terpisah (BL-025) |
| 2026-07-31 | DECISION | Dokumentasi | Indeks Entri & Protokol Baca untuk CHANGELOG/BACKLOG |
| 2026-07-31 | ADDITION | Kasir | Tagihan Terbuka Pindah ke Topbar Kasir (BL-023) |
| 2026-07-31 | HOTFIX | Langganan | Turunan Periode Bulanan Meluber di Bulan Pendek (BL-029) |
| 2026-07-31 | HOTFIX | Kasir | Riwayat Kasir Dibatasi ke Sesi Kas Berjalan (BL-027) |
| 2026-07-31 | HOTFIX | Kasir | Split Bill: Nominal Non-Tunai Jadi Turunan, & Cukup-Bayar Diuji Terhadap Harga DB (BL-021, BL-022) |
| 2026-07-31 | HOTFIX | Kas | Rekonsiliasi Kas Menghitung Penjualan Tunai, Per-Laci, & Per-Tanggal Efektif (BL-028 Tahap A) |
| 2026-07-29 | ADDITION | Dapur | Papan Antrian Dapur (BL-019) |
| 2026-07-29 | ADDITION | Langganan | Fondasi Capability Flags & Gerbang Tersinkron |
| 2026-07-29 | HOTFIX | Self-Order | Gerbang Langganan di Jalur Self-Order (BL-020) |
| 2026-07-27 | ADDITION | Stok | Saran Jual dari Sinyal Stok (BL-017) |
| 2026-07-27 | SCHEMA | Langganan | Dimensi Harga Bebas — Aturan Berkriteria (BL-015) |
| 2026-07-25 | ADDITION | Dokumentasi | Hub Dokumentasi Publik — Dua Jalur |
| 2026-07-25 | ADDITION | Mobile API | Permissions RBAC di Mobile API (BL-003) |
| 2026-07-25 | ADDITION | Infra | Pesan Validasi Berbahasa Indonesia (BL-004) |
| 2026-07-25 | ADDITION | Auth | Peringatan Login Gagal & Verifikasi Email (BL-011, BL-014) |
| 2026-07-25 | ADDITION | Langganan | Jalur Subsidi & Aturan Harga Dinamis — PHASE SAAS Tahap C & D (BL-005, BL-006) |
| 2026-07-25 | ADDITION | Langganan | Langganan Dasar Jalur Normal — PHASE SAAS Tahap B (BL-005) |
| 2026-07-22 | ADDITION | Auth | Pemulihan Kata Sandi Pengguna Tenant (BL-012) |
| 2026-07-22 | ADDITION | Platform | Pemulihan Kata Sandi Akun Platform (BL-010) |
| 2026-07-22 | ADDITION | Platform | Halaman & Retensi Jejak Audit (BL-009) |
| 2026-07-21 | ADDITION | Platform | Manajemen Akun Platform (BL-008) |
| 2026-07-21 | HOTFIX | Auth | Rate Limit Endpoint Login (BL-007) |
| 2026-07-21 | ADDITION | Platform | Platform Console — Fondasi Panel Pemilik SaaS (Tahap A) |
| 2026-07-21 | HOTFIX | RBAC | Lima Kendala Implementasi RBAC & Resolusinya |
| 2026-07-21 | ADDITION | RBAC | RBAC — Kontrol Akses Modul untuk Staf Non-Owner |
| 2026-07-21 | HOTFIX | Test | Ekspektasi Dua Test Usang (BL-002) |
| 2026-07-16 | ADDITION | PWA | Transaksi Offline + Sinkronisasi (PWA Fase B/C/D) |
| 2026-07-15 | HOTFIX | AI | Penomoran Ordered List Hasil AI (BL-001) |
| 2026-07-15 | SCHEMA | PWA | Idempotency `client_uuid` di Checkout (PWA Fase A) |
| 2026-07-14 | ADDITION | Transaksi | Edit Transaksi (Owner & Kasir) + Recalc Stok & Audit Trail |
| 2026-07-11 | DECISION | AI | SumoPod Jadi Provider AI Default (OpenAI-Compatible Gateway) |
| 2026-07-11 | ADDITION | AI | MCP Server: Data Bridge Read-Only untuk AI Client Milik Owner |
| 2026-07-10 | DECISION | AI | RunAiAnalysisJob Autentikasi sebagai Pemilik Analisis (Tenant Scoping di Queue) |
| 2026-05-29 | ADDITION | Mobile API | Public API Reference Page for Mobile POS |
| 2026-05-29 | DECISION | Mobile API | API Consumer Dipindahkan ke Prefix `/api/v1` |
| 2026-05-28 | ADDITION | UI | Owner Modifier Quick Settings, Cashier Account Menu, and UI Stability Fixes |
| 2026-05-28 | ADDITION | UI | UI System Refresh, Cashier Flow Guard, and Shared Components |
| 2026-05-25 | ADDITION | Mobile API | Mobile API Phase 2: Operasi Kasir Lengkap |
| 2026-03-07 | ADDITION | Kasir | UX POS: Auto-Amount Non-Cash, Thermal Receipt, Open Bill Customer Name |
| 2026-03-07 | ADDITION | Dashboard | Dashboard Owner: Badge Self Order pada Transaksi Terbaru |
| 2026-03-06 | ADDITION | Infra | Integrasi Dependency Xendit SDK (Persiapan Payment Gateway) |
| 2026-03-06 | RECONCILE | Transaksi | Transaction Status Naming |
| 2026-03-06 | RECONCILE | Stok | Stock Movement Type Naming |
| 2026-03-06 | DECISION | Auth | Auth Method: Manual → Laravel Sanctum (SPA Mode) |
| 2026-03-06 | SCHEMA | Kas | Penambahan Tabel `cash_drawers` |
| 2026-03-06 | SCHEMA | Produk | Penambahan Kolom `expiry_date` di `product_variants` |
| 2026-03-06 | SCHEMA | Infra | Penambahan `deleted_at` (SoftDeletes) di 6 Tabel |
| 2026-03-06 | DECISION | Kas | `expected_amount` Cash Drawer = Semua Payment Method |
| 2026-03-06 | ADDITION | Infra | Tenant Isolation via Global Scope + Trait |
| 2026-03-06 | ADDITION | Infra | Image Upload Service dengan Konversi WEBP |
| 2026-03-06 | ADDITION | Kasir | Flash `lastTransaction` via Inertia untuk Receipt Modal |
| 2026-03-06 | ADDITION | Kas | Redirect Tutup Kas ke Summary Page |
| 2026-03-06 | ADDITION | Kas | Transaction Count di Cash Drawer Summary |
| 2026-03-06 | ADDITION | Dashboard | Dependency chart.js + vue-chartjs untuk Dashboard Chart |
| 2026-03-06 | ADDITION | UI | Navigasi Sidebar Owner Ditambah Menu Phase 5 |
| 2026-03-06 | HOTFIX | Stok | BadgeHelperService Dead Stock Query Bug |
| 2026-03-06 | HOTFIX | Produk | CategoryController Soft Delete Tidak Nullify product.category_id |
| 2026-03-06 | SCHEMA | Stok | Penambahan 'void' di ENUM stock_movements.type (Create Migration) |
| 2026-03-06 | ADDITION | Infra | HasFactory Trait pada Semua Model |
| 2026-03-06 | ADDITION | Kasir | Fitur Open Bill (Simpan Pesanan Tanpa Bayar) |
| 2026-03-06 | SCHEMA | Transaksi | Penambahan Kolom `notes` di `transaction_items` |
| 2026-03-06 | ADDITION | Kasir | Catatan (Notes) per Item di Halaman POS |
| 2026-03-06 | ADDITION | Kasir | Halaman Riwayat Transaksi Kasir |
| 2026-03-06 | DECISION | Kas | Reversal: `expected_amount` Cash Drawer = Cash Only (Bukan Semua Payment) |
| 2026-03-06 | ADDITION | Kasir | Perbaikan Payment Modal (Lebar, Sticky Total, Denominasi, Format Angka) |
| 2026-03-06 | ADDITION | UI | Sidebar Owner Grouped dengan Dropdown Collapsible |
| 2026-03-06 | SCHEMA | Self-Order | Penambahan Kolom Self-Order & Fulfillment di `transactions` |
| 2026-03-06 | ADDITION | Self-Order | API Layer untuk Self Order (Sanctum + Xendit) |
| 2026-03-06 | DECISION | Self-Order | Self-Order: Stok Tidak Dikurangi Sebelum Bayar |
| 2026-03-06 | DECISION | Self-Order | Fulfillment Tracking: Opsional, Terpisah dari Payment Status |

---

## Revision History

---

### [ADDITION] Rincian Tenant Bertab, dan Daftarnya Kembali Jadi Daftar (BL-042)
- **Tanggal:** 2026-08-01
- **Fase Terkait:** Di Luar Fase — `[BL-042]`
- **Dampak:** Routing | Service | Controller | Resource | Frontend | Test
- **Breaking Change:** Ya, pada rute. Rincian akun pindah dari `GET /platform/subscriptions/{tenant}` ke **`GET /platform/tenants/{tenant}`**, dan omzet dari `GET /platform/subscriptions/{tenant}/revenue` ke **`GET /platform/tenants/{tenant}/revenue`**. Keduanya **mengalihkan**, bukan mati. Rute tulis tidak berubah — `PUT /platform/subscriptions/{subscription}/seats` tetap di alamatnya.
- **Deskripsi:** Halaman Daftar Tenant tidak lagi memipihkan tiap klien jadi lima kolom informasi. Yang tersisa adalah nama, pemilik, status, dan satu tombol **Lihat detail**; jenis usaha, jumlah akun, dan tanggal terdaftar pindah ke rinciannya. Rinciannya sendiri berpindah kepemilikan ke modul Daftar Tenant dan dipecah jadi lima tab — **Ikhtisar · Langganan · Tagihan · Kapabilitas · Omzet** — dengan tab baru **Kapabilitas** yang menampilkan fitur kasir yang menyala untuk toko itu (`kitchen_queue`, `self_order`, `ai`), sesuatu yang sebelumnya tidak pernah ikut di payload platform sama sekali.
- **Alasan:** Satu-satunya jalan ke rincian adalah nama tenant yang diam-diam bisa diklik, dan tautannya menunjuk halaman yang digerbang modul lain: daftarnya `tenants`, rinciannya `subscriptions,payments`. Bagi pemilik SaaS yang memegang semua modul ini tidak terasa, tapi seluruh gerbang modul di panel ini justru dibuat supaya staf bisa diberi sebagian — dan staf ber-`tenants`-saja mendapat halaman yang setiap barisnya menaut ke 403. Rinciannya pun satu gulungan: empat urusan yang berbeda umur dan berbeda kepekaan berbaris vertikal, dan karena riwayat tagihan sengaja tidak dipotong, tenant berumur setahun mendorong panel omzet keluar layar.
- **File Terdampak:**
  - `routes/web.php` — `tenants.show` baru, `revenue.show` pindah, dua `Route::redirect` untuk alamat lama
  - `app/Http/Controllers/Platform/TenantController.php` — `show()`, audit `tenants.show`
  - `app/Http/Controllers/Platform/SubscriptionController.php` — `show()` dilepas
  - `app/Http/Controllers/Platform/RevenueController.php` — merender `Platform/Tenants/Show`
  - `app/Services/Platform/AccountOverview.php` — katalog `CAPABILITIES`, `user_count`, `can.tenants`
  - `app/Http/Resources/Platform/TenantResource.php` — `status`
  - `resources/js/Components/Platform/TabNav.vue` — **baru**
  - `resources/js/Pages/Platform/Tenants/Show.vue` — **baru**, menggantikan `Subscriptions/Show.vue` yang dihapus
  - `resources/js/Pages/Platform/Tenants/Index.vue` — kolom disusutkan, tombol aksi
  - `tests/Feature/Platform/PlatformTenantDetailTest.php` — **baru**, 9 test
  - `tests/Feature/Platform/{PlatformBillingTest,PlatformRevenueTest,PricingDimensionTest}.php` — URL & nama komponen disesuaikan
- **Keputusan yang perlu diingat:**
  - **Tab Kapabilitas HANYA MEMBACA.** Tidak ada tombol menyalakan atau mematikan fitur kasir dari panel platform, mengikuti alasan yang sama seperti pencabutan kuasa mengubah jenis usaha: cara kerja usaha orang bukan milik penyedia layanannya. Yang diberikan halaman ini adalah kuasa **mengetahui** — pemilik SaaS tetap perlu tahu fitur apa yang aktif saat menjawab keluhan atau menjelaskan tarif. Ada satu test yang sengaja menjaga ketiadaan rute pengubahnya.
  - **Nilainya dibaca lewat `Tenant::hasFeature()`, bukan kolomnya langsung.** Menambah fitur kelak cukup satu baris di katalog `AccountOverview::CAPABILITIES` plus satu cabang di `hasFeature()` — bukan satu kolom baru yang harus diingat di tiga tempat.
  - **Gerbang rinciannya "salah satu cukup" dari TIGA modul** (`tenants,subscriptions,payments`), karena dua daftar bermuara ke halaman yang sama. Menyempitkannya ke `tenants` hanya akan memindahkan 403 yang sama ke pintu yang lain: pemegang modul tagihan sampai ke sini dari daftar langganan. Isinya tetap disaring per modul di `AccountOverview` — yang tidak boleh dilihat tidak ikut terkirim.
  - **Omzet tetap rute tersendiri yang beraudit.** Yang berubah hanya alamatnya; ia masih merender komponen yang sama dengan bagian omzetnya terisi, dan kini mendaratkan pembacanya langsung di tab Omzet. Membuka rincian tenant tetap tercatat sebagai kejadian **rutin** (terdeduplikasi), membuka omzetnya tetap **sensitif** (tanpa deduplikasi).
  - **Tab yang aktif dititipkan ke query string (`?tab=tagihan`), bukan disimpan di komponen saja.** Setiap tindakan tagihan berakhir dengan redirect kembali ke alamat ini; tanpa jejak di URL, memverifikasi satu bukti bayar akan melempar pembacanya keluar dari tab yang sedang ia kerjakan. Pengalihan dari alamat lama tidak membawa serta query string-nya — tautan lama memang tidak pernah punya.
  - **Tautan "kembali" mengikuti modul pembacanya.** Ke daftar tenant bila ia memegangnya, ke daftar langganan bila tidak. Tombol kembali yang menunjuk halaman terlarang adalah bentuk kecil dari kekeliruan yang sama seperti yang membuat halaman ini pindah.

---

### [DECISION] Jenis Usaha Berpindah ke Pemilik Toko, dan "Subsidi" Jadi "Harga Adaptif"
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — meninjau ulang keputusan `[BL-015]`
- **Dampak:** Schema (data) | Controller | Frontend | Test
- **Breaking Change:** Ya, kecil. Rute `PUT /platform/tenants/{tenant}/business-type` **dihapus**. Nilai `tenants.business_type` yang `null` di-backfill jadi `lainnya`.
- **Deskripsi:** Dua perubahan kosakata dan kewenangan yang berjalan bersama. Pertama, jenis usaha tidak lagi diubah dari panel platform — editornya pindah ke Owner → Pengaturan, kolomnya punya nilai bawaan `lainnya`, dan panel platform hanya membacanya. Kedua, istilah "subsidi" dicabut dari seluruh permukaan yang dibaca orang dan diganti **Harga Adaptif** (lawannya: **Harga Tetap**).
- **Alasan:** `[BL-015]` menempatkan pengubah jenis usaha di panel platform dengan alasan yang benar soal akibatnya — kolom itu dasar penetapan harga dan dibekukan ke `invoices.pricing_context` — tapi keliru soal siapa yang berhak. Yang tahu jenis usahanya adalah pemilik toko, dan keterangan usaha yang bisa diganti penyedia layanan tanpa sepengetahuan pemiliknya bukan keterangan yang sehat, sekalipun perubahannya tercatat di audit. Kebutuhan asli `[BL-015]` — tenant lama yang nilainya kosong — dijawab oleh nilai bawaan, bukan oleh kuasa mengedit milik orang lain. Soal istilah: "subsidi" menempatkan klien sebagai penerima bantuan, padahal yang terjadi adalah pertukaran — mereka membuka omzetnya, tarifnya menyesuaikan.
- **File Terdampak:**
  - `database/migrations/2026_07_31_150104_set_default_business_type_on_tenants_table.php` — backfill `null` menjadi `lainnya`
  - `app/Models/Tenant.php` — `BUSINESS_TYPE_DEFAULT` dan `$attributes`
  - `app/Http/Controllers/Auth/AuthController.php` — pendaftaran jatuh ke bawaan, bukan ke `null`
  - `app/Http/Controllers/Owner/SettingsController.php` — prop `businessTypes` dan validasi `sometimes|required`
  - `app/Http/Controllers/Platform/TenantController.php` — `updateBusinessType()` dihapus
  - `resources/js/Pages/Owner/Settings/Index.vue` — pemilih jenis usaha
  - `resources/js/Pages/Platform/Tenants/Index.vue` — dropdown jadi teks
  - `resources/js/support/platform.js` — kosakata `PRICING_TRACK`
  - `config/platform-rbac.php`, `config/docs.php`, `resources/js/Pages/Billing/*.vue`, `app/Http/Controllers/Billing/ConsentController.php` — istilah
  - `tests/Feature/Platform/PricingDimensionTest.php` — 3 test disesuaikan/ditambah
- **Keputusan yang perlu diingat:**
  - **Bawaannya `lainnya`, bukan tipe yang paling umum.** Menebak "kuliner" karena itu mayoritas berarti memasang dasar harga yang salah pada orang yang belum pernah ditanya. Bawaan netral tidak mengaku tahu apa pun, dan bisa diperbaiki pemiliknya kapan saja.
  - **Kolomnya tetap `nullable` di basis data.** Yang menjamin nilainya terisi adalah pendaftaran, `$attributes` model, dan form Pengaturan. Menaikkannya jadi `NOT NULL` hanya menambah risiko migrasi tanpa menutup celah yang masih terbuka.
  - **Validasinya `sometimes|required`, bukan `required`.** Nilai kosong ditolak, tapi field yang tidak dikirim sama sekali berarti "jangan sentuh". `PATCH /owner/settings` menerima beberapa bagian form sekaligus; `required` polos membuat pemanggil yang tidak berkepentingan dengan jenis usaha gagal menyimpan apa pun.
  - **Nilai di basis data TIDAK ikut berganti nama.** `pricing_track` tetap `normal` / `subsidized`, begitu pula `TenantConsent::TYPE_SUBSIDIZED` dan URL `/langganan/persetujuan/subsidized`. Yang berubah hanya apa yang dibaca orang. Mengganti nilai enum menuntut migrasi di tiga tabel demi nol manfaat.
  - **`resources/consents/subsidized-v1.md` SENGAJA tidak disentuh.** Dokumen persetujuan berversi, dan `ConsentService::hasAgreedToCurrent()` membandingkan versi — menyunting teksnya berarti setiap tenant jalur adaptif harus menyetujui ulang dokumen hukum hanya karena kita mengganti sebuah nama. Itu keputusan pemilik SaaS, bukan efek samping perapian kosakata. Selama belum diputuskan, dokumen itu satu-satunya tempat kata "subsidi" masih hidup.

---

### [ADDITION] Konsol Platform: Langganan & Tagihan Menyatu, Batas Pengguna Bisa Diatur
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — perapian konsol platform
- **Dampak:** Middleware | Service | Controller | Resource | Frontend | Test
- **Breaking Change:** Ya, pada rute. `GET /platform/invoices` kini **mengalihkan** ke `/platform/subscriptions`; `GET /platform/revenue/{tenant}` pindah ke `/platform/subscriptions/{tenant}/revenue`. Rute tulis tagihan tidak berubah.
- **Deskripsi:** Menu "Langganan" dan "Pembayaran" lebur jadi satu bagian **Langganan & Tagihan**, dengan halaman rincian per akun (`/platform/subscriptions/{tenant}`) yang menyatukan keadaan langganan, riwayat tagihan, dan — lewat tautan beraudit tersendiri — omzet bulanan jalur Harga Adaptif. Batas pengguna kini punya tombol pengatur beralasan. Seluruh halaman platform dipindahkan ke satu set komponen bersama, dan `PlatformLayout` disamakan strukturnya dengan `OwnerLayout`.
- **Alasan:** Tiga halaman terpisah yang tidak saling menaut membuat pertanyaan yang paling sering diajukan — "kenapa tenant ini ditangguhkan?" — hanya bisa dijawab dengan membuka dua halaman lalu mencocokkan namanya sendiri. Batas pengguna hanya bisa bergerak lewat tagihan yang dibayar tenant; untuk sisa keadaannya (salah pilih jumlah, kesepakatan di luar aplikasi, koreksi setelah bukti ditolak) satu-satunya penyelesaian adalah menyunting database. Dan panel platform menuliskan sendiri kartu, tabel, tombol, serta modalnya — sehingga padding, radius, dan warna tombolnya pelan-pelan menyimpang dari panel kasir dan owner.
- **File Terdampak:**
  - `app/Http/Middleware/EnsurePlatformModule.php` — menerima beberapa modul, "salah satu cukup"
  - `app/Services/Platform/AccountOverview.php` — **baru**, payload rincian akun
  - `app/Http/Controllers/Platform/SubscriptionController.php` — `show()`, `updateSeats()`, ringkasan tagihan
  - `app/Http/Controllers/Platform/RevenueController.php` — merender komponen yang sama
  - `app/Http/Resources/Platform/InvoiceResource.php` — `kind`, `grants_seats`, `previous_seats`
  - `resources/js/Components/Platform/*.vue` — **baru** (PageHeader, Panel, DataTable, StatusBadge, StatCard, FormField, Notice)
  - `resources/js/support/platform.js` — **baru**, format dan kosakata bersama
  - `resources/js/Components/{Button,Modal,ConfirmDialog}.vue` — pindah ke token tema
  - `resources/js/Layouts/PlatformLayout.vue` — disamakan dengan OwnerLayout
  - `resources/js/Pages/Platform/**` — seluruhnya; `Invoices/Index.vue` dan `Revenue/Show.vue` dihapus
  - `tests/Feature/Platform/{PlatformBillingTest,PlatformRevenueTest}.php` — 5 test baru, URL disesuaikan
- **Keputusan yang perlu diingat:**
  - **Omzet TIDAK ikut di halaman rincian akun.** Ia punya rute sendiri (`/subscriptions/{tenant}/revenue`) yang merender komponen yang sama dengan bagian omzetnya terisi. Yang dijaga bukan halaman yang terpisah melainkan **tindakan** yang terpisah: menengok keadaan langganan tenant tidak boleh menghasilkan catatan "membuka data bisnis klien" yang tak pernah benar-benar terjadi.
  - **`platform.can` kini menerima beberapa modul dengan makna "salah satu cukup".** Dipakai hanya pada rute BACA halaman gabungan. Rute tulis tetap digerbang satu modul — `updateSeats` menuntut `subscriptions`, bukan `subscriptions,payments`: yang hanya memegang tagihan tidak berkepentingan mengubah batas paket. Isi halaman disaring per modul di controller, dan yang tidak boleh dilihat **tidak ikut terkirim** — bukan terkirim lalu disembunyikan di Vue.
  - **Menurunkan batas pengguna di bawah pemakaian aktif diizinkan.** Tidak ada akun yang dinonaktifkan karenanya, mengikuti alasan yang sama seperti penolakan bukti bayar: yang tertutup adalah penambahan berikutnya, bukan pekerjaan orang yang sedang berjalan.
  - **`reason` wajib pada perubahan batas pengguna.** Angka sebelum-sesudah saja tidak menjawab "kenapa", dan itulah pertanyaan yang diajukan tenant.
  - **`JsonResource::collection()` MENGUBAH isi paginator.** Peta seat, tagihan, dan kelompok harga harus dihitung sebelum resource dirakit; sesudahnya `getCollection()` mengembalikan resource, bukan model. Sudah menjatuhkan halaman ini sekali.
  - **Halaman masuk platform tetap memakai `bg-slate-800`.** Warna gelapnya memang pembeda yang disengaja antara konsol pengelola dan panel tenant; yang diseragamkan adalah metrik — padding, radius, tinggi baris, bentuk tombol dan modal — bukan paletnya.

---

### [ADDITION] Pintu Masuk Langganan & Ringkasan Tagihan di Dashboard (BL-040)
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — menutup `[BL-040]`
- **Dampak:** Service | Controller | Frontend | Test
- **Breaking Change:** Tidak. Tidak ada rute, kolom, maupun bentuk data yang berubah; yang ditambahkan hanya satu prop baru di dashboard dan satu entri navigasi.
- **Deskripsi:** Halaman `/langganan` kini punya pintu masuk tetap — entri "Langganan & Tagihan" di grup Pengaturan pada sidebar owner — dan dashboard membawa ringkasan keadaan langganan: label status, jalur harga, tanggal berakhirnya periode, serta tagihan terbuka berikut nominal dan jatuh temponya.
- **Alasan:** Seluruh isi yang diminta pemilik sebenarnya sudah dirender halaman langganan sejak `PHASE SAAS`. Yang tidak ada adalah cara menemukannya: tidak satu pun berkas di `resources/js` menaut ke sana selain halaman Billing itu sendiri. Praktis, owner baru sampai ke sana kalau mengetik URL-nya, atau kalau langganannya **sudah terlanjur bermasalah** dan gerbangnya melempar ke sana — persis kebalikan dari gunanya.
- **File Terdampak:**
  - `app/Services/SubscriptionService.php` — `suspensionDateFor()` dan `outstandingInvoice()`
  - `app/Http/Controllers/Owner/DashboardController.php` — prop `subscription`
  - `app/Http/Controllers/Billing/SubscriptionController.php` — perhitungan `suspends_at` pindah ke service
  - `resources/js/Layouts/OwnerLayout.vue` — entri navigasi
  - `resources/js/Pages/Owner/Dashboard.vue` — kartu ringkasan
  - `tests/Feature/Owner/DashboardTest.php` — 7 test baru
- **Keputusan yang perlu diingat:**
  - **Tanggal penangguhan berpindah ke `SubscriptionService::suspensionDateFor()`.** Sebelumnya ia dihitung inline di controller Billing; menyalinnya ke dashboard akan melahirkan kebenaran kedua yang bercabang begitu `grace_days` diubah. Ia tetap dihitung, bukan disimpan.
  - **Entri navigasinya `ownerOnly`, rutenya TIDAK.** Halaman langganan sengaja terbuka untuk semua pengguna tenant — begitu tenant ditangguhkan setiap halaman lain mengarah ke sana, dan kasir yang sedang bekerja tidak boleh mendarat di 403. Yang dibatasi hanya pintu masuk tetapnya: tagihan adalah urusan owner dengan penyedia layanan.
  - **`rejected` dan `awaiting_verification` ikut dihitung tagihan terbuka.** `rejected` justru yang paling perlu terlihat — buktinya ditolak, jadi tagihannya kembali menunggu tindakan. `awaiting_verification` tidak menuntut apa-apa dari tenant, tapi menyembunyikannya membuat owner mengira tak ada tagihan sama sekali sampai buktinya ternyata ditolak.
  - **Diurut menurut jatuh tempo, bukan id.** Tagihan upgrade terbit di tengah periode dan bisa jatuh tempo lebih dulu daripada tagihan bulanan yang nomornya lebih kecil.
  - **Tanpa tagihan terbuka, barisnya tidak muncul sama sekali** — bukan "Rp 0". Nominal nol adalah pernyataan tentang uang; diam bukan.
  - **Tanggal kalender dirakit komponennya, bukan lewat `new Date(string)`.** Pola yang sama dengan halaman Langganan: bentuk `'2026-08-21'` dibaca sebagai tengah malam UTC dan mundur sehari di zona yang di belakang UTC.
- **Catatan:** halaman `/langganan` tetap berdiri sendiri di luar `OwnerLayout` — ia harus tetap masuk akal bagi kasir dan bagi tenant yang ditangguhkan, dan ia sudah punya tautan "Kembali ke aplikasi" sendiri. Kartu ringkasan ini juga memperlihatkan `[BL-041]` secara telanjang di layar: tenant jalur normal menampilkan tarif **Rp 0**, karena paket `dasar` memang belum diberi angka.

---

### [ADDITION] Penawaran Wajib Diselesaikan & Status "Ditolak" Terpisah (BL-025)
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — menutup `[BL-025]`; berdiri di atas `[BL-017]`
- **Dampak:** Migration | Model | Service | Controller | Frontend | Test
- **Breaking Change:** Tidak. Kolom baru bawaannya **mati**, jadi outlet yang tidak menyalakannya berperilaku persis seperti sebelumnya.
- **Deskripsi:** Owner kini bisa mewajibkan tiap saran jual dijawab sebelum kasir boleh menekan BAYAR. Bersamaan dengan itu, tombol × berubah makna dari "tutup saran" jadi **"ditolak pelanggan"**, dan status itu disimpan terpisah dari `ignored`.
- **Alasan:** Tanpa saklar ini owner tidak punya cara memastikan penawaran benar-benar disampaikan — saran bisa ditutup begitu saja dan checkout tetap jalan. Dan tanpa memisahkan `rejected` dari `ignored`, menyalakan mode wajib justru akan **merusak** angka konversinya: semua saran jadi "terjawab", sehingga penyebutnya kehilangan arti.
- **File Terdampak:**
  - `database/migrations/..._add_upsell_mandatory_to_tenants_table.php` — kolom `upsell_mandatory`, default `false`
  - `app/Models/Tenant.php` — fillable, cast, dan `$attributes`
  - `app/Models/UpsellEvent.php` — `STATUS_REJECTED`, `statuses()`, `offeredStatuses()`
  - `app/Services/Upsell/UpsellIndexBuilder.php` — `mandatory` ikut indeks
  - `app/Services/Upsell/UpsellEventRecorder.php`, `app/Http/Requests/StoreTransactionRequest.php` — menerima status baru
  - `app/Http/Controllers/Owner/SettingsController.php`, `resources/js/Pages/Owner/Settings/Index.vue` — saklarnya
  - `app/Http/Controllers/Owner/ReportController.php`, `resources/js/Pages/Owner/Reports/Upsell.vue` — `offer_rate` di samping `conversion_rate`
  - `resources/js/composables/useUpsell.js`, `resources/js/Components/UpsellStrip.vue`, `resources/js/Pages/Cashier/POS.vue`
  - `tests/Feature/Upsell/UpsellEventTest.php` — 5 test baru
- **Keputusan yang perlu diingat:**
  - **× dan "Diterima" SAMA-SAMA menyelesaikan saran** (keputusan pemilik 2026-07-31). Yang tidak tersedia hanyalah melewatinya tanpa menjawab. Label lama "Tawarkan" diganti "Diterima" karena menekannya berarti pelanggan sudah setuju — barangnya langsung masuk keranjang, jadi kata "tawarkan" menggambarkan langkah yang sebenarnya sudah lewat.
  - **Saklarnya menumpang indeks upsell, bukan prop tersendiri.** Dengan begitu ia ikut ter-snapshot `useCatalogCache` dan tetap berlaku saat perangkat offline. Aturan yang mengikat online tapi bebas dilewati offline tidak mengikat apa pun.
  - **`upsell_mandatory` sengaja TIDAK masuk `Tenant::hasFeature()`.** Ia aturan kerja, bukan kapabilitas modul: tidak menggerbangi satu rute pun. Menaruhnya di sana akan mengaburkan arti `hasFeature()` yang selama ini berarti "permukaan ini ada atau tidak".
  - **Penegakannya di klien saja, dan itu memang cukup.** Ini disiplin kerja, bukan batas keamanan. Server **tidak** menolak checkout yang sarannya belum dijawab — prinsip yang sama sudah dipakai untuk `upsell_events`: statistik upsell tidak boleh punya kuasa menggagalkan penjualan yang sah.
  - **Laporan kini punya dua angka, dan bedanya penting.** `conversion_rate` dari semua yang tampil (ikut mengukur seberapa sering kasir menawarkan); `offer_rate` hanya dari yang benar-benar sampai ke pelanggan (menilai mutu sarannya). Menyalakan mode wajib akan menaikkan `conversion_rate` tanpa satu pun saran jadi lebih baik — `offer_rate` yang tidak bisa dikelabui begitu.
  - **Peringatan di halaman Settings menyebut risikonya apa adanya:** kasir yang terburu akan menekan "ditolak" tanpa menawarkan, dan lonjakan angka penolakan adalah tanda pertamanya. Fitur yang bisa menahan penjualan harus jujur soal cara ia bisa disiasati.
- **Catatan:** alert "kenapa tombol bayar mati" yang juga diminta `[BL-025]` sudah sebagian mendarat lebih dulu di `PaymentModal` bersama `[BL-021]`; entri ini melengkapinya di tombol BAYAR pada POS.

---

### [DECISION] Indeks Entri & Protokol Baca untuk CHANGELOG/BACKLOG
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — perawatan dokumentasi
- **Dampak:** Dokumentasi | Konvensi
- **Breaking Change:** Tidak untuk kode. Ya untuk konvensi: entri changelog baru **wajib** disertai satu baris di `## Indeks Entri`, dan entri backlog yang selesai dipindahkan ke `docs/BACKLOG-ARCHIVE.md`.
- **Deskripsi:** `docs/CHANGELOG.md` sudah 1.383 baris (130 KB, ~37k token) dan `docs/BACKLOG.md` 679 baris (95 KB, ~27k token), dan keduanya hanya bisa diakses dengan membaca utuh karena tidak ada lapisan pencarian. Tiga hal ditambahkan: (1) `## Indeks Entri` di CHANGELOG — satu baris per entri (69 entri, ~2k token), cukup untuk memilih entri tanpa membuka isinya; (2) 22 entri backlog yang sudah selesai pindah ke `docs/BACKLOG-ARCHIVE.md`, menyisakan tabel ringkas di file utama; (3) protokol bacanya ditulis di `CLAUDE.md` supaya berlaku otomatis di tiap sesi.
- **Alasan:** Laju pertumbuhannya ~11 entri/bulan (~26 KB/bulan) — akhir tahun CHANGELOG menembus 300 KB dan tidak lagi muat dibaca bersamaan dengan pekerjaan lain. Yang mahal sebetulnya bukan ukuran filenya, melainkan tidak adanya cara mengaksesnya selain membaca semuanya. Indeks + protokol memangkas biaya baca dari ~37k jadi ~2,5k token tanpa membuang satu kalimat pun dari riwayatnya.
- **File Terdampak:**
  - `docs/CHANGELOG.md` — bagian `Cara Membaca File Ini` dan `Indeks Entri` (69 entri)
  - `docs/BACKLOG.md` — 679 → 376 baris; `Riwayat Selesai` berubah jadi tabel indeks
  - `docs/BACKLOG-ARCHIVE.md` — **file baru**, 341 baris, 22 entri selesai
  - `CLAUDE.md` — bagian `Changelog & Backlog` + protokol baca berurutan
- **Keputusan yang perlu diingat:**
  - **Indeksnya dipelihara manual, dan itu risiko yang disadari.** Indeks bisa tertinggal dari isinya. Penangkalnya bukan disiplin semata: fallback `grep -n '^### '` diturunkan dari file itu sendiri dan tidak pernah basi, jadi indeks yang kurang satu baris tetap tidak membuat entrinya hilang.
  - **Indeks sengaja tidak menyimpan nomor baris.** Entri baru ditulis di paling atas, jadi setiap nomor baris bergeser tiap kali. Kunci pencariannya adalah judul entri — ditulis persis seperti headingnya — dan kode `BL-xxx`.
  - **CHANGELOG belum dipecah per kuartal.** Arsip bergilir baru berguna kalau indeksnya sendiri sudah mahal, dan pada 69 entri belum. Ambang berikutnya: saat file lewat ~2.000 baris, sisakan ~15 entri terbaru dan pindahkan sisanya ke `docs/changelog/YYYY-Qn.md`.
  - **Yang masih terbuka: `File Terdampak` menduplikasi git.** Bagian itu porsi terbesar tiap entri, padahal `git show <hash> --stat` menyimpannya lebih akurat. Memangkasnya (dengan mencantumkan commit hash di entri) belum dikerjakan karena mengubah bentuk entri lama, bukan sekadar menambah lapisan di atasnya.

---

### [ADDITION] Tagihan Terbuka Pindah ke Topbar Kasir (BL-023)
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — menutup `[BL-023]`
- **Dampak:** Middleware | Controller | Frontend | Test
- **Breaking Change:** Tidak untuk pengguna. Ya untuk kode: prop `openBills` **dihapus** dari `Cashier/POS`; sumbernya kini `page.props.cashier.openBills`.
- **Deskripsi:** Daftar tagihan terbuka tinggal di dalam area gulir item keranjang, sehingga ikut tergeser saat keranjang panjang dan bercampur dengan barang yang sedang diinput. Kini jadi tombol ber-badge di `CashierTopbar` dengan panelnya sendiri, dan tersedia di **semua** halaman kasir.
- **Alasan:** Dua hal yang tidak berhubungan berbagi satu ruang gulir, dan akibat praktisnya paling terasa saat paling mahal — tagihan yang menunggu dibayar hilang dari pandangan justru ketika kasir sibuk. Selain itu daftarnya dulu hanya ada di POS: kasir yang sedang membuka Riwayat atau Kas tidak tahu ada tagihan menggantung.
- **File Terdampak:**
  - `app/Http/Middleware/HandleInertiaRequests.php` — kunci `cashier` (openBills + paymentMethods) + helper `openBillsFor()`
  - `app/Http/Controllers/Cashier/POSController.php` — berhenti mengirim `openBills`
  - `resources/js/Components/CashierTopbar.vue` — tombol, badge, panel, dan **seluruh alur pelunasan**
  - `resources/js/Pages/Cashier/POS.vue` — panel lama, handler, dan `PaymentModal` kedua dilepas
  - `tests/Feature/Cashier/POSTest.php` — 3 test baru
- **Keputusan yang perlu diingat:**
  - **Alur pelunasan ikut pindah, bukan hanya daftarnya.** Kalau tombol "Bayar" tetap milik POS, panel di halaman Riwayat cuma jadi pajangan yang memaksa kasir berpindah halaman. Karena itu `PaymentModal` + modal sukses + struk sekarang milik topbar. Konsekuensinya `paymentMethods` ikut dibagikan — satu query kecil, dan hanya di rute kasir.
  - **Data bersamanya digerbang `routeIs('cashier.*')`.** Topbar hanya ada di sana; menghitung tagihan terbuka pada tiap request halaman owner/platform adalah query yang tak pernah dibaca. Ada test yang memastikan `cashier` bernilai `null` di halaman owner.
  - **Cakupannya tetap `user_id`, tidak diam-diam diperluas ke tenant.** Ini memindahkan tempat, bukan mengubah siapa yang melihat tagihan siapa — dan ada test yang menjaga tagihan kasir lain tidak bocor. Kalau nanti tagihan perlu dilunasi dari kasir mana pun, itu keputusan tersendiri.
  - **Penolakan saat offline ikut pindah bersama tombolnya.** Melunasi tagihan terbuka mengubah baris yang sudah ada di server dan kasir lain bisa sedang melunasi yang sama; menolak lebih baik daripada berisiko melunasi dua kali. Alasan yang sama sudah tertulis di `saveAsOpenBill()` dan tetap berlaku.
  - **`PaymentModal` kini terpasang dua kali di halaman POS** (checkout dan pelunasan). Keduanya `v-if`, jadi hanya satu yang hidup — tapi ingat bahwa perbaikan pada modal itu otomatis menyentuh kedua jalur (lihat `[BL-021]`).

---

### [HOTFIX] Turunan Periode Bulanan Meluber di Bulan Pendek (BL-029)
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — menutup `[BL-029]`
- **Dampak:** Job | Command | Factory | Test
- **Breaking Change:** Tidak. Memperbaiki periode yang dihitung, bukan cara menghitungnya.
- **Deskripsi:** Ditemukan sebagai satu test subsidi yang gagal, ternyata **tiga bug produksi**. `Carbon::subMonth()` bukan berarti "bulan lalu" melainkan "tanggal yang sama sebulan lalu, meluber bila tanggal itu tidak ada". Pada 31 Juli, `now()->subMonth()` menghasilkan **1 Juli** — bulan berjalan.
- **Alasan:** Ketiganya menurunkan periode `Y-m` dari `now()` dengan aritmetika bulan, dan ketiganya salah bulan di tanggal 29–31. Yang paling berbahaya menghapus data.
- **File Terdampak:**
  - `app/Jobs/ComputeTenantMonthlyRevenue.php:41` — job menghitung **bulan berjalan** alih-alih bulan yang sudah tutup, persis yang dilarang komentarnya sendiri
  - `app/Console/Commands/PruneTenantMetrics.php:30` — batas retensi bergeser satu bulan penuh, pada perintah yang **menghapus** ringkasan omzet
  - `app/Console/Commands/ComputeTenantRevenue.php:36` — laporan hasil menyebut periode yang berbeda dari yang baru saja dihitung job
  - `database/factories/TenantMonthlyMetricFactory.php:23`, `tests/Feature/Subscription/*`, `tests/Feature/Platform/PlatformRevenueTest.php` — sumber test yang sama
- **Perbaikan:** `startOfMonth()` **dulu**, baru `subMonths()`. Urutan ini menyatakan maksudnya — yang dibandingkan memang bulannya, bukan tanggalnya — dan tidak bisa meluber karena tanggal 1 selalu ada.
- **Keputusan yang perlu diingat:**
  - **Gejalanya test merah, penyakitnya di produksi.** Test-nya membuat dua baris untuk periode yang sama lalu menabrak unique constraint. Yang jauh lebih mahal adalah job dan perintah prune, yang tidak punya penjaga apa pun dan diam saja saat salah bulan.
  - **Test regresinya menyetel waktu ke tanggal 31**, bukan mengandalkan kapan suite kebetulan dijalankan. Sudah diverifikasi gagal pada kode lama sebelum diterima.
  - **`[BL-030]` sengaja TIDAK diborong ke sini.** Jatuh tempo langganan (`addMonth()` di `InvoiceController`) punya luberan yang sama, tapi memperbaikinya menuntut keputusan aturan bisnis — langganan mulai 31 Januari jatuh tempo 28 Februari atau 1 Maret? Menggeser tanggal tagih pelanggan sambil membetulkan bug lain bukan keputusan yang boleh diambil diam-diam.

---

### [HOTFIX] Riwayat Kasir Dibatasi ke Sesi Kas Berjalan (BL-027)
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — menutup `[BL-027]`
- **Dampak:** Controller | Frontend | Test
- **Breaking Change:** Ya untuk kasir: `/cashier/transactions` tidak lagi menampilkan seluruh riwayat akun, dan parameter `?date=` diabaikan untuk peran kasir. Owner tidak berubah selain bawaannya kini hari ini.
- **Deskripsi:** Filter tanggal bersifat opsional dan tidak punya nilai bawaan, sehingga halaman riwayat membuka semua transaksi kasir sejak akun dibuat. Kini dibatasi ke sesi laci yang sedang terbuka, dengan `hari ini` sebagai cadangan bila tidak ada sesi.
- **Alasan:** Kasir yang ingin mengoreksi penjualan barusan harus menyaring ratusan baris lama, dan mesin kasir yang dipakai bergantian memperlihatkan riwayat shift orang sebelumnya lebih banyak dari yang perlu. Penyaring `user_id` sudah ada sejak awal — yang hilang hanya batas waktunya.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/POSController.php` — `history()` + helper `historyScopeLabel()`
  - `resources/js/Pages/Cashier/TransactionHistory.vue` — chip cakupan, pemilih tanggal khusus owner, empty state menyebut batasnya
  - `tests/Feature/Cashier/POSTest.php` — 4 test baru
- **Keputusan yang perlu diingat:**
  - **Batasnya ditegakkan di server, bukan disembunyikan di UI.** Menyembunyikan pemilih tanggal saja akan dilewati siapa pun yang mengetik `?date=` di URL, jadi `date` hanya dibaca untuk peran owner. Ada test khusus yang menyelundupkannya lewat query string.
  - **Batas waktu memakai tanggal EFEKTIF**, sejalan dengan `[BL-028]`: penjualan offline muncul di shift yang benar-benar melakukannya, bukan di shift yang kebetulan berjalan saat ia tersinkron.
  - **`canEditTransaction()` sengaja TIDAK ikut diubah.** Ia masih membandingkan `created_at` karena merupakan cermin `TransactionEditService::assertEditable()`; mengubah salah satunya saja akan memunculkan tombol edit yang ditolak service. Ketidakcocokannya condong ke arah aman (baris bisa tampil tanpa bisa diedit, tidak sebaliknya).
  - **Cakupan yang berlaku selalu disebutkan** lewat chip di panel filter dan di empty state. Daftar yang diam-diam terpotong lebih membingungkan daripada daftar panjang.

---

### [HOTFIX] Split Bill: Nominal Non-Tunai Jadi Turunan, & Cukup-Bayar Diuji Terhadap Harga DB (BL-021, BL-022)
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — menutup `[BL-021]` dan `[BL-022]`
- **Dampak:** Service | Frontend | Test
- **Breaking Change:** Tidak untuk pembayaran satu baris (perilakunya identik). Ya dalam satu hal yang disengaja: checkout kini **menolak** pembayaran yang kurang terhadap harga DB, yang sebelumnya diterima diam-diam.
- **Deskripsi:** Modal pembayaran mengisi nominal non-tunai **sekali** saat metode dipilih lalu membekukannya, sehingga mengoreksi baris tunai setelahnya meninggalkan angka QRIS yang basi — dan angka basi itulah yang tersimpan ke `transaction_payments`. Terpisah dari itu, `checkout()` memvalidasi cukup-bayar memakai harga kiriman klien, lalu menghitung total dari harga DB, dan tetap menyelesaikan transaksi meski hasilnya kurang bayar.
- **Alasan:** Keduanya menulis angka uang yang salah ke database, dan keduanya menjalar: nominal per metode yang salah merusak rekap per metode dan `expected_amount` laci; kurang bayar yang lolos merusak omzet.
- **File Terdampak:**
  - `resources/js/Components/PaymentModal.vue` — ditulis ulang bagian nominalnya
  - `app/Services/TransactionService.php` — penjagaan cukup-bayar di `checkout()`
  - `tests/Feature/Cashier/POSTest.php` — 5 test baru (2 untuk BL-022, 3 untuk BL-021)
- **Keputusan yang perlu diingat:**
  - **Satu baris penyeimbang, dan ia dipilih, bukan ditetapkan.** Nominal non-tunai kini turunan `total − Σ baris lain`, dievaluasi ulang tiap perubahan. Yang menyeimbangkan adalah baris non-tunai **terakhir yang belum disentuh kasir**; begitu kasir mengetik di sana, baris itu jadi manual dan penyeimbangnya berpindah. Dua baris yang sama-sama "otomatis" tidak punya jawaban tunggal, jadi keadaan itu tidak diizinkan ada.
  - **Kembalian hanya dari porsi tunai:** `max(0, Σ tunai − (total − Σ non-tunai))`. Ditambah penjagaan baru: non-tunai melebihi total ditolak, karena itu menjanjikan kembalian yang tidak bisa diberikan.
  - **Tombol cepat kini ada di SETIAP baris tunai**, dan "Uang Pas" berubah jadi "Sisa" saat split. Sebelumnya tombol itu tetap terlihat pada baris pertama tapi diam-diam berhenti bekerja begitu baris kedua ditambahkan — inilah yang membuat kasir harus mengetik manual.
  - **Penjagaan kurang bayar sengaja hanya "tidak boleh kurang", BUKAN "harga klien harus sama persis dengan DB".** Syarat kedua akan menabrak harga diskon yang sah begitu `[BL-018]` dikerjakan.
  - **`commitOffline()` tidak lewat penjagaan itu.** Penjualan offline yang sudah terjadi secara fisik tetap ditandai `needs_review`, tidak pernah ditolak — asimetri yang memang disengaja dan sudah tertulis di docblock-nya.
- **Batas yang diketahui:** proyek ini belum punya test runner JavaScript, jadi logika `PaymentModal` **tidak tercakup test otomatis**. Yang diuji adalah apa yang benar-benar tersimpan dari split (nominal per metode, kurang bayar ditolak, pelunasan open bill). Menambah Vitest adalah perubahan dependency dan menunggu persetujuan pemilik.

---

### [HOTFIX] Rekonsiliasi Kas Menghitung Penjualan Tunai, Per-Laci, & Per-Tanggal Efektif (BL-028 Tahap A)
- **Tanggal:** 2026-07-31
- **Fase Terkait:** Di Luar Fase — menutup Tahap A `[BL-028]`; Tahap B (`cash_drawer_id`) sengaja ditunda
- **Dampak:** Service | Controller | Frontend | Test
- **Breaking Change:** Tidak. Tanpa migrasi, tanpa perubahan skema, tanpa backfill.
- **Deskripsi:** Layar tutup kas dulu menghitung selisih sebagai `uang fisik − modal awal`, sehingga kasir yang menjual Rp 500.000 tunai melihat "Selisih +500.000" seolah lacinya kelebihan uang. Rumus yang benar sebenarnya sudah ada di `close()` tapi tidak pernah sampai ke layar, dan punya dua cacat sendiri. Ketiganya ditutup sekaligus lewat satu service bersama.
- **Alasan:** Angka yang dipakai kasir mempertanggungjawabkan uang fisik salah di setiap penutupan sesi (207 sesi sejauh ini). Dua cacat lainnya masih laten dan justru itu alasan memperbaikinya sekarang — memperbaiki bug yang belum merusak data jauh lebih murah daripada memperbaikinya sambil membersihkan akibatnya.
- **File Terdampak:**
  - `app/Services/CashDrawerReconciliation.php` — **baru**; satu-satunya sumber angka rekonsiliasi
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `index()` mengirim `reconciliation`; `close()` & `summary()` memakai service, ~35 baris query duplikat dihapus
  - `resources/js/Pages/Cashier/CashDrawer.vue` — rincian modal + tunai masuk − kembalian = seharusnya di laci; non-tunai ditandai "tidak masuk laci"; pratinjau memuat ulang angkanya sebelum ditampilkan
  - `tests/Feature/Cashier/CashDrawerReconciliationTest.php` — **baru**, 8 test
- **Tiga cacat yang ditutup:**
  1. **Penjualan tunai tidak terlihat di layar.** Pratinjau kini menampilkan rinciannya, bukan hanya modal awal vs uang fisik.
  2. **Scope per-laci.** Query dulu menyaring `tenant_id` saja, sehingga dua kasir yang shift bersamaan sama-sama menghitung seluruh uang tunai toko sebagai miliknya. Kini disaring `user_id` laci. Data per 2026-07-31 membuktikan cacat ini belum pernah aktif (1 kasir per tenant, 0 sesi tumpang-tindih) — diperbaiki selagi masih laten.
  3. **Jendela waktu memakai `created_at`.** Penjualan offline yang tersinkron belakangan dihitung ke laci hari sinkronisasi, bukan hari uangnya masuk. Kini memakai `Transaction::scopeWhereEffectiveBetween()` — scope yang sudah ada dan sudah dipakai papan antrian dengan alasan yang persis sama.
- **Keputusan yang perlu diingat:**
  - **`cash_drawer_id` BUKAN prasyarat.** Catatan awal `[BL-028]` menyebutnya begitu; itu keliru. `transactions.user_id` + jendela sesi sudah memisahkan laci dengan benar, termasuk untuk kasir kedua. Kolom eksplisitnya (Tahap B) ditunda sampai benar-benar dibutuhkan — backfill di atas data yang belum pernah salah adalah risiko tanpa imbalan.
  - **Pratinjau memuat ulang, bukan menghitung sendiri.** `previewClose()` melakukan partial reload `reconciliation` sebelum menampilkan ringkasan. Pratinjau yang basi akan menjanjikan selisih berbeda dari yang akhirnya tercatat `close()` — cara tercepat membuat kasir berhenti mempercayai kedua angkanya.
  - **Batas yang disadari & sengaja dibiarkan:** penjualan offline yang terjadi di dalam sesi tapi baru tersinkron setelah lacinya ditutup tidak terhitung di mana pun, karena `expected_amount` sesi itu sudah dibekukan. Memakai `created_at` tidak menyelesaikannya, hanya memindahkan kesalahannya. Jawabannya menunggu Tahap B.

---

### [ADDITION] Papan Antrian Dapur (BL-019)
- **Tanggal:** 2026-07-29
- **Fase Terkait:** PHASE QUEUE — menutup `[BL-019]`; berdiri di atas fase capability flags di bawah
- **Dampak:** Migration | Model | Service | Controller | Resource | Route | Frontend | Test
- **Breaking Change:** Tidak untuk mode cafe (flag `kitchen_queue_enabled` bawaannya mati → nol perubahan). Ya untuk konsumen `PATCH /api/v1/orders/{tx}/fulfillment`, yang kini menerima `expected_from`.
- **Deskripsi:** Kolom fulfillment yang sudah tertanam sejak Self-Order akhirnya punya **wajah**: papan operator di `/cashier/queue` dengan nomor antrian, urutan yang bisa diubah, penanda BELUM BAYAR, dan timer tunggu. Ditujukan untuk warung ber-dapur dan kaki lima, di mana satu orang merangkap masak dan kasir.
- **Alasan:** Skema aplikasi ini berorientasi kasir cafe — bayar, cetak, selesai. Operator tunggal butuh tahu pesanan mana masuk duluan dan mana yang perlu didahulukan, dan sampai sekarang satu-satunya jalur memajukan status ada di API Sanctum tanpa UI sama sekali.
- **File Terdampak:**
  - `database/migrations/..._add_queue_columns_to_transactions_table.php` — `queue_number`, `sort_index`, `preparing_at`, `ready_at`
  - `database/migrations/..._add_queue_board_index_to_transactions_table.php` — indeks `transactions_queue_board_index`
  - `database/migrations/..._backfill_stale_fulfillment_status.php` — migrasi DATA, `down()` no-op berkomentar
  - `app/Services/Queue/QueueNumberAllocator.php` + `DailySequenceAllocator.php` + binding di `AppServiceProvider`
  - `app/Services/FulfillmentService.php` — `advance($expectedFrom)`, `moveToTop`, `moveUp`, `moveDown`
  - `app/Services/TransactionService.php` — `$queueMode` di `checkout()`, nomor di `confirmSelfOrderPayment()`, `void()` menolkan fulfillment
  - `app/Http/Controllers/Cashier/QueueController.php` + `app/Http/Resources/QueueCardResource.php`
  - `app/Http/Controllers/Api/V1/ApiOrderController.php` — memanggil service, menerima `expected_from`
  - `app/Http/Middleware/EnsureSubscriptionActive.php` — `cashier.queue.*` masuk `ALWAYS_ALLOWED`
  - `resources/js/Pages/Cashier/Queue.vue`, `Components/ReceiptModal.vue`, `Components/CashierTopbar.vue`, `Layouts/OwnerLayout.vue`, `services/escpos.js`
  - `app/Http/Controllers/Api/XenditWebhookController.php` — respons memuat `queue_number`
  - `tests/Feature/KitchenQueueTest.php` (22) + `tests/Unit/FulfillmentServiceTest.php` (13)
- **Keputusan arsitektur yang perlu diingat:**
  - **Empat penjaga anti-kunci dipasang meski fase offline belum dibuka.** `queue_number` bukan identitas (hanya label; identitas tetap `id`/`code`), pengalokasian nomor di balik seam `QueueNumberAllocator`, `sort_index` diturunkan dari `effectiveDate()` bukan `now()`, dan batas hari papan juga memakai `effectiveDate()`. Semuanya nyaris tak berbiaya hari ini karena online dan offline identik untuk transaksi biasa — dan mahal sekali kalau baru disadari nanti.
  - **`expected_from` wajib di permukaan web.** Papan di-poll tiap 7 detik dan bisa dibuka dua orang, jadi kartu basi adalah keadaan normal. Tanpa pemeriksaan ini satu tap pada kartu basi melompati satu status diam-diam. Ini juga yang membuat kenaikan ke Reverb aman dilakukan belakangan.
  - **Perubahan perilaku yang disengaja: `checkout()` kini bersyarat `$queueMode`, bukan `$isOpenBill`.** Versi lama memberi `waiting` pada setiap open bill bahkan tanpa papan yang mengerjakannya — sumber timbunan yang dibersihkan migrasi backfill. Tak terlihat oleh tenant mode cafe karena tak ada permukaan yang merendernya, tapi tetap dikunci dengan test tersendiri.
  - **`payOpenBill()` tidak disentuh sama sekali.** Saat mode antrian hidup, pelunasan memang tidak boleh mengubah status masak — memasak dan membayar dua hal berbeda. Diuji.
  - **Dahulukan, bukan sembilan tap.** Aksi prioritas utamanya satu-tap ke puncak dengan satu konfirmasi; ▲/▼ tinggal sebagai penghalus tanpa modal, dan tidak dirender sama sekali pada kartu `ready`.
  - **`id` sebagai pemecah seri.** `sort_index` lahir dari cap waktu milidetik dan dua self-order bisa jatuh di milidetik yang sama; tanpa pemecah seri, perbandingan strict melewati kartu kembar dan tombolnya terasa rusak.
  - **`cashier.queue.*` di `ALWAYS_ALLOWED`.** Menyelesaikan pesanan yang uangnya sudah diterima bukan layanan baru. Halaman papannya sendiri (`cashier.queue`, tanpa akhiran) tidak tercakup pola itu — dan tidak perlu, karena GET sudah lolos sebagai method aman.
- **Batas yang diterima sadar (Keputusan pemilik 2026-07-28):** mode antrian **tidak aktif saat perangkat offline**, dan penjualan hasil sinkronisasi **tidak menyusul masuk papan**. Dinyatakan di UI lewat pita offline dan di deskripsi toggle Settings, serta dikunci test — bukan dibiarkan tersirat. Di luar lingkup juga: pengelompokan pekerjaan (batching), fulfillment parsial, websocket, dan pembatalan langsung dari papan.
- **Catatan Migrasi:** `php artisan migrate` lalu `npm run build`. Migrasi backfill **tidak bisa dibatalkan** — ia menolkan `fulfillment_status` pada transaksi lama yang sudah tuntas. Papan menyala per tenant lewat toggle **Mode & Fitur Outlet** di Pengaturan.

---

### [ADDITION] Fondasi Capability Flags & Gerbang Tersinkron
- **Tanggal:** 2026-07-29
- **Fase Terkait:** PHASE FEATURE-FLAGS — prasyarat `PHASE QUEUE`
- **Dampak:** Migration | Model | Middleware | Route | Job | Controller | Frontend | Test
- **Breaking Change:** Ya untuk konsumen API self-order — `POST /api/v1/orders`, `POST /api/v1/upsell/suggestions`, dan `PATCH /api/v1/orders/{tx}/fulfillment` kini bisa menjawab `403` berkode `feature_disabled`.
- **Deskripsi:** Tiga kapabilitas per tenant (`kitchen_queue_enabled`, `self_order_enabled`, `ai_enabled`) dengan satu sumber kebenaran `Tenant::hasFeature()`, dua middleware gerbang (web + API/JSON), dan gerbang terpasang di **setiap** pintu masuk yang ada: web, API self-order, job AI, dan MCP.
- **Alasan:** Satu fitur bisa dicapai lewat pintu yang mekanisme autentikasinya berbeda-beda, dan gerbang di satu pintu tidak menutup pintu lain. Yang paling mendesak: **MCP sudah hidup penuh** — tiga tool terdaftar, token bisa diterbitkan dari Settings — dan sampai sekarang satu-satunya cara menghentikan aksesnya adalah mencabut tokennya. Tidak ada saklar.
- **File Terdampak:**
  - `database/migrations/..._add_capability_flags_to_tenants_table.php` — tiga boolean + backfill `self_order_enabled = true`
  - `app/Models/Tenant.php` — `$fillable`, `casts()`, `$attributes`, `hasFeature()`
  - `app/Http/Middleware/EnsureTenantFeature.php` + `EnsureTenantFeatureApi.php` + alias di `bootstrap/app.php`
  - `routes/api.php`, `routes/web.php`, `routes/ai.php` — gerbang per permukaan
  - `app/Jobs/RunAiAnalysisJob.php` — guard sebelum kuota & provider
  - `app/Http/Middleware/HandleInertiaRequests.php` — flag dibagikan ke klien
  - `app/Http/Controllers/Owner/SettingsController.php` + `resources/js/Pages/Owner/Settings/Index.vue`
  - `resources/js/Layouts/OwnerLayout.vue`, `Components/CashierTopbar.vue` — nav bersyarat
  - `tests/Feature/FeatureGatingTest.php` — 14 test
- **Keputusan arsitektur yang perlu diingat:**
  - **Gagal tertutup.** Nama fitur yang tidak dikenal menjawab `false`, bukan `true`. Salah ketik harus menutup pintu, bukan membukanya diam-diam. Diuji.
  - **Backfill `self_order_enabled = true` adalah penjaga status quo, bukan kerapian.** Self-order bukan fitur baru; membiarkan baris lama `false` berarti pada hari rilis setiap integrasi n8n yang hidup menerima 403. Risikonya tidak setara — keliru `false` mematikan yang sedang bekerja, keliru `true` tidak mengubah apa pun bagi yang tak memakainya. `ai_enabled` default `true` dengan alasan sama.
  - **`$attributes` ikut diisi.** `hasFeature()` atas instance yang belum dibaca ulang dari basis data akan menerima `null` dan menjawab `false` — termasuk untuk `ai_enabled` yang seharusnya `true`. Dikunci test.
  - **Dua middleware, bukan satu.** Web butuh abort; API, MCP, dan mobile butuh JSON. `code` yang stabil (`feature_disabled`) lebih penting daripada `message`: n8n mencocokkan kode, bukan kalimat.
  - **`feature` mendahului `permission`.** Keduanya ortogonal — `permission` menjawab "pengguna ini boleh?", `feature` menjawab "outlet ini punya kapabilitasnya?" — dan urutannya menentukan sebab penolakan yang didengar owner. "Tidak punya izin" akan mengirimnya memeriksa halaman Role, tempat yang salah sama sekali.
  - **Job dijaga terpisah dari middleware.** `RunAiAnalysisJob` tak lewat HTTP dan bisa sudah mengantre saat flag dimatikan. Flag diperiksa **sebelum** kuota — terbalik berarti tenant yang fiturnya mati tetap menghabiskan jatah hariannya. Diuji dua arah.
  - **`/api/v1/products` sengaja di luar gerbang.** Katalog bukan pemesanan, dan endpoint yang sama dipakai jalur mobile.
  - **Badge "Self Order" historis tetap tampil** di dashboard meski flag mati. Itu data masa lalu; menyembunyikan riwayat karena fitur dimatikan hari ini adalah menghapus sejarah, bukan menggerbang fitur.
  - **Tidak ada `business_type` di Settings.** Kolom itu milik penetapan harga (`[BL-015]`) dan dibekukan ke `invoices.pricing_context`; mengeditnya dari sana akan diam-diam mengubah dasar tagihan langganan.
- **Catatan Migrasi:** `php artisan migrate` lalu `npm run build`. Tenant lama otomatis mempertahankan self-order dan AI; `kitchen_queue_enabled` mati untuk semua dan dinyalakan per outlet dari Pengaturan.

---

### [HOTFIX] Gerbang Langganan di Jalur Self-Order (BL-020)
- **Tanggal:** 2026-07-29
- **Fase Terkait:** Di Luar Fase — menutup `[BL-020]`
- **Dampak:** Route | Config (alias middleware) | Test
- **Breaking Change:** Ya untuk konsumen API — `POST /api/v1/orders`, `GET /api/v1/products`, dan `POST /api/v1/upsell/suggestions` kini bisa menjawab **403** pada tenant yang langganannya tidak aktif. Integrasi n8n perlu menangani status ini.
- **Deskripsi:** Grup rute self-order hanya memakai `auth:sanctum`, sehingga `EnsureSubscriptionActive` — yang ikut lewat grup `tenant`/`tenant.api` — **tidak pernah berjalan di sana**. Tenant `suspended` dan `grace` masih bisa menerima pesanan baru lewat n8n/Telegram padahal jalur webnya sudah tertutup. Gerbangnya kini dipasang lewat alias `subscription` yang baru.
- **Alasan:** Dugaan di `[BL-020]` dibaca dari susunan middleware, bukan dari percobaan. Test verifikasi ditulis lebih dulu dan **membuktikannya nyata**: `POST /api/v1/orders` pada tenant `suspended` menjawab 422 (validasi controller) alih-alih 403, dan `GET /api/v1/products` menjawab 200. `grace` menurut definisinya hanya-baca, jadi menerima pesanan baru di sana bertentangan dengan janji sistem langganannya sendiri.
- **File Terdampak:**
  - `bootstrap/app.php` — alias baru `subscription` => `EnsureSubscriptionActive`
  - `routes/api.php` — grup self-order jadi `['auth:sanctum', 'subscription']`; rute fulfillment dipisah ke grupnya sendiri
  - `tests/Feature/Subscription/SelfOrderSubscriptionGateTest.php` — 8 test
- **Keputusan yang perlu diingat:**
  - **Alias, bukan grup `tenant.api`.** Menempelkan grup itu akan menyeret `EnsureEmailVerified`, dan token n8n tidak punya kotak masuk untuk membuktikan apa pun — seluruh jalur self-order akan mati pada tenant yang surelnya belum terverifikasi. Pertanyaannya memang "gerbang langganan mana yang berlaku untuk token mesin", bukan "tambahkan `tenant.api`?". Ada test yang menjaga batas ini.
  - **`PATCH /orders/{id}/fulfillment` SENGAJA di luar gerbang.** Memajukan status pesanan menyelesaikan kewajiban yang uangnya sudah diterima — bukan layanan baru. Menutupnya akan menghentikan dapur di tengah antrean pada hari langganan lewat jatuh tempo, bertentangan dengan prinsip yang ditulis middleware-nya sendiri: *"Menyandera data pelanggan bukan alat penagihan yang sah"*.
  - **`ALWAYS_ALLOWED` tidak disentuh.** `[BL-020]` mencatat bahwa entri ini dan `[BL-019]` mendorong gerbang yang sama ke dua arah berbeda. Memisahkan rute fulfillment di berkas rute — bukan menambah pengecualian di middleware — membuat kebijakan antrean seutuhnya tetap milik `[BL-019]`, dan dua test menjaga arah yang berlawanan itu masing-masing.
  - **Permukaan Sanctum lain sudah diperiksa** lewat `php artisan route:list --path=api`: seluruh grup mobile sudah memakai `tenant.api`, jadi self-order satu-satunya yang bocor.
- **Catatan Migrasi:** Tidak ada migration. Cukup `php artisan route:clear` bila rute pernah di-cache.

---

### [ADDITION] Saran Jual dari Sinyal Stok (BL-017)
- **Tanggal:** 2026-07-27
- **Fase Terkait:** Di Luar Fase — PHASE UPSELL Tahap A–E, menutup `[BL-017]`
- **Dampak:** Migration | Model | Service | Controller | Route | Config | Frontend | Test
- **Breaking Change:** Tidak — `BadgeHelperService`, dashboard owner, dan harga produk tidak disentuh sama sekali
- **Deskripsi:** Sinyal stok yang selama ini hanya jadi peringatan di dashboard owner kini juga muncul sebagai **saran jual di layar kasir dan di jalur self-order**. Tiga jenis saran: `attach` (add-on yang paling sering menyertai item, dari riwayat ko-okurensi modifier), `pressed_stock` (barang mendekati kedaluwarsa / tak terjual sebulan), dan `upsize` (varian sama-produk dengan harga setingkat di atasnya). Nasib tiap saran — diambil atau diabaikan — dicatat di tabel baru `upsell_events` dan dirangkum di laporan `/owner/reports/upsell`.
- **Alasan:** Saran yang diterima setelah sesi pitching. Peringatan yang ada menghadap ke arah yang salah untuk berjualan: badge muncul di dashboard owner, dilihat entah kapan, dan tidak menawarkan tindakan selain "restock" — padahal momen upsell terjadi di layar kasir saat pelanggan masih berdiri di depan meja.
- **File Terdampak:**
  - `database/migrations/2026_07_27_100000_create_upsell_events_table.php` — tabel kejadian; `transaction_id` sengaja `nullOnDelete` agar menghapus transaksi tidak diam-diam memperbaiki angka konversi
  - `app/Models/UpsellEvent.php`, `database/factories/UpsellEventFactory.php`
  - `config/upsell.php` — saklar, batas tampilan, ambang tiap jenis saran
  - `app/Services/Upsell/` — `SellableVariantQuery` (penjaga kandidat), `Suggestion` (DTO), `UpsellIndexBuilder`, `UpsellEventRecorder`, dan tiga strategi di `Strategies/`
  - `app/Http/Controllers/Cashier/POSController.php` — prop `upsell`
  - `app/Services/TransactionService.php` — pencatatan event di `checkout()`, `createSelfOrder()`, dan `commitOffline()`
  - `app/Http/Requests/StoreTransactionRequest.php` & `SyncOfflineTransactionsRequest.php` — validasi `upsell_events`
  - `app/Http/Controllers/Api/V1/ApiUpsellController.php` + `ApiOrderController.php` + `routes/api.php`
  - `app/Http/Controllers/Owner/ReportController.php` + `resources/js/Pages/Owner/Reports/Upsell.vue` + `routes/web.php` + `resources/js/Layouts/OwnerLayout.vue`
  - `resources/js/composables/useUpsell.js`, `resources/js/Components/UpsellStrip.vue`, `resources/js/Pages/Cashier/POS.vue`
  - `resources/js/composables/useCatalogCache.js` & `useOfflineQueue.js` — indeks ikut snapshot, event ikut outbox
  - `tests/Feature/Upsell/UpsellSuggestionTest.php` & `UpsellEventTest.php` — 26 test
- **Keputusan arsitektur yang perlu diingat:**
  - **Kandidat dihitung server, penyaringan dilakukan client.** Indeks saran ikut props POS, bukan endpoint per perubahan keranjang — dengan begitu ia ikut ter-snapshot `useCatalogCache` dan **tetap hidup saat perangkat offline**. Endpoint per-keranjang akan mati justru di warung yang sinyalnya paling buruk.
  - **Tidak ada perubahan harga apa pun di sini.** `pressed_stock` menawarkan barang tertekan pada harga katalog. Bundling **berdiskon** ditahan sampai `[BL-018]` menyediakan tempat sah untuk harga di bawah katalog.
  - **Varian `expired` tidak pernah jadi kandidat**, ditegakkan di `SellableVariantQuery` dan diuji — batas keamanan pangan, bukan pilihan bisnis.
  - **Tidak ada kolom urutan varian baru.** `price` sudah mendefinisikan urutan "naik ukuran"; kolom manual akan membuat fiturnya mati diam-diam pada tenant yang tidak mengisinya.
  - **Statistik upsell tidak pernah menggagalkan penjualan.** `UpsellEventRecorder` membuang payload cacat (dengan log) dan menolkan FK milik tenant lain, alih-alih melempar exception.
- **Catatan Migrasi:** `php artisan migrate` lalu `npm run build`. Fitur bisa dimatikan seluruhnya lewat `UPSELL_ENABLED=false`, atau per jenis lewat `config/upsell.php`.

---

### [SCHEMA] Dimensi Harga Bebas — Aturan Berkriteria (BL-015)
- **Tanggal:** 2026-07-27
- **Fase Terkait:** Di Luar Fase — lanjutan PHASE SAAS Tahap D, menutup `[BL-015]`
- **Dampak:** Migration | Model | Service | Controller | Route | Config | Frontend | Test
- **Breaking Change:** Ya — `pricing_rules.min_revenue` & `max_revenue` dibuang; `PricingService::bracketFor()` tidak lagi mengembalikan `min`/`max`
- **Deskripsi:** Aturan harga berpindah dari **kolom tetap** ke **baris berkriteria**. Satu aturan kini punya banyak syarat (`dimensi`, `operator`, `nilai`) plus `priority`; kategori harga baru cukup ditulis sebagai baris baru dari `/platform/pricing-rules`, tanpa migration dan tanpa deploy. Empat dimensi tersedia di jalur pertama: omzet bulanan, jumlah transaksi/bulan, pengguna aktif, dan tipe usaha.
- **Alasan:** Permintaan pemilik SaaS — harga harus bisa dibedakan oleh "berbagai kategori", bukan omzet dan seat saja. Sebelum ini, kategori ketiga menuntut migration + ubah service + ubah form + deploy.
- **File Terdampak:**
  - `database/migrations/2026_07_26_180115_create_pricing_rule_conditions_table.php` — tabel syarat, kolom `priority`, backfill bracket lama, pembuangan dua kolom omzet
  - `database/migrations/2026_07_26_180117_add_business_type_to_tenants_table.php`
  - `database/migrations/2026_07_26_180118_add_pricing_trail_to_invoices_table.php` — `pricing_rule_id` + `pricing_context`
  - `config/pricing-dimensions.php` — katalog dimensi berikut penanda consent-nya
  - `app/Services/Pricing/` — `DimensionResolver` + empat resolver + `DimensionRegistry`
  - `app/Services/PricingService.php` — `matchContext()`, `resolveFor()`, `publishRule()`
  - `app/Models/PricingRule.php`, `app/Models/PricingRuleCondition.php`
  - `app/Http/Controllers/Platform/{PricingRuleController,InvoiceController,TenantController}.php`
  - `app/Http/Controllers/Auth/AuthController.php`, `resources/js/Pages/Auth/Register.vue`
  - `resources/js/Pages/Platform/{PricingRules,Invoices,Tenants}/Index.vue`
  - `tests/Feature/Platform/PricingDimensionTest.php` — 22 test; suite penuh **483 hijau**
- **Keputusan:**
  1. **Katalog dimensi tetap tinggal di kode, dan itu bukan kompromi yang bisa dihindari.** Tiap dimensi butuh sumber angkanya; nilai yang tidak bisa dihitung aplikasi tidak akan pernah bisa jadi dasar harga sebebas apa pun panelnya. Yang bebas sepenuhnya adalah **ambang dan tarifnya** — daftar dimensi tumbuh sekali per jenis data, bukan sekali per skema harga.
  2. **Gagal menutup, bukan gagal membuka.** Syarat yang dimensinya tak bisa dihitung — belum disetujui, belum pernah dihitung, atau dicabut dari katalog — membuat aturannya **tidak cocok**. Kebalikannya akan membuat aturan bersyarat justru berlaku paling luas bagi tenant yang datanya paling sedikit diketahui.
  3. **Penjaga consent dipindahkan ke `DimensionRegistry`, satu pintu untuk semua pemanggil.** Syaratnya persetujuan yang **masih aktif**, sengaja bukan `hasAgreedToCurrent()`: memakai pemeriksaan versi-terkini berarti satu kali menaikkan versi teks consent akan memadamkan dimensi ini bagi seluruh tenant sekaligus — harga mereka berubah diam-diam pada hari revisi teks terbit. Pencabutan tetap memadamkannya seketika.
  4. **Hasilnya disambungkan ke penerbitan tagihan, tapi hanya sebagai usulan.** Kolom nominal terisi sendiri dan tetap bisa diketik ulang. `pricing_rule_id` dicatat **hanya bila nominal yang terbit sama dengan tarif aturannya** — menautkan aturan pada angka yang diketik ulang akan melahirkan jejak yang berbohong.
  5. **`pricing_context` dibekukan di tiap tagihan.** Dengan banyak syarat per aturan, "aturan mana yang berlaku waktu itu" tak lagi bisa direka ulang dari satu angka omzet — dan menghitungnya ulang hanya mengembalikan nilai hari ini. Kolomnya `$hidden` karena memuat data bisnis.
  6. **Tipe usaha ditanyakan saat pendaftaran, opsional.** Menanyakannya belakangan berarti seluruh tenant yang mendaftar lebih dulu tak pernah punya nilainya; mewajibkannya akan menjegal pendaftaran demi pertanyaan yang jawabannya bisa "belum jelas".
  7. **`Platform\TenantController` mendapat satu tulisan pertamanya** — koreksi tipe usaha. Bukan kemudahan melainkan kebutuhan: tanpa jalan mengisinya, dimensi itu tak berguna selamanya bagi tenant yang sudah terdaftar.
- **Yang TIDAK dikerjakan (sengaja):** dimensi **wilayah**, **jumlah outlet**, dan **durasi langganan**. Outlet belum punya tabelnya; durasi menuntut keputusan siklus penagihan tersendiri karena periode satu bulan tertanam di `current_period_end` dan beberapa tempat lain.
- **Catatan:** empat penjaga PHASE SAAS Tahap D tetap utuh dan masih diuji — `effective_from`, `price_locked`, `bracketFor($asOf)`, dan larangan menghapus aturan yang sudah berlaku. `PricingGrandfatherTest` disesuaikan ke bentuk baru tanpa mengubah niat satu pun test-nya.
- **Temuan sampingan yang ikut diperbaiki:** fixture `PlatformRevenueTest` membuat ringkasan omzet **tanpa** baris persetujuan — kombinasi yang tak bisa terjadi di produksi, karena job penghitung omzet menyaring berdasarkan consent. Sebelumnya lolos karena tampilan bracket memang belum memeriksa consent.

---

hro### [ADDITION] Pemisahan README ↔ Panduan Demo
- **Tanggal:** 2026-07-25
- **Fase Terkait:** Di Luar Fase — pemeliharaan dokumentasi
- **Dampak:** Dokumentasi
- **Breaking Change:** Tidak
- **Deskripsi:** `README.md` ditulis ulang sebagai penjelasan garis besar sistem, dan seluruh isi yang bersifat operasional-demo dipindah ke berkas baru `PANDUAN-DEMO.md` di akar repo. Keduanya saling menaut.
- **Alasan:** README sebelumnya sudah menyimpang jadi dua hal sekaligus dan keduanya tidak dilayani dengan baik. Pertama, ia menumpuk lima blok "Update Terbaru" berurutan tanggal — itu pekerjaan `CHANGELOG.md`, dan menduplikasinya di README berarti dua tempat yang harus disamakan setiap kali ada perubahan. Kedua, daftar fiturnya berhenti di sekitar 2026-07-11: tidak menyebut RBAC modul, Platform Console, sistem langganan dua jalur, Analisis AI, PWA offline, maupun edit transaksi — padahal semuanya sudah terpasang. Sementara itu kredensial demo dan urutan seeder tidak tertulis di mana pun, jadi setiap sesi demo dimulai dengan menggali seeder.
- **File Terdampak:**
  - `README.md` — ditulis ulang: gambaran umum tiga jenis pengguna, arsitektur (dua lapis auth, isolasi tenant, grup middleware bertenant), fitur per peran, model langganan, kontrol akses, API, MCP, Analisis AI, instalasi, pengujian, struktur direktori. Blok "Update Terbaru" dibuang — isinya sudah ada di berkas ini.
  - `PANDUAN-DEMO.md` — **baru**: kredensial tiap akun, isi data kedua tenant demo, urutan seeder beserta mana yang idempotent, kondisi langganan, alur demo lima babak, dan batasan yang perlu diketahui sebelum presentasi.
- **Catatan:** Dua hal yang terverifikasi saat penulisan dan layak dicatat karena mudah salah diingat: (1) `gudang@sapi.test` dibuat lewat UI Staf, bukan seeder — kata sandinya tidak ada di kode mana pun; (2) kedua seeder transaksi menyemai sampai `now()` **saat dijalankan**, jadi seed yang sudah lama membuat kartu "Omzet Hari Ini" kosong. Keduanya didokumentasikan di `PANDUAN-DEMO.md` beserta perintah pemulihannya.

---

### [ADDITION] Hub Dokumentasi Publik — Dua Jalur
- **Tanggal:** 2026-07-25
- **Fase Terkait:** Di Luar Fase — permintaan pemilik SaaS
- **Dampak:** Config | Controller | Route | Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Hub dokumentasi publik di `/dokumentasi` dengan dua jalur — **Panduan Penggunaan** (pemilik usaha & kasir, 6 halaman) dan **Dokumentasi Developer** (integrator & AI, 3 halaman). Ditautkan dari nav dan footer landing page.
- **Alasan:** Permintaan langsung. Sekaligus memperbaiki hal yang tidak disadari: `/api-docs` sudah ada sejak lama tapi **tidak ditautkan dari mana pun** — dokumentasi yang tidak bisa ditemukan sama saja dengan tidak ada.
- **File Terdampak:**
  - `config/docs.php` — manifes: menggerakkan hub, sidebar, dan validasi rute
  - `app/Http/Controllers/Public/DocsController.php`
  - `resources/docs/{panduan,developer}/*.md` — 9 halaman
  - `resources/views/public/docs/{layout,index,page}.blade.php`
  - `resources/views/public/landing.blade.php` — tautan di nav (desktop + mobile) dan footer
  - `tests/Feature/Public/DocsTest.php` — 9 test, suite penuh **461 hijau**
- **Keputusan:**
  1. **Isi berupa markdown** di `resources/docs/`, dirender server-side — pola yang sama dengan dokumen persetujuan. Jauh lebih ramah ditulis dan ditinjau daripada Blade untuk teks yang memang prosa.
  2. **Manifes di config adalah gerbangnya, bukan keberadaan berkas.** Halaman yang tidak terdaftar tidak bisa dibuka meski berkasnya ada; kalau dibalik, segmen `{page}` dari URL berubah jadi jalan menyusuri sistem berkas.
  3. **`/api-docs` dibiarkan berdiri sendiri.** Kartu endpoint dan badge method-nya tidak terwakili markdown, dan halamannya sudah baik — hub menautkannya, bukan menyerapnya.
  4. **CSS dokumentasi ditulis terpisah**, tidak diambil dari berkas `api-docs.blade.php` yang 1.800 baris. Paletnya disalin agar sekeluarga, tapi komponen khusus endpoint tidak ikut.
- **Catatan:** halaman ini publik dan tidak menuntut login — calon pelanggan membacanya sebelum punya akun.

---

### [ADDITION] Permissions RBAC di Mobile API (BL-003)
- **Tanggal:** 2026-07-25
- **Fase Terkait:** Di Luar Fase — sisa Keputusan C plan RBAC
- **Dampak:** Model | Middleware | Controller | Config | Test
- **Breaking Change:** Tidak — payload autentikasi bertambah field, tidak ada yang berubah bentuk
- **Deskripsi:** Payload login mobile dan `/mobile/tenant/profile` kini menyertakan `permissions` (owner → `['*']`, staf → daftar modul). Middleware `permission.api` tersedia sebagai kembaran JSON dari `permission:` milik web.
- **File Terdampak:**
  - `app/Models/User.php` — `modulePermissions()`, satu sumber untuk web & mobile
  - `app/Http/Middleware/HandleInertiaRequests.php` — memakai method yang sama
  - `app/Http/Middleware/EnsureModuleApi.php` + alias `permission.api`
  - `MobileAuthController`, `MobileTenantController`
  - `tests/Feature/Api/MobilePermissionsTest.php` — 11 test, suite penuh **452 hijau**

- **TEMUAN yang mengubah lingkup, dan keputusan atasnya** *(2026-07-25)*:
  Entri backlog mengandaikan endpoint mobile bisa digerbang per modul. Kenyataannya **tidak satu pun endpoint mobile yang ada saat ini punya padanan bergerbang modul di web** — semuanya POS, laci kas, dan riwayat kasir, yang di web sengaja hanya digerbang `role:cashier,owner`. Test RBAC menuliskannya terang-terangan: *"Cashier routes are never RBAC-gated"*.

  **Keputusan pemilik SaaS:** `pos` dan `cash_drawer` tetap jadi **penanda menu**, bukan gerbang rute — di web maupun mobile. Alasannya: menggerbangnya di mobile akan mengunci staf yang dibuat lewat pilihan "Tanpa role (POS saja)" (opsi pertama di form tambah staf), sekaligus membuat orang yang sama ditolak aplikasi tapi diterima peramban.

  Akibatnya `permission.api` **belum menggerbang endpoint apa pun** hari ini. Ia tetap dipasang dan diuji, siap untuk endpoint mobile berikutnya yang memang bermodul (mis. bila kelak ada endpoint laporan atau stok).

- **Lubang yang tersingkap dan SENGAJA dibiarkan:** di web, kasir ber-role "Gudang" (hanya `stock`) masih bisa mengetik `/cashier/pos` dan berjualan, karena rute kasir tak pernah digerbang modul. Menutupnya adalah perubahan perilaku bagi staf yang sudah ada, dan diputuskan tidak dikerjakan sekarang.
- **Yang ikut membaik:** perhitungan izin sisi web tidak lagi ditulis inline di `HandleInertiaRequests` — dua perhitungan mirip pasti bercabang begitu salah satunya diperbaiki.

---

### [ADDITION] Pesan Validasi Berbahasa Indonesia (BL-004)
- **Tanggal:** 2026-07-25
- **Fase Terkait:** Di Luar Fase
- **Dampak:** Config | Lang | Controller | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Seluruh pesan validasi bawaan Laravel kini berbahasa Indonesia, berikut nama kolomnya. Berlaku di **semua form** sekaligus — web tenant, panel platform, dan API mobile — bukan per-form.
- **Alasan:** Seluruh antarmuka aplikasi ini berbahasa Indonesia; pesan validasi adalah satu-satunya tempat yang masih berbahasa Inggris.
- **File Terdampak:**
  - `lang/id/{validation,auth,passwords,pagination}.php` — terjemahan lengkap; `lang/en/*` ikut dipublikasikan sebagai fallback
  - `config/app.php` — `locale` default jadi `'id'`, `fallback_locale` tetap `'en'`
  - `.env` & `.env.example` — `APP_LOCALE=id`
  - Tiga controller autentikasi — pesan kredensial salah dipindah ke `__('auth.failed')`
  - `tests/Feature/LocalizationTest.php` — 11 test, suite penuh **441 hijau**
- **Keputusan yang perlu diketahui:**
  1. **Ditempuh lewat locale global** (usulan 1 di backlog), bukan `messages()` per FormRequest (usulan 2). Sekali kerja untuk semua form, dan form baru ikut terjemahan dengan sendirinya.
  2. **Daftar `attributes` disusun dari kolom yang benar-benar divalidasi aplikasi ini**, bukan daftar umum. Tanpa daftar itu, pesannya berbunyi "Kolom business_name wajib diisi" — separuh jadi, dan justru lebih janggal daripada tidak diterjemahkan.
  3. **Default locale ditaruh di `config/app.php`**, bukan hanya `.env`, supaya berlaku juga saat test berjalan dan di pemasangan yang lupa mengisinya.
  4. **Fallback tetap `'en'`.** Kunci yang terlewat lebih baik muncul sebagai kalimat Inggris daripada sebagai `validation.required`.
- **Efek samping yang menguntungkan:** tanggal yang dirender lewat `translatedFormat()` ikut berbahasa Indonesia — mis. tenggat penangguhan di halaman langganan kini berbunyi "20 Agustus 2026".
- **Catatan Migrasi:** Tidak ada migrasi. Jalankan `php artisan config:clear` bila config sempat di-cache.

---

### [ADDITION] Peringatan Login Gagal & Verifikasi Email (BL-011, BL-014)
- **Tanggal:** 2026-07-25
- **Fase Terkait:** Di Luar Fase — dua entri backlog yang dikerjakan bersama karena berbagi kanal peringatan
- **Dampak:** Migration | Model | Service | Notification | Middleware | Controller | Command | Route | Config | Frontend | Test
- **Breaking Change:** Tidak — pengguna yang sudah ada diisi mundur sebagai terverifikasi
- **Deskripsi:**
  **`[BL-011]`** — percobaan masuk yang gagal ke panel platform kini memicu peringatan surel ke pemilik SaaS. Dua pola dengan ambang sendiri: banyak kegagalan pada satu alamat (penebakan kata sandi) dan banyak alamat berbeda dari satu IP (penebakan akun). Berjalan tiap jam.
  **`[BL-014]`** — pemilik usaha yang mendaftar sendiri wajib memverifikasi alamat surelnya sebelum aplikasi bisa dipakai. IP pendaftaran dicatat, dan pendaftaran berulang dari satu IP **ditandai untuk ditinjau** — bukan diblokir.
- **Alasan:** Keduanya butuh hal yang sama: cara memberi tahu pemilik SaaS bahwa ada yang perlu ia lihat. Dikerjakan bersama supaya mekanismenya satu, bukan dua yang mirip.
- **KOREKSI atas catatan lama:** `[BL-011]` menyatakan *"belum ada kanal notifikasi apa pun di proyek (email/Telegram)"*. **Itu sudah tidak benar sejak `[BL-010]`/`[BL-012]`** — surel sudah jadi kanal yang bekerja lewat notifikasi pemulihan kata sandi. Jadi tidak ada kanal baru yang dibangun; Telegram/n8n tidak diperlukan.
- **File Terdampak:**
  - Fondasi bersama: `config/platform-alerts.php`, `app/Notifications/PlatformAlert.php`, `app/Services/PlatformAlertService.php`
  - BL-011: `app/Console/Commands/AlertFailedLogins.php` (terjadwal per jam)
  - BL-014: migrasi `add_signup_tracking_to_tenants_table`, `app/Http/Middleware/EnsureEmailVerified.php`, `app/Http/Controllers/Auth/EmailVerificationController.php`, `app/Notifications/TenantVerifyEmail.php`, `app/Services/SignupGuardService.php`, `resources/js/Pages/Auth/VerifyEmail.vue`
  - `app/Console/Commands/PruneAbandonedTenants.php` — **sengaja tidak dijadwalkan**
  - `tests/Feature/Platform/FailedLoginAlertTest.php`, `tests/Feature/Auth/EmailVerificationTest.php` — suite penuh **430 hijau**
- **Keputusan yang perlu diketahui:**
  1. **Peringatan sejenis dijeda** (`cooldown_minutes`, default 3 jam). Kotak masuk yang penuh peringatan identik dibaca persis sama seperti kotak masuk tanpa peringatan. Baris audit `alert.sent` yang mencatat pengiriman itu pula yang jadi dasar jedanya — satu sumber, bukan dua.
  2. **Staf platform tidak menerima peringatan keamanan**, hanya akun `is_owner`. Staf bisa saja hanya dipercaya satu modul.
  3. **Pendaftaran berulang ditandai, bukan diblokir.** Satu IP publik bisa dipakai bersama satu kompleks pertokoan.
  4. **Staf buatan owner langsung terverifikasi.** Kasir warung kecil kerap tak punya alamat surel sendiri.
  5. **Pemangkasan tenant terbengkalai tidak dijadwalkan**, memakai `ConfirmableTrait` dan `--dry-run`. Syaratnya sempit: tak pernah diverifikasi, tak pernah bertransaksi, lebih tua dari 30 hari.
- **Cakupan yang perlu disadari:** peringatan login gagal hanya mencakup **panel platform**. Login tenant yang gagal belum dicatat di mana pun — sisi tenant tidak punya tabel audit sendiri.
- **Catatan Migrasi:** `php artisan migrate`. Di lingkungan lokal `MAIL_MAILER=log`, jadi surel verifikasi mendarat di `storage/logs/laravel.log`. Isi `PLATFORM_ALERT_EMAIL` bila peringatan sebaiknya masuk ke kotak surel operasional.

---

### [ADDITION] Jalur Subsidi & Aturan Harga Dinamis — PHASE SAAS Tahap C & D (BL-005, BL-006)
- **Tanggal:** 2026-07-25
- **Fase Terkait:** `docs/phases-2/PHASE-SAAS_Platform-Console-Subscription.md` — Tahap C & D (menutup fase ini seluruhnya)
- **Dampak:** Migration | Model | Service | Job | Command | Controller | Route | Config | Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Jalur subsidi UMKM berjalan penuh — omzet dihitung otomatis ke tabel ringkasan, dokumen persetujuan sendiri, pengajuan & pencabutan mandiri, tampilan bracket di panel platform dengan angka persis lewat aksi tercatat. Aturan harga berpindah dari config ke tabel yang di-CRUD pemilik SaaS, dengan grandfathering lewat `effective_from`.
- **Alasan:** Menutup `[BL-006]` (sistem langganan dua jalur) dan sisa `[BL-005]`.
- **File Terdampak (ringkas):**
  - Migrasi: `tenant_monthly_metrics`, `pricing_rules`, kolom `subscriptions.track_changed_at|track_reverts_at`
  - `app/Jobs/ComputeTenantMonthlyRevenue.php` — di luar namespace `Platform`, satu-satunya pintu ke `transactions`
  - `app/Console/Commands/{ComputeTenantRevenue,PruneTenantMetrics}.php` — terjadwal tanggal 1
  - `app/Services/PricingService.php`, penambahan di `SubscriptionService` & `ConsentService`
  - `app/Http/Controllers/Platform/{RevenueController,PricingRuleController}.php`
  - `resources/consents/subsidized-v1.md`
  - 3 berkas test baru — suite penuh **402 hijau**

- **Keputusan yang MEMBATALKAN keputusan sebelumnya:**
  **Daftar tenant menampilkan bracket, bukan angka persis.** Keputusan 2026-07-21 menyatakan angka rupiah tampil langsung di daftar. Diganti 2026-07-25: daftar hanya menampilkan kelompok harga, dan angka persis dibuka di `/platform/revenue/{tenant}` yang tiap kunjungannya tercatat `sensitive` **tanpa deduplikasi**. Alasannya ditulis di dokumen consent klien dan bisa mereka tuntut buktinya.

- **Penyimpangan dari plan (disengaja):**
  1. **Job penghitung menyaring dua hal**, bukan satu: `pricing_track = subsidized` **dan** persetujuan yang masih aktif. Dengan pencabutan yang baru berlaku di akhir periode, saringan tunggal akan terus mengumpulkan data sebulan penuh setelah tenant menarik izinnya.
  2. **`config('subscription.revenue_brackets')` tidak lagi dibaca aplikasi** — hanya benih migrasi `pricing_rules`.
  3. **Perpindahan jalur diberi jarak minimum 3 bulan** (`subscriptions.track_changed_at`) — plan menyisakannya sebagai pertanyaan terbuka.
- **Catatan Migrasi:** `php artisan migrate`. Pemasangan lama otomatis mendapat bracket awal dari config, dengan `effective_from` 2000-01-01 agar tenant berjalan tidak kehilangan bracket-nya.

---

### [ADDITION] Langganan Dasar Jalur Normal — PHASE SAAS Tahap B (BL-005)
- **Tanggal:** 2026-07-25
- **Fase Terkait:** `docs/phases-2/PHASE-SAAS_Platform-Console-Subscription.md` — Tahap B
- **Dampak:** Migration | Model | Service | Middleware | Controller | Route | Config | Frontend | Test
- **Breaking Change:** Tidak — tenant yang sudah ada diisi mundur ke `active` dengan seat selebar pemakaiannya sekarang
- **Deskripsi:** Tenant kini punya siklus hidup komersial: trial 1 bulan saat mendaftar, masa tenggang hanya-baca saat periodenya lewat, penangguhan bila tenggangnya habis. Batas seat ditegakkan keras, staf bisa dinonaktifkan alih-alih dihapus, ada dokumen persetujuan jalur normal berversi, dan pemilik SaaS punya halaman langganan & pembayaran dengan verifikasi bukti transfer manual.
- **Alasan:** Tahap A hanya membangun identitas dan panel baca. Tanpa model langganan, tidak ada cara menagih, membatasi, atau mengakhiri layanan selain lewat database.
- **File Terdampak (ringkas):**
  - Migrasi: `plans`, `subscriptions`, `invoices`, `tenant_consents`; kolom `tenants.status`, `tenants.pricing_track`, `users.is_active`, `invoices.kind|grants_seats|previous_seats`, `subscriptions.provisional_blocked`
  - `app/Services/SubscriptionService.php`, `app/Services/ConsentService.php`
  - `app/Http/Middleware/EnsureSubscriptionActive.php` — digabung ke grup `tenant` & `tenant.api`
  - `app/Console/Commands/AdvanceSubscriptionLifecycle.php` — terjadwal harian 03:30
  - `app/Http/Controllers/Billing/{SubscriptionController,ConsentController,UpgradeController}.php`
  - `app/Http/Controllers/Platform/{SubscriptionController,InvoiceController}.php` + dua resource daftar-putih
  - `resources/consents/normal-v1.md`, `config/subscription.php`
  - 5 berkas test baru di `tests/Feature/Subscription/` + `tests/Feature/Platform/PlatformBillingTest.php` — suite penuh 358 hijau

- **Penyimpangan dari dokumen plan (disengaja, dicatat agar tidak terbaca sebagai kelalaian):**
  1. **`subscriptions` tidak punya kolom `status`.** Bagian 4 plan memuat status di dua tabel sekaligus. Keadaan operasional akhirnya hanya hidup di `tenants.status` — middleware membacanya di tiap request, dan dua kolom status berdampingan pasti melenceng satu sama lain cepat atau lambat.
  2. **Keadaan `grace` ditambahkan** di antara aktif dan tertangguh, sebagai wujud keputusan "read-only + tenggang" yang diambil 2026-07-25.
  3. **`tenant`/`tenant.api` diubah dari alias jadi grup middleware**, supaya gerbang langganan tidak bisa terlupakan saat ada grup rute baru.
  4. **Definisi seat = pengguna aktif**, dengan `seat_high_water` sebagai dasar tagihan. Plan menyisakan definisi seat sebagai keputusan terbuka; ditutup 2026-07-25.
- **Catatan Migrasi:** Jalankan `php artisan migrate`. Migrasi `add_upgrade_columns_to_invoices_table` membuat indeks unik baru sebelum membuang yang lama — urutan sebaliknya ditolak MySQL karena foreign key `tenant_id` bersandar pada indeks lama itu.

---

### [ADDITION] Pemulihan Kata Sandi Pengguna Tenant (BL-012)
- **Tanggal:** 2026-07-22
- **Fase Terkait:** Di Luar Fase (temuan saat mengerjakan `[BL-010]`)
- **Dampak:** Model | Notification | Controller | Route | Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Owner dan staf tenant kini punya alur lupa/atur-ulang kata sandi. Sebelumnya **tidak ada satu pun** rute password reset di aplikasi — owner yang lupa kata sandi hanya bisa ditolong lewat akses database langsung.
- **Alasan:** Dampaknya lebih besar daripada sisi platform: staf masih bisa ditolong owner lewat manajemen staf, tapi owner tidak bisa ditolong siapa pun.
- **File Terdampak:**
  - `app/Notifications/TenantResetPassword.php`, `app/Models/User.php` (`sendPasswordResetNotification`)
  - `app/Http/Controllers/Auth/PasswordResetController.php`, `routes/web.php`
  - `app/Providers/AppServiceProvider.php` — limiter `password-reset`
  - `resources/js/Pages/Auth/{ForgotPassword,ResetPassword}.vue`, tautan di `Login.vue`
  - `tests/Feature/Auth/PasswordResetTest.php` — 12 test baru
- **Keputusan teknis yang perlu diketahui:**
  1. **Tidak perlu migration.** Tabel `password_reset_tokens` sudah ada sejak migrasi bawaan Laravel, broker `users` sudah terkonfigurasi, dan `User` sudah memakai `Notifiable` — yang benar-benar hilang hanya rute, controller, dan halamannya. `users.email` unik global, jadi tak ada tabrakan token antar tenant.
  2. **Nama rute `password.reset` dipertahankan untuk sisi tenant**, karena itulah yang dirujuk kontrak `CanResetPassword` bawaan Laravel. Sisi platform memakai `platform.password.reset` dengan notifikasinya sendiri.
  3. **Limiter terpisah dari platform** (`password-reset` vs `platform-password-reset`): kasir yang menghabiskan jatah percobaan tidak boleh ikut mengunci jalur pemulihan pemilik SaaS. Ada test untuk batas itu.
  4. **Balasan permintaan selalu seragam**, sama seperti sisi platform, agar halaman ini tidak jadi alat memeriksa apakah sebuah email punya akun.
  5. **Pencatatan ke log aplikasi**, bukan tabel audit. Sisi tenant belum punya tabel jejak audit sendiri, dan membuatnya khusus untuk ini akan jadi perluasan lingkup.
- **Catatan Migrasi:** Tidak ada migration. `npm run build` untuk halaman barunya. Sama seperti `[BL-010]`: **`MAIL_MAILER` masih `log`** — di server sungguhan wajib dikonfigurasi ke pengirim nyata, atau tautan pemulihan tidak akan pernah sampai.

---

### [ADDITION] Pemulihan Kata Sandi Akun Platform (BL-010)
- **Tanggal:** 2026-07-22
- **Fase Terkait:** `PHASE-SAAS_Platform-Console-Subscription.md` — pelengkap Tahap A
- **Dampak:** Migration | Model | Notification | Controller | Config | Route | Frontend | Seeder | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Akun platform kini punya alur lupa/atur-ulang kata sandi sendiri. Selain itu `.env.example` memuat variabel `PLATFORM_ADMIN_*`, dan seeder menolak berjalan dengan kata sandi bawaan di luar lingkungan lokal.
- **Alasan:** Sebelumnya, pemilik SaaS yang lupa kata sandinya hanya bisa ditolong lewat akses database langsung. Ditambah, seeder diam-diam memakai kata sandi `password` bila env tidak diisi — tanpa satu pun pengingat, karena variabelnya juga tidak ada di `.env.example`.
- **File Terdampak:**
  - `database/migrations/2026_07_22_012951_create_platform_password_reset_tokens_table.php`
  - `config/auth.php` — broker `platform_users`
  - `app/Notifications/PlatformResetPassword.php`, `app/Models/PlatformUser.php` (`sendPasswordResetNotification`, trait `Notifiable`)
  - `app/Http/Controllers/Platform/PasswordResetController.php`, `routes/web.php`
  - `app/Providers/AppServiceProvider.php` — limiter `platform-password-reset`
  - `resources/js/Pages/Platform/{ForgotPassword,ResetPassword}.vue`, tautan di `Login.vue`
  - `.env.example`, `database/seeders/PlatformUserSeeder.php`
  - `tests/Feature/Platform/PlatformPasswordResetTest.php` — 10 test baru
- **Keputusan teknis yang perlu diketahui:**
  1. **Tabel token terpisah dari tenant.** Alamat email yang sama bisa terdaftar di kedua dunia; kalau tokennya ditumpuk di satu tabel, permintaan di satu sisi menimpa token sisi lain, dan token yang bocor dari satu sisi bisa dipakai di sisi lain. Terverifikasi lewat test: email tenant tidak pernah menerima tautan platform.
  2. **Notifikasi sendiri, bukan bawaan Laravel.** Yang bawaan menyusun tautan lewat `route('password.reset')` milik tenant; penerimanya akan mendarat di halaman yang tak akan pernah mengenali akunnya. Terverifikasi di log surel: tautannya mengarah ke `/platform/reset-password/...`.
  3. **Balasan permintaan selalu seragam**, terdaftar atau tidak. Membedakan keduanya akan mengubah halaman ini jadi alat memeriksa keberadaan akun pada panel yang memegang data seluruh klien. Statusnya tetap dicatat di jejak audit — penyisir terekam tanpa mendapat petunjuk dari layar.
  4. **Seeder gagal keras di luar `APP_ENV` lokal** bila `PLATFORM_ADMIN_PASSWORD` masih bawaan. Perhatikan `env()` mengembalikan null saat config di-cache — tanpa gerbang ini, deploy yang sudah rapi pun bisa berakhir memakai kata sandi `password`.
- **Belum dikerjakan:** 2FA (dicatat sebagai `[BL-011]`… lihat `[BL-013]` di BACKLOG).
- **Catatan Migrasi:** `php artisan migrate` lalu `npm run build`. **`MAIL_MAILER` masih `log`** di lingkungan pengembangan — di server sungguhan wajib dikonfigurasi ke pengirim nyata, atau tautan pemulihan tidak akan pernah sampai.

---

### [ADDITION] Halaman & Retensi Jejak Audit (BL-009)
- **Tanggal:** 2026-07-22
- **Fase Terkait:** `PHASE-SAAS_Platform-Console-Subscription.md` — pelengkap Tahap A
- **Dampak:** Migration | Model | Controller | Config | Route | Console | Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Jejak audit kini punya halaman baca (`/platform/audit-logs`) dengan filter, derajat kejadian (`routine`/`sensitive`) untuk menekan kebisingan, dan retensi berbeda per derajat yang dijalankan perintah terjadwal.
- **Alasan:** Log yang hanya bisa dibaca lewat query database tidak berfungsi sebagai alat pertanggungjawaban ke klien. Ditambah, pencatatan setiap kali daftar dibuka membuat kejadian penting tenggelam, dan tabelnya tumbuh tanpa batas.
- **File Terdampak:**
  - `config/platform-audit.php` — garis rutin/sensitif, jendela deduplikasi, retensi
  - `database/migrations/2026_07_22_011535_add_severity_to_platform_audit_logs_table.php` — kolom `severity` + isi mundur
  - `app/Models/PlatformAuditLog.php` — `record()` (sensitif) & `recordRoutine()` (dideduplikasi)
  - `app/Http/Controllers/Platform/AuditLogController.php`, `resources/js/Pages/Platform/AuditLogs/Index.vue`
  - `app/Console/Commands/PrunePlatformAuditLogs.php` + jadwal harian di `routes/console.php`
  - `config/platform-rbac.php` — `audit_logs` jadi `available => true`
  - `tests/Feature/Platform/PlatformAuditLogTest.php` — 12 test baru
- **Keputusan teknis yang perlu diketahui:**
  1. **Garis rutin vs sensitif.** `routine` = akses **baca** yang wajar berulang (buka daftar, login berhasil, logout). `sensitive` = **mengubah keadaan**, membuka **data bisnis klien**, atau **kejadian keamanan** (login gagal). Pedoman untuk kejadian baru ditulis di config: *"kalau klien menuntut pertanggungjawaban, apakah baris ini yang akan saya tunjukkan?"*
  2. **Kejadian rutin dideduplikasi, bukan dihapus.** Menghapusnya akan membuat pertanyaan "siapa membuka daftar klien saya minggu lalu?" tak terjawab — padahal itulah pertanyaan yang ingin dijawab audit log. Deduplikasi per aktor+aksi+subjek dalam 15 menit menyelesaikan kebisingan tanpa kehilangan jawabannya. Terverifikasi: empat kali membuka daftar tenant menghasilkan satu baris.
  3. **Deduplikasi per aktor, bukan global** — kalau global, akun kedua yang membuka daftar yang sama justru tersembunyi dari jejak.
  4. **Default kolom `severity` adalah `sensitive`.** Kejadian yang belum diklasifikasikan tidak boleh diam-diam terbuang lebih cepat oleh pemangkasan; hanya akses baca yang sengaja diturunkan derajatnya.
  5. **Jejak bertahan meski akunnya dihapus** (FK `nullOnDelete`) — ditampilkan sebagai "Akun telah dihapus", karena justru itu gunanya.
- **Catatan Migrasi:** `php artisan migrate` lalu `npm run build`. Pastikan penjadwal Laravel (`schedule:run`) berjalan di server agar `platform:prune-audit-logs` benar-benar dieksekusi harian; perintah ini juga bisa dipanggil manual dan punya `--dry-run`.

---

### [ADDITION] Manajemen Akun Platform (BL-008)
- **Tanggal:** 2026-07-21
- **Fase Terkait:** `PHASE-SAAS_Platform-Console-Subscription.md` — pelengkap Tahap A (tidak terjadwal di Tahap B–D mana pun)
- **Dampak:** Migration | Model | Middleware | Controller | Config | Route | Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Halaman `/platform/users` untuk membuat akun platform, mengubahnya, dan mencentang modul per akun — melengkapi mekanisme `platform_user_modules` yang sudah ada sejak Tahap A tapi belum punya UI. Sebelumnya akun hanya bisa dibuat lewat seeder dan modulnya hanya bisa diubah lewat database.
- **Alasan:** Pola "tambah akun lalu tinggal dicentang modulnya" adalah kebutuhan yang diminta sejak awal, tapi tidak masuk rencana tahap mana pun — jadi akan terlewat kalau tidak dikerjakan terpisah.
- **File Terdampak:**
  - `database/migrations/2026_07_21_221120_add_is_owner_to_platform_users_table.php` — kolom `is_owner` + isi mundur
  - `app/Models/PlatformUser.php` — `isOwner()`, `moduleNames()`/`hasModule()` melewati pengecekan untuk owner
  - `app/Http/Middleware/EnsurePlatformOwner.php` + alias `platform.owner` di `bootstrap/app.php`
  - `app/Http/Controllers/Platform/PlatformUserController.php`, `resources/js/Pages/Platform/Users/Index.vue`
  - `config/platform-rbac.php` — flag `available` per modul
  - `app/Http/Resources/Platform/TenantResource.php` — dipindah dari `App\Http\Resources\PlatformTenantResource`
  - `app/Http/Middleware/HandleInertiaRequests.php` — `auth.platformUser.is_owner`
  - `resources/js/Layouts/PlatformLayout.vue` — nav `ownerOnly`
  - `tests/Feature/Platform/PlatformUserManagementTest.php` — 11 test baru
- **Keputusan teknis yang perlu diketahui:**
  1. **Manajemen akun dijaga penanda `is_owner`, BUKAN modul grantable.** Ini keamanan, bukan gaya: modul yang bisa dicentang berarti staf platform yang memegangnya dapat mencentangkan `revenue_data` untuk dirinya sendiri, sehingga pemisahan modul sensitif kehilangan artinya. Mengikuti pola sisi tenant (`role:owner` untuk manajemen staf/role, lihat catatan di `config/rbac.php`).
  2. **Owner tidak menyimpan baris modul sama sekali.** Aksesnya berasal dari penanda; menuliskan baris modul untuknya hanya jadi data menyesatkan yang tampak bisa dicabut padahal tidak berpengaruh. Migration ikut membersihkan baris sisa seeder Tahap A.
  3. **Modul tanpa halaman ditandai `available => false`** dan ditolak di validasi — memberikan izin yang tidak berefek hanya menyesatkan yang mengatur.
  4. **Arch test resource kini berbasis namespace**, bukan satu nama kelas, sehingga resource platform berikutnya otomatis terjaga.
  5. Dua penjaga terhadap terkunci sendiri: akun sendiri tak bisa dihapus, dan akun pemilik terakhir tak bisa dihapus.
- **Catatan Migrasi:** `php artisan migrate` (mengisi mundur `is_owner` untuk akun platform pertama) lalu `npm run build`.

---

### [HOTFIX] Rate Limit Endpoint Login (BL-007)
- **Tanggal:** 2026-07-21
- **Fase Terkait:** Di Luar Fase (temuan review setelah Platform Console Tahap A)
- **Dampak:** Route | Provider | Test
- **Breaking Change:** Tidak
- **Deskripsi:** `POST /login` dan `POST /platform/login` sebelumnya tidak punya pembatas laju sama sekali, sehingga tebak-kata-sandi otomatis tidak menemui hambatan. Ditambahkan tiga limiter bernama yang semuanya berkunci **email + IP**; endpoint mobile yang tadinya `throttle:5,1` (per IP) ikut dipindahkan ke limiter bernama agar konsisten.
- **Alasan:** Satu akun platform yang jebol membuka data administratif seluruh klien sekaligus. Audit log sudah mencatat `login.failed` dengan rajin — tapi mencatat serangan tanpa menghentikannya tidak melindungi siapa pun.
- **File Terdampak:**
  - `app/Providers/AppServiceProvider.php` — limiter `login`, `platform-login`, `mobile-login` + helper kunci & balasan
  - `routes/web.php` — `throttle:login` dan `throttle:platform-login`
  - `routes/api.php` — `throttle:5,1` → `throttle:mobile-login`
  - `tests/Feature/Security/LoginThrottleTest.php` — 8 test baru
- **Keputusan teknis yang perlu diketahui:**
  1. **Kunci email + IP, bukan IP saja.** Satu warung atau kantor sering keluar lewat satu IP publik; pembatasan per-IP murni membuat kasir saling mengunci padahal tak ada yang menyerang. Email dinormalkan lowercase supaya mengubah kapitalisasi tidak memberi jatah baru.
  2. **Langit-langit 20/jam per IP hanya di panel platform.** Ia menahan penebakan lintas-email yang lolos dari kunci email+IP, dan aman di sana karena penggunanya segelintir. Di login tenant hal yang sama justru berbahaya — karena itu sengaja tidak dipasang, dan ada test yang menjaga batas itu tidak merembet.
  3. **Balasan berupa error validasi berbahasa Indonesia**, bukan halaman 429 generik (lewat `Limit::response()`), karena alur login memakai Inertia. Endpoint mobile tetap 429 JSON.
- **Catatan Migrasi:** Tidak ada. Limiter memakai cache; di produksi pastikan `CACHE_STORE` bukan `array` agar hitungannya bertahan lintas proses.

---

### [ADDITION] Platform Console — Fondasi Panel Pemilik SaaS (Tahap A)
- **Tanggal:** 2026-07-21
- **Fase Terkait:** `docs/phases-2/PHASE-SAAS_Platform-Console-Subscription.md` — Tahap A (dari `[BL-005]`/`[BL-006]`)
- **Dampak:** Migration | Model | Middleware | Controller | Config | Route | Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Tingkat akses ketiga di atas tenant: akun **pemilik SaaS** dengan tabel & guard sendiri (`platform_users` / guard `platform`), izin per modul lewat `platform_user_modules` + `config/platform-rbac.php`, panel `/platform` berisi beranda dan daftar tenant **read-only**, serta jejak audit `platform_audit_logs`. Belum ada langganan, harga, maupun data omset — itu Tahap B–D.
- **Alasan:** Pemilik SaaS perlu tahu siapa kliennya dan berapa akunnya untuk menjalankan bisnis, **tanpa** bisa melihat data operasional klien. Fondasi isolasinya sengaja dibangun lebih dulu berikut testnya, karena semua tahap berikutnya menumpuk di atasnya.
- **File Terdampak:**
  - `database/migrations/2026_07_21_1324{29,30,31}_*` — `platform_users`, `platform_audit_logs`, `platform_user_modules`
  - `app/Models/PlatformUser.php`, `PlatformUserModule.php`, `PlatformAuditLog.php` — model baru
  - `app/Models/Tenant.php` — relasi `owners()` (kontak penagihan)
  - `config/auth.php` — guard `platform` + provider `platform_users`
  - `config/platform-rbac.php` — katalog 6 modul platform
  - `app/Http/Middleware/EnsurePlatformModule.php` + alias `platform.can` di `bootstrap/app.php`
  - `app/Http/Middleware/HandleInertiaRequests.php` — bedakan `User` vs `PlatformUser` lewat `instanceof`; akun platform dibagikan di kunci **terpisah** `auth.platformUser`
  - `bootstrap/app.php` — `redirectGuestsTo()` bersyarat untuk area `/platform`
  - `app/Http/Controllers/Platform/{Auth,Dashboard,Tenant}Controller.php`, `app/Http/Resources/PlatformTenantResource.php`
  - `resources/js/Layouts/PlatformLayout.vue`, `resources/js/Pages/Platform/**`
  - `database/seeders/PlatformUserSeeder.php`, `database/factories/PlatformUserFactory.php`
  - `tests/Feature/Platform/{PlatformAuthTest,PlatformIsolationTest,PlatformArchTest}.php` — 16 test
- **Keputusan teknis yang perlu diketahui:**
  1. **spatie TIDAK dipakai di sisi platform.** Pivot `model_has_roles` menuntut `tenant_id` non-null karena kolom itu bagian dari primary key-nya, sedangkan akun platform tak punya tenant. Diganti tabel `platform_user_modules` yang sederhana; pola UI modul+centang tetap sama seperti Phase RBAC.
  2. **Isolasi tidak bersandar pada `TenantScope`.** Di request platform scope itu memfilter `tenant_id = null` (nol baris — gagal menutup, aman), tapi di job/command/seeder ia tidak aktif sama sekali. Karena itu batasnya dijaga tiga lapis: arch test (impor), isolation test (payload HTTP), dan resource daftar-putih.
  3. **`PlatformTenantResource` memakai daftar putih**, bukan `parent::toArray()` — kolom baru di `tenants` (mis. `ai_api_key`) tidak ikut bocor dengan sendirinya.
- **Catatan Migrasi:** `php artisan migrate` lalu `php artisan db:seed --class=PlatformUserSeeder`. Akun pertama diambil dari `PLATFORM_ADMIN_EMAIL`/`PLATFORM_ADMIN_PASSWORD`; nilai bawaan hanya untuk lokal dan **wajib** diganti sebelum dipakai di server sungguhan. Perlu `npm run build` karena ada komponen Vue baru.

---

### [HOTFIX] Lima Kendala Implementasi RBAC & Resolusinya
- **Tanggal:** 2026-07-21
- **Fase Terkait:** Phase RBAC (menyertai entri `[ADDITION]` RBAC di bawah)
- **Dampak:** Middleware | Controller | Config | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Lima kendala yang ditemukan saat mengimplementasikan RBAC, semuanya sudah diselesaikan dan diverifikasi (suite penuh hijau — 218 passed):
  1. **Inertia `share()` berjalan sebelum team-id spatie di-set.** Inertia mengevaluasi `share()` di awal middleware global, sebelum `EnsureTenant` men-set team-id → evaluasi eager `permissions` mendapat team-id `null` (kosong). **Resolusi:** `auth.user.permissions` dijadikan **lazy closure** agar diresolusi di fase render (team-id sudah benar).
  2. **`getPermissionNames()` (relasi Eloquent) kosong dari konteks share Inertia** meski team-id benar. **Resolusi:** daftar permission share dihitung via `$user->can($module)` per modul katalog — jalur registrar/Gate spatie yang sama dengan middleware `permission:`. Terverifikasi (test `staff page role list…`) bahwa `getRoleNames()`/`getPermissionNames()` **tetap benar** di controller biasa; hanya fase-share yang rapuh.
  3. **Kebocoran daftar role antar-tenant** di `StaffController@index` (query `Role` tak di-scope team). **Resolusi:** filter `where('tenant_id', …)` + test regresi memastikan role tenant B tak muncul di halaman staf tenant A.
  4. **Ekspektasi test POS kasir salah** — kasir tanpa sesi kas **sengaja** di-redirect ke `cashier.cash-drawer` (baseline, bukan blokir RBAC). **Resolusi:** ekspektasi test dikoreksi (`assertRedirect`), bukan mengubah perilaku aplikasi.
  5. **Katalog modul terkopel ke class Seeder + metadata terduplikasi** (middleware & `RoleController` meng-import `PermissionCatalogSeeder`; `RoleController` punya `MODULE_META` sendiri). **Resolusi:** katalog diekstrak ke **`config/rbac.php`** sebagai single source of truth (dipakai seeder, controller, middleware).
- **Alasan:** Menuntaskan kendala agar tidak menyisakan workaround rapuh / hutang teknis, dan menegakkan satu sumber kebenaran untuk katalog modul.
- **File Terdampak:**
  - `config/rbac.php` — **baru**: katalog modul (name → label, sensitive)
  - `app/Http/Middleware/HandleInertiaRequests.php` — lazy closure + `can()` + baca `config('rbac.modules')`
  - `app/Http/Controllers/Owner/StaffController.php` — daftar role di-scope tenant
  - `app/Http/Controllers/Owner/RoleController.php` — hapus `MODULE_META`, baca `config('rbac.modules')`
  - `database/seeders/PermissionCatalogSeeder.php` — baca `config('rbac.modules')` (hapus const `MODULES`)
  - `tests/Feature/Authorization/ModuleAccessTest.php` — test regresi isolasi role di halaman staf (kini 12 test)
- **Catatan Migrasi:** Bila config di-cache di server, jalankan `php artisan config:clear` (atau `config:cache`) agar `config/rbac.php` termuat.

---

### [ADDITION] RBAC — Kontrol Akses Modul untuk Staf Non-Owner
- **Tanggal:** 2026-07-21
- **Fase Terkait:** Phase RBAC (`docs/phases-2/PHASE-RBAC_Module-Access-Control.md`)
- **Dampak:** Dependency | Migration | Model | Middleware | Controller | Route | Frontend | Config
- **Breaking Change:** Tidak (additive — enum `role` owner/cashier tetap sebagai gerbang kasar)
- **Deskripsi:** Owner kini bisa membuat **role per-tenant** berisi kumpulan **modul** dan mengikat akun staf ke role tersebut. Staf non-owner hanya melihat & mengakses modul yang diberikan. Owner tetap super-admin (bypass semua cek permission via `Gate::before`). Implementasi memakai `spatie/laravel-permission` fitur **teams** (`team_foreign_key = tenant_id`) sehingga role terisolasi per tenant. Katalog 7 modul global: `pos`, `cash_drawer`, `products`, `stock`, `reports`, `payment_methods`, `ai_analysis`. Keputusan: shell nav staf memakai sidebar terfilter yang sama (Keputusan A); modul sensitif (`payment_methods`, `ai_analysis`) grantable tapi default tidak dicentang (Keputusan B); Mobile API ditunda ke backlog `BL-003` (Keputusan C).
- **Alasan:** Memenuhi kebutuhan "Kasir 1 hanya POS; Kasir 2 POS + Inventori" dengan role kustom per usaha.
- **File Terdampak:**
  - `composer.json` — tambah dependency `spatie/laravel-permission`
  - `config/permission.php` — `teams=true`, `team_foreign_key='tenant_id'`
  - `config/rbac.php` — katalog modul (single source of truth; lihat entri `[HOTFIX]` di atas)
  - `database/migrations/*_create_permission_tables.php` — tabel permission (kolom `tenant_id`)
  - `app/Models/User.php` — trait `HasRoles`
  - `app/Http/Middleware/EnsureTenant.php` + `EnsureTenantApi.php` — set team-id spatie setelah cek tenant
  - `app/Providers/AppServiceProvider.php` — `Gate::before` owner bypass
  - `bootstrap/app.php` — alias middleware `permission` (alias `role` lama tak diubah)
  - `database/seeders/PermissionCatalogSeeder.php` — katalog 7 modul (global, idempotent); dipanggil di `DatabaseSeeder`
  - `app/Http/Controllers/Owner/StaffController.php` + `RoleController.php` — CRUD staf & role (owner-only)
  - `routes/web.php` — grup owner dipecah: modul grantable → `permission:<modul>`; sensitif/owner-eksklusif → tetap `role:owner`
  - `app/Http/Middleware/HandleInertiaRequests.php` — share `auth.user.permissions` (lazy closure; owner `['*']`)
  - `resources/js/Layouts/OwnerLayout.vue` — sidebar difilter `can()` + grup "Tim & Akses"
  - `resources/js/Components/CashierTopbar.vue` — jalur staf ke modul owner (Keputusan A)
  - `resources/js/Pages/Owner/Staff/Index.vue` + `Owner/Roles/Index.vue` — UI kelola staf & role
  - `tests/Feature/Authorization/ModuleAccessTest.php` — 11 test (owner bypass, gating, isolasi tenant, share, CRUD)
- **Catatan Migrasi:** Jalankan `php artisan migrate` lalu `php artisan db:seed --class=PermissionCatalogSeeder` (idempotent, additif). Suite penuh hijau — 218 passed. **Catatan teknis:** share permission dihitung via `can()` per modul (jalur registrar spatie), bukan relasi Eloquent `getPermissionNames()` yang rapuh terhadap konteks team saat dipanggil dari middleware Inertia.

---

### [HOTFIX] Ekspektasi Dua Test Usang (BL-002)
- **Tanggal:** 2026-07-21
- **Fase Terkait:** Di Luar Fase (backlog `BL-002`)
- **Dampak:** Test
- **Breaking Change:** Tidak
- **Deskripsi:** Dua test dengan ekspektasi usang membuat suite selalu merah (menutupi sinyal regresi). `ExampleTest` meng-assert `GET /` → `302`, padahal `/` kini me-render landing page (`200`). `AuthTest > owner can access cashier routes` meng-assert `GET /cashier/cash-drawer` → `200` untuk owner, padahal rute itu **sengaja** mengalihkan owner ke POS (`302`). Keduanya adalah ekspektasi test yang salah, bukan bug aplikasi.
- **Alasan:** Suite hijau adalah pagar agar regresi baru mudah terdeteksi; dua kegagalan pre-existing mengaburkannya.
- **File Terdampak:**
  - `tests/Feature/ExampleTest.php` — assert `200` (sesuai nama test "successful response")
  - `tests/Feature/Auth/AuthTest.php` — arahkan ke `/cashier/pos` (rute kasir yang ter-render untuk owner) alih-alih `/cashier/cash-drawer`
- **Catatan:** Tidak ada test yang dihapus. Suite penuh hijau — 206 passed.

---

### [ADDITION] Transaksi Offline + Sinkronisasi (PWA Fase B/C/D)
- **Tanggal:** 2026-07-16
- **Fase Terkait:** Phase PWA (`docs/phases-2/PHASE-PWA_Offline-Transaction-Sync.md`) — Fase B, C, D
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Kasir kini bisa terus menjual (tunai) saat internet putus. Katalog di-cache ke IndexedDB, transaksi offline diantre di outbox lokal, lalu tersinkron otomatis saat online lewat `POST /cashier/transactions/sync`. Konflik ditangani optimistik: penjualan yang sudah terjadi fisik **tidak pernah ditolak** — stok boleh minus, transaksi ditandai `needs_review`, owner mengoreksi lewat halaman Koreksi Offline. Payload offline tidak pernah dipercaya mentah: total dihitung ulang server, kepemilikan tenant & cash-only ditegakkan, `occurred_at` dicek kewajarannya.
- **Alasan:** Menutup kehilangan penjualan saat koneksi putus tanpa mengorbankan integritas data. Melanjutkan Fase A (`client_uuid`) yang sudah menyediakan fondasi idempotensi.
- **Dependency Baru:** `idb@8.0.3` (~1–2 KB gzip) — disetujui owner 2026-07-16, lihat Keputusan Terbuka A di dokumen fase.
- **File Terdampak:**
  - `database/migrations/2026_07_16_231216_add_offline_sync_to_transactions.php` — kolom `channel`, `occurred_at`, `synced_at`, `sync_status`, `device_id` + index `(tenant_id, sync_status)` & `(tenant_id, occurred_at)`
  - `app/Models/Transaction.php` — konstanta channel/sync_status, `effectiveDate()`, `effectiveDateSql()`, scope `whereEffectiveDate`/`whereEffectiveBetween`/`whereEffectiveFrom`
  - `app/Services/TransactionService.php` — `commitOffline()`, `processOfflineItems()`, `resolveOfflineModifiers()`, `assertCashOnly()`, `parseOccurredAt()`, `generateTransactionCodeFor()`
  - `app/Http/Controllers/Cashier/POSController.php` — `sync()` (JSON, try/catch per item)
  - `app/Http/Requests/SyncOfflineTransactionsRequest.php` — validasi bentuk batch
  - `app/Http/Controllers/Owner/OfflineReviewController.php` + `resources/js/Pages/Owner/OfflineReview/Index.vue` — halaman koreksi
  - `app/Services/BadgeHelperService.php` — badge `needs_review`
  - `app/Http/Controllers/Owner/ReportController.php`, `Owner/DashboardController.php`, `app/Services/ProfitService.php`, `app/Services/AiContextService.php` — laporan pakai tanggal efektif
  - `resources/js/services/offlineDb.js`, `offlineSession.js`, `deviceId.js` — layer IndexedDB & siklus hidup data offline
  - `resources/js/composables/useCatalogCache.js`, `useOfflineQueue.js`, `useOnlineStatus.js`
  - `resources/js/Pages/Cashier/POS.vue` — cache katalog, enqueue offline, cash-only guard, pemicu sync
  - `public/sw.js` — `PAGE_CACHE` untuk shell POS, `CLEAR_PRIVATE_CACHES`, `CACHE_VERSION` → v2
  - `tests/Feature/OfflineSyncTest.php` — 30 test
- **Keputusan Arsitektur Penting:**
  - `channel` kolom string baru, **bukan** value tambahan pada enum `source` — `source` (pos|self_order) dan `channel` (online|offline) ortogonal; mengubah enum juga bermasalah di SQLite test suite.
  - Antrean transaksi ada di layer aplikasi (IndexedDB), **bukan** Background Sync di SW — kontrol penuh atas idempotensi & konflik. Background Sync tetap di luar scope (Keputusan C).
  - Outbox menyimpan `cashier_id` dan hanya di-flush oleh kasir yang membuatnya — server mengatribusikan penjualan ke user yang login saat sync, sehingga tanpa penjaga ini penjualan bisa masuk ke laporan shift kasir yang salah.
  - `PAGE_CACHE` (HTML ter-autentikasi) dibersihkan saat logout; outbox **tidak** — outbox berisi uang yang belum sampai ke server.
  - Open bill & pembayaran tagihan terbuka diblokir saat offline (memutasi baris server yang bisa disentuh till lain).
- **Catatan Migrasi:** `php artisan migrate` lalu `npm install && npm run build`. `CACHE_VERSION` sudah dinaikkan sehingga SW lama otomatis membuang cache-nya saat activate.

---

### [HOTFIX] Penomoran Ordered List Hasil AI (BL-001)
- **Tanggal:** 2026-07-15
- **Fase Terkait:** Di Luar Fase (backlog `BL-001`)
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** `renderMarkdown()` menutup `<ol>`/`<ul>` setiap kali menemui baris kosong. Karena output LLM sering menyisipkan baris kosong antar item list, tiap item jadi list terpisah dan browser menomori ulang mulai "1." (tampil "1. 1. 1."). Kini saat menemui baris kosong, renderer mengintip baris non-kosong berikutnya: bila masih item list dengan tipe sama, list dibiarkan terbuka (baris kosong = pemisah item), selain itu list ditutup seperti semula. List campuran (ol→ul) dan list yang diikuti paragraf tetap terpisah dengan benar.
- **Alasan:** Menampilkan penomoran hasil analisis AI sesuai maksud ("1. 2. 3.") tanpa menambah dependency markdown.
- **File Terdampak:**
  - `resources/js/Pages/Owner/AiAnalysis/Index.vue` — `renderMarkdown()` look-ahead pada baris kosong

---

### [SCHEMA] Idempotency `client_uuid` di Checkout (PWA Fase A)
- **Tanggal:** 2026-07-15
- **Fase Terkait:** Phase PWA (`docs/phases-2/PHASE-PWA_Offline-Transaction-Sync.md`) — Fase A
- **Dampak:** Migration | Model | Service | Request | Frontend
- **Breaking Change:** Tidak (kolom baru `nullable`; payload `client_uuid` opsional)
- **Deskripsi:** Menambah kolom `client_uuid` (nullable) di `transactions` dengan unique `(tenant_id, client_uuid)`. Client (`POS.vue`) membuat `crypto.randomUUID()` sekali per percobaan checkout dan mempertahankannya lintas retry; direset hanya setelah sukses. `TransactionService::checkout()` mendedup by `client_uuid` — request identik (retry jaringan flaky / double-submit) mengembalikan transaksi lama sebagai no-op alih-alih menggandakan. Berlaku untuk checkout normal maupun open bill. Ini fondasi idempotensi untuk offline-sync (Fase C) sekaligus menutup celah double-input online sekarang.
- **Alasan:** Guard `processing` di UI tidak melindungi dari retry jaringan; `client_uuid` memberi perlindungan double-input di lapisan jaringan/server.
- **File Terdampak:**
  - `database/migrations/2026_07_13_175345_add_client_uuid_to_transactions_table.php` — kolom + unique `(tenant_id, client_uuid)`
  - `app/Models/Transaction.php` — `client_uuid` di `$fillable`
  - `app/Services/TransactionService.php` — dedup by `client_uuid` di `checkout()`
  - `app/Http/Requests/StoreTransactionRequest.php` — validasi `client_uuid` → `nullable|uuid`
  - `resources/js/Pages/Cashier/POS.vue` — generate/kirim/reset UUID per checkout (normal + open bill)
  - `tests/Feature/Cashier/POSTest.php` — cakupan idempotensi

---

### [ADDITION] Edit Transaksi (Owner & Kasir) + Recalc Stok & Audit Trail
- **Tanggal:** 2026-07-14
- **Fase Terkait:** Phase EDIT-TX (`docs/phases-2/PHASE-EDIT-TX_Owner-Cashier-Transaction-Edit.md`)
- **Dampak:** Migration | Model | Service | Controller | Route | Frontend
- **Breaking Change:** Tidak (migrasi hanya menambah kolom/enum value)
- **Deskripsi:** Owner dapat mengedit transaksi `completed` kapan saja; kasir hanya untuk transaksi dalam shift laci kas miliknya yang masih terbuka (`created_at >= opened_at`, `closed_at = null`). Edit full (item, modifier, pembayaran) dengan stok dihitung ulang lewat **delta per varian** (satu `StockMovement` tipe `edit` per varian yang berubah), harga otoritatif dari DB, seluruhnya dalam satu `DB::transaction()` dengan `lockForUpdate()`. Setiap edit direkam di `transaction_edits` (before/after + alasan). UI berupa modal reusable yang membungkus `Components/Modal.vue` dan mereuse `ModifierModal`/`PaymentModal`.
- **Alasan:** Memberi jalan resmi koreksi salah input tanpa void+input ulang, sambil menjaga integritas stok, laporan, dan kepercayaan data lewat audit trail.
- **File Terdampak:**
  - `database/migrations/..._add_edit_type_to_stock_movements.php` — tambah enum value `edit`
  - `database/migrations/..._add_edit_columns_to_transactions.php` — `edited_at`, `edited_by`
  - `database/migrations/..._create_transaction_edits_table.php` — tabel audit before/after
  - `app/Models/{StockMovement,Transaction,TransactionEdit}.php` — konstanta, relasi `edits()`/`editor()`
  - `app/Services/TransactionEditService.php` — logika edit + guard `assertEditable()`
  - `app/Http/Requests/EditTransactionRequest.php`, `app/Http/Controllers/Cashier/TransactionEditController.php`
  - `routes/web.php` — `PUT /cashier/transactions/{transaction}` (`cashier.transactions.update`)
  - `app/Http/Controllers/Owner/ReportController.php`, `app/Http/Controllers/Cashier/POSController.php` — kirim katalog (deferred) + flag `can_edit`/riwayat edit
  - `resources/js/Components/TransactionEditModal.vue` + entry point di `Owner/Transactions/Detail.vue` & `Cashier/TransactionHistory.vue`
- **Catatan Migrasi:** Nilai enum `edit` juga ditambahkan ke migrasi create `stock_movements` agar konsisten pada driver SQLite (mengikuti pola penambahan `void`).

---

### [DECISION] SumoPod Jadi Provider AI Default (OpenAI-Compatible Gateway)
- **Tanggal:** 2026-07-11
- **Fase Terkait:** Phase-AI-2 (AI Engine)
- **Dampak:** Service | Config | Controller | Frontend
- **Breaking Change:** Tidak (default berubah `gemini` → `sumopod`; provider lama tetap didukung)
- **Deskripsi:** Menambah provider `sumopod` sebagai default. SumoPod adalah gateway AI OpenAI-compatible (persis OpenAI, hanya beda base URL `https://ai.sumopod.com/v1`), jadi `SumoPodProvider` cukup extend `OpenAiProvider` dan override endpoint chat completions. `OpenAiProvider` di-refactor agar endpoint & label provider bisa di-override lewat method `endpoint()`/`providerLabel()` (tanpa mengubah kontrak `AiProvider`). Satu API key SumoPod (`sk-...`) memberi akses ke banyak model (Anthropic, OpenAI, Gemini, DeepSeek, dll) via nama model.
- **Alasan:** Menyederhanakan setup BYOK & free-tier: satu penyedia + satu format key gaya OpenAI untuk banyak model, dengan budget limit di dashboard SumoPod. Tanpa dependency PHP baru — tetap via `Http` facade sesuai prinsip Phase-AI-2.
- **File Terdampak:**
  - `app/Services/Ai/SumoPodProvider.php` — provider baru (extends `OpenAiProvider`, override base URL ke `https://ai.sumopod.com/v1/chat/completions`)
  - `app/Services/Ai/OpenAiProvider.php` — endpoint & label di-ekstrak ke `endpoint()`/`providerLabel()`; props `protected`
  - `app/Services/Ai/AiProviderFactory.php` — `match` tambah cabang `sumopod`
  - `config/ai.php` — `default` → `sumopod`, tambah `models.sumopod`; `config/services.php` — `sumopod` key
  - `app/Http/Controllers/Owner/SettingsController.php` — validasi `ai_provider` tambah `sumopod`
  - `resources/js/Pages/Owner/Settings/Index.vue` — opsi provider "SumoPod" + label default
  - `.env.example` — `AI_DEFAULT_PROVIDER=sumopod`, `SUMOPOD_API_KEY`, `AI_SUMOPOD_MODEL`
  - `tests/Feature/Ai/AiProviderTest.php`, `tests/Feature/Ai/AiProviderFactoryTest.php` — test SumoPod (Http::fake + resolusi factory)
- **Catatan Migrasi:** Tidak ada migrasi. Isi `SUMOPOD_API_KEY` (untuk free tier: `AI_FREE_TIER_KEY` = key SumoPod) lalu `php artisan config:clear`. Detail penyedia di `docs/phases-2/PHASE-AI-2b_SumoPod-Provider.md`.

---

### [ADDITION] MCP Server: Data Bridge Read-Only untuk AI Client Milik Owner
- **Tanggal:** 2026-07-11
- **Fase Terkait:** Phase-AI-4 (MCP Server)
- **Dampak:** Route | Controller | Service | Config | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** MCP Server (`POST /mcp/business`) mengekspos data agregat tenant (penjualan, profit, menu) sebagai sumber data read-only untuk AI client milik owner (mis. Claude Desktop). Tiga tool: `get-sales-summary`, `get-profit`, `get-menu`. Akses via bearer token Sanctum yang dibuat/dicabut owner di Settings; endpoint dibatasi rate limit `mcp` (60/menit), owner-only, tenant-scoped otomatis, tanpa PII.
- **Alasan:** Pada MCP, LLM yang memanggil adalah client milik owner — app hanya menyediakan data dan tidak menanggung biaya LLM. Karena itu MCP **tidak** memakai kuota/free-tier AI aplikasi (beda dari AI-2/AI-3); cukup rate limit untuk cegah abuse. `ProductCatalogService` diekstrak agar query katalog dipakai bersama MCP & API consumer.
- **File Terdampak:**
  - `routes/ai.php` — `Mcp::web('/mcp/business', SapiBusinessServer::class)` + middleware `auth:sanctum`, `tenant.api`, `role:owner`, `throttle:mcp`
  - `app/Mcp/Servers/SapiBusinessServer.php`, `app/Mcp/Tools/*` — server + `BusinessDataTool` (base) + 3 tool
  - `app/Services/ProductCatalogService.php` — katalog produk bersama; `app/Http/Controllers/Api/V1/ApiProductController.php` — refactor memakai service
  - `app/Providers/AppServiceProvider.php` — RateLimiter `mcp`
  - `app/Http/Controllers/Owner/SettingsController.php`, `resources/js/Pages/Owner/Settings/Index.vue`, `app/Http/Middleware/HandleInertiaRequests.php` — generate/cabut token MCP (plaintext flash sekali)
- **Catatan Migrasi:** Tidak ada migrasi. Detail & backlog tools di `docs/phases-2/PHASE-AI-4_MCP-Server.md`.

---

### [DECISION] RunAiAnalysisJob Autentikasi sebagai Pemilik Analisis (Tenant Scoping di Queue)
- **Tanggal:** 2026-07-10
- **Fase Terkait:** Phase-AI-3 (AI Analysis)
- **Dampak:** Job | Service
- **Breaking Change:** Tidak
- **Deskripsi:** `RunAiAnalysisJob` memanggil `Auth::setUser($analysis->user)` sebelum membangun konteks, lalu `Auth::forgetGuards()` di blok `finally`. Ini menyimpang dari contoh kode awal di dokumen `PHASE-AI-3` yang tidak meng-set auth di dalam Job.
- **Alasan:** `AiContextService` dan `ProfitService` bergantung pada `TenantScope` global yang berbasis `auth()`. Karena queue job berjalan tanpa sesi HTTP, tanpa autentikasi eksplisit query konteks tidak ter-scope ke tenant yang benar (berisiko membaca/menggabungkan data lintas tenant). Meng-set user pemilik analisis memastikan seluruh agregasi ter-scope ke tenant tersebut; `forgetGuards()` mencegah kebocoran state auth antar job pada worker yang sama.
- **File Terdampak:**
  - `app/Jobs/RunAiAnalysisJob.php` — `Auth::setUser()` sebelum `buildContext()`, `Auth::forgetGuards()` di `finally`
  - `docs/phases-2/PHASE-AI-3_AI-Analysis.md` — contoh kode Job disinkronkan dengan implementasi
- **Catatan Migrasi:** Tidak ada migrasi. Perilaku hanya relevan saat worker queue memproses beberapa job dari tenant berbeda.

---

### [ADDITION] Public API Reference Page for Mobile POS
- **Tanggal:** 2026-05-29
- **Fase Terkait:** Cross-Phase (Phase-2 Mobile API / Public Docs)
- **Dampak:** View | Controller | Route
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan halaman dokumentasi publik di `/api-docs` untuk referensi endpoint Mobile POS, autentikasi Sanctum, contoh request/response JSON, dan batasan akses per role.
- **Alasan:** Mengurangi ketergantungan pada dokumentasi manual terpisah dan memberi sumber referensi tunggal yang bisa diakses langsung dari aplikasi saat integrasi client mobile berjalan.
- **File Terdampak:**
  - `app/Http/Controllers/Public/LandingController.php` — tambah action `docs()` untuk halaman dokumentasi
  - `routes/web.php` — route baru `/api-docs`
  - `resources/views/public/api-docs.blade.php` — halaman referensi API publik
- **Catatan Migrasi:** Tidak ada migrasi database. Pastikan aset frontend/Vite tersedia jika style global dipakai di halaman dokumentasi.

---

### [DECISION] API Consumer Dipindahkan ke Prefix `/api/v1`
- **Tanggal:** 2026-05-29
- **Fase Terkait:** Cross-Phase (Self Order / Mobile API)
- **Dampak:** Controller | Route | API Contract
- **Breaking Change:** Ya
- **Deskripsi:** Semua endpoint consumer yang sebelumnya berada langsung di bawah `/api/*` dipindahkan ke namespace versi pertama di `/api/v1/*`. Controller terkait juga dipindahkan dari `App\Http\Controllers\Api\...` ke `App\Http\Controllers\Api\V1\...` agar struktur implementasi mengikuti kontrak route versi.
- **Alasan:** Menyiapkan kompatibilitas jangka panjang untuk perubahan contract API tanpa mengganggu webhook eksternal atau endpoint versi lama yang nantinya perlu dipelihara paralel.
- **File Terdampak:**
  - `routes/api.php` — regroup route consumer di bawah prefix `v1`
  - `app/Http/Controllers/Api/V1/ApiProductController.php` — lokasi baru endpoint daftar produk
  - `app/Http/Controllers/Api/V1/ApiOrderController.php` — lokasi baru endpoint self-order dan fulfillment
  - `app/Http/Controllers/Api/V1/Mobile/MobileAuthController.php` — lokasi baru auth mobile
  - `app/Http/Controllers/Api/V1/Mobile/MobileTenantController.php` — lokasi baru profil tenant mobile
  - `app/Http/Controllers/Api/V1/Mobile/MobileCashDrawerController.php` — lokasi baru operasi cash drawer mobile
  - `app/Http/Controllers/Api/V1/Mobile/MobileTransactionController.php` — lokasi baru operasi transaksi mobile
  - `app/Http/Controllers/Api/ApiProductController.php` — controller lama dihapus
  - `app/Http/Controllers/Api/ApiOrderController.php` — controller lama dihapus
  - `app/Http/Controllers/Api/Mobile/*.php` — controller mobile lama dihapus
- **Catatan Migrasi:** Update semua consumer/client dari path lama ke path baru, misalnya `/api/mobile/...` menjadi `/api/v1/mobile/...`, `/api/products` menjadi `/api/v1/products`, dan `/api/orders` menjadi `/api/v1/orders`. Endpoint `POST /api/xendit/webhook` tetap non-versioned.

---

### [ADDITION] Owner Modifier Quick Settings, Cashier Account Menu, and UI Stability Fixes
- **Tanggal:** 2026-05-28
- **Fase Terkait:** Cross-Phase (Phase-3 POS / Phase-5 Dashboard)
- **Dampak:** Frontend | Controller | Route | View
- **Breaking Change:** Tidak
- **Deskripsi:** Penambahan dan perbaikan terfokus pada operasional harian:
  1. Owner kini bisa mengubah pengaturan modifier group (`is_required`, `is_multiple`) langsung dari halaman list tanpa masuk form edit terpisah.
  2. Detail modifier group diperluas dengan daftar produk yang menggunakan modifier dan ringkasan visual yang lebih jelas.
  3. Topbar kasir diperbarui dengan dropdown akun (nama, email, dan logout) agar navigasi lebih ringkas di layar sempit.
  4. Date picker dipindah ke `Teleport` dengan posisi `fixed` adaptif agar tidak terpotong container scroll dan tetap terlihat saat resize/scroll.
  5. Owner layout mendapat custom scrollbar pada sidebar dan area konten untuk konsistensi visual.
  6. Penambahan meta CSRF token pada layout app untuk kompatibilitas request client-side yang membutuhkan token dari DOM.
- **Alasan:** Mengurangi friction pada konfigurasi modifier, meningkatkan kejelasan konteks data owner, menstabilkan komponen input tanggal di berbagai layout, dan memperbaiki ergonomi navigasi kasir.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/ModifierController.php` — tambah endpoint update settings + preload relasi produk
  - `routes/web.php` — route patch untuk `modifiers.settings`
  - `resources/js/Pages/Owner/Modifiers/Index.vue` — quick toggle settings + section detail produk pemakai modifier
  - `resources/js/Components/CashierTopbar.vue` — dropdown akun + logout di menu profil
  - `resources/js/Components/DatePicker.vue` — teleport popup + kalkulasi posisi viewport-aware
  - `resources/js/Layouts/OwnerLayout.vue` — penerapan kelas scrollbar custom
  - `resources/css/app.css` — style scrollbar sidebar/main
  - `resources/views/app.blade.php` — meta `csrf-token`
- **Catatan Migrasi:** Tidak ada migrasi database. Jalankan `npm run build`/`npm run dev` untuk memverifikasi aset frontend setelah update komponen.

---

### [ADDITION] UI System Refresh, Cashier Flow Guard, and Shared Components
- **Tanggal:** 2026-05-28
- **Fase Terkait:** Cross-Phase (Phase-3 POS / Phase-5 Dashboard)
- **Dampak:** Frontend | Controller | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Pembaruan besar pada antarmuka dan pengalaman operasional:
  1. Penerapan design token semantik global (primary/success/warning/destructive) dan font aplikasi.
  2. Penambahan komponen reusable baru: `CashierTopbar`, `DatePicker`, dan composable `useFlash`.
  3. Refactor komponen shared dan halaman owner/cashier agar konsisten dengan sistem tema baru.
  4. Guard backend alur kas: owner tidak lagi diwajibkan membuka sesi kas dan tidak dapat mengelola cash drawer kasir.
  5. Penyegaran halaman login untuk UX lebih modern dan konsisten dengan tema aplikasi.
- **Alasan:** Menyatukan visual language aplikasi, mengurangi inkonsistensi UI lintas halaman, memperjelas pemisahan alur owner vs kasir, dan meningkatkan kecepatan operasional kasir.
- **File Terdampak:**
  - `resources/css/app.css` — token warna semantik, radius, mapping warna tema
  - `resources/views/app.blade.php` — preload font Plus Jakarta Sans + JetBrains Mono
  - `resources/js/Components/CashierTopbar.vue` — topbar kasir reusable
  - `resources/js/Components/DatePicker.vue` — date picker custom untuk filter laporan
  - `resources/js/composables/useFlash.js` — state notifikasi global
  - `resources/js/Components/*.vue` — migrasi style komponen shared ke token semantik
  - `resources/js/Pages/Cashier/*.vue` — perbaikan flow kasir, notifikasi, dan wording
  - `resources/js/Pages/Owner/**/*.vue` — harmonisasi UI dashboard/management owner
  - `resources/js/Pages/Auth/Login.vue` — redesign halaman login
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — pembatasan sesi kas untuk role kasir
  - `app/Http/Controllers/Cashier/POSController.php` — owner bypass kewajiban buka kas
- **Catatan Migrasi:** Tidak ada migrasi database. Disarankan jalankan `npm run build` untuk validasi aset frontend.

---

### [ADDITION] Mobile API Phase 2: Operasi Kasir Lengkap
- **Tanggal:** 2026-05-25
- **Fase Terkait:** Phase-2 (Mobile API Enhancement)
- **Dampak:** Controller | Route | API Contract
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan endpoint mobile untuk operasional kasir harian yang lebih lengkap, meliputi buka kas, tutup kas, ringkasan sesi kas, daftar transaksi, pembayaran open bill, dan void transaksi.
- **Alasan:** Melengkapi alur operasional kasir mobile end-to-end agar proses shift kas dan lifecycle transaksi dapat ditangani penuh dari mobile client.
- **File Terdampak:**
  - `app/Http/Controllers/Api/Mobile/MobileCashDrawerController.php` — tambah method `open`, `close`, dan `summary` dengan validasi serta perhitungan expected amount/difference.
  - `app/Http/Controllers/Api/Mobile/MobileTransactionController.php` — tambah method `index`, `pay`, dan `void` untuk list transaksi, pembayaran open bill, serta pembatalan transaksi.
  - `routes/api.php` — registrasi route baru untuk operasi kas drawer dan transaksi (group role cashier/owner dan owner-only void).
- **Catatan Migrasi:** Tidak ada migrasi database baru. Pastikan role middleware `role:cashier,owner` dan `role:owner` aktif serta payment method cash memiliki `type = cash` untuk kalkulasi tutup kas.

---

### [ADDITION] UX POS: Auto-Amount Non-Cash, Thermal Receipt, Open Bill Customer Name
- **Tanggal:** 2026-03-07
- **Fase Terkait:** Phase-3 POS / Phase-5 UX
- **Dampak:** Frontend | Controller | Request
- **Breaking Change:** Tidak
- **Deskripsi:** Tiga improvement UX pada alur kasir:
  1. **PaymentModal** — metode non-cash (QRIS/Transfer) kini otomatis mengisi nominal sesuai total transaksi tanpa perlu input manual. Metode cash tetap menggunakan input nominal + tombol denominasi cepat. Kembalian hanya muncul jika ada metode cash.
  2. **ReceiptModal** — desain ulang struk cetak menjadi format thermal 80mm standar, menampilkan nama perusahaan (tenant), info transaksi (no., tanggal, waktu, kasir, pelanggan), item + modifier, subtotal, total, pembayaran per metode, kembali, dan footer.
  3. **Open Bill** — tambah modal input nama pelanggan saat menekan "Open Bill". Nama pelanggan ditampilkan pada kartu open bill di sidebar dan tercatat di struk.
- **Alasan:** Meningkatkan kecepatan kasir (tidak perlu ketik nominal QRIS), struk lebih profesional dan siap cetak ke printer thermal, serta mempermudah identifikasi pesanan open bill per pelanggan/meja.
- **File Terdampak:**
  - `resources/js/Components/PaymentModal.vue` — smart cash vs non-cash input, `onMethodChange` auto-amount, kembalian conditional
  - `resources/js/Components/ReceiptModal.vue` — full redesign thermal receipt, prop `tenantName`, format mono-font, section header/info/items/total/payment/footer
  - `resources/js/Pages/Cashier/POS.vue` — prop `tenantName`, modal input nama pelanggan untuk open bill (`showOpenBillNameModal`, `openBillCustomerName`, `confirmSaveOpenBill`), tampilkan nama di kartu open bill
  - `app/Http/Controllers/Cashier/POSController.php` — pass `tenantName` ke Inertia, load relasi `user:id,name` pada `lastTransaction` sebelum flash
  - `app/Http/Requests/StoreTransactionRequest.php` — tambah rule `customer_name: nullable|string|max:100`
- **Catatan Migrasi:** Tidak perlu migrasi. Kolom `customer_name` sudah ada di tabel `transactions`.

---

### [ADDITION] Dashboard Owner: Badge Self Order pada Transaksi Terbaru
- **Tanggal:** 2026-03-07
- **Fase Terkait:** Phase-5 Dashboard
- **Dampak:** Frontend | Controller
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan kolom `source` pada query `recentTransactions` di DashboardController dan badge "Self Order" (bg-blue) di samping kode transaksi pada tabel Transaksi Terbaru di halaman Dashboard Owner.
- **Alasan:** Membedakan visually transaksi dari channel POS vs Self Order langsung dari dashboard.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/DashboardController.php` — tambah `'source'` di `get([...])`
  - `resources/js/Pages/Owner/Dashboard.vue` — wrap `tx.code` + `<span v-if="tx.source === 'self_order'>` dalam flex div

---


- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase
- **Dampak:** Dependency | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan package `barryvdh/laravel-ide-helper` (sebagai dependensi dev) untuk mengatasi issue property & magic method Laravel yang sering dianggap error oleh IDE/Editor (VS Code, PhpStorm).
- **Alasan:** Meningkatkan DX (Developer Experience) agar editor dapat mengenali model properti, facades, dan magic method Laravel lainnya dengan tepat.
- **File Terdampak:**
  - `composer.json` — Tambahan `barryvdh/laravel-ide-helper` di require-dev.
  - `.gitignore` — Ignore file hasil generate (`_ide_helper.php`, `.phpstorm.meta.php`, `_ide_helper_models.php`).
- **Catatan Migrasi:** Jika IDE masih complain, jalankan perintah `php artisan ide-helper:generate`, `php artisan ide-helper:meta`, dan `php artisan ide-helper:models -N`.

---

### [ADDITION] Integrasi Dependency Xendit SDK (Persiapan Payment Gateway)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase
- **Dampak:** Dependency | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan package `xendit/xendit-php` ke dependency aplikasi dan konfigurasi service `xendit` di `config/services.php` (`secret_key`, `webhook_token`).
- **Alasan:** Persiapan integrasi payment gateway Xendit untuk kebutuhan pembayaran digital dan webhook settlement.
- **File Terdampak:**
  - `composer.json` — tambah dependency `xendit/xendit-php`
  - `composer.lock` — lock file update setelah install package
  - `config/services.php` — tambah config `xendit.secret_key` dan `xendit.webhook_token`
- **Catatan Migrasi:** Tambahkan env `XENDIT_SECRET_KEY` dan `XENDIT_WEBHOOK_TOKEN` pada environment yang digunakan.

---

### [RECONCILE] Transaction Status Naming
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Cross-Phase (Phase-1 migration, Phase-3 logic)
- **Dampak:** Migration | Model | Service
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Status transaksi diubah dari v1.0 naming ke naming yang lebih jelas
- **Alasan:** `open/paid/cancelled` kurang deskriptif. `pending/completed/voided` lebih konsisten dengan industry standard dan lebih jelas untuk developer baru.
- **Perubahan:**
  | v1.0 (lama) | Final (baru) | Keterangan |
  |---|---|---|
  | `open` | `pending` | Transaksi dibuat, belum dibayar |
  | `paid` | `completed` | Pembayaran diterima, stok sudah dikurangi |
  | `cancelled` | `voided` | Transaksi dibatalkan |
- **File Terdampak:**
  - `database/migrations/xxxx_create_transactions_table.php` — ENUM values
  - `app/Models/Transaction.php` — status constants
  - `app/Services/TransactionService.php` — status transitions

---

### [RECONCILE] Stock Movement Type Naming
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Cross-Phase (Phase-1 migration, Phase-4 logic)
- **Dampak:** Migration | Model | Service
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Tipe stock movement diubah dari generic ke deskriptif
- **Alasan:** `in/out` terlalu generik, tidak langsung menjelaskan konteks bisnis. `sale/restock/adjustment` langsung menjelaskan alasan perubahan stok.
- **Perubahan:**
  | v1.0 (lama) | Final (baru) | Keterangan |
  |---|---|---|
  | `in` | `restock` | Barang masuk dari supplier/restock |
  | `out` | `sale` | Stok keluar karena transaksi penjualan |
  | `adjustment` | `adjustment` | Koreksi manual (positif/negatif) |
- **File Terdampak:**
  - `database/migrations/xxxx_create_stock_movements_table.php` — ENUM values
  - `app/Models/StockMovement.php` — type constants
  - `app/Services/StockService.php` — movement type logic

---

### [DECISION] Auth Method: Manual → Laravel Sanctum (SPA Mode)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Config | Middleware | Controller
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Mekanisme auth diubah dari full manual ke Laravel Sanctum SPA mode
- **Alasan:** Sanctum SPA mode tetap cookie-based (tidak perlu manage token), sudah terintegrasi dengan Laravel, dan kompatibel sempurna dengan Inertia.js. Lebih secure (CSRF protection built-in) tanpa overhead tambahan dibanding manual auth.
- **Implementasi:**
  - Install: `composer require laravel/sanctum` (sudah included di Laravel 12)
  - Config: `config/sanctum.php` — set stateful domains
  - Middleware: Sanctum middleware di `api` routes jika dibutuhkan nanti
  - Auth flow tetap session-based, Sanctum hanya menambah layer keamanan
- **File Terdampak:**
  - `config/sanctum.php` — konfigurasi
  - `app/Http/Controllers/Auth/AuthController.php` — auth logic
  - `bootstrap/app.php` — middleware registration

---

### [SCHEMA] Penambahan Tabel `cash_drawers`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation (migration), Phase-3 POS (logic)
- **Dampak:** Migration | Model | Controller | Service
- **Breaking Change:** Tidak (tabel baru)
- **Deskripsi:** Tabel baru untuk sesi kas per shift kasir, sesuai v1.1 Section 1
- **Detail:** Lihat `docs/phases/PHASE-1_Foundation.md` — Section Database

---

### [SCHEMA] Penambahan Kolom `expiry_date` di `product_variants`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation (migration), Phase-5 Badges (logic)
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** Kolom expiry_date untuk mendukung badge "Potensi Expired", sesuai v1.1 Section 2

---

### [SCHEMA] Penambahan `deleted_at` (SoftDeletes) di 6 Tabel
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** SoftDeletes ditambahkan ke: `categories`, `products`, `product_variants`, `modifier_groups`, `modifiers`, `payment_methods`. Sesuai v1.1 Section 7.
- **Alasan:** Menjaga integritas data historis transaksi. Record yang dihapus user tidak benar-benar hilang dari database.

---

### [DECISION] `expected_amount` Cash Drawer = Semua Payment Method
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Service
- **Breaking Change:** Tidak
- **Deskripsi:** `expected_amount` di `cash_drawers` dihitung dari **semua** metode pembayaran (cash + QRIS + transfer), bukan hanya cash.
- **Alasan:** Keputusan bisnis — owner ingin melihat total expected revenue di sesi tersebut, bukan hanya uang fisik.
- **Catatan:** Di summary tutup kas, tetap tampilkan rekap per metode pembayaran agar owner bisa reconcile masing-masing channel.

---

### [ADDITION] Tenant Isolation via Global Scope + Trait
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Model | Trait
- **Breaking Change:** Tidak
- **Deskripsi:** Implementasi `BelongsToTenant` trait + `TenantScope` global scope untuk auto-filter & auto-assign tenant_id. Sesuai v1.1 Section 6.

---

### [ADDITION] Image Upload Service dengan Konversi WEBP
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-2 Master Data CRUD
- **Dampak:** Service | Config | Dependency
- **Breaking Change:** Tidak
- **Deskripsi:** ImageService untuk upload & konversi gambar produk ke WEBP. Dependency: `intervention/image`. Sesuai v1.1 Section 8.

---

### [ADDITION] Flash `lastTransaction` via Inertia untuk Receipt Modal
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Middleware | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `flash.lastTransaction` di `HandleInertiaRequests` middleware agar data transaksi terakhir yang berhasil bisa dikirim ke frontend untuk ditampilkan di ReceiptModal (struk). Phase 3 doc tidak secara eksplisit mendefinisikan mekanisme passing data transaksi ke receipt.
- **Alasan:** Inertia redirect-back tidak bisa mengirim data object secara langsung. Flash session digunakan sebagai bridge untuk menampilkan struk setelah checkout sukses.
- **File Terdampak:**
  - `app/Http/Middleware/HandleInertiaRequests.php` — tambah `lastTransaction` di flash share
  - `app/Http/Controllers/Cashier/POSController.php` — `->with('lastTransaction', ...)` di store method

---

### [ADDITION] Redirect Tutup Kas ke Summary Page
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Route
- **Breaking Change:** Tidak
- **Deskripsi:** Setelah tutup kas, kasir langsung di-redirect ke halaman `CashDrawerSummary` (rekap sesi), bukan kembali ke halaman `CashDrawer.index`. Phase 3 doc menunjukkan redirect ke `cash-drawer.index`.
- **Alasan:** UX lebih baik — kasir langsung melihat rekap sesi lengkap (per payment method, selisih) setelah tutup kas.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `close()` redirect ke `cash-drawer.summary`

---

### [ADDITION] Transaction Count di Cash Drawer Summary
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `transactionCount` (jumlah transaksi completed dalam sesi) ke data yang dikirim ke `CashDrawerSummary.vue`. Tidak ada di Phase 3 doc.
- **Alasan:** Informasi tambahan yang berguna untuk rekap shift kasir.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `summary()` menghitung dan mengirim `transactionCount`

---

### [ADDITION] Dependency chart.js + vue-chartjs untuk Dashboard Chart
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `chart.js` dan `vue-chartjs` sebagai dependency npm untuk menampilkan bar chart trend pendapatan 7 hari di halaman Dashboard owner. Phase 5 doc menyebutkan chart sebagai "opsional, bisa pakai chart.js" tanpa mendefinisikan dependency secara eksplisit.
- **Alasan:** Dipilih atas permintaan user. Chart.js + vue-chartjs ringan dan memberikan visualisasi data yang interaktif.
- **File Terdampak:**
  - `package.json` — tambah dependency `chart.js`, `vue-chartjs`
  - `resources/js/Components/DailyChart.vue` — komponen chart baru

---

### [ADDITION] Navigasi Sidebar Owner Ditambah Menu Phase 5
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan 3 menu navigasi baru di sidebar OwnerLayout: Laporan Harian, Transaksi, Sesi Kas. Serta menambahkan 3 ikon SVG baru (report, receipt, cash) untuk navigasi tersebut. Phase 5 doc tidak secara eksplisit mendefinisikan perubahan pada layout/navigasi.
- **Alasan:** Halaman-halaman baru Phase 5 perlu bisa diakses via navigasi sidebar agar UX konsisten dengan halaman owner lainnya.
- **File Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue` — tambah navigation items + ikon SVG

---

### [HOTFIX] BadgeHelperService Dead Stock Query Bug
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (ditemukan saat testing)
- **Dampak:** Service
- **Breaking Change:** Tidak
- **Deskripsi:** Query dead stock badge menggunakan `transactionItems.created_at` yang tidak ada (tabel `transaction_items` memiliki `$timestamps = false`). Diperbaiki dengan query melalui relasi `transactionItems.transaction` dan filter berdasarkan `transactions.created_at` serta `transactions.status = completed`.
- **Alasan:** Bug — query selalu gagal karena kolom `created_at` tidak ada di tabel `transaction_items`, sehingga badge dead stock tidak pernah akurat.
- **File Terdampak:**
  - `app/Services/BadgeHelperService.php` — perbaikan `whereDoesntHave` query dari `transactionItems` ke `transactionItems.transaction`

---

### [HOTFIX] CategoryController Soft Delete Tidak Nullify product.category_id
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (ditemukan saat testing)
- **Dampak:** Controller
- **Breaking Change:** Tidak
- **Deskripsi:** Komentar di controller mengklaim `nullOnDelete` DB constraint akan handle nullify `category_id` produk saat kategori dihapus. Namun soft delete tidak memicu DB-level foreign key constraint. Diperbaiki dengan menambahkan manual `$category->products()->update(['category_id' => null])` sebelum soft delete.
- **Alasan:** Bug — produk tetap mereferensi kategori yang sudah di-soft-delete, menyebabkan data inkonsisten.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/CategoryController.php` — tambah manual nullify sebelum `$category->delete()`

---

### [SCHEMA] Penambahan 'void' di ENUM stock_movements.type (Create Migration)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (fix untuk SQLite test compatibility)
- **Dampak:** Migration
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `'void'` ke ENUM di migration create `stock_movements` agar SQLite CHECK constraint mengizinkan tipe void. Migration ALTER tetap ada untuk MySQL production yang sudah menjalankan migration create sebelumnya.
- **Alasan:** SQLite (test database) membuat CHECK constraint dari ENUM. Migration ALTER yang skip SQLite menyebabkan 'void' tidak dikenal di test environment.
- **File Terdampak:**
  - `database/migrations/2026_03_06_000015_create_stock_movements_table.php` — ENUM values ditambah `'void'`
  - `database/migrations/2026_03_06_100002_add_void_type_to_stock_movements.php` — tetap ada driver-aware skip untuk SQLite

---

### [ADDITION] HasFactory Trait pada Semua Model
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA
- **Dampak:** Model
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `HasFactory` trait ke semua model yang belum memilikinya: `Transaction`, `CashDrawer`, `StockMovement`, `Tenant`, `Category`, `Product`, `ProductVariant`, `ModifierGroup`, `Modifier`, `PaymentMethod`, `TransactionPayment`. Diperlukan agar `Model::factory()` dapat digunakan di test suite.
- **Alasan:** Prasyarat untuk factory-based testing di Pest PHP.
- **File Terdampak:**
  - `app/Models/Transaction.php` — tambah `use HasFactory`
  - `app/Models/CashDrawer.php` — tambah `use HasFactory`
  - `app/Models/StockMovement.php` — tambah `use HasFactory`
  - (dan 8 model lain yang sudah ditambahkan sebelumnya)

---

### [ADDITION] Fitur Open Bill (Simpan Pesanan Tanpa Bayar)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Service | Route | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Kasir kini bisa menyimpan pesanan sebagai "Open Bill" (status `pending`) tanpa langsung membayar. Stok dikurangi saat bill dibuat untuk mencegah overselling. Pembayaran dilakukan kemudian dari panel Open Bill di halaman POS. Phase 3 doc tidak mendefinisikan fitur open bill.
- **Alasan:** Permintaan user — di banyak skenario F&B, pelanggan pesan dulu dan bayar belakangan (makan di tempat, tab, dll). Stok tetap dikurangi di awal agar tidak terjual ganda.
- **File Terdampak:**
  - `app/Services/TransactionService.php` — `checkout()` menerima flag `is_open_bill`; method baru `payOpenBill()` untuk menyelesaikan pembayaran
  - `app/Http/Requests/StoreTransactionRequest.php` — validasi `payments` jadi nullable + field `is_open_bill`
  - `app/Http/Controllers/Cashier/POSController.php` — method `payOpenBill()`, load `openBills` untuk frontend
  - `routes/web.php` — route `POST /cashier/transactions/{transaction}/pay`
  - `resources/js/Pages/Cashier/POS.vue` — panel Open Bill, tombol "Open Bill", flow pembayaran pending

---

### [SCHEMA] Penambahan Kolom `notes` di `transaction_items`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** Kolom `notes` (text, nullable) ditambahkan ke tabel `transaction_items` untuk menyimpan catatan per item pesanan (contoh: "less sugar", "no ice"). Tidak ada di Phase 3 doc.
- **Alasan:** Permintaan user — support catatan khusus per item pesanan, umum di bisnis F&B.
- **File Terdampak:**
  - `database/migrations/2026_03_06_200001_add_notes_to_transaction_items_table.php` — migration baru
  - `app/Models/TransactionItem.php` — `notes` ditambahkan ke `$fillable`
- **Catatan Migrasi:** Jalankan `php artisan migrate` untuk menambah kolom.

---

### [ADDITION] Catatan (Notes) per Item di Halaman POS
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Frontend | Service
- **Breaking Change:** Tidak
- **Deskripsi:** Setiap item di cart kini bisa memiliki catatan individual. Item dengan produk & modifier sama tapi catatan berbeda ditampilkan sebagai baris terpisah di cart. Catatan ditampilkan di struk (ReceiptModal). Phase 3 doc tidak mendefinisikan fitur notes.
- **Alasan:** Permintaan user — catatan per item umum di POS F&B untuk instruksi khusus dapur/barista.
- **File Terdampak:**
  - `resources/js/Components/CartItem.vue` — toggle notes + input text per item
  - `resources/js/Pages/Cashier/POS.vue` — dedup cart mempertimbangkan notes, handler `updateCartNotes()`
  - `resources/js/Components/ReceiptModal.vue` — tampilkan notes item di struk
  - `app/Services/TransactionService.php` — simpan `notes` per item ke database

---

### [ADDITION] Halaman Riwayat Transaksi Kasir
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Route | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Halaman baru `Cashier/TransactionHistory` untuk kasir melihat riwayat transaksi mereka sendiri, dengan filter status dan tanggal, serta tombol cetak ulang struk. Phase 3 doc tidak mendefinisikan halaman history khusus untuk kasir.
- **Alasan:** Permintaan user — kasir perlu bisa melihat & mencetak ulang struk transaksi sebelumnya tanpa harus minta akses owner.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/POSController.php` — method `history()` dengan filter & pagination
  - `routes/web.php` — route `GET /cashier/transactions`
  - `resources/js/Pages/Cashier/TransactionHistory.vue` — halaman baru
  - `resources/js/Pages/Cashier/POS.vue` — link "Riwayat" di top bar

---

### [DECISION] Reversal: `expected_amount` Cash Drawer = Cash Only (Bukan Semua Payment)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Frontend
- **Breaking Change:** Ya (mengubah behavior kalkulasi expected_amount)
- **Deskripsi:** **Membalik keputusan sebelumnya.** `expected_amount` di `cash_drawers` kini dihitung hanya dari pembayaran tunai (cash) dikurangi kembalian, bukan dari semua metode pembayaran. Formula baru: `opening_amount + Σ(cash payments) - Σ(change given)`.
- **Alasan:** Permintaan user — `expected_amount` seharusnya merepresentasikan uang fisik yang diharapkan ada di laci kas. Pembayaran QRIS dan transfer tidak masuk ke laci kas fisik. Keputusan sebelumnya (`expected_amount = semua payment method`) dibatalkan.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `close()` query hanya `TransactionPayment` with `paymentMethod.type = 'cash'`, kurangi `change_amount`
  - `resources/js/Pages/Cashier/CashDrawerSummary.vue` — label diubah dari "Expected Amount" ke "Expected Cash (uang tunai di laci)"

---

### [ADDITION] Perbaikan Payment Modal (Lebar, Sticky Total, Denominasi, Format Angka)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Beberapa perbaikan UX pada PaymentModal: (1) Modal diperlebar dari `max-w-md` ke `max-w-lg`, (2) Total Belanja menjadi sticky headline yang selalu terlihat saat scroll, (3) Tombol denominasi: "Uang Pas" + "+20rb" + "+50rb" + "+100rb" yang kumulatif, (4) Input amount menggunakan `type="text" inputmode="numeric"` dengan auto-format ribuan (titik).
- **Alasan:** Permintaan user — modal terlalu kecil, total tidak terlihat saat scroll, dan kasir butuh shortcut denominasi untuk mempercepat proses pembayaran.
- **File Terdampak:**
  - `resources/js/Components/PaymentModal.vue` — semua perubahan di atas

---

### [ADDITION] Sidebar Owner Grouped dengan Dropdown Collapsible
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Navigasi sidebar OwnerLayout diubah dari flat list ke grouped sections: Dashboard (standalone), "Atur Menu" (Kategori, Produk, Stok, Modifier), "Keuangan" (Laporan Harian, Transaksi, Sesi Kas, Pembayaran). Setiap group memiliki dropdown collapsible dengan chevron animasi. Group label ter-highlight saat salah satu child-nya aktif. Juga memperbaiki issue sidebar yang memiliki empty space di bawah yang bisa di-scroll.
- **Alasan:** Permintaan user — navigasi flat terlalu panjang dan sulit dinavigasi. Grouping membantu organisasi menu berdasarkan domain bisnis. Perbaikan scroll menghilangkan empty space yang tidak perlu.
- **File Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue` — rewrite navigasi dari flat array ke `sidebarGroups` array, collapsible dropdown per group, `overflow-y-auto` hanya pada nav

---

### [SCHEMA] Penambahan Kolom Self-Order & Fulfillment di `transactions`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase (Self Order Feature)
- **Dampak:** Migration | Model | Service | Controller | Route | Config
- **Breaking Change:** Tidak (kolom baru, nullable/default)
- **Deskripsi:** Menambahkan 5 kolom baru ke tabel `transactions` untuk mendukung fitur Self Order via Telegram + Xendit payment dan sistem fulfillment tracking:
  - `source` (enum: pos, self_order) — asal pesanan
  - `order_type` (enum: dine_in, pickup) — tipe pesanan
  - `fulfillment_status` (enum nullable: waiting, preparing, ready, done) — status penyajian
  - `customer_name` (varchar 100, nullable) — nama customer terstruktur
  - `table_number` (varchar 10, nullable) — nomor meja free text
- **Alasan:** Mendukung multi-channel order (POS + Telegram) dengan tracking status penyajian per pesanan. Fulfillment dipisahkan dari payment status untuk fleksibilitas (bayar dulu / sajikan dulu).
- **File Terdampak:**
  - `database/migrations/2026_03_06_300001_add_selforder_and_fulfillment_to_transactions.php` — migration baru
  - `app/Models/Transaction.php` — constants baru (SOURCE_*, ORDER_TYPE_*, FULFILLMENT_*), $fillable, helper methods (isSelfOrder, hasFulfillmentTracking, advanceFulfillment)
  - `database/factories/TransactionFactory.php` — state methods baru (selfOrder, withFulfillment, pickup)
- **Catatan Migrasi:** Jalankan `php artisan migrate`

---

### [ADDITION] API Layer untuk Self Order (Sanctum + Xendit)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase (Self Order Feature)
- **Dampak:** Controller | Route | Config | Service
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan REST API layer untuk integrasi Self Order via n8n/Telegram:
  - `GET /api/products` — daftar produk aktif + variant tersedia (Sanctum auth)
  - `POST /api/orders` — buat self-order + generate Xendit Invoice (Sanctum auth)
  - `PATCH /api/orders/{transaction}/fulfillment` — advance fulfillment status (Sanctum auth)
  - `POST /api/xendit/webhook` — handle Xendit payment callback (token auth)
- **Alasan:** Dibutuhkan sebagai backend untuk n8n workflow yang menerima order dari Telegram bot.
- **File Terdampak:**
  - `routes/api.php` — file baru, semua API routes
  - `bootstrap/app.php` — registrasi api routes
  - `app/Http/Controllers/Api/ApiProductController.php` — controller baru
  - `app/Http/Controllers/Api/ApiOrderController.php` — controller baru
  - `app/Http/Controllers/Api/XenditWebhookController.php` — controller baru

---

### [DECISION] Self-Order: Stok Tidak Dikurangi Sebelum Bayar
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase (Self Order Feature)
- **Dampak:** Service
- **Breaking Change:** Tidak
- **Deskripsi:** Untuk self-order, stok **TIDAK** dikurangi saat order dibuat. Stok baru dikurangi setelah Xendit webhook mengkonfirmasi pembayaran (status PAID). Jika invoice expired (30 menit), transaksi langsung di-void tanpa perlu restore stok.
- **Alasan:** Mencegah order fiktif / tidak dibayar yang mengurangi stok. Berbeda dengan POS (stok langsung dikurangi) dan open bill POS (stok langsung dikurangi karena customer sudah di lokasi).
- **File Terdampak:**
  - `app/Services/TransactionService.php` — method baru: `createSelfOrder()` (tanpa deduct), `confirmSelfOrderPayment()` (deduct setelah bayar), `voidExpiredSelfOrder()` (void tanpa restore). Refactor `processItems()` sebagai shared method dengan flag `deductStock`.

---

### [DECISION] Fulfillment Tracking: Opsional, Terpisah dari Payment Status
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase (Self Order Feature)
- **Dampak:** Model | Service
- **Breaking Change:** Tidak
- **Deskripsi:** Fulfillment tracking (`waiting → preparing → ready → done`) terpisah dari payment status (`pending → completed → voided`). Fulfillment = `null` untuk POS bayar langsung (tidak perlu tracking), `waiting` untuk open bill dan self-order (setelah bayar). Kasir + Owner yang manage fulfillment (tanpa role kitchen baru). Customer diberitahu secara manual (panggil nama).
- **Alasan:** POS bayar langsung di counter tidak perlu tracking penyajian. Fulfillment hanya relevan untuk pesanan yang butuh waktu (open bill, self-order).

---

## Template Entry Kosong (Copy-Paste)

```markdown
### [TIPE] Judul Singkat
- **Tanggal:** YYYY-MM-DD
- **Fase Terkait:** Phase-X / Di Luar Fase / Cross-Phase
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config
- **Breaking Change:** Ya / Tidak
- **Deskripsi:** 
- **Alasan:** 
- **File Terdampak:**
  - `path/to/file` — deskripsi
- **Catatan Migrasi:** (opsional)
```
