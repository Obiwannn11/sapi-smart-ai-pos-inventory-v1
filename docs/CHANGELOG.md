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
| 2026-09-04 | DECISION | Pengaturan | Ketiga Halaman Pengaturan Berhenti Merakit Kontrolnya Sendiri — Centang, Dropdown, dan Tombol Dipulangkan ke Komponen Bersama |
| 2026-09-04 | DECISION | AI Analysis | Hasil AI Berhenti Menasihati dan Mulai Menunjuk Angka — Prompt Ditulis Ulang, Penomoran & Polling-nya Diperbaiki |
| 2026-09-03 | DECISION | Promosi | Tiga Halaman Saran Jual & Diskon Berhenti Menjelaskan Dirinya — Batasnya Ditunjuk, Bukan Diceritakan |
| 2026-09-03 | DECISION | Langganan | Halaman Langganan Dipecah Jadi Tiga Tab — Dua Kolom Dicoba Lebih Dulu dan Dibatalkan |
| 2026-09-03 | ADDITION | Langganan | Bayar Tagihan Langganan Selesai di Satu Modal — Kata Sandi Owner sebagai Ganti Aplikasi Bank |
| 2026-08-31 | ADDITION | Langganan | Tenant yang Ditangguhkan Punya Jalan Pulang — Satu Tagihan Pemulihan, Diminta Sendiri (BL-051) |
| 2026-08-31 | HOTFIX | Langganan | Periode `Y-m` Berhenti Memungut Tanggal Hari Ini — Tagihan Juni Tidak Lagi Dihargai Aturan Juli |
| 2026-08-31 | DECISION | Frontend | Halaman Vue Berhenti Dikirim Berombongan — Satu Bundel 1.136 KB Jadi Chunk per Halaman (BL-094) |
| 2026-08-29 | ADDITION | Platform | Kunci Pajak Punya Jalan Bukanya — Operator Membuka Kuncinya, Bukan Setelannya (BL-065 Butir 4) |
| 2026-08-29 | DECISION | Profit | Margin Diukur terhadap Pendapatan Toko, Bukan terhadap Pajak yang Menumpang di Atasnya (BL-065) |
| 2026-08-29 | ADDITION | Laporan | Pajak Terpungut Punya Angkanya Sendiri di Laporan (BL-065 Butir e) |
| 2026-08-28 | SCHEMA | Pajak | Pajak Masuk ke Kasir — Uang Transaksi Berhenti Jadi Satu Angka (BL-065) |
| 2026-08-28 | ADDITION | PWA | Antrean Penjualan Offline Berhenti Bergantung pada Tab yang Terbuka (BL-016 Bagian B) |
| 2026-08-26 | HOTFIX | PWA | Kasir Offline Berhenti Menunggu Katalog yang Tidak Akan Pernah Datang (BL-095, BL-096) |
| 2026-08-24 | HOTFIX | Publik | Halaman Depan Berhenti Mengunduh Seluruh Aplikasi Vue yang Tidak Dipakainya (BL-091) |
| 2026-08-24 | REFACTOR | Infra | Dua Aset Yatim Dibuang, dan Celah yang Selama Ini Ditambal Manusia Dijaga Test (BL-077) |
| 2026-08-24 | ADDITION | Infra | Riwayat yang Tidak Bisa Boot Dibiarkan, Peringatannya yang Dipindah ke Tempat Terbaca (BL-072) |
| 2026-08-24 | DECISION | Auth | Jenis Usaha Tetap Opsional, tapi Bawaannya Berhenti Ditebakkan Diam-diam (BL-079) |
| 2026-08-22 | DECISION | Waktu | Seluruh Aplikasi Berjalan di Jam Toko (WITA), dan Satu Tempat Saja yang Menjawab "Hari Ini" (BL-082) |
| 2026-08-21 | ADDITION | Kas | Uang Keluar Laci Punya Tempat Mencatatnya, dan Efeknya yang Ditahan — Bukan Pencatatannya (BL-087) |
| 2026-08-21 | ADDITION | Kas | Sesi Kas Punya Umur, dan yang Lewat Ditutup Sistem Tanpa Mengaku Sudah Dihitung (BL-088) |
| 2026-08-21 | HOTFIX | Upsell | Saran yang Terlanjur Diterima Bisa Ditarik Lagi, dan Transaksi yang Di-void Berhenti Mengaku Berhasil (BL-092 butir 3) |
| 2026-08-21 | ADDITION | Upsell | Owner Melihat Saran Otomatis, Aturannya Sendiri, dan Siapa yang Mengisi Tiga Slot Kasir (BL-092 butir 1-2) |
| 2026-08-21 | ADDITION | Kas | Membuka Angka Seharusnya Meninggalkan Jejak yang Dibaca Pemilik (BL-090) |
| 2026-08-21 | REFACTOR | Kas | Tutup Kas Punya Halamannya Sendiri, dan Angka yang Sudah Terbaca Tidak Bisa Disunting Diam-diam (BL-086 butir 2) |
| 2026-08-20 | HOTFIX | UI | Tab Demo Ketiga Berhenti Meramal dan Jadi Saran Jual yang Memang Sudah Jalan (BL-089) |
| 2026-08-21 | ADDITION | Kas | Angka yang Jadi Jawaban Disembunyikan Selama Sesi Berjalan (BL-086 butir 1) |
| 2026-08-21 | REFACTOR | UI | Aturan Saran Jual dan Diskon Keluar dari "Keuangan", Jadi Grup Sendiri (BL-085) |
| 2026-08-20 | HOTFIX | UI | Hero Berhenti Menjanjikan Ramalan Stok dan Tombol yang Tidak Punya Jalan (BL-083) |
| 2026-08-21 | HOTFIX | UI | Sidebar Owner Menyebut Nama Toko yang Sedang Dibuka, Bukan Nama Produknya (BL-084) |
| 2026-08-20 | ADDITION | UI | Lima Tangkapan Layar Landing Diambil Ulang dari Aplikasi yang Berjalan, dan Jadi WebP (BL-032 butir 3) |
| 2026-08-20 | HOTFIX | Seeder | Seeder Demo Melewatkan Hari yang Hanya Berisi Tagihan Terbuka |
| 2026-08-20 | ADDITION | Langganan | Kuota AI Tambahan Akhirnya Bisa Dibeli, dan Dilepas Lagi — Persis seperti Kursi (BL-069) |
| 2026-08-20 | DEPRECATE | UI | Testimoni Karangan Dicabut, Diganti Bagian yang Tidak Mengaku Sebagai Kesaksian (BL-078) |
| 2026-08-20 | ADDITION | Kasir | Tagihan Terbuka Punya Umur, dan yang Lewat Jadi Kas Negatif yang Hanya Owner Bisa Bereskan (BL-031) |
| 2026-08-20 | FIX | Langganan | Tagihan Tenant Adaptif Menunggu Omzetnya, dan Berhenti Menebak Bulan (BL-080) |
| 2026-08-20 | FIX | Langganan | Penghitung Omzet Dipindah ke Sebelum Penerbit Tagihan (BL-080 butir a) |
| 2026-08-19 | DECISION | Kasir | Tagihan Terbuka Hidup Satu Hari, Lalu Jadi Kas Negatif yang Hanya Owner Bisa Bereskan (BL-031) |
| 2026-08-19 | DECISION | Langganan | Kuota AI Tambahan Dijual seperti Seat — +5 Analisis/Hari Rp 15.000 per Bulan (BL-069) |
| 2026-08-19 | DEPRECATE | Langganan | Tombol Simulasi Pembayaran Dicabut — Jalur Uang Ketiga Ditutup (BL-061) |
| 2026-08-19 | DECISION | Langganan | Prabayar, dan Tarifnya dari Omzet Bulan Sebelumnya (BL-056) |
| 2026-08-19 | ADDITION | Harga | Diskon Jadi Entitas Tersendiri, dengan Lantai Untung yang Hanya Manusia Boleh Tembus (BL-018) |
| 2026-08-19 | ADDITION | Platform | Akun Platform Punya Faktor Kedua, dan Kata Sandi yang Benar Tidak Lagi Berarti Masuk (BL-013) |
| 2026-08-19 | ADDITION | Upsell | Owner Akhirnya Bisa Menargetkan Saran Jualnya Sendiri, dan Aturannya Selalu Menang Slot (BL-074) |
| 2026-08-19 | ADDITION | Kasir | Pembayaran Non-Tunai Bisa Difoto, dan Sinkronisasi Offline Ternyata Tidak Perlu Ikut Berubah (BL-075) |
| 2026-08-19 | ADDITION | Unggahan | Gambar Dikecilkan di Perangkat Sebelum Diunggah, dan Bukti Transfer Berhenti Mendarat Mentah (BL-077 butir a & b) |
| 2026-08-15 | ADDITION | Pendaftaran | Jenis Usaha Akhirnya Menentukan Fitur, Bukan Cuma Harga (BL-034) |
| 2026-08-15 | ADDITION | UI | Halaman Menunjukkan Bentuknya Sebelum Datanya Sampai (BL-037) |
| 2026-08-15 | ADDITION | Langganan | Masa Coba Berakhir dengan Pertanyaan, Bukan dengan Tagihan (BL-044 butir c) |
| 2026-08-15 | ADDITION | Pendaftaran | Halaman Daftar Menyebut Masa Gratis yang Berakhir dengan Tagihan (BL-071) |
| 2026-08-14 | HOTFIX | UI | Avatar Testimoni Turun dari 1,8 MB Jadi 5 KB — dan Ternyata JPEG Berekstensi `.png` (BL-032 butir 3) |
| 2026-08-14 | HOTFIX | UI | Landing Berhenti Menjanjikan Login Google, Katalog Otomatis, dan "Ribuan UMKM" (BL-032 butir 1) |
| 2026-08-14 | ADDITION | Langganan | Tenant Jalur Harga Tetap Akhirnya Bisa Melihat Kelasnya Sendiri (BL-041 butir b) |
| 2026-08-14 | ADDITION | Langganan | Landing Menjelaskan Cara Tarif Dihitung — dan Berhenti Menjanjikan Penguncian yang Tidak Pernah Ada (BL-066) |
| 2026-08-14 | ADDITION | Langganan | Isi Paket Terlihat Sebelum Orang Mendaftar, dan Berhenti Terpecah Dua di Dalam Aplikasi (BL-067) |
| 2026-08-14 | ADDITION | Langganan | Halaman `/harga` Membacakan Kedua Jalur Tarif kepada Orang yang Belum Mendaftar (BL-041 butir c) |
| 2026-08-14 | HOTFIX | UI | Dua Paket Karangan di Landing Diganti Paket yang Benar-Benar Ditagihkan (BL-032 butir 2) |
| 2026-08-14 | ADDITION | Langganan | Satu Pembaca Harga untuk Permukaan Tanpa Sesi, Sebelum Ada yang Membacanya (BL-041, BL-066, BL-067) |
| 2026-08-14 | REFACTOR | Pengaturan | "Profil Usaha" Pecah Jadi Tiga Halaman dan Tiga Endpoint — Satu Tombol Simpan Tidak Lagi Menulis Merek, Tarif, Modul, dan Kunci API Sekaligus (BL-039) |
| 2026-08-14 | ADDITION | Langganan | Nominal yang Menyimpang dari Aturan Wajib Beralasan, dan Alasannya Dibaca Tenant yang Ditagih (BL-057) |
| 2026-08-14 | ADDITION | RBAC | Halaman Staf Menjawab "Orang Ini Bisa Buka Apa Saja", dan Baris Owner Mengaku Melewati Seluruh Pemeriksaan (BL-038) |
| 2026-08-14 | DECISION | UI | Tiga Permukaan Publik Jadi Satu Keluarga: `SAPI POS` Resmi, Palet Tunggal, dan Tailwind CDN Dilepas (BL-033) |
| 2026-08-13 | ADDITION | AI | Kuota AI Berhenti Tinggal di `.env`: Kebijakan Berjangka Waktu, Promo, dan Tombol Mengembalikan Jatah Hari Ini (BL-047) |
| 2026-08-13 | ADDITION | Laporan | Grafik Kedua Aplikasi Ini: Garis Tren di Rekap Bulanan (BL-064) |
| 2026-08-13 | ADDITION | UI | Kerangka Pemuatan Jadi Komponen, dan Empat Halaman Terberat Berhenti Menunggu Kueri Paling Lambat (BL-037) |
| 2026-08-13 | ADDITION | AI | Sisa Kuota AI Pindah ke Halaman yang Membelanjakannya, dan Penolakannya Pindah dari Antrean ke Layar (BL-062) |
| 2026-08-13 | ADDITION | Laporan | Laporan Bulanan: Satu Bulan Kalender, Diagregasi di Basis Data, dengan Unduhan CSV (BL-063) |
| 2026-08-10 | HOTFIX | Langganan | `price_locked` Nol Berhenti Dibaca Sebagai Tarif Rp 0 (BL-041) |
| 2026-08-10 | ADDITION | Langganan | Pengajuan Harga Adaptif Punya Halaman, Dinilai Seketika, dan Berujung pada Ambang (BL-055) |
| 2026-08-08 | ADDITION | Langganan | Seat Tambahan Jadi Komponen Bulanan, dan Untuk Pertama Kalinya Bisa Dilepas (BL-053) |
| 2026-08-08 | ADDITION | Langganan | Masa Tenggang Jadi Tangga Tiga Tahap — Kasir Berhenti Mati di Hari Pertama (BL-054) |
| 2026-08-07 | ADDITION | Langganan | Masa Gratis Berakhir dengan Perpindahan, Bukan dengan Jatuh ke Tenggang (BL-052) |
| 2026-08-07 | ADDITION | Langganan | Pembayaran Peragaan Berhasil Sendiri: Tombol "Bayar Penuh" Turun Jadi Alat Pengembangan |
| 2026-08-07 | HOTFIX | Langganan | Penjaga Periode-Ganda Menyaring `kind`, dan Tenant yang Dilewati Berhenti Menghilang dari Hitungan (BL-058) |
| 2026-08-07 | ADDITION | Langganan | Halaman Bayar Berdiri di Atas Gateway Tiruan yang Bicara Seperti Gateway Sungguhan (BL-059) |
| 2026-08-07 | DECISION | Langganan | Struktur Harga Ditetapkan: Free Dua Bulan, Adaptif Jadi Diskon `paid-1`, dan Tenggat Berhenti Mematikan Kasir |
| 2026-08-07 | HOTFIX | Langganan | Masa Tenggang Ikut Ditagih, dan Aturan Tunggakan Bertahan Setelah Ditinjau |
| 2026-08-07 | HOTFIX | Langganan | Tagihan Rp 0 Berhenti Meminta Bukti Transfer Nol Rupiah (BL-049 butir b & c) |
| 2026-08-06 | ADDITION | Platform | Paket Kedua Akhirnya Bisa Dihuni: Tenant Bisa Dipindahkan, Seat Bayarnya Ikut (BL-046) |
| 2026-08-06 | ADDITION | Langganan | Keadaan Langganan Terlihat di Setiap Layar, & Satu Pintu Menuju Aktif (BL-045) |
| 2026-08-06 | DECISION | Produk | Gambar Produk Pindah ke Disk Privat, Diseragamkan Ukurannya, dan Punya Cadangan Inisial |
| 2026-08-06 | ADDITION | Langganan | Tagihan Periode Terbit Sendiri Sebelum Aksesnya Menyempit (BL-044 butir b) |
| 2026-08-05 | DECISION | Langganan | Tanggal Tagih Jadi Jangkar: Bulan Pendek Menjepit Sementara, Tidak Menggeser Selamanya (BL-030) |
| 2026-08-05 | HOTFIX | Auth | Pengalihan Setelah Masuk Memilah Dua Dunia, Bukan Cuma Sebelum Masuk (BL-043) |
| 2026-08-01 | DECISION | Demo | Seeder Transaksi Menambal Hari Kosong, Bukan Mereset atau Menumpuk |
| 2026-08-01 | ADDITION | Platform | Aturan Tarif Bisa Disunting & Dihentikan, Paket Punya Batas AI dan Peran Penampung (BL-046, BL-047) |
| 2026-08-01 | ADDITION | Langganan | Warna Khas per Jalur Harga, Jejak Persetujuan, & Perkiraan Tarif Adaptif |
| 2026-08-01 | DECISION | Langganan | Halaman Langganan Masuk ke Shell Owner, dan Sidebar Mati Saat Ditangguhkan |
| 2026-08-01 | ADDITION | Kasir | Identitas Pesanan Bisa Diisi dari Kasir, & Nomor Panggil Lepas dari Papan Dapur (BL-026) |
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

### [DECISION] Ketiga Halaman Pengaturan Berhenti Merakit Kontrolnya Sendiri — Centang, Dropdown, dan Tombol Dipulangkan ke Komponen Bersama
- **Tanggal:** 2026-09-04
- **Fase Terkait:** Di Luar Fase — penyeragaman tampilan, diminta pemilik.
- **Dampak:** Frontend — ketiga tab Pengaturan (`Profil & Merek`, `Cara Kerja Sistem`, `Integrasi & Kredensial`) dan dua komponen bersama yang dipakai belasan halaman lain.
- **Breaking Change:** Tidak. Tidak ada rute, request, maupun nama field yang berubah; 113 test `tests/Feature/Owner` tetap hijau.
- **Deskripsi:** Ketiga halaman Pengaturan menulis sendiri centang, dropdown, dan tombolnya dari `<input type="checkbox">`, `<select>`, dan `<button>` mentah — padahal `Checkbox.vue`, `SelectDropdown.vue`, dan `Button.vue` sudah ada dan dipakai halaman lain. Akibatnya kontrol yang sama terlihat berbeda tergantung halaman mana yang sedang dibuka: centang bawaan browser di Pengaturan, centang kartu bertanda di Produk; menu abu-abu sistem di Pengaturan, popup bertanda centang hijau di Aturan Diskon. Sembilan belas kontrol dipulangkan ke komponen bersama, dan warna galat yang bercampur (`text-red-600` di satu baris, `text-destructive` di baris berikutnya) diseragamkan ke token tema.
- **Dua cacat komponen yang baru ketahuan karena dipakai di sini.** Keduanya sudah lama ada dan diam:
  1. `SelectDropdown` menghitung "sudah memilih" dari **isi nilainya** (`modelValue !== ''`), bukan dari ada tidaknya opsi yang cocok. Daftar yang memakai `''` sebagai pilihan sah karena itu tidak pernah bisa menampilkan labelnya — kolom Provider menampilkan placeholder abu-abu "Pilih..." padahal "Default (SumoPod gratis)" sedang terpilih. Ini bukan cuma soal halaman ini: **tujuh halaman lain** — `Produk` (daftar & form), `Transaksi`, `Mutasi Stok`, `Riwayat Kasir`, `Staf`, `Aturan Saran Jual` — punya opsi `''` berlabel "Semua …", "Tanpa Kategori", "Tanpa role", atau "Setiap penjualan" yang selama ini juga tampil sebagai placeholder abu-abu, bukan sebagai pilihan yang sedang berlaku. Satu perbaikan di komponennya menyembuhkan semuanya.
  2. Tombol "kosongkan" di dalam pemicu dropdown adalah `<button>` di dalam `<button>`. HTML tidak mengizinkannya, dan browser diam-diam mengeluarkannya dari induknya. Diganti `<span role="button">` yang tetap bisa dicapai keyboard.
- **Kenapa centangnya sekalian diberi peran dan fokus.** `Checkbox.vue` tidak memakai `<input>` sama sekali — ia `<label>` dengan `@click`. Artinya keyboard tidak pernah bisa mencapainya dan pembaca layar hanya menemukan teks biasa. Sekarang ia `<div role="checkbox">` dengan `aria-checked`, `tabindex`, dan Spasi/Enter. Ditambah prop `align="start"` supaya kotaknya sejajar baris pertama pada baris yang keterangannya panjang — di Pengaturan tiap centang punya satu paragraf penjelasan, dan kotak yang melayang di tengah paragraf terbaca seperti milik kalimat yang sedang disejajarinya.
- **File Terdampak:**
  - `resources/js/Pages/Owner/Settings/Index.vue` — dropdown Jenis Usaha dan tombol simpan; pembatas `border-t` di atasnya sengaja dipertahankan, karena kolom itu satu-satunya di halaman ini yang menggerakkan tarif.
  - `resources/js/Pages/Owner/Settings/Operations.vue` — 6 centang, 3 dropdown, 2 tombol simpan dipulangkan ke komponen bersama; label field diseragamkan ke `text-gray-700`; cincin fokus input angka disamakan.
  - `resources/js/Pages/Owner/Settings/Integrations.vue` — dropdown Provider, tombol simpan, dua tombol salin, tombol generate & cabut token.
  - `resources/js/Components/SelectDropdown.vue` — `hasSelection` dihitung dari opsi yang cocok; Escape menutup dari mana pun fokusnya (handler pindah ke `document` karena popupnya di-teleport ke `<body>`); `role="listbox"`/`option`, `aria-expanded`; tombol kosongkan tidak lagi bersarang.
  - `resources/js/Components/Checkbox.vue` — `role="checkbox"` + keyboard, prop `align`, keadaan disabled untuk varian kartu.
- **Catatan Migrasi:** Tidak ada. Jalankan `npm run build` seperti biasa.

---

### [DECISION] Hasil AI Berhenti Menasihati dan Mulai Menunjuk Angka — Prompt Ditulis Ulang, Penomoran & Polling-nya Diperbaiki
- **Tanggal:** 2026-09-04
- **Fase Terkait:** Di Luar Fase — revisi tampilan & prompt, diminta pemilik.
- **Dampak:** Halaman AI Analysis (tampilan + renderer) dan prompt di `RunAiAnalysisJob`
- **Breaking Change:** Tidak. Tidak ada skema, rute, maupun kontrak provider yang berubah; `AiContextService` tidak disentuh sama sekali.
- **Deskripsi:** Pemilik menempelkan satu hasil analisis apa adanya dan menyebutnya *"sejujurnya gajelas dan tidak membantu"*, dengan contoh yang tepat sasaran: *"Tingkatkan Pengalaman Pelanggan: perbaiki layanan dan atmosfer kafe untuk menarik lebih banyak pelanggan."* Kalimat itu benar untuk kafe mana pun di dunia, dan owner membayar kuota AI untuk mendapatkannya. Bersamanya datang empat keluhan tampilan yang jauh lebih kecil tapi ikut diperbaiki di sesi yang sama.
- **Kenapa nasihat umum lolos dari prompt lama, dan kenapa memintanya "lebih spesifik" tidak akan cukup.** Prompt lama hanya berisi satu kalimat: *"beri jawaban ringkas, actionable, dalam Bahasa Indonesia, dengan angka konkret"*. "Perbaiki layanan dan atmosfer" **memenuhi** permintaan itu — bentuknya kalimat perintah, jadi ia terbaca actionable, dan model dengan senang hati menempelkan satu angka di sebelahnya ("misalnya 1 transaksi di 27 Juli") tanpa angka itu punya hubungan apa pun dengan tindakannya. Karena itu larangannya sekarang ditulis sebagai **bentuk yang ditolak**, lengkap dengan daftar kalimat terlarang yang benar-benar muncul di hasil kemarin — "tingkatkan pelayanan", "perbaiki atmosfer", "perbanyak promosi", "diversifikasi menu", "tingkatkan pengalaman pelanggan" — dan tiap rekomendasi diwajibkan memuat **empat** hal sekaligus: objeknya, tindakannya, angka dasarnya, dan perkiraan dampaknya beserta cara menghitungnya. Satu dari empat yang hilang cukup untuk mengembalikan nasihat generik.
- **Model sekarang diberi peta datanya, bukan hanya datanya.** Tiga kesalahan baca yang terlihat di hasil kemarin semuanya lahir dari menebak arti kunci: `profit_by_item.others` — yang sebetulnya RINGKASAN varian yang tidak muat ([BL-069]) — dibaca sebagai produk bernama "others"; `projection` dihitung ulang dengan rumus karangan sendiri padahal angkanya sudah tersedia; dan margin diturunkan dari `revenue`, bukan `net_revenue`. Peta tujuh kunci di kepala prompt menutup ketiganya, dan kalimat pajak tetap **bersyarat** seperti sebelumnya ([BL-065]) — mayoritas tenant tidak memungut apa pun, dan menjelaskan pajak kepada mereka hanya menambah tempat untuk salah.
- **"Belum bisa dijawab dari data" jadi jalan keluar yang sah.** Tanpa itu, model yang kehabisan bahan akan menambal dengan saran umum — persis mekanisme yang menghasilkan keluhan ini. Sekarang ia diminta menyebut data apa yang kurang, dan dilarang menyimpulkan sebab (cuaca, hari libur, pesaing) yang memang tidak ada di payload.
- **Tiap tipe analisis meminta susunan bagiannya sendiri**, bukan lagi satu kalimat pertanyaan. Saran Diskon menuntut kandidat + margin sesudah diskon + **titik impas** (berapa tambahan qty agar potongannya tertutup), dan melarang usulan yang menjatuhkan margin di bawah 0%. Proyeksi Profit memisahkan angka aktual, proyeksi, pendorong/penghambat, dan tindakan. Insight Umum mendapat bagian "Yang Menyimpang" yang **boleh kosong** — "kalau tidak ada yang menyimpang, tulis apa adanya, jangan dicari-cari", karena anomali yang dikarang lebih mahal daripada anomali yang tidak dilaporkan. Pertanyaan Sendiri dibawa apa adanya tapi dipagari kalimat yang sama.
- **Penomoran yang selalu kembali ke 1 ternyata cacat renderer, bukan cacat model.** Pemilik melaporkannya sebagai "angka urutan tidak berurutan", dan tebakan pertama yang wajar — model menulis "1." berulang-ulang — memang benar, tapi bukan sebabnya. `renderMarkdown()` menutup `<ol>` begitu bertemu butir bertitik di bawah sebuah langkah, lalu membukanya lagi di langkah berikutnya; `<ol>` yang baru **selalu** mulai dari 1, berapa pun angka yang diketik model. Rendernya kini bertumpuk: daftar bertitik di bawah langkah bernomor ditulis DI DALAM `<li>`-nya, `<ol>`-nya tidak pernah tertutup, dan nomornya berjalan 1, 2, 3 apa pun yang diketik model. Baris kosong berhenti menutup daftar (model menyelanginya demi keterbacaan), dan hanya judul atau paragraf tak menjorok yang menutupnya.
- **Hanya SATU arah yang dianggap bersarang**, dan itu keputusan, bukan kelalaian: butir bertitik sesudah langkah bernomor bersarang; daftar bernomor sesudah daftar bertitik **tidak** — arah itu jauh lebih sering berarti dua daftar terpisah, dan menebaknya salah akan menjorokkan seluruh daftar kedua ke bawah butir terakhir daftar pertama. Model tidak menjorokkan butirnya, jadi indentasi tidak bisa dipakai sebagai penentu.
- **"Token Terpakai" diganti "Rentang Data".** Pemilik menyebutnya "tentu saja bukan informasi yang penting pada user", dan itu tepat: jumlah token adalah ongkos internal yang tidak bisa ditindaklanjuti siapa pun yang membacanya. Penggantinya bukan sekadar pengisi tempat — panjang rentang justru yang menentukan cara membaca kartu di bawahnya, karena proyeksinya adalah rata-rata harian dikali angka itu.
- **Formulirnya jadi SATU baris di layar lebar, empat kolom sama besar.** Percobaan pertama hanya menyempitkan dropdown tipe jadi 2 : 3 dari lima kolom; pemilik menolaknya — *"pemilihan desain atas bawah yang buruk, itu karena berlaku di mode desktop padahal muat untuk 1 baris saja semua tanpa perlu baris baru"* — dan meminta lebarnya dibuat rata *"biarpun tulisan atau tanggalnya lebih pendek"*. Sekarang `Tipe Analisis`, `Dari Tanggal`, `Sampai Tanggal`, dan tombol `Analisa` berdiri sejajar dalam satu kisi empat kolom, masing-masing selebar kolomnya, bukan selebar isinya. Meter kuota turun ke bawah baris itu — satu-satunya hal yang berpindah, dan ia tetap terbaca sebelum tombolnya ditekan.
- **Rentangnya dipecah jadi dua field berlabel sendiri, dan pemisah "–" dibuang.** Selama "Periode" tetap satu sel berisi dua tanggal, keduanya tidak akan pernah sederajat dengan dropdown di sebelahnya — ia satu kolom yang dibagi dua, bukan dua kolom. Memberi masing-masing labelnya sendiri membuat kerataan yang diminta lahir dari strukturnya, bukan dari tebakan lebar.
- **`DatePicker` dapat mode `block`, dan bulannya disingkat di mode itu.** Tanpa lebar yang dipatok, tombol tanggal melar mengikuti teksnya — "Kam, 6 Agustus 2026" dan "Jum, 4 September 2026" berhenti di tempat berbeda, dan itu yang membuat barisnya terlihat ragged. **Penyingkatan bulannya bukan kosmetik:** diukur pada kasus desktop tersempit (jendela 1100px dengan sidebar 224px), tiap kolom hanya menyisakan **111px** untuk teks tanggalnya, sedangkan "Jum, 4 September 2026" butuh **150px** — ia akan terpotong jadi "Jum, 4 September…", membuang justru tahunnya. "Jum, 4 Sep 2026" muat utuh di 111px, dan bentuk itu memang sudah dipakai tabel riwayat di halaman yang sama. Mode `block` hanya dipakai di sini; empat halaman lain yang memakai `DatePicker` tidak berubah sama sekali karena `block` bawaannya `false`.
- **Kisinya diukur terhadap LEBAR KARTU, bukan lebar jendela (`@container`).** Versi pertama memakai `lg:grid-cols-4`, dan itu salah di tempat yang tidak terlihat sampai diukur: `lg:` menyala tepat di jendela 1024px, sementara sidebar owner memakan ~224px — kartunya tinggal 663px, kolomnya menyusut jadi **153px**, dan tanggalnya terpotong justru di lebar yang paling lazim dipakai laptop. Breakpoint jendela memang tidak bisa melihat sidebar; yang menentukan muat atau tidak adalah kartunya. Formulirnya kini `@container` dengan `@md:grid-cols-2` dan `@4xl:grid-cols-4`, jadi keputusannya diambil dari lebar yang sebenarnya menampung kontrolnya. **Akibatnya benar di keempat kombinasi**, dan keempatnya diukur di aplikasi berjalan: jendela 1024 + sidebar terbuka → kartu 663px → dua kolom 323px (bukan empat kolom yang terjepit); jendela 1280 + sidebar terbuka → kartu 919px → empat kolom 218px; jendela 1440 → kartu 975px → empat kolom 232px; dan jendela 1024 dengan sidebar tertutup tetap dapat empat kolom, karena kartunya memang cukup lebar. Tidak ada satu pun yang terpotong.
- **Kolom "Pertanyaan" ikut masuk ke dalam kisi, dan tombolnya dipatok ke kolom terakhir.** Kalau kolom pertanyaan berdiri di luar kisi, tombol kirim berakhir di ATAS kolom isian yang dikirimnya. Ia kini satu sel selebar penuh di dalam kisi yang sama, dan tombolnya memakai `lg:col-start-4` — dipatok, bukan dibiarkan mengalir, karena begitu pertanyaannya memakan satu baris penuh, tombol yang mengalir mendarat di kolom pertama: kiri bawah, tempat yang tidak dicari orang saat mencari tombol kirim.
- **Dua perbaikan tampilan kecil lain:** label tanggal di `DatePicker` diberi `whitespace-nowrap` sehingga tanggal tidak lagi patah **di permukaan mana pun**, bukan hanya di halaman ini; dan "Lihat →" jadi tombol betulan (`Button` `soft`, berubah `primary` + "Ditampilkan" pada baris yang sedang tampil), karena satu-satunya hal yang bisa ditekan di baris itu dulu terlihat sama dengan badge status di sebelahnya.
- **Menekan "Lihat" sekarang menggulir ke hasilnya.** Kartu hasil berdiri di ATAS tabel riwayat, jadi menekan tombol pada baris yang jauh ke bawah dulu terasa seperti tombol yang tidak berbuat apa-apa — isinya berganti di luar layar. Submit analisis baru ikut menggulir ke sana.
- **Pemutar yang berputar selamanya — ditemukan saat memverifikasi perubahan di atas, bukan dilaporkan.** Analisis yang baru dipesan terus menampilkan "Memproses analisis…" padahal di basis data ia sudah `completed`; hanya memuat ulang halaman yang menampakkannya. Sebabnya seluruh rantai polling digantungkan pada satu watcher `status`: sekali muat ulang dijadwalkan, dan jadwal berikutnya **hanya lahir kalau nilainya berubah**. Analisis yang butuh lebih dari 3 detik menjawab `processing` dua kali berturut-turut — nilainya sama, watcher-nya diam, polling-nya mati di situ. **Yang membuatnya bertahan selama ini:** analisis cepat memang melewati tiga nilai berbeda (`pending` → `processing` → `completed`), jadi rantainya utuh secara kebetulan, dan cacatnya hanya muncul pada analisis yang lambat — justru yang paling butuh pemutarnya. Jadwalnya kini dipasang ulang lewat `onFinish` sesudah SETIAP muat ulang, apa pun status yang kembali; watcher-nya tinggal melayani pemicu yang bukan muat ulang (analisis dipilih dari riwayat, dan pemuatan halaman pertama).
- **Yang TIDAK dikerjakan, dan sudah dicatat sebagai `[BL-100]`:** permintaan pemilik agar barang di hasil analisis bisa diklik. Ia tidak bisa diselesaikan di sesi ini karena payload LLM memang **tidak pernah membawa id** — `top_products` dan `profit_by_item` dikelompokkan `variant_name`, kolom terdenormalisasi di `transaction_items` — nama varian tidak dijamin unik dan tidak dijamin masih ada di katalog, dan `owner/products` hari ini tidak membaca satu pun query string maupun punya kotak pencarian. Menyuruh model menulis tautannya sendiri sengaja **ditolak sebagai jalan pintas**: ia akan mengarang tujuan untuk nama yang tidak ada, dan hasilnya tautan mati yang terlihat sah. Urutannya di backlog dibalik — buat tujuannya dulu (`?q=` di halaman Produk, berguna sendirian), petakan nama ke id **di server**, baru tautkan.
- **Yang sudah diuji:** `tests/Feature/Ai/RunAiAnalysisJobTest.php` bertambah empat test yang memeriksa teks prompt yang benar-benar terkirim ke provider — larangan saran umum, susunan bagian per tipe analisis, pertanyaan sendiri yang dibawa apa adanya tapi tetap dipagari, dan kalimat pajak yang hanya ikut untuk tenant yang memungut. Jawaban yang buruk tetap terlihat seperti jawaban, jadi hilangnya satu aturan tidak akan ketahuan tanpa dipatok di sini. Renderer barunya diperiksa terhadap teks keluhan aslinya (tiga "1." beruntun dengan butir di antaranya → satu `<ol>` bernomor 1, 2, 3), plus penomoran yang direset judul, escaping HTML, dan daftar bertitik yang diikuti daftar bernomor. `vite build` lolos.
- **Layoutnya diukur di aplikasi berjalan, bukan dikira-kira.** Tiap kontrol diukur `scrollWidth` vs `clientWidth` dan `gridTemplateColumns` dibaca langsung dari halaman Owner yang sesungguhnya, pada 1024 / 1280 / 1440 dengan sidebar terbuka, plus lebar sempit untuk susunan satu kolom. Pengukuran itu yang **menemukan** cacat 153px di jendela 1024 — mata tidak menangkapnya, dan mock tanpa sidebar juga tidak. Susunan "Pertanyaan Sendiri" ikut diperiksa di aplikasi: tiga kontrol 232px di baris pertama, kolom pertanyaan 975px di baris kedua, dan tombolnya di x=1004 dengan tepi kanan **rata persis** dengan kolom pertanyaan di 1235px.
- **Diperagakan di aplikasi berjalan, bukan hanya di test.** Satu analisis Insight Umum benar-benar dijalankan pada data Kopi Nusantara: hasilnya menyebut `Croissant - Plain` yang expired, `Matcha Latte - Regular` bermargin 25,71%, dan tanggal 2026-08-08 yang omzetnya anjlok ke Rp112.000 — tiap tindakan membawa Objek, Tindakan, Dasar, dan Perkiraan Dampak, bernomor 1 sampai 5. Perbaikan polling-nya diperagakan **tanpa membelanjakan kuota**: satu baris `processing` dipasang sementara, dibiarkan dijawab sama selama 11 detik (keadaan yang dulu mematikan poll-nya), lalu diubah jadi `completed` — halaman menampakkannya sendiri tanpa dimuat ulang, dan barisnya dihapus setelahnya.
- **File Terdampak:**
  - `app/Jobs/RunAiAnalysisJob.php` — `prompts()` ditulis ulang: peta data, tujuh aturan isi, dan susunan bagian per tipe
  - `resources/js/Pages/Owner/AiAnalysis/Index.vue` — `renderMarkdown()` bertumpuk, polling dipasang ulang lewat `onFinish`, kartu "Rentang Data", tombol riwayat, gulir ke hasil, kolom formulir 2 : 3
  - `resources/js/Components/DatePicker.vue` — prop `block` (lebar penuh, bulan disingkat, teks terpotong elipsis); label tanggal tidak lagi patah baris
  - `tests/Feature/Ai/RunAiAnalysisJobTest.php` — empat test bentuk prompt
- **Catatan Migrasi:** Tidak ada. Analisis lama tetap tersimpan apa adanya; `tokens_used` masih dicatat di basis data, hanya berhenti ditampilkan.

### [DECISION] Tiga Halaman Saran Jual & Diskon Berhenti Menjelaskan Dirinya — Batasnya Ditunjuk, Bukan Diceritakan
- **Tanggal:** 2026-09-03
- **Fase Terkait:** Di Luar Fase — revisi tampilan, diminta pemilik.
- **Dampak:** Frontend, dengan tiga perubahan server yang menopangnya
- **Breaking Change:** Tidak. Satu rute baru, dua kunci baru di larik yang sudah ada, satu komponen pindah folder. Tidak ada skema, tidak ada perilaku kasir yang berubah.
- **Deskripsi:** Pemilik menyebut ketiga halaman ini "benar-benar membingungkan", dan menunjuk sebabnya dengan tepat: **keterangan yang saya tulis sendiri** membuatnya ramai. Kalimatnya layak dikutip karena ia yang menentukan bentuk seluruh perubahan ini — *"memberikan info itu tidak selalu penting, dan UIUX yang bagus adalah user langsung paham apa ini itu tanpa penjelasan; lebih parah lagi ketika kamu sudah berikan penjelasan tapi belum paham, membuat keseluruhannya tidak berguna."* Yang dibongkar karena itu: dua persentase berpenyebut berbeda di `Reports/Upsell.vue`, dua kotak biru berisi batas tak terlihat di kedua halaman aturan, dan tujuh paragraf bantuan di dalam formulirnya.
- **Satu aturan yang dipakai memutuskan tiap blok teks: bisakah ini DIPERAGAKAN?** Kalau ya, teksnya dibuang dan peragaannya yang tinggal. Batas yang diterangkan di kepala halaman dibaca sekali, oleh owner yang belum punya satu pun aturan, lalu tidak pernah dibaca lagi tepat pada saat batas itu menggigit. Batas yang ditunjukkan pada barang milik owner sendiri terbaca justru pada saat itu.
- **Empat kartu + satu strip abu-abu jadi satu corong tiga tahap.** `Tingkat Terima` (diterima ÷ tampil) dan `Tingkat sukses tawar` (diterima ÷ ditawarkan) hidup di dua tempat berbeda dengan dua penyebut berbeda; komentar di kodenya sendiri sudah mengakui keduanya "mudah tertukar", dan tambalannya waktu itu adalah paragraf. Sekarang: **Muncul di layar → Ditawarkan kasir → Jadi dibeli**, tiap persentase menempel pada batang yang menjadi penyebutnya. Selisih "tampil tanpa dijawab" tidak lagi ditulis sebagai kalimat — ia adalah penyempitan batangnya. `conversion_rate` berhenti ditampilkan sebagai angka berlabel; ia lebar batang terakhir dibanding batang pertama. ([BL-025])
- **Kolom "Gabungan" dibuang dari panel Otomatis vs Aturan Anda.** Isinya salinan persis corong di atasnya. Angka yang sama muncul dua kali dalam satu layar membuat pembacanya mencari beda yang tidak ada. Judul kolom "Aturan Anda" jadi tautan ke halaman aturannya — pertanyaan yang menyusul angka itu selalu "di mana saya mengubahnya", dan tautan bukan keterangan.
- **`Aturan Saran Jual` jadi dua tab, dan pratinjaunya naik dari dasar halaman.** "Yang Muncul di Kasir Hari Ini" — simulasi yang memakai `UpsellIndexBuilder` yang sama persis dengan kasir — dulu terkubur di bawah tabel dan modal, padahal ia jawaban atas pertanyaan pertama owner tiap kali ia selesai menulis aturan. **Dua penawar dipasang justru karena tab menyembunyikan isinya:** angka slot terisi ikut di label tabnya, dan menyimpan aturan melompatkan halaman ke tab pratinjau. Perbandingan yang menuntut satu klik lebih dulu adalah perbandingan yang tidak pernah terjadi.
- **Kotak biru di kedua halaman aturan dihapus.** Penjaga stok, batas tiga slot, dan urutan rebutan sekarang diperagakan tab pratinjau. Lantai untung dan barang kedaluwarsa jadi keterangan baris. Satu fakta sengaja **tidak dipindahkan ke mana pun**: "hanya Anda yang bisa menjual di bawah lantai, langsung dari kasir" — itu pengetahuan tentang layar kasir, dan tempatnya bukan di halaman ini.
- **"Tidak berlaku (cek stok/kedaluwarsa)" dipecah jadi lima sebab.** `DiscountService::priceFor()` sudah tahu sebabnya sejak dulu, tapi hanya mengembalikan `rule: null`; layarnya lalu menyuruh owner memeriksa dua hal, sementara tiga sebab lain tidak disebut sama sekali. Sekarang larik kembaliannya membawa `reason` — `expired`, `unknown_cost`, `no_cut_today`, `floor_absorbed`, `cut_too_small` — dan `clamped` untuk harga yang **turun tapi tertahan lantai**. Dua yang terakhir sengaja dibedakan: lantai yang sudah setinggi katalog menuntut margin minimum diturunkan, potongan yang lebih kecil daripada satu langkah pembulatan cukup dinaikkan persentasenya.
- **Lantai untung berhenti diterangkan dan mulai ditunjuk.** Chip "Tertahan lantai" muncul pada baris yang potongannya benar-benar terjepit, dan label lantai di pratinjau formulir membawa rumusnya sendiri — "Lantai untung (modal + 10%)", tertaut ke Cara Kerja Sistem. Konsepnya diajarkan oleh barang milik owner, pada saat ia menggigit.
- **Tiga kontrol diganti, dan tiap penggantian membunuh satu paragraf bantuan:**
  1. **Kolom isian "Urutan" 0–999 jadi dua panah di tabel.** Angka prioritas adalah cara mesin mengurutkan; owner yang ingin satu aturan tampil lebih dulu sedang menunjuk baris. Rute baru `POST /owner/upsell-rules/{rule}/move`.
  2. **"Potongan awal" + "Potongan terdalam" jadi satu kontrol berpasangan** — `10% → 40%` dengan "pada hari kedaluwarsa" sebagai satuan, bukan sebagai paragraf.
  3. **Tabrakan nama diselesaikan.** Halaman diskon punya dua field bernama "alasan": dropdown `trigger` dan teks bebas `reason`. Dropdownnya jadi **"Cara potongan bekerja"** dengan label opsi yang memikul penjelasannya ("Tetap — alasan saya sendiri", "Membesar sendiri mendekati kedaluwarsa"), dan teksnya jadi **"Catatan untuk laporan"**.
- **Prioritas ditulis ULANG saat digeser, bukan ditukar dua-dua.** Nilai bawaannya 0, jadi aturan yang belum pernah disentuh semuanya seri dan urutannya jatuh ke `id`; menukar dua angka nol tidak memindahkan apa pun di layar. `move()` menyusun ulang seluruh daftar dalam satu transaksi. Jumlah aturan buatan owner selalu kecil, jadi menulis ulang semuanya lebih murah daripada menjaga kebenaran penukaran berpasangan.
- **Aturan baru mendarat di ATAS daftar.** Slot kasir hanya tiga; aturan yang lahir di urutan terakhir tidak muncul di mana pun, dan owner yang baru saja menuliskannya menyimpulkan fiturnya rusak. Berpasangan dengan lompatan otomatis ke tab pratinjau, aturan baru langsung terlihat memenangkan slotnya. Nilai `priority` yang dikirim eksplisit tetap dihormati, jadi jalur API lama tidak berubah.
- **Paragraf yang menyuruh pemilik warung menyunting `config/upsell.php` dibuang**, dan lubang yang ditinggalkannya dicatat sebagai `[BL-099]`. Saklar per-jenis saran memang hanya ada di berkas config, sedangkan laporannya memisahkan angka per jenis justru supaya jenis yang tak pernah laku bisa dimatikan. Menyebut jalan yang tidak bisa ditempuh pembacanya lebih buruk daripada diam.
- **`TabNav` pindah dari `Components/Platform/` ke `Components/`.** Ia tidak pernah punya isi khas platform, dan halaman Owner yang kedua membutuhkannya — menyalinnya berarti dua komponen tab yang suatu saat berbeda di satu baris tanpa ada yang menyadarinya. Satu-satunya pemakai lamanya (`Platform/Tenants/Show.vue`) ikut disesuaikan.
- **Diperiksa di peramban sebagai `owner@sapi.test`, dan dua cacat ditemukan di sana — bukan oleh tes.** (1) Pada baris yang terjepit, angka lantai diulang tepat di bawah harga jadi yang nilainya sama persis; angka kembar berdampingan membuat pembacanya mencari beda yang tidak ada, jadi chipnya kini berdiri sendiri. (2) Di 375px, persentase corong dipaksa satu baris dan **meluber keluar kartunya** — "25% dari yang ditawarkan · 3 ditolak" lebih panjang daripada sisa ruang di sebelah batang; sekarang ia turun ke bawah batang di bawah `lg`. Yang diperagakan: keempat sebab diskon berdampingan dalam satu tabel (potongan terlalu kecil, barang kedaluwarsa, tertahan lantai, dan satu yang berlaku normal), kontrol berpasangan `30% → 50%` beserta pratinjau harganya, corong 8 → 4 → 1, perpindahan tab beserta `aria-selected`, lencana slot di label tab, panah urutan pada tiga aturan yang **prioritasnya sama-sama 0** (kasus yang membuat panah terasa rusak — urutannya benar-benar berpindah), dan lompatan otomatis ke tab pratinjau setelah menyimpan. Baris pratinjau yang dipakai memperagakannya dibuat sementara di basis data dev lalu dihapus, dan prioritas aturan yang ikut tergeser dikembalikan ke nilai asalnya.
- **Live test di kasir menemukan cacat ketiga, dan itu yang paling telak.** Satu penjualan sungguhan dijalankan sampai tuntas (Espresso Single + Hot → Extra Shot diterima, naik ukuran ke Double ditolak, bayar tunai): `upsell_events` mencatat `attach/accepted` +Rp 5.000 dan `upsize/rejected`, dan corong hari itu membacanya persis — muncul 2, ditawarkan 2 (100%), dibeli 1 (50% dari yang ditawarkan · 1 ditolak), omzet Rp 5.000. Yang gagal justru kartu **"Saat barang tertentu masuk keranjang"**: tabelnya tidak punya baris kepala sama sekali, jadi dua kolomnya tak bernama, dan tiap saran ditampilkan sebagai pil yang **jenisnya hanya dikodekan lewat warna latar** — namanya sembunyi di tooltip. Perombakan ini membuang keterangannya tanpa menggantinya dengan struktur; itu bukan menyederhanakan, itu menghapus. Sekarang tabelnya berkepala "Kalau ini masuk keranjang" / "Yang dilihat kasir", dan jenisnya jadi teks di samping nama barang, sebentuk dengan daftar di atasnya.
- **Kosakata lencana pratinjau disamakan dengan `UpsellStrip.vue`** — Tambah, Dorong, Naik ukuran, Pilihan pemilik. Sebelumnya hanya WARNANYA yang sama, sementara katanya dikarang ulang ("Tambah add-on", "Barang tertekan", "Aturan Anda"), sehingga owner yang membandingkan tab ini dengan layar kasirnya sedang memetakan dua kosakata. Tabel di halaman laporan sengaja tidak ikut: ia buku besar, bukan cermin.
- **Kolom "Berlaku" ditumpuk dan diberi label, dan di situlah cacat yang merusak data ketahuan ([BL-082]).** Permintaannya sederhana — ganti `2026-08-19 – 2026-08-30` jadi hari-tanggal-bulan-tahun — tapi begitu nama harinya ikut ditulis, selisihnya kelihatan: aturan yang di basis data mulai **20** Agustus dilaporkan mulai **19**. Sebabnya cast `date` pada `starts_on`/`ends_on`: ia menyimpan tengah malam zona bisnis lalu menyerialkannya sebagai UTC, sehingga di Asia/Makassar tanggalnya berangkat ke layar sebagai pukul 16.00 sehari sebelumnya, dan setiap layar memotongnya dengan `.slice(0, 10)`. Yang ketiga dari tiga akibatnya bukan kosmetik: formulir Edit terisi tanggal yang sudah mundur, dan menyimpannya menuliskan kemunduran itu kembali — aturan yang berulang kali disunting merayap mundur sehari tiap suntingan. Cast-nya jadi `date:Y-m-d` di **kedua** model, dan tesnya dibuktikan menggigit dengan mengembalikan cast lama sebentar. Bentuk tanggalnya sendiri mengikuti `DatePicker` yang sudah ada, bukan bentuk baru, dan dua tanggalnya bertumpuk dengan label "Mulai"/"Sampai" — dua tanggal berdampingan menyuruh pembacanya menebak mana yang mulai.
- **Kolom "Aksi" jadi tumpukan tiga tombol** di kedua halaman aturan. Tiga tombol berjajar memaksa kolomnya selebar tiga tombol, dan merekalah yang pertama terdorong keluar batas tabel di layar sempit; setelah ditumpuk, kedua tabel muat tanpa gulir mendatar di 820px.
- **Lencana status berhenti memakai satu abu-abu untuk semua sebab.** Sebelumnya "owner sendiri yang mematikannya" terlihat persis seperti "stoknya habis", padahal yang pertama tidak menunggu apa-apa dan yang kedua menunggu owner bertindak. Sekarang tiga nada, seluruhnya token sistem desain: **berjalan** (`success`), **disengaja atau soal waktu** (`muted` — dimatikan, belum mulai, sudah berakhir, potongan 0% hari ini), dan **ada yang menghalangi dan bisa Anda bereskan** (`warning` — stok habis, barang kedaluwarsa, modal belum diisi, potongan terlalu kecil, habis dimakan lantai). Tiga, bukan satu warna per sebab: yang dijawab warnanya hanya "perlu saya apa-apakan atau tidak"; pertanyaan "kenapa" sudah dijawab tulisannya. Nada peringatannya memakai `text-warning-foreground`, bukan `text-warning` — token `--warning` kuning terang (L 0.78) dan di atas tint 15% ia nyaris tak terbaca.
- **Panah urutan diberi bingkai dan tooltip.** Sebagai chevron abu-abu polos ia terbaca sebagai hiasan, dan bentuk panah sendirian tidak bisa menjawab "naik ke mana, supaya apa". Sekarang tombol berbingkai dengan `title` yang menyebut akibatnya — "Naikkan — aturan ini lebih dulu mengisi slot kasir" — dan kepala kolom "URUTAN" tidak lagi `sr-only`.
- **Tebal jatuh pada NILAI, bukan pada labelnya.** Di kolom Berlaku yang dicari mata adalah tanggalnya; "Mulai"/"Sampai" cuma penopang yang menjawab tanggal yang mana. Satu aturan untuk seluruh kolom, termasuk "Selamanya" dan "dimatikan" — keduanya juga nilai.
- **Yang sudah diuji:** `tests/Feature/DynamicDiscountTest.php` bertambah tujuh test untuk kelima sebab, penanda `clamped`, dan bentuk payload yang benar-benar sampai ke layar; `tests/Feature/Upsell/ManualUpsellRuleTest.php` bertambah lima untuk `move()` — termasuk kasus yang membuat panahnya terasa rusak padahal tidak (prioritas seri di angka 0), batas atas/bawah daftar, arah yang tidak sah, dan isolasi tenant. Seluruh berkas Vue yang disentuh lolos `vite build`.
- **File Terdampak:**
  - `app/Services/DiscountService.php` — enam konstanta `REASON_*`; `priceFor()` mengembalikan `reason` dan `clamped`
  - `app/Http/Controllers/Owner/UpsellRuleController.php` — `move()`; `store()` menaruh aturan baru di puncak
  - `routes/web.php` — `POST owner/upsell-rules/{upsellRule}/move`
  - `resources/js/Pages/Owner/Reports/Upsell.vue` — corong, panel dua kolom, empat keterangan dibuang
  - `resources/js/Pages/Owner/UpsellRules/Index.vue` — dua tab, panah urutan, kotak biru & enam keterangan dibuang, field `priority` dilepas dari formulir
  - `resources/js/Pages/Owner/DiscountRules/Index.vue` — sebab spesifik, chip "Tertahan lantai", kontrol potongan berpasangan, dua field "alasan" diberi nama yang berbeda
  - `resources/js/Components/TabNav.vue` — pindah dari `Components/Platform/`
  - `resources/js/Pages/Platform/Tenants/Show.vue` — jalur impor `TabNav`
- **Catatan Migrasi:** Tidak ada.

---

### [DECISION] Halaman Langganan Dipecah Jadi Tiga Tab — Dua Kolom Dicoba Lebih Dulu dan Dibatalkan
- **Tanggal:** 2026-09-03
- **Fase Terkait:** Di Luar Fase — revisi tampilan, diminta pemilik.
- **Dampak:** Frontend
- **Breaking Change:** Tidak. Tidak ada prop, rute, controller, atau perilaku server yang berubah; yang berubah hanya susunan, dan tiga kalimat yang menunjuk arah.
- **Deskripsi:** `Billing/Show.vue` tidak lagi satu gulungan berisi sembilan kartu. Di atas, selalu terlihat: **kartu keadaan dan kartu persetujuan berdampingan**, lalu panel pemulihan bila ada. Sisanya masuk tiga tab — **Ringkasan · Tagihan · Kapasitas**. Wadahnya `max-w-5xl`.
- **Dua kolom dicoba lebih dulu, dan dibatalkan sebelum di-commit.** Percobaan pertama melebarkan halaman ke `max-w-6xl` dan membaginya 7/5 menurut peran (yang dibaca vs yang diubah). Halamannya memang memendek, tapi hasilnya masonry, bukan grid: judul seksi berdiri di y=118/302/881 di kolom kiri dan y=118/627 di kanan — hanya baris pertama yang sejajar — dengan tujuh kartu setinggi 124/519/195/224/210/320/349 px. Tinggi tiap kartu bergantung `v-if` yang berbeda-beda per tenant, jadi ketidaksejajarannya tidak bisa diperbaiki dengan mengatur ulang urutan; ia melekat pada bentuknya. Mengukur *dasar* kedua kolom (1.061 vs 1.207 px) sempat membuatnya tampak seimbang — itu satu-satunya tempat di halaman ini di mana simetri tidak ada gunanya.
- **Kenapa tab, dan bukan sub-nav sticky.** Pola halaman setelan yang lazim (kolom navigasi sempit di kiri + satu kolom konten) menghilangkan masalah yang sama, karena kolom kirinya navigasi dan tidak dituntut menyentuh dasar halaman. Tapi `OwnerLayout` sudah memasang sidebar aplikasi di kiri; menambah satu lagi berarti dua sidebar berdampingan.
- **Pemecahannya menurut pertanyaan, bukan menurut panjang isi.** "Saya dapat apa" (Ringkasan), "saya harus bayar apa" (Tagihan), "saya mau ubah kapasitas" (Kapasitas). Membagi menurut panjang akan membuat isi tab berpindah-pindah antar tenant, karena hampir tiap kartu punya `v-if` sendiri.
- **Tiga hal sengaja TIDAK masuk tab.** Kartu keadaan dan panel pemulihan: tenant yang ditangguhkan mendarat di halaman ini justru karena aplikasinya tertutup, dan menaruh satu-satunya jalan keluarnya di balik tab yang harus ia tebak dulu adalah kemunduran yang menyamar sebagai perapian. Kartu persetujuan: alasannya bentuk. Kartu keadaan isinya tiga baris pendek; selebar halaman ia menyisakan dua pertiga ruang kosong yang tidak dipakai apa pun. Persetujuan menjawab pertanyaan sejenis — "apa yang berlaku atas saya sekarang" — panjangnya sepadan, dan berdampingan keduanya mengisi satu baris utuh alih-alih dua baris setengah kosong.
- **Tab keempat dibubarkan, bukan dibiarkan berisi satu kartu.** Begitu persetujuan naik ke baris kepala, tab "Persetujuan" tinggal memuat kartu Harga Adaptif sendirian selebar halaman — persis cacat yang sedang diperbaiki, dalam bentuk lain. Harga Adaptif pindah ke tab Ringkasan, berdampingan dengan kartu isi paket mulai `lg`: keduanya menjawab satu pertanyaan dari dua sisi, "tarif saya berapa" dan "tarif itu ditentukan bagaimana".
- **Tab Kapasitas hanya ada untuk owner**, karena kedua panelnya sejak dulu memang hanya dirender untuk owner. Jumlah kolom baris tabnya ikut jumlah tab (`grid-cols-3` / `grid-cols-2`), supaya kasir tidak melihat satu sel kosong di ujung baris.
- **Simetri di dalam tab dijamin grid, bukan diharapkan.** Setiap pasangan kartu tingginya diseragamkan `align-items: stretch` bawaan grid. Bedanya dengan dua kolom yang dibatalkan: di sana tiap KOLOM menampung isi sepanjang apa pun, di sini tiap BARIS punya satu tinggi. Kolom keduanya hanya dipasang kalau kartunya memang ada dua — tenant ber-BYOK tidak punya panel kuota AI, dan satu kartu selebar setengah layar dengan separuh kosong di sebelahnya adalah cacat yang sama lagi.
- **Tab yang sedang dibuka ditulis di fragment URL.** `/langganan#tagihan` bisa dikirim dan muat ulang tidak melempar orang kembali ke tab pertama. Fragment, bukan query string: ia murni urusan tampilan dan tidak pernah sampai ke server. Ada pendengar `hashchange` juga — berpindah fragment dari halaman yang sudah terbuka tidak memuat ulang apa pun, dan tanpa pendengar itu tautan `#tagihan` akan mengubah bilah URL tanpa memindahkan tab satu pun. `#persetujuan` yang sudah telanjur tersimpan jatuh ke Ringkasan, bukan ke layar kosong.
- **`preserveState: true` dipasang di semua POST dalam tab.** Tanpa itu halaman dirakit ulang dari nol setelah beli/lepas kursi, `activeTab` kembali ke Ringkasan, dan owner terlempar dari tab yang justru ingin ia lihat hasilnya. Nilai formulirnya tetap direset lewat `onSuccess`; yang dipertahankan tabnya, bukan angka yang sudah terpakai.
- **`v-show`, bukan `v-if`, untuk ketiga panel.** `aria-controls` selalu menunjuk elemen yang benar-benar ada, dan angka yang sudah diketik di formulir kapasitas tidak hilang ketika seseorang melirik tab Tagihan lalu kembali.
- **Tiga kalimat yang menunjuk arah ikut dibetulkan**, karena "di bawah" tidak lagi berarti apa-apa: catatan pemulihan `already_invoiced`, peringatan kursi penuh, dan penolakan penambahan pengguna yang tertahan sekarang menyebut nama tabnya. Peringatan kursi penuh sekalian dibuat sadar-peran — sebelumnya ia menyuruh SEMUA orang "tambah pengguna di bawah", padahal panelnya sejak dulu hanya untuk owner.
- **Tombol baris tagihan berjajar mulai `sm`**, menggantikan dua tombol `block w-full` yang bertumpuk dan membuat tiap baris setinggi tiga baris teks.
- **Diperiksa di peramban, sampai susunan empat-tab.** Sebagai `owner@sapi.test` di 1.440×900 dan 375×812: tiap tab dibuka satu per satu, `aria-controls` menunjuk panel yang ada, panah kiri/kanan + Home + End memindahkan tab dan fragmennya ikut, `/langganan#kapasitas` benar saat muat dingin, dan mengganti fragment dari halaman terbuka ikut memindahkan tab. Lencana tab Tagihan dan pasangan tombol bayar/unggah bukti diperiksa dengan menandai satu tagihan dev jadi `unpaid` sementara, lalu dikembalikan ke `paid` beserta `paid_at` aslinya. **Belum** diperagakan: susunan tiga-tab terakhir (persetujuan naik ke baris kepala, Harga Adaptif pindah ke Ringkasan) — sesi login dev keburu kedaluwarsa; yang sudah lolos untuknya baru `vite build` dan `tests/Feature/Subscription`. Juga belum: bertahannya tab melewati POST beli/lepas kursi, karena mencobanya berarti menerbitkan tagihan di basis data dev.
- **File Terdampak:**
  - `resources/js/Pages/Billing/Show.vue` — `tabs`/`activeTab`/`focusTab` + sinkronisasi fragment, baris kepala keadaan+persetujuan, `role="tablist"` tiga tab dengan lencana tagihan, tiga `role="tabpanel"`, pasangan kartu `lg:grid-cols-2` / `md:grid-cols-2`, `preserveState` di semua POST, tiga kalimat penunjuk arah
- **Catatan Migrasi:** Tidak ada.

---

### [ADDITION] Bayar Tagihan Langganan Selesai di Satu Modal — Kata Sandi Owner sebagai Ganti Aplikasi Bank
- **Tanggal:** 2026-09-03
- **Fase Terkait:** Di Luar Fase — kebutuhan peragaan/pitching, diminta pemilik. Bukan penggantian `[BL-059]`.
- **Dampak:** Controller | Route | Frontend | Test
- **Breaking Change:** Tidak. Alur tiga halaman (`Billing/Pay` → `Billing/PaymentInstruction`) tetap ada, tetap berutas sama, dan tetap diuji; yang bertambah hanya satu pintu di sampingnya.
- **Deskripsi:** Tombol "Bayar sekarang" di daftar tagihan halaman langganan tidak lagi berpindah halaman. Ia membuka modal berisi tiga langkah di tempat: pilih kanal → ketik kata sandi akun sendiri → layar "memproses" → tagihannya lunas di baris yang sama.
- **Kenapa ada, padahal `[BL-059]` sudah bekerja.** Alur tiga halaman itu dibangun untuk berbentuk sama dengan penyedia sungguhan, dan untuk tujuan itu ia benar. Yang tidak bisa dilakukannya adalah menjawab pertanyaan yang muncul di ruang rapat — "kalau saya bayar sekarang, seperti apa?" Di sana tidak ada aplikasi bank untuk memindai QR, tidak ada delapan detik untuk menunggu pelunasan otomatis, dan dua kali pindah halaman adalah dua kesempatan perhatian penonton hilang. Memasang penyedia sungguhan hanya demi bisa memperagakannya berarti mengurus administrasi PG sebelum ada satu klien pun yang membayar.
- **Kata sandinya bukan hiasan, dan itu satu-satunya alasan pintu ini pantas ada.** Ia diperiksa di server dengan `Hash::check()` terhadap akun yang sedang masuk, dan rutenya `role:owner` — jadi yang ditanya selalu pemilik usaha. Ia menggantikan otentikasi yang di dunia nyata terjadi di aplikasi bank. Tanpa satu langkah pun yang menuntut sesuatu yang hanya diketahui pemiliknya, yang dipasang di halaman langganan cuma tombol yang melunasi tagihan — persis benda yang `[BL-061]` cabut.
- **Pelunasannya tetap tidak terjadi di controller ini.** Sama seperti `simulate()`, `checkout()` menyusun notifikasi bertanda tangan lewat `FakeGateway::callbackRequest()` lalu menyerahkannya ke `PaymentWebhookController`. Verifikasi tanda tangan, penjaga idempotensi, pemeriksaan nominal, tenggat, dan jejak `payments.settled` semuanya tetap dilewati; `InvoiceSettlement` tetap satu-satunya pintu menuju `active`. Jalur pintas yang melunasi sendiri hanya akan membuktikan bahwa jalur pintasnya bekerja.
- **404 di luar driver tiruan, bukan 403.** Penyedia sungguhan tidak punya "bayar dengan kata sandi", jadi alamatnya harus ikut hilang begitu `PAYMENT_DRIVER` bukan `fake` — bukan sekadar tombolnya berhenti dirender. Menjawab "terlarang" justru memberi tahu penanyanya bahwa ada sesuatu di sini yang bisa dibuka. Gerbang produksi di `PaymentGatewayManager` tetap lapis pertamanya.
- **Rutenya dibatasi lajunya (`throttle:8,1`).** Ini satu-satunya rute tenant yang menerima kata sandi di luar layar masuk; tanpa pembatas, ia jadi tempat paling nyaman untuk menebak kata sandi owner berulang kali. Kata sandi juga diperiksa **sebelum** instruksi bayar dibuat, sehingga tebakan yang salah tidak meninggalkan tumpukan nomor transaksi yang menganggur.
- **Penantiannya sengaja tidak dipotong.** Server menjawab dalam puluhan milidetik, dan pembayaran yang selesai secepat itu terbaca sebagai tombol, bukan sebagai pembayaran. Modalnya menahan hasilnya sampai `MINIMUM_PROCESSING_MS` (2.400 ms) lewat sambil menjalankan tiga kalimat status yang menyebut nama kanal yang dipilih — supaya pilihan kanal di atasnya terasa berakibat sesuatu. Ini murni lapisan tampilan; server tidak menunggu apa pun.
- **Jawaban 200 tidak otomatis dirayakan.** Tagihan yang keburu lunas lewat jalur lain kembali dengan flash galat dan tanpa galat validasi. Modalnya memeriksa `flash.error` sebelum berpindah ke layar berhasil; merayakan yang tidak terbayar adalah satu-satunya hal yang tidak boleh dibohongi di layar ini.
- **Instruksi yang masih hidup dipakai ulang**, alasannya sama dengan `create()`: satu tagihan tidak boleh punya dua nomor transaksi yang sama-sama ditunggu. Kanal yang baru dipilih boleh berbeda dari kanal instruksi lama — yang dilunasi tagihan yang sama dengan nominal yang sama.
- **Kanal ikut dikirim di prop `payment` halaman langganan.** Tanpa itu modalnya harus mengunjungi halaman lain dulu untuk tahu kanal apa saja yang tersedia — dan sekali ia berpindah halaman, ia bukan modal lagi. Aman di-resolve di sana karena dijaga `payment.enabled`: `PaymentGatewayManager::has()` sudah menjawab false untuk driver tiruan di produksi, sehingga `driver()` yang melempar exception tidak pernah terpanggil.
- **Diperagakan sungguhan, bukan cuma diuji.** Alurnya dijalankan di peramban atas basis data dev dengan `owner@sapi.test`: kata sandi salah → medan merah, tidak ada instruksi yang terbit; kata sandi benar → tiga kalimat status → layar berhasil → baris tagihannya berubah "Lunas" tanpa muat ulang manual. Data dev yang dipakai untuk itu dikembalikan ke keadaan semula setelahnya.
- **File Terdampak:**
  - `app/Http/Controllers/Billing/PaymentController.php` — `checkout()`; docblock kelas menyebut pintu keempat
  - `app/Http/Controllers/Billing/SubscriptionController.php` — prop `payment.channels`
  - `routes/web.php` — `POST /langganan/tagihan/{invoice}/bayar-cepat`, `role:owner` + `throttle:8,1`
  - `resources/js/Components/BillingCheckoutModal.vue` — baru
  - `resources/js/Pages/Billing/Show.vue` — tombol pembuka modal menggantikan `<Link>`, render modal
  - `tests/Feature/Subscription/PaymentGatewayTest.php` — 9 tes: lunas lewat webhook, kata sandi salah, kanal tak dikenal, kasir & tenant lain, tagihan yang sudah lunas, pakai ulang instruksi, tenant tertangguh, 404 di luar driver tiruan, dan kanal di prop halaman
- **Catatan Migrasi:** Tidak ada. Pintu ini hidup selama `PAYMENT_DRIVER=fake`; menyetelnya ke penyedia sungguhan menghilangkan tombol **dan** alamat rutenya sekaligus, tanpa perubahan kode.

---

### [ADDITION] Tenant yang Ditangguhkan Punya Jalan Pulang — Satu Tagihan Pemulihan, Diminta Sendiri (BL-051)
- **Tanggal:** 2026-08-31
- **Fase Terkait:** Di Luar Fase — `[BL-051]`, dibuka 2026-08-07 sebagai **keputusan pemilik**, bukan pekerjaan kode. Keputusannya jatuh 2026-08-31: **opsi (ii)**.
- **Dampak:** Service | Controller | Route | UI | Test
- **Breaking Change:** Tidak. Tak ada perilaku lama yang berubah; yang ada hanyalah satu jalan yang sebelumnya tidak pernah ada.
- **Deskripsi:** Tenant berstatus `suspended` kini bisa meminta sendiri satu tagihan pemulihan lewat tombol di halaman langganan. Tagihannya lahir karena tenant memintanya, bukan karena kalender; melunasinya membawa tenant kembali ke `active` lewat jalur pelunasan yang sudah ada.
- **Masalah yang ditutupnya.** Penerbit otomatis menagih `trial`, `active`, dan `grace`; `suspended` sengaja di luar, karena menerbitkan tagihan atas bulan yang tak bisa dipakai berarti menumbuhkan utang yang tak pernah diminta siapa pun. Konsekuensinya hanya terlihat dari **arah sebaliknya**: sekali tertangguh tanpa tagihan terbuka — buktinya pernah ditolak lalu tak pernah diselesaikan, atau ia tertangguh sebelum tarifnya pernah ditetapkan — tak ada apa pun yang bisa ia bayar untuk pulih. `current_period_end` beku, penerbit tak menyentuhnya, dan satu-satunya pintu adalah pemilik SaaS mengetikkan tagihannya manual di `/platform/invoices`. Itu persis keadaan yang `[BL-044]` tutup, hanya bergeser satu status ke kanan.
- **Kenapa opsi (ii), bukan (i) atau (iii).** Ketiganya masuk akal dan artinya berbeda. Penerbitan otomatis penuh (iii) berarti tenant yang **sudah pergi** terus menerima tagihan bulanan seumur hidup — utang yang tumbuh di belakang punggung orang yang tak pernah memintanya. Tetap manual (i) berarti setiap tenant yang ingin kembali harus menghubungi manusia lebih dulu. Opsi (ii) meminjam bentuk yang sudah ada di aplikasi ini — tenant menekan sesuatu, tagihannya terbit — dan memindahkan pemicunya dari kalender ke tenant itu sendiri.
- **SATU tagihan, dan penjaganya bukan niat baik.** `[BL-051]`(b) melarang menerbitkan satu tagihan per bulan yang terlewat, karena aturan "tunggakan tidak ditumpuk" di `renewPeriod()` memulihkan tepat satu periode per pembayaran: begitu ada dua tagihan langganan terbuka, melompati periode berarti benar-benar melompati uang. Yang menegakkannya di sini bukan kode baru melainkan pilihan periodenya — tagihan pemulihan memakai `current_period_end` yang **beku**, sehingga kunci `(tenant_id, period, kind)`-nya sama persis dengan yang sudah dijaga penerbit massal, dan penjaga periode-ganda menolak yang kedua. Docblock `renewPeriod()` sendiri menyebut "penerbitan yang ikut berjalan untuk tenant `suspended`" sebagai salah satu dari dua hal yang bisa mematahkan aturan itu; docblock-nya diperbarui untuk menjelaskan kenapa yang ini justru tidak.
- **Pemulihannya tidak ditulis dua kali.** `InvoiceSettlement::settle()` sudah membawa tenant ke `active` dan memajukan periodenya — satu-satunya pintu menuju keadaan itu. Tak ada satu baris pun di jalur baru ini yang menulis `Tenant::STATUS_ACTIVE`; menambah jalur kedua berarti tenant bisa pulih tanpa uangnya masuk.
- **Empat penolakan, empat kalimat berbeda.** Tombolnya mati — dan halaman menyebut alasannya — saat: tagihan periode itu sudah ada (termasuk yang `rejected`, yang masih bisa dibayar dan diunggahi bukti baru; tenantnya tidak buntu, ia hanya belum menyelesaikannya), tarifnya tidak bisa dihitung, totalnya nol, atau ringkasan omzet penentu tarif Adaptif belum ada. Tiga yang terakhir adalah tempat **opsi (i) tetap berlaku**: yang menghalangi tenant itu bukan uang, jadi tak ada tagihan yang bisa menjawabnya, dan ia diarahkan menghubungi pengelola. Keadaannya diputuskan di server dan dikirim ke halaman sebagai status — bukan boolean — supaya tombol yang mati tidak membuat tenant menebak, sama seperti `subsidy.reason` di halaman yang sama.
- **Kesiapan ringkasan omzet diperiksa dengan alasan yang sama seperti di penerbit massal** (`[BL-080]`): tanpa ringkasan, penetapan harga menjatuhkan tenant Adaptif ke paket penampung, dan tagihan yang lahir di jalur itu bukan tagihan yang tertunda melainkan tagihan yang **terlalu mahal**. Menagih terlalu mahal seorang tenant yang baru saja meminta jalan pulang adalah arah kesalahan yang paling merugikan.
- **Satu refactor yang dituntut oleh lahirnya penerbit kedua.** Urutan tarif → seat → kuota AI → pecahan `billing_breakdown` diangkat keluar dari `issueDuePeriodInvoices()` menjadi `draftSubscriptionInvoice()`, dipakai kedua penerbit. Yang dijaga bukan kerapian melainkan uang: dua penerbit yang menyalin urutan itu pasti bercabang begitu salah satunya diperbaiki, dan cabang di jalur uang adalah jenis kesalahan yang paling lama tidak terlihat. Yang **tidak** ikut diangkat adalah hal-hal yang memang berbeda antar penerbit — penghitung, jejak audit, pemberitahuan, dan pembedaan "tertunda" versus "lewat tenggat".
- **Jejaknya menyebut siapa yang meminta.** `platform_user_id` selalu null pada kejadian ini — tak ada orang platform yang menekan tombolnya — jadi `invoices.reactivation` mencatat `requested_by`. Tanpa itu jejaknya menyisakan tagihan yang seolah lahir sendiri.
- **Rutenya diberi nama `billing.reactivate`** supaya tercakup `ALWAYS_ALLOWED` di `EnsureSubscriptionActive`. Tanpa awalan itu, rutenya akan ditolak persis oleh keadaan yang hendak ia akhiri.
- **Belum pernah dilihat di layar sungguhan.** Tidak ada satu pun tenant berstatus `suspended` di basis data dev — itu juga alasan `[BL-051]` berprioritas Low sejak dibuka. Yang membuktikannya sepuluh tes, termasuk lingkaran penuhnya: minta → bayar → `active` dengan periode yang mendarat di masa depan.
- **File Terdampak:**
  - `app/Services/SubscriptionService.php` — `issueReactivationInvoice()`, `reactivationStateFor()`, `draftSubscriptionInvoice()` (diangkat dari `issueDuePeriodInvoices()`); docblock `renewPeriod()` dan `issueDuePeriodInvoices()` diperbarui
  - `app/Http/Controllers/Billing/ReactivationController.php` — baru
  - `app/Http/Controllers/Billing/SubscriptionController.php` — prop `reactivation`
  - `routes/web.php` — `POST /langganan/aktifkan-kembali`, digerbang `role:owner`
  - `resources/js/Pages/Billing/Show.vue` — panel pemulihan, tepat di bawah kartu keadaan
  - `tests/Feature/Subscription/ReactivationTest.php` — 10 tes baru
- **Catatan Migrasi:** Tidak ada migrasi. Tenant `suspended` yang sudah punya tagihan terbuka tidak terpengaruh sama sekali — jalur ini hanya menyala ketika tidak ada yang bisa dibayar.

---

### [HOTFIX] Periode `Y-m` Berhenti Memungut Tanggal Hari Ini — Tagihan Juni Tidak Lagi Dihargai Aturan Juli
- **Tanggal:** 2026-08-31
- **Fase Terkait:** Di Luar Fase — ditemukan saat `[BL-094]` dikerjakan, dari lima tes yang gagal dan **tidak** disebabkan olehnya
- **Dampak:** Service | Job | Test
- **Breaking Change:** Tidak. Yang berubah hanya cara satu string `YYYY-MM` diurai; pada 28 dari 31 hari hasilnya memang sudah sama.
- **Deskripsi:** `Carbon::createFromFormat('Y-m', $period)` diganti `Carbon::createFromFormat('Y-m-d', $period.'-01')` di dua tempat: `SubscriptionService::pricingAsOf()` dan `ComputeTenantMonthlyRevenue::periodOrLastClosedMonth()`.
- **Alasan:** `createFromFormat` mengisi satuan yang **tidak disebut formatnya** dari hari ini — termasuk tanggalnya. Dijalankan pada tanggal 31 atas bulan berisi 30 hari, `2026-06` menjadi `2026-06-31` yang tidak ada, dinormalkan Carbon jadi `2026-07-01`. `startOfMonth()` yang menyusul sudah terlambat: ia merapikan bulan yang **salah**.

  ```
  // pada 2026-08-31
  Carbon::createFromFormat('Y-m', '2026-06')->startOfMonth()  =>  2026-07
  ```

- **Ini bukan tes yang rewel, dan itu bagian terpentingnya.** Keduanya jalur uang:
  - `pricingAsOf()` adalah **titik waktu penetapan harga** sebuah periode tagihan, dipakai bersama oleh penerbit otomatis dan penerbit manual di `Platform\InvoiceController`. Meleset ke bulan berikutnya berarti tagihan Juni dihargai dengan aturan harga yang baru berdiri di bulan Juli — persis yang docblock-nya sendiri berjanji tidak akan terjadi ("aturan yang mulai berlaku di tengah bulan tidak boleh mengubah harga bulan yang sudah berjalan").
  - `periodOrLastClosedMonth()` menentukan bulan yang dihitung `subscriptions:compute-revenue --period`. Barisnya masuk `tenant_monthly_metrics`, dan tabel itu masukan bracket harga Adaptif — jadi perhitungan ulang yang dijalankan pada tanggal 31 menulis omzet bulan lain di bawah label periode yang diminta.
- **Ironinya: luberan ini sudah pernah dipikirkan, di berkas yang sama.** Docblock tepat di atas `periodOrLastClosedMonth()` memperingatkan `[BL-029]` dengan gamblang — "31 Juli − 1 bulan = 31 Juni yang tidak ada" — dan menyuruh `startOfMonth()` dulu baru `subMonth()`. Peringatan itu benar, dan ia hanya menutupi cabang `now()`. Cabang `--period` di baris berikutnya meluber lewat mekanisme yang berbeda, dan lolos justru karena peringatannya terlihat sudah menjaga tempat itu.
- **`Owner\ReportController` sudah memakai bentuk yang benar sejak awal** (`'Y-m-d', $month.'-01'`). Ketiga situs pengurai periode di basis kode ini kini seragam; yang lain hanya mem-`format('Y-m')` ke luar, dan arah itu tidak pernah meluber.
- **Empat penjaga baru, seluruhnya dipatok tanggalnya.** Cacat ini hidup selama sebelas bulan setiap tahun karena tesnya memakai `now()`: ia hanya gagal pada tanggal 31, dan hanya untuk bulan yang lebih pendek. Yang baru memakai `travelTo`/`setTestNow` ke 31 Juli, jadi gagal **setiap hari** bila cacatnya kembali — bukan sekali dalam dua belas. Salah satunya menguji Februari, bentuk terparahnya: luberannya tiga hari, jadi periodenya mendarat di Maret. Keempatnya **dibuktikan gagal lebih dulu** dengan mengembalikan `createFromFormat('Y-m', ...)`.
- **Yang diperiksa dan sengaja TIDAK diubah:** tes lama `periode bisa dipilih untuk menghitung ulang bulan tertentu` dibiarkan apa adanya. Ia tetap bergantung `now()`, tapi bukan lagi satu-satunya yang menjaga tempat itu — dan mengubahnya berarti kehilangan satu tes yang memang menjalankan jalur ini pada tanggal sungguhan.
- **Kelima tes yang gagal itu ternyata bukan satu cacat, melainkan dua — dan yang kelima BUKAN cacat produksi.** Empat di antaranya jatuh dari luberan `Y-m` di atas. Yang kelima (`PlatformBillingTest`) jatuh karena `SubscriptionFactory` menurunkan `billing_anchor_day` dari `now()->addMonthNoOverflow()`: dijalankan pada tanggal 31, jangkarnya lahir **sudah terjepit** jadi 30, sementara harapan tesnya (`now()->addMonthsNoOverflow(2)`) tetap mengira 31. Kode penerbitnya benar untuk jangkar 30; yang keliru adalah pintasan di tesnya. Jamnya dipatok ke tanggal netral, karena yang diuji berkas itu adalah verifikasi-mengaktifkan-mengunci — penjepitan sudah punya penjaganya sendiri di `SubscriptionBillingDateTest` (`[BL-030]`). Dipisahkan supaya tidak terbaca seolah tagihan pernah salah karenanya: tidak pernah.
- **File Terdampak:**
  - `app/Services/SubscriptionService.php` — `pricingAsOf()`
  - `app/Jobs/ComputeTenantMonthlyRevenue.php` — `periodOrLastClosedMonth()`
  - `tests/Feature/Subscription/SubscriptionBillingDateTest.php` — 2 tes baru
  - `tests/Feature/Subscription/ComputeTenantMonthlyRevenueTest.php` — 2 tes baru, di bagian `[BL-029]` yang sudah ada
  - `tests/Feature/Platform/PlatformBillingTest.php` — jam dipatok; tidak ada kode produksi yang berubah karenanya
- **Catatan Migrasi:** Tidak ada migrasi. **Namun baris `tenant_monthly_metrics` yang pernah ditulis `--period` pada tanggal 31 berisi omzet bulan yang salah** dan tidak diperbaiki sendiri oleh perubahan ini. Jalankan ulang `php artisan subscriptions:compute-revenue --period YYYY-MM` untuk periode yang dicurigai; `updateOrCreate` akan menimpanya dengan angka yang benar. Jadwal bulanan otomatis tidak terdampak — ia memakai cabang `now()`, yang tidak pernah punya cacat ini.

---

### [DECISION] Halaman Vue Berhenti Dikirim Berombongan — Satu Bundel 1.136 KB Jadi Chunk per Halaman (BL-094)
- **Tanggal:** 2026-08-31
- **Fase Terkait:** Di Luar Fase — `[BL-094]`, yaitu butir (c) `[BL-091]` yang sengaja dipisahkan saat entri itu dikerjakan. Entrinya selesai
- **Dampak:** Frontend | Build | Test
- **Breaking Change:** Tidak ada perubahan API, rute, maupun data. Yang berubah adalah **cara setiap halaman dimuat** — resolusi komponen kini asinkron — dan itu diperlakukan sebagai perubahan perilaku, bukan sebagai optimasi diam-diam.
- **Deskripsi:** `resources/js/app.js` berpindah dari `import.meta.glob('./Pages/**/*.vue', { eager: true })` ke glob malas dengan `resolvePageComponent` dari `laravel-vite-plugin/inertia-helpers`. Vite memecah 56 halaman jadi chunk masing-masing, dan entry-nya turun dari **1.136 KB jadi 264 KB** (−77%).
- **Alasan:** `[BL-091]` sudah membebaskan pengunjung yang belum punya akun dari bundel itu. Yang tersisa ditanggung pengguna yang sudah masuk: kasir yang seharian hanya membuka satu layar tetap mengunduh panel platform, laporan, dan langganan pada muat pertama.

- **Butir (b) entrinya dijalankan apa adanya: diukur, bukan diasumsikan.** Entrinya memperingatkan bahwa 56 permintaan kecil pada sambungan lambat bisa lebih buruk daripada satu bundel besar yang sudah di-cache. Angka sebenarnya sesudah build (`public/build/manifest.json`, ukuran mentah, entry + seluruh graf impor statis halamannya):

  | | Sebelum | Sesudah |
  |---|---|---|
  | Entry | 1.136 KB (1 berkas) | 264 KB (1 berkas, tanpa impor statis) |
  | Halaman teringan | 1.136 KB / 1 permintaan | 266 KB / 2 permintaan (`Auth/VerifyEmail`) |
  | Halaman median | 1.136 KB / 1 permintaan | 309 KB / 11 permintaan (`Owner/Stock/Movements`) |
  | Halaman terberat | 1.136 KB / 1 permintaan | 493 KB / 15 permintaan (`Owner/Dashboard`) |
  | Rata-rata permintaan | 1 | 9,6 |

  Angka "sebelum" adalah build yang ada di disk tepat sebelum perubahan ini (`app-TcvBsAwx.js`, 1.162.860 byte). `[BL-091]` dan `[BL-094]` menyebut 1.117 KB — itu pengukuran build yang lebih lama, dan bundelnya memang terus tumbuh selama ia masih menampung setiap halaman baru. Perbedaannya bukan koreksi; ia justru memperlihatkan sifat bundel yang tidak dipecah.

  Kekhawatiran "56 permintaan" tidak terjadi: paling banyak **15**, rata-rata **9,6**, dan Vite memuatnya paralel lewat `__vitePreload`, bukan berantai. Halaman **terberat** pun hanya 43% dari bundel lama.

- **Butir (c) — bilah kemajuan `[BL-037]` — diperiksa, dan `delay: 500` sengaja TIDAK diubah.** Inertia menunggu `resolve` selesai sebelum menukar halaman (`CurrentPage.resolve()` di `@inertiajs/core`), jadi unduhan chunk kini berada **di dalam** jendela bilah itu. Konsekuensi yang nyata: pada cache dingin kerangka pemuatan tidak lagi yang pertama sampai — ia baru berdiri sesudah chunk-nya tiba. Yang tidak berubah adalah kesimpulannya: pada sambungan wajar tambahannya puluhan milidetik dan bilah tetap tak sempat lahir, sedangkan pada sambungan lambat bilah inilah yang memang dibutuhkan. Yang diubah adalah **komentarnya**, supaya alasan "jarang terlihat" tidak lagi bertumpu pada keadaan yang sudah tidak berlaku.
- **Mode offline diperiksa terpisah, dan tidak rusak.** `public/sw.js` melayani `/build/assets/**` secara cache-first tanpa daftar precache, jadi chunk halaman masuk `ASSET_CACHE` pada muat online pertamanya — syarat yang sama persis dengan HTML halamannya sendiri di `PAGE_CACHE`. Satu-satunya rute yang memang offline-capable (`/cashier/pos`) menarik 17 berkas, dan seluruhnya sudah ter-cache saat halaman itu terakhir dibuka online. `CACHE_VERSION` **tidak dinaikkan**: nama berkasnya ber-hash, jadi tidak ada entri lama yang menunjuk berkas yang hilang.
- **Diverifikasi di peramban terhadap hasil build, bukan dev server.** `public/hot` disingkirkan supaya `@vite` merender berkas ber-hash. Login → `Owner/Dashboard` (halaman terberat, 15 chunk) → perpindahan Inertia ke `Owner/Products` → `Cashier/POS`: seluruhnya mount, kerangka pemuatan tetap muncul, **nol error konsol**. Perpindahan ke `Owner/Products` menarik 4 chunk baru secara paralel.
- **Penjaganya dibuktikan gagal lebih dulu.** `eager: true` dikembalikan ke globnya, tes ditolak di baris yang benar dengan pesan yang menyebut jalan keluarnya, lalu keadaan dikembalikan. Ini bentuk kemunduran yang paling mungkin terjadi: satu kata yang disalin kembali, tanpa satu pun error dan tanpa satu pun halaman rusak — persis cara `[BL-091]` bertahan berbulan-bulan.
- **Dua dari tiga penjaganya membaca hasil build, dan sengaja melewati diri bila `public/build` belum ada.** `.gitignore` mengecualikannya, jadi checkout bersih tidak punya apa pun untuk diperiksa. Yang menahan kemunduran di segala keadaan adalah penjaga sumbernya — yang membaca `resources/js/app.js` langsung dan selalu berjalan.
- **Keadaan tes saat entri ini mendarat, ditulis apa adanya: 1.295 lolos, 5 gagal — dan kelimanya BUKAN dari perubahan ini.** Seluruhnya di jalur langganan/tagihan (`PlatformBillingTest`, `AutoInvoiceTest`, `ComputeTenantMonthlyRevenueTest`, `InvoicePostponementTest`), dan entri ini tidak menyentuh satu baris PHP pun — yang berubah hanya `resources/js/app.js`, tiga berkas dokumentasi, dan satu berkas tes baru. Penyebabnya ditemukan saat ditelusuri, dan ia cacat produksi, bukan tes yang rewel: `ComputeTenantMonthlyRevenue::periodOrLastClosedMonth()` memakai `Carbon::createFromFormat('Y-m', $period)`, yang mengisi tanggalnya dari **hari ini**. Dijalankan pada tanggal 31 atas bulan berisi 30 hari, `2026-06` meluber jadi `2026-07-01` dan `startOfMonth()` menghasilkan **Juli**. Jadi `--period 2026-06` pada tanggal 31 menghitung bulan yang salah, dan angkanya adalah masukan bracket harga Adaptif. Ironinya, docblock tepat di atas metode itu sudah memperingatkan luberan yang sama untuk cabang `now()`-nya. Dicatat terpisah, bukan ditambal di sini — ia utang milik jalur tagihan, dan menumpangkannya pada commit frontend justru menyembunyikannya.
- **File Terdampak:**
  - `resources/js/app.js` — glob malas + `resolvePageComponent`; komentar `progress` `[BL-037]` diperbarui untuk model pemuatan yang baru
  - `tests/Feature/AppBundleSplittingTest.php` — **baru**, 3 tes: sumber `app.js` tidak eager, tiap halaman punya chunk sendiri di manifest, entry tidak melewati anggaran 400 KB
- **Catatan Migrasi:** Tidak ada. `npm run build` wajib dijalankan ulang saat deploy — sudah ada di `.github/workflows/deploy.yml`. Nama berkasnya ber-hash, jadi tidak ada cache peramban maupun service worker yang perlu dibatalkan manual.

---

### [ADDITION] Kunci Pajak Punya Jalan Bukanya — Operator Membuka Kuncinya, Bukan Setelannya (BL-065 Butir 4)
- **Tanggal:** 2026-08-29
- **Fase Terkait:** Di Luar Fase — `[BL-065]` butir 4, utang yang tertinggal sejak pajak mendarat 2026-08-28
- **Dampak:** Migration, Model, Controller, Route, Service, Frontend
- **Breaking Change:** Tidak — kolom baru nullable, dan tenant yang tidak pernah dibukakan kuncinya berperilaku persis seperti sebelumnya. Seluruh 1.297 tes lolos.
- **Deskripsi:**
  `TaxSettingsController` sudah menyuruh pemilik toko "hubungi operator untuk membukanya" sejak pajak mendarat — tanpa operator punya apa pun untuk menjawab. Janji tanpa mekanisme lebih buruk daripada larangan yang jujur, dan inilah mekanismenya.

  Rincian tenant di konsol platform kini menampilkan keadaan pajak toko beserta kuncinya, dengan satu tombol: **buka kunci**. Pembukaan menuntut alasan tertulis, tercatat di jejak audit sebagai kejadian sensitif lengkap dengan keadaan pajak sebelum dibuka, dan bisa ditutup kembali sebelum jendelanya habis.
- **Keputusan yang membentuknya:**
  1. **Yang dibuka adalah KUNCINYA, bukan setelannya.** Tidak ada satu pun jalur di controller ini yang menulis `tax_enabled`, `tax_mode`, `tax_rate`, atau `tax_label`. Operator mengembalikan kemampuan pemilik toko memilih; yang memilih tetap pemilik toko, di layarnya sendiri.

     Pembedaan ini bukan kerapian. `TenantController` pernah punya kuasa mengubah `business_type` tenant dan kuasa itu **dicabut** (`[BL-015]`), dengan alasan yang berlaku persis sama di sini: cara kerja usaha orang bukan milik penyedia layanan, sekalipun nilainya ikut menentukan tarif. Yang diberikan konsol platform adalah kuasa mengetahui, bukan kuasa memutuskan.
  2. **Controller-nya terpisah dari `TenantController`.** Docblock di sana menyatakan rincian tenant "read-only, tanpa kecuali", dan menyelipkan satu tulisan ke dalamnya akan membuat pernyataan itu tidak lagi benar. `TenantTaxLockController` hidup sendiri supaya kalimat itu tetap bisa dibaca apa adanya.
  3. **Berupa jendela waktu, bukan boolean.** Kunci yang dibuka tanpa batas adalah kunci yang mati: kalau pemiliknya lupa memakainya hari itu, tidak ada yang menutupnya kembali dan lubang yang penguncian ini hindari terbuka diam-diam berbulan-bulan. **Tujuh hari** — operator dan pemilik toko jarang duduk di meja yang sama, dan jendela yang habis sebelum pemiliknya sempat membuka aplikasinya hanya menghasilkan permintaan kedua.
  4. **Sekali pakai.** Jendelanya habis begitu perubahan yang membutuhkannya benar-benar tersimpan. Satu pembukaan untuk satu perubahan; yang kedua butuh keputusan baru. Menyimpan **tarif saja** — yang memang tidak pernah terkunci — sengaja tidak menghabiskannya: pemilik yang membetulkan tarif sambil menimbang modenya tidak boleh kehilangan kesempatan yang baru diberikan kepadanya.
  5. **Alasan wajib ditulis, minimal sepuluh karakter.** Pembukaan tanpa alasan adalah baris audit yang tidak bisa menjawab pertanyaan yang membuatnya dicatat — pola yang sama dengan penjualan di bawah lantai margin (`[BL-018]`).
  6. **Digerbang `platform.can:tenants` saja** — lebih sempit daripada halaman yang menampungnya, yang menerima tiga modul. Membuka kunci adalah kewenangan mengurus tenant, bukan menagihnya; pemegang modul tagihan yang sampai ke halaman ini dari daftar langganan tetap melihat keadaan kuncinya, tanpa tombolnya.
  7. **Layar pemilik menyebutkan batas waktunya.** Kesempatan yang tidak menyebutkan kapan habisnya akan dikira berlaku selamanya. `locked` dan `lock_opened_until` dikirim sebagai dua field terpisah, bukan satu boolean gabungan: "terkunci" dan "sedang dibukakan" adalah dua fakta berbeda.
- **Yang TIDAK dikerjakan:**
  - **Tidak ada notifikasi ke pemilik toko.** Ia mengetahuinya saat membuka layar Pengaturan. Untuk alur yang dimulai dari teleponnya sendiri, itu cukup — pemberitahuan yang benar baru perlu kalau pembukaan bisa terjadi tanpa ia meminta.
  - **Tidak ada modul platform baru.** Menambah entri ke katalog berarti izin baru yang harus dicentang ulang untuk tiap staf; `tenants` sudah kewenangan yang tepat.
- **File Terkait:**
  - `database/migrations/2026_08_29_203953_add_tax_lock_window_to_tenants_table.php`
  - `app/Http/Controllers/Platform/TenantTaxLockController.php` — buka & tutup, teraudit
  - `app/Models/Tenant.php` — `taxLockOpen()`, `taxSettingsEditable()`
  - `app/Http/Controllers/Owner/Settings/TaxSettingsController.php` — menghormati dan menghabiskan jendelanya
  - `app/Services/Platform/AccountOverview.php`, `resources/js/Pages/Platform/Tenants/Show.vue` — panel pajak & kuncinya
  - `resources/js/Pages/Owner/Settings/Operations.vue` — batas waktu di layar pemilik
  - `tests/Feature/Platform/PlatformTenantDetailTest.php`, `tests/Feature/Owner/TaxSettingsTest.php` — 11 test baru

---

### [DECISION] Margin Diukur terhadap Pendapatan Toko, Bukan terhadap Pajak yang Menumpang di Atasnya (BL-065)
- **Tanggal:** 2026-08-29
- **Fase Terkait:** Di Luar Fase — pertanyaan terbuka terakhir `[BL-065]` yang menyangkut angka, diputuskan pemilik setelah butir (e) mendarat
- **Dampak:** Service, Job, MCP
- **Breaking Change:** Tidak untuk tenant tanpa pajak — backfill menjamin `subtotal_amount = total_amount` di seluruh masa sebelum pajak ada, jadi angkanya identik. Payload profit melebar (`net_revenue`, `tax`); konsumennya hanya konteks analisis AI dan MCP `GetProfitTool`, keduanya internal. Seluruh 1.286 tes lolos.
- **Deskripsi:**
  `ProfitService` mengambil `revenue` dari `total_amount`, sehingga margin dihitung terhadap uang yang berpindah tangan alih-alih terhadap pendapatan toko. Sekarang payload membawa ketiganya apa adanya — `revenue` (dibayar pelanggan, arti lamanya **tidak** berubah), `net_revenue` (pendapatan toko), dan `tax` — dengan `gross_profit` dan `margin_pct` diturunkan dari `net_revenue`.
- **Koreksi terhadap catatan di `[BL-065]`:**
  Backlog menulis masalah ini hanya ada di mode **exclusive**. Itu keliru: **kedua mode salah, dan salahnya persis sebesar `tax_amount`.** Pada tarif 11% untuk toko bermargin nyata 30%, keduanya melaporkan **36,9%** — meleset hampir tujuh poin persen.

  Yang berbeda hanya cara ia menipu. Di exclusive, menyalakan pajak membuat margin terlihat *naik* — absurd, tapi mencurigakan. Di inclusive, `total_amount` tidak bergerak sama sekali saat pajak dinyalakan, jadi margin terbaca persis seperti sebelumnya padahal margin sebenarnya baru saja turun. Mode kedua yang lebih berbahaya justru karena tidak ada apa pun di layar yang berubah untuk memancing pertanyaan.
- **Retakan kedua yang ditemukan saat memutuskan:**
  `overallProfit()` memakai `total_amount`, tapi `profitByProduct()` memakai `SUM(transaction_items.subtotal)`. Sebelum pajak ada keduanya identik. Setelahnya tidak: di **exclusive** jumlah baris item sama dengan `subtotal_amount` (bersih), sehingga ringkasan melaporkan 63,96% sementara rincian per produk melaporkan 60% — **untuk periode yang sama, dikirim berdampingan dalam satu payload** oleh `AiContextService`. Di **inclusive** keduanya sepakat dan sama-sama kotor.
- **Keputusan:**
  1. **`revenue` tidak berganti arti.** Ia tetap uang yang dibayar pelanggan, sejalan dengan `total_amount` di seluruh basis kode. Yang ditambahkan adalah `net_revenue` dan `tax` di sebelahnya. Konsekuensinya `revenue - cogs != gross_profit` untuk tenant yang memungut — dan itu memang benar: selisihnya bukan untung yang hilang, melainkan uang yang tidak pernah jadi milik toko.
  2. **Rincian per produk ikut dikoreksi sekarang, bukan ditunda.** Baris mode inclusive diurai dengan tarif yang dibekukan di transaksinya. Hasil penjumlahannya bisa meleset rupiah dari `subtotal_amount` transaksi karena pembulatannya jatuh di tempat lain — dan test mengunci selisih itu apa adanya (45.045,05 per baris vs 45.045,00 per transaksi). `[BL-065]` butir 8 menolak pembulatan per item **untuk struk**, di mana pelanggan memverifikasi angka tercetak sambil berdiri di depan kasir; di sini tidak ada yang memegang selisihnya, dan dua angka yang konsisten satu sama lain lebih berharga daripada rupiah terakhir.
  3. **Model diberi tahu, bukan dibiarkan menebak.** Satu-satunya pembaca angka ini adalah mesin: konteks analisis AI dan MCP `GetProfitTool`. `RunAiAnalysisJob` menambahkan satu kalimat ke system prompt — **hanya** untuk tenant yang memungut — yang menyebutkan mana yang pendapatan toko dan menyuruh menghitung margin dari sana. `#[Description]` milik `GetProfitTool` diperbarui dengan isi yang sama, karena itulah dokumentasi yang dilihat klien MCP eksternal.
  4. **Agregat `others` di konteks AI ikut pindah dasar.** Ekor katalog yang diringkas dengan dasar berbeda dari barisnya akan terbaca lebih sehat daripada isinya.
- **Yang TIDAK dikerjakan:**
  `ProfitService` masih memakai `product_variants.cost_price` **hari ini**, bukan `transaction_items.cost_price_at_sale` yang sudah dibekukan per baris sejak `[BL-018]`. Distorsi kedua pada angka yang sama, sudah tercatat sebagai keterbatasan yang diketahui di docblock kelasnya, dan sengaja dibiarkan di luar keputusan ini.
- **File Terkait:**
  - `app/Services/ProfitService.php` — dasar margin, koreksi inclusive per produk
  - `app/Services/AiContextService.php` — agregat `others`
  - `app/Jobs/RunAiAnalysisJob.php` — kalimat pajak bersyarat di system prompt
  - `app/Mcp/Tools/GetProfitTool.php` — deskripsi payload
  - `tests/Feature/ProfitServiceTest.php` — 5 test baru

---

### [ADDITION] Pajak Terpungut Punya Angkanya Sendiri di Laporan (BL-065 Butir e)
- **Tanggal:** 2026-08-29
- **Fase Terkait:** Di Luar Fase — `[BL-065]` butir **(e)**, sisa terakhir dari pekerjaan pajak yang mendarat di kasir 2026-08-28
- **Dampak:** Controller, Factory, Frontend
- **Breaking Change:** Tidak — bentuk `total_amount` dan artinya tidak disentuh, dan tenant yang tidak memungut pajak tidak melihat satu pun angka baru. Seluruh 1.281 tes lolos.
- **Deskripsi:**
  Pajak sudah dipungut di kasir sejak 2026-08-28, tapi laporan masih menjumlahkan `total_amount` polos — sehingga pemilik yang memungut melihat satu angka omzet yang diam-diam sudah memuat uang titipan pelanggan, dan tidak punya angka kedua untuk menyetorkannya. Laporan harian dan bulanan sekarang menyebut ketiganya terpisah: **omzet sebelum pajak** (pendapatan toko), **pajak terpungut** (yang dititipkan untuk disetorkan), dan **dibayar pelanggan** (uang yang masuk).

  Di mode inclusive pemisahan ini yang pertama kali membuat selisihnya terlihat: pelanggan membayar angka yang sama seperti sebelum pajak menyala, dan yang turun adalah pendapatan toko. Sebelum ini, layar tidak punya tempat untuk mengatakannya.
- **Alasan:**
  Butir (e) adalah satu-satunya butir `[BL-065]` yang tidak ikut mendarat kemarin, dan ia justru yang dipakai untuk membayar pajaknya. Fitur yang memungut tanpa melaporkan berarti menyerahkan pekerjaan penjumlahannya kembali ke pemilik — dengan data yang sudah ada di basis data.
- **Keputusan yang diambil saat mengerjakannya:**
  1. **`total_revenue` TIDAK berubah arti.** Ia tetap "yang dibayar pelanggan". Dua angka baru (`net_revenue`, `tax_collected`) ditambahkan **di sebelahnya**, bukan menggantikannya — dua belas tempat sudah membaca kolomnya dengan arti itu.
  2. **Tenant yang tidak memungut tidak melihat apa pun.** Bagian pajak muncul bila `tax_enabled` menyala **atau** ada pajak yang benar-benar terpungut di periode itu. Syarat kedua yang menjaga periode lampau tetap terbaca kalau sakelarnya kelak dibuka lewat konsol platform. Kolom nol di setiap baris CSV bukan kejujuran, melainkan derau yang harus dibaca ulang tiap bulan oleh mayoritas yang tidak memungut.
  3. **Labelnya diambil dari transaksinya, bukan dari setelan tenant hari ini.** `tax_label` sengaja **tidak** ikut terkunci saat penjualan berpajak pertama (`[BL-065]` butir 6), jadi tenant yang mengganti "PPN" jadi "PB1" bulan lalu tidak boleh membuat laporan lamanya menyebut dasar hukum yang salah. Satu periode dengan dua label menyebut keduanya — menampilkan salah satu berarti memilih sebagian angka lalu menamainya seluruhnya.
  4. **Pajak per hari ikut diagregasi di deret bulanan yang sudah ada**, bukan lewat kueri sendiri: barisnya sama dan penyaringnya sama, dan ringkasan bulan memang sudah diturunkan dari deret itu. Unduhan CSV karenanya mendapat kolom pajak per tanggal tanpa satu pun kueri tambahan.
  5. **`average_transaction` tetap dihitung dari `total_amount`.** Rata-rata belanja adalah berapa yang dikeluarkan pelanggan, dan itu memang termasuk pajaknya.
- **Lubang yang ditemukan dan ditutup di jalan:**
  `TransactionFactory` menulis `total_amount` tanpa `subtotal_amount`, sehingga setiap transaksi buatan pabrik melanggar invarian `subtotal + pajak = total` — subtotalnya tertinggal di 0. Belum ada test yang mengandalkannya, tapi setiap laporan yang menjumlahkan `subtotal_amount` akan membaca nol di sana. Pabriknya kini menurunkan subtotal dari total lewat closure (bawaannya penjualan tanpa pajak, dan di sana subtotal memang sama dengan total), plus state `taxed()` yang menghitung ketiga angkanya lewat `TaxCalculator` yang sama dengan produksi. `$base`-nya jadi argumen, bukan dibaca dari `total_amount`: atribut yang diberikan ke `create()` menimpa state, dan `total_amount` yang dititipkan di sana akan menyisakan pajak dari angka acak bawaan pabrik.
- **Yang TIDAK dikerjakan:**
  - **`ProfitService` belum diputuskan** — masih menghitung margin terhadap `total_amount`, yang di mode exclusive membuat margin tampak lebih besar dari kenyataan. Pertanyaan ini milik `[BL-065]` dan sengaja dibiarkan terbuka di sana; ia terpisah dari dasar penagihan dan boleh dijawab berbeda.
  - **Dashboard tidak disentuh.** Butir (e) menyebut laporan harian dan bulanan; dashboard adalah layar sekilas, bukan dasar setoran.
  - **Jalan buka kunci di konsol platform belum ada.** `TaxSettingsController` sudah menyuruh tenant "hubungi operator", tapi operatornya belum punya tombolnya — tetap terbuka di `[BL-065]` butir 4.
- **File Terkait:**
  - `app/Http/Controllers/Owner/ReportController.php` — dua angka di ringkasan harian/bulanan, `taxContext()`, kolom pajak di CSV
  - `database/factories/TransactionFactory.php` — invarian subtotal dan state `taxed()`
  - `resources/js/Pages/Owner/Reports/Daily.vue`, `Monthly.vue` — panel pajak
  - `tests/Feature/Owner/ReportTest.php` — 7 test baru

---

### [SCHEMA] Pajak Masuk ke Kasir — Uang Transaksi Berhenti Jadi Satu Angka (BL-065)
- **Tanggal:** 2026-08-28
- **Fase Terkait:** Di Luar Fase — `[BL-065]`, dikerjakan setelah kedelapan keputusan bentuknya diambil pemilik (commit `aa1aceb`)
- **Dampak:** Migration, Model, Service, Controller, Route, Frontend
- **Breaking Change:** Tidak — `total_amount` tetap berarti "yang dibayar pelanggan", dan bawaan pajak **mati** sehingga tenant yang tidak menyalakannya tidak melihat perubahan apa pun. Seluruh 1.274 tes lolos.
- **Deskripsi:**
  Tenant kini bisa memungut pajak pada setiap penjualan, dengan dua mode: **exclusive** (pajak ditambahkan di atas harga — yang dibayar pelanggan naik, pendapatan toko tetap) dan **inclusive** (harga katalog sudah mengandungnya — yang dibayar pelanggan tidak berubah, pendapatan toko yang turun). Struk mencetak pembagiannya, dan angkanya bisa dijumlahkan ulang oleh pelanggan yang berdiri di depan kasir.

  `transactions` mendapat `subtotal_amount` dan `tax_amount` di samping `total_amount`, plus tiga kolom yang **membekukan** setelan pajak pada saat penjualan (`tax_rate`, `tax_mode`, `tax_label`) — sehingga struk yang dicetak ulang berbulan-bulan kemudian menghasilkan angka yang sama dengan kertas yang dibawa pulang, walau tarifnya sudah berubah.
- **Alasan:**
  Sebelum ini tidak ada pajak sama sekali — nol kata di seluruh `app/`, `config/`, dan migrasi. Aplikasi mengasumsikan harga jual adalah angka final dan tidak punya cara menyatakan berapa bagian dari angka itu yang sebetulnya milik negara atau daerah. Tenant yang omzetnya melewati Rp 4,8 miliar wajib memungut PPN, dan rumah makan/kafe memungut PBJT daerah; keduanya tidak punya tempat untuk dinyatakan.
- **Keputusan yang menentukan bentuknya** (lengkap dengan alasan menolak alternatifnya di `docs/BACKLOG.md` `[BL-065]`):
  1. **Satu tarif per tenant, bukan per produk** — aritmetika keranjang hidup di banyak tempat, tiga di antaranya JavaScript dan satu berjalan offline. Per-produk bisa ditambahkan kelak tanpa membongkar apa pun (`products.tax_exempt` nullable); kebalikannya tidak.
  2. **Menyalakan pajak selalu boleh**; **mematikan dan menukar mode terkunci** setelah penjualan berpajak pertama — bukan sejak sakelarnya dinyalakan, sehingga tenant yang berubah pikiran sebelum menjual apa pun tidak terjebak.
  3. **Tarif dan label TIDAK dikunci** — tarif memang berubah di dunia nyata (PPN pernah naik 10% → 11%; PBJT beda tiap Perda), dan tarif berbeda tidak membuat angka lama tidak sebanding.
  4. **Label ditanyakan, tidak ditebak dari `business_type`** — mengulangi kesalahan yang baru diperbaiki `[BL-079]`, kali ini dengan hasil tebakan yang tercetak di struk pelanggan.
  5. **Bracket Harga Adaptif tetap memakai `total_amount`** — nol perubahan pada dua belas tempat penjumlahan yang sudah ada. Ongkosnya diterima sadar: tenant mode exclusive ditagih atas ~11% uang yang bukan miliknya. Bisa dibalik kelak tanpa migrasi karena kolomnya sudah ditulis.
  6. **Pembulatan menjaga `subtotal + pajak = total` tepat** — selalu pajak yang dibulatkan ke rupiah penuh, angka ketiga hasil pengurangan. Jangkarnya berbeda per mode: subtotal di exclusive (harga katalog itu nyata), total di inclusive (uang yang berpindah tangan itu nyata).
- **File Terdampak:**
  - `database/migrations/2026_08_28_030340_add_tax_columns_to_tenants_table.php` — `tax_enabled` (default **false**), `tax_mode`, `tax_rate`, `tax_label` (nullable, tanpa default)
  - `database/migrations/2026_08_28_030340_add_tax_columns_to_transactions_table.php` — `subtotal_amount`, `tax_amount`, plus tiga kolom konteks beku; backfill `subtotal_amount = total_amount` (benar untuk seluruh masa sebelum pajak ada, diverifikasi atas 8.185 baris)
  - `app/Services/TaxCalculator.php` — **baru**, satu-satunya tempat aritmetika pajak hidup di sisi PHP
  - `resources/js/support/tax.js` — **baru**, cerminannya di sisi klien; kasir offline mencetak struk sebelum server melihat penjualannya, jadi salinan kedua tak terhindarkan — salinan ketiga dan keempat yang dihindari
  - `app/Services/TransactionService.php` — keempat jalur penulis (checkout, self-order, commit offline) memakai kalkulatornya; di `checkout()` total berpajak menggantikan angka dasar **sebelum** pemeriksaan cukup-bayar
  - `app/Services/TransactionEditService.php` — recalc memakai konteks **beku pada transaksi**, bukan setelan tenant hari ini
  - `app/Http/Controllers/Owner/Settings/TaxSettingsController.php` — **baru**, endpoint tulis tersendiri berikut penguncian
  - `routes/web.php` — `PATCH settings/operations/tax`
  - `resources/js/Pages/Owner/Settings/Operations.vue` — kartu pajak dengan contoh yang menunjukkan **siapa menanggung**, bukan seberapa besar
  - `resources/js/Components/ReceiptModal.vue`, `resources/js/services/escpos.js`, `resources/js/Components/TransactionSuccessModal.vue` — berhenti menjumlahkan baris item sebagai subtotal
  - `resources/js/Pages/Cashier/POS.vue`, `resources/js/composables/useCatalogCache.js`, `app/Http/Controllers/Cashier/POSController.php` — konteks pajak ikut snapshot katalog offline
  - `app/Http/Controllers/Api/V1/Mobile/MobileTransactionController.php` — struk mobile membawa ketiga angkanya
- **Jebakan yang sudah dicatat di `[BL-065]`:** `updateOrCreate` di `ComputeTenantMonthlyRevenue.php:114` akan menimpa metrik bulan lampau bila dasar bracket kelak dipindah — kalau diganti, ganti maju saja. `CashDrawerReconciliation.php:99` harus tetap `total_amount`: uang fisik di laci memang sejumlah itu.
- **Catatan Migrasi:** `php artisan migrate`. Tidak ada langkah manual — pajak lahir mati untuk semua tenant, dan transaksi lama terisi `subtotal_amount = total_amount`, `tax_amount = 0`.
- **Yang belum termasuk:** laporan pajak terpungut sebagai angka kedua di laporan harian/bulanan (`[BL-065]` butir (e), menunggu bersama `[BL-063]`), service charge, dan dasar perhitungan margin di `ProfitService` — ketiganya sengaja dibiarkan terbuka dan tercatat di entri backlognya.

---

### [ADDITION] Antrean Penjualan Offline Berhenti Bergantung pada Tab yang Terbuka (BL-016 Bagian B)
- **Tanggal:** 2026-08-28
- **Fase Terkait:** Di Luar Fase — dua "perbaikan murah" `[BL-016]` Bagian B; entrinya **tetap Open** (bagian printer tidak tersentuh)
- **Dampak:** Frontend, Service Worker
- **Breaking Change:** Tidak
- **Deskripsi:**
  Transaksi offline sudah bekerja sejak PHASE PWA (2026-07-16); yang belum ada adalah jaminannya. `[BL-016]` Bagian B mencatat dua janji yang tidak bisa diberikan peramban, dan keduanya kini ditambal sejauh yang bisa ditambal tanpa lapisan native:

  **(B.1) Sinkronisasi tidak lagi menuntut tab POS terbuka.** Sebelum ini `flush()` hanya jalan selama halaman POS hidup — kasir yang menutup tab saat tutup toko meninggalkan penjualan menggantung sampai ada orang membuka POS lagi. Service worker kini mendaftarkan Background Sync dan mengirimkan antrean saat peramban merasa jaringan kembali, ada tab atau tidak.

  **(B.2) IndexedDB diminta jadi persisten.** `navigator.storage.persist()` dipanggil saat POS dibuka. Tanpa itu antrean berstatus "sebisanya": tekanan penyimpanan atau satu ketukan "Hapus data penjelajahan" membuangnya tanpa jejak — dan antrean adalah **satu-satunya data di aplikasi ini yang belum punya salinan di server**.
- **Alasan:**
  Keduanya sudah tertulis di `[BL-016]` sebagai pekerjaan yang **tidak menunggu lapisan native** dan sebaiknya dikerjakan lebih dulu, karena keduanya mengecilkan kerugian bila native ditunda. Keduanya juga tetap berguna bagi pengguna PWA yang tidak akan memasang APK — dan pengguna itu tidak akan pernah hilang. Perlu ditegaskan supaya tidak salah dibaca: **ini tidak menutup lubangnya**, hanya memperkecil jendela kehilangan penjualan.
- **Tiga keputusan yang menentukan bentuknya, dan alasannya:**
  1. **Service worker MENGIRIM lalu MELUPAKAN — ia tidak pernah menulis ke outbox.** Tidak ada rekonsiliasi, penghitung percobaan, maupun penghapusan baris di sana. `client_uuid` membuat pengiriman ulang gratis: server men-dedup, jadi saat aplikasi dibuka lagi, `flush()` miliknya sendiri mengirim baris yang sama, menerima `duplicate`, lalu membereskannya di satu-satunya tempat yang memang memilikinya. Yang penting sudah terjadi — uangnya sampai ke server berjam-jam sebelumnya. Menyalin aturan antrean (MAX_ATTEMPTS, parkir `failed`, vonis per baris) ke `sw.js` akan menaruh salinan kedua di berkas yang tidak dijangkau pelari uji mana pun.
  2. **Penjaga atribusi ikut diberlakukan di service worker.** Server mengkredit penjualan tersinkron ke pengguna yang sedang terautentikasi, dan outbox bisa memuat baris beberapa kasir yang bergantian memakai satu mesin. Karena itu halaman menitipkan `cashier_id` dan token CSRF, dan worker menyaring dengan dua syarat yang sama seperti halaman. Tanpa itu, penjualan A bisa masuk ke nama dan laporan shift B.
  3. **Titipan itu tinggal di Cache API, bukan IndexedDB.** Menambah store berarti menaikkan `DB_VERSION`, dan upgrade IndexedDB **tertahan selama masih ada tab lain yang memegang versi lama**. Tahanan itu akan mendarat di `enqueue()` — satu-satunya panggilan di aplikasi ini yang tidak boleh menggantung, karena ada kasir berdiri menunggu transaksinya selesai. Cache tidak punya skema dan tidak punya migrasi, jadi risikonya bukan diperkecil melainkan tidak ada. Versi skema IndexedDB **tetap v1**.
- **File Terdampak:**
  - `resources/js/services/backgroundSync.js` — **baru.** Tag sync, titipan kredensial, dan pendaftaran sync. Seluruh alasan di atas ditulis di kepala berkasnya
  - `public/sw.js` — handler `sync` (kirim-lalu-lupakan), pembacaan outbox **read-only** tanpa menyebut versi DB, dan `SYNC_META_CACHE` yang sengaja **tidak berversi** serta dikecualikan dari sapuan `activate`
  - `resources/js/services/offlineDb.js` — `requestPersistentStorage()`
  - `resources/js/Pages/Cashier/POS.vue` — memanggilnya saat mount
  - `resources/js/composables/useOfflineQueue.js` — menitipkan kredensial dan mendaftarkan sync saat penjualan masuk antrean, dan saat `flush()` gagal karena jaringan
  - `resources/js/services/offlineSession.js` — kredensial ikut dibersihkan saat logout; baris outbox tetap **tidak** disentuh
  - `tests/Feature/OfflineDurabilityTest.php` — **baru.** 9 penjaga
- **Yang dijaga test, dan yang tidak — ditulis apa adanya:**
  `public/sw.js` berkas tulisan tangan di luar bundel: ia tidak bisa meng-`import` apa pun dari `resources/js`, jadi setiap nama yang dipakai bersama **disalin, bukan dibagi**. Penjaga di `OfflineDurabilityTest` menahan persis kelas kegagalan itu — tag sync, nama cache, kunci kredensial, nama store outbox, bentuk payload, batas batch, dan daftar cache yang dikecualikan `activate`. Semuanya gagal **tanpa satu pun pesan error** kalau meleset: halaman mendaftarkan tag yang tidak didengar siapa pun, dan pengiriman di latar berhenti tanpa ada yang tahu.
  Yang **tidak** bisa dijaga dari sini: perilaku Background Sync itu sendiri. Proyek ini tidak punya pelari uji JavaScript, dan uji yang sesungguhnya menuntut peramban sungguhan — jual offline, **tutup semua tab**, nyalakan jaringan, lalu tunggu permintaannya datang. Itu belum dilakukan.
- **Catatan Migrasi:** Tidak ada. `CACHE_VERSION` sengaja **tidak** dinaikkan: `SHELL_ASSETS` tidak berubah, dan menaikkannya justru akan membuang `PAGE_CACHE` — perangkat yang sedang offline saat pembaruan datang akan kehilangan salinan POS-nya. `sw.js` yang berubah isinya sudah cukup membuat peramban memasang worker baru dengan sendirinya.
- **Yang TETAP terbuka di `[BL-016]`:** seluruh bagian printer (High), dan Bagian B tidak berubah statusnya jadi selesai — Background Sync **tidak tersedia di WebView Android**, sehingga cangkang Capacitor nanti tetap menuntut aplikasi dibuka; menutupnya betul-betul butuh `WorkManager` di sisi native (`[BL-016]` D5).

---

### [HOTFIX] Kasir Offline Berhenti Menunggu Katalog yang Tidak Akan Pernah Datang (BL-095, BL-096)
- **Tanggal:** 2026-08-26
- **Fase Terkait:** Di Luar Fase — temuan spike Tahap 1 `[BL-016]`; menutup `[BL-095]` dan `[BL-096]`
- **Dampak:** Frontend | PWA | Test
- **Breaking Change:** Tidak. Tidak ada skema, endpoint, maupun kontrak prop yang berubah. `CACHE_VERSION` naik `v3` → `v4`, jadi cache shell/aset/halaman lama dibuang sekali saat service worker berikutnya aktif — perilaku yang memang dirancang untuk itu.
- **Deskripsi:** Saat aplikasi dinyalakan dari keadaan mati sementara server tidak terjangkau, POS tampil utuh lalu **tidak bisa dipakai menjual apa pun**: rak produk menahan "Memuat katalog produk…" selamanya. Sekarang katalog cadangan terbaca, spanduk "Mode Offline" muncul dengan jam katalognya, dan aplikasi memulihkan dirinya sendiri begitu server kembali. Halaman offline juga tidak lagi buntu.
- **Alasan:** Ditemukan saat menguji gerbang keputusan Tahap 1 `[BL-016]` — dan hanya bisa ditemukan dengan mematikan servernya sungguhan. Rantai sebabnya tiga keputusan yang masing-masing benar sendiri-sendiri, dan hanya salah ketika bertemu.

- **Sebabnya bukan mesin offline yang rusak — mesinnya utuh, pemicunya yang tidak pernah menyala.** `products` adalah prop tertunda (`Inertia::defer()`, konsekuensi `[BL-037]`), jadi ia tidak ikut di dokumen yang disimpan service worker; permintaan susulannya gagal karena server memang tidak ada; dan **tidak ada satu pun yang memberi tahu halaman bahwa ia offline** — `navigator.onLine` tetap `true` karena antarmuka jaringan tidak pernah putus, yang mati cuma servernya. Akibatnya `isOnline` tetap `true`, `watch(isOnline)` tidak pernah menyala, dan `loadSnapshot()` tidak pernah dipanggil. Snapshot katalognya sendiri lengkap di IndexedDB sepanjang waktu — ia hanya tidak pernah dibaca.
- **Peringatannya sudah tertulis di kode, bertahun sebelum gejalanya muncul.** `useOnlineStatus.js` menyebut `navigator.onLine` "never as proof a request will succeed" dan menyediakan `markOffline()` sebagai jalan keluarnya. Jalur prop tertunda hanyalah satu-satunya pengirim permintaan yang tidak pernah memanggilnya.
- **Usulan (b) entrinya ditolak sadar saat dikerjakan.** `[BL-095]`(b) mengusulkan `catalogReady` tidak lagi bergantung pada `isOnline`, melainkan pada ketersediaan snapshot. Itu akan memperlihatkan **katalog basi kepada kasir yang sedang online** selagi prop tertundanya masih di jalan — menukar POS yang tidak bisa menjual dengan POS yang menjual di harga lama. Yang benar ternyata membiarkan `catalogReady` apa adanya dan membuat `isOnline` jujur.
- **Menambah pemicu offline tanpa jalan pulang akan melahirkan cacat kedua.** `markOnline()` sudah diekspor sejak lama tapi **tidak pernah dipanggil dari mana pun**, dan `online` milik peramban tidak akan menyala pada kasus ini karena jaringannya tidak pernah putus. Tanpa pemulih, perbaikan ini akan mengunci kasir di mode tunai sampai ia kebetulan berpindah halaman. Karena itu ikut mendarat pendengar `success` dan penyelidik berkala 60 detik yang mengetuk pintu dengan satu muat ulang parsial.
- **`CACHE_VERSION` naik bukan sebagai kerapian, melainkan syarat sampainya perbaikan.** `offline.html` terdaftar di `SHELL_ASSETS`, dan shell hanya diprecache ulang saat versi cache berubah. Tanpa naik ke `v4`, setiap pemasangan yang sudah ada akan terus menyajikan halaman buntu yang lama — selamanya, tanpa satu pun pesan yang terlihat.
- **Diverifikasi dengan server yang benar-benar mati, bukan `navigator.onLine` yang dipalsukan** — justru selisih antara keduanya yang melahirkan cacat ini. Ketiga jalurnya dibuktikan di Chrome sungguhan: cold start offline di `/cashier/pos` memuat katalog dari snapshot (`navigator.onLine` tetap `true` selama itu), cold start di `/` mendarat di halaman offline lalu tombol "Buka Kasir" membuka POS yang berfungsi, dan penyelidik 60 detik memulihkan keadaan setelah server dihidupkan lagi tanpa satu pun peristiwa `online`.
- **Batas yang jujur soal testnya:** `tests/Feature/OfflineColdStartTest.php` adalah penjaga **teks sumber**, bukan uji perilaku — proyek ini tidak punya pelari uji JavaScript, dan polanya mengikuti `StaticAssetBudgetTest` yang sudah menjaga `sw.js` dengan cara sama. Ia mencegah bentuk lamanya kembali; ia tidak membuktikan perilakunya. Pembuktian perilaku menuntut peramban dengan servernya dimatikan, dan itu dikerjakan manual.
- **File Terdampak:**
  - `resources/js/Pages/Cashier/POS.vue` — `loadSnapshot()` jadi tanpa syarat; pendengar `exception`/`success` dipasang dan dilepas di `onUnmounted`; penyelidik pemulihan di interval 60 detik
  - `public/offline.html` — tautan "Buka Kasir" ke `/cashier/pos`, dan kalimatnya berhenti menyuruh orang menunggu koneksi
  - `public/sw.js` — `CACHE_VERSION` `v3` → `v4`
  - `tests/Feature/OfflineColdStartTest.php` — **baru**, 6 tes
- **Catatan Migrasi:** Tidak ada tindakan manual. Pengguna yang sudah memasang PWA akan mendapat shell `v4` saat service worker berikutnya aktif; katalog dan antrean penjualan di IndexedDB **tidak** tersentuh oleh pergantian versi cache.

---

### [HOTFIX] Halaman Depan Berhenti Mengunduh Seluruh Aplikasi Vue yang Tidak Dipakainya (BL-091)
- **Tanggal:** 2026-08-24
- **Fase Terkait:** Di Luar Fase — butir (a) dan (d) `[BL-091]`, entrinya selesai
- **Dampak:** Frontend | Test
- **Breaking Change:** Tidak. Tidak ada satu baris pun kode aplikasi yang disentuh; yang hilang hanya satu argumen `@vite` dan satu berkas Blade yang tak pernah dirujuk rute mana pun.
- **Deskripsi:** `resources/js/app.js` dicabut dari `resources/views/public/landing.blade.php`, menyisakan `resources/css/app.css`. `resources/views/welcome.blade.php` dihapus. Dua penjaga baru memastikan keduanya tidak kembali.
- **Alasan:** Halaman Blade publik memuat entry point Inertia padahal tak satu pun punya elemen mount. `createInertiaApp` tetap berjalan, tidak menemukan `#app`, lalu melempar `TypeError: Cannot read properties of null (reading 'component')` dua kali per kunjungan. Errornya gejala paling ringan; yang mahal adalah muatannya — `import.meta.glob(..., eager: true)` menjadikan seluruh 56 halaman Vue satu bundel **1.117 KB**, dan bundel itu diunduh serta diurai oleh setiap orang yang membuka halaman depan, termasuk yang belum punya akun dan tidak akan pernah melihat satu pun halaman di dalamnya.

- **Ini penghapusan, bukan optimasi — dan itu yang membuatnya layak berdiri sendiri.** `[BL-032]` dan `[BL-077]` menghabiskan pekerjaan nyata menurunkan gambar landing dari 531 KB jadi 294 KB. Satu berkas JavaScript yang tidak dipakai halaman itu sama sekali berukuran hampir **empat kali lipat** seluruh penghematan tersebut, dan menutupnya menuntut satu argumen `@vite` dicabut.
- **`app.css` sengaja ditahan.** Berbeda dengan JS-nya, gaya Tailwind halaman publik memang berasal dari sana. Memisahkan JS tanpa ikut menyeret CSS adalah bagian yang harus disengaja, bukan diasumsikan — dan penjaganya melarang entry JS-nya saja, bukan `@vite` secara keseluruhan.
- **Dari empat Blade yang dicatat entrinya, hanya dua yang masih melanggar.** `api-docs.blade.php` dan `docs/layout.blade.php` sudah bersih sendiri di `9fd2fd5`/`e93ba1a` tanpa pernah tercatat. Itu justru alasan penjaganya ada: yang diperbaiki diam-diam bisa kembali diam-diam.
- **Usulan (b) entrinya — entry point `public.js` tersendiri — dibatalkan saat dikerjakan.** Landing tidak memanggil apa pun dari bundel Inertia: peragaan POS, tab demo, FAQ, dan `IntersectionObserver`-nya inline di Blade, dengan Alpine dari CDN. Entry point baru akan lahir kosong. Bila suatu saat memang dibutuhkan, catatannya tetap ada di arsip `[BL-091]`.
- **`welcome.blade.php` dihapus, bukan ditambal.** Ia halaman starter bawaan Laravel — tidak dirujuk satu pun rute, controller, atau test, dan isinya ~30 KB CSS Tailwind inline. Menambal `@vite`-nya berarti merawat halaman yang tak pernah dibuka siapa pun; yang ia tinggalkan cuma contoh salin-tempel yang salah untuk orang berikutnya.
- **Penjaganya sempat lulus terhadap pelanggaran yang sengaja dibuat.** Versi pertama tes HTML mencari `/build/assets/app-*.js`, dan tidak menemukannya — karena `public/hot` ada, Vite merender URL dev server (`.../resources/js/app.js`). Penjaga yang hanya tahu satu dari dua bentuk itu akan diam persis di lingkungan tempat orang bekerja sehari-hari. Versi yang mendarat memeriksa keduanya, dan **kedua penjaga dibuktikan gagal lebih dulu** dengan mengembalikan `app.js` ke landing sebelum dinyatakan bekerja.
- **Butir (c) — `eager: true` — TIDAK ikut, sesuai perintah entrinya sendiri.** Ia memecah bundel per halaman untuk pengguna yang sudah masuk, tapi mengubah cara setiap halaman dimuat dan pantas diuji sendiri. Dicatat sebagai `[BL-094]`.
- **File Terdampak:**
  - `resources/views/public/landing.blade.php` — `@vite(['resources/css/app.css'])`, tanpa `app.js`
  - `resources/views/welcome.blade.php` — **dihapus**
  - `tests/Feature/Public/PublicBundleSeparationTest.php` — **baru**, 4 tes: pemindaian sumber seluruh Blade di `resources/views/public/`, plus HTML `/`, `/api-docs`, `/dokumentasi`
- **Catatan Migrasi:** Tidak ada. `npm run build` berikutnya tidak berubah bentuknya — `resources/js/app.js` tetap entry point aplikasi untuk `app.blade.php`.

---

### [REFACTOR] Dua Aset Yatim Dibuang, dan Celah yang Selama Ini Ditambal Manusia Dijaga Test (BL-077)
- **Tanggal:** 2026-08-24
- **Fase Terkait:** Di Luar Fase — menutup butir (c) `[BL-077]`, entrinya selesai
- **Dampak:** Aset | PWA | Test
- **Breaking Change:** Tidak. Tidak ada berkas yang masih dirujuk halaman mana pun yang dihapus.
- **Deskripsi:** `public/Feature-Showcase.png` (44 KB) dan `public/sapi-logo.png` (92 KB) dihapus, `SHELL_ASSETS` service worker dibersihkan dari yang kedua, dan `CACHE_VERSION` naik `v2` → `v3`. Sebagai gantinya lahir `tests/Feature/Public/StaticAssetBudgetTest.php` dengan tiga penjaga: tidak ada raster non-WEBP baru di `public/`, tidak ada aset melewati 120 KB, dan setiap berkas yang diprecache service worker benar-benar ada.
- **Alasan:** Butir (c) entrinya mengeluh bahwa aset statis tidak punya pipeline sama sekali — yang berarti setiap perbaikan dikerjakan manual sekali jalan, dan tidak ada yang mencegah berkas berat berikutnya masuk dengan cara yang sama.

- **Usulan asli entrinya — satu plugin Vite pengonversi gambar — DIBATALKAN, dan alasannya bukan soal dependensi.** Vite hanya memproses aset yang di-`import` lewat bundel. Berkas di `public/` disalin apa adanya dan **tidak pernah disentuh Vite**. Plugin itu akan menambah dependensi yang tidak menyentuh satu pun berkas yang jadi alasan entrinya ditulis. Yang benar-benar berlaku untuk `public/` adalah pemeriksaan, bukan pipeline.
- **Empat dari enam berkas yang didaftarkan entrinya ternyata sudah beres.** `Stock-Management`, `Dashboard-owner`, `Product-List`, ditambah `POS-Interface` dan `Reports-Daily`, seluruhnya sudah WEBP sejak 2026-08-21 — sekali lagi secara manual. Yang tersisa hanya dua berkas, dan keduanya tidak dirujuk satu halaman pun.
- **`sapi-logo.png` ternyata BUKAN berkas yatim, dan hampir dihapus sebagai yatim.** Ia terdaftar di `SHELL_ASSETS` pada `public/sw.js`. `cache.addAll()` bersifat semua-atau-tidak: satu 404 di sana **menggagalkan seluruh install service worker**, dan aplikasi kehilangan shell offline-nya — tanpa satu pun pesan error yang terlihat pengguna, karena halamannya tetap normal selama online. Yang menagihnya baru kasir yang kehilangan sinyal. Jadi 92 KB itu bukan sekadar menganggur; ia diunduh setiap install untuk gambar yang tidak pernah dirender halaman mana pun (`offline.html` memakai `/icons/icon-192.png`, dan manifes hanya menyebut `/icons/*`).
- **Penjaga ketiga lahir langsung dari nyaris-celaka itu**, dan itu yang membuatnya layak ada: ia membaca `SHELL_ASSETS` dari `sw.js` lalu menuntut tiap entrinya benar-benar ada di disk. Kesalahan ini tidak mungkin ditangkap tes lain — tidak ada tes yang meng-install service worker.
- **`icons/` dan `favicon.ico` sengaja dikecualikan, bukan terlewat.** `manifest.webmanifest` menuliskan `"type": "image/png"` untuk ketiga ikonnya, dan dukungan WEBP untuk ikon PWA maupun favicon masih timpang antar peramban. Alasannya ditulis di dalam `excludedAssetPaths()` supaya pengecualian berikutnya tidak ditambahkan tanpa alasan.
- **Ketiga penjaga diuji dengan cara dilanggar**, bukan hanya dijalankan: satu PNG 195 KB disusupkan ke `public/` dan satu entri palsu ditambahkan ke `SHELL_ASSETS`; ketiganya gagal dengan pesan yang menyebut berkas dan jalan keluarnya, lalu keadaan dikembalikan. Penjaga yang tidak pernah terbukti bisa gagal tidak menjaga apa pun.
- **Berkas:** `public/Feature-Showcase.png`, `public/sapi-logo.png` (dihapus) · `public/sw.js` — `SHELL_ASSETS`, `CACHE_VERSION` `v2`→`v3` · `tests/Feature/Public/StaticAssetBudgetTest.php` (baru, 3 tes)
- **Catatan Migrasi:** Tidak ada migrasi data. `CACHE_VERSION` yang naik membuat peramban pengguna membuang shell lama dan mengunduh ulang yang baru pada kunjungan berikutnya — ini memang yang diinginkan, karena entri `/sapi-logo.png` di cache lama menunjuk berkas yang sudah tidak ada.

---

### [ADDITION] Riwayat yang Tidak Bisa Boot Dibiarkan, Peringatannya yang Dipindah ke Tempat Terbaca (BL-072)
- **Tanggal:** 2026-08-24
- **Fase Terkait:** Di Luar Fase — menutup `[BL-072]` lewat butir (d)
- **Dampak:** Dokumentasi | Tooling | Test
- **Breaking Change:** Tidak.
- **Keputusan pemilik 2026-08-24:** entri ini ditutup dengan butir (d) — aturan ke depan beserta penjaganya. Butir (c), menulis ulang delapan commit, **tetap ditolak** sesuai keputusan 2026-08-08.
- **Deskripsi:** Peringatan tentang rentang `2ffd393`..`329f592` pindah dari `docs/BACKLOG.md` ke `CLAUDE.md`, bersama aturan "kelas dan pemakainya masuk di commit yang sama, atau kelasnya lebih dulu". Aturan itu kini punya pemeriksa: `composer run check:boot`, yang menjalankan `tests/Feature/CommitBootabilityTest.php`.
- **Alasan:** Rentang itu tetap tidak bisa boot selamanya, dan itu disengaja. Yang bisa diperbaiki adalah nasib orang yang menyusurinya: `git bisect` di sana menunjuk commit yang salah, dan `git revert 342082c` akan mematikan `HEAD`. Peringatan itu sebelumnya terkubur di baris 267 sebuah berkas 726 baris yang aturannya sendiri melarang dibaca utuh — jadi ia hanya akan ditemukan oleh orang yang sudah tahu harus mencarinya.

- **Uji cepat yang disarankan entrinya sendiri — `php artisan route:list` — DICOBA dan TIDAK MENANGKAP CACATNYA.** Cacat aslinya ditirukan persis (impor digantung di `HandleInertiaRequests`), dan `route:list` tetap keluar dengan status 0. Sebabnya ada di PHP, bukan di Laravel: sebuah `use` hanyalah alias di waktu kompilasi dan tidak pernah memicu autoloader sampai kelasnya benar-benar dipakai. Kalau butir (d) dipasang apa adanya, yang mendarat adalah penjaga yang tidak bisa gagal.
- **Karena itu penjaganya membaca impornya, bukan menjalankan aplikasinya.** `CommitBootabilityTest` menyisir setiap `use` tingkat atas di `app/`, `database/`, `routes/`, dan `config/`, lalu menuntut sasarannya bisa dimuat sebagai kelas, interface, trait, atau enum. Ia melaporkan `berkas:baris → kelas`, jadi yang gagal langsung menunjuk tempatnya.
- **Penjaganya diuji dengan menirukan cacat aslinya**, bukan hanya dijalankan pada kode yang sehat: satu impor menggantung disuntikkan ke `HandleInertiaRequests`, tes gagal dan menyebut berkas beserta nomor barisnya, lalu berkasnya dikembalikan.
- **`use function` dan `use const` sengaja dilewati**, dan `use` yang menjorok di badan kelas ikut terlewat karena polanya menempel di awal baris. Yang pertama mengimpor simbol yang bukan kelas; yang kedua pemakaian trait, bukan impor namespace. Keduanya akan jadi laporan palsu, dan penjaga yang sering salah lapor akan dimatikan orang.
- **`vendor/` di luar sisiran** — isinya bukan yang kita commit, dan impor bersyarat untuk paket opsional di sana akan melaporkan kegagalan yang bukan kegagalan.
- **Yang TIDAK berubah, dan perlu disadari:** riwayatnya tetap rusak. Entri ini tidak menyembuhkan satu commit pun; ia mencegah cacat yang sama lahir lagi, dan memastikan orang berikutnya diperingatkan **sebelum** tersesat, bukan sesudah.
- **Berkas:** `CLAUDE.md` — aturan commit atomik + blok peringatan riwayat · `composer.json` — skrip `check:boot` · `tests/Feature/CommitBootabilityTest.php` (baru)
- **Catatan Migrasi:** Tidak ada. `composer run check:boot` dijalankan manual sebelum commit; belum dipasang sebagai hook maupun langkah CI — repositori ini belum punya workflow tes, dan menambahkannya keputusan tersendiri.

---

### [DECISION] Jenis Usaha Tetap Opsional, tapi Bawaannya Berhenti Ditebakkan Diam-diam (BL-079)
- **Tanggal:** 2026-08-24
- **Fase Terkait:** Di Luar Fase — menjawab sebagian `[BL-079]`; entrinya **tetap terbuka**
- **Dampak:** Frontend | Controller | Test
- **Breaking Change:** Tidak. Validasi, kolom, dan nilai tersimpan tidak berubah sama sekali.
- **Keputusan pemilik 2026-08-24:** opsi **(iii)** — `business_type` tetap opsional saat mendaftar, tapi layarnya berhenti menyembunyikan apa yang sebenarnya tersimpan.
- **Deskripsi:** Pilihan kosong di formulir daftar berganti dari "Belum ditentukan" menjadi "Belum yakin — disamakan dengan Lainnya", dan di atas pemilihnya ditambahkan satu baris yang menyebut kenapa pertanyaannya diajukan serta bahwa jawabannya bisa diubah dari Pengaturan. Tidak ada validasi yang diketatkan dan tidak ada migrasi.
- **Alasan:** Sejak `pricing_rules` bisa bersyarat jenis usaha, tenant yang membiarkannya kosong dinilai sebagai `lainnya` — jawaban yang **ditebakkan untuknya**, bukan yang ia pilih. "Belum ditentukan" dan "Lainnya" adalah dua pilihan berbeda di layar yang mendarat di nilai tersimpan yang sama, dan tidak ada apa pun yang mengatakannya.

- **Mewajibkannya ditolak, dan usulan (a) entrinya sendiri yang jadi alasannya:** jangan dikerjakan sebelum ada aturan harga yang benar-benar bersyarat `business_type`. Diperiksa saat entri ini dikerjakan — **belum ada satu pun**. Mewajibkan sekarang berarti menaikkan gesekan pendaftaran demi dimensi yang belum menentukan tarif siapa pun.
- **Aplikasi ini sudah tidak konsisten dengan dirinya sendiri, dan itu TIDAK diseragamkan di sini.** `BusinessProfileController` sudah memvalidasi `business_type` sebagai `required`, sementara pendaftaran menerima kosong — wajib saat diubah, opsional saat dibuat. Menyeragamkannya berarti memilih salah satu arah, dan arah itu persis pertanyaan yang sedang ditahan. Dibiarkan sadar, dan dicatat di sini supaya tidak "dirapikan" tanpa keputusan.
- **Kenapa entrinya tetap terbuka:** yang terjawab hanya "layarnya berbohong atau tidak". Pertanyaan aslinya — wajibkan atau tidak, dan apa nasib tenant yang sudah terlanjur berbawaan — menunggu aturan harga pertama yang memakai dimensi ini.
- **Empat tes mengunci kedua sisi keputusan sekaligus** — bahwa mendaftar tanpa menjawab tetap boleh, bahwa yang kosong mendarat di bawaan dan bukan `null`, bahwa layarnya menyebut nama bawaan itu, dan bahwa jenis usaha di luar katalog tetap ditolak. Tes ketiga membaca nama bawaannya dari `config('pricing-dimensions')`, bukan menuliskannya, jadi ia ikut benar bila bawaan itu suatu saat diganti.
- **Berkas:** `resources/js/Pages/Auth/Register.vue` — label pilihan kosong + baris penjelasan · `app/Http/Controllers/Auth/AuthController.php` — catatan keputusan pada aturan validasinya · `tests/Feature/Auth/BusinessTypeOptionalityTest.php` (baru, 4 tes)
- **Catatan Migrasi:** Tidak ada. Tenant yang sudah tersimpan sebagai `lainnya` dibiarkan apa adanya — memindahkannya berarti menebak jawaban yang tidak pernah diberikan, dan itu persis kesalahan yang sedang diperbaiki.

---

### [DECISION] Seluruh Aplikasi Berjalan di Jam Toko (WITA), dan Satu Tempat Saja yang Menjawab "Hari Ini" (BL-082)
- **Tanggal:** 2026-08-22
- **Fase Terkait:** Di Luar Fase — menutup `[BL-082]`
- **Dampak:** Config | Service | Controller | Frontend | Test
- **Breaking Change:** Tidak untuk data yang sudah tersimpan (lihat "Catatan Migrasi"), **ya** untuk arti `now()` di seluruh basis kode: sejak entri ini `now()` adalah waktu toko, bukan UTC.
- **Keputusan pemilik 2026-08-22:** **satu zona untuk seluruh aplikasi**, `Asia/Makassar` (WITA), bukan satu zona per tenant. Nilainya dibaca dari `APP_TIMEZONE`, jadi produksi bisa berbeda dari lokal tanpa menyentuh kode.
- **Deskripsi:** `config/app.php` berhenti menulis mati `'UTC'` dan membaca `env('APP_TIMEZONE', 'Asia/Makassar')`. Karena itu seluruh `now()`, `DATE()` pengelompokan, dan cap waktu yang ditulis Eloquent kini berada di jam toko. Di atasnya lahir `App\Services\BusinessClock` — satu-satunya tempat yang menyebut zona bisnis dan menjawab "hari ini", "minggu ini", "periode bulan ini". Sisi peramban ikut di commit yang sama lewat meta tag `business-timezone` dan `resources/js/support/date.js`.
- **Alasan:** Server berjalan di UTC sementara tokonya tidak. Batas hari UTC jatuh pukul **08.00 WITA**, jadi penjualan antara tengah malam dan jam itu masuk ke **laporan hari sebelumnya** — dan Laporan Harian adalah angka yang dipakai pemilik menutup harinya. Gejalanya sudah terlihat tanpa dicari: pada satu layar yang sama, topbar menulis "Jumat, 21 Agustus" (tanggal peramban) sedangkan pemilih tanggal Laporan Harian default ke "Kamis, 20 Agustus" (tanggal server).

- **Zonanya diubah di config, BUKAN dengan mengurangi delapan jam di beberapa kueri.** Tambalan per kueri memperbaiki layar yang sedang dilihat dan meninggalkan sisanya berselisih dengan layar itu — dan selisih antar-laporan jauh lebih mahal daripada selisih terhadap UTC, karena ia baru ketahuan saat dua angka diadu.
- **`BusinessClock` tidak menghitung ulang apa pun, dan memang itu maksudnya.** Karena aplikasinya sudah berjalan di zona toko, ia hanya menamai maksudnya di titik-titik yang mengelompokkan per hari. Nilainya ada pada hari ketika zonanya harus jadi per tenant: yang perlu berubah hanya satu berkas, bukan setiap laporan.
- **Satu lubang tidak ikut sembuh dengan mengubah config: cap waktu yang datang DARI perangkat.** Peramban mengirim instan UTC (`toISOString()` selalu berakhiran `Z`), Carbon mempertahankan zona asal string itu, dan Eloquent menyimpan kolom datetime dengan memformat objeknya apa adanya. Tanpa `BusinessClock::fromClient()`, penjualan offline pukul 09.00 WITA tersimpan sebagai `01:00` — persis kesalahan yang sedang diperbaiki, lewat pintu yang berbeda.
- **Sisi peramban punya dua kesalahan yang berbeda, dan keduanya diperbaiki.** `new Date().toISOString().slice(0, 10)` adalah tanggal **UTC** — salah hari sepanjang pukul 00.00–08.00 WITA, dan ia jadi nilai bawaan tanggal berlaku aturan diskon, kuota AI, aturan harga, dan periode tagihan. `new Date().getFullYear()` dan kawan-kawannya adalah tanggal **perangkat** — benar hanya selama tablet tokonya disetel benar.
- **Yang sengaja TIDAK dipaksa ke zona toko: tanggal yang dirakit dari komponen lokal.** `new Date(year, month - 1, day)` sudah tengah malam lokal; menambahkan `timeZone` justru memproyeksikannya ulang dan bisa menggeser labelnya satu hari. Konvensi ini sudah dipakai halaman tagihan sejak sebelum entri ini, dan tetap dipertahankan.
- **Yang tidak terkena, dan alasannya:** rekonsiliasi kas membandingkan antar-timestamp (`opened_at`–`closed_at`); umur tagihan terbuka 24 jam berbasis durasi. Perbandingan timestamp dan durasi tidak peduli zona.
- **Berkas:** `config/app.php`, `.env.example` — `APP_TIMEZONE` · `app/Services/BusinessClock.php` (baru) · `app/Http/Controllers/Owner/DashboardController.php`, `app/Http/Controllers/Owner/ReportController.php` — "hari ini"/rentang bawaan · `app/Services/TransactionService.php` — `parseOccurredAt()` · `resources/views/app.blade.php` — meta `business-timezone` · `resources/js/support/date.js` (baru) · 24 berkas Vue/JS: batas hari (`DatePicker`, `MonthPicker`, `GraceModal`, `Stock/Index`, `Transactions/Detail`, `DiscountRules`, `UpsellRules`, `AiAnalysis`, `AiQuota`, `PricingRules`, `Tenants/Show`, `Dashboard`, `Billing/Show`) dan tampilan yang dipatok ke zona toko · `tests/Feature/BusinessTimezoneTest.php` (6 tes baru)
- **Catatan Migrasi:** **Tidak ada migrasi data, dan itu disengaja.** Kolom datetime menyimpan waktu polos tanpa zona; sesudah perubahan ini baris lama dibaca sebagai WITA. Karena pengelompokan harian dan bulanan bekerja pada string yang sama persis, **tidak satu pun angka laporan riwayat berubah** — termasuk `tenant_monthly_metrics` yang jadi dasar bracket Harga Adaptif, sehingga tidak ada penghitungan ulang yang perlu dijalankan. Yang bergeser hanya jendela yang dihitung dari `now()` ("hari ini", "7 hari terakhir"), dan hanya sekali, saat perubahan ini mendarat. Jam pada data demo yang lama ikut terbaca delapan jam lebih awal dari yang disemai; `php artisan db:seed` menyegarkannya bila jam demonya penting. Data tenant sungguhan belum ada saat entri ini ditulis — bila suatu hari ada, perubahan zona **harus** diberitahukan lebih dulu, bukan didiamkan.

---

### [HOTFIX] Saran yang Terlanjur Diterima Bisa Ditarik Lagi, dan Transaksi yang Di-void Berhenti Mengaku Berhasil (BL-092 butir 3)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — butir 3 `[BL-092]`
- **Dampak:** Frontend | Controller | Test
- **Breaking Change:** Tidak. Tidak ada baris `upsell_events` yang diubah atau dihapus; yang berubah hanya baris mana yang ikut dihitung di laporan, dan apa yang dikirim POS saat checkout.
- **Deskripsi:** Saran yang sudah ditekan "Diterima" kini tetap terlihat di strip kasir dengan tombol **Batalkan** — yang mengembalikan keranjang ke keadaan semula (add-on dilepas, naik ukuran dikembalikan ke varian lama, baris tambahan dikeluarkan) sekaligus mengembalikan saran itu ke keadaan menunggu keputusan. Penerimaan yang jejaknya hilang dari keranjang **menarik dirinya sendiri**. Di sisi laporan, event yang menempel pada transaksi berstatus `voided` tidak lagi dihitung.
- **Alasan:** Sebelum ini "diterima" adalah keputusan sekali jalan. Salah pencet dan pelanggan yang berubah pikiran tidak punya jalan keluar selain menghapus barisnya dan memasukkan ulang secara manual — dan `acceptedByKey` tidak pernah tahu itu terjadi, sehingga `collectEvents()` tetap mengirim `status: accepted` beserta `extra_amount` untuk penjualan yang tidak pernah terjadi. Angka yang mengaku lebih besar dari kenyataan adalah cara tercepat membuat seluruh laporan ini tidak dipercaya.

- **Tiga jenis saran menyentuh keranjang dengan tiga cara, jadi pembatalannya juga tiga.** Add-on melepas modifier dari barisnya; naik ukuran mengembalikan `variant_id`, nama, dan harga dari salinan yang disimpan **saat diterima** (tanpa salinan itu tidak ada apa pun untuk dikembalikan, karena naik ukuran menimpa barisnya); barang tertekan dan aturan pemilik menurunkan qty satu, atau mengeluarkan barisnya bila tinggal satu.
- **Penarikan otomatis ada karena kasir tidak akan ingat menekan Batalkan lebih dulu.** Yang diingat orang saat antrean panjang hanya membereskan keranjangnya. `useUpsell` menerima satu callback `isApplied` dan memeriksa tiap penerimaan setiap kali keranjang berubah — composable-nya tetap tidak tahu apa-apa soal bentuk keranjang POS.
- **Ditarik berarti KEMBALI MENUNGGU, bukan langsung "ditolak".** Salah pencet dan pelanggan yang membatalkan adalah dua peristiwa berbeda, dan hanya kasir yang tahu mana yang baru saja terjadi — ia menjawabnya lewat kedua tombol yang muncul kembali. Yang jejaknya hilang tanpa jawaban lanjutan tercatat `ignored`: tampil, tidak dijawab, nol rupiah. Tak satu pun dari keduanya menggelembungkan `offer_rate`.
- **Transaksi yang di-void adalah bentuk kedua dari cacat yang sama, dan ia hidup di server.** Kasir yang membatalkan lalu memasukkan ulang satu transaksi membuat saran yang sama terhitung **dua kali**. Penyaringnya `whereDoesntHave`, bukan join: `transaction_id` boleh NULL karena transaksi yang benar-benar dihapus melepasnya — dan migrasinya memilih `nullOnDelete` justru supaya menghapus transaksi tidak diam-diam memperbaiki angka konversi. Event yatim itu tetap dihitung.
- **Jenis `manual` akhirnya punya warnanya sendiri di strip kasir.** Sejak `[BL-074]` ia menumpang tampilan add-on karena `TONE` tidak punya barisnya, sehingga saran yang ditulis pemilik toko tampil sebagai tebakan mesin biasa — menghapus satu-satunya keterangan yang membuatnya layak diucapkan.
- **Berkas:** `resources/js/composables/useUpsell.js` — `retract()`, `accepted`, penjaga `isApplied` · `resources/js/Components/UpsellStrip.vue` — daftar yang sudah diambil, tone `manual` · `resources/js/Pages/Cashier/POS.vue` — `undoUpsell()`, salinan pemulihan naik ukuran, `upsellStillApplied()` · `app/Http/Controllers/Owner/ReportController.php` — penyaring transaksi `voided` · `tests/Feature/Upsell/UpsellEventTest.php` (2 tes baru)

---

### [ADDITION] Owner Melihat Saran Otomatis, Aturannya Sendiri, dan Siapa yang Mengisi Tiga Slot Kasir (BL-092 butir 1-2)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — butir 1 dan 2 `[BL-092]`, kelanjutan `[BL-074]`
- **Dampak:** Controller | Service | Frontend | Test
- **Breaking Change:** Tidak. Prop `summary` laporan upsell tidak berubah bentuk; yang lahir satu prop `sources` di sampingnya dan satu kelompok tunda baru (`pratinjau`) di halaman aturan.
- **Deskripsi:** Halaman **Aturan Saran Jual** mendapat bagian "Yang Muncul di Kasir Hari Ini": daftar saran tanpa pemicu (aturan pemilik **dan** barang tertekan stok yang ditemukan sistem) bertanda **Tampil**/**Tergeser**, ditambah tabel per barang pemicu berisi saran yang menang slot bila barang itu masuk keranjang. Laporan **Saran Jual** mendapat tiga kolom berdampingan: **Gabungan**, **Otomatis (sistem)**, dan **Aturan Anda**.
- **Alasan:** Halaman aturan hanya memperlihatkan separuh kenyataan — aturan yang pemilik tulis sendiri, tanpa satu pun saran yang ditemukan mesin dari stok. Ia tidak punya cara melihat siapa yang sedang mengisi tiga slot kasir, dan aturan yang tidak muncul terbaca sebagai fitur rusak padahal ia hanya kalah skor atau stoknya habis. Rekap per jenis di laporan sudah memuat bahannya, tapi menuntut pemilik menjumlahkan tiga baris mesin di kepalanya untuk melawankannya dengan satu baris manual adalah cara paling pasti membuat perbandingan itu tidak pernah dilakukan.

- **Pratinjaunya memakai kode pemilihan yang sama persis dengan kasir, bukan tiruannya.** `UpsellIndexBuilder::forCart()` dipecah jadi `rankForCart()` (seluruh kandidat, terurut skor) dan `pickForCart()` (potong sebanyak slot). Pratinjau yang menyimpang dari kenyataan lebih buruk daripada tidak ada pratinjau.
- **Yang KALAH slot justru inti bagian ini.** Kasir tidak pernah melihat ekor daftarnya; pertanyaan pemilik persis "apa yang tidak muncul gara-gara batas 3 ini?" — jadi barisnya tetap dipajang, diredupkan, bertanda **Tergeser**.
- **Jenis yang dimatikan lewat `config/upsell.php` disebutkan di layar.** Saklar darurat yang tidak kelihatan membuat pemilik menyimpulkan aturannya sendiri yang rusak.
- **Kelompok tunda tersendiri (`pratinjau`), bukan menumpang daftar aturan.** Merakit indeks menelusuri seluruh katalog, stok, dan riwayat penjualan; menyatukannya berarti tabel aturan ikut menunggu pekerjaan yang tidak ada hubungannya dengannya.
- **Kolom "Gabungan" tetap ada dan tetap di depan.** Pertanyaan pertama pemilik selalu "fitur ini menghasilkan atau tidak", bukan "mesin atau saya yang menang". Ketiganya dibaca dari satu query beragregat per jenis, bukan tiga rombongan query yang sumbernya sama persis.
- **Daftar pemicu dipotong di 25 baris, dengan sisanya disebutkan.** Katalog besar bisa punya ratusan pemicu, dan daftar sepanjang itu tidak dibaca siapa pun.
- **Berkas:** `app/Services/Upsell/UpsellIndexBuilder.php` — `rankForCart()`/`pickForCart()` · `app/Http/Controllers/Owner/UpsellRuleController.php` — `slotPreview()` · `app/Http/Controllers/Owner/ReportController.php` — `upsellSummary()`, prop `sources` · `resources/js/Pages/Owner/UpsellRules/Index.vue` · `resources/js/Pages/Owner/Reports/Upsell.vue` · `tests/Feature/Upsell/ManualUpsellRuleTest.php` (3 tes baru) · `tests/Feature/Upsell/UpsellEventTest.php` (1 tes baru)

---

### [HOTFIX] Tab Demo Ketiga Berhenti Meramal dan Jadi Saran Jual yang Memang Sudah Jalan (BL-089)
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — sisa `[BL-083]`, yang penyisiran hero-nya membuktikan klaim ramalan stok berdiri jauh di luar hero. Menutup `[BL-089]`
- **Dampak:** Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Tab demo ketiga "Prediksi Stok (Machine Learning)" diganti **Saran Jual**; jawaban FAQ tentang cara AI meramal stok diganti penjelasan cara `BadgeHelperService` bekerja; dan ketiga tombol aksi di peragaan Badge Helper diturunkan jadi pil hitungan.
- **Alasan:** Tidak ada model, tidak ada pustaka ML, dan tidak ada satu pun perhitungan horizon di basis kode — yang ada empat perbandingan ambang. Menyebut teknologi tertentu dengan nama adalah klaim yang paling mudah diperiksa orang luar, dan jawaban FAQ yang menjelaskan **mekanisme** jauh lebih meyakinkan, karenanya jauh lebih menyesatkan, daripada slogan.

- **Penggantinya dipilih karena sudah jalan dan belum pernah dipamerkan.** Saran Jual punya empat strategi (`UpsizeVariant`, `AttachModifier`, `PressedStock`, `ManualRule`) dan sejak `[BL-074]` owner bisa menargetkan sarannya sendiri. Peragaannya memakai bentuk `Suggestion` apa adanya — label, catatan, tambahan rupiah — dengan kode alasan diambil dari konstanta `REASON_*` pada `UpsellEvent`, dan sebuah test mengunci keempat kode itu benar-benar muncul di halaman.
- **Butir (c) dikerjakan lebih dulu dan sendirian,** karena ia satu-satunya yang tidak menunggu keputusan pemilik. Ternyata ia memuat tiga cacat: tombol tanpa jalan, "Habis dalam 2-3 hari" (ramalan yang sama seperti kartu hero `[BL-083]`), dan badge berjudul "Upsell" — jenis yang tidak pernah dihasilkan `BadgeHelperService`. Warnanya juga diluruskan ke `severityClasses` milik `BadgeCard.vue`.
- **Dua tombolnya menyebut fitur yang nol kode, bukan fitur tanpa jalan pintas.** `supplier`/`purchase_order` dan `bundle` tidak mengembalikan satu hasil pun di `app/` maupun `database/migrations/`.
- **Klaim baru nyaris lahir untuk ketiga kalinya saat memperbaiki yang lama.** Contoh upsell sempat ditulis "sering dibeli bersama" sebelum diperiksa; diganti "tawarkan Double" yang memetakan ke `UpsizeVariantStrategy`. Pola yang sama terjadi di `[BL-078]` ("prediksi stok", "barcode") dan `[BL-083]`. Yang menggantikan sebuah karangan harus diperiksa sekeras yang diganti.
- **Testnya menemukan bahwa komentar rasional ikut terkirim ke peramban.** Penjelasannya mula-mula `/* … */` di dalam `<script>`, sehingga frasa "Pesan ke Supplier" dan "Buat Bundle" tetap ada di HTML dan test gagal. Diubah jadi `{{-- … --}}`, yang dibuang Blade di server.
- **Berkas:** `resources/views/public/landing.blade.php` — label tab, panel demo ketiga, `badgeTemplates`, `upsellData`, renderer, FAQ · `tests/Feature/Public/LandingClaimsTest.php` — dua test baru dan satu catatan diperbarui (74 tes lulus di `tests/Feature/Public`)

---

### [HOTFIX] Hero Berhenti Menjanjikan Ramalan Stok dan Tombol yang Tidak Punya Jalan (BL-083)
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — kelanjutan `[BL-032]` butir (1), yang penyisirannya berhenti di teks bagian isi dan tidak pernah mencapai hero. Menutup `[BL-083]`
- **Dampak:** Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Tiga klaim di layar pertama dicabut. Kartu "Prediksi Stok — Aman Hingga 14 Hari" jadi "Stok Kritis — 3 varian mendekati habis"; tombol "Eksekusi Sekarang" jadi keterangan "Muncul di dashboard Anda"; dan paragraf hero berhenti menyebut "memprediksi stok Anda".
- **Alasan:** Ketiganya bisa diperiksa ke kode, dan ketiganya tidak ada. `BadgeHelperService` membandingkan ambang tetap — stok ≤ 5, stok habis, tak terjual 30 hari, lewat `expiry_date` — dan tidak pernah menghitung horizon. `BadgeCard.vue` hanya membuka-tutup, jadi tidak ada jalan satu tekan dari saran ke diskon terpasang.

- **Kalimat penggantinya meminjam kalimat produk sendiri, bukan dikarang ulang.** "3 varian mendekati habis" adalah bentuk persis pesan badge `low_stock` (`"{count} varian mendekati habis"`), dan warnanya turun dari hijau ke amber mengikuti `severity: warning` milik badge itu. Yang dijanjikan di landing kini sama dengan yang dilihat orang setelah masuk — dan itu, bukan nada kalimatnya, yang membuatnya aman.
- **Ikon centang ikut diganti segitiga peringatan.** Kartu lama berwajah kabar baik karena isinya memang kabar baik ("aman hingga 14 hari"). Isinya sekarang peringatan; mempertahankan centang hijau akan membuat gambar dan kalimatnya berselisih.
- **Tombolnya dicabut, bukan diganti kata.** Mengganti label sambil mempertahankan bentuk tombol tetap mengundang tekanan yang tidak akan menghasilkan apa pun. Yang menggantikannya paragraf kecil berwarna redup.
- **Penyisiran hero — butir (c) — menemukan yang ketiga, dan ia yang paling menonjol.** Paragraf utama hero, bukan kartu melayang, adalah tempat "memprediksi stok Anda" berdiri. Dua kartu itu hanya yang paling mudah terlihat.
- **Asumsi entri backlognya terbukti keliru, dan sisanya sengaja tidak dibongkar.** Sebutan ramalan stok masih berdiri di tab demo interaktif berjudul "Prediksi Stok (Machine Learning)", tombol "Jalankan Ulang Prediksi AI", jawaban FAQ yang menjelaskan mekanismenya, dan `predictData` yang memperagakan sisa hari per produk. Itu satu dari tiga pilar bagian Demo — keputusan pemasaran, bukan konsekuensi teknis. Dicatat sebagai `[BL-089]`.
- **Testnya sengaja hanya mengunci hero,** dengan alasan yang ditulis di dalam testnya sendiri: menyapu seluruh sebutan "prediksi" akan membuatnya gagal sampai `[BL-089]` dikerjakan, padahal `[BL-089]` menunggu keputusan pemilik.
- **Berkas:** `resources/views/public/landing.blade.php` — paragraf hero, dua kartu melayang · `tests/Feature/Public/LandingClaimsTest.php` — satu test baru (72 tes lulus di `tests/Feature/Public`)

---

### [ADDITION] Uang Keluar Laci Punya Tempat Mencatatnya, dan Efeknya yang Ditahan — Bukan Pencatatannya (BL-087)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — menutup `[BL-087]`, entri terakhir dari catatan pemilik 2026-08-21. Ia yang paling akhir dikerjakan karena ia satu-satunya yang mengubah rumus `expected_amount`, dan `[BL-086]` serta `[BL-088]` harus mendarat lebih dulu supaya layarnya tidak dibongkar dua kali
- **Dampak:** Schema | Model | Service | Controller | Route | Settings | Frontend | Test
- **Breaking Change:** Ya untuk rumusnya, tidak untuk data yang ada. `expected_amount` kini `modal + tunai masuk − kembalian − pengeluaran disetujui + setoran disetujui`. Tabelnya lahir kosong, jadi tidak ada satu pun sesi lama yang angkanya berubah.
- **Deskripsi:** Kasir bisa mencatat uang yang keluar dari laci (setoran ke pemilik, beli galon, tukar uang kecil) dan yang masuk di luar penjualan, dengan **alasan wajib**. Di bawah ambang tenant — bawaan **Rp 50.000**, diatur pemilik di *Cara Kerja Sistem* — catatannya langsung berlaku. Di atasnya ia **tercatat dan terlihat tapi belum mengurangi uang seharusnya di laci** sampai pemilik menyetujuinya dari daftar Sesi Kas.
- **Alasan:** Dilaporkan pemilik — "membuat button untuk meminta uang yang ada pada saat kas aktif sebelum tertutup". Sebelum ini rumus rekonsiliasi hanya mengenal tiga angka yang semuanya diturunkan dari transaksi penjualan; uang yang benar-benar keluar dari laci menghilang sebagai **selisih kurang** dan kasir yang menanggung tuduhannya.

- **Yang ditahan efeknya, bukan pencatatannya — dan itu keputusan pemilik 2026-08-21 di antara lima bentuk yang ditawarkan.** Menolak pencatatan (bentuk "owner-only") akan mengosongkan catatan kas: kasir yang tidak bisa mencatat tetap mengeluarkan uangnya, dan kita kembali ke keadaan hari ini dengan tambahan satu tombol yang tidak berguna. Yang dipilih memisahkan dua hal yang sempat tercampur di entri backlognya: **siapa yang boleh mencatat** (selalu kasir) dan **kapan angkanya boleh bergerak** (di bawah ambang, atau setelah pemilik membaca).
- **Tanpa penahan itu, fitur ini membatalkan `[BL-086]` dari sisi sebaliknya.** Kita baru saja menutup jalan kasir menyamakan angka dari sisi hitungan fisik; pengeluaran yang langsung mengurangi uang seharusnya membuka jalan yang sama dari sisi seberangnya — laci kurang Rp 200.000, catat pengeluaran Rp 200.000, selisih nol. **Pengeluaran adalah satu-satunya angka kas yang sumbernya ucapan manusia**; semua angka lain punya pembanding di `transactions`.
- **Yang menunggu persetujuan duduk DI BAWAH garis Selisih, tidak ikut menghitungnya** — pola yang sudah dipakai kas negatif `[BL-031]` dan alasannya sama: terlihat dan dipertanggungjawabkan, tapi belum jadi pengurang yang sah. Kasir tetap melihatnya supaya selisih kurang di layarnya punya keterangan, bukan jadi tuduhan tanpa konteks.
- **Setoran MASUK tidak pernah menunggu persetujuan, berapa pun nominalnya.** Ambangnya ada untuk menahan uang yang keluar. Menambah uang ke laci tidak bisa dipakai menutupi kekurangan — ia justru memperbesar tuntutan terhadap kasir sendiri, dan menahannya hanya akan membuat orang berhenti mencatat.
- **Ambangnya kolom tenant, bukan konstanta seperti `CashDrawer::MAX_SESSION_HOURS`,** atas permintaan pemilik agar bisa diubah dari dashboard — dan memang di sinilah tempatnya: Rp 50.000 adalah uang galon di satu toko dan setoran setengah hari di toko lain. Ia duduk di sebelah `min_margin_percent`, angka kebijakan pertama yang jadi kolom tenant dengan alasan yang sama persis. **Nilainya menentukan BENTUK fiturnya, bukan cuma besarannya:** 0 berarti setiap pengeluaran menunggu pemilik, angka sangat besar berarti tak ada yang pernah menunggu.
- **Kasir diberi tahu SELAGI mengetik bahwa nominalnya akan menunggu.** Aturan yang baru diketahui sesudah tombol simpan ditekan terbaca sebagai penolakan, bukan sebagai aturan — dan kasir akan menyimpulkan fiturnya rusak lalu berhenti memakainya.
- **Persetujuan `role:owner`, bukan izin modul `cash_drawer`,** dengan alasan yang sama seperti kas negatif: MELIHAT mutasi kas soal laporan, MENYETUJUINYA soal pertanggungjawaban. Menggantungkannya pada modul kas akan memberi kuasa menyetujui kepada setiap kasir yang boleh membuka sesi — yaitu justru orang yang pengeluarannya sedang ditinjau.
- **Keputusan yang sudah diambil tidak bisa diputar balik dari layar itu.** Membalik persetujuan berarti `expected_amount` sebuah sesi berubah SESUDAH kasirnya menghitung uang dan menandatangani selisihnya — menuduh orang atas angka yang berbeda dari yang ia lihat.
- **`reviewed_by` kosong pada yang disetujui otomatis, dan itu bukan kelalaian.** Keduanya sama-sama `approved` dan sama-sama menggerakkan angka; yang membedakan hanya apakah ada manusia yang membacanya, dan pemilik yang menelusuri selisih perlu bisa memisahkan itu.
- **Daftar yang menunggu TIDAK ditunda (`Inertia::defer`), tidak seperti daftar sesi di bawahnya.** Ia satu-satunya bagian halaman itu yang menuntut tindakan, dan tindakan yang baru muncul setelah kerangka pemuatan hilang akan terlewat oleh pemilik yang sudah selesai membaca.
- **Mutasi menempel langsung pada `cash_drawer_id`,** jadi kepemilikannya eksplisit dan tidak perlu diturunkan dari `user_id` + rentang jam seperti transaksi. Inilah yang dulu diminta `[BL-028]` Tahap B untuk transaksi, dan di sini ia gratis karena tabelnya lahir sesudah pelajaran itu.
- **Yang sengaja TIDAK dikerjakan: foto struk.** Ia sempat disarankan sebagai tambahan murah karena `ProofFileService` sudah ada, tapi jalur unggahnya menuntut direktori, klaim berkas, dan **kebijakan retensi yang belum diputuskan** — persoalan yang sama yang masih terbuka di `[BL-076]`. Dicatat sebagai `[BL-093]` alih-alih diselipkan setengah jadi.
- **Berkas:** `database/migrations/2026_08_21_043157_create_cash_drawer_movements_table.php` (baru) · `database/migrations/2026_08_21_043158_add_cash_payout_approval_threshold_to_tenants_table.php` (baru) · `app/Models/CashDrawerMovement.php` (baru) · `app/Http/Requests/StoreCashDrawerMovementRequest.php` (baru) · `app/Http/Controllers/Owner/CashDrawerMovementController.php` (baru) · `app/Services/CashDrawerReconciliation.php` — rumus + `movementsOf()` · `app/Http/Controllers/Cashier/CashDrawerController.php` — `storeMovement()` · `app/Http/Controllers/Owner/ReportController.php` — `pendingMovements` · `app/Http/Controllers/Owner/Settings/SystemBehaviorController.php` · `app/Models/CashDrawer.php`, `app/Models/Tenant.php` · `routes/web.php` · `resources/js/Pages/Cashier/CashDrawer.vue`, `CashDrawerClose.vue`, `Owner/CashDrawers/Index.vue`, `Owner/Settings/Operations.vue` · `README.md` — bagian Kasir, Laporan, dan Tugas terjadwal disegarkan · `tests/Feature/Cashier/CashDrawerMovementTest.php` (baru, 14 tes)

---

### [ADDITION] Sesi Kas Punya Umur, dan yang Lewat Ditutup Sistem Tanpa Mengaku Sudah Dihitung (BL-088)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — menutup `[BL-088]`, entri terakhir dari catatan pemilik 2026-08-21 yang menyangkut kas
- **Dampak:** Schema | Model | Service | Console | Controller | Frontend | Test
- **Breaking Change:** Ya, tapi tidak untuk data yang ada. Sesi kas yang dulu hidup selamanya kini berhenti setelah **24 jam**. Kolomnya lahir `false` untuk seluruh baris lama, jadi tidak ada satu pun sesi yang statusnya berubah oleh pemasangan ini — yang berubah adalah nasib sesi yang menggantung sesudahnya.
- **Deskripsi:** Sesi kas berumur `CashDrawer::MAX_SESSION_HOURS` (24 jam) sejak dibuka. Lewat itu `cash-drawers:expire` — terjadwal tiap jam pada menit ke-5 — menutupnya paksa: `closed_at` diisi, `closed_by_system` ditandai, `expected_amount` dicatat, dan `closing_amount` beserta `difference` **dibiarkan kosong**. Kasir diperingatkan di layar empat jam sebelum batasnya lewat, dan daftar Sesi Kas pemilik membedakan "Ditutup sistem" dari "Closed".
- **Alasan:** Dilaporkan pemilik — "masa hidup kas cuma sehari dan auto close dan perlu perbaikan ketika lewat". Sebelum ini tidak ada satu pun tugas terjadwal yang menyentuh `cash_drawers`: kasir yang pulang tanpa menekan "Tutup Kas" meninggalkan sesi yang esok paginya MENOLAK dibuka lagi ("Anda masih memiliki sesi kas yang terbuka"), sementara rekonsiliasinya diam-diam menghitung penjualan dua hari sebagai isi satu laci.

- **Sesi yang ditutup sistem tidak pernah mengaku sudah dihitung, dan itu butir yang tidak boleh dilonggarkan.** Mengisi `closing_amount` dengan `expected_amount` akan menghasilkan selisih nol yang rapi di setiap laporan — dan itu kebohongan yang persis sama dengan yang baru saja dilarang `[BL-086]`, hanya berpindah dari layar kasir ke basis data. `expected_amount` sebaliknya DIISI, karena ia angka milik sistem sendiri dan bukan pernyataan tentang uang fisik yang tidak pernah dihitung siapa pun.
- **Ongkosnya nyata dan diterima sadar:** uang fisik sesi itu hilang dari pertanggungjawaban. Yang ditukar dengannya adalah sesi yang tidak menggantung selamanya dan tidak memblokir kas keesokan harinya. Justru karena ongkosnya nyata, penandanya harus jujur — di situlah butir di atas berdiri.
- **`closed_by_system` jadi kolom sendiri, bukan diturunkan dari `closing_amount === null`.** Keduanya kebetulan sama hari ini. Menyandarkan artinya pada kebetulan itu berarti sesi mana pun yang kelak boleh ditutup tanpa hitungan — `[BL-087]` menyentuh wilayah yang sama — akan terbaca sebagai "ditutup sistem" tanpa satu baris kode pun berubah.
- **Tiap jam, bukan harian, dan alasannya sama persis dengan `open-bills:expire`.** Sapuan harian membuat batas 24 jam berarti "antara satu dan dua hari", tergantung sesi itu lahir beberapa menit sebelum atau sesudah sapuan lewat — dan peringatan di layar kasir yang menyebut sisa waktunya akan ikut berbohong. Digeser ke menit ke-5 supaya kedua sapuan tidak muncul sebagai satu lonjakan yang sama di log ketika salah satunya bermasalah.
- **Rekonsiliasi dihitung SEBELUM `closed_at` diisi.** Jendela sesi memakai `closed_at ?? now()`; menutup lebih dulu akan memotong jendela di titik yang sama dan menghasilkan angka yang benar hanya karena kebetulan urutan baris. Urutan itu ditulis eksplisit di servicenya supaya tidak ada yang "merapikannya" belakangan.
- **Peringatannya muncul EMPAT JAM sebelum batas, bukan sesudah.** Peringatan yang datang setelah sesi tertutup tidak berguna: uangnya sudah tidak bisa dihitung. Empat jam dipilih karena ia lebih panjang dari sisa shift mana pun yang masuk akal. Ada pula keadaan kedua yang sengaja tidak didiamkan — sudah lewat 24 jam tapi sapuan per jam belum menyentuhnya — dan di sana kalimatnya berubah jadi ajakan menutup sekarang selagi masih bisa dihitung.
- **Sisa waktunya dihitung dari jam dinding yang berdetak, bukan sekali saat halaman dirender.** Tab kasir dibiarkan terbuka semalaman, dan justru tab itulah yang paling butuh peringatan ini; angka sisa yang dihitung sekali akan basi persis di tempat ia paling dibutuhkan. Servernya karena itu mengirim dua TITIK WAKTU (`expires_at`, `warn_from`), bukan "sisa berapa jam".
- **Berkas:** `database/migrations/2026_08_21_020320_add_closed_by_system_to_cash_drawers_table.php` (baru) · `app/Services/CashDrawerExpiryService.php` (baru) · `app/Console/Commands/ExpireCashDrawers.php` (baru) · `app/Models/CashDrawer.php` — `MAX_SESSION_HOURS`, `STALE_WARNING_HOURS`, `staleCutoff()`, `scopeStale()` · `routes/console.php` — `hourlyAt(5)` · `app/Http/Controllers/Cashier/CashDrawerController.php` — prop `sessionLimit` · `resources/js/Pages/Cashier/CashDrawer.vue` · `resources/js/Pages/Owner/CashDrawers/Index.vue` · `tests/Feature/Cashier/CashDrawerExpiryTest.php` (baru, 8 tes)

---

### [ADDITION] Membuka Angka Seharusnya Meninggalkan Jejak yang Dibaca Pemilik (BL-090)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — menutup `[BL-090]`, sisa `[BL-086]` yang dipecah di hari yang sama
- **Dampak:** Schema | Model | Controller | Route | Frontend | Test
- **Breaking Change:** Tidak. Tabel baru yang lahir kosong; tidak ada perilaku lama yang berubah selain munculnya satu kolom di daftar sesi kas pemilik.
- **Deskripsi:** Setiap kali kasir menekan "Tampilkan uang seharusnya" di halaman sesi kas, satu baris tercatat di `cash_drawer_reveals` — laci mana, siapa, jam berapa. Daftar Sesi Kas milik pemilik mendapat kolom **"Angka Dibuka"**: jumlah pembukaan, dengan waktu pembukaan pertama di tooltip-nya.
- **Alasan:** `[BL-086]` menyembunyikan angka "seharusnya di laci" di layar, tapi ia tetap ikut props Inertia dan terbaca dari devtools. Pemilik memilih **bentuk (2)** di antara dua yang ditawarkan `[BL-090]`: mencatat, bukan mencegah.

- **Bentuk (1) ditolak, dan alasannya bukan kesulitan teknis.** Endpoint yang menahan angkanya sampai hitungan fisik masuk memang lebih ketat — tapi ia mematikan tombol "Tampilkan uang seharusnya" yang pemilik sendiri minta untuk peragaan di `[BL-086]`. Penjaga yang menghapus fitur yang diminta orang yang sama akan dicabut lagi pada peragaan berikutnya.
- **Ini cara kasir sungguhan menyelesaikannya: penyimpangan tidak diblokir, ia jadi terlihat.** Membuka angkanya sah dan ada alasan wajar untuk melakukannya — mengecek laci di tengah shift, menjawab pertanyaan pemilik. Yang dijawab jejak ini hanya satu hal: apakah angkanya sudah terbaca sebelum uang fisik dihitung. Penilaiannya milik pemilik, dan **tidak ada satu pun tempat di kode ini yang menyebutnya pelanggaran**.
- **Kasir diberi tahu SEBELUM menekan, bukan sesudah.** Kalimat di bawah tombolnya berbunyi "Boleh dibuka kalau memang perlu — pemilik akan melihat catatan bahwa angkanya dibuka." Jejak yang baru diketahui belakangan terasa seperti jebakan, dan kasir yang merasa dijebak berhenti mempercayai seluruh layar itu — termasuk bagian yang dibuat untuk melindunginya.
- **Tabel tersendiri, bukan kolom penghitung di `cash_drawers`.** Yang ditanyakan pemilik saat curiga bukan "berapa kali" melainkan "kapan, dan oleh siapa". Penghitung menjawab yang pertama saja dan tidak bisa dibuat menjawab yang kedua tanpa migrasi kedua. `user_id`-nya pun disimpan terpisah dari `cash_drawers.user_id`: pemilik yang kelak membuka laci kasirnya akan tercatat sebagai dirinya sendiri.
- **Klien menahan diri satu kali per pemuatan halaman; servernya tidak.** Menyalakan-mematikan bergantian akan menumpuk baris yang menjawab hal yang sama. Tapi `reveal()` tidak menganggap pengungkapan kedua sebagai duplikat — memuat ulang halaman lalu membukanya lagi adalah peristiwa lain di jam yang lain, dan itu justru yang ingin dilihat pemilik.
- **Gagal mencatat tidak membatalkan apa pun.** Angkanya sudah terbuka di layar sebelum permintaannya dikirim; `reveal()` menjawab `back()` tanpa muatan dan diam saja bila tidak ada sesi terbuka. Permintaan yang datang terlambat — sesi baru ditutup di perangkat lain — bukan kesalahan pengguna, jadi bukan 4xx.
- **Daftarnya memakai `withCount` + `withMin`, bukan memuat barisnya.** Yang dipajang hanya dua angka; memuat seluruh jejak untuk 25 sesi berarti puluhan baris yang tak satu pun ditampilkan. Yang ditampilkan waktu **pertama**, bukan terakhir: "kapan ia pertama tahu" menjawab apakah hitungannya sudah tercemar; pembukaan terakhir tidak.
- **Berkas:** `database/migrations/2026_08_20_215535_create_cash_drawer_reveals_table.php` (baru) · `app/Models/CashDrawerReveal.php` (baru) · `app/Models/CashDrawer.php` — relasi `reveals()` · `app/Http/Controllers/Cashier/CashDrawerController.php` — `reveal()` · `routes/web.php` · `app/Http/Controllers/Owner/ReportController.php` — `cashDrawers()` · `resources/js/Pages/Cashier/CashDrawer.vue` · `resources/js/Pages/Owner/CashDrawers/Index.vue` · `tests/Feature/Cashier/CashDrawerRevealTrailTest.php` (baru, 5 tes)

---

### [REFACTOR] Tutup Kas Punya Halamannya Sendiri, dan Angka yang Sudah Terbaca Tidak Bisa Disunting Diam-diam (BL-086 butir 2)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — butir 2 `[BL-086]`, menutup entrinya seluruhnya. Sisa penegakan sisi servernya dipecah jadi `[BL-090]`
- **Dampak:** Controller | Route | Frontend | Test
- **Breaking Change:** Tidak. Rute lama tidak dihapus dan tidak berpindah; yang lahir satu rute baru.
- **Deskripsi:** Alur tutup kas pindah ke halamannya sendiri, `GET /cashier/cash-drawer/close`, lengkap dengan modal konfirmasi sebelum sesi benar-benar ditutup. Halaman kas menyisakan keadaan sesi, tombol ke POS, dan satu tautan "Tutup Kas". Bersamaan dengan itu, kolom uang fisik **terkunci begitu ringkasan pernah dibuka** — mengubahnya menuntut menekan "Hitung ulang", yang mengosongkan kolomnya.
- **Alasan:** Dilaporkan pemilik — "seharusnya ada flow tersendiri ketika mau tutup kas (jadi kayak 2 menu begitu, bukan dalam 1 menu keliatannya)". Membuka kas dan mempertanggungjawabkannya terpisah beberapa jam dan berbeda niat; sebelum ini keduanya menumpang satu layar, sehingga kasir melewati alur tutup kas setiap kali sekadar memeriksa lacinya.

- **Lubang "Kembali" ditutup di commit yang sama, dan itu bukan tambahan melainkan syarat.** `[BL-086]` butir 1 menyembunyikan angka seharusnya sampai hitungan fisik disetorkan — tapi dari layar ringkasan kasir bisa menekan "Kembali" dan menyunting hitungannya **setelah** membaca selisih. Penjagaan yang batal dalam dua klik bukan penjagaan, jadi memisahkan rutenya tanpa menutup lubang ini akan memindahkan cacatnya, bukan memperbaikinya.
- **Yang dipasang friksi yang terlihat, bukan larangan.** Salah ketik itu nyata, dan kasir yang terkunci pada angka salah akan menutup kas dengan selisih karangan — persis kerusakan yang sedang dicegah. Karena itu "Hitung ulang" **mengosongkan** kolomnya alih-alih sekadar membuka kuncinya: revisi dimulai dari nol dan jadi tindakan yang disengaja, bukan koreksi diam-diam terhadap angka yang jawabannya sudah terbaca.
- **`showClose()` mengembalikan kasir tanpa sesi ke halaman kas, bukan 404.** Tidak ada yang bisa ditutup, dan yang ia butuhkan saat itu adalah formulir membuka kas — yang ada di halaman itu. 404 hanya benar secara teknis.
- **Halaman baru ini MEMANG mengirim angka rekonsiliasinya, dan itu bukan kelalaian.** Kasir datang ke sini untuk mempertanggungjawabkan lacinya; yang dijaga `[BL-086]` adalah halaman sesi, tempat angka itu terbaca sepanjang shift tanpa ada yang diminta dari kasir. Batasnya tetap sama: kapan hitungan fisik disetorkan.
- **Satu perbaikan kecil ikut terbawa:** selisih negatif dulu tampil `Rp -3.000` — tanda minus mendarat di antara satuan dan angkanya karena `formatCurrency` menerima bilangan negatif apa adanya. Sekarang `−Rp 3.000`, sejajar dengan baris "Kembalian keluar" di atasnya yang sudah memakai bentuk itu sejak dulu.
- **Modal konfirmasinya memakai `ConfirmDialog` yang sudah ada,** bukan modal baru. Ia menyebut nominal yang akan tercatat, karena konfirmasi yang tidak mengulang angkanya hanya melatih orang menekan "Ya".
- **Berkas:** `app/Http/Controllers/Cashier/CashDrawerController.php` — `showClose()` · `routes/web.php` — `cashier.cash-drawer.close-form` · `resources/js/Pages/Cashier/CashDrawerClose.vue` (baru) · `resources/js/Pages/Cashier/CashDrawer.vue` — blok tutup kas (140 baris) diganti satu tautan · `tests/Feature/Cashier/CashDrawerCloseFlowTest.php` (baru, 5 tes) · `tests/Feature/Cashier/CashDrawerBlindCountTest.php` (jangkar penjaganya mengikuti pemecahan halaman)

---

### [ADDITION] Angka yang Jadi Jawaban Disembunyikan Selama Sesi Berjalan (BL-086 butir 1)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — butir 1 & 3 `[BL-086]`; butir 2 (pemecahan rute buka/tutup kas) **belum dikerjakan** dan entrinya tetap terbuka
- **Dampak:** Frontend | Test
- **Breaking Change:** Tidak. Tidak ada rute, kolom, maupun perhitungan yang berubah — yang berubah adalah kapan angkanya boleh dibaca.
- **Deskripsi:** Di panel "Sesi Kas Aktif", ketiga angka yang menyusun isi laci — penjualan tunai, kembalian keluar, dan totalnya "Seharusnya di laci" — kini tertutup secara bawaan, dengan satu tombol **"Tampilkan uang seharusnya"** yang membukanya. Ringkasan tutup kas tetap membuka semuanya seperti sebelumnya.
- **Alasan:** Dilaporkan pemilik — "kasir sisa input yang nyata (agar tidak manipulatif)". Sebelum ini kasir bisa membaca "Seharusnya di laci Rp 518.000" sepanjang shift lalu mengetik ulang angka itu di kolom uang fisik: selisihnya selalu nol, dan laci yang benar-benar kurang tidak pernah ketahuan.

- **Penghitungan buta adalah satu-satunya alasan rekonsiliasi kas ada.** Seluruh mesin di `CashDrawerReconciliation` — pemisahan per laci, tanggal efektif, kas negatif `[BL-031]` — dibangun untuk menghasilkan satu angka pembanding. Angka pembanding yang sudah dibaca lebih dulu oleh orang yang sedang dibandingkan bukan pembanding, dan seluruh mesin itu jadi hiasan.
- **Yang ditutup ketiga angkanya, bukan cuma totalnya, dan itu koreksi terhadap usulan di backlognya.** `expected_amount` = modal + tunai masuk − kembalian keluar, dan modal awal justru **tetap terlihat** karena kasir sendiri yang memasukkannya pagi tadi. Menutup total sambil memajang dua penjumlah lainnya bukan penghitungan buta, itu soal hitungan — dan soal hitungan yang bisa dikerjakan di kepala dalam tiga detik.
- **Batas antara "bocoran" dan "bantuan" adalah kapan hitungan fisik disetorkan, bukan angka mana yang ditampilkan.** Rincian yang sama — tunai masuk, kembalian keluar, non-tunai bertanda "tidak masuk laci", kas negatif — tetap utuh di ringkasan tutup kas. `[BL-028]` Tahap A butir 4 yang memintanya masih berlaku sepenuhnya di sana: kasir tidak boleh mencari uang QRIS di dalam laci. Yang butir itu tidak pisahkan, dan entri ini pisahkan, adalah **sebelum** versus **sesudah** kasir menyetorkan hitungannya.
- **Yang dipajang saat tertutup adalah alasannya, bukan sekadar titik-titik.** Kasir yang tidak tahu kenapa angkanya hilang akan mengira halamannya rusak dan melaporkannya sebagai bug — dan sebuah penjaga yang terbaca sebagai kerusakan akan dicabut oleh orang berikutnya yang "membetulkannya".
- **Ini penyembunyian di sisi klien, dan layarnya tidak berpura-pura sebaliknya.** `reconciliation` tetap ikut props Inertia dan terbaca dari devtools. Pemilik meminta bentuk yang bisa **diperagakan**, dan untuk itu — juga untuk menghilangkan godaan sehari-hari — ini cukup. Penegakan sungguhan menuntut `index()` berhenti mengirimkannya sampai hitungan fisik disetorkan, dan itu tetap terbuka di `[BL-086]`.
- **Satu lubang ditemukan saat mengerjakannya dan sengaja TIDAK ditambal di sini:** dari layar ringkasan, kasir bisa menekan "Kembali" lalu mengubah angka uang fisiknya **setelah** membaca selisih. Penutupnya murah — kunci kolomnya begitu ringkasan pernah dibuka — tapi ia mengubah alur, dan alurnya sedang menunggu pemecahan rute di butir 2. Menambalnya sekarang berarti membongkar layar yang sama dua kali. Ia ditulis di backlognya, bukan didiamkan.
- **Berkas:** `resources/js/Pages/Cashier/CashDrawer.vue` · `tests/Feature/Cashier/CashDrawerBlindCountTest.php` (baru, 4 tes)

---

### [REFACTOR] Aturan Saran Jual dan Diskon Keluar dari "Keuangan", Jadi Grup Sendiri (BL-085)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — menutup `[BL-085]`, entri kedua dari catatan pemilik 2026-08-21
- **Dampak:** Frontend | Test
- **Breaking Change:** Tidak. Tidak ada rute, izin, maupun gerbang yang berubah — hanya di grup mana tautannya dipajang.
- **Deskripsi:** Grup baru **"Penjualan & Promosi"** di sidebar owner, berisi Saran Jual, Aturan Saran Jual, dan Aturan Diskon. Keuangan menyisakan tujuh item: Laporan Harian, Laporan Bulanan, Transaksi, Sesi Kas, Koreksi Offline, Pembayaran, AI Analysis.
- **Alasan:** Dilaporkan pemilik — "menu nya tercampur sebagai keuangan dan saya rasa kurang cocok". Aturan Saran Jual (`[BL-074]`) dan Aturan Diskon (`[BL-018]`) adalah tempat owner **menyusun cara berjualan**: keduanya menulis aturan yang berlaku ke depan, bukan melaporkan apa yang sudah terjadi. Menaruhnya di antara Laporan Harian dan Sesi Kas membuat orang mencarinya di tempat yang salah.

- **"Saran Jual" ikut pindah meski ia laporan, dan itu keputusan, bukan kelalaian.** Ia laporan **tentang** dua item di bawahnya. Menahannya di Keuangan berarti owner membaca hasil saran jualnya di satu grup lalu membetulkan sebabnya di grup lain — pemisahan yang benar secara kategori dan salah secara pekerjaan.
- **Ditemukan saat memindahkan, bukan saat mencari: grup Keuangan tinggal empat piksel dari terpotong diam-diam.** Isi grup dibatasi `max-h-96` (384px) saat terbuka; sepuluh itemnya mengukur **380px** di peramban. Item kesebelas mana pun — dan sudah ada `[BL-065]` (pajak) serta `[BL-087]` yang kelak menambah layar — akan hilang dari sidebar tanpa satu pun tanda, karena pembatasnya `max-height` bukan `overflow`. Sesudah pemecahan: 266px dan 114px. Batas itu **tidak diubah**; yang berkurang adalah kemungkinannya tertabrak, dan ia tetap ranjau bagi grup mana pun yang tumbuh melewati sepuluh item.
- **Gerbang tiap item ikut pindah apa adanya, dan itu yang dijaga tesnya.** Menulis aturan saran jual dan diskon `ownerOnly`; membaca laporannya cukup `perm: 'reports'`. Menyamakan keduanya saat memindahkan grup akan memberi kuasa MENULIS kepada siapa pun yang hanya diberi hak MEMBACA laporan — persis yang ditolak `UpsellRuleController` di komentar kelasnya — dan **tidak akan terlihat sama sekali dari layar owner**, karena owner melewati setiap pemeriksaan.
- **Keadaan buka-tutup grup tidak perlu disentuh.** `collapsedGroups` berkunci label dan bawaannya terbuka, jadi label baru langsung bekerja tanpa migrasi keadaan apa pun.
- **Berkas:** `resources/js/Layouts/OwnerLayout.vue` · `tests/Feature/Owner/SidebarNavGroupsTest.php` (baru, 3 tes)

---

### [HOTFIX] Sidebar Owner Menyebut Nama Toko yang Sedang Dibuka, Bukan Nama Produknya (BL-084)
- **Tanggal:** 2026-08-21
- **Fase Terkait:** Di Luar Fase — menutup `[BL-084]`, entri pertama dari catatan pemilik 2026-08-21
- **Dampak:** Frontend | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Kepala sidebar owner membaca `auth.tenant.name` — nama usaha penggunanya — dengan cadangan `'SAPI POS'`. Glyph di sebelahnya ikut jadi inisial toko itu.
- **Alasan:** Shell owner menyebut nama produk di satu-satunya tempat yang seharusnya menyebut nama pembacanya. Kasir di aplikasi yang sama sudah melihat nama tokonya sendiri di topbar sejak lama; hanya sidebar owner yang tidak ikut, dan tidak ada alasan yang pernah ditulis untuk itu.

- **`[BL-033]` tidak dilanggar, lingkupnya diperjelas.** Keputusan "SAPI POS resmi" mengatur **permukaan publik** — landing, login, halaman galat — tempat pembacanya memang belum punya toko dan merek produklah satu-satunya yang bisa disebut. Shell owner ikut terbawa padahal pembacanya sudah pasti berada di dalam satu toko, sudah masuk, dan sudah tahu memakai aplikasi apa.
- **Cadangannya `'SAPI POS'`, bukan string kosong,** dan itu bukan kehati-hatian berlebihan: shell ini juga dirender sesaat sebelum props tenant sampai, dan kepala sidebar yang kosong terbaca sebagai halaman rusak. Pola dan cadangannya disalin persis dari `CashierTopbar.vue` — dua permukaan yang menjawab pertanyaan sama ("saya sedang di toko mana") tidak boleh menjawabnya dengan dua cara.
- **Glyph `S` ikut berubah, dan pertanyaannya sengaja dijawab di sini alih-alih ditunda.** `S` di sebelah tulisan "Kopi Nusantara" tidak terbaca sebagai penanda produk melainkan sebagai merek yang salah. Satu baris `computed`; kalau pemilik memutuskan sebaliknya, mengembalikannya juga satu baris.
- **Nama panjang dipotong, bukan dibiarkan mendorong tata letak.** Sidebarnya `w-64` dan `overflow-hidden`; nama toko yang panjang akan tertelan diam-diam di tengah kata. `truncate max-w-[10.5rem]` plus `title` membuat pemotongannya terlihat sebagai pemotongan, dan nama utuhnya tetap bisa dibaca dari tooltip.
- **Tesnya dua lapis karena satu lapis tidak bisa membuktikan apa pun sendirian.** Shell-nya Vue dan tidak pernah dirender PHP, jadi tes HTTP hanya sanggup membuktikan **datanya sampai** (`auth.tenant.name` ada di props tiap halaman owner) — sementara berkas Vue-nya bisa saja berhenti membacanya besok tanpa satu tes pun gagal. Lapis kedua karena itu memeriksa berkas layout-nya langsung: `>SAPI<` tidak boleh kembali sebagai teks tetap.
- **Berkas:** `resources/js/Layouts/OwnerLayout.vue` · `tests/Feature/Owner/SidebarBrandingTest.php` (baru, 2 tes)

---

### [ADDITION] Lima Tangkapan Layar Landing Diambil Ulang dari Aplikasi yang Berjalan, dan Jadi WebP (BL-032 butir 3)
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — butir terakhir `[BL-032]`, tertahan sejak 2026-08-14 bukan karena kodenya melainkan karena tidak ada alat yang bisa menulis berkas gambar. Menutup `[BL-032]` seluruhnya
- **Dampak:** Frontend | Aset | Test
- **Breaking Change:** Tidak
- **Deskripsi:** `Dashboard-owner`, `POS-Interface`, `Reports-Daily`, `Stock-Management`, dan `Product-List` diambil ulang dari aplikasi yang benar-benar berjalan, menggantikan berkas bertanggal 25 Mei. Kelimanya kini WebP q80 pada 2160x1350 — dimensi yang sama persis dengan PNG yang digantikannya. Keenam rujukan di landing menunjuk `.webp`, lengkap dengan `width`/`height` dan `loading="lazy"` untuk lima yang berada di bawah lipatan.
- **Alasan:** Berkas Mei mendahului hampir semua yang dikirim sejak itu: tema hijau, topbar tagihan terbuka, papan antrian, Saran Jual, Aturan Diskon, AI Analysis, Koreksi Offline, Staf, dan Role tidak ada satu pun di sana. `Product-List.png` yang lama bahkan memajang ikon "gambar rusak" di setiap kartu.

- **Penghalang yang dicatat dua kali sebagai buntu ternyata hanya satu server MCP yang belum tersambung.** Entri backlognya sudah menuliskan tiga jalan keluar; yang terjadi adalah nomor (3) — `chrome-devtools` tersambung, dan alat `take_screenshot`-nya menerima `filePath`. Yang membuat hasilnya setara berkas lama adalah `emulate` dengan `<lebar>x<tinggi>x<DPR>`: 1440x900 pada DPR 1,5 menghasilkan 2160x1350 asli, jadi ketajaman 2x didapat tanpa memperbesar gambar sedikit pun.
- **1440 CSS px dipilih setelah 1080 terbukti salah, dan itu bukan soal selera.** Pada 1080 px, keempat kartu statistik dashboard memampatkan angkanya jadi "Rp 3.0…", "Rp 13…", dan "Rp 9.2…". Tangkapan layar produk yang memajang angka terpotong lebih buruk daripada tangkapan layar usang. Pada 1440 keempatnya utuh.
- **Keranjang POS sengaja diisi lebih dulu.** Layar kasir berkeranjang kosong tidak menunjukkan apa pun yang membedakan aplikasi ini; yang terpasang sekarang memuat satu item bermodifier (Iced + Less Sugar), satu item biasa, tombol "Harga khusus" per baris, serta "Tunda Bayar" dan "BAYAR" dalam keadaan aktif. Pengisiannya berhenti tepat sebelum "BAYAR" — tidak ada transaksi yang tercatat demi sebuah gambar.
- **Turun ke WebP menghemat lebih dari yang terlihat.** PNG hasil tangkapan berjumlah 1.066 KB; WebP q80 dari berkas yang sama berjumlah 294 KB. Yang layak dicatat: itu **lebih ringan daripada lima PNG Mei yang digantikannya** (531 KB), padahal isinya jauh lebih padat.
- **`width`/`height` dipasang bersama `loading="lazy"`, bukan sesudahnya.** Lima dari enam gambar dimuat malas, jadi mereka menyusul justru ketika pembaca sedang membaca; tanpa dimensi, tiap kedatangan menggeser isi halaman. Yang di hero sengaja **tidak** lazy karena ia di atas lipatan. Sebuah test mengunci keenamnya membawa dimensi.
- **Dua temuan lain saat memotret sengaja tidak dikerjakan di sini**, dan keduanya jadi entri sendiri: `[BL-082]` (aplikasi berjalan di UTC sementara tokonya tidak) dan `[BL-083]` (kartu melayang di hero menjanjikan prediksi stok yang tidak ada mesinnya).
- **Yang diketahui masih kurang:** nol produk demo punya foto di kedua tenant, jadi kartu produk memakai penampung berinisial.
- **Berkas:** `public/{Dashboard-owner,POS-Interface,Reports-Daily,Stock-Management,Product-List}.webp` (baru) · `public/{…}.png` (dihapus) · `resources/views/public/landing.blade.php` — enam rujukan · `tests/Feature/Public/LandingClaimsTest.php` — dua test baru (71 tes lulus di `tests/Feature/Public`)

---

### [HOTFIX] Seeder Demo Melewatkan Hari yang Hanya Berisi Tagihan Terbuka
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — ditemukan saat menyegarkan data demo untuk `[BL-032]` butir (3)
- **Dampak:** Seeder | Test
- **Breaking Change:** Tidak
- **Deskripsi:** `FillsMissingSalesDays::existingSalesDays()` kini hanya menghitung transaksi `completed` sebagai "hari ini sudah ada penjualannya". Sebelumnya ia menghitung transaksi berstatus apa pun.
- **Alasan:** Satu tagihan terbuka `pending` yang ditinggalkan — sisa uji coba `[BL-031]` — cukup untuk menandai hari itu terisi, padahal omzetnya nol. Seeder lalu melewatinya, dan dashboard "hari ini" memajang angka kosong. Itu persis kegagalan yang trait ini ditulis untuk mencegah, hanya lewat pintu yang lain: ia dijalankan tepat pada hari demo, saat kesalahan paling mahal.

- **`voided` dan `unsettled` ikut dikecualikan dengan alasan yang sama.** Yang pertama penjualan yang dibatalkan; yang kedua, sejak `[BL-031]`, justru kas negatif. Tidak satu pun dari keduanya berarti "hari itu ada penjualannya".
- **Yang dijaga trait ini sejak awal tetap utuh:** transaksi yang dibuat lewat UI menjelang demo tidak disentuh, selama ia memang penjualan selesai. Dua test mengunci kedua sisinya — hari yang hanya berisi tagihan terbuka tetap disemai, hari yang penjualannya sudah selesai tetap dilewati.
- **Terbukti menangkap cacatnya:** test barunya dijalankan sekali dengan penyaring status dicabut dan gagal di sana, lalu lulus setelah dikembalikan.
- **Berkas:** `database/seeders/Concerns/FillsMissingSalesDays.php` · `tests/Feature/DemoSeederTest.php` — dua test baru (6 tes lulus)

---

### [ADDITION] Kuota AI Tambahan Akhirnya Bisa Dibeli, dan Dilepas Lagi — Persis seperti Kursi (BL-069)
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — menutup `[BL-069]`, sisa `[BL-053]` butir (c) yang dipecah 2026-08-08 dan terhalang harga sampai keputusan pemilik 2026-08-19
- **Dampak:** Migrasi | Model | Service | Controller | Route | Config | Frontend | Test
- **Breaking Change:** Tidak. Kolomnya lahir nol, jadi tidak ada satu pun tenant yang jatah atau tagihannya berubah oleh pemasangan ini.
- **Deskripsi:** Tenant bisa membeli blok kuota analisis AI dari halaman langganan — satu blok = **+5 analisis/hari** seharga **Rp 15.000/bulan**, berulang, seragam antar paket. Berlaku hari itu juga, masuk tagihan bulanan berikutnya, dan bisa dilepas lagi dengan pelepasan yang berlaku satu periode penuh ke depan.
- **Alasan:** Jatah AI per paket (5/15/30/60) adalah satu-satunya angka di aplikasi ini yang kurangnya hanya bisa diobati dengan naik paket — langkah Rp 50.000 untuk kebutuhan yang kadang sebesar lima analisis. `[BL-053]` sudah memutuskan bentuknya di 2026-08-07 ("bayar bulanan begitu"), dan yang menahannya sejak itu bukan kodenya melainkan angkanya.

- **Seluruhnya meniru pola seat, dan kemiripan itu keputusan, bukan kemalasan.** Tiga kolom yang mencerminkan `purchased_extra_seats`/`scheduled_extra_seats`/`seat_release_at` satu lawan satu, `aiQuotaChargeFor()` kembaran `seatChargeFor()`, `grantAiQuota()`/`releaseAiQuota()`/`applyDueAiQuotaReleases()` kembaran ketiga metode seat, dan panel yang duduk tepat di bawah panel kursi. Pola kedua untuk bentuk yang sama akan berselisih pada hal yang paling mahal untuk salah: apa yang berhak ditagih.
- **Yang dijual PLAFON HARIAN, bukan kredit — dan layarnya menyebut kerugiannya sendiri.** Keputusan pemilik 2026-08-19 menolak saran paket kredit di entri backlognya secara sadar: keseragaman pola pembelian dinilai lebih berharga daripada penghematan kredit. Akibatnya sebagian pembeli membayar Rp 15.000 untuk kapasitas yang mereka pakai beberapa hari saja, dan panelnya mengatakan itu terus terang ("kalau kuota ekstra hanya Anda butuhkan beberapa hari sebulan, biayanya tetap sebulan penuh"). Keputusan yang diambil sadar adalah keputusan yang boleh disebutkan kepada yang membayarnya.
- **`profit_by_item` dibatasi LEBIH DULU, sebelum satu baris harga pun ditulis.** Pengukuran `[BL-069]` menemukan konteks AI tidak tumbuh mengikuti jumlah transaksi — `AiContextService` mengagregasi — kecuali satu bagian: `profit_by_item` mengirim satu baris per produk tanpa batas, dan tenant 200 produk menambah 5.000–8.000 token, tiga sampai empat kali lipat dari 1.800 yang terukur. Harga yang ditetapkan atas ongkos tanpa atap akan selalu salah untuk tenant terbesar, yaitu justru yang paling mungkin membeli. Sekarang berhenti di 20 baris (`ai.context.profit_by_item_limit`), dan **sisanya diringkas jadi satu baris agregat, bukan dibuang diam-diam** — daftar yang dipotong tanpa tanda akan terbaca model sebagai seluruh katalog, dan jawabannya akan menyebut "produk paling merugi Anda" untuk produk yang kebetulan lolos batas.
- **Kuota yang dibeli ditambahkan meski batas paketnya NOL, dan itu bedanya dari promo.** `bonusOn()` menolak membuka fitur yang sebuah paket sengaja tidak menjualnya, karena promo umum tidak pernah diminta siapa pun. Kuota yang dibeli sebaliknya: ia diminta, disetujui, dan masuk tagihan bulanan. Menelannya karena tenant kebetulan turun ke paket berjatah nol berarti menagih kapasitas yang tidak pernah diberikan. Yang menolak pembelian di paket tanpa AI adalah alur belinya, bukan pembacanya.
- **Urutan pembacaan tetap tunggal (`[BL-047]`(b)), tidak lahir pembaca kedua.** Kuota beli disisipkan di `AiQuota::baseLimitFor()` sebagai lapis di ATAS paket — bukan mata rantai keempat di bawahnya, yang akan membuat tenant `paid-3` yang membeli satu blok justru turun jatahnya dari 60 ke 5. Promo tetap terakhir, di atas hasil semuanya.
- **Harganya di config, bukan kolom di `plans`,** dan di situlah ia berpisah dari seat. `extra_seat_price` bertangga per paket; ongkos satu analisis tidak berbeda menurut paket pembelinya, jadi tangga di sana hanya akan jadi angka yang harus dijelaskan tanpa punya dasar.
- **Ada atap `max_blocks` (20), dan seat tidak punya padanannya.** Plafon harian yang dibeli tidak pernah ditinjau ulang siapa pun sesudahnya: tanpa batas, satu salah ketik ("100" alih-alih "1") jadi tagihan Rp 1.500.000 sekaligus paparan ongkos 500 analisis/hari yang menetap sampai ada yang menyadarinya.
- **Tidak ada padanan `seatReleaseCeiling()`, dan ketiadaannya disengaja.** Seat yang diduduki staf aktif tidak boleh dilepas karena pelepasannya mematikan akun orang yang sedang bekerja. Kuota AI tidak diduduki siapa pun — yang terjadi paling buruk adalah jatah harian turun kembali ke angka paketnya, dan itu justru yang tenant minta.
- **Larangan `[BL-067]`(e) dicabut, bukan dilanggar.** Landing dan `/harga` dilarang menjanjikan pembelian kuota AI SELAMA alurnya belum berbentuk. Sekarang berbentuk, jadi `/harga` menyebutnya beserta harga dan satuannya. Test penjaganya tidak dihapus melainkan **diganti syaratnya**: yang dijaga kini bukan lagi "jangan menyebut", melainkan "jangan menjualnya sebagai paket sekali pakai" — selisih antara plafon bulanan dan kredit sekali beli baru ketahuan di tagihan bulan kedua, tempat paling mahal untuk menemukannya.
- **Yang menentukan untung-rugi fitur AI tetap PILIHAN MODEL, bukan harga kuotanya, dan entri ini tidak mengubahnya sedikit pun.** Rentang ongkos antar model 60× dari ujung ke ujung. Margin Rp 15.000/+5 adalah 91% pada `gpt-4o-mini` dan **negatif** pada model kelas Sonnet, di mana `paid-3` sudah merugi meski tak seorang pun membeli tambahan. Peringatannya ditulis di `config/subscription.php` tepat di sebelah angkanya: periksa tabel ongkos di `[BL-069]` sebelum mengganti `AI_SUMOPOD_MODEL`, bukan sesudahnya.
- **Yang sengaja TIDAK dikerjakan:** daftar model AI yang tersedia — nama-nama yang beredar belum diverifikasi terhadap katalog SumoPod maupun harga resminya, dan menuliskannya ke config berarti menawarkan pilihan yang bisa gagal di panggilan pertama. Penghitungan pemakaian yang berbeda per model juga tidak dibangun, atas pertimbangan pemilik bahwa ia berlebihan: satu satuan ("satu analisis") dipertahankan apa pun model di belakangnya, sehingga seluruh risiko perubahan model ditanggung sisi kita — benar untuk tenant, dan justru karena itu batas modelnya harus dijaga di sisi kita.
- **Berkas:** `database/migrations/2026_08_20_153305_add_purchased_ai_quota_to_subscriptions_table.php` (baru) · `app/Http/Controllers/Billing/AiQuotaController.php` (baru) · `app/Models/Subscription.php` · `app/Services/Ai/AiQuota.php` · `app/Services/SubscriptionService.php` · `app/Services/AiContextService.php` · `app/Services/Pricing/PublicPricing.php` · `app/Http/Controllers/Billing/SubscriptionController.php` · `app/Console/Commands/AdvanceSubscriptionLifecycle.php` · `config/subscription.php` · `config/ai.php` · `routes/web.php` · `resources/js/Pages/Billing/Show.vue` · `resources/views/public/pricing.blade.php` · `tests/Feature/Ai/AiQuotaPurchaseTest.php` (baru, 10 tes) · `tests/Feature/Subscription/AiQuotaPurchaseFlowTest.php` (baru, 13 tes) · `tests/Feature/Subscription/AutoInvoiceTest.php` (+6 tes) · `tests/Feature/AiContextServiceTest.php` (+3 tes) · `tests/Feature/Public/PlanContentsTest.php` (penjaga `[BL-067]`(e) diganti syaratnya)

---

### [DEPRECATE] Testimoni Karangan Dicabut, Diganti Bagian yang Tidak Mengaku Sebagai Kesaksian (BL-078)
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — sisa terakhir `[BL-032]`, dipisah 2026-08-14 karena keputusannya pemasaran, bukan teknis. Menutup `[BL-078]`
- **Dampak:** Frontend | Aset | Test
- **Breaking Change:** Tidak
- **Deskripsi:** Bagian "Testimoni — Cerita Sukses Bersama SAPI" di landing dicabut. Tiga kutipan bernama (Andi/Senja Coffee, Santi/Roti Enak, Budi/Toko Kelontong Modern) beserta tiga potret stoknya hilang, digantikan bagian **"Untuk Siapa SAPI Dibuat"**: tiga kartu persona — Kafe & Kedai Kopi, Toko Kelontong & Retail, Usaha dengan Beberapa Kasir — yang masing-masing menyebut tiga kemampuan yang benar-benar ada.
- **Alasan:** Dijawab pemilik 2026-08-20: kutipannya memang karangan. Basis data berisi dua tenant, keduanya demo (`Kopi Nusantara`, `Kopi Story`); tidak ada pelanggan bernama itu dan potretnya foto stok. `[BL-032]` sudah membuang empat klaim palsu lain di halaman yang sama pada 2026-08-14 — ini yang tersisa, dan ia satu-satunya karangan di permukaan pertama yang dilihat calon klien.

- **Diganti, bukan dihapus, dan itu pilihan pemilik di antara dua jalan yang sama jujurnya.** Menghapus berarti menunggu pengguna sungguhan yang bersedia dikutip. Mengganti bentuknya menyampaikan hal yang sama — "aplikasi ini untuk usaha seperti Anda" — tanpa mengarang orang yang mengatakannya. Tata letaknya karena itu tidak dibongkar: tiga kartu tetap tiga kartu, hanya nama+wajah+tanda kutip yang berganti jadi ikon, judul kategori, dan daftar kemampuan.
- **Dua kemampuan hampir ikut ditulis padahal tidak ada wujudnya, dan keduanya dicegat sebelum mendarat.** (1) *"Prediksi stok menipis sebelum kehabisan"* — `BadgeHelperService` bekerja dengan **ambang tetap**, bukan ramalan: stok ≤ 5, stok habis, tak terjual 30 hari, dan lewat `expiry_date`. Ditulis jadi "Peringatan stok kritis, habis, dan dead stock". (2) *"barcode"* — `product_variants` punya kolom `sku` dan tidak ada pemindai di mana pun; pencarian POS hanya mencocokkan nama produk & varian. Ditulis jadi "SKU". Bagian yang dibuat untuk berhenti mengarang nyaris lahir membawa karangan barunya sendiri — dan itu justru bukti kenapa daftar kemampuan lebih berbahaya daripada kutipan: ia terbaca sebagai fakta yang bisa diperiksa.
- **Ketiga berkas avatar dihapus dari `public/`, bukan ditinggalkan yatim.** `avatar_andi/santi/budi.webp` tidak dipakai di tempat lain mana pun. Meninggalkannya berarti tiga wajah orang asing tetap dilayani di URL yang bisa ditebak, dari halaman yang sudah berhenti mengklaim mereka pelanggan.
- **Test lamanya diganti, bukan dihapus.** `LandingClaimsTest` dulu menjaga **bobot** avatarnya (≤ 15 KB, `[BL-032]` butir 3); penjaga itu kehilangan objeknya begitu berkasnya tidak ada. Penggantinya menjaga dua hal yang sekarang benar-benar penting: nama usaha karangannya, kata "Testimoni", dan `avatar_` tidak muncul lagi di HTML — dan ketiga berkasnya tidak ada di `public/`. Menghapus test itu saja akan membuat testimoni bisa kembali tanpa ada yang menagih.
- **Berkas:** `resources/views/public/landing.blade.php` — bagian testimoni (52 baris) diganti bagian persona · `public/avatar_andi.webp`, `public/avatar_santi.webp`, `public/avatar_budi.webp` (dihapus) · `tests/Feature/Public/LandingClaimsTest.php` — satu test bobot avatar diganti dua test (6 tes, 34 asersi, lulus)

---

### [ADDITION] Tagihan Terbuka Punya Umur, dan yang Lewat Jadi Kas Negatif yang Hanya Owner Bisa Bereskan (BL-031)
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — pelaksanaan keputusan pemilik 2026-08-19 yang tercatat di `[DECISION] Tagihan Terbuka Hidup Satu Hari, Lalu Jadi Kas Negatif yang Hanya Owner Bisa Bereskan (BL-031)`. Menutup `[BL-031]` seluruhnya
- **Dampak:** Schema | Model | Service | Console | Controller | Routes | Frontend | Test
- **Breaking Change:** Ya, tapi tidak untuk data yang ada. Status transaksi bertambah satu nilai (`unsettled`), dan sebuah tagihan terbuka yang dulu hidup selamanya kini berhenti bisa dilunasi kasir setelah 24 jam. Tidak ada migrasi data: per 2026-07-31 database tidak punya satu pun transaksi `pending`, dan jendela itu masih terbuka saat entri ini mendarat.
- **Deskripsi:** Tagihan terbuka POS kini berumur **24 jam** dihitung dari tanggal efektif penjualannya. Lewat itu `open-bills:expire` — terjadwal tiap jam — memindahkannya ke status `unsettled`, yaitu **kas negatif**: berhenti jadi tagihan hidup, tapi stoknya TIDAK dipulihkan. Yang boleh membereskannya hanya pemilik, dan hanya dari dashboard transaksi pemilik, lewat dua jalan: mencatat pelunasan terlambat, atau menghapuskan tagihannya — dan hanya di jalan kedua stok kembali.
- **Alasan:** Sebelum ini tidak ada satu pun tugas terjadwal yang menyentuh transaksi `pending`. Tagihan yang ditinggalkan hidup selamanya sambil menyandera stok yang sudah berkurang sejak tagihan dibuat, tanpa apa pun yang menagihnya kembali. Itu bukan keputusan melainkan bawaan yang tak pernah dipilih; pemilik memilihnya 2026-08-19.

- **`unsettled` dijadikan STATUS, bukan penanda di samping `pending`, dan itu yang membuat sisanya aman.** Aplikasi ini sudah menyebar penjaga `!== STATUS_PENDING` di banyak tempat — `payOpenBill`, API mobile, webhook Xendit. Status keempat membuat semuanya **otomatis menolak** tagihan yang lewat umur; penanda terpisah akan membuat semuanya diam-diam tetap menerima, dan tiap tempat harus diingat satu per satu. Yang tetap ditulis eksplisit hanya satu: `payOpenBill()` menolak lebih dulu dengan kalimatnya sendiri, karena pesan "sudah dibayar" akan menyuruh kasir mencari uang yang tidak pernah masuk alih-alih memanggil pemilik.
- **Stok TIDAK dipulihkan di jalur 24 jam, dan itu koreksi terhadap usulan awal entri backlognya.** Usulan lama berbunyi "pemulihan stok wajib ikut pada jalur pembatalan mana pun" — ditulis dengan asumsi jalurnya pembatalan. Kas negatif bukan pembatalan: barangnya sudah keluar dan dibawa pelanggan, jadi memulihkan stok akan membuat pembukuan bersih tapi bohong. Stok baru kembali di `writeOff()`, yaitu ketika pemilik menyatakan tagihan itu memang tak akan pernah dibayar — dan di sana pula kas negatifnya ditutup.
- **24 jam dihitung dari `occurred_at`, bukan `created_at`, dan itu ditulis di kodenya, bukan disimpulkan.** Penjualan offline ber-`created_at` waktu sinkronisasi, jadi `created_at` akan memberi tagihan kemarin umur yang baru lahir hari ini — cacat yang sudah dibuktikan `[BL-028]` cacat #3 pada rekonsiliasi kas. `Transaction::openBillCutoff()` jadi satu-satunya tempat angka 24 hidup, dan scope `liveOpenBills()`/`expiredOpenBills()` membacanya bersama.
- **Sapuannya TIAP JAM, dan itu yang menentukan seberapa benar batas 24 jamnya.** Sapuan harian berarti sebuah tagihan bisa hidup sampai 48 jam hanya karena ia lahir tepat sesudah sapuan lewat, dan kalimat "berlaku 24 jam" di layar kasir jadi bohong. Celah sisa di antara dua sapuan ditutup dari sisi lain: `HandleInertiaRequests::openBillsFor()` menyaring umur juga, jadi tagihan yang menurut aturan sudah mati tidak pernah sempat tampil — apalagi ditekan "Bayar".
- **Sapuannya wajib melepaskan tagihan dari papan dapur, dan itu bukan kerapian.** Tanpa `fulfillment_status = null`, timbunan `waiting` cuma berpindah sumber — persis timbunan yang pernah dibersihkan migrasi `backfill_stale_fulfillment_status`. Papan memang sudah menyaring tanggal efektif hari ini; dua lapis dipakai karena satu lapis akan bocor lewat jalur yang belum ada hari ini, alasan yang sama yang sudah tertulis di `void()`.
- **Self-order `pending` sengaja TIDAK ikut tersapu.** Stoknya belum dikurangi dan ia punya jalur kedaluwarsanya sendiri (`voidExpiredSelfOrder`). Menyapunya lewat sini akan mencatat kas negatif atas barang yang tidak pernah keluar. Penyaring `source = pos` ada di scope-nya, bukan di pemanggilnya.
- **Kas negatif muncul di rekonsiliasi kas tapi sengaja TIDAK masuk `expected_amount`.** Rumus itu menjawab satu pertanyaan saja — berapa uang fisik yang seharusnya ada di laci — dan uang tagihan ini tidak pernah masuk laci mana pun. Menambahkannya akan membuat kasir tampak kurang sebesar tagihan yang bukan ia pegang, persis jenis angka salah yang `[BL-028]` baru saja perbaiki. Ia jadi barisnya sendiri di pratinjau tutup kas dan di rekap sesi. Penyaringnya `unsettled_at`, bukan tanggal penjualannya: tagihannya memang lahir di shift lain, dan yang jatuh ke sesi ini adalah SAAT ia berhenti bisa ditagih. Batas yang disadari: bila kasir itu tidak sedang membuka laci saat sapuan berjalan, kas negatifnya tidak muncul di sesi mana pun — ia tetap terlihat penuh di dashboard pemilik.
- **Uangnya tetap milik laci yang MELUNASI (`[BL-028]`), dan sesudah 24 jam tidak ada laci yang menerimanya.** Pelunasan terlambat oleh pemilik memang tidak jatuh ke laci mana pun, dan itu bukan kelalaian: tanggal efektif penjualannya sudah di luar jendela sesi kas mana pun, jadi rekonsiliasi tidak akan memungutnya. Yang tertutup di sana adalah kas negatifnya.
- **Rute pembereskan digerbang `role:owner`, bukan izin modul laporan yang menampilkan halamannya.** MELIHAT kas negatif adalah soal laporan; MEMBERESKANNYA soal pertanggungjawaban. Staf yang diberi akses laporan tidak dengan sendirinya boleh menutup selisih.
- **Kalimat batasnya muncul di tiga tempat, semuanya sebelum kasir dirugikan olehnya:** pesan sukses saat "Tunda Bayar" ditekan, kepala panel tagihan di topbar, dan sisa umur per tagihan yang dihitung di layar dari `expires_at` — bukan kalimat jadi dari server, karena tab kasir sering dibiarkan terbuka berjam-jam dan kalimat yang dibekukan saat halaman dimuat akan menjanjikan sisa waktu yang sudah lewat.
- **Berkas:** `database/migrations/2026_08_20_090000_add_unsettled_status_to_transactions.php` (baru) · `app/Models/Transaction.php` · `app/Services/OpenBillExpiryService.php` (baru) · `app/Console/Commands/ExpireOpenBills.php` (baru) · `routes/console.php` · `app/Services/TransactionService.php` · `app/Services/CashDrawerReconciliation.php` · `app/Http/Middleware/HandleInertiaRequests.php` · `app/Http/Controllers/Owner/UnsettledBillController.php` (baru) · `app/Http/Controllers/Cashier/POSController.php` · `app/Http/Controllers/Cashier/CashDrawerController.php` · `routes/web.php` · `resources/js/Components/CashierTopbar.vue` · `resources/js/Pages/Cashier/POS.vue` · `resources/js/Pages/Cashier/CashDrawer.vue` · `resources/js/Pages/Cashier/CashDrawerSummary.vue` · `resources/js/Pages/Owner/Transactions/Index.vue` · `resources/js/Pages/Owner/Transactions/Detail.vue` · `tests/Feature/OpenBillExpiryTest.php` (baru, 13 tes)

---

### [FIX] Tagihan Tenant Adaptif Menunggu Omzetnya, dan Berhenti Menebak Bulan (BL-080)
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — `[BL-080]` butir (b) opsi (i) dan butir (c). Menutup entri itu seluruhnya; butir (a) sudah mendarat lebih dulu hari yang sama
- **Dampak:** Service | Console | Config | Test
- **Breaking Change:** Tidak untuk tenant jalur Harga Tetap — tak satu pun perilakunya berubah. Untuk tenant jalur Adaptif berjangkar tanggal 1–7: tagihannya kini terbit belakangan, dengan masa siap 0–6 hari alih-alih 7. Itu yang ditukar, dan disengaja.
- **Deskripsi:** Penerbit tagihan tidak lagi menagih tenant Harga Adaptif sebelum ringkasan omzet bulan penentu tarifnya ada. Selama ringkasannya belum tiba, tagihannya **ditunda** — dan karena `advance-lifecycle` berjalan harian, ia terbit sendiri di hari ringkasan itu ditulis. Bersamaan dengan itu, kedua resolver dimensi berhenti memungut "ringkasan terbaru sampai `asOf`" dan mulai meminta **bulan yang tepat**.
- **Alasan:** Butir (a) memperbaiki urutan jadwal dan itu menyembuhkan jangkar tanggal 8, tapi jangkar 1–7 tidak tersentuh olehnya: tagihannya terbit H-7, jadi di akhir bulan sebelumnya, sebelum bulan itu tutup — jam berapa pun penghitung omzet dijalankan. Pemilik memilih opsi (i) dari delapan pilihan yang dipertimbangkan, dan menolak seluruh keluarga yang menggeser tanggal tagih dari tanggal daftar.

- **KONSEPNYA, karena ia yang menjelaskan kenapa perbaikan jadwal saja tidak cukup: dua penanggalan yang tidak pernah disuruh sepakat.** Siklus tagih **berputar per tenant** — `billingAnchorDay()` diturunkan dari tanggal daftar, periodenya berjalan dari tanggal D bulan M ke tanggal D bulan M+1, dan tagihannya terbit H-7 dari ujung itu. Ringkasan omzet **serentak dan global** — `tenant_monthly_metrics.period` berformat `YYYY-MM`, ditulis sekali sebulan atas bulan yang baru tutup, dan dipangkas retensinya dalam satuan bulan. Aturan `[BL-056]` ditulis dalam bahasa KALENDER ("omzet bulan sebelumnya") tapi dieksekusi pada momen yang ditentukan jam TENANT. Untuk jangkar tanggal 9+ keduanya kebetulan berimpit; untuk 1–7 tidak, dan tidak akan pernah, berapa pun jadwalnya digeser. Menamai ini yang mengubah daftar pilihan `[BL-080]` dari tiga jadi delapan — tiga yang semula tertulis ternyata satu keluarga, yaitu "biarkan dua penanggalan itu, geser sambungannya".
- **SISTEMNYA, dalam satu kalimat: tagihan menunggu datanya, dan tidak pernah menebak.** Selama ringkasan bulan penentu tarif belum ada, tagihan tenant Adaptif tidak diterbitkan; `advance-lifecycle` berjalan harian, jadi ia terbit sendiri di hari ringkasan itu ditulis. Yang ditukar dinyatakan terang-terangan, bukan disembunyikan: masa siap tenant berjangkar 1–7 menjadi 0–6 hari, bukan 7 — jangkar tanggal 1 menerima tagihannya di pagi hari yang sama ia jatuh tempo. `invoice_lead_days` karena itu batas TERCEPAT, bukan janji, dan docblock-nya kini menyebutkan itu.
- **Penjaganya berdiri SEBELUM penetapan harga, dan itu seluruh alasan `MetricReadiness` ada sebagai kelas tersendiri.** Ini yang paling mudah salah dikerjakan: `resolveFor()` tidak pernah kehabisan jawaban. Tanpa aturan yang cocok ia menjatuhkan tenant ke **paket penampung**, dan angka itu berupa harga yang sah. Jadi sekadar membuat resolvernya tegas — butir (c) sendirian — justru akan menagih tenant subsidi dengan tarif penampung, lazimnya paket termahal, gara-gara sebuah baris yang baru ditulis besok pagi. "Sudah bisa dihitung belum" harus ditanyakan lebih dulu, bukan disimpulkan dari hasilnya.
- **Bulan yang diminta kini TEGAS, dan itu memperbaiki dua hal sekaligus.** `MonthlyMetricResolver::requiredPeriodFor()` menetapkan satu-satunya definisi "bulan sebelum periode yang ditagih", dan `metricFor()` meminta persis bulan itu. Yang pertama: ringkasan yang belum ada menjawab `null`, bukan angka bulan lain. Yang kedua, dan tidak diminta backlognya — **menghitung ulang tagihan lama berhenti berbohong**: dengan aturan lama, tagihan Maret yang dihitung ulang di bulan Juli memungut ringkasan MARET, yang saat tagihannya terbit belum ada. Sekarang ia mengembalikan Februari, yaitu angka yang benar-benar dipakai saat itu.
- **Dua dimensi, satu jalan.** Omzet dan cacah transaksi membaca baris ringkasan yang sama dan dulu menyalin query yang sama. Salinan itu berbahaya persis di sini: perbaikan yang hanya diterapkan pada satu sisi meninggalkan dimensi lain memungut bulan berbeda, dan aturan harga yang menyebut keduanya jadi punya syarat yang tak pernah bisa dipenuhi bersamaan. Keduanya kini turun dari `MonthlyMetricResolver`.
- **Penundaannya BERSYARAT.** Hanya jalur Adaptif. Jalur Harga Tetap tidak pernah punya baris ringkasan sama sekali — gerbang privasi di `ComputeTenantMonthlyRevenue` memastikan datanya memang tidak ada, bukan disembunyikan — jadi menundanya berarti mencabut masa siap tenant tanpa menukar apa pun.
- **Penundaannya punya BATAS, dan batas itu bukan kehati-hatian teoretis.** Tanpa batas, "tunda sampai ringkasannya ada" berubah diam-diam jadi "tidak pernah ditagih". Jendelanya nyata dan tertulis di dokumen persetujuan subsidi itu sendiri: mencabut consent **menghapus seluruh ringkasan omzet seketika**, sementara jalur harganya baru kembali normal di akhir periode. Di sela itu tenant masih Adaptif dan ringkasannya sudah tidak ada lagi. Lewat hari jatuh tempo, penundaan berhenti dan keadaannya dinaikkan.
- **Yang dinaikkan itu TIDAK menerbitkan tagihan, dan itu pilihan.** Menerbitkan dari paket penampung berarti menagih tenant subsidi dengan tarif termahal karena sebuah cron gagal — persis arah kesalahan yang paling merugikan tenant, dan yang paling akan diadukan. Tagihan yang tertahan menunda uang; tagihan yang salah mengambilnya. Jadi tagihannya ditahan, dicatat sebagai `invoices.postponement-overdue`, dan **satu surel dikirim** lewat `PlatformAlertService` yang sudah ada. Jejak audit menjawab "apa yang terjadi" bagi yang sempat membukanya; keadaan ini butuh seseorang yang belum tahu harus membuka apa pun.
- **Yang wajar dan yang tidak punya baris keluaran yang berbeda.** Tertunda beberapa hari adalah keadaan NORMAL dan berulang tiap bulan bagi tenant berjangkar awal bulan — dilaporkan sebagai baris biasa, dan sengaja tidak masuk jejak audit, karena jejak yang terisi hal yang sama tiap hari menenggelamkan kejadian yang benar-benar perlu terlihat. Yang lewat jatuh tempo punya barisnya sendiri sebagai peringatan.
- **`invoice_lead_days` kini menyebut dirinya batas TERCEPAT, bukan janji.** Config yang menyatakan "tagihan terbit H-7" tanpa pengecualian adalah config yang berbohong sejak entri ini. Docblock-nya menyebutkan siapa yang terkena dan berapa masa siap yang sebenarnya mereka dapat.
- **Empat tes lama diperbaiki FIXTURE-nya, bukan asersinya.** Tiga di `PricingDimensionTest` dan dua di `AutoInvoiceTest` memasang ringkasan omzet untuk bulan yang SAMA dengan periode yang ditagih, lalu lolos karena resolver lama memungut ringkasan terbaru mana pun. Bulan itu tidak pernah benar menurut `[BL-056]`; yang menutupinya adalah cacat yang entri ini cabut. Dua tes Adaptif di `AutoInvoiceTest` juga tidak punya ringkasan sama sekali — tanpa barisnya, yang teruji jadi penundaannya, bukan bracket dan harga seat yang jadi maksud tesnya.
- **Yang sengaja TIDAK dikerjakan:** memindahkan omzet penentu tarif ke jendela 30 hari yang selalu penuh (opsi (iv-b)). Ia mengembalikan masa siap 7 hari untuk semua orang, tapi ongkosnya bukan di kode: dokumen persetujuan yang sudah ditandatangani tenant menyatakan pengumpulannya "sekali sebulan, setelah bulan berjalan tutup", jadi ia menuntut versi consent baru dan persetujuan ulang setiap tenant Adaptif. Dicatat sebagai `[BL-081]` beserta dua syarat kapan ia layak dibuka lagi.
- **Berkas:** `app/Services/Pricing/MonthlyMetricResolver.php` (baru) · `app/Services/Pricing/MetricReadiness.php` (baru) · `app/Services/Pricing/MonthlyRevenueResolver.php` · `app/Services/Pricing/TransactionCountResolver.php` · `app/Services/SubscriptionService.php` · `app/Console/Commands/AdvanceSubscriptionLifecycle.php` · `config/subscription.php` · `tests/Feature/Subscription/InvoicePostponementTest.php` (baru, 8 tes) · `tests/Feature/Subscription/AutoInvoiceTest.php` · `tests/Feature/Platform/PricingDimensionTest.php`

---

### [FIX] Penghitung Omzet Dipindah ke Sebelum Penerbit Tagihan (BL-080 butir a)
- **Tanggal:** 2026-08-20
- **Fase Terkait:** Di Luar Fase — `[BL-080]` butir (a). Butir (b) dan (c) sengaja TIDAK dikerjakan di sini
- **Dampak:** Jadwal | Test
- **Breaking Change:** Tidak. Tidak ada tabel, kolom, atau perilaku aplikasi yang berubah — hanya jam sebuah perintah terjadwal.
- **Deskripsi:** `subscriptions:compute-revenue` bergeser dari tanggal 1 pukul **04:00** ke pukul **02:40**, sehingga ia berjalan sebelum `subscriptions:advance-lifecycle` (harian 03:30) alih-alih tiga puluh menit sesudahnya. Penerbit tagihan tinggal di dalam `advanceLifecycle()`, jadi sebelum ini setiap tanggal 1 tagihan terbit sebelum ringkasan omzet bulan yang baru tutup pernah ditulis.
- **Alasan:** Bukan error yang berbunyi, melainkan angka salah yang diam. `MonthlyRevenueResolver::metricFor()` mengambil ringkasan **terbaru** dengan `period <= asOf`, bukan ringkasan bulan tertentu — jadi ringkasan yang belum ada tidak menghasilkan kegagalan, ia menghasilkan angka bulan sebelumnya lagi. Tenant jalur Adaptif berjangkar tanggal 8 tertagih dari omzet dua bulan lalu, dan pada tangga 90/75/50/25% selisih satu bracket adalah Rp 25.000/bulan.

- **Yang DIGESER adalah penghitungnya, bukan penerbitnya** — dan itu pilihan, bukan kebetulan. `advance-lifecycle` sengaja berjalan sebelum jam buka warung supaya tenant yang jatuh ke masa tenggang mengetahuinya di awal hari, bukan di tengah antrean pembeli. Alasan itu masih berlaku dan tidak boleh dibuang untuk memperbaiki hal lain.
- **Ongkosnya nyata dan ditulis di tempatnya:** 04:00 dulu dipilih agar jaraknya cukup jauh dari tengah malam, termasuk untuk sinkronisasi offline larut malam. Jarak itu kini menyusut dari empat jam ke 2 jam 40 menit. Yang lebih larut dari itu terlewat, dan `--period` memang ada persis untuk menghitung ulang bulannya — tapi tagihan yang sudah terbit tetap butuh tinjauan manual.
- **Ketergantungan antar-jadwalnya kini TERTULIS, bukan tersirat dari urutan baris.** Penyebab cacat ini sejak awal adalah dua angka yang ditetapkan pada waktu berbeda untuk alasan berbeda, tanpa satu pun tempat yang menyatakan hubungannya. `config/subscription.php` sudah lama melakukan hal yang benar untuk `trial_choice_lead_days` ("wajib lebih besar dari `invoice_lead_days`", lengkap dengan alasannya); komentar di kedua sisi `routes/console.php` kini melakukan hal yang sama. Urutan yang benar tanpa alasan tertulis adalah urutan yang akan digeser lagi oleh orang berikutnya.
- **Dijaga tes, bukan hanya komentar.** `tests/Feature/Subscription/ScheduleOrderTest.php` membaca ekspresi cron yang benar-benar terdaftar dan menuntut empat hal: penghitung sebelum penerbit, penghitung tetap ≥ 02:00 dari tengah malam, pemangkas ringkasan sesudah penghitungnya, dan keduanya benar-benar bertemu di tanggal 1 (yang satu bulanan, yang lain harian — urutan jam saja tidak cukup bila mereka tak pernah menyala di hari yang sama). Pemeriksaan: mengembalikan 02:40 ke 04:00 membuat tes pertama gagal.
- **Yang TIDAK diselesaikan entri ini, dan itu penting.** Butir (a) tidak menyembuhkan tenant berjangkar **tanggal 1–7**: tagihan mereka terbit H-7, jadi tetap sebelum bulan sebelumnya tutup, jam berapa pun penghitungnya berjalan. Sisa itu menunggu keputusan pemilik di `[BL-080]` butir (b), dan butir (c) — membuat `MonthlyRevenueResolver` tegas soal bulan yang diminta — memang harus menunggu (b), karena tanpa (b) ia hanya mengubah tagihan yang salah jadi tagihan yang tidak terbit sama sekali.
- **Berkas:** `routes/console.php` · `tests/Feature/Subscription/ScheduleOrderTest.php` (baru, 4 tes)

---

### [ADDITION] Diskon Jadi Entitas Tersendiri, dengan Lantai Untung yang Hanya Manusia Boleh Tembus (BL-018)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — `[BL-018]`, poin 1–7 seluruhnya
- **Dampak:** Migrasi | Model | Service | Controller | Request | Route | Config | Frontend | Test
- **Breaking Change:** Tidak untuk pengguna — tanpa aturan diskon, setiap harga tetap harga katalog. Untuk pengembang: satu perubahan LAPISAN, dijelaskan di bawah.
- **Deskripsi:** Diskon kini punya tabelnya sendiri (`discount_rules`), lantai untung yang dihitung dari `cost_price` dan margin milik owner, pendalaman potongan seiring tanggal kedaluwarsa mendekat, dan jejak lengkap pada tiap baris penjualan. Owner — dan hanya owner — bisa menembus lantai itu dari kasir, dengan alasan tertulis yang ikut tercatat.
- **Alasan:** Penghalangnya tidak pernah rumus harga; `cost_price` dan `price` sudah berdampingan sejak awal. Yang belum ada adalah **tempat sah untuk menaruh harga diskonnya**. Menurunkan `price` varian sebagai "cara mendiskon" membuat potongan itu tak bisa dibedakan dari perubahan harga permanen, dan laporan tidak akan pernah bisa menjawab "berapa yang kita korbankan untuk menghabiskan stok bulan ini".

- **Aturan diskon ADALAH langkah persetujuannya.** Backlognya (poin 6) meminta fitur ini dimulai dari "sarankan lalu owner menyetujui, bukan otomatis", karena harga yang turun sendiri secara keliru adalah uang yang keluar dan sukar ditarik kembali. Sistem tidak pernah menurunkan harga atas inisiatifnya sendiri — ia hanya memberlakukan baris yang owner tuliskan di halaman **Aturan Diskon**, lengkap dengan alasan dan masa berlakunya.
- **Rumus berhenti di lantai; MANUSIA boleh melanjutkan.** `DiscountService::priceFor()` menjepit hasilnya di `floorFor()` tanpa kecuali, dan ada tesnya: aturan 60% pada barang bermodal tinggi berhenti tepat di lantai. Satu-satunya jalan ke bawah adalah `override_unit_price` dari owner — selalu tindakan manusia yang disengaja, tidak pernah hasil rumus (keputusan pemilik 2026-07-29).
- **Wewenangnya ditegakkan DI SERVER, bukan dengan menyembunyikan tombolnya.** Kasir yang mengirim `override_unit_price` sendiri ditolak `TransactionService`, bukan cuma tidak melihat tombolnya. Dipilih tegas begini karena menaikkan izin belakangan jauh lebih mudah daripada menariknya kembali dari kasir yang sudah terbiasa.
- **Alasan wajib, dan ia MENEMPEL pada barisnya.** Bukan di log terpisah yang bisa dipangkas retensi — alasan yang tidak ikut baris penjualannya adalah alasan yang hilang persis saat laporan dibuka berbulan-bulan kemudian. Untuk potongan beraturan, alasan aturannya **disalin** ke tiap baris: aturannya bisa disunting atau dihapus, dan laporan bulan lalu harus tetap menjelaskan dirinya sendiri.
- **`cost_price_at_sale` dan `margin_floor_at_sale` dibekukan bersama barisnya.** Ini menutup keterbatasan yang sudah lama tercatat di `ProfitService` dan yang jadi jauh lebih tajam di sini: begitu `cost_price` jadi dasar klaim "diskon ini tetap untung", perubahan harga modal di kemudian hari akan **mengubah klaim atas penjualan yang sudah lewat**. Tanpa lantainya ikut dibekukan, "seberapa dalam tembusnya" juga tak bisa dihitung ulang.
- **Pembulatan KE ATAS, kelipatan 500.** Rp 18.750 jadi Rp 19.000. Pembulatan ke bawah bisa menembus lantai yang baru saja susah payah dihitung — pembulatan yang salah arah membuat seluruh penjaganya sia-sia. 500 dipilih karena itu pecahan terkecil yang benar-benar beredar di laci; membulatkan ke rupiah terdekat menghasilkan harga yang tidak bisa dibayar tunai.
- **Barang yang SUDAH kedaluwarsa tidak pernah didiskon.** Diskon hanya untuk yang MENDEKATI kedaluwarsa. Batas keamanan pangan, bukan pilihan bisnis, jadi ia dijaga di `DiscountService` — bukan diserahkan pada kedisiplinan kasir, dan bukan pula pada owner yang menulis aturannya.
- **EMPAT jalur harga diperbarui bersama, dan ketiganya yang disebut backlog memang mudah terlewat:**
  1. **Checkout** — `processItems()` memanggil `resolveItemPrice()`, yang mengembalikan harga beserta seluruh kolom jejaknya.
  2. **Pengeditan transaksi** — `TransactionEditService` menghapus lalu membangun ulang baris item dari kiriman client, yang hanya berisi varian dan qty. Tanpa penyelamatan harga, transaksi yang terjual berdiskon lalu diedit karena alasan lain akan **diam-diam naik kembali ke harga katalog**. Persetujuan penembusan lantai ikut diselamatkan — menyimpan harganya tapi membuang persetujuannya membuat baris itu terlihat seperti diskon biasa di laporan.
  3. **Sinkronisasi offline** — `amountsMatch()` dulu hanya mengenal harga katalog, jadi setiap penjualan berdiskon offline akan membanjiri `needs_review` dan sinyal anomali jadi berisik lalu berhenti dipercaya. Sekarang harga katalog **dan** harga berdiskon sama-sama sah; apa pun di luar keduanya tetap ditandai.
  4. **Katalog offline / props POS** — `effective_price` ikut dikirim bersama katalognya. Ini bukan kenyamanan: server menghitung ulang harganya sendiri saat checkout, jadi layar yang menampilkan harga katalog akan menyebut satu angka lalu menagih angka lain, dan kasir tidak punya cara menjelaskan selisihnya.
- **PERUBAHAN LAPISAN yang perlu diketahui pengembang.** `StoreTransactionRequest` dulu menjumlahkan total belanja dari `items.*.unit_price` — angka kiriman KLIEN. Itu salah dalam dua arah: ia tidak menjaga apa pun (klien yang mengirim harga kecil ikut menurunkan ambang yang harus ia bayar), dan sejak entri ini ia menolak yang sah (penjualan berdiskon yang dibayar pas terbaca sebagai kurang bayar). Sekarang totalnya dihitung dari harga SERVER. Akibatnya satu test lama berpindah lapisan: payload berharga basi kini gugur sebagai galat validasi `payments`, bukan sebagai galat flash dari service. Yang diuji tetap sama, dan penolakannya kini terjadi sebelum satu baris pun ditulis.
- **Laporan memisahkan yang DIKORBANKAN dari yang sekadar didiskon.** Dua angka: total potongan, dan bagian yang dijual di bawah lantai — berikut barisnya, alasannya, dan siapa yang menyetujui. Tanpa pemisahan itu, sebuah penjualan rugi terlihat persis seperti diskon 5% yang sehat, dan itu justru angka yang paling ingin dilihat owner.
- **Kasir diberi tahu batasnya, bukan dibiarkan menemui jalan buntu.** Keranjang kasir memuat satu kalimat: diskon yang sudah disetujui berlaku otomatis, harga di bawah batas untung hanya bisa ditetapkan pemilik. Tombol kelabu tanpa penjelasan adalah jalan buntu, bukan validasi — pelajaran yang sudah dibayar di `[BL-021]`.
- **Ambang untung milik owner, bukan konstanta**, dan default-nya 10% dengan sengaja rendah: ia LANTAI, bukan target. Lantai yang ketinggalan tinggi membuat fitur diskonnya nyaris tidak pernah bisa menawarkan apa pun, dan owner akan menyimpulkan ia rusak. Halaman aturannya juga memajang pratinjau harga berikut jepitan lantainya **sebelum** aturan disimpan, dengan alasan yang sama.
- **Yang sengaja TIDAK dikerjakan: bundling berdiskon.** `[BL-017]` mencatat entri ini sebagai satu-satunya penghalangnya, dan penghalang itu kini hilang — `PressedStockStrategy` sekarang punya tempat sah untuk harga di bawah katalog. Menyambungkannya adalah pekerjaan tersendiri, dan lantai margin **wajib** ikut ditegakkan di sisi saran saat itu dikerjakan, bukan hanya di sisi harga.
- **Berkas:** `database/migrations/2026_08_19_112853_create_discount_rules_table.php` (baru) · `..._112854_add_discount_columns_to_transaction_items_table.php` (baru) · `..._112855_add_min_margin_percent_to_tenants_table.php` (baru) · `app/Services/DiscountService.php` (baru) · `app/Models/DiscountRule.php` (baru) · `database/factories/DiscountRuleFactory.php` (baru) · `app/Http/Controllers/Owner/DiscountRuleController.php` (baru) · `app/Http/Requests/StoreDiscountRuleRequest.php` (baru) · `resources/js/Pages/Owner/DiscountRules/Index.vue` (baru) · `app/Services/TransactionService.php` · `app/Services/TransactionEditService.php` · `app/Http/Requests/StoreTransactionRequest.php` · `app/Http/Controllers/Cashier/POSController.php` · `app/Http/Controllers/Owner/ReportController.php` · `app/Http/Controllers/Owner/Settings/SystemBehaviorController.php` · `app/Models/Tenant.php` · `app/Models/TransactionItem.php` · `routes/web.php` · `resources/js/Pages/Cashier/POS.vue` · `resources/js/Components/ModifierModal.vue` · `resources/js/Pages/Owner/Reports/Daily.vue` · `resources/js/Pages/Owner/Settings/Operations.vue` · `resources/js/Layouts/OwnerLayout.vue` · `tests/Feature/DynamicDiscountTest.php` (baru, 26 tes) · `tests/Feature/Cashier/POSTest.php` (satu assertion berpindah lapisan)

---

### [ADDITION] Akun Platform Punya Faktor Kedua, dan Kata Sandi yang Benar Tidak Lagi Berarti Masuk (BL-013)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — `[BL-013]`, sisa terakhir `[BL-010]` yang sejak awal ditandai "catat sebagai target"
- **Dampak:** Migrasi | Model | Service | Controller | Route | Middleware | Frontend | Test
- **Breaking Change:** Tidak. Faktor kedua **opsional** dan mati bawaannya; akun yang tidak mendaftarkannya masuk persis seperti sebelumnya.
- **Deskripsi:** Akun platform bisa mendaftarkan aplikasi authenticator (TOTP, RFC 6238) lewat halaman **Keamanan Akun**, lengkap dengan delapan kode pemulihan sekali-pakai. Sejak itu login jadi dua langkah, dan pengaktifan/pencabutannya tercatat sebagai kejadian `sensitive`.
- **Alasan:** Satu akun di panel ini memegang data administratif seluruh klien. Lapisannya sudah lebih baik daripada saat dicatat pertama kali — ada throttle (`[BL-007]`), jejak audit yang bisa dibaca (`[BL-009]`), pemulihan kata sandi yang tidak membocorkan keberadaan akun (`[BL-010]`) — tapi tetap: siapa pun yang memegang kata sandinya langsung masuk.

- **Kata sandi yang benar MELEPAS sesinya kembali, bukan mempertahankannya sambil "meminta" kode.** `Auth::guard('platform')->attempt()` berhasil, lalu `logout()` dipanggil segera dan yang tersimpan di sesi hanya **id** yang menunggu. Alternatif yang jauh lebih sering ditulis — biarkan sesi terautentikasi lalu pasang middleware yang mengalihkan ke layar kode — menghasilkan gerbang yang bisa dilewati dengan menutup halamannya, dan gerbang seperti itu adalah teater. Ada tesnya: `expect(auth('platform')->check())->toBeFalse()` tepat setelah kata sandi benar.
- **TOTP, bukan OTP surel — dan itu keputusan keamanan, bukan selera.** Surel adalah jalur pemulihan kata sandi akun ini. Faktor kedua yang dikirim ke sana berarti kotak masuk yang jebol menjebol keduanya sekaligus, dan dua faktor itu sebenarnya satu.
- **Ditulis sendiri, tanpa menambah dependensi.** Menambah paket butuh persetujuan lebih dulu di proyek ini, sedangkan seluruh algoritmanya muat dalam satu kelas: HMAC-SHA1 atas nomor langkah waktu, pemotongan dinamis (RFC 4226 §5.3), enam digit. Yang **tidak** ditulis sendiri adalah kriptografinya — `hash_hmac` dan `hash_equals` milik PHP yang mengerjakannya. Base32 ditulis manual karena PHP memang tidak punya fungsi bawaannya.
- **Konsekuensi dari tidak menambah dependensi: tidak ada kode QR.** Merender QR butuh pustaka, jadi halaman pendaftaran menampilkan **kuncinya** dalam potongan empat huruf untuk dimasukkan manual, plus tautan `otpauth://` yang bisa disalin. Setiap authenticator arus utama menerima pemasukan manual, dan panel ini hanya dipakai segelintir akun internal. Bila QR suatu saat diinginkan, itu penambahan dependensi tersendiri yang perlu persetujuan.
- **Pendaftarannya DUA LANGKAH, dan `two_factor_confirmed_at` adalah kolom terpisah karena itu.** Rahasianya lahir saat layar dibuka; akunnya baru terkunci setelah satu kode dari aplikasi dibuktikan. Tanpa pemisahan itu, membuka layar lalu menutup tab akan mengunci akun dengan rahasia yang tidak pernah masuk ke ponsel mana pun — pada panel yang memegang data seluruh klien, tanpa jalan keluar selain menyunting basis data.
- **Kode pemulihan wajib ada, bukan pelengkap.** Ponsel hilang tanpa kode pemulihan berarti akun terkunci permanen. Ditampilkan **sekali saja**, dan halamannya mengatakan itu terus terang — kode yang bisa dilihat lagi kapan saja adalah kode yang tidak pernah dicatat siapa pun.
- **TOTP dicoba lebih dulu, kode pemulihan hanya SETELAH ia gagal.** Urutan sebaliknya akan membakar satu kode pemulihan setiap kali seseorang salah ketik digit terakhir. Ada tesnya.
- **Rahasianya terenkripsi di tingkat model, bukan sekadar `$hidden`.** Rahasia TOTP yang terbaca dari dump basis data atau cadangan yang bocor membangkitkan kode sah **selamanya** — faktor kedua yang bisa dibaca bersama faktor pertama bukan faktor kedua. Ada tes yang membaca kolom mentahnya lewat `DB::table()` dan memastikan isinya berbeda dari rahasianya.
- **Rahasia dan kode pemulihan menyeberang lewat FLASH, bukan prop halaman.** Prop tetap berarti keduanya ikut di setiap kunjungan berikutnya ke halaman itu, termasuk lama setelah pendaftarannya selesai. Keduanya didaftarkan eksplisit di `HandleInertiaRequests` karena daftar flash di sana memang whitelist.
- **Mematikan dan menerbitkan ulang kode pemulihan menuntut kata sandi.** Sesi yang tertinggal terbuka di komputer bersama tidak boleh bisa melepas lapisan ini dengan satu klik — itu akan membuatnya bisa dicabut oleh persis orang yang ia ada untuk hadang.
- **Pencabutannya yang paling perlu terlihat di jejak audit.** `two-factor.enabled`, `two-factor.disabled`, `two-factor.recovery-codes-regenerated`, dan `two-factor.recovery-used` semuanya `sensitive`; `two-factor.failed` juga, dengan alasan yang sama seperti `login.failed`. Menyalakan lapisan keamanan adalah kabar baik; mencabutnya bisa jadi langkah pertama seseorang yang baru menguasai akun.
- **Halaman Keamanan Akun TIDAK digerbang `platform.can` mana pun, dan nav-nya tanpa `modules` maupun `ownerOnly`.** Keamanan akun sendiri bukan modul yang bisa dipegangkan atau ditahan; staf platform yang tidak dipegangi satu modul pun tetap harus bisa mengamankan akunnya. Ada tesnya.
- **Throttle layar kode kedua memakai limiter yang sama dengan login.** Kode enam angka punya sejuta kemungkinan, dan tanpa batas percobaan sejuta bukan angka besar sama sekali.
- **`email_verified_at` yang disebut entri backlognya sengaja TIDAK ikut.** Ia hal lain — verifikasi kepemilikan alamat surel, bukan faktor kedua — dan menambahkannya di sini berarti mengubah alur pembuatan akun platform sebagai efek samping entri keamanan login. Bila diinginkan, ia entri tersendiri.
- **Berkas:** `database/migrations/2026_08_19_111521_add_two_factor_to_platform_users_table.php` (baru) · `app/Services/Platform/TotpService.php` (baru) · `app/Http/Controllers/Platform/TwoFactorController.php` (baru) · `resources/js/Pages/Platform/TwoFactorChallenge.vue` (baru) · `resources/js/Pages/Platform/TwoFactorSetup.vue` (baru) · `app/Models/PlatformUser.php` · `app/Http/Controllers/Platform/AuthController.php` · `app/Http/Middleware/HandleInertiaRequests.php` · `routes/web.php` · `resources/js/Layouts/PlatformLayout.vue` · `tests/Feature/Platform/PlatformTwoFactorTest.php` (baru, 20 tes)

---

### [ADDITION] Owner Akhirnya Bisa Menargetkan Saran Jualnya Sendiri, dan Aturannya Selalu Menang Slot (BL-074)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — `[BL-074]`, pelengkap sisi manual dari `[BL-017]` (sisi otomatis, selesai 2026-07-27)
- **Dampak:** Migrasi | Model | Service | Controller | Request | Route | Config | Frontend | Test
- **Breaking Change:** Tidak untuk pengguna. Satu perubahan perilaku yang perlu diketahui: `upsell.max_per_transaction` naik dari **2 ke 3**, jadi setiap tenant kini melihat sampai tiga saran per penjualan, bukan dua.
- **Deskripsi:** Tabel `upsell_rules` dan `ManualRuleStrategy` memberi owner tempat menuliskan targetnya sendiri — "kalau beli nasi goreng, tawarkan teh manis" (berpemicu) dan "bulan ini dorong kopi susu botol" (tanpa pemicu) — lengkap dengan jendela tanggal, urutan, dan saklar per aturan. Halamannya berdiri sendiri di **Aturan Saran Jual**, owner-eksklusif.
- **Alasan:** Seluruh saran jual sampai hari ini **ditemukan mesin**: ko-okurensi modifier 30 hari, barang tertekan stok/kedaluwarsa, naik ukuran dari selisih harga. Orang yang paling tahu barangnya sendiri — dan paling tahu barang mana yang sedang perlu didorong dan kenapa — tidak punya satu pun tempat untuk mengatakannya. Yang tersedia hanya saklar tingkat `config/upsell.php`, dan itu berkas kode, bukan halaman.

- **Strategi KEEMPAT, bukan mesin kedua.** Kontrak `SuggestionStrategy` sudah ada dan `UpsellIndexBuilder` sudah merakit banyak strategi jadi satu indeks. Karena keluarannya `Suggestion` yang sama persis, **tidak ada** perubahan pada bentuk props POS, `UpsellStrip.vue`, `useUpsell.js`, maupun pencatatan event. Seluruh biaya fitur ini ada di satu kelas baru dan satu tabel baru.
- **`ManualRuleStrategy` mengisi KEDUA sisi indeks, dan itu bukan kerumitan yang dicari-cari.** Ia satu-satunya strategi yang mengimplementasikan `SuggestionStrategy` **dan** `CartLevelStrategy`, karena dua kalimat pemilik yang terdengar sama sebenarnya berbeda bentuk: yang satu butuh pemicu, yang satu berlaku pada setiap keranjang. Memaksa keduanya jadi satu bentuk berarti salah satunya jadi janggal.
- **Pemicunya VARIAN, bukan produk atau kategori** (keputusan pemilik 2026-08-19). Yang paling longgar paling mahal di sisi penyaringan client — dan penyaringan itu berjalan tiap klik, di perangkat kasir yang paling lemah, kerap sambil offline.
- **Aturan manual menang lewat LANTAI SKOR, bukan lewat urutannya di daftar strategi.** Skornya `1000 + priority`; skor mesin tertinggi hari ini 100 (`PressedStockStrategy` untuk barang yang kedaluwarsa hari ini). Client menyortir berdasarkan skor lalu memotong di `max_per_transaction`, jadi lantai inilah yang benar-benar memenangkan slotnya. Alternatifnya — mengadu skor manual dengan skor mesin — berarti saran yang owner pasang sendiri bisa tergeser diam-diam oleh angka yang tidak pernah ia lihat, dan ia akan menyimpulkan fiturnya rusak. Dari tempat ia berdiri, memang itu yang terjadi.
- **`max_per_transaction` naik 2 → 3, atas permintaan pemilik.** Begitu owner bisa memasang sarannya sendiri, dua slot berarti saran mesin nyaris selalu tergeser habis. Pemilik secara eksplisit meminta angka ini **diverifikasi langsung di layar kasir** ("mau verifikasi kalau 3 muncul itu bagaimana hasilnya") — bila strip-nya jadi terlalu ramai, angka inilah yang diturunkan lagi, bukan fiturnya yang dicabut.
- **`type: 'manual'` sebagai nilai keempat, dan itu bukan formalitas.** Laporan konversi memisahkan angka per jenis, jadi inilah satu-satunya cara owner bisa tahu apakah tebakannya sendiri mengalahkan tebakan mesin. Tanpa ini, aturan manual jadi fitur yang tidak pernah bisa dievaluasi. Tidak perlu migrasi: `upsell_events.type` sudah bertipe `string`, bukan enum.
- **Penjaga kandidat berlaku tanpa pengecualian, dan ada tiga tes yang menjaganya.** Varian kedaluwarsa, stok nol, dan produk nonaktif tetap gugur walaupun owner sendiri yang menuliskannya — `ManualRuleStrategy` menyaring lewat `SellableVariantQuery` yang sama. Owner yang menyuruh menawarkan barang habis tidak sedang meminta barang habis ditawarkan; ia hanya belum tahu stoknya nol. Karena itu tabelnya juga **menampilkan alasannya** ("Stok barangnya habis") alih-alih membiarkan barisnya terlihat aktif padahal tidak pernah muncul.
- **Owner-eksklusif, bukan modul RBAC baru.** Menggantungkannya pada permission `reports` — yang menggerbangi laporan upsell di sebelahnya — akan memberi kuasa **menulis** kepada siapa pun yang hanya diberi hak **membaca** laporan. Menambah modul RBAC baru berarti menambah permission yang harus di-seed dan dipahami owner. Rutenya masuk grup `role:owner` yang sudah ada.
- **`toggle()` terpisah dari `update()`, dengan sengaja.** Mematikan aturan adalah tindakan satu klik dari tabel; memaksanya melewati validasi formulir penuh berarti aturan yang produknya sudah terhapus **tidak bisa dimatikan sama sekali** — persis saat owner paling ingin mematikannya.
- **Scope tenant EKSPLISIT di strategi, bukan bersandar pada `TenantScope`.** Pembangunan indeks juga dipanggil dari jalur tanpa pengguna terautentikasi (self-order, dan kelak pekerjaan antrean). Pelajaran yang sama sudah dibayar di `[BL-047]` dan di SAAS Tahap A.
- **Keterbatasan yang sudah diketahui dan diterima: jendela tanggal bisa kedaluwarsa DI DALAM snapshot offline.** Indeksnya ikut ter-snapshot `useCatalogCache`, jadi perangkat yang seharian offline akan terus menawarkan promo yang berakhir semalam. Diterima karena **harganya tetap harga katalog** — yang basi hanya ajakannya, bukan uangnya. Persoalan yang sama akan jauh lebih serius di `[BL-018]`, di mana yang ikut basi adalah potongan harganya.
- **`config/upsell.php` `types.manual` adalah saklar DARURAT, bukan cara mengatur.** Mematikannya membungkam seluruh aturan manual sekaligus. Pengaturan per aturan tempatnya di halaman, per baris.
- **Berkas:** `database/migrations/2026_08_19_072049_create_upsell_rules_table.php` (baru) · `app/Models/UpsellRule.php` (baru) · `database/factories/UpsellRuleFactory.php` (baru) · `app/Services/Upsell/Strategies/ManualRuleStrategy.php` (baru) · `app/Http/Controllers/Owner/UpsellRuleController.php` (baru) · `app/Http/Requests/StoreUpsellRuleRequest.php` (baru) · `resources/js/Pages/Owner/UpsellRules/Index.vue` (baru) · `app/Services/Upsell/UpsellIndexBuilder.php` · `app/Models/UpsellEvent.php` · `config/upsell.php` · `routes/web.php` · `resources/js/Layouts/OwnerLayout.vue` · `tests/Feature/Upsell/ManualUpsellRuleTest.php` (baru, 20 tes)

---

### [DECISION] Tagihan Terbuka Hidup Satu Hari, Lalu Jadi Kas Negatif yang Hanya Owner Bisa Bereskan (BL-031)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — menutup pertanyaan `[BL-031]` yang terbuka sejak 2026-07-31. **Keputusan saja; kodenya belum ada.**
- **Dampak:** (belum) Migration | Service | Console | Controller | Frontend
- **Breaking Change:** Tidak — per hari ini basis data tidak punya satu pun transaksi `pending`, jadi tidak ada tagihan hidup yang aturannya berubah di bawah kaki penggunanya. Jendela itu tertutup begitu Tunda Bayar benar-benar dipakai.
- **Deskripsi:** Umur tagihan terbuka (Tunda Bayar) ditetapkan **per hari**. Tagihan yang belum dilunasi 24 jam setelah transaksinya tercatat berhenti menjadi tagihan hidup dan **dicatat sebagai kas negatif**. Yang boleh membereskannya **hanya owner**, dan hanya dari **dashboard transaksi owner** — bukan dari menu log transaksi kasir.
- **Alasan:** Pertanyaan pemilik sejak `[BL-023]` selesai — "apakah open bill itu hidup berdasarkan waktu hidup kas / shift kasir atau per hari atau sampai diselesaikan". Yang berjalan sampai hari ini adalah **bawaan yang tak pernah dipilih siapa pun**: tagihan hidup sampai dilunasi, selamanya, terikat `user_id` dan bukan pada hari maupun laci. Tutup kas tidak menyentuhnya; berganti hari tidak menyentuhnya.

- **Yang membuat keputusan ini bukan soal tampilan: stok sudah berkurang sejak tagihan dibuat.** `TransactionService::processItems(..., deductStock: true)` berlaku juga untuk open bill. Tagihan yang ditinggalkan menyandera stok **tanpa batas waktu**, dan sampai hari ini tidak ada apa pun yang menagihnya kembali — tidak ada kedaluwarsa, tidak ada pembersihan, tidak ada peringatan. Ini konsekuensi paling mahal dari keadaan lama, dan yang paling tidak terlihat.
- **"Jadi kas negatif" dipilih, bukan "dibatalkan" — dan itu membuat keputusan ini berbeda dari opsi "per hari" yang ditulis di entri backlognya.** Membatalkan tagihan akan memulihkan stok dan menghapus jejak uangnya: bersih di pembukuan, tapi bohong — barangnya sudah keluar dan dibawa pelanggan. Mencatatnya sebagai kas negatif menyatakan yang sebenarnya terjadi, yaitu **ada barang keluar yang belum dibayar**, dan meninggalkan angkanya di tempat yang harus dipertanggungjawabkan seseorang.
- **Karena itu stok TIDAK dipulihkan saat tagihan lewat 24 jam,** dan catatan "pemulihan stok wajib ikut" di `[BL-031]` **tidak berlaku** untuk jalur ini — catatan itu ditulis dengan asumsi jalurnya pembatalan. Ia tetap berlaku bila kelak ada jalur pembatalan sungguhan (owner memutuskan tagihannya memang tak akan pernah dibayar); di sanalah stok kembali, dan di sana pula kas negatifnya ditutup.
- **Kenapa hanya owner, dan kenapa bukan di layar kasir.** Kas negatif adalah selisih yang harus dipertanggungjawabkan; membiarkan kasir menyuntingnya berarti orang yang bertanggung jawab atas selisih itu juga yang bisa merapikannya. Pemisahan ini melanjutkan garis yang sudah ada, bukan menggambar garis baru: `POSController::canEditTransaction()` sudah menolak kasir menyunting transaksi di luar sesi lacinya yang terbuka. Yang keputusan ini tambahkan adalah **tempat** suntingan itu boleh terjadi.
- **Kombinasi yang sudah aneh hari ini, dan jadi tegas sesudah ini.** Kasir tidak bisa mengedit tagihan dari shift sebelumnya tapi tetap **melihat** dan **bisa melunasinya** — boleh menerima uangnya, tidak boleh membetulkan isinya. Sesudah 24 jam keduanya berhenti, karena tagihannya bukan lagi tagihan.
- **Uangnya tetap milik laci yang MELUNASI.** `[BL-028]` sudah menetapkan itu dan keputusan ini tidak mengubahnya: tagihan yang dibuat shift pagi lalu dilunasi shift malam menaruh uangnya di laci malam. Yang berubah hanya jendela hidupnya — sesudah 24 jam tidak ada laci mana pun yang akan menerimanya.
- **Antrian dapur ikut bersih, tapi hanya kalau tugasnya menyentuhnya juga.** Mode antrian menaruh open bill di papan dapur (`fulfillment_status = waiting`), dan tagihan yang hidup berhari-hari menumpuk di sana — persis timbunan yang pernah dibersihkan migrasi `backfill_stale_fulfillment_status`. Tugas terjadwal yang memindahkan tagihan ke kas negatif **wajib sekaligus** melepaskannya dari papan; kalau tidak, sumber timbunannya cuma berpindah tempat.
- **Asimetri dengan self-order akhirnya beralasan.** `voidExpiredSelfOrder()` sudah punya jalur kedaluwarsa sejak dulu, open bill POS tidak. Sesudah ini keduanya punya, tapi **berakhir berbeda** dengan sengaja: self-order yang tak pernah diambil tidak pernah mengeluarkan barang, jadi ia dibatalkan dan stoknya kembali. Open bill sudah mengeluarkan barangnya.
- **Yang harus ikut dibangun, dan belum ada satu pun hari ini:** tugas terjadwal di `routes/console.php` (tak ada satu pun tugas yang menyentuh transaksi `pending`) · penampung kas negatif berikut tempatnya muncul di rekonsiliasi kas · jalur sunting di dashboard transaksi owner · pembatas umur di `HandleInertiaRequests::openBillsFor()`, yang sekarang menyaring `user_id` + `status pending` **tanpa batas waktu apa pun** · dan kalimat di UI kasir yang menyebut tagihannya bertahan sampai kapan, karena orang yang menekan "Tunda Bayar" berhak tahu.
- **Satu hal yang harus diputuskan saat implementasinya dimulai:** 24 jam dihitung dari `occurred_at` atau `created_at`. Jawabannya `occurred_at` — `[BL-028]` cacat #3 sudah membuktikan `created_at` melempar penjualan offline ke hari sinkronisasinya — tapi itu perlu ditulis di kodenya, bukan disimpulkan dari entri ini.
- **Berkas:** belum ada. `docs/BACKLOG.md` `[BL-031]` diperbarui dengan keputusan ini dan tetap **Open** untuk pekerjaan kodenya.

---

### [DECISION] Kuota AI Tambahan Dijual seperti Seat — +5 Analisis/Hari Rp 15.000 per Bulan (BL-069)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — menjawab angka yang menghalangi `[BL-069]` sejak 2026-08-08. **Keputusan saja; kodenya belum ada.**
- **Dampak:** (belum) Migration | Model | Service | Controller | Frontend
- **Breaking Change:** Tidak.
- **Deskripsi:** Kuota AI tambahan dijual **mengikuti pola seat apa adanya**: komponen bulanan berulang, **Rp 15.000/bulan untuk +5 analisis per hari**, seragam antar paket. Dibeli dari halaman langganan, ikut masuk `pricing_context.billing_breakdown`, dan dilepas dengan pola yang sama (berlaku satu periode penuh ke depan, bukan seketika). Pilihan model AI **tetap di keadaan bawaan** — `gpt-4o-mini` lewat provider SumoPod — dan **tidak** ada kuota yang berbeda per model.
- **Alasan:** Yang menghalangi `[BL-069]` bukan kodenya melainkan harganya; polanya sudah terbukti di seat dan tinggal ditiru. Pemilik memilih satuan **plafon harian**, bukan kredit habis-pakai.

- **Keputusan ini menolak saran (1) di entri backlognya, dan penolakannya sadar.** `[BL-069]` menyarankan **paket kredit**, dengan dua alasan: plafon bulanan berarti menanggung paparan 30× sementara tenant biasanya menabrak batas pada satu hari sibuk saja, sehingga harganya harus dipatok untuk kasus terburuk dan semua orang kemahalan. Pemilik memilih plafon harian, dengan pertimbangan keseragaman pola pembelian — satu panel, satu cara melepas, satu komponen tagihan, persis seat — lebih berharga daripada penghematan yang didapat kredit. **Yang harus disadari:** ini memang berarti sebagian pembeli membayar Rp 15.000 untuk kapasitas yang mereka pakai beberapa hari saja.
- **Paparannya tetap aman di angka yang dipilih, dan itulah yang membuat penolakan di atas tidak berbahaya.** +5 analisis/hari × 30 hari = 150 analisis. Pada `gpt-4o-mini` (± Rp 9/analisis, dari pengukuran nyata atas 8 analisis di `ai_analyses.tokens_used`) itu **± Rp 1.350** ongkos terhadap Rp 15.000 pendapatan — margin 91% bahkan bila kuotanya dihabiskan setiap hari. Kredit akan lebih murah lagi, tapi keduanya berada di sisi aman yang sama.
- **Yang menentukan untung-rugi fitur AI tetap PILIHAN MODEL, bukan harga kuotanya.** Ini temuan terpenting `[BL-069]` dan keputusan ini tidak mengubahnya sedikit pun: rentang ongkos antar model 60× dari ujung ke ujung — Rp 2 di `qwen3.7-flash` sampai Rp 134 di `claude-sonnet-5`. Pada model kelas Sonnet, `paid-3` sudah merugi **meski tak seorang pun membeli kuota tambahan**: Rp 241.200 ongkos atas langganan Rp 200.000. Harga Rp 15.000/+5 hanya sah selama modelnya sekelas `gpt-4o-mini`. **Periksa tabel ongkos di `[BL-069]` sebelum mengganti `AI_SUMOPOD_MODEL`, bukan sesudahnya.**
- **Pilihan model sengaja tetap tunggal dan bawaan, dan tidak ada kuota per-model.** Permintaan pemilik: biarkan opsi AI di keadaan bawaan untuk sekarang. `config/ai.php` sudah menyediakan bentuknya — satu model per provider, disetel lewat env (`AI_SUMOPOD_MODEL`, dst.) — dan itu cukup. Yang **tidak** dibangun, atas pertimbangan pemilik bahwa ia berlebihan: penghitungan pemakaian yang berbeda per model. Satu satuan ("satu analisis") dipertahankan apa adanya, apa pun model yang kebetulan dipakai di belakang. Menjadikan kuota bergantung model berarti tenant membeli satuan yang artinya bisa berubah tanpa ia diberi tahu.
- **Konsekuensi kalimat di atas, dan ia mengikat saat memilih model:** karena satuannya seragam sementara ongkos per satuan tidak, **seluruh risiko perubahan model ditanggung sisi kita**, bukan tenant. Itu pilihan yang benar untuk tenant, dan justru karena itu batas modelnya harus dijaga di sisi kita.
- **Daftar model yang tersedia belum ditulis, dan itu sisa pekerjaan yang disebut pemilik sendiri.** Nama-nama yang beredar di percakapan belum diverifikasi terhadap katalog SumoPod maupun harga resminya. Sampai daftar itu ada dan ongkos tiap barisnya dihitung dengan cara yang sama seperti tabel di `[BL-069]`, `config/ai.php` tetap pada satu model per provider. Menuliskan nama model yang belum diperiksa ke config berarti menawarkan pilihan yang bisa gagal di panggilan pertama.
- **`AiQuota::dailyLimitFor()` mendapat satu tingkat baru DI ATAS paket, bukan pembaca kedua.** Urutan pembacaannya sudah tunggal dan tetap sejak `[BL-047]`: batas paket → kebijakan bawaan platform (`ai_quota_policies` mode `baseline`) → `config/ai.php`, lalu promo (mode `bonus`) menambah di atas hasilnya. Kuota yang **dibeli** masuk satu tingkat di atas paket, sebelum promo dijumlahkan. Pembaca kedua akan melahirkan dua jawaban untuk "berapa jatah saya hari ini".
- **Kolomnya di `subscriptions`, bukan paket baru per tenant.** `Plan::limits` sudah JSON dan menggoda untuk dipakai, tapi melahirkan paket per tenant akan meledakkan tabel paket. Ini persis keputusan yang sudah diambil untuk seat, dan alasannya tidak berubah.
- **Batasi `profit_by_item` sebelum, bukan sesudah, ini dikerjakan.** Pengukuran di `[BL-069]` menunjukkan token TIDAK tumbuh mengikuti jumlah transaksi (`AiContextService` mengagregasi), tapi `profit_by_item` mengirim satu baris per produk **tanpa batas**. Tenant dengan 200 produk menambah 5.000–8.000 token masukan, tiga sampai empat kali lipat. Harga yang ditetapkan hari ini akan salah untuk tenant terbesar — yaitu justru yang paling mungkin membeli kuota tambahan.
- **Jangan dijanjikan di landing sebelum kodenya ada** — `[BL-067]`(e).
- **Catatan kurs.** Seluruh ongkos rupiah di atas memakai asumsi Rp 16.500/USD dan **tidak terkunci**. Harga jual berdenominasi rupiah di atas ongkos berdenominasi dolar berarti marginnya menipis sendiri saat rupiah melemah. Pada margin 91% itu tidak berbahaya.
- **Berkas:** belum ada. `docs/BACKLOG.md` `[BL-069]` diperbarui dengan angka ini dan tetap **Open** untuk pekerjaan kodenya.

---

### [DEPRECATE] Tombol Simulasi Pembayaran Dicabut — Jalur Uang Ketiga Ditutup (BL-061)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — menutup `[BL-061]`, yang sejak awal berbunyi "dicabut setelah peragaan". Peragaannya sudah berjalan.
- **Dampak:** Controller | Service | Route | Frontend | Test
- **Breaking Change:** Tidak. Rute `billing.simulate.store` hilang, tapi ia hanya pernah bisa dipanggil tenant bertanda `is_demo` di luar produksi — di produksi ia menjawab 404 sejak hari pertama.
- **Deskripsi:** `SimulatedPaymentController`, rute `billing.simulate.store`, prop `simulation`, tombol "Simulasikan pembayaran" di halaman langganan, dan `InvoiceSettlement::canSimulate()` dicabut. Konstanta `SOURCE_SIMULATION` **dipertahankan** sebagai peninggalan. Jalur peragaan lewat gateway tiruan (`[BL-059]`, `billing.payment.simulate`) tidak disentuh — itu yang menggantikannya.
- **Alasan:** Setelah `[BL-059]` ada **tiga** cara sebuah tagihan berpindah ke lunas tanpa uang sungguhan diperiksa: bukti transfer manual (sah, tetap ada), gateway tiruan lewat webhook (jalur baru), dan tombol simulasi satu klik (peninggalan `[BL-045]`). Yang ketiga mubazir — ia melunasi dengan caranya sendiri, **tidak** lewat webhook, dan karena itu tidak membuktikan apa pun tentang jalur yang akan dipakai produksi. Gerbangnya masih benar, jadi ini bukan lubang keamanan; yang menjadikannya utang adalah **jumlahnya**. Tiap jalur menuju `active` adalah satu tempat lagi yang harus ikut dipikirkan setiap kali aturan pelunasan berubah.

- **`SOURCE_SIMULATION` sengaja tidak dihapus, dan itu bukan sisa pembersihan yang terlewat.** Nilai `simulation` mungkin sudah tertulis di kolom `settled_via` beberapa tagihan peragaan. Konstanta yang hilang membuat riwayat itu tak lagi terbaca dari kode, dan angka di database yang tidak punya nama di mana pun adalah angka yang suatu hari akan ditafsirkan salah. Ia diberi docblock yang menyebutnya peninggalan dan melarang jalur baru memakainya.
- **`canSimulate()` ikut dicabut karena tak ada pemanggil lain yang tersisa, dan pencabutannya memindahkan satu tanggung jawab.** Docblock `PaymentGatewayManager` menyebut `canSimulate()` sebagai salah satu dari **dua** penjaga lubang `provisional_blocked`. Sesudah ini gerbang di `PaymentGatewayManager` — driver tiruan yang **gagal di-resolve** di produksi, keras, sebelum satu baris pun jalan — adalah **satu-satunya** yang menjaganya. Docblock-nya diperbarui supaya menyatakan itu, bukan menunjuk kelas yang sudah hilang.
- **Satu pertanggungan dipindahkan, bukan dihapus.** `SimulatedPaymentTest` ikut jalur yang dicabut, tapi tes terakhirnya menguji jalur yang **tetap hidup**: pemilik SaaS memverifikasi bukti transfer lewat `InvoiceSettlement`. Jalur itu sudah tertutup lebih lengkap di `PlatformBillingTest` (status, `verified_by`, tenant aktif, `price_locked`, penyambungan periode) kecuali satu hal — `settled_via` tidak pernah diperiksa di sana. Assertion itu dipindahkan ke tes yang sudah ada, alih-alih menyalin seluruh tesnya ke berkas kedua: `settled_via` adalah satu-satunya yang membedakan pelunasan berbukti dari pelunasan tanpa bukti di riwayat tagihan.
- **Yang TIDAK dicabut, supaya tidak dicari-cari nanti:** `billing.payment.simulate` (panel peragaan pada halaman instruksi pembayaran) tetap ada. Ia tidak melunasi apa pun sendiri — ia memicu webhook, jalur yang sama dengan yang akan dipakai produksi. Itu justru alasan `[BL-059]` dibentuk begitu, dan alasan tombol lama jadi mubazir.
- **Verifikasi:** 276 tes suite Langganan lulus (1.325 assertion), 57 tes suite Platform Billing + Payment Gateway lulus.
- **Berkas:** `app/Http/Controllers/Billing/SimulatedPaymentController.php` (dihapus) · `tests/Feature/Subscription/SimulatedPaymentTest.php` (dihapus) · `routes/web.php` · `app/Services/Billing/InvoiceSettlement.php` · `app/Services/Billing/Gateways/PaymentGatewayManager.php` (docblock) · `app/Http/Controllers/Billing/SubscriptionController.php` · `resources/js/Pages/Billing/Show.vue` · `tests/Feature/Platform/PlatformBillingTest.php` (+1 assertion)

---

### [DECISION] Prabayar, dan Tarifnya dari Omzet Bulan Sebelumnya (BL-056)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — menutup `[BL-056]`, satu-satunya entri backlog berprioritas High yang biayanya cuma satu jawaban.
- **Dampak:** Dokumentasi (`README.md`) — dan **satu cacat penjadwalan yang baru terungkap justru karena keputusan ini dinyatakan**, lihat di bawah.
- **Breaking Change:** Tidak. Keputusannya mempertahankan perilaku yang sudah dipilih `[BL-055]`, jadi tidak ada tagihan yang nominalnya berubah.
- **Deskripsi:** Model langganan dinyatakan **prabayar** — bayar di awal periode untuk periode itu, bukan ditagih di belakang atas pemakaian yang sudah lewat. Karena itu **tarif periode P dihitung dari omzet bulan sebelum P.** Turunannya: pengajuan Harga Adaptif berlaku untuk **periode berikutnya**, bukan periode yang sedang ditagih — yaitu opsi (i) di `[BL-056]`.
- **Alasan:** Pemilik menahan pertanyaan ini saat menutup struktur harga 2026-08-07 ("apakah ajukan itu untuk bulan pengajuan itu, atau bulan depan... catat saja dulu"). `[BL-055]` mendarat 2026-08-10 dengan opsi (i) sebagai **keadaan bawaan yang dipertahankan**, bukan sebagai jawaban — supaya tidak ada keputusan pemilik yang diambil diam-diam. Entri ini menjadikannya jawaban.

- **Contoh yang jadi acuan, dari pemilik sendiri:** daftar Juli → Juli & Agustus gratis (`trial_months = 2`) → tagihan pertama September, tarifnya dari **omzet Agustus**; Oktober dari omzet September, dan seterusnya.
- **Kenapa prabayar memaksa aturan itu, bukan sekadar cocok dengannya.** Tagihan sebuah periode terbit **sebelum** periode itu dimulai (`invoice_lead_days`, H-7). Pada saat tagihannya terbit, bulan yang ditagih belum terjadi — jadi tarifnya tidak mungkin diambil dari omzet bulan itu. Satu-satunya angka yang sudah ada adalah omzet bulan sebelumnya.
- **Kenapa opsi (ii) ditolak, dan kenapa penolakannya lebih dari soal kemudahan.** Opsi (ii) — pengajuan berlaku untuk bulan pengajuan — menuntut tagihan terbuka diterbitkan ulang. `[BL-056]` sudah memperingatkan bahayanya: **pengajuan yang bisa memotong tunggakan berjalan adalah jalan keluar dari tagihan mana pun.** Penjaga alaminya memang ada — harganya dihitung dari penjualan yang tenant catat sendiri, jadi menekannya merusak datanya sendiri — tapi mengandalkan penjaga yang tak pernah dinyatakan adalah cara mendapat lubang yang tidak ada yang ingat pernah dibiarkan terbuka.
- **Harganya membeku saat tagihan terbit, dan itu bagian dari keputusan — bukan batasan teknis yang kebetulan ada.** `Invoice::amount` dan `pricing_context` dibekukan di saat terbit. Tenant yang mengajukan di tengah tenggat tetap melunasi tagihan yang ada; keringanannya muncul di tagihan berikutnya. Kalimat sukses consent sudah menjanjikan itu sejak awal, jadi keputusan ini sekaligus menyelaraskan janji dengan perilakunya.
- **Kekhawatiran yang sudah aman dan tidak perlu dikerjakan:** bahwa sistem memeriksa omzet **bulan berjalan** padahal tunggakannya dari bulan lalu. `pricingAsOf()` memakai awal bulan periode tagihan dan `current_period_end` **membeku** selama tenant belum membayar — jadi omzet yang dipakai memang omzet periode yang tertunggak, bukan omzet bulan tenant akhirnya membayar.

- **Temuan yang muncul justru karena aturannya dinyatakan: hari ini aturan di atas hanya berlaku untuk sebagian tenant.** Ini bukan bagian dari keputusannya melainkan cacat yang baru terlihat begitu ada aturan untuk mengukurnya. Dua hal bertumpuk:
  1. **`subscriptions:compute-revenue` (tanggal 1, 04:00) berjalan SESUDAH `subscriptions:advance-lifecycle` (harian, 03:30)** — dan penerbit tagihan tinggal di dalam `advanceLifecycle()`. Setiap tanggal 1, penerbit berjalan 30 menit **sebelum** omzet bulan yang baru tutup dihitung.
  2. **Tagihan terbit H-7**, sementara jangkar tagih diambil dari tanggal daftar. Untuk tenant berjangkar tanggal 1–8, tagihan periode September terbit **sebelum** Agustus tutup, sehingga `MonthlyRevenueResolver` — yang mengambil ringkasan terbaru dengan `period <= asOf` — jatuh ke **Juli**.

  Akibatnya tenant berjangkar tanggal **1–8** ditagih dari omzet **dua bulan** sebelumnya, sementara tenant berjangkar tanggal 9+ ditagih sebagaimana keputusan ini. Contoh pemilik sendiri — daftar 7 Juli — mendarat tepat di jendela yang salah: tagihan Septembernya memakai omzet **Juli**, yang bagi tenant itu bahkan bukan bulan penuh (ia baru berjualan sejak tanggal 7), sehingga omzetnya terlalu rendah dan bracket Adaptifnya terlalu murah. Pada tangga 90/75/50/25% selisih satu bracket itu Rp 25.000/bulan.
- **Cacat itu TIDAK diperbaiki di entri ini, dan sengaja dipisahkan jadi `[BL-080]`.** Butir (1) tinggal menukar jam di `routes/console.php`, tapi butir (2) menuntut pilihan yang punya konsekuensi sendiri: memundurkan penerbitan tagihan bagi sebagian tenant berarti memperpendek masa pemberitahuan, dan itu keputusan komersial, bukan perbaikan penjadwalan. Menggabungkan keduanya dalam satu commit akan menyelundupkan keputusan kedua di balik yang pertama.
- **Berkas:** `README.md` — bagian "Model Bisnis & Langganan", subbagian baru "Prabayar, dan harganya dari bulan lalu"; sekaligus meluruskan dua hal yang sudah usang di bagian yang sama: siklus hidup yang masih menulis `trial (30 hari)`/`trial_days` padahal config sudah `trial_months = 2`, dan aturan seat yang masih menyebut `seat_high_water` sebagai dasar tagihan padahal `[BL-053]` sudah menggantinya dengan `purchased_extra_seats`. · `docs/BACKLOG.md` (`[BL-056]` ditutup, `[BL-080]` lahir)

---

### [ADDITION] Pembayaran Non-Tunai Bisa Difoto, dan Sinkronisasi Offline Ternyata Tidak Perlu Ikut Berubah (BL-075)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — `[BL-075]`
- **Dampak:** Migrasi | Model | Service | Controller | Request | Route | Command | Frontend | Test
- **Breaking Change:** Tidak. `payment_proof_enabled` bawaannya `false`, jadi tidak ada satu pun toko yang alur kasirnya berubah sampai owner-nya sendiri menyalakannya.
- **Deskripsi:** Kasir bisa memotret bukti QRIS/transfer sebelum penjualan ditutup, dan fotonya melekat pada **pembayarannya** — bukan pada penjualannya. Bisa dinyalakan/dimatikan per toko, dan saat menyala fotonya **wajib**. Fotonya duduk di disk privat sebagai WEBP dan hanya keluar lewat rute ber-auth dengan pemeriksaan tenant.
- **Alasan:** Sebelum ini, kasir yang menerima QRIS hanya bisa mengetik kode referensi — dan kode yang diketik tangan tidak membuktikan apa pun soal uangnya masuk. Perselisihan "katanya sudah transfer" tidak punya alat bukti apa pun di sisi toko.

- **TEMUAN YANG MEMBATALKAN SEPARUH RENCANA: penjualan offline sudah cash-only, dan sudah begitu sejak awal.** `TransactionService::assertCashOnly()` menolak setiap pembayaran non-tunai pada jalur sinkronisasi, dan `POS.vue` bahkan sudah menyaring daftar metode jadi tunai saja saat perangkat offline. Artinya seluruh bagian "OFFLINE" di entri backlognya — kompresi sebelum masuk antrean, penyimpanan sebagai Blob alih-alih base64, `navigator.storage.persist()` sebagai prasyarat, unggahan yang terpisah dari pengiriman penjualannya — **menjawab masalah yang belum ada**. Non-tunai tidak pernah terjadi offline, jadi tidak pernah ada foto yang perlu antre. Keputusan pemilik "wajib saat online, dilewati saat offline" karena itu **tidak butuh kode sama sekali**: yang bisa dilewati offline tidak pernah muncul di sana.
- **Fotonya diunggah SEBELUM penjualannya disimpan, dan itu keputusan paling menentukan di entri ini.** Checkout POS mengirim JSON bersarang — item, modifier, pembayaran, kejadian upsell. Menyelipkan berkas ke dalamnya memaksa seluruh payload pindah ke `multipart/form-data`, di mana setiap angka jadi string dan setiap boolean jadi `"1"`/`"0"`. Menukar encoding seluruh jalur checkout demi satu foto berarti mempertaruhkan validasi yang menjaga **uang** demi hal yang jauh lebih sepele daripada uang. Jadi: foto diunggah sendiri lewat `POST /cashier/bukti-bayar`, mengembalikan TOKEN, dan checkout mengirim tokennya sebagai string biasa. Efek sampingnya justru yang diminta backlognya butir offline (4) — unggahan foto jadi langkah yang bisa diulang sendiri tanpa mengirim ulang penjualannya.
- **Tokennya diperiksa benar-benar menunjuk berkas yang ada.** Aturan `uuid` saja tidak cukup: "wajib berfoto" yang bisa dipenuhi dengan **mengetik** UUID sembarang bukan kewajiban. Ada tesnya.
- **EMPAT jalur pembuat baris pembayaran, bukan tiga.** Backlognya menyebut tiga (checkout, tagihan terbuka, sinkronisasi offline). Yang keempat ditemukan saat mengerjakannya: `TransactionEditService` **menghapus lalu membangun ulang** seluruh baris pembayaran dari kiriman client — dan client edit tidak pernah mengirim bukti. Tanpa penanganan, mengoreksi jumlah item pada penjualan QRIS akan **menghapus buktinya sebagai efek samping**: tanpa galat, tanpa jejak, ketahuan berbulan-bulan kemudian saat perselisihan datang. Sekarang bukti diselamatkan lewat `payment_method_id`, dan berkas milik metode yang dicabut ikut dihapus supaya tidak jadi yatim.
- **Pelunasan tagihan terbuka ikut mewajibkan, dan validasinya pindah ke FormRequest karena itu.** Meja yang memesan dulu lalu membayar QRIS saat pulang tidak pernah muncul dalam pengujian manual yang berhenti di layar POS. `PayOpenBillRequest` lahir untuk itu, dan `CashierTopbar` — yang memuat modal pelunasannya di **setiap** halaman kasir — ikut diberi saklarnya lewat prop Inertia yang dibagikan. Tombol yang menolak tanpa memberi jalan memotret adalah jalan buntu, dan itu justru yang backlognya larang.
- **Kolomnya di `transaction_payments`, bukan di `transactions`.** Satu transaksi bisa dibayar setengah tunai setengah QRIS; buktinya melekat pada bagian yang lewat QRIS. Ada tes split bill yang memastikan baris tunai tetap `null` dan tidak pernah diminta foto.
- **"Non-tunai" diturunkan dari `payment_methods.type`, bukan dari daftar baru.** Enum-nya sudah membedakan `cash` dari sisanya sejak migrasi pertama. Tipe yang ditambahkan nanti otomatis ikut terhitung non-tunai — jawaban yang memang benar, dan alasan `PaymentMethod::TYPE_CASH` jadi satu-satunya konstanta yang ditambahkan.
- **`ProofFileService` dipakai ulang, bukan `ImageService`.** Kelas dari `[BL-077]` itu memakai `scaleDown()` yang menjaga rasio; `cover(800,800)` milik `ImageService` akan memotong tepat nominal dan kode referensi yang jadi alasan foto itu diambil. PDF sengaja **tidak** diterima di sini, berbeda dari bukti transfer langganan: ini foto yang diambil kasir di tempat, dan berkas yang tidak bisa dipratinjau adalah berkas yang tidak pernah bisa diperiksa siapa pun.
- **Retensi: TANPA BATAS untuk sekarang, atas keputusan pemilik (2026-08-19).** Tidak ada pembersihan otomatis untuk foto yang sudah melekat pada pembayaran. Konsekuensinya sudah tertulis di backlognya dan tetap berlaku: disk server tumbuh tanpa batas, dan `[BL-076]` (object storage) berubah dari peningkatan jadi pekerjaan yang mendesak. Yang **ada** perintah pembersihnya hanya berkas TERTUNDA yang tidak pernah diklaim — `payment-proofs:prune-unclaimed`, harian pukul 03:50, batas 24 jam. Keduanya sengaja dipisah: kebersihan disk bukan kebijakan retensi, dan mencampurnya di satu perintah membuat perubahan kebijakan berisiko menghapus yang bukan sampah.
- **Batas 24 jam, bukan satu jam.** Batas ketat akan menghapus foto milik modal pembayaran yang **masih terbuka** di perangkat kasir — dan yang hilang bukan sekadar berkas, melainkan kemampuan menyelesaikan penjualan yang sedang berlangsung.
- **Satu lubang yang diketahui dan sengaja dibiarkan terbuka.** Modal EDIT transaksi belum punya tombol kamera, jadi pembayaran yang diubah menjadi non-tunai lewat pengeditan bisa lolos tanpa bukti. Menutupnya dengan menolak edit semacam itu akan menciptakan jalan buntu di layar yang tidak punya cara memotret. Pengeditan sendiri sudah dibatasi (owner kapan saja, kasir hanya dalam shift laci terbuka) dan seluruhnya teraudit. Dicatat di `[BL-075]` sebagai sisa.
- **Berkas:** `database/migrations/2026_08_19_070144_add_payment_proof_enabled_to_tenants_table.php` (baru) · `..._add_proof_path_to_transaction_payments_table.php` (baru) · `app/Services/PaymentProofService.php` (baru) · `app/Http/Controllers/Cashier/PaymentProofController.php` (baru) · `app/Http/Requests/PayOpenBillRequest.php` (baru) · `app/Console/Commands/PruneUnclaimedPaymentProofs.php` (baru) · `app/Models/Tenant.php` · `app/Models/PaymentMethod.php` · `app/Models/TransactionPayment.php` · `app/Services/TransactionService.php` · `app/Services/TransactionEditService.php` · `app/Http/Requests/StoreTransactionRequest.php` · `app/Http/Controllers/MediaController.php` · `app/Http/Controllers/Cashier/POSController.php` · `app/Http/Controllers/Owner/Settings/SystemBehaviorController.php` · `app/Http/Middleware/HandleInertiaRequests.php` · `routes/web.php` · `routes/console.php` · `resources/js/Components/PaymentModal.vue` · `resources/js/Components/CashierTopbar.vue` · `resources/js/Pages/Cashier/POS.vue` · `resources/js/Pages/Owner/Settings/Operations.vue` · `tests/Feature/Cashier/PaymentProofTest.php` (baru, 15 tes)

---

### [ADDITION] Gambar Dikecilkan di Perangkat Sebelum Diunggah, dan Bukti Transfer Berhenti Mendarat Mentah (BL-077 butir a & b)
- **Tanggal:** 2026-08-19
- **Fase Terkait:** Di Luar Fase — `[BL-077]` butir (a) dan (b). Butir (c) tidak dikerjakan; alasannya di bawah.
- **Dampak:** Frontend | Service | Controller | Test
- **Breaking Change:** Tidak. Berkas yang sudah tersimpan tidak disentuh, dan kolom `invoices.proof_path` tetap bentuk yang sama — hanya isinya yang kini bisa berakhiran `.webp`.
- **Deskripsi:** Composable `useImageCompressor` mengecilkan dan mengonversi gambar ke WEBP **di peramban**, sebelum satu byte pun menyeberangi jaringan; `ImageUpload` (formulir produk) sudah memakainya. Di sisi server, `ProofFileService` baru menormalkan bukti transfer langganan yang selama ini disimpan apa adanya — dengan PDF tetap disalin utuh.
- **Alasan:** Pertanyaan pemilik — "apakah ada auto compress resize dan convert ke webp untuk gambar" — ternyata berjawab "ada, tapi hanya untuk foto produk". `ImageService` sudah melakukan ketiganya dengan benar, tapi ia baru bekerja **setelah** berkasnya sampai, dan dua jalur lain tidak menyentuhnya sama sekali. Butir (a) dikerjakan lebih dulu bukan karena paling mudah, melainkan karena ia prasyarat `[BL-075]`: begitu foto bukti bayar masuk outbox IndexedDB, "dikecilkan di server" tidak menolong apa-apa — tidak ada server yang bisa dihubungi.

- **Kompresi tidak pernah boleh menggagalkan unggahan, dan itu membentuk seluruh modulnya.** Setiap jalur gagal di `useImageCompressor` — peramban tanpa `createImageBitmap`, berkas yang tak bisa didekode, `canvas.toBlob` yang mengembalikan `null` karena WEBP tak didukung — berakhir dengan **mengembalikan berkas aslinya**, bukan melempar error. Penghematan yang berubah jadi syarat sah adalah penghematan yang suatu hari akan membuat owner tidak bisa menyimpan produknya sama sekali, di peramban yang tidak pernah kita uji.
- **Hasil yang lebih besar dibuang.** Tangkapan layar PNG kecil dan gambar yang sudah WEBP kerap **membengkak** setelah dikodekan ulang. Bila `blob.size >= file.size`, yang dipakai tetap yang asli — kalau tidak, fitur bernama "kompresi" akan diam-diam menambah beban di sebagian kasus.
- **Urutan validasi di `ImageUpload` dibalik, dan itu memperbaiki penolakan yang keliru.** Dulu: tipe → ukuran → terima. Foto 8 MB dari kamera ponsel ditolak mentah-mentah padahal setelah dikecilkan ia beberapa ratus kilobyte. Sekarang: tipe → **kompresi** → ukuran, diperiksa atas hasilnya. Yang ditolak jadi gambar yang benar-benar tak bisa dikecilkan, bukan gambar yang belum dicoba.
- **GIF beranimasi tidak disentuh.** Menggambarnya ke canvas menyisakan satu frame diam — itu bukan versi lebih kecil dari berkas yang sama, itu berkas lain.
- **`ProofFileService` berdiri sendiri, dan `ImageService` sengaja tidak diutak-atik.** Kontrak `ImageService` tegas: produk, **persegi**, dua rendition, disk privat. Dua di antaranya salah untuk bukti bayar. Tangkapan layar e-wallet itu tinggi dan sempit, dan `cover(800,800)` memotong tepat bagian yang jadi alasan foto itu diambil — nominal dan kode referensinya; di kelas baru gambar dimuat **ke dalam** kotak lewat `scaleDown()`, rasio dijaga, tidak pernah dipotong, tidak pernah diperbesar. Dan bukti bayar bisa berupa PDF, yang tidak bisa dilewatkan ke encoder WEBP sama sekali.
- **Percabangan gambar-vs-PDF ada di dalam service, bukan di pemanggil.** `[BL-075]` akan jadi pemanggil kedua, dan aturan "PDF disalin apa adanya" yang ditulis dua kali adalah aturan yang suatu saat akan berbeda di satu tempat.
- **Unggah ulang menghapus berkas sebelumnya.** Tombolnya berlabel "Unggah ulang bukti" dan memang dipakai — tanpa penghapusan, tiap percobaan meninggalkan berkas yatim di disk yang tidak akan pernah dibuka lagi, di disk yang `[BL-076]` sudah tandai sebagai masalah.
- **Butir (c) — plugin Vite untuk aset statis — tidak dikerjakan, dan bukan karena terlewat.** Ia menuntut penambahan dependensi, yang butuh persetujuan lebih dulu. Lima tangkapan layar landing yang masih PNG ditangani lewat `[BL-032]` butir (3) sebagai konversi sekali jalan; celah "berkas berat berikutnya masuk dengan cara yang sama" masih terbuka dan tetap tercatat di `[BL-077]`.
- **`Billing/Show.vue` sengaja tidak ikut disentuh.** Pemilih berkas bukti transfer di sana belum memakai composable-nya karena berkas itu sedang punya perubahan lain yang belum di-commit (pencabutan tombol simulasi, `[BL-061]`); menumpanginya berarti mencampur dua pekerjaan dalam satu commit. Sisi servernya sudah menormalkan berkasnya, jadi yang tertinggal hanya penghematan bandwidth, bukan penghematan disk.
- **Tidak ada test JS, dan itu keadaan proyek, bukan pilihan entri ini.** Belum ada test runner sisi peramban di `package.json`; menambahkannya adalah penambahan dependensi. Yang bisa diuji programatik — seluruh butir (b) — diuji: WEBP untuk gambar, `.pdf` apa adanya untuk PDF, dan penghapusan berkas lama saat unggah ulang. Sisi peramban diverifikasi lewat `npm run build` dan pratinjau.
- **Berkas:** `resources/js/composables/useImageCompressor.js` (baru) · `resources/js/Components/ImageUpload.vue` · `app/Services/ProofFileService.php` (baru) · `app/Http/Controllers/Billing/UpgradeController.php` · `tests/Feature/Subscription/ProvisionalUpgradeTest.php` (+3 tes)

---

### [ADDITION] Jenis Usaha Akhirnya Menentukan Fitur, Bukan Cuma Harga (BL-034)
- **Tanggal:** 2026-08-15
- **Fase Terkait:** Di Luar Fase — `[BL-034]`
- **Dampak:** Config | Service | Controller | Frontend | Test
- **Breaking Change:** Tidak. Tenant yang sudah ada tidak disentuh — preset hanya berlaku pada pendaftaran baru.
- **Deskripsi:** Jawaban "Jenis Usaha" di formulir daftar kini menyetel kapabilitas awal tenant, bukan cuma dasar penetapan harga. Mendaftar sebagai Kuliner menyalakan antrian dapur; Retail dan Jasa tidak. Petanya tinggal di `config/business-presets.php`, dan pendaftar melihat hasilnya sebagai daftar centang yang bisa ia ubah sebelum menekan Daftar.
- **Alasan:** Pemilik memintanya begini: "pakai paket kategori, misal bazar maka aktif itu cuma kasir dan stock biasa, kalau cafe maka akan aktif open bill dll". Sebelum entri ini, warung dan kafe mendarat di aplikasi yang **persis sama** — antrian dapur mati, pesan mandiri mati — lalu harus menemukan sendiri halaman Pengaturan untuk menyalakannya. Akibatnya pengguna baru menilai produk dari keadaan paling kosongnya, dan pertanyaan yang sudah kita ajukan di formulir tidak dipakai untuk apa pun yang ia rasakan.

- **Yang dikirim formulir adalah HASIL centangnya, bukan nama presetnya.** Ini keputusan yang menentukan bentuk seluruh sisanya. Mengirim `business_type` lalu membiarkan server menyimpulkan fiturnya akan membuat daftar centang di layar jadi hiasan: pendaftar yang melepas "Antrian dapur" tetap mendapatkannya, dan tidak ada satu pun tempat di mana kekeliruan itu terlihat. Karena yang dikirim daftar akhirnya, preset tidak pernah jadi kata terakhir — ia cuma mengisi keadaan awal.
- **Daftar kosong dihormati, dan itu butuh `array_key_exists`, bukan `?:`.** Pendaftar yang melepas semua centang memang meminta aplikasi paling polos. Kalau `[]` diperlakukan sebagai "tidak dijawab" lalu jatuh ke preset, ia akan tetap mendapat Analisis AI yang baru saja ia tolak — `ai_enabled` bawaannya **menyala** di kolom database. Karena alasan yang sama `columnsFor()` selalu menyebut SETIAP kolom, bukan hanya yang menyala: kolom yang tidak disebut diam-diam mengambil bawaan `$attributes`.
- **Preset berlaku SEKALI, saat pendaftaran — dan itu ditegakkan, bukan cuma diniatkan.** Sejak `[DECISION] Jenis Usaha Berpindah ke Pemilik Toko` (2026-07-31), jenis usaha bisa diubah kapan saja dari Pengaturan. Mengubahnya sengaja **tidak** menerapkan ulang preset: pemilik yang sudah mematikan antrian dapur lalu membetulkan jenis usahanya dari "lainnya" ke "kuliner" sedang memperbaiki keterangan tokonya, bukan meminta layar dapur muncul kembali. `BusinessProfileController` tidak menyentuh satu pun kolom `*_enabled`, dan ada tesnya yang gagal bila seseorang menambahkannya.
- **`self_order` tidak menyala di preset mana pun, dan itu bukan kelalaian.** Menyalakannya membuka tautan pemesanan yang bisa diakses siapa saja. Menyimpulkan dari "orang ini memilih Kuliner" bahwa ia ingin katalognya publik adalah kesimpulan yang tidak dibayar oleh datanya. Itu keputusan yang harus diambil pemiliknya sendiri, sadar bahwa ia mengambilnya.
- **Preset `bazar` belum ada di sini, dan entri ini tidak berpura-pura punya.** Permintaan pemilik menyebut bazar lebih dulu daripada kafe, tapi mode bazar belum punya wujud apa pun di kode — `[BL-035]` masih menunggu keputusan tentang **apa yang berubah** saat ia aktif. Menambahkan preset `bazar` yang mencentang fitur-fitur yang sudah ada hari ini akan mengulang persis kesalahan yang `[BL-032]` butir 1 baru saja bersihkan dari landing: menyebut sesuatu seolah ada wujudnya. Begitu `[BL-035]` diputuskan, bazar masuk sebagai **satu baris tambahan** di config — bukan sebagai perubahan alur pendaftaran. Di situlah nilai peta ini.
- **Menimpa centang yang sudah disentuh, saat jenis usaha diganti.** Alternatifnya — "berhenti menerapkan preset begitu pengguna menyentuh daftarnya" — mengejutkan ke arah yang lebih buruk: orang yang salah pilih "Retail" lalu membetulkannya jadi "Kuliner" akan mendapat daftar retail yang tidak pernah ia minta, tanpa petunjuk bahwa pilihan barunya diabaikan. Menimpa itu terlihat — daftarnya berubah di depan mata dan masih bisa disunting lagi.
- **Yang sengaja TIDAK ikut jadi preset:** `upsell_mandatory` dan `order_identity_mode`. Keduanya ada di halaman Pengaturan yang sama dan gampang ikut terbawa, tapi keduanya **aturan kerja**, bukan kapabilitas modul — tidak menggerbangi satu rute pun, dan `upsell_mandatory` bahkan bisa menahan tombol bayar. Menebaknya dari jenis usaha berarti menebak cara orang bekerja, bukan modul apa yang ia butuhkan.
- **Satu tes menjaga config-nya jujur.** `setiap jenis usaha punya preset, dan setiap fitur preset dikenali Tenant` memeriksa tiga hal yang semuanya gagal dalam diam bila salah: jenis usaha tanpa baris preset (mendarat tanpa kapabilitas apa pun), nama fitur yang salah ketik (`hasFeature()` menjawab `false` tanpa keluhan — centangnya menyala di layar lalu tidak menggerbangi apa-apa), dan kolom yang tidak `$fillable` (`Tenant::create()` membuangnya diam-diam).
- **Berkas:** `config/business-presets.php` (baru) · `app/Services/BusinessPresetService.php` (baru) · `app/Http/Controllers/Auth/AuthController.php` · `app/Http/Controllers/Owner/Settings/BusinessProfileController.php` (komentar penjaga) · `resources/js/Pages/Auth/Register.vue` · `tests/Feature/Auth/BusinessPresetTest.php` (baru, 10 tes)

---

### [ADDITION] Halaman Menunjukkan Bentuknya Sebelum Datanya Sampai (BL-037)
- **Tanggal:** 2026-08-15
- **Fase Terkait:** Di Luar Fase — `[BL-037]`, gelombang kedua. Gelombang pertama (delapan komponen kerangka + empat halaman tersibuk) selesai 2026-08-13.
- **Dampak:** Controller | Frontend | Test | DESIGN.md
- **Breaking Change:** Tidak untuk pengguna. Untuk pengembang: 20 prop halaman berpindah ke `Inertia::defer()`, jadi test yang memeriksanya harus lewat `loadDeferredProps()` — bila sebuah test tiba-tiba berkata "Property [x] does not exist", inilah sebabnya.
- **Deskripsi:** Sisa tabel pelacakan `[BL-037]` habis. Setiap halaman berdaftar panjang kini menjawab perpindahan dengan bentuknya sendiri — kerangka yang menempati ruang yang sama dengan isinya — alih-alih dengan garis tipis di puncak layar. Bilah kemajuannya tidak dihapus, hanya diberi `delay: 500`.
- **Alasan:** Permintaan pemilik yang melahirkan entri ini berbunyi "render skeleton, hilangkan progress bar yang mengganggu dan keliatan aplikasi lambat loading". Komponennya sudah lahir 2026-08-13 berikut aturan pemakaiannya di `DESIGN.md` §6; yang tersisa adalah pekerjaan mekanis — dan justru di situ letak bahayanya, karena kerangka yang dipasang tanpa `Inertia::defer()` di sisi server tidak akan pernah tampil sedetik pun.

- **Tiga penyimpangan dari rencana, dan ketiganya disengaja.**
  1. **`Platform/Dashboard` dikeluarkan dari daftar.** Controllernya hanya mengirim dua cacah. Menunda dua bilangan bulat berarti menukar respons yang sudah instan dengan permintaan HTTP kedua.
  2. **`dailySeries` di rekap bulanan tetap eager**, berlawanan dengan usulan awal entri backlognya. Angka ringkasan di kepala halaman **diturunkan dari deret itu**, jadi kuerinya sudah terlanjur jalan untuk cat pertama; menundanya hanya memindahkan 31 baris kecil ke permintaan kedua dan membuat grafiknya berkedip untuk data yang sudah ada di tangan. Aturan Pasangan di `DESIGN.md` §6 bekerja dua arah: prop yang tidak ditunda tidak boleh diberi kerangka, sama seperti prop yang ditunda tidak boleh tanpa kerangka.
  3. **Sebagian "SkeletonTable" di usulan awal menjadi `SkeletonGrid` + `SkeletonCard`.** Riwayat kasir, daftar produk, stok, modifier, dan papan dapur ternyata bukan tabel melainkan tumpukan kartu. Aturan Ruang yang Sama menang atas apa yang tertulis di kolom usulan — kerangka tabel di atas halaman berisi kartu justru menggeser isinya saat data tiba.
- **Empat prop, satu kueri: daftar langganan platform.** `Platform/Subscriptions/Index` mengirim daftarnya beserta tiga peta pendamping (`seat_usage`, `billing`, `brackets`) yang **semuanya diturunkan dari halaman baris yang sama**. Empat closure `Inertia::defer()` yang masing-masing mengulang paginasinya akan membuat penundaan ini lebih mahal daripada tidak menunda sama sekali. Keempatnya kini berbagi satu hasil ber-memo dan satu grup, jadi tetap satu kueri dan satu permintaan lanjutan.
- **Papan dapur ditunda tanpa merusak polling-nya.** `Cashier/Queue` menyegarkan diri tiap tujuh detik lewat `router.reload({ only: ['queue'] })` — permintaan parsial yang menyebut nama propnya, dan permintaan seperti itu **tetap** menyelesaikan prop yang ditunda. Papan lama juga tetap terpasang sampai papan baru datang, jadi kerangkanya hanya tampil di pemuatan pertama, bukan berkedip di tiap poll.
- **Kerangka buatan sendiri yang tersisa ikut dimigrasikan.** Dua blok `animate-pulse` lepas — di `TransactionEditModal.vue` dan di `Owner/AiAnalysis/Index.vue` — diganti `SkeletonText`. Yang di AiAnalysis menunggu **pekerjaan antrean**, bukan prop Inertia, jadi ia tidak berpasangan dengan `Inertia::defer()` mana pun; yang disamakan hanyalah bahasa pemuatannya. Setelah ini tidak ada lagi `animate-pulse` di luar `resources/js/Components/Skeleton/` selain satu titik status berdenyut di halaman instruksi pembayaran, yang memang bukan kerangka.
- **Bilah kemajuan dikecilkan, bukan dimatikan — dan `DESIGN.md` berbalik arah karenanya.** Aturan Bilah Kemajuan dulu berbunyi "ia bertahan sampai tabel pelacakan habis". Sekarang tabelnya habis, dan aturannya ditulis ulang jadi **larangan menghapusnya**: kerangka hanya bisa berdiri di atas prop yang ditunda, sedangkan pengiriman formulir (POST/PUT) tidak punya prop tertunda sama sekali. Menghapus bilahnya berarti mencabut umpan balik dari setiap tombol Simpan di aplikasi, plus dari halaman yang sengaja tidak masuk daftar — autentikasi, formulir, tagihan bertahap, dan galat. `delay: 500` membuatnya praktis tak terlihat di navigasi biasa, yang memang tujuan permintaan awalnya.
- **Satu jebakan sisi klien, dua kali muncul.** Prop yang ditunda bernilai `undefined` saat `setup()` berjalan. Di `Owner/Stock/Index.vue` `expandAll()` dulu dipanggil sekali di setup dan akan membuka nol baris selamanya; ia kini juga menunggu propnya lewat `watch`. Pola yang sama sudah menambal `POS.vue` pada gelombang pertama, saat panen snapshot offline nyaris menimpa katalog tersimpan dengan katalog kosong.
- **Dua uji kebocoran data platform ikut diperkuat, karena penundaan melemahkannya diam-diam.** `PlatformIsolationTest` dan `PlatformRevenueTest` memeriksa isi respons mentah untuk memastikan data operasional tenant tidak ikut terkirim. Sejak daftarnya ditunda, respons pertamanya **memang kosong** — dan pemeriksaan seperti itu lulus tanpa membuktikan apa pun. Keduanya kini menembak permintaan parsial yang benar-benar membawa datanya, dan lebih dulu memastikan payload itu berisi (`toContain('Kopi Story')`) supaya alamat yang salah tidak lulus hanya karena tidak berisi apa-apa.
- **File Terdampak:**
  - Controller (prop berpindah ke `Inertia::defer()`): `Cashier/POSController@history`, `Cashier/QueueController@index`, `Owner/ProductController@index`, `Owner/StockController@index/@history/@movements`, `Owner/ReportController@monthly/@upsell/@cashDrawers`, `Owner/OfflineReviewController@index`, `Owner/CategoryController`, `Owner/ModifierController`, `Owner/PaymentMethodController`, `Owner/RoleController`, `Owner/StaffController`, `Platform/TenantController@index`, `Platform/SubscriptionController@index`, `Platform/AuditLogController@index`.
  - Halaman Vue (pembungkus `<Deferred>` + kerangka): 17 berkas di `resources/js/Pages`, plus `Components/TransactionEditModal.vue`.
  - `resources/js/app.js` — `progress.delay = 500`.
  - `DESIGN.md` §6 — Aturan Bilah Kemajuan ditulis ulang.
  - `tests/Feature/DeferredPageDataTest.php` — 12 test tambahan, satu di antaranya berdataset untuk delapan halaman yang isinya cuma satu daftar.
  - Test yang menyesuaikan diri: `Cashier/POSTest`, `KitchenQueueTest`, `Owner/StaffModuleVisibilityTest`, `Authorization/ModuleAccessTest`, `Platform/PlatformAuditLogTest`, `Platform/PlatformBillingTest`, `Platform/PlatformIsolationTest`, `Platform/PlatformRevenueTest`.
- **Cara memeriksanya.** 987 test lewat, 4.744 assertion. Yang dikunci test barunya adalah pembagian eager/tertunda tiap halaman — mana yang **wajib** ada di cat pertama dan mana yang menyusul. Pembagian itu tidak terlihat dari mana pun kecuali dari berkas test itu: begitu sebuah prop dikembalikan menjadi eager, kerangka yang menggantikannya tidak akan pernah terlihat lagi dan tidak ada satu pun test lain yang gagal.
- **Yang TIDAK dikerjakan:** kerangkanya belum pernah dilihat pada koneksi lambat sungguhan — di dev, respons pertama dan permintaan lanjutannya sama-sama tiba dalam puluhan milidetik, jadi kerangkanya lewat terlalu cepat untuk dinilai. Pengukuran yang sebenarnya menunggu pengujian dengan throttling jaringan, atau tenant berkatalog besar.

---

### [ADDITION] Masa Coba Berakhir dengan Pertanyaan, Bukan dengan Tagihan (BL-044 butir c)
- **Tanggal:** 2026-08-15
- **Fase Terkait:** Di Luar Fase — `[BL-044]` butir (c), butir terakhir entri itu. Penghalangnya (`[BL-048]`) ditutup `[BL-055]` pada 2026-08-10.
- **Dampak:** Config | Service | Controller | Frontend | Test
- **Breaking Change:** Tidak. Satu kunci config baru dan satu kunci payload baru; tidak ada yang berubah bentuk.
- **Deskripsi:** Dashboard owner menyodorkan kedua jalur harga berikut angkanya masing-masing sebelum masa coba habis — dan menyodorkannya cukup awal untuk masih sempat mengubah tagihan pertama.
- **Alasan:** Catatan pemilik yang melahirkan `[BL-044]` berbunyi "wajib melakukan ajukan subsidi **atau** kena tagihan biaya normal". Kata "atau" itu mengandaikan tenant pernah **ditanya**. Sampai sekarang tidak pernah: jalur harga dipilih diam-diam saat pendaftaran (`startTrial()` selalu `normal`) dan hanya berubah bila tenant sendiri menemukan halaman Langganan. Butir (b) sudah membuat tagihannya terbit sendiri; yang belum ada adalah pertanyaan yang mendahuluinya.

- **Jendelanya H-14, bukan H-7, dan selisih itu adalah seluruh isi butir ini.** Tagihan berbayar pertama terbit `invoice_lead_days` (7) hari sebelum masa coba habis, nominalnya dibekukan di sana, dan `issueDuePeriodInvoices()` menolak menerbitkan periode yang sama dua kali — penjaga yang memang benar. Pilihan yang baru disodorkan di hari yang sama karena itu tiba **setelah keputusannya sudah diambilkan**: tenant memilih Harga Adaptif, tetap menerima tagihan harga penuh, dan keringanannya baru berlaku sebulan kemudian. Config `trial_choice_lead_days` = 14 memberi satu minggu penuh untuk memutuskan sebelum angkanya mengeras. Angkanya wajib lebih besar dari `invoice_lead_days`, dan alasannya ditulis di docblock config-nya supaya tidak ada yang menurunkannya tanpa tahu apa yang dipatahkan.
- **Sesudah H-7 kartunya TIDAK hilang, keadaannya yang berubah.** `first_invoice_issued` dikirim sebagai fakta, dan kalimat di layar berganti jadi "pindah jalur tetap bisa, tapi berlakunya pada tagihan berikutnya". Menyembunyikan tawarannya akan terasa lebih rapi dan justru menyesatkan; membiarkan kalimatnya sama akan membuat tenant membaca tagihannya sebagai kesalahan sistem.
- **Tenant yang tidak layak tetap melihat kartunya, dengan satu jalur dan sebabnya.** Kelayakan ditanyakan ke `adaptiveVerdict()` — bukan diperiksa ulang di dashboard — jadi tenant beromzet di atas ambang membaca "Omzet Anda Rp X, di atas batas Rp Y untuk keringanan" alih-alih menemukan tombol yang mati. Menyembunyikan kartunya seluruhnya akan berarti kelompok itu tidak pernah diberi tahu bahwa masa gratisnya berujung tagihan Rp 100.000 — dan itu persis kabar yang paling perlu mereka dengar. Kalimatnya sengaja sejalan dengan yang sudah dipakai `/langganan` (`[BL-055]`(c)): dua jawaban berbeda atas pertanyaan yang sama membuat tenant bertanya mana yang benar.
- **Jalur "tidak melakukan apa-apa" disebut lebih dulu, berikut isinya.** Diam tetap sebuah pilihan, dan kartu Harga Tetap menyebut nama paket tujuannya, tarifnya, dan bahwa data penjualan tenant tetap tertutup. Peringatan yang hanya berkata "masa coba Anda akan habis" memberi tahu tanpa memberi jalan.

- **Cacat yang ditemukan saat mengerjakannya, dan ikut diperbaiki: perkiraan adaptif dibandingkan terhadap Rp 0.** `SubsidyEstimator::estimateFor()` menerima "tarif sekarang" sebagai pembanding, dan kedua halaman langganan mengirimkan `effectivePrice()` — yang bagi tenant masa coba adalah **nol**. Akibatnya `is_cheaper` selalu `false` dan setiap tenant masa coba dijawab "Harga Tetap masih lebih menguntungkan": dibandingkan dengan gratis, memang selalu. Itu jatuh tepat pada satu-satunya kelompok yang sedang diminta memilih jalurnya. Pembandingnya kini `SubscriptionService::comparisonPriceFor()` — tarif paket yang akan dihuni tenant setelah masa coba, atau tarif berjalannya bila tidak ada perpindahan yang menunggu. Diperbaiki di **ketiga** pembacanya sekaligus (dashboard, `/langganan`, `/langganan/harga-adaptif`), karena satu layar yang diperbaiki sendirian akan berselisih dengan dua yang lain.
- **`postTrialPlanFor()` pindah dari controller ke service.** Ia semula `protected` di `Billing\SubscriptionController` dan hanya melayani satu prop; sekarang tiga pembaca menanyakannya — halaman langganan, kartu pilihan, dan pembanding tarif di atas. Tiga salinan pasti bercabang pada hari pertama salah satunya diperbaiki. Angkanya sengaja `base_price` paket tujuan dan **bukan** `PricingService::resolveFor()`: tenant masa coba masih duduk di paket gratis, jadi resolver menjawab tarif paket itu — Rp 0 — dan menjanjikannya sebagai harga bulan depan berarti berbohong tepat pada layar yang meminta tenant memutuskan.
- **Perkiraan tarif hanya dihitung bila jalurnya memang terbuka.** Untuk tenant yang ditolak, perkiraan bukan informasi melainkan tawaran yang akan ditolak — dan ia menyeret satu agregat penjualan ke **tiap** pemuatan dashboard tanpa ada yang membacanya. Urutannya mengikuti `adaptiveVerdict()`: yang paling murah dan paling pasti didahulukan.
- **Tanpa paket tujuan, kartunya tidak muncul sama sekali.** Menyebut "masa coba Anda akan habis" tanpa bisa menyebutkan menjadi apa hanya menakuti tanpa memberi jalan. Salah setel platform ditagih ke pemilik SaaS lewat peringatan `graduateExpiredTrials()`, bukan ke tenant — konsisten dengan keputusan yang sama di `[BL-052]`(c).
- **File Terdampak:**
  - `config/subscription.php` — `trial_choice_lead_days` = 14, **baru**, berikut docblock yang menjelaskan kenapa ia wajib > `invoice_lead_days`.
  - `app/Services/SubscriptionService.php` — `trialChoice()`, `postTrialPlanFor()`, `comparisonPriceFor()`, `trialChoiceLeadDays()`; `SubsidyEstimator` masuk sebagai dependensi konstruktor.
  - `app/Http/Controllers/Owner/DashboardController.php` — prop `subscription.trial_choice`.
  - `app/Http/Controllers/Billing/SubscriptionController.php` — `postTrialPlanFor()` didelegasikan ke service (metode lokalnya dihapus); pembanding estimasi diperbaiki.
  - `app/Http/Controllers/Billing/AdaptiveController.php` — pembanding estimasi diperbaiki.
  - `resources/js/Pages/Owner/Dashboard.vue` — kartu dua jalur di dalam ringkasan langganan, plus kalimat penjelas tiap keadaan.
  - `tests/Feature/Subscription/TrialChoiceTest.php` — **baru.** 9 test.
- **Cara memeriksanya.** 286 test di `tests/Feature/Subscription` lewat, 9 di antaranya baru. Yang dikunci test barunya: jendelanya terbuka H-14 dan tertutup di H-15; `first_invoice_issued` berbalik tepat di H-7; tenant di atas ambang mendapat satu jalur berikut `reason` dan `ceiling`-nya, dan **tanpa** perkiraan; tanpa paket tujuan prop-nya `null`; tenant di luar masa coba prop-nya `null`; dan pembanding estimasi bernilai Rp 100.000 — bukan Rp 0 — di dashboard **dan** di `/langganan`, dua-duanya, supaya perbaikannya tidak bisa lepas di satu sisi saja.
- **Yang TIDAK dikerjakan:** kartunya belum pernah dilihat di layar sungguhan — kedua tenant di basis data dev berstatus `active`, jadi tidak ada yang jatuh di dalam jendelanya. Dengan `[BL-044]` tertutup, tarif jalur Harga Tetap sendiri masih menunggu `[BL-041]`(a), dan pemulihan tenant `suspended` masih terbuka di `[BL-051]`.

---

### [ADDITION] Halaman Daftar Menyebut Masa Gratis yang Berakhir dengan Tagihan (BL-071)
- **Tanggal:** 2026-08-15
- **Fase Terkait:** Di Luar Fase — `[BL-071]`, plus sisa `[BL-032]` (CTA landing yang masih menunjuk `/login`).
- **Dampak:** Backend | Frontend | Landing | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** Halaman `/register` kini menyebut lama masa gratis, paket tujuan sesudahnya beserta tarifnya, dan kapan tagihan pertama terbit — sebelum tombol "Daftar" ditekan. CTA pendaftaran di landing berhenti berbunyi "Daftar Gratis" dan berganti "Coba Gratis N Bulan".
- **Alasan:** Sejak `[BL-052]` masa gratis berakhir dengan **perpindahan otomatis** ke paket berbayar, dan tagihan pertamanya terbit tujuh hari sebelum masa gratis habis. Halaman `/langganan` sudah mengatakan itu — tapi baru **setelah** orang punya akun. Di titik keputusan yang sebenarnya, halaman daftar, tidak ada satu pun kata "gratis", "coba", "bulan", "paket", atau "Rp". Yang mendaftar menerima "Daftar Gratis" dari landing, mengisi lima kolom, dan sebagian baru tahu ada tarif menunggunya ketika tagihan pertamanya datang.

- **Tiga keputusan pemilik 2026-08-15 yang menentukan bentuknya.** Entri backlognya sengaja menahan pekerjaan sampai ketiganya dijawab, karena jawaban yang berbeda menghasilkan kode yang berbeda, bukan kalimat yang berbeda:
  1. **Kalimat informatif, bukan kotak centang.** Pemberitahuannya panel di atas tombol Daftar; tidak ada yang dicatat. Persetujuan jalur harga tetap hidup terpisah di `/langganan/persetujuan` seperti sebelumnya. Konsekuensinya jelas dan diterima: tidak ada bukti per-orang bahwa pemberitahuan ini dibaca — bila suatu saat itu dibutuhkan, ia menjadi tipe `TenantConsent` baru, bukan tambalan pada halaman daftar.
  2. **Paket tidak dipilih saat mendaftar.** Semua tenant baru tetap masuk lewat `free` dan masa gratisnya; halaman daftar hanya **memberitahu** paket tujuannya. `is_post_trial_target` karenanya tetap penanda **global** milik pemilik SaaS, bukan pilihan per tenant — dan `startTrial()` tidak disentuh sama sekali.
  3. **"Daftar Gratis" diganti.** Ia tidak salah — dua bulan memang gratis — tapi ia menyembunyikan bagian yang paling menentukan.
- **Angkanya dibacakan, bukan disalin — dan itulah inti entri ini.** `PublicPricing::trialNotice()` menjadi satu-satunya sumbernya: `SubscriptionService::trialMonths()` untuk lamanya, `config('subscription.invoice_lead_days')` untuk jarak terbit tagihan, dan `Plan::postTrialTarget()` untuk paket tujuannya. Ketiganya memang dibuat bisa berubah tanpa deploy — panjang masa gratis dan jarak tagihan di config, penanda paket tujuan di panel platform. Halaman daftar yang menuliskan "2 bulan, lalu Rp 100.000" sebagai teks mati akan berbohong pada hari salah satunya digeser.
- **Keadaan "belum ada paket tujuan" punya jawabannya sendiri, dan bukan diam.** `post_trial_plan` bernilai `null` untuk dua hal yang berakibat sama: pemilik SaaS belum menandai paket mana pun, atau yang ditandai justru paket gratis itu sendiri — persis yang ditolak `SubscriptionService::graduateExpiredTrials()`, jadi memang tidak akan ada perpindahan yang bisa diumumkan. Dalam keadaan itu halaman daftar tetap berbicara, hanya tidak menyebut paket: "Ini masa coba, bukan paket gratis selamanya." Yang dihindari dua-duanya — kalimat setengah jadi, dan janji gratis tanpa ujung.
- **Sisa `[BL-032]` ikut ditutup karena barisnya memang sedang disentuh.** Empat CTA pendaftaran di landing (hero, nav seluler, ajakan di bagian perbandingan, dan CTA penutup) masih menunjuk `/login` padahal `/register` sudah ada. Tombol nav seluler dulu satu tombol berbunyi "Login / Daftar Gratis" untuk satu tujuan; sekarang dua tombol untuk dua tujuan. Yang tersisa menunjuk `/login` hanya yang memang berlabel "Login" dan "Masuk", dan sebuah test menjaga itu tetap begitu.
- **Yang TIDAK dikerjakan, dan sengaja:** `business_type` tetap `nullable` (butir (d) entri backlognya) — ia dimensi harga sungguhan sekarang, tapi mewajibkannya adalah perubahan alur pendaftaran tersendiri, bukan tempelan pada pekerjaan ini. Dicatat ulang sebagai `[BL-079]`.
- **File Terdampak:**
  - `app/Services/Pricing/PublicPricing.php` — `trialNotice()` **baru**; satu pembaca untuk halaman daftar, sekeluarga dengan `snapshot()` yang sudah melayani `/harga` dan landing.
  - `app/Http/Controllers/Auth/AuthController.php` — `showRegister()` menerima `PublicPricing` dan mengirim prop `trial`.
  - `resources/js/Pages/Auth/Register.vue` — panel pemberitahuan di atas tombol Daftar; kalimatnya disusun di `computed`, angkanya dari prop.
  - `resources/views/public/landing.blade.php` — empat CTA berpindah ke `route('register')`, labelnya menyebut lama masa gratis dari `$pricing['trial_months']`.
  - `tests/Feature/Auth/RegisterTrialNoticeTest.php` — **baru**, 5 test. Yang terpenting: mengubah penanda paket tujuan **lewat endpoint panel** lalu memastikan halaman daftar ikut menyebut paket yang baru (butir (c) entri backlognya).
  - `tests/Feature/Public/LandingPricingTest.php` — 2 test tambahan: CTA menyebut lama masa gratis dari config, dan tak ada lagi tautan `/login` selain yang memang berlabel masuk.

---

### [HOTFIX] Avatar Testimoni Turun dari 1,8 MB Jadi 5 KB — dan Ternyata JPEG Berekstensi `.png` (BL-032 butir 3)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-032]` butir (3), bagian avatarnya.
- **Dampak:** Frontend | Aset | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** Tiga avatar testimoni turun dari ±600 KB masing-masing jadi ±2 KB, sebagai WebP 96 piksel.
- **Alasan:** Ketiganya berukuran 1024×1024 dan dirender `w-12 h-12` — 48 piksel. Digabung 1,8 MB, lebih berat dari seluruh tangkapan layar landing dijumlahkan.

- **Berkasnya ternyata JPEG, bukan PNG, dan itu separuh penjelasan bobotnya.** `avatar_andi.png` dan dua saudaranya berawalan `FF D8 FF E0 ... JFIF` — JPEG dengan ekstensi `.png`. Ketahuan karena `imagecreatefrompng()` menolak membukanya. Konversinya karena itu memakai `imagecreatefromstring()`, yang mendeteksi formatnya sendiri.
- **Dikerjakan dengan GD bawaan PHP, tanpa menambah dependensi.** ImageMagick tidak terpasang, `cwebp` tidak ada, `sharp` bukan bagian `node_modules`. Yang tersedia adalah ekstensi `gd` PHP dengan dukungan WebP menyala. Perlu dicatat sebagai jebakan lingkungan: di Windows, `convert` yang ada di `PATH` **bukan** ImageMagick melainkan utilitas konversi sistem berkas bawaan Windows — menjalankannya dengan argumen gambar bukan sekadar gagal.
- **96 piksel untuk kotak 48 piksel**, supaya tetap tajam di layar kepadatan ganda. Atribut `width`/`height` dan `loading="lazy"` ikut dipasang: yang pertama mencegah pergeseran tata letak saat gambarnya menyusul, yang kedua menahan ketiganya sampai bagian testimoni benar-benar didekati.
- **Berkas PNG lamanya dihapus, bukan ditinggalkan.** Ia tidak lagi dirujuk siapa pun, dan membiarkan 1,8 MB menganggur di `public/` mengalahkan seluruh tujuan butir ini. Riwayat git tetap memegangnya bila suatu saat dibutuhkan.
- **File Terdampak:**
  - `public/avatar_{andi,budi,santi}.webp` — **baru**, 1,9 / 1,5 / 1,9 KB.
  - `public/avatar_{andi,budi,santi}.png` — **dihapus**, ±600 KB masing-masing.
  - `resources/views/public/landing.blade.php` — rujukannya berpindah, plus `width`/`height`/`loading`.
  - `tests/Feature/Public/LandingClaimsTest.php` — satu test: rujukannya `.webp`, berkasnya ada dan di bawah 15 KB, dan `.png`-nya benar-benar hilang.
- **Cara memeriksanya.** 65 test di `tests/Feature/Public` lewat. Hasil konversinya juga dibuka dan dilihat — 96 piksel, wajahnya masih terbaca, bukan sekadar berkas yang ukurannya benar.
- **Yang TIDAK dikerjakan:** bagian tangkapan layar `[BL-032]`(3) — kelimanya masih tertanggal 25 Mei. Entrinya tetap **Open** untuk itu.

---

### [HOTFIX] Landing Berhenti Menjanjikan Login Google, Katalog Otomatis, dan "Ribuan UMKM" (BL-032 butir 1)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-032]` butir (1).
- **Dampak:** Frontend | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** Daftar fitur di landing ditulis ulang dari kapabilitas yang benar-benar ada, dan empat klaim yang tidak didukung apa pun dibuang.
- **Alasan:** Entri ini semula berbunyi "tulis ulang daftar fitur dari kapabilitas yang benar-benar ada" — masalah kelengkapan. Yang ditemukan saat mengerjakannya lebih tajam: sebagian isinya bukan sekadar usang melainkan **tidak pernah ada**.

- **Empat klaim dibuang karena salah, bukan karena usang — masing-masing diperiksa ke kode lebih dulu.**
  1. **"Cukup hubungkan akun Google Anda"** beserta tombol "Lanjutkan dengan Google" yang dimockup lengkap dengan logonya. Tidak ada Socialite di `composer.json`, tidak ada rute OAuth, dan `AuthController@register` hanya menerima email + kata sandi. Ini bukan fitur usang; ini jalur masuk yang tidak pernah ada.
  2. **"SAPI otomatis membuatkan kategori produk, daftar menu populer, dan saran stok awal."** `register()` membuat tepat tiga hal: tenant, langganan masa coba, dan user owner. Tidak ada satu baris katalog pun disemai — `[BL-034]` justru mencatat kebalikannya, bahwa tiap tenant baru mendarat di aplikasi paling kosongnya.
  3. **"AI SAPI akan langsung mengenali kebutuhan bisnis Anda"** dari kategori usaha. `business_type` tidak menyentuh satu pun `*_enabled`; ia hanya dipakai penetapan harga (`[BL-034]`).
  4. **"digunakan ribuan UMKM."** Basis datanya berisi dua tenant, keduanya demo.
- **Yang menggantikannya adalah yang memang bisa ditunjuk barisnya.** Kartu fiturnya kini menyebut split bill, modifier per item, tagihan terbuka di topbar kasir, sesi kas dengan ringkasan selisih, stok per varian berikut riwayat dan opname, papan antrian dapur, saran jual dari sinyal stok, kuota AI yang terlihat sisanya, BYOK, dan RBAC per modul. Langkah pendaftarannya menyebut apa yang benar-benar terjadi: empat isian, verifikasi email, masa gratis dua bulan yang berakhir dengan perpindahan otomatis, lalu katalog yang disusun sendiri.
- **Kapabilitas yang berbentuk API disebut sebagai API, bukan sebagai layar.** Pesan mandiri (`api/orders`), POS mobile (`api/mobile/*`), dan MCP semuanya nyata — dan tidak satu pun punya halaman bawaan. `[BL-032]` mengeluh landing tidak menyebutnya sama sekali; menyebutnya seolah fitur yang tinggal dibuka akan mengulang persis kesalahan yang entri ini bersihkan. Kartunya menyebutnya antarmuka program dan menaut ke `/api-docs`.
- **Testimoni pelanggan sengaja TIDAK disentuh.** Tiga kutipan bernama (Andi, Santi, Budi) berdiri di atas potret stok dan kemungkinan besar karangan juga — tapi menghapus testimoni adalah keputusan pemasaran pemilik, bukan konsekuensi teknis dari butir ini. Dicatat sebagai `[BL-078]`.
- **File Terdampak:**
  - `resources/views/public/landing.blade.php` — tiga kartu fitur ditulis ulang, satu kartu API ditambahkan, empat langkah pendaftaran dibetulkan, mockup tombol Google dibuang.
  - `tests/Feature/Public/LandingClaimsTest.php` — **baru.** 5 test yang mengunci klaim-klaim itu tidak kembali, dan mengunci sebutan kapabilitasnya tetap ada.
- **Cara memeriksanya.** 65 test di `tests/Feature/Public` lewat. Test-nya sengaja mengunci **sebutan**, bukan kalimatnya: menulis ulang copy-nya tidak akan menggagalkan test, sementara menghidupkan kembali janji Google atau katalog otomatis akan.
- **Yang TIDAK dikerjakan:** `[BL-032]` butir (3) bagian tangkapan layar masih tertunda; avatar sudah beres di entri terpisah.

---

### [ADDITION] Tenant Jalur Harga Tetap Akhirnya Bisa Melihat Kelasnya Sendiri (BL-041 butir b)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-041]` butir (b), butir terakhir entri itu. Commit ketujuh dan penutup jalan permukaan publik.
- **Dampak:** Service | Controller | Frontend | Test
- **Breaking Change:** Tidak. Kunci payload baru; tidak ada yang berubah bentuk.
- **Deskripsi:** Halaman `/langganan` kini menyebut kelas harga tenant jalur Harga Tetap beserta dasarnya — dan menyatakan terus terang bahwa kelas itu ditentukan tanpa melihat penjualannya.
- **Alasan:** `bracket` hanya terisi bila `isSubsidized()`, sehingga tenant bayar-penuh tidak punya penjelasan apa pun mengapa tarifnya sekian. Mereka justru kelompok yang paling berhak atas penjelasan itu: mereka tidak membuka data apa pun, jadi angkanya tidak bisa mereka telusuri sendiri.

- **Mesinnya ternyata sudah ada seluruhnya — yang kurang cuma jalan ke layar.** Sejak `[BL-015]`, `resolveFor()` mencocokkan SELURUH dimensi, bukan omzet saja; dan `DimensionRegistry::valueFor()` sudah memadamkan dimensi ber-consent bagi tenant yang tidak menyetujuinya. Artinya aturan berdimensi seat aktif atau tipe usaha **sudah** berlaku bagi tenant jalur Harga Tetap sejak lama, tanpa satu pun dari mereka bisa melihatnya. Yang ditambahkan karenanya bukan mesin baru melainkan satu pembaca (`PricingService::classificationFor()`) dan satu kartu.
- **Batas privasinya ditegakkan DUA lapis, dan lapis kedua bukan berlebihan.** Lapis pertama sudah ada: dimensi ber-consent bernilai `null` bagi tenant yang tidak menyetujuinya. Lapis kedua ada di `classificationFor()` sendiri — `basis` hanya memuat dimensi yang `requires_consent`-nya `null`, disaring lewat `DimensionRegistry::consentFreeNames()`. Tanpa lapis kedua, metode ini akan membocorkan omzet begitu ia dipanggil untuk tenant yang **sudah** menyetujui: lapis pertama berhenti melindungi persis pada saat itu. Ada test yang menyetujui consent lebih dulu lalu memastikan omzet tetap tidak muncul di `basis` — bukan mengandalkan `null` yang kebetulan.
- **Kunci `bracket` TIDAK dipakai ulang meski entrinya menulis "perluas `bracket`".** Bentuknya memuat `period` dan `revenue` yang tidak berlaku di jalur Harga Tetap, dan `Billing/Show.vue` membacanya sebagai "Dihitung dari omzet ...". Memaksakannya berarti kartu yang berbunyi "Dihitung dari omzet null", atau null-guard di setiap barisnya. Kunci barunya `classification`, dengan bentuknya sendiri.
- **`classification` `null` untuk tenant jalur Harga Adaptif, dan itu disengaja.** Mereka sudah punya `subsidy.bracket` lengkap dengan omzet yang mendasarinya. Dua kartu yang menjawab pertanyaan sama dengan angka sama hanya membuat pembacanya bertanya mana yang benar — pola yang sama dengan `bracket` vs `estimate` yang sudah berlaku di halaman itu.
- **`rule` dan `plan` dibedakan, tidak dilebur.** "Tarif Anda berlaku karena aturan X cocok" dan "tidak ada aturan yang cocok, jadi yang berlaku tarif paket" adalah dua jawaban berbeda atas pertanyaan yang sama. Yang kedua bukan kegagalan dan pantas disebut namanya — tanpa itu, tenant yang tarifnya memang datang dari paket akan melihat kartu kosong dan mengira sistemnya rusak.
- **Nilai atribut diterjemahkan di server, bukan di Vue.** Peta `options` (`kuliner` → "Kuliner / F&B") tinggal di `config/pricing-dimensions.php`; menyalinnya ke frontend berarti dua daftar tipe usaha yang harus diingat untuk diubah bersama. Payload mengirim `display` yang sudah jadi, dan `null` bila nilainya memang sudah terbaca apa adanya.
- **File Terdampak:**
  - `app/Services/Pricing/DimensionRegistry.php` — `consentFreeNames()`, satu-satunya definisi "aman dibacakan kepada tenant jalur Harga Tetap".
  - `app/Services/PricingService.php` — `classificationFor()`.
  - `app/Http/Controllers/Billing/SubscriptionController.php` — kunci `classification`, terisi hanya untuk jalur Harga Tetap.
  - `resources/js/Pages/Billing/Show.vue` — kartu kelas harga, tepat di bawah tarif yang ia jelaskan.
  - `tests/Feature/Subscription/FixedTrackClassificationTest.php` — **baru.** 7 test.
- **Cara memeriksanya.** 496 test di `tests/Feature/Subscription`, `tests/Feature/Platform`, dan `tests/Feature/Public` lewat. Bentuk payload-nya dijaga dua test Inertia — terisi untuk jalur Harga Tetap, `null` untuk jalur Harga Adaptif. **Kartunya sendiri belum pernah dilihat di peramban**: `/langganan` ada di balik login, dan memasukkan kredensial bukan sesuatu yang saya lakukan. Yang membuktikannya berjalan adalah test payload dan build frontend yang lolos, bukan tangkapan layar.
- **Yang TIDAK dikerjakan:** aturan tarif berdimensi seat/tipe usaha belum benar-benar ada di basis data mana pun — kartu ini akan berbunyi "tarif paket" sampai pemilik SaaS menerbitkan satu. Itu keputusan komersial, bukan pekerjaan kode, dan sengaja tidak ditebak di sini.

---

### [ADDITION] Landing Menjelaskan Cara Tarif Dihitung — dan Berhenti Menjanjikan Penguncian yang Tidak Pernah Ada (BL-066)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-066]` butir (a), (c), (d), (e) utuh; butir (b) dua dari tiga, satu klaimnya dibatalkan karena tidak benar. Commit keenam di jalan permukaan publik.
- **Dampak:** Frontend | Dokumentasi | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** Landing dapat bagian "Bagaimana Harga Anda Dihitung" — tiga langkah, tangga kelas dengan angkanya, ambang kelayakan, dan tiga hal yang **tidak** dilakukan platform. Panduan langganan diperdalam jadi versi panjangnya dan ditautkan dari sana.
- **Alasan:** Mekanisme inilah pembeda produk ini, dan sampai kemarin tidak satu kata pun tentangnya ada di permukaan publik. Penjelasan yang benar sudah lama ada — di dokumen pitching internal, bukan di halaman yang dibaca orang. Sejak `[BL-055]` tenant mengajukan Harga Adaptif **sendiri** sambil menyerahkan consent atas data penjualannya; meminta orang menyetujui itu tanpa halaman publik yang menjelaskannya adalah cara tercepat membuat pengajuan itu ditolak, atau lebih buruk, disetujui tanpa dipahami.

- **Satu dari tiga kalimat yang diminta butir (b) TIDAK ditulis, karena tidak benar — dan ini temuan paling penting dari commit ini.** Butir itu meminta menuliskan "tarif yang sudah dibayar terkunci (`price_locked`)". Diperiksa terhadap kode sebelum ditulis: penerbit tagihan **tidak pernah** membaca `price_locked`. `issueDuePeriodInvoices()` selalu menghitung ulang lewat `PricingService::resolveFor()` (`SubscriptionService.php:486`); `price_locked` hanya dibaca untuk tampilan layar (`Subscription::effectivePrice()`, `AccountOverview`). `[BL-041]` sudah mengoreksi klaim *grandfathering* ini pada 2026-08-07 — entri `[BL-066]` rupanya ditulis sebelum koreksi itu dan tidak ikut diperbarui. Menuliskannya di landing berarti menjanjikan yang tidak dilakukan sistem: tenant yang kelasnya naik bulan depan **akan** ditagih tarif kelas barunya.
- **Yang ditulis sebagai gantinya benar dan bisa diperiksa:** tagihan yang **sudah terbit** membekukan dasar perhitungannya di `invoices.pricing_context`, jadi ia tidak berubah surut, dan aturan tarif baru hanya berlaku ke depan. Ada test yang menjaga janji penguncian itu tidak masuk kembali lewat penyuntingan berikutnya.
- **Dua kalimat lain di butir (b) diperiksa dan ternyata benar.** "Platform tidak mengintip transaksi per item": `tenant_monthly_metrics` hanya menyimpan `revenue` dan `transaction_count` per bulan — tidak ada kolom lain, dan `MonthlyRevenueResolver` membaca **hanya** dari tabel ringkasan itu, tidak pernah dari `transactions`. "Tidak ada laporan mandiri yang bisa dicurangi": angkanya ditulis job `ComputeTenantMonthlyRevenue`, tidak ada satu pun kolom yang diisi tenant.
- **Istilahnya "Harga Adaptif" di mana-mana, dan "dynamic pricing" tidak muncul sama sekali (`[BL-066]`(e)).** Tabrakan istilah yang diperingatkan entrinya nyata: `[BL-018]` memakai "harga dinamis" untuk diskon barang mendekati kedaluwarsa — hal yang sama sekali berbeda. Ada test yang menyapu `/` dan `/harga` untuk ketiga variannya.
- **Angkanya dari `pricing_rules`, dan test-nya membuktikan itu bukan kebetulan.** Satu test mengubah harga sebuah bracket lalu memuat ulang landing dan memastikan angka lamanya benar-benar hilang. Halaman yang menjelaskan mekanisme dengan angka yang berbeda dari mesinnya lebih buruk daripada halaman yang diam.
- **Tanpa satu pun aturan tarif, seluruh bagian ini hilang.** Penjelasan tentang tangga yang tidak ada hanya menjanjikan jalur yang tidak bisa diambil siapa pun.
- **Panduan langganan diperdalam — dan tiga fakta usang di dalamnya ikut dibetulkan.** `[BL-066]`(d) meminta memperdalamnya sebagai versi panjang, dan entrinya sendiri menandai isinya "perlu diperiksa ulang terhadap keputusan 2026-08-07". Yang ditemukan: (1) masa coba tertulis "30 hari pertama", padahal `trial_months` = **2 bulan**; (2) masa tenggang digambarkan memutus penyimpanan transaksi sejak hari pertama, padahal sejak `[BL-054]` ia bertingkat dan kasir baru berhenti di sepertiga terakhir; (3) istilahnya masih "jalur normal"/"jalur subsidi UMKM", yang sudah diganti sejak 2026-07-31. Ketiganya dibetulkan, dengan satu baris yang menyebut istilah lamanya supaya pembaca yang mengingatnya tidak tersesat. Klaim "harga yang Anda setujui terkunci" di bagian "Tarif berubah?" juga dibetulkan dengan alasan yang sama seperti di atas.
- **File Terdampak:**
  - `resources/views/public/landing.blade.php` — bagian `#harga-adaptif`: tiga langkah, tangga kelas dari `pricing_rules`, ambang, tiga kartu "yang tidak terjadi", dan tautan ke panduan.
  - `resources/docs/panduan/langganan.md` — tiga bagian baru (cara tarif dihitung, arah turun-naiknya, ambang), istilah disatukan, dan tiga fakta usang dibetulkan.
  - `tests/Feature/Public/AdaptivePricingExplainerTest.php` — **baru.** 12 test.
- **Cara memeriksanya.** 60 test di `tests/Feature/Public` lewat. Landing juga dibuka di peramban: tangga terbaca 10k/25k/50k/75k dengan ambang Rp 50 jt — sama dengan keputusan pemilik 2026-08-07 — dan pada 417px ketiga grid-nya menumpuk jadi satu kolom tanpa halaman menggeser ke samping.
- **Yang TIDAK dikerjakan:** tabrakan istilah dengan `[BL-018]` belum **diselesaikan**, hanya dihindari — permukaan publik konsisten memakai "Harga Adaptif", tapi keputusan nama untuk diskon kedaluwarsa masih menggantung. Tersisa satu entri di jalan ini: `[BL-041]` butir (b).

---

### [ADDITION] Isi Paket Terlihat Sebelum Orang Mendaftar, dan Berhenti Terpecah Dua di Dalam Aplikasi (BL-067)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-067]` butir (a)–(e). Commit kelima di jalan permukaan publik.
- **Dampak:** Controller | Frontend | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** Berapa pengguna dan berapa analisis AI yang didapat sebuah paket kini terbaca di `/harga` dan di landing — sebelum orang mendaftar — dan di `/langganan` keduanya duduk di satu blok, bukan terpecah antara dua halaman.
- **Alasan:** Datanya sudah lengkap dan rapi di `plans` sejak lama; yang tidak ada adalah satu pun permukaan yang memperlihatkannya. Calon klien memilih paket tanpa tahu berapa kasir yang boleh dipakainya; pendaftar baru menemukan batasnya saat menambah staf ketiga dan ditolak.

- **Istilahnya "pengguna", BUKAN "seat" — dan ini penyimpangan yang disengaja dari bunyi harfiah entrinya.** `[BL-067]`(c) meminta memakai "seat bawaan paket" vs "seat tambahan" sesuai keputusan 2026-08-07, dan menutup dengan "jangan memperkenalkan kata ketiga di permukaan publik". Kedua syarat itu ternyata bertabrakan: pencarian di seluruh layar tenant menemukan kata "seat" **nol kali** sebagai teks yang dibaca orang — `/langganan` menulis "Pengguna tambahan", "kursi", dan "N dari paket X". Memakai "seat" di halaman publik justru akan **menjadi** kata ketiga yang dilarang kalimat penutupnya. Yang dipakai karenanya "Pengguna termasuk" / "Pengguna tambahan", menyalin label yang sudah ada di `Billing/Show.vue`. Pasangan konsepnya — bawaan paket vs tambahan — tetap persis seperti yang diputuskan.
- **Kuota AI di `/langganan` memakai `AiQuotaMeter` varian `compact`, bukan `detailed` — dan alasannya bukan selera.** Peran halaman ini lebih dekat ke Pengaturan, jadi `detailed` yang tampak benar. Tapi teks varian itu berbunyi "Kosongkan kolom API Key **di bawah**" dan "Isi kunci API Anda sendiri **di bawah**" — benar di Pengaturan, karena kolomnya memang ada di sana, dan menunjuk ke ruang kosong di halaman langganan. Varian `compact` tidak terikat tempat, dan jalan keluarnya berupa **tautan** ke halaman kredensial. Ini ditemukan saat membaca komponennya, bukan setelah dipasang.
- **Satu pembaca, bukan hitungan ketiga.** `SubscriptionController` memanggil `AiQuota::snapshotFor()` — kelas yang sama yang dipakai Pengaturan dan AI Analysis. Angka yang dibacakan di tiga layar wajib identik, dan tiga tempat yang menghitung sendiri-sendiri adalah cara termudah membuatnya tidak.
- **`ai_daily` null tetap dibaca "ikut bawaan platform" di kedua permukaan publik.** Pembedaan ini sudah dijaga `PublicPricing` dan `Plan::limit()`; di sini ia akhirnya sampai ke layar. Ada test yang memastikan tidak ada permukaan yang menuliskan "0 analisis" untuk paket yang sebenarnya dapat jatah.
- **Konsekuensi kuota habis ditulis terus terang (`[BL-067]`(d)).** "Bila habis, analisis berikutnya ditolak sampai besok — fitur lain di aplikasi tidak ikut berhenti", plus BYOK sebagai jalan keluar. Kalimat kedua itu yang menentukan: batas yang tidak dijelaskan konsekuensinya akan dibaca sebagai batas keras yang memutus seluruh aplikasi.
- **Tidak ada janji membeli kuota AI di mana pun (`[BL-067]`(e)), dan itu dijaga test.** Alur belinya belum berbentuk sama sekali — tidak ada kolom, tidak ada tagihan, tidak ada layar; `[BL-069]` masih terhalang keputusan harga. Test-nya menyapu `/` dan `/harga` sekaligus, supaya janji itu tidak masuk diam-diam lewat penyuntingan berikutnya.
- **File Terdampak:**
  - `resources/views/public/pricing.blade.php` — tabel jalur Harga Tetap dapat tiga kolom baru (pengguna termasuk, analisis AI/hari, pengguna tambahan) plus catatan konsekuensi kuota.
  - `resources/views/public/landing.blade.php` — tiap kartu paket dapat daftar isi paketnya, dari `plans`.
  - `app/Http/Controllers/Billing/SubscriptionController.php` — mengirim `aiQuota` dari `AiQuota::snapshotFor()`.
  - `resources/js/Pages/Billing/Show.vue` — meteran kuota AI masuk ke blok yang sama dengan kursi.
  - `tests/Feature/Public/PlanContentsTest.php` — **baru.** 7 test, termasuk penjaga istilah dan penjaga janji-kuota.
- **Cara memeriksanya.** 48 test di `tests/Feature/Public` dan 278 di `tests/Feature/Subscription` + `SettingsAiTest` lewat. Kedua permukaan publik juga dibuka di peramban: tabel `/harga` memajang 2/5, 3/15, 5/30, 10/60 dengan seat tambahan 20k/15k/12,5k/10k — sama persis dengan keputusan pemilik 2026-08-07 — dan pada 390px halaman tidak ikut menggeser ke samping karena tabelnya bergulir di dalam kotaknya sendiri.
- **Yang TIDAK dikerjakan:** `[BL-069]` (kuota AI yang bisa dibeli) tidak disentuh dan sengaja tidak dijanjikan. Daftar **kapabilitas** per paket — fitur apa yang menyala di paket mana — juga masih belum ada; itu `[BL-032]` butir (1), dan sumber datanya memang belum ada (`plans.limits` memuat batas, bukan kapabilitas).

---

### [ADDITION] Halaman `/harga` Membacakan Kedua Jalur Tarif kepada Orang yang Belum Mendaftar (BL-041 butir c)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-041]` butir (c). Commit keempat di jalan permukaan publik; pemakai kedua `PublicPricing`.
- **Dampak:** Controller | Route | Frontend | Test
- **Breaking Change:** Tidak. Rute baru, tidak ada yang berubah perilakunya.
- **Deskripsi:** `/harga` berdiri: tarif jalur Harga Tetap per paket, tangga Harga Adaptif beserta rentang omzet dan ambangnya, perbandingan tegas dua jalur, dan syarat perpindahannya — semuanya dibaca dari `plans` dan `pricing_rules`. Landing menautkannya.
- **Alasan:** Mesin "kelas sesuai omzet" sudah berjalan lama, dan sejak `[BL-055]` tangganya bisa dilihat tenant di `/langganan/harga-adaptif`. Yang tidak pernah ada adalah halaman untuk orang yang **belum** mendaftar — padahal merekalah yang harus memutuskan apakah jalur ini masuk akal baginya.

- **Menumpang kerangka `/dokumentasi`, bukan menulis kerangka keempat.** `[BL-033]` baru saja menyatukan tiga permukaan publik; menambahkan halaman kelima dengan gaya sendiri akan membatalkan pekerjaan itu di hari yang sama. Kerangka docs dibuat sedikit lebih umum untuk menampungnya: judul topbar, tautannya, dan sufiks `<title>` jadi `@yield` bersuku cadang bawaan — halaman dokumentasi yang ada tidak berubah sama sekali karena bawaannya persis nilai lamanya.
- **Kata-kata rentang omzet disalin persis dari `Billing/Adaptive.vue:49-52`, bukan disusun ulang.** Versi pertama saya menulis "Di bawah Rp 2.000.000" untuk bracket A dan "Rp X – Rp Y" untuk sisanya. Itu **berbeda** dari yang sudah dibaca tenant di halaman langganan, yang menulis "Rp 0 – di bawah Rp 2.000.000". Dua halaman yang menjelaskan tangga yang sama tidak boleh menyebut rentang yang sama dengan dua cara berbeda, jadi yang publik yang mengalah. Termasuk cabang `Semua omzet` yang semula saya lewatkan.
- **Ambang hanya disebut bila memang ada.** `ceiling` null berarti tangganya tidak berujung, bukan ambang nol. Ada test yang memastikan halaman tidak menuliskan "di bawah Rp 0" — kalimat yang akan memberi tahu **setiap** pengunjung bahwa omzetnya terlalu tinggi untuk Harga Adaptif.
- **Tanpa satu pun aturan tarif, bagian Adaptif hilang seluruhnya — bukan jadi tabel kosong.** Halaman tetap berdiri dengan jalur Harga Tetap saja. Tabel tangga tanpa satu baris pun lebih buruk daripada tidak ada: ia menjanjikan jalur yang tidak bisa diambil siapa pun.
- **Perbandingannya menyebut apa yang diminta dari pembaca, bukan hanya apa yang ia dapat.** Kolom Harga Adaptif menuliskan terus terang bahwa jalur itu menuntut persetujuan eksplisit atas pemakaian total omzet bulanan, dan bahwa angkanya dihitung otomatis dari transaksi — bukan dari laporan yang diisi sendiri. Meminta orang menyetujui sesuatu tanpa lebih dulu bisa membacanya adalah cara tercepat membuat pengajuan itu ditolak, atau lebih buruk, disetujui tanpa dipahami.
- **Landing menaut, tidak menyalin.** Entri backlognya menulis "Landing menaut ke sana, menggantikan bagian harga yang sekarang" — dan "bagian harga yang sekarang" saat itu berarti dua paket karangan, yang sudah diganti data nyata beberapa jam sebelumnya. Jadi yang ditambahkan hanya tautannya; kartu di landing tetap menjawab "berapa", dan `/harga` menjawab sisanya. Memuat tangga bracket di landing berarti menulis tabel kedua yang harus dijaga tetap sama.
- **File Terdampak:**
  - `app/Http/Controllers/Public/PricingController.php` — **baru.** Satu aksi; seluruh angkanya dari `PublicPricing`.
  - `routes/web.php` — rute `/harga` bernama `pricing`, di kelompok rute publik.
  - `resources/views/public/pricing.blade.php` — **baru.** Hero, tabel jalur Harga Tetap, tangga Adaptif, perbandingan dua kolom, dan syarat perpindahan jalur.
  - `resources/views/public/docs/layout.blade.php` — judul topbar, tautannya, dan sufiks `<title>` jadi `@yield` bersuku cadang bawaan.
  - `resources/views/public/landing.blade.php` — tautan "Lihat rincian harga & jalur Harga Adaptif" di bawah kartu paket.
  - `tests/Feature/Public/PricingPageTest.php` — **baru.** 10 test.
- **Cara memeriksanya.** 41 test di `tests/Feature/Public` lewat. Halaman juga dibuka di peramban: tangga A–D terbaca dari `pricing_rules` dengan rentang dan tarif yang benar (10k/25k/50k/75k, ambang Rp 50 jt), judul tab "Harga — SAPI POS", dan pada 390px perbandingan dua kolomnya menumpuk jadi satu tanpa halaman ikut menggeser ke samping.
- **Yang TIDAK dikerjakan:** `[BL-041]` butir (b) — kelas harga untuk tenant jalur Harga Tetap di `/langganan` — belum disentuh, dan entrinya tetap **Open**. Halaman ini juga belum menyebut seat bawaan maupun kuota AI tiap paket; itu `[BL-067]`, commit berikutnya.

---

### [HOTFIX] Dua Paket Karangan di Landing Diganti Paket yang Benar-Benar Ditagihkan (BL-032 butir 2)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-032]` butir (2). Commit ketiga di jalan permukaan publik; pemakai pertama `PublicPricing` yang mendarat sejam sebelumnya.
- **Dampak:** Controller | Frontend | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** Bagian harga di landing memajang "Core POS Rp 149k" dan "Smart SAPI Rp 299k" — dua paket yang tidak pernah ada di sistem. Sekarang kartunya dibangun dari `plans` lewat `PublicPricing`, jadi yang dibaca calon klien adalah yang benar-benar akan ditagihkan.
- **Alasan:** Halaman harga yang berbeda dari tagihan sungguhan adalah cacat terburuk yang bisa dimiliki halaman harga. Calon klien yang membaca "Rp 299k/bulan" lalu mendaftar akan mendapati tagihan yang sama sekali lain — hari ini Rp 0 selama dua bulan, lalu Rp 100.000.

- **Yang dipajang sekarang adalah keempat paket aktif, bukan dua paket pilihan.** `free` Rp 0 · `paid-1` Rp 100.000 · `paid-2` Rp 150.000 · `paid-3` Rp 200.000, termurah lebih dulu. Paket nonaktif tersaring di `PublicPricing`, jadi paket yang berhenti dijual tidak akan diam-diam muncul kembali di halaman publik.
- **Paket yang disorot dipilih oleh data, bukan oleh selera.** Sorotannya menempel pada `is_post_trial_target` — paket yang benar-benar dihuni tenant setelah masa gratisnya habis. Label lamanya "Paling Populer" diganti "Paling Banyak Dipakai", karena yang pertama adalah klaim tentang pasar yang tidak dimiliki siapa pun di proyek ini, sementara yang kedua adalah pernyataan tentang mekanisme yang memang benar.
- **Daftar fitur per paket ikut hilang, dan itu disengaja.** Dua kartu lama memajang bullet fitur yang menempel pada paket karangan. Begitu paketnya datang dari `plans`, tidak ada satu pun sumber data yang menjawab "paket ini dapat fitur apa" — `plans.limits` memuat batas, bukan daftar kapabilitas. Menuliskannya kembali dengan tangan berarti mengulang kesalahan yang sama pada kolom yang berbeda. Isi paket yang benar-benar berbasis data (seat bawaan, kuota AI) datang lewat `[BL-067]`; daftar kapabilitasnya lewat `[BL-032]` butir (1), yang tetap terbuka atas keputusan pemilik.
- **Dua CTA di bagian harga ikut dibetulkan ke `/register`; tiga sisanya sengaja tidak disentuh.** Barisnya memang ditulis ulang, dan menulis ulang sebuah tombol ke tujuan yang sudah diketahui salah tidak bisa dibenarkan. Tiga CTA lain di landing (hero, nav seluler, footer) masih menunjuk `/login` — di luar bagian yang dikerjakan entri ini, dan tetap tercatat di `[BL-032]`.
- **Masa gratis disebut angkanya, dibaca dari config.** Kartu `free` menuliskan "{n} bulan pertama, lalu pindah ke paket berbayar" dengan `n` dari `subscription.trial_months`. Paket gratis tanpa keterangan itu terbaca sebagai gratis selamanya — lalu perpindahan paksa di bulan ketiga (`[BL-052]`) akan terbaca sebagai tagihan yang muncul entah dari mana.
- **Tinggi kartu diratakan `items-stretch` + `mt-auto`, bukan paragraf kosong.** Versi pertama memakai `<p>&nbsp;</p>` sebagai pengganjal supaya tombolnya sejajar; itu dibuang setelah diperiksa di peramban bahwa `mt-auto` sudah melakukannya sendiri.
- **File Terdampak:**
  - `app/Http/Controllers/Public/LandingController.php` — `index()` menerima `PublicPricing` dan mengirim `snapshot()` ke view. Pint sekalian membuang `use Illuminate\Http\Request` yang memang sudah tidak terpakai sebelum perubahan ini.
  - `resources/views/public/landing.blade.php` — blok `#pricing` (±78 baris) diganti perulangan `@foreach` atas `$pricing['plans']`.
  - `tests/Feature/Public/LandingPricingTest.php` — **baru.** 6 test: paket nyata terpajang, dua paket karangan tidak ada lagi, paket gratis beserta lama masa gratisnya, harga yang berubah di basis data ikut berubah di halaman, paket nonaktif tersaring, dan tombolnya menuju pendaftaran.
- **Cara memeriksanya.** 31 test di `tests/Feature/Public` lewat. Satu di antaranya mengubah `base_price` lalu memuat ulang halaman dan memastikan angka lamanya benar-benar hilang — itu inti butir (2), dan test yang hanya memeriksa "angkanya muncul" tidak akan menangkapnya. Halaman juga diperiksa di peramban pada 1280px: empat kolom, keempat kartu setinggi 335px, tombolnya sejajar di dasar, dan `paid-1` tersorot.
- **Yang TIDAK dikerjakan:** `[BL-032]` butir (1) tulis-ulang daftar fitur dan butir (3) tangkapan layar & avatar ±600 KB tetap **Open**. Bagian harga juga belum menyebut seat maupun kuota AI — itu `[BL-067]`, dan belum menjelaskan cara tarif Adaptif dihitung — itu `[BL-066]`.

---

### [ADDITION] Satu Pembaca Harga untuk Permukaan Tanpa Sesi, Sebelum Ada yang Membacanya (BL-041, BL-066, BL-067)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — dasar bersama `[BL-041]`(c), `[BL-066]`(c), dan `[BL-067]`(a). Commit kedua di jalan permukaan publik, setelah `[BL-033]`.
- **Dampak:** Service | Test
- **Breaking Change:** Tidak. Belum ada satu pun pemanggil; tidak ada rute, controller, maupun layar yang berubah.
- **Deskripsi:** `PublicPricing` membacakan paket aktif, tangga Harga Adaptif, ambangnya, dan syarat masa gratis serta perpindahan jalur — tanpa menyentuh tenant maupun sesi. Ia belum dipakai siapa pun; tiga commit berikutnya yang akan memakainya.
- **Alasan:** Tiga entri backlog menuntut angka yang sama di tempat yang berbeda, dan ketiganya menulis syarat yang identik: angkanya ditarik dari `plans` dan `pricing_rules`, bukan diketik di HTML. `[BL-067]`(a) menyebut bahayanya paling jelas — "jangan dibuat sebagai tabel HTML ketiga yang bisa basi sendiri".

- **Kelas ini mendarat tanpa pemanggil, dan itu disengaja.** Ia dasar bersama tiga entri; menempelkannya ke salah satu berarti dua entri berikutnya mewarisi bentuk yang dipilih untuk keperluan lain. Commit tersendiri juga membuat aturan bacanya bisa ditinjau sebagai aturan — bukan terselip di antara markup halaman harga.
- **Tidak menyentuh tenant sama sekali, dan itu syarat berdirinya.** Ia dipanggil dari halaman tanpa sesi. Segala yang butuh tenant — bracket berjalan, perkiraan tarif, penilaian kelayakan — tetap di `PricingService` dan `AdaptiveEligibility` yang memang memegang tenantnya. Ada test yang menjaga batas ini, karena melanggarnya berarti halaman publik meledak bagi tamu atau, lebih buruk, membacakan data satu tenant kepada semua orang.
- **Tangganya dipanggil, bukan diturunkan ulang.** `adaptiveLadder()` dan `adaptiveCeiling()` sudah ada sejak `[BL-055]` dan keduanya memang tidak menyentuh tenant. Halaman publik dan `/langganan/harga-adaptif` karenanya membaca fungsi yang sama — dua halaman yang menjelaskan tangga yang sama tidak boleh bisa berbeda.
- **`ceiling` bernilai `null` berarti "tidak ada ambang", bukan "ambangnya nol".** Bedanya menentukan, dan arah gagalnya sudah diperingatkan `[BL-048]`: satu bracket yang lupa diberi batas atas membuat ambangnya hilang, dan halaman yang menukar keduanya akan memberi tahu **setiap** pengunjung bahwa omzetnya terlalu tinggi untuk Harga Adaptif. Dijaga test tersendiri.
- **`ai_daily` bernilai `null` juga berarti "ikut bawaan platform", bukan nol.** Ini membawa naik pembedaan yang sudah dijaga `Plan::limit()`: paket yang tidak menyetel batas berbeda dari paket yang menyetel batas nol, dan yang kedua berarti paket itu sengaja tidak menjual AI. Meleburnya jadi satu membuat halaman publik memajang "0 analisis/hari" untuk paket yang sebenarnya dapat jatah. Angka bawaan platform sengaja **tidak** diambil dari `AiQuota` — pintunya (`dailyLimitFor()`) menuntut tenant, dan promo berjangka yang ikut terhitung di sana tidak layak dipajang di halaman publik sebagai kuota tetap.
- **Hanya paket `is_active`.** Paket nonaktif adalah paket yang pemiliknya berhenti menjual; memajangnya mengundang pendaftaran ke sesuatu yang tidak ada tempatnya.
- **Urutannya `base_price` lalu `id`.** Pemutus keduanya bukan hiasan: dua paket berharga sama tanpa pemutus akan berpindah-pindah urutan antar permintaan, dan tabel harga yang barisnya bergeser tiap muat terbaca sebagai halaman yang rusak.
- **Angkanya mentah, pemformatan Rupiah tidak ikut.** Pembaca ini mengirim `float`; menuliskannya sebagai "Rp 100.000" di sini berarti memilih satu bentuk untuk semua pemakainya, termasuk yang belum ada.
- **File Terdampak:**
  - `app/Services/Pricing/PublicPricing.php` — **baru.** Satu metode publik, `snapshot()`, mengembalikan `plans`, `adaptive` (ladder + ceiling + nama paket penampungnya), `trial_months`, dan `track_switch_minimum_months`.
  - `tests/Feature/Subscription/PublicPricingTest.php` — **baru.** 8 test: urutan & isi paket, paket nonaktif tersaring, `ai_daily` null vs nol, tangga & ambang dari `pricing_rules`, tangga tanpa ujung, paket penampung adaptif, syarat masa gratis/perpindahan, dan batas "tidak menyentuh tenant".
- **Cara memeriksanya.** 270 test di `tests/Feature/Subscription` lewat. Test barunya mengosongkan `plans` dan `pricing_rules` lebih dulu lalu menyusun tangganya sendiri: yang diuji bentuk pembacanya, bukan isi benih migrasi yang bisa berubah kapan saja.
- **Yang TIDAK dikerjakan:** belum ada halaman yang memakainya. `/harga` menyusul di `[BL-041]`(c), isi paket di `[BL-067]`, penjelasan cara tarif dihitung di `[BL-066]`.

---

### [REFACTOR] "Profil Usaha" Pecah Jadi Tiga Halaman dan Tiga Endpoint — Satu Tombol Simpan Tidak Lagi Menulis Merek, Tarif, Modul, dan Kunci API Sekaligus (BL-039)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-039]`, seluruh butirnya.
- **Dampak:** Routing | Controller | Frontend | Test
- **Breaking Change:** Tidak untuk pengguna. Untuk kode: `Owner\SettingsController` **dihapus** dan diganti tiga controller di `app/Http/Controllers/Owner/Settings/`; nama rute `owner.settings.mcp-token.*` menjadi `owner.settings.integrations.mcp-token.*`. Path `/owner/settings` dan nama rute `owner.settings.index`/`.update` sengaja dipertahankan, jadi bookmark dan tautan yang sudah beredar tidak putus.
- **Deskripsi:** Halaman "Profil Usaha" memuat lima urusan dengan tingkat risiko yang jauh berbeda di balik satu tombol simpan. Sekarang ia tiga halaman dengan tiga endpoint terpisah: **Profil & Merek** (nama, alamat, telepon, jenis usaha), **Cara Kerja Sistem** (kapabilitas modul, aturan kerja kasir, identitas pesanan), dan **Integrasi & Kredensial** (penyedia AI + kunci, token MCP).
- **Alasan:** Review demo pemilik — "pada profile usaha dan setting usaha, pisahkan bersifat brand usaha, cara kerja sistem, sampai ke api key dll settingannya pisahkan semua". Kasusnya menguat sejak `[DECISION] Jenis Usaha Berpindah ke Pemilik Toko` (2026-07-31): satu permintaan `PATCH` bisa menulis identitas usaha, **dasar tarif bulan depan**, kapabilitas modul, dan kunci API penyedia AI sekaligus.

- **Yang dipecah endpoint-nya, bukan aturan validasinya.** `business_type` dan `order_identity_mode` sudah memakai `sometimes|required` justru untuk keadaan ini, jadi tiap controller tinggal membawa potongan aturan miliknya. Tidak ada satu aturan validasi pun yang berubah maknanya.
- **Pemisahannya jadi sifat rute, bukan kesepakatan yang dijaga kehati-hatian.** `$request->validate()` hanya mengembalikan kunci yang divalidasi, jadi field milik halaman lain yang ikut terkirim tidak tersimpan — bukan karena ada yang menyaringnya, melainkan karena endpoint itu tidak pernah tahu field itu ada. Tiga test mengunci arah ini satu per satu: Profil tidak bisa menulis kredensial/kapabilitas, Cara Kerja tidak bisa menulis kredensial/dasar tarif, Integrasi tidak bisa menulis kapabilitas/dasar tarif.
- **Jenis usaha ditaruh di Profil & Merek, bukan di halaman tersendiri.** Ia memang keterangan usaha, dan memisahkannya hanya akan membuat orang mencarinya di tempat yang salah. Yang wajib ikut — dan sekarang ada — satu kalimat di sebelah kolomnya: mengubahnya ikut menentukan tarif periode berikutnya, dan tagihan yang sudah terbit tidak berubah. Kolom yang menggerakkan uang tidak boleh terlihat sama tak berbahayanya dengan kolom nomor telepon.
- **Sakelar `ai_enabled` TIDAK ikut pindah ke halaman kredensial meski berkerabat.** Ia kapabilitas modul, tempatnya di Cara Kerja Sistem. Yang ikut ke halaman Integrasi hanya bacaannya, dipakai memasang peringatan terus terang: selama modulnya mati, apa pun yang disimpan di halaman itu belum dipakai. Tanpa itu owner akan menghabiskan waktu mencari kesalahan pada kuncinya.
- **`isActive()` di sidebar ikut diperbaiki, dan ini bukan pekerjaan kosmetik.** `/owner/settings` kini jadi awalan `/owner/settings/operations`, sehingga `url.startsWith(href)` menyalakan induk DAN anaknya sekaligus — dan breadcrumb, yang mengambil kecocokan pertama, akan menyebut halaman yang salah. Daftar "href yang jadi induk" diturunkan dari sidebar itu sendiri, bukan ditulis tangan, supaya halaman bersarang berikutnya tidak mengulang bug yang sama diam-diam.
- **`SettingsNav` ada karena sidebar menghilang di layar kecil.** Owner yang baru mengganti jenis usaha biasanya meneruskan ke halaman sebelahnya; di ponsel jalannya cuma lewat menu yang harus dibuka dulu.
- **Tautan jalan keluar di `AiQuotaMeter` ikut dibetulkan.** Ia menawarkan BYOK lalu mengirim orang ke "Buka Pengaturan" — halaman yang sejak entri ini tidak lagi memuat kolom kuncinya. Sekarang ia menunjuk langsung ke halaman kredensial, dan labelnya berbunyi "Atur Kunci API".
- **Satu test lama diganti pembandingnya, bukan dihapus.** `OrderIdentityTest` membuktikan aturan `sometimes` dengan mengirim `phone` dan memastikan mode identitas tidak balik ke `none`. Sejak telepon pindah ke endpoint lain, pengiriman itu tidak lagi membuktikan apa pun; pembandingnya diganti `ai_enabled`, yang MASIH satu endpoint dengan mode identitas.
- **Yang sengaja TIDAK dikerjakan, dan alasannya:**
  - **Unggah logo struk.** Usulan `[BL-039]` menyebutnya masuk Profil & Merek, tapi `logo` hari ini kolom mati — pencarian di seluruh `app/`, `resources/`, dan `routes/` hanya menemukannya di `$fillable` `Tenant`. Membangunnya berarti fitur baru, dan ia menabrak `[BL-076]` (berkas tenant belum punya jalan ke object storage). Ditahan sampai `[BL-076]` dijawab.
  - **Nama usaha tetap `disabled`.** Membukanya menyeret `slug`, dan itu keputusan tersendiri yang tidak diminta entri ini.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/SettingsController.php` — **dihapus**, dipecah jadi tiga.
  - `app/Http/Controllers/Owner/Settings/BusinessProfileController.php` — **baru.** `address`, `phone`, `business_type`.
  - `app/Http/Controllers/Owner/Settings/SystemBehaviorController.php` — **baru.** Empat sakelar + `order_identity_mode`, beserta `featureWarnings`.
  - `app/Http/Controllers/Owner/Settings/IntegrationController.php` — **baru.** Kredensial AI, snapshot kuota, dan token MCP.
  - `routes/web.php` — tiga pasang `GET`/`PATCH` menggantikan satu pasang; rute token MCP pindah ke bawah `settings/integrations`.
  - `resources/js/Pages/Owner/Settings/Index.vue` — tinggal Profil & Merek.
  - `resources/js/Pages/Owner/Settings/Operations.vue`, `Integrations.vue` — **baru.**
  - `resources/js/Components/SettingsNav.vue` — **baru.** Penunjuk arah antar ketiganya.
  - `resources/js/Layouts/OwnerLayout.vue` — grup "Pengaturan" jadi empat tautan; ikon `cog` & `key` ditambahkan; `isActive()` diperbaiki untuk href bersarang.
  - `resources/js/Components/AiQuotaMeter.vue` — tautan jalan keluar BYOK menunjuk ke halaman kredensial.
  - `tests/Feature/Owner/SettingsSplitTest.php` — **baru.** 6 test yang mengunci pemisahannya.
  - `tests/Feature/Owner/SettingsAiTest.php`, `SettingsMcpTest.php`, `tests/Feature/FeatureGatingTest.php`, `OrderIdentityTest.php`, `Platform/PricingDimensionTest.php`, `Upsell/UpsellEventTest.php` — diarahkan ulang ke endpoint yang sekarang memiliki field-nya.
- **Cara memeriksanya.** Seluruh suite dijalankan: **909 test lulus (3.928 assertion)**. Ketiga halaman juga dibuka di peramban dengan sesi owner yang sudah berjalan — ketiganya merender, breadcrumb menyebut halaman yang benar (bukan induknya), dan satu putaran simpan sungguhan lewat formulir Integrasi terbukti mendarat di basis data lalu dikembalikan ke keadaan semula.
- **Dampak ke `[BL-034]`:** halaman "Cara Kerja Sistem" inilah tempat preset fitur pendaftaran nanti bisa ditinjau ulang owner. Perlu dicatat untuk yang mengerjakannya: preset berlaku **sekali saat pendaftaran**, jadi halaman ini tidak boleh menampilkan "preset Anda: kafe" seolah bisa diterapkan ulang — owner yang sudah mematikan antrian dapur tidak boleh mendapatkannya kembali.

---

### [ADDITION] Nominal yang Menyimpang dari Aturan Wajib Beralasan, dan Alasannya Dibaca Tenant yang Ditagih (BL-057)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-057]` butir (a) dan (b); butir (c) memang tidak menuntut kode.
- **Dampak:** Migration | Model | Controller | Frontend | Test
- **Breaking Change:** Tidak untuk data yang sudah ada — kolomnya nullable dan tagihan lama tetap terbaca. **Ya untuk pemanggil penerbit manual:** `POST /platform/invoices` sekarang menolak nominal yang menyimpang dari tarif aturan bila `amount_reason` kosong.
- **Deskripsi:** Penerbit tagihan manual di panel platform menerima nominal bebas tanpa pernah menanyakan kenapa. Sekarang ia menanyakannya — tapi hanya ketika nominalnya benar-benar menyimpang dari tarif aturan — dan jawabannya ikut terbaca tenant di halaman langganan mereka.
- **Alasan:** Keputusan pemilik 2026-08-07 — "atau saya input manual dan paksa berikan komentar atau alasan". Dua mekanisme lain yang diminta keputusan itu ternyata sudah berdiri (dropdown paket sudah mewajibkan `reason`; nominal manual memang hanya berlaku sebulan karena penerbit otomatis tidak pernah membaca harga terkunci), jadi yang tersisa memang hanya kolom ini.

- **Wajibnya bersyarat, dan syaratnya tidak bisa ditulis sebagai aturan validasi biasa.** `follows_rule` baru bisa dijawab setelah tenant dan aturan harganya di-resolve — dan itu terjadi jauh setelah `$request->validate()`. Karena itu pemeriksaannya jadi lapis kedua: validasi pertama menerima `amount_reason` sebagai `nullable`, lalu setelah `$mengikutiAturan` dihitung, `ValidationException::withMessages()` dilempar bila alasannya kosong. Bentuk itu dipilih, bukan `back()->with('error')`, karena hanya ia yang menempelkan pesannya pada kolomnya di formulir; pesan yang mendarat di toast hilang bersama toast-nya dan meninggalkan formulir yang menolak tanpa menunjuk apa pun.
- **Alasan tidak diminta untuk tagihan yang mengikuti aturan — itu keputusan, bukan kelonggaran.** Memaksa alasan pada nominal yang persis sama dengan tarif aturan hanya melatih orang mengetik "sesuai aturan" tanpa membacanya, dan kolom yang selalu diisi basa-basi berhenti bermakna justru pada tagihan yang benar-benar istimewa. Deteksinya sudah ada di controller sejak `[BL-015]`; ia tinggal dipakai sebagai syarat.
- **Tidak ada aturan yang cocok = menyimpang.** Bila tarifnya keluar dari paket penampung atau tidak ada sama sekali, `follows_rule` bernilai false dan alasannya wajib. Itu benar secara maksud: di situ angkanya adalah keputusan orang, bukan hasil aturan. Konsekuensinya nyata dan disengaja — empat tes lama yang menerbitkan tagihan menyimpang kini harus menyertakan alasan.
- **Spasi bukan alasan.** Nilainya di-`trim()` sebelum diperiksa dan sebelum disimpan, jadi `'   '` ditolak sama seperti kolom kosong.
- **Alasan untuk penyimpangan yang tidak terjadi tidak disimpan.** Bila nominalnya dikembalikan ke tarif aturan setelah alasannya terlanjur diketik, yang tersimpan `null`. Kalimat yang menjelaskan penyimpangan yang tidak ada hanya membingungkan tenant yang membacanya.
- **Kolom baru, bukan menumpang `rejection_reason` — dan itu bukan kerapian belaka.** Keduanya kalimat bebas yang dibaca tenant, tapi menjawab pertanyaan berbeda pada saat berbeda: `rejection_reason` menjelaskan kenapa BUKTI BAYAR ditolak dan lahir setelah tagihan berjalan; `amount_reason` menjelaskan kenapa NOMINALNYA begini dan lahir bersama tagihannya. Satu tagihan bisa punya keduanya sekaligus — harga khusus yang buktinya kurang — dan satu kolom berarti yang kedua menimpa yang pertama.
- **Terlihat tenant adalah keputusan yang diambil sadar (opsi A), bukan kelalaian daftar putih.** Alternatifnya — alasan internal yang hanya masuk jejak audit — lebih murah dan tidak butuh migrasi sama sekali. Yang membuatnya kalah: masalah yang dijawab entri ini justru "nominal yang berbeda dari daftar harga tanpa penjelasan adalah pertanyaan yang pasti datang", dan yang bertanya adalah tenant. Konsekuensinya ditanggung penulisnya, jadi formulirnya mengatakannya terus terang di footnote kolomnya: *"Tenant ikut membacanya — tulis yang memang boleh mereka baca."*
- **Di layar tenant ia bukan `text-destructive`.** Penolakan bukti bayar tepat di bawahnya berwarna merah karena memang kabar buruk. Alasan nominal seringnya justru sebaliknya — potongan harga — jadi ia muted. Dua kalimat yang berdampingan dengan warna sama akan terbaca sama-sama sebagai masalah.
- **Formulirnya menunjukkan syaratnya sebelum tombol ditekan.** `followsRule` di Vue mencerminkan perhitungan controller dengan ambang yang sama (0,01), memanfaatkan endpoint `/platform/invoices/suggestion` yang sudah dipanggil formulir itu. Ia **tidak** menggantikan penjaga server — hanya membuat labelnya berubah jadi "Alasan nominal khusus *" begitu angkanya menyimpang. Saat usulannya belum atau gagal diambil, jawabannya diperlakukan sebagai "belum diketahui" dan kolomnya ditampilkan wajib: lebih baik meminta sesuatu yang ternyata tak perlu daripada menolak setelah dikirim.
- **File Terdampak:**
  - `database/migrations/2026_08_13_192332_add_amount_reason_to_invoices_table.php` — **baru.** `invoices.amount_reason`, `string(500)` nullable, setelah `pricing_context`.
  - `app/Models/Invoice.php` — `amount_reason` masuk `$fillable`.
  - `app/Http/Controllers/Platform/InvoiceController.php` — `store()` memvalidasi `amount_reason` sebagai nullable, mewajibkannya lewat `ValidationException` ketika `follows_rule` false, menyimpannya (di-`trim`, null bila mengikuti aturan), dan menaruhnya di `meta` jejak audit berdampingan dengan `follows_rule`.
  - `app/Http/Resources/Platform/InvoiceResource.php` — `amount_reason` masuk daftar putih.
  - `app/Http/Controllers/Billing/SubscriptionController.php` — `amount_reason` ikut di peta tagihan sisi tenant.
  - `resources/js/Pages/Platform/Tenants/Show.vue` — kolom alasan di formulir terbit tagihan dengan label & footnote yang berubah menurut `reasonRequired`; computed `followsRule`/`reasonRequired`; alasannya tampil di bawah nominal pada tabel riwayat tagihan.
  - `resources/js/Pages/Billing/Show.vue` — alasannya tampil di baris tagihan tenant, muted, di atas `rejection_reason`.
  - `tests/Feature/Platform/PlatformBillingTest.php` — 7 test baru (ditolak tanpa alasan, spasi bukan alasan, diterima + masuk jejak audit, nominal sesuai aturan tidak dimintai alasan, alasan basa-basi tidak disimpan, terlihat di panel platform, terbaca tenant di `/langganan`) + 2 test lama disesuaikan.
  - `tests/Feature/Platform/PricingDimensionTest.php`, `tests/Feature/Subscription/AutoInvoiceTest.php` — masing-masing satu test lama disesuaikan: keduanya menerbitkan tagihan yang menyimpang dan kini menyertakan alasan.
- **Cara memeriksanya.** 421 test di `tests/Feature/Platform` dan `tests/Feature/Subscription` lulus. Suite lengkap (916 test) juga dijalankan: satu kegagalan, dan bukan dari sini — `reset link requests are rate limited` menyeberangi batas menit `Limit::perMinute(3)` pada run yang memakan berjam-jam karena mesinnya terbebani; ia lulus saat dijalankan sendiri. Sisi Vue diverifikasi lewat `vite build`, **tidak** lewat peramban — kedua halaman ada di balik login, dan mengisi kata sandi bukan tindakan yang saya lakukan.
- **Yang TIDAK dikerjakan:** butir (c) `[BL-057]` — kolom "harga khusus permanen" per tenant — memang tidak boleh ada, dan tetap tidak ada. Harga tetap datang dari paket; tenant yang perlu harga tetap lain seharusnya mendapat paket baru yang auditable dan muncul di panel.

---

### [ADDITION] Halaman Staf Menjawab "Orang Ini Bisa Buka Apa Saja", dan Baris Owner Mengaku Melewati Seluruh Pemeriksaan (BL-038)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-038]`, seluruh butirnya.
- **Dampak:** Controller | Frontend | Test
- **Breaking Change:** Tidak. Tidak ada rute, skema, maupun aturan izin yang berubah — yang bertambah hanya prop Inertia dan tampilannya.
- **Deskripsi:** Halaman Staf sebelumnya menjawab "orang ini rolenya apa"; sekarang ia menjawab "orang ini bisa membuka apa saja", dengan lencana modul di bawah nama rolenya. Owner ikut berbaris sebagai baris tersendiri yang menyatakan terus terang bahwa aksesnya tidak berasal dari role.
- **Alasan:** Review demo pemilik — "dalam konsep tim dan akses, staf dan role konsepnya dia langsung melihat kalau orang ini (email ini) memiliki akses ke module module berikut". Sebelum ini jawabannya menuntut tiga langkah: baca nama role di halaman Staf, pindah ke halaman Role, cocokkan sendiri.

- **Datanya tidak dihitung ulang — ia sudah ada sejak RBAC mendarat.** `User::modulePermissions()` sudah menjadi satu-satunya sumber jawaban ini dan sudah dipakai `HandleInertiaRequests` serta payload autentikasi mobile. Yang ditambahkan controller hanya memanggilnya per baris. Menulis perhitungan kedua khusus untuk halaman ini akan melahirkan dua jawaban yang pasti bercabang begitu salah satunya diperbaiki.
- **Owner mendapat baris sendiri, dan itu bagian yang paling penting dari entri ini.** Kueri staf menyaring `role = 'cashier'`, jadi selama ini satu-satunya akun yang aksesnya TIDAK berasal dari role justru satu-satunya yang tidak pernah muncul di layar mana pun. Akibat praktisnya nyata: `Gate::before` meloloskan owner dari setiap gerbang, sehingga mencabut modul dari role yang kebetulan dipegang owner tidak mengubah apa pun — dan tidak ada yang memberi tahu. Barisnya sekarang mengatakannya: satu lencana "Akses penuh — melewati seluruh pemeriksaan", plus satu kalimat bahwa mengubah role tidak akan membatasinya.
- **Baris owner dibaca dari `['*']`, bukan dari "ini kan baris owner".** `modulePermissions()` sudah mengembalikan `['*']` untuk owner, dan Vue-nya memeriksa nilai itu (`bypassesEveryCheck`) alih-alih menyimpulkan dari jenis barisnya. Bedanya baru terasa nanti: kalau suatu saat ada akun lain yang melewati pemeriksaan, ia akan tampil benar tanpa halaman ini disentuh.
- **`ownerRows()` mengambil dari kolom `role`, bukan dari user yang sedang masuk.** Satu usaha yang dijalankan berdua punya dua baris owner. Daftar yang hanya menampilkan dirinya sendiri justru menyembunyikan rekannya — padahal "siapa saja yang bisa apa di toko ini" adalah pertanyaan yang halaman ini ada untuk menjawabnya.
- **Barisnya sengaja tanpa tombol aksi.** Edit, Nonaktifkan, dan Hapus semuanya ditolak server untuk owner (`authorizeStaff()` sudah meng-`abort_if` `isOwner()`), dan tombol yang selalu ditolak lebih buruk daripada tombol yang tidak ada.
- **`with('roles.permissions')` bukan optimasi spekulatif.** `modulePermissions()` menanyakan tujuh modul lewat Gate; tanpa eager load, tiap pertanyaan menarik relasi rolenya sendiri — tujuh kueri per orang, dikali jumlah staf. Jumlahnya memang dibatasi seat sehingga tak akan meledak, tapi biayanya nol dan alasannya ditulis di tempatnya.
- **Label modul datang dari `config/rbac.php` lewat prop, bukan diketik ulang di Vue.** Bentuk propnya sama persis dengan yang sudah dikirim halaman Role, sehingga tidak ada daftar label kedua yang harus ikut diperbarui saat katalog modul berubah.
- **Satu perubahan kata yang bukan kosmetik.** Staf tanpa role dulu berlencana "POS saja". Itu tidak benar: tanpa role ia tidak memegang permission `pos` sama sekali — yang membuatnya tetap bisa mengakses kasir adalah hal lain. Lencananya sekarang "Tanpa role", dengan keterangan "Belum ada modul — hanya bisa membuka kasir" di bawahnya, sehingga yang tertulis di layar sama dengan yang benar-benar dihitung `modulePermissions()` (daftar kosong).
- **File Terdampak:**
  - `app/Http/Controllers/Owner/StaffController.php` — `index()` mengirim `modules` per staf, prop `owners` baru, dan katalog label `modules`; kueri staf meng-eager-load `roles.permissions`; method privat `ownerRows()` baru.
  - `resources/js/Pages/Owner/Staff/Index.vue` — prop `owners` & `modules`, helper `moduleLabel()` dan `bypassesEveryCheck()`; baris owner di atas daftar staf; kolom "Role" jadi "Role & modul" dengan lencana modul di bawah nama role; lencana "POS saja" jadi "Tanpa role"; teks kosong jadi "Belum ada staf selain pemilik" karena tabelnya tidak lagi benar-benar kosong.
  - `tests/Feature/Owner/StaffModuleVisibilityTest.php` — **baru.** 8 test: modul efektif per staf, staf tanpa role berdaftar kosong, pencabutan modul dari role terlihat di halaman, owner punya baris, baris owner berisi `['*']` bahkan ketika owner memegang role, owner kedua ikut terdaftar, owner tenant lain tidak bocor, dan katalog label terkirim utuh.
- **Cara memeriksanya.** 8 test baru lewat, dan 104 test di `tests/Feature/Owner`, `tests/Feature/Authorization`, serta `SeatLimitTest` ikut dijalankan dan tetap hijau. Sisi Vue diverifikasi lewat `vite build` (876 modul, sukses) — **tidak** lewat peramban: halaman ini ada di balik login, dan mengisi kata sandi bukan tindakan yang boleh saya lakukan.
- **Yang TIDAK dikerjakan:** tidak ada. Seluruh butir `[BL-038]` tertutup, termasuk tambahan baris owner yang diminta pemilik saat entri ini dikerjakan.

---

### [DECISION] Tiga Permukaan Publik Jadi Satu Keluarga: `SAPI POS` Resmi, Palet Tunggal, dan Tailwind CDN Dilepas (BL-033)
- **Tanggal:** 2026-08-14
- **Fase Terkait:** Di Luar Fase — `[BL-033]`, seluruh butirnya. Ini commit pertama dari satu jalan yang menggarap empat entri permukaan publik (`[BL-033]`, `[BL-032]`(2), `[BL-041]`(b)(c), `[BL-066]`, `[BL-067]`); ia dikerjakan lebih dulu supaya halaman `/harga` yang menyusul lahir sudah memakai gaya keluarga ini, bukan disentuh ulang belakangan.
- **Dampak:** Frontend | Test
- **Breaking Change:** Tidak. Tidak ada rute, controller, maupun data yang berubah.
- **Deskripsi:** `/`, `/api-docs`, dan `/dokumentasi` sebelumnya berdiri di atas tiga sumber gaya berbeda dan dua identitas merek berbeda. Sekarang ketiganya memuat satu partial palet, satu partial wordmark, dan satu set ikon yang sama dengan aplikasi di balik login.
- **Alasan:** Review demo pemilik — "dokumentasi publik dan dokumentasi teknis seperti ai dan api kasih konsisten" dan "konsep logo SAPI Pos seperti di halaman dokumentasi menarik". Yang diminta bukan memindahkan halaman, melainkan membuat ketiganya terasa satu produk.

- **Wordmark `SAPI POS` versi dokumentasi jadi identitas resmi, dan dua permukaan lain yang mengalah.** Landing dan `/api-docs` memakai "SAPI" telanjang; `/dokumentasi` memakai "SAPI" tebal + "POS" kecil berhuruf besar. Yang kedua yang dipilih pemilik, jadi partialnya dibentuk persis dari CSS `.docs-brand` yang ia gantikan — 17px/800 untuk "SAPI", 10px/uppercase/`0.12em`/`--text-dim` untuk "POS". Konsekuensinya `/dokumentasi` **tidak berubah sedikit pun** secara visual, yang memang seharusnya: ia halaman rujukannya, bukan yang perlu diperbaiki.
- **Palet jadi partial Blade, BUKAN pindah ke `resources/css/app.css` — dan penting untuk tahu kenapa.** Entri backlognya menawarkan kedua jalan. Yang kedua tidak bisa dipakai: nama `--border` di palet publik bertabrakan dengan token bernama sama milik shell aplikasi (`app.css`), yang dipetakan `@theme inline` jadi utilitas `border-border` dan dipakai seluruh halaman Vue. Menaruh nilai publik di `:root` global berarti menggeser garis di setiap layar di balik login demi tiga halaman pemasaran. Partial menjaganya tetap sebatas permukaan yang memakainya.
- **Token khusus komponen sengaja tidak ikut diangkat.** `--badge-*` (badge metode HTTP) dan `--code-*` (blok kode) tetap tinggal di `api-docs.blade.php` karena hanya satu permukaan memakainya. Yang naik ke partial hanya yang benar-benar dipakai bersama, plus `--red-soft` yang jadi rujukan `--badge-delete-text`.
- **Melepas `cdn.tailwindcss.com` bukan sekadar membuang dua permintaan pihak ketiga — CDN itu Tailwind v3, sementara build Vite proyek ini v4.** Karena skripnya menyuntikkan `<style>` saat runtime, ia dimuat **setelah** `app.css` dan karenanya menang. Artinya selama ini dua halaman publik dirender oleh Tailwind versi lain daripada seluruh aplikasi, dengan arti kelas yang berbeda. Yang benar-benar berbeda di halaman ini cuma satu: `shadow-sm` v3 (`0 1px 2px rgb(0 0 0/0.05)`) adalah `shadow-xs` di v4, jadi tujuh tempat di landing diganti namanya supaya bayangannya tetap setipis sekarang, bukan menebal diam-diam.
- **`flex-shrink-0` diperiksa dan sengaja TIDAK diubah.** Dugaan awalnya utilitas ini hilang di v4 dan 12 pemakaiannya akan patah begitu CDN dilepas. Build membuktikan sebaliknya — v4 masih memancarkan `.flex-shrink-0,.shrink-0{flex-shrink:0}`. Penggantian nama yang sempat dilakukan dikembalikan: ia tidak mengubah satu piksel pun, dan mencampur 12 baris rapi-rapian ke commit yang seharusnya bisa dibaca sebagai "satu keluarga desain" hanya membuat diff-nya sulit ditinjau. Utilitas usang yang sama dipakai ±30 komponen Vue; membereskannya adalah pekerjaan tersendiri, bukan sisipan di sini.
- **Ikon menyusul ke permukaan publik.** Ketiganya tidak memuat satu pun tautan favicon, jadi tab pemasaran menampilkan ikon bawaan peramban sementara tab aplikasi menampilkan ikon SAPI — dua produk berbeda bagi orang yang membuka keduanya berdampingan. Partial `favicon` memuat berkas yang persis sama dengan `resources/views/app.blade.php`, termasuk `theme-color`.
- **`public/sapi-logo.png` tetap tidak dirujuk siapa pun.** Entri backlognya menyebutnya sebagai aset menganggur, dan ia masih menganggur: wordmark yang dipilih pemilik berbentuk teks, bukan gambar, jadi memasangnya hanya akan menambah identitas ketiga. Berkasnya dibiarkan, bukan dihapus — keputusan membuang aset bukan bagian dari entri ini.
- **File Terdampak:**
  - `resources/views/public/partials/theme.blade.php` — **baru.** 13 token palet bersama; komentarnya memuat alasan ia tidak boleh pindah ke `app.css`.
  - `resources/views/public/partials/wordmark.blade.php` — **baru.** Wordmark `SAPI POS` dengan tiga ukuran (`sm` topbar dokumentasi, `md` nav, `lg` footer) dan penanda `interactive` untuk induk `.group`. Warnanya dari token palet, bukan skala abu-abu Tailwind, supaya ikut bergeser bila paletnya berubah.
  - `resources/views/public/partials/favicon.blade.php` — **baru.** Ikon + `theme-color`, sama dengan shell aplikasi.
  - `resources/views/public/landing.blade.php` — CDN Tailwind beserta blok `tailwind.config`-nya dibuang; `@include` tiga partial; dua wordmark (nav + footer) diganti; tujuh `shadow-sm` → `shadow-xs`.
  - `resources/views/public/api-docs.blade.php` — CDN dibuang; 13 token palet duplikat dihapus dari `<style>`-nya, menyisakan token badge & kode; dua wordmark diganti; aturan `.footer-brand` yang jadi mati ikut dihapus.
  - `resources/views/public/docs/layout.blade.php` — `:root` duplikat diganti `@include`; aturan `.docs-brand b` dan `.docs-brand span` yang jadi mati dihapus; komentar kepala berkas yang menyatakan paletnya "disalin dari /api-docs" diperbarui karena tidak lagi benar.
  - `tests/Feature/Public/BrandingTest.php` — **baru.** 4 test × 4 permukaan (`/`, `/api-docs`, `/dokumentasi`, `/dokumentasi/panduan`): wordmark, token palet, ikon, dan ketiadaan CDN Tailwind.
- **Cara memeriksanya.** 25 test di `tests/Feature/Public` lewat. Selain itu ketiga halaman dibuka di peramban dan diperiksa lewat *computed style*, bukan hanya lewat HTML — karena yang dipertaruhkan penghapusan CDN adalah gaya yang benar-benar terpakai, bukan kelas yang tertulis. Yang dipastikan: token palet resolve di ketiganya; wordmark `/dokumentasi` tetap 17px/10px seperti sebelumnya; badge dan blok kode `/api-docs` masih berwarna; dan `shadow-xs` menghasilkan `rgba(0,0,0,0.05) 0 1px 2px 0` — nilai v3 yang lama, terjaga.
- **Yang TIDAK dikerjakan:** `[BL-032]` butir (1) tulis-ulang daftar fitur dan butir (3) tangkapan layar & avatar ±600 KB tetap **Open** atas keputusan pemilik. Bagian harga karangan "Rp 149k / Rp 299k" di landing juga masih berdiri — itu `[BL-032]`(2), commit berikutnya di jalan yang sama.

---

### [ADDITION] Kuota AI Berhenti Tinggal di `.env`: Kebijakan Berjangka Waktu, Promo, dan Tombol Mengembalikan Jatah Hari Ini (BL-047)
- **Tanggal:** 2026-08-13
- **Fase Terkait:** Di Luar Fase — `[BL-047]` butir (a), (c), dan (d). Butir (b) sudah mendarat 2026-08-01 dan tidak disentuh ulang.
- **Dampak:** Migration | Model | Service | Controller | Route | Config | Frontend | Test
- **Breaking Change:** Tidak. Selama tabel `ai_quota_policies` kosong — dan ia lahir kosong — setiap tenant mendapat angka yang persis sama seperti sebelumnya. `config/ai.php` tetap jadi lapis terakhir, bukan dihapus.
- **Deskripsi:** Kuota AI harian dulu satu angka di `.env`, berlaku sama untuk semua orang, dan mengubahnya menuntut akses server plus `config:clear`. Sekarang ia kebijakan berbentuk data dengan masa berlaku, disunting dari panel platform, dan pemakaian hari ini bisa dikembalikan lewat tombolnya sendiri.

- **Promo TIDAK jadi mata rantai di urutan fallback, meski entrinya menyuruh begitu — dan justru itu yang membuatnya berguna.** `[BL-047]`(a) menempatkan kebijakan berjangka "di antara" batas paket dan bawaan config. Ditulis begitu persis, ia lahir sebagai kode mati: keempat paket yang ada hari ini (`free`, `paid-1`, `paid-2`, `paid-3`) semuanya menyetel `limits.ai_daily` sendiri (5/15/30/60), jadi lapis yang hanya mengisi kekosongan bawaan tidak akan pernah terbaca oleh satu pun tenant yang berlangganan. Bentuk yang dipakai memisahkan dua pertanyaan yang memang berbeda: `baseline` menjawab "berapa kuota bawaan platform" dan tetap duduk di antara paket dan config seperti yang diminta; `bonus` menjawab "promo dalam waktu tertentu" dan menambah **di atas** batas yang sudah berlaku, termasuk bagi tenant yang paketnya sudah punya batas sendiri.
- **`effective_until`, bukan hanya `effective_from`.** Ini satu-satunya beda bentuk dari `pricing_rules`, dan alasannya disebut entrinya sendiri: promo tanpa tanggal akhir akan berakhir sebagai angka yang lupa dikembalikan. Kebijakan yang lewat tanggalnya berhenti terbaca tanpa siapa pun perlu mengingatnya.
- **Promo tidak membukakan AI untuk paket yang batasnya nol.** `Plan::limit()` membedakan "paket tidak menyetel batas" dari "paket menyetel batas nol", dan yang kedua berarti paket itu sengaja tidak menjual AI. Promo umum yang diam-diam membukanya akan membagikan fitur berbayar kepada orang yang tidak membelinya, jadi `bonusOn()` mengembalikan nol saat batas dasarnya nol.
- **Satu pembaca, tetap satu pembaca.** Seluruh lapis baru masuk ke `AiQuota` yang sudah ada, bukan ke kelas kedua — syarat yang ditulis `[BL-047]`(b) dan alasan kelas itu berdiri: angka yang dibacakan ke owner dan angka yang menolak permintaannya wajib identik. Kebijakannya di-memo per instance karena ia berlaku platform-wide dan `snapshotFor()` sendiri membacanya dua kali.
- **Menyunting kebijakan yang sudah berlaku DIIZINKAN — berbeda dari `pricing_rules`, dan sengaja.** Larangan di sana melindungi dasar harga periode yang sudah ditagihkan. Di sini tidak ada yang setara: kuota dinilai langsung tiap permintaan, tidak pernah surut, tidak pernah masuk tagihan tenant. Yang justru berbahaya adalah kebalikannya — satu-satunya cara menghentikan promo yang telanjur kebablasan adalah memajukan tanggal akhirnya, dan melarang suntingan berarti membiarkannya berjalan dengan tagihan kunci bersama yang ikut berjalan.
- **"Reset semua" berdiri sendiri, jauh dari form kebijakan, dengan konfirmasinya sendiri (`[BL-047]`(d)).** Dua hal yang berbeda arti: mengubah kuota mengubah jatah mulai sekarang; reset mengembalikan jatah yang **sudah** terpakai hari ini kepada semua orang. Satu tombol untuk keduanya berarti pemilik SaaS yang hanya merapikan angka bawaan diam-diam membelanjakan ulang kuota sehari penuh. Barisnya dihapus, bukan disetel nol — ketiadaan baris `ai_usages` sudah berarti nol, dan baris nol hanya menambah bentuk kedua untuk keadaan yang sama.
- **Modul `ai_quota` terpisah dari `pricing_rules`.** Keduanya sama-sama sensitif tapi menyentuh uang yang berbeda: aturan harga menentukan tarif yang dibayar tenant, kuota AI menentukan tagihan kunci bersama yang ditanggung pemilik SaaS. Staf yang boleh menyunting daftar paket tidak dengan sendirinya boleh menaikkan biaya API bulanan.
- **Angka pemakaian yang muncul di panel hanya gabungan.** `usageTodaySummary()` mengembalikan jumlah tenant dan jumlah analisis, tanpa rincian per tenant — batas yang sama yang dijaga `PlatformArchTest`. Panel platform boleh tahu seberapa besar tagihan kunci bersamanya tumbuh hari ini; ia tidak boleh tahu tenant mana yang memakainya sebanyak apa. Jejak audit `ai-quota.reset` mencatat `tenants_affected`, bukan daftar tenantnya.
- **Promo disebutkan namanya di layar tenant.** `snapshotFor()` mengirim `bonus` dan `bonus_label` terpisah, bukan dilebur ke `daily_limit`, dan `AiQuotaMeter` menuliskannya sebagai baris tersendiri. Jatah yang naik tanpa alasan yang terbaca akan dikira jatah tetap — lalu hari promonya berakhir akan terbaca sebagai aplikasi yang mendadak memotong jatah.
- **File Terdampak:**
  - `database/migrations/2026_08_13_112407_create_ai_quota_policies_table.php` — tabel baru; `mode` sebagai `string` bukan `enum` (SQLite tidak bisa mengubah enum tanpa membangun ulang tabel, dan pengujian berjalan di sana), `softDeletes` supaya "kenapa bulan lalu kuota kami 20?" tetap bisa dijawab setelah kebijakannya dicabut. **Tidak menulis baris awal apa pun.**
  - `app/Models/AiQuotaPolicy.php` — model baru; `scopeEffectiveOn()` dan `winnerFor()`. Tanpa kolom `priority`: dua kebijakan sejenis yang tumpang tindih selalu berarti yang belakangan diterbitkan menggantikan yang sebelumnya, jadi "yang paling baru berlaku menang" sudah tunggal tanpa knob tambahan.
  - `app/Services/Ai/AiQuota.php` — lapis kebijakan, `bonusOn()`, `usageTodaySummary()`, `resetUsageToday()`; `limit_source` kini bernilai `plan` | `policy` | `platform`.
  - `app/Http/Controllers/Platform/AiQuotaController.php` — halaman baru; lima aksi, empat di antaranya menulis jejak `sensitive`.
  - `config/platform-rbac.php`, `routes/web.php`, `resources/js/Layouts/PlatformLayout.vue` — modul `ai_quota`, gerbang `platform.can:ai_quota`, dan menu "Kuota AI" di bawah kelompok Komersial.
  - `resources/js/Pages/Platform/AiQuota/Index.vue` — tiga kartu keadaan berjalan, tabel kebijakan, dan panel reset yang terpisah di bawah.
  - `resources/js/Components/AiQuotaMeter.vue`, `app/Http/Controllers/Owner/AiAnalysisController.php` — sisi tenant membaca `bonus`/`bonus_label`.
  - `tests/Feature/Ai/AiQuotaPolicyTest.php` (14 test), `tests/Feature/Platform/PlatformAiQuotaTest.php` (10 test), `tests/Feature/Owner/SettingsAiTest.php` (+1).
- **Cara memeriksanya.** 195 test di `tests/Feature/Ai`, `tests/Feature/Platform`, dan `SettingsAiTest` lewat, termasuk `PlatformArchTest`. Yang dikunci test bukan angkanya melainkan urutannya — paket mendahului kebijakan, kebijakan mendahului config, promo menambah di atas ketiganya, dan promo tidak menyentuh paket berbatas nol — plus keadaan yang paling mahal bila salah: promo kedaluwarsa yang ikut terbaca sebagai berlaku, dan reset yang ikut menghapus riwayat kemarin.
- **Yang TIDAK dikerjakan:** kuota AI yang bisa **dibeli** tenant tetap tidak ada — itu `[BL-069]`, masih terhalang keputusan harga, dan bentuknya berbeda (hak per langganan, bukan kebijakan platform). `AiQuota::dailyLimitFor()` sekarang punya tempat yang jelas untuk menyisipkannya: satu tingkat di atas paket, sebelum promo dijumlahkan.

---

### [ADDITION] Grafik Kedua Aplikasi Ini: Garis Tren di Rekap Bulanan (BL-064)
- **Tanggal:** 2026-08-13
- **Fase Terkait:** Di Luar Fase — `[BL-064]`, dipasang di atas rekap bulanan yang mendarat hari yang sama (`[BL-063]`)
- **Dampak:** Frontend
- **Breaking Change:** Tidak. `DailyChart` di dashboard tidak disentuh sama sekali.
- **Deskripsi:** Sampai kemarin seluruh aplikasi hanya punya satu grafik — batang tujuh hari di dashboard — dan halaman Laporan, tempat orang justru datang untuk melihat pola, seratus persen berupa tabel. `TrendChart.vue` menambah bentuk kedua: garis, untuk deret panjang yang bentuknya memang lain.

- **Komponen baru, bukan `DailyChart` yang dibuat serba-bisa.** Menambahkan prop `type` ke komponen yang sudah ada akan menyatukan dua grafik yang sumbu, kerapatan titik, dan tujuannya berbeda — dan percabangannya akan menumpuk jauh sebelum pemakai ketiga muncul. Batang tetap milik perbandingan tujuh hari yang berdiri sendiri; garis milik deret sebulan.
- **Warnanya dibaca dari tema, termasuk yang tembus pandang.** `--color-primary` dan `--color-brand` diambil dari CSS variable seperti `DailyChart`. Yang baru adalah arsirannya: warna tema ditulis dalam `oklch()`, yang tidak bisa disisipi alpha dengan menempel string, jadi warnanya dilukis ke kanvas 1×1 lalu pikselnya dibaca kembali — apa pun format yang dipahami browser masuk, `rgba()` keluar. Grafiknya tetap ikut tema tanpa satu pun heksadesimal dipatok.
- **Titik-titiknya disembunyikan sampai disentuh.** Tiga puluh satu bulatan di satu garis membuat bentuk trennya sendiri sulit dilihat; `pointRadius: 0` dengan `pointHitRadius: 12` menjaga garisnya bersih tanpa membuat tooltipnya sulit dikejar. Label sumbu X memakai `autoSkip` — tanggal 1, 3, 5, … alih-alih 31 angka yang saling menindih.
- **Tooltipnya menjawab pertanyaan berikutnya, bukan mengulang yang sudah terlihat.** Judulnya tanggal lengkap berbahasa Indonesia, isinya omzet, barisnya yang terakhir jumlah transaksi — dan hari nol menulis "tidak ada penjualan", bukan "0 transaksi" yang terbaca seperti kesalahan.
- **Kanvas kosong diganti keterangan.** Tenant baru yang membuka laporan dan melihat kotak putih tanpa penjelasan akan menganggapnya rusak, bukan kosong. Keadaan kosongnya dipicu oleh "tidak ada omzet sama sekali", bukan oleh "tidak ada data" — deret bulanan selalu berisi 31 baris, jadi memeriksa panjang array tidak akan pernah menangkap bulan yang sepi.
- **File Terdampak:**
  - `resources/js/Components/TrendChart.vue` — komponen baru; `LineElement`, `PointElement`, `CategoryScale`, `LinearScale`, `Filler`, `Tooltip` diregistrasi di sana, terpisah dari registrasi `DailyChart`.
  - `resources/js/Pages/Owner/Reports/Monthly.vue` — dipasang tepat di atas tabel "Rincian Harian", memakai `dailySeries` yang memang sudah disiapkan `[BL-063]` (setiap tanggal ada, termasuk hari nol — garisnya tidak perlu diinterpolasi).
- **Cara memeriksanya.** Proyek ini tidak punya test runner frontend, dan menambahkannya adalah perubahan dependensi tersendiri. Yang bisa diperiksa tanpa itu sudah diperiksa: bentuk `dailySeries` dikunci sepuluh test Pest milik `[BL-063]`, dan komponennya dijalankan di luar aplikasi dengan data 31 hari — 31 titik tergambar, `oklch(0.62 0.13 165)` benar berubah jadi `rgba(2, 158, 114, 0.12)` untuk arsirannya, sumbu Y mulai dari nol, label X menyusut jadi 16, dan bulan tanpa omzet menampilkan keterangan alih-alih kanvas.
- **Yang masih terbuka:** tren per jam di laporan harian — butir (c) `[BL-064]` menyebutnya sebagai "pertimbangkan sesudahnya", dan memang belum dikerjakan. Ia butuh agregasi per jam yang hari ini tidak ada di `daily()`, dan pertanyaan yang dijawabnya berbeda ("jam berapa toko ramai", bukan "bulan ini naik atau turun"). Buka entri sendiri bila memang dibutuhkan.

---

### [ADDITION] Kerangka Pemuatan Jadi Komponen, dan Empat Halaman Terberat Berhenti Menunggu Kueri Paling Lambat (BL-037)
- **Tanggal:** 2026-08-13
- **Fase Terkait:** Di Luar Fase — `[BL-037]`
- **Dampak:** Frontend | Controller | Design System | Test
- **Breaking Change:** Tidak untuk pemakai. Untuk kode: `products` + `upsell` di `Cashier/POS`, `dailyTrend` + `badges` + `recentTransactions` di `Owner/Dashboard`, `transactions` + `paymentSummary` + `topProducts` di `Owner/Reports/Daily`, dan `transactions` di `Owner/Transactions/Index` **tidak lagi ada di respons pertama**. Test yang memeriksa prop-prop itu harus lewat `loadDeferredProps()`.
- **Deskripsi:** Sampai sebelum ini seluruh prop dihitung sebelum respons dikirim, jadi halaman tidak muncul sama sekali sampai kueri paling lambat selesai — dan selama itu satu-satunya umpan balik adalah garis tipis di puncak layar yang, seperti dikatakan pemiliknya sendiri, justru membuat aplikasinya "keliatan lambat loading". Dua halaman terberatnya kebetulan yang paling sering dibuka: layar kasir dan dashboard. Yang berubah bukan kecepatan kueri-nya, melainkan urutannya: yang dicari mata lebih dulu dikirim lebih dulu, dan yang menyusul menempati ruangnya dengan kerangka.

- **Komponennya dulu, penerapannya kemudian — dan sengaja begitu.** Delapan berkas di `resources/js/Components/Skeleton/` berdiri di atas satu primitif: `Skeleton.vue` memegang warna (`bg-muted`), skala radius, dan denyutnya, dan tidak ada komponen lain yang menuliskannya sendiri. Ukuran justru **bukan** prop — pemakainya mengirim lewat class, karena tinggi dan lebar sebuah kerangka harus menyalin isi yang digantikannya, bukan mengarang ukurannya sendiri. Di atasnya: `SkeletonText`, `SkeletonPanel` (kulit kartu berikut penampung judulnya), `SkeletonCard`, `SkeletonGrid`, `SkeletonTable`, `SkeletonList`, `SkeletonChart`. Tanpa lapisan primitif ini, "ganti nada abu-abunya" akan jadi sebelas suntingan berkas alih-alih satu.
- **Aturannya masuk `DESIGN.md` §6, bukan cuma ke dalam kepala orang yang menulisnya.** Enam aturan bernama: **Ruang yang Sama** (salin class grid, padding baris, tinggi kanvas, ketebalan border), **Pasangan** (satu kerangka = satu `Inertia::defer()`; sebelah saja lebih buruk daripada tidak ada — prop tertunda tanpa kerangka mengedipkan panel kosong, kerangka tanpa prop tertunda tidak pernah tampil), **Cat Pertama** (yang eager adalah jawaban yang dicari layar itu ditambah apa pun yang bisa ditindaklanjuti sambil menunggu), **Satu Nada** (hanya `bg-muted`; tanpa gradien sapuan, tanpa abu kedua), **Pemuatan yang Diumumkan** (`role="status"` + label `sr-only` berbahasa Indonesia, bloknya `aria-hidden`), dan **Progress Bar** (bar Inertia tetap sampai tabel pelacakan di `[BL-037]` habis). Ditambah tiga larangan baru di daftar Don't, yang pertamanya: jangan menulis blok `animate-pulse` lepas di dalam halaman. Kerangka kedua yang lahir sendirian adalah awal dari dialek kedua.
- **Setiap kerangka memakai `motion-reduce:animate-none`.** Pengguna yang meminta gerak dikurangi tetap melihat penanda pemuatan, hanya tanpa denyutnya. Denyutnya sendiri opacity — bukan properti tata letak, sesuai larangan yang sudah ada di §7.
- **Di POS yang ditunda katalog dan indeks upsell — metode bayar TIDAK.** Usulan awal di backlog menyebut metode pembayaran ikut ditunda; itu tidak dilakukan. Kueri-nya satu baris pendek, dan kasir bisa menekan Bayar dalam jeda sebelum permintaan lanjutan sampai. Chip kategori juga tetap eager supaya penyaringnya sudah bisa disentuh saat kerangka gridnya masih berdenyut. Katalog dan indeks upsell dikirim dalam **satu** grup, bukan dua: `useCatalogCache` menyimpan keduanya sebagai satu snapshot offline, jadi kalau mereka sampai terpisah, ada satu jendela ketika snapshot tersimpan berisi katalog tanpa sarannya.
- **Jebakan offline yang hampir lolos: panen snapshot yang menimpa dengan kosong.** `POS.vue` dulu memanen katalog ke IndexedDB pada `onMounted`. Dengan katalog yang ditunda, pada saat itu `props.products` masih `undefined` — dan panen itu akan menulis **katalog kosong di atas snapshot yang masih bagus**, cacat yang tidak terlihat sama sekali sampai koneksi kasir jatuh dan layarnya kosong. Panennya sekarang menunggu propnya datang (`watch` dengan penjaga `Array.isArray`), dan grid produk dibuka oleh `catalogReady` — bukan `<Deferred>` — karena saat offline yang ditunggu bukan permintaan lanjutan (tidak akan pernah datang) melainkan pembacaan snapshot. Kasir tanpa snapshot tersimpan pun berhenti di kalimat yang menjelaskan itu, bukan di kerangka yang berdenyut selamanya.
- **Dashboard: badge dipisah ke grupnya sendiri.** Tren harian dan lima transaksi terakhir masing-masing satu kueri; `BadgeHelperService::generate()` memeriksa stok, upsell, dan kas sekaligus. Satu grup untuk semuanya berarti dua bagian yang cepat menunggu satu yang lambat. Metrik hari ini dan ringkasan langganan tetap eager — yang pertama adalah isi utama layarnya, yang kedua satu-satunya pintu masuk ke halaman tagihan.
- **Laporan harian berhenti memuat sehari transaksi hanya untuk menjumlahkannya.** Ringkasannya dulu `$transactions->sum('total_amount')` di atas koleksi lengkap berikut item dan pembayaran tiap baris. Karena daftarnya kini ditunda, koleksi itu tidak ada lagi di respons pertama — jadi ringkasannya dihitung dengan `SUM`/`COUNT` di basis data. Angkanya sama; yang hilang hanya pekerjaan memuat setiap baris untuk dijumlahkan di PHP. Rekap metode bayar dan produk terlaris berbagi grup `rekap`, terpisah dari daftar panjangnya, supaya dua tabel kecil itu tidak menunggu di belakangnya.
- **Riwayat transaksi owner: kerangkanya juga muncul saat filter diubah.** Mengubah filter mengirim ulang seluruh kunjungan, jadi prop yang ditunda hilang lagi dan kerangkanya tampil kembali. Itu bukan efek samping yang ditolerir, itu yang diinginkan: tanda bahwa isi tabel sedang diganti, alih-alih tabel lama yang diam-diam tertinggal di layar seolah masih jawaban atas filter yang baru.
- **Kontrak pembagiannya diuji, karena tidak ada hal lain yang akan menangkapnya.** `tests/Feature/DeferredPageDataTest.php` memeriksa untuk keempat halaman: mana yang harus ada di respons pertama, mana yang harus absen, dan bahwa yang absen benar-benar sampai di permintaan lanjutannya (termasuk per grup, `default` vs `badges` vs `rekap`). Tanpa berkas ini, mengembalikan satu prop menjadi eager akan membuat kerangkanya tidak pernah terlihat lagi **tanpa satu pun tes gagal** — kemunduran yang paling mudah terjadi dan paling sulit disadari.
- **File Terdampak:**
  - `resources/js/Components/Skeleton/Skeleton.vue` — primitif: `bg-muted`, skala radius (`none` untuk sudut satu sisi), `animate-pulse motion-reduce:animate-none`, `aria-hidden`.
  - `resources/js/Components/Skeleton/SkeletonText.vue` — tumpukan baris teks; baris penutup lebih pendek supaya terbaca sebagai prosa, bukan tabel.
  - `resources/js/Components/Skeleton/SkeletonPanel.vue` — kulit kartu + penampung judul; `flush` untuk isi yang menempel tepi (tabel dan daftar baris), `action` untuk judul yang berpasangan dengan tautan.
  - `resources/js/Components/Skeleton/SkeletonCard.vue` — satu kartu; `media` (kartu produk POS), `icon` (kartu alert), `borderWidth` menyalin ketebalan stroke kartu aslinya.
  - `resources/js/Components/Skeleton/SkeletonGrid.vue` — pengulang kartu di grid yang sama; string `columns` disalin apa adanya dari grid yang digantikan.
  - `resources/js/Components/Skeleton/SkeletonTable.vue` — `<table>` sungguhan supaya kolomnya dibagi peramban seperti nanti; lebar selnya bersiklus dari pola tetap, bukan acak.
  - `resources/js/Components/Skeleton/SkeletonList.vue` — baris daftar: kotak depan, dua baris teks, pasangan nilai/meta di kanan.
  - `resources/js/Components/Skeleton/SkeletonChart.vue` — batang setinggi kanvas aslinya, dari pola tetap: kerangka tidak boleh mengarang tren.
  - `app/Http/Controllers/Cashier/POSController.php` — `products` + `upsell` ke `Inertia::defer()` dalam satu grup; kategori dan metode bayar tetap eager, dengan alasannya ditulis di tempat.
  - `app/Http/Controllers/Owner/DashboardController.php` — `dailyTrend` + `recentTransactions` (grup `default`) dan `badges` (grup sendiri) ditunda; metrik dan ringkasan langganan tetap eager.
  - `app/Http/Controllers/Owner/ReportController.php` — `daily()`: ringkasan pindah ke agregat, `transactions` ditunda, `paymentSummary` + `topProducts` ke grup `rekap`; `transactions()`: paginasinya ditunda.
  - `resources/js/Pages/Cashier/POS.vue` — kerangka grid produk lewat `catalogReady`, panen snapshot pindah dari `onMounted` ke `watch`.
  - `resources/js/Pages/Owner/Dashboard.vue`, `resources/js/Pages/Owner/Reports/Daily.vue`, `resources/js/Pages/Owner/Transactions/Index.vue` — tiap bagian yang ditunda dibungkus `<Deferred>` dengan kerangkanya sebagai `#fallback`.
  - `DESIGN.md` — bagian baru §6 "Loading States (Skeletons)" (daftar komponen + enam aturan bernama), "Do's and Don'ts" bergeser ke §7 dan bertambah dua Do serta tiga Don't.
  - `tests/Feature/DeferredPageDataTest.php` — 4 test baru: pembagian eager/deferred keempat halaman, per grup, plus angka ringkasan laporan harian yang kini datang dari agregat.
  - `tests/Feature/Upsell/UpsellEventTest.php` — pemeriksaan `upsell.mandatory` pindah ke dalam `loadDeferredProps()`.
- **Yang masih terbuka:** `[BL-037]` **belum ditutup**. Ia kini memegang tabel "Pelacakan Penerapan" berisi sisa halaman beserta prop yang harus ikut ditunda di masing-masingnya — dipimpin `Cashier/TransactionHistory` dan `Owner/Products/Index`. Dua tempat yang punya kerangka buatan sendiri sebelum sistem ini ada (`TransactionEditModal.vue:193` dan `Owner/AiAnalysis/Index.vue:324`) belum dimigrasikan; keduanya tercatat di tabel itu. Progress bar di `app.js` sengaja tidak disentuh: ia masih satu-satunya penanda untuk halaman yang belum punya kerangka, dan mematikannya lebih dulu akan menghapus umpan balik alih-alih menggantinya.

---

### [ADDITION] Sisa Kuota AI Pindah ke Halaman yang Membelanjakannya, dan Penolakannya Pindah dari Antrean ke Layar (BL-062)
- **Tanggal:** 2026-08-13
- **Fase Terkait:** Di Luar Fase — `[BL-062]`
- **Dampak:** Service | Controller | Frontend | Test
- **Breaking Change:** Tidak. Prop `aiFreeTier` di halaman Pengaturan berganti nama jadi `aiQuota` dan berisi lebih banyak kunci; tidak ada API publik atau skema yang tersentuh.
- **Deskripsi:** Kuota AI dibelanjakan di `/owner/ai-analysis` tapi satu-satunya tempat sisanya bisa dilihat adalah `/owner/settings`. Akibatnya bukan sekadar tidak nyaman: karena penolakan kuota terjadi di dalam `RunAiAnalysisJob`, owner menekan "Analisa", melihat statusnya `pending`, lalu beberapa detik kemudian menemukan analisisnya `failed` — tanpa pernah diberi tahu di layar itu bahwa jatahnya memang sudah nol sejak sebelum ia menekan tombolnya. Angka yang bisa mencegah itu sudah dihitung dan sudah punya kelasnya sendiri; ia hanya tidak pernah dikirim ke halaman yang membutuhkannya.

- **Satu blok data, dua layar, dan sengaja begitu.** `AiQuota::snapshotFor()` jadi satu-satunya perakit keadaan kuota: `using_free_tier`, `daily_limit`, `used`, `remaining`, `limit_source`, `plan_name`. Merakitnya di masing-masing controller berarti syarat BYOK ditulis dua kali, dan yang pertama kali salah menuliskannya akan memasang "sisa 0 dari 5" di layar tenant yang justru tak berbatas. Pertanyaan "perlu dijatah?" tetap didelegasikan ke `AiProviderFactory::isUsingFreeTier()`, tidak ditiru — batas kelas yang sudah ditulis di docblock `AiQuota` tetap berlaku, `snapshotFor()` hanya menyusun jawabannya.
- **Kuotanya dikirim dari `index()` DAN `show()`.** Keduanya me-render komponen yang sama, jadi melewatkan salah satunya membuat angkanya hilang begitu satu analisis dibuka lewat tautannya. Ia juga ikut dalam daftar prop yang disegarkan polling (`only: ['active', 'analyses', 'aiQuota']`), karena jatah baru terpotong saat analisisnya **berhasil** — di antrean, bukan saat tombol ditekan. Tanpa itu meternya memperlihatkan angka dari sebelum analisis yang baru saja selesai.
- **Penolakannya pindah ke `store()`, dan tidak menghapus penolakan di antrean.** Job tetap memeriksa hal yang sama — ia harus, karena bisa mengantre lebih lama daripada jatah yang tersisa — tapi penolakan di sana lahir sebagai baris `failed` di riwayat owner yang sebetulnya bisa dicegah sebelum apa pun dibuat. Kini permintaan tanpa jatah berbalik dengan flash `error` dan **nol baris** `ai_analyses`. Kalimatnya sejalan dengan yang dilontarkan `assertQuota()`, supaya satu keadaan tidak menerima dua penjelasan berbeda.
- **Tombolnya mati di layar, dan itu bukan pengganti pagar servernya.** `:disabled` pada tombol "Analisa" adalah yang membuat penolakan terbaca sebelum diklik; pemeriksaan di `store()` adalah yang membuatnya tetap benar saat permintaan datang tanpa lewat tombol itu.
- **Tenant ber-BYOK melihat keterangan lain, bukan angka nol.** "Memakai kunci API sendiri — tanpa batas kuota harian", tanpa meter, dengan tombol tetap hidup — dan `used` dikirim nol, bukan angka basi dari masa sebelum kuncinya diisi.
- **Batas nol dibedakan dari kuota habis.** Paket yang memang tidak menyertakan analisis AI (`plans.limits.ai_daily = 0`) berbunyi "Paket ini tidak menyertakan analisis AI" dengan jalan keluar "naikkan paket", bukan "kuota habis, coba lagi besok" — kalimat yang menyuruh owner menunggu sesuatu yang tidak akan datang.
- **Di Pengaturan ia TIDAK dicabut, malah dibedah.** Di sana angkanya adalah konteks untuk keputusan BYOK; di AI Analysis ia peringatan sebelum bertindak. Dua pembaca, dua maksud. Versi Pengaturan menampilkan batas/terpakai/sisa, meter pemakaian berpersentase, asal batasnya (nama paket, atau bawaan platform), dan dua hal yang sebelumnya tidak pernah dikatakan di mana pun: jatah tidak menumpuk ke hari berikutnya, dan hanya analisis yang berhasil yang memotong kuota.
- **Panelnya berhenti disembunyikan saat kunci BYOK terisi.** Sebelumnya blok kuota lenyap begitu `ai_key_set` benar, jadi tepat di keadaan ketika owner sedang menimbang untuk melepas kuncinya, layar tidak mengatakan apa yang akan ia dapatkan kembali. Kini keadaan BYOK punya isinya sendiri, lengkap dengan angka kuota yang berlaku bila kolom kuncinya dikosongkan.
- **Ambang "hampir habis" ada di 34% sisa.** Di bawah itu meternya berubah amber sebelum berubah merah di nol. Peringatan yang baru muncul saat jatahnya benar-benar nol bukan peringatan, itu pemberitahuan.
- **File Terdampak:**
  - `app/Services/Ai/AiQuota.php` — `snapshotFor()` + injeksi `AiProviderFactory`; docblock kelasnya diperluas untuk menamai pengecualian yang disengaja itu.
  - `app/Http/Controllers/Owner/AiAnalysisController.php` — `aiQuota` di `index()` dan `show()`, penolakan kuota di `store()`, plus penolong `quotaSnapshot()` dan `quotaRejection()`.
  - `app/Http/Controllers/Owner/SettingsController.php` — `aiFreeTier` (dua kunci) diganti `aiQuota` (blok penuh).
  - `resources/js/composables/useAiQuota.js` — penurun keadaan (`byok` / `unavailable` / `empty` / `low` / `ok`), dipakai bersama oleh meternya dan oleh tombol yang dimatikan supaya keduanya tidak bisa berselisih.
  - `resources/js/Components/AiQuotaMeter.vue` — dua varian dari satu komponen: `compact` (AI Analysis) dan `detailed` (Pengaturan).
  - `resources/js/Pages/Owner/AiAnalysis/Index.vue` — meter ringkas sebaris dengan tombol kirim, tombol ber-`:disabled`, dan `aiQuota` ikut disegarkan polling.
  - `resources/js/Pages/Owner/Settings/Index.vue` — blok satu baris lama diganti panel lengkapnya.
  - `tests/Feature/Owner/AiAnalysisControllerTest.php` — 6 test baru: kuota terkirim di `index()` dan `show()`, BYOK dibaca tanpa batas, penolakan di layar tanpa baris `failed` dan tanpa job, paket tanpa jatah AI, dan BYOK yang tidak pernah ditolak.
  - `tests/Feature/Owner/SettingsAiTest.php` — nama prop diperbarui, plus BYOK (`used` nol) dan batas yang bersumber dari paket.
- **Yang TIDAK dikerjakan:** saran aslinya menyinggung "menu AI di analysis". Menu `/ai-analysis` sudah ada di `OwnerLayout`; kalau yang dimaksud menu **turunan** (mis. riwayat vs buat baru), itu permintaan terpisah yang perlu diperjelas lebih dulu dan tidak ikut di sini.

---

### [ADDITION] Laporan Bulanan: Satu Bulan Kalender, Diagregasi di Basis Data, dengan Unduhan CSV (BL-063)
- **Tanggal:** 2026-08-13
- **Fase Terkait:** Di Luar Fase — `[BL-063]`
- **Dampak:** Controller | Rute | Frontend | Test
- **Breaking Change:** Tidak. Laporan harian, tren 7 hari di dashboard, dan seluruh permukaan lama tidak disentuh.
- **Deskripsi:** Sebelum ini setiap laporan di aplikasi menjawab pertanyaan yang sama — "hari ini bagaimana". Tidak ada satu pun yang menjawab "bulan ini bagaimana", padahal itu satuan yang dipakai pemilik toko saat menghitung sewa, gaji, dan setoran; yang paling dekat adalah tren 7 hari di dashboard, terlalu pendek untuk membedakan pola akhir pekan, apalagi tanggal muda dari tanggal tua. `/owner/reports/monthly` mengisi lubang itu.

- **Bulan kalender, bukan periode langganan — dan keduanya sengaja tidak bertemu di satu layar.** Periode langganan tenant berjangkar di tanggal daftar (keputusan 2026-08-07), jadi "bulan ini" versi tagihan bukan tanggal 1–31. Laporan ini operasional: yang dipakai menghitung sewa dan gaji adalah bulan kalender, dan mencampur dua definisi "bulan" di satu halaman hanya melahirkan dua angka omzet yang sama-sama benar dan saling membantah. Periode langganan tetap milik `/langganan`.
- **Tidak ada satu baris transaksi pun yang dimuat ke memori.** `daily()` boleh menarik transaksinya satu per satu karena sehari muat; sebulan di tenant yang ramai tidak. Seluruh isi halaman datang dari empat query agregat — deret harian, total bulan pembanding, rekap metode bayar, dan produk terlaris — dan angka ringkasannya (omzet, jumlah transaksi, void, rata-rata) diturunkan dari deret harian yang sudah jadi, bukan dari query baru per angka.
- **Deret hariannya memuat SETIAP tanggal, termasuk yang nol.** Hari tutup yang hilang dari deret akan tersambung jadi garis lurus begitu grafiknya dipasang (`[BL-064]`), dan garis itu terbaca seolah toko tetap ramai. Nol yang eksplisit membuat deretnya jujur sebelum ada yang menggambarnya.
- **Angkanya memakai tanggal penjualan sebenarnya.** Rekapnya berdiri di atas `effectiveDateSql()`, jadi penjualan offline yang baru tersinkron di bulan berikutnya tetap dihitung di bulan saat transaksinya terjadi — cacat yang paling mahal untuk ditemukan belakangan justru tidak pernah diwarisi.
- **Perbandingan dengan bulan sebelumnya ikut, dan "tidak ada pembanding" ditulis apa adanya.** Rekap bulanan tanpa pembanding hanya angka besar tanpa arti. Tapi tumbuh dari nol bukan "naik 100%": bulan pembanding yang kosong menghasilkan `null`, dan layarnya menulis "Tidak ada pembanding", bukan persentase yang dikarang.
- **"Hari berjualan", bukan jumlah hari kalender.** Rata-rata omzet harian dibagi dengan hari yang benar-benar ada transaksinya. Toko yang libur enam hari tidak boleh terbaca seperti toko yang sepi sebulan penuh.
- **CSV-nya berisi persis yang ada di layar, dalam empat blok bersekat.** Rekap bulanan yang tidak bisa dibawa ke spreadsheet akan tetap disalin ulang dengan tangan. BOM UTF-8 dipasang di depan supaya Excel tidak membacanya sebagai ANSI dan mengacak nama produk beraksen.
- **Bulan yang belum terjadi tidak bisa dipilih.** Tombol "bulan berikutnya" mati di bulan berjalan dan bulan-bulan di depan padam di panel pemilih. Laporan kosong tanpa sebab adalah cara termudah membuat aplikasi yang sehat terlihat rusak.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/ReportController.php` — `monthly()`, `monthlyExport()`, plus penolong privat `resolveMonth()`, `dailySeriesFor()`, `summarizeSeries()`, `monthTotals()`, `deltaPercent()`, `paymentSummaryFor()`, `topProductsFor()`, `monthLabel()`.
  - `routes/web.php` — `reports.monthly` dan `reports.monthly.export`, di dalam grup `permission:reports` yang sudah ada.
  - `resources/js/Pages/Owner/Reports/Monthly.vue` — halamannya: kartu ringkasan, ritme bulan, tabel pembanding, rekap metode bayar, produk terlaris, rincian harian (tiap baris bertaut ke laporan hariannya).
  - `resources/js/Components/MonthPicker.vue` — pemilih bulan (panah maju/mundur + panel 12 bulan), mengikuti pola `DatePicker.vue`.
  - `resources/js/Layouts/OwnerLayout.vue` — menu "Laporan Bulanan" di grup Keuangan, plus ikon `trending-up`.
  - `tests/Feature/Owner/ReportTest.php` — 10 test baru: batas bulan, tanggal efektif vs tanggal sync, void terpisah dari omzet, hari nol tetap ada di deret, pembanding bulan lalu (termasuk yang tanpa pembanding), parameter tak terbaca jatuh ke bulan berjalan, isolasi antar-tenant, dan isi CSV-nya.
- **Yang masih terbuka:** grafik garisnya belum dipasang — itu `[BL-064]`, dan `dailySeries` di payload memang sudah disiapkan untuk jadi sumbernya. Sampai grafiknya mendarat, deret itu dibaca lewat tabel "Rincian Harian". Laba kotor sengaja tidak ikut: `ProfitService` sudah ada, tapi COGS-nya bersandar pada `cost_price` yang tidak semua tenant isi, dan margin 100% palsu di laporan bulanan lebih buruk daripada tidak ada angka margin sama sekali.

---

### [HOTFIX] `price_locked` Nol Berhenti Dibaca Sebagai Tarif Rp 0 (BL-041)

**Tanggal:** 2026-08-10 · **Area:** Langganan · **Menutup:** catatan terakhir `[BL-041]` ("dua tenant lama memegang `price_locked = 0.00`, dan itu belum diputuskan")

**Masalahnya.** `price_locked` bertipe `decimal(12,2)`, jadi `0.00` **bukan** `null` — dan `price_locked ?? plan->base_price`, ungkapan yang dipakai halaman langganan untuk menjawab "berapa tarif Anda", karena itu tidak pernah jatuh ke tarif paket. Tenant `Kopi Story` (paket `paid-1`, `base_price` Rp 100.000) membaca **"Rp 0/bulan"** di `/langganan` sementara `issueDuePeriodInvoices()` menyiapkan tagihan Rp 100.000 untuknya. Bukan angka yang kurang tepat: layar yang membantah tagihan, pada satu-satunya halaman yang dibuka tenant untuk mengetahui berapa yang harus ia bayar.

**Keputusannya: `price_locked = 0` adalah cacat data, bukan grandfathering yang perlu dihormati.** Tiga hal yang bersama-sama menutup pembacaan sebaliknya:

- **Tak ada jalan yang memutuskannya.** Tagihan langganan Rp 0 tidak bisa lahir sendiri — `issueDuePeriodInvoices()` menolak menerbitkannya (`$amount <= 0.0`), dan `settleIfFree()` sengaja menolak melunasinya. Yang tersisa hanya pemilik SaaS yang mengetik tagihan Rp 0 di panel (`min:0`), dan itu sudah dinamai bahaya oleh docblock-nya sendiri sejak `[BL-049]` — bukan kebijakan.
- **Nol yang benar-benar ada di basis data artinya "belum diketahui", bukan "nol".** Keduanya ditulis di tempat yang tidak pernah tahu tarifnya: backfill migrasi `2026_07_24_181638` (`'price_locked' => 0` untuk tenant yang sudah ada) dan bawaan `SubscriptionFactory`. Yang jujur adalah `null` — persis yang ditulis `startTrial()`.
- **Ia tidak meng-grandfather apa pun sekalipun dianggap sah.** Penagih tidak pernah membaca `price_locked`; `issueDuePeriodInvoices()` selalu menghitung ulang lewat `resolveFor()` (koreksi 2026-08-07 di `[BL-041]`). Memajang Rp 0 karena itu memajang tarif yang tidak akan dihormati siapa pun.

**Dikerjakan dua-duanya — sumbernya dan layarnya.** Menambal salah satu saja meninggalkan separuh cacatnya hidup: hanya menambal layar membiarkan nol baru terus lahir, hanya menambal sumbernya membiarkan dua baris yang sudah nol tetap berbohong di layar.

- **`InvoiceSettlement::settle()` berhenti menulis `price_locked` dari nominal nol.** Perpanjangan periode dan pengaktifan tenant TIDAK ikut dicabut: yang diputuskan orang yang menerbitkan tagihan Rp 0 adalah "bulan ini gratis", bukan "tarifnya nol mulai sekarang". Mencabut keduanya sekaligus justru meninggalkan tenant di masa tenggang atas tagihan yang sudah lunas.
- **`Subscription::effectivePrice()` jadi satu-satunya rumah aturannya**, dan membaca `price_locked` non-positif sebagai kosong. Diletakkan di model, bukan di controller, karena ungkapan `?? ` itu sudah tersalin ke lebih dari satu tempat — termasuk ke dalam template Vue-nya — dan jebakan "nol yang bukan null" akan terlewat di penyalinan berikutnya.
- **Kedua halaman yang menjawab "berapa tarif Anda" memanggilnya, bukan menghitung sendiri.** `Billing\SubscriptionController` (`/langganan`) dan `Billing\AdaptiveController` (`/langganan/harga-adaptif`) sama-sama menyalin ungkapan itu berikut jebakannya; keduanya kini tidak bisa lagi berselisih, dan ada test yang menjaganya.
- **Halaman langganan menerima satu angka jadi.** `subscription.price_locked` dan `subscription.base_price` diganti `subscription.effective_price`; `Billing/Show.vue` tidak lagi menyatukan dua angka mentah sendiri.

**Tenant yang memang gratis tetap terbaca Rp 0.** Ia tinggal di paket `free`, yang `base_price`-nya juga nol — jadi menganggap nol sebagai kosong tidak pernah membesarkan tarif siapa pun di layar. Itu yang membuat aturannya aman diterapkan tanpa memandang paket.

**Akibat kedua yang ikut terperbaiki, dan justru yang paling merugikan.** `$effectivePrice` juga masuk ke `SubsidyEstimator::estimateFor()` sebagai tarif pembanding. Terhadap Rp 0 palsu, bracket Rp 25.000 terbaca **lebih mahal** — halamannya lalu memberi tahu tenant yang paling butuh keringanan bahwa mengajukannya tidak menguntungkan. Ada testnya.

**Yang sengaja TIDAK dikerjakan.** (1) **Baris nol yang sudah ada tidak dinormalkan jadi `null` lewat migrasi.** Sejak pembacaannya diperbaiki ia tidak lagi menyesatkan siapa pun, dan menulisi kolom uang untuk kerapian saja bukan alasan yang cukup. (2) **Panel platform tetap memajang kolomnya apa adanya** (`AccountOverview`, `Platform/Tenants/Show.vue`) — di sana yang ditanyakan memang isi kolomnya, bukan tarif yang berlaku. Satu catatan tersisa di sana: petunjuk "Terkunci sejak pembayaran terakhir." berbunyi untuk nilai nol yang tak pernah berasal dari pembayaran mana pun.

**Verifikasi:** `tests/Feature/Subscription/EffectivePriceTest.php` — 9 test, dua arah sekaligus. Nol dibaca sebagai kosong, **dan** harga terkunci yang sungguhan (Rp 50.000 di bawah tarif paket Rp 100.000) tetap dihormati; perbaikan yang hanya menegakkan yang pertama akan diam-diam mencabut grandfathering setiap tenant yang pernah menyepakati tarif di bawah tarif paketnya.

---

### [ADDITION] Pengajuan Harga Adaptif Punya Halaman, Dinilai Seketika, dan Berujung pada Ambang (BL-055)

**Tanggal:** 2026-08-10 · **Area:** Langganan · **Menutup:** `[BL-055]`, sisa `[BL-048]`(b), pemadam kedua `[BL-054]`(c)

**Masalahnya.** Sejak paket berbayar termurah Rp 100.000, tenant yang tidak sanggup membayarnya tidak punya jalan lain selain jalur Adaptif — dan jalur itu hanya bisa dimasuki lewat halaman persetujuan yang tidak pernah menyebut dirinya sebagai pengajuan keringanan dan tidak menampilkan satu pun angka. Jembatan satu-satunya dari Rp 0 ke tarif berbayar tidak punya papan nama.

**Tangganya dibaca dari data, dan ambangnya diturunkan dari tangga itu.** `PricingService::adaptiveLadder()` menyusun anak tangga dari `pricing_rules` — label, tarif, batas bawah, batas atas — dan `adaptiveCeiling()` mengambil batas atas tertingginya. Keduanya membaca lewat `effectiveRules()`, kumpulan yang **persis sama** dengan yang dipakai `matchContext()` menetapkan harga; tangga yang dipajang dari query sendiri akan menampilkan revisi lama sebuah bracket sementara tagihannya memakai revisi baru. Ambangnya karena itu tidak pernah jadi angka kedua yang harus dijaga tetap sinkron: menggeser `lt` pada bracket teratas dari `/platform/pricing-rules` menggeser ambangnya juga.

**Arah gagalnya ditegakkan, bukan diasumsikan.** Peringatan `[BL-048]` terpasang sebagai kode: kelayakan diperiksa dari **angka omzet terhadap ambang**, bukan dari `price === null`. Bracket yang lupa diberi batas atas membuat `adaptiveCeiling()` mengembalikan `null` — "tak ada ambang", bukan "ambangnya nol" — sehingga tidak seorang pun ditolak. Satu baris syarat yang salah ketik karena itu tidak bisa lagi diam-diam mendorong seluruh tenant ke paket berbayar penuh. Ada testnya, dan itu test yang paling penting di berkasnya.

**Kelayakan dijawab satu kali, dengan ALASANNYA.** `AdaptiveEligibility` menjawab satu pertanyaan — apakah omzetnya di atas ambang — untuk dua arah yang berlawanan, dan sumber angkanya berbeda karena gerbang privasinya berbeda: tenant yang **mengajukan** dinilai dari `SubsidyEstimator` (dihitung untuk mata pemiliknya sendiri, dalam permintaan itu juga, tidak pernah disimpan), tenant yang **sudah di dalam** dinilai dari `tenant_monthly_metrics`. `SubscriptionService::adaptiveVerdict()` merangkainya jadi `eligible` / `active` / `cooldown` / `above_ceiling`. Ketiga penolakan punya jalan keluar yang berbeda — menunggu, membayar penuh, atau tidak melakukan apa-apa — dan layar sekarang menyebutkan yang mana, termasuk angkanya: "Omzet Anda Rp 80.000.000, di atas batas Rp 50.000.000 untuk keringanan."

**Penilaiannya seketika, bukan tanggal 1.** `switchToSubsidized()` memanggil `ComputeTenantMonthlyRevenue::recordFor()` begitu jalurnya berpindah. Sebelumnya tenant yang baru menyerahkan data omzetnya demi keringanan mendarat di halaman yang berkata "omzet Anda belum dihitung" sampai empat minggu — tepat pada orang yang mengajukan karena tidak sanggup membayar bulan ini. Gerbang privasi dua lapisnya diperiksa ulang **di dalam** `recordFor()`, bukan dipercayakan kepada pemanggil: syarat yang hanya hidup di query `handle()` akan dilewati pemanggil kedua yang lupa.

**Melewati ambang berarti pemberitahuan dulu, pindah kemudian.** `reviewAdaptiveCeiling()` berjalan paling akhir di `advanceLifecycle()` dan menandai tenant Adaptif yang omzetnya melewati ujung tangga dengan `track_reverts_at = current_period_end`. Urutannya mengikat: lebih dulu, tenant yang baru ditandai hari ini akan ikut tersapu loop pengembalian di jalan yang sama dan pindah paket di detik yang sama ia diberitahu. Tarif periode berjalan tidak disentuh — `price_locked` dan `invoices.pricing_context` memang dibangun supaya harga berjalan bisa dipertanggungjawabkan.

**Kolom baru `subscriptions.track_revert_reason`, dan kenapa ia harus disimpan.** Pencabutan sukarela dan pemindahan ambang sama-sama berakhir di jalur normal, tapi hanya yang kedua ikut memindahkan paket ke penampung Adaptif. Bisa saja dibedakan dengan menebak dari keadaan lain — "consent-nya masih aktif, berarti ini pemindahan ambang" — dan tebakan itu benar hari ini lalu diam-diam salah pada sebab ketiga yang ditulis siapa pun kelak. Yang keliru bukan sebuah label di layar melainkan paket yang ditagihkan.

**Halaman `/langganan/harga-adaptif`.** Tarif yang berlaku, tagihan langganan terakhir sebagai bukti tarif itu benar-benar diterapkan, tanggal masuk jalur adaptif, tangga bracket lengkap dengan anak tangganya sendiri ditandai, dan ambangnya disebutkan terus terang. Terbuka untuk staf juga — yang digerbang `role:owner` adalah tindakan menyetujuinya, bukan membaca alasannya. Halaman langganan berhenti menaut langsung ke dokumen persetujuan: meminta orang menyetujui pembukaan data penjualannya sebelum ia melihat tangga tarifnya adalah tukar-menukar yang tidak seimbang.

**Pagarnya di tempat perpindahan terjadi, bukan di layar.** `ConsentController::store()` menanyakan verdict yang sama, jadi satu POST tidak bisa melewati tombol yang tidak dirender. Ada testnya.

**Pemadam kedua `[BL-054]`(c) ikut terpasang.** Modal penagihan padam untuk tenant yang sudah mengajukan Adaptif — ia sudah melakukan persis hal yang diminta halaman itu, dan meneruskan teriakan kepadanya menghukum orang yang menurut. Seperti pemadam pertama, yang berhenti hanya notifikasinya; jam tenggatnya jalan terus, karena kalau tidak, mengajukan lalu mendiamkannya jadi cara membeli waktu tanpa membayar.

**Yang sengaja TIDAK diputuskan.** `[BL-056]` — pengajuan berlaku untuk bulan pengajuan atau bulan berikutnya — tetap terbuka dan menunggu keputusan pemilik. Yang mendarat mengikuti apa yang sudah dijanjikan kode dan kalimat suksesnya sejak awal: **berlaku mulai periode berikutnya**. Memilih opsi (ii) di sini akan menjawab pertanyaan pemilik atas namanya sendiri, dan opsi itu menuntut aturan tegas soal tagihan yang sudah sebagian dibayar. Butir (d) `[BL-055]` — pemeriksaan ulang berkala yang menaikkan tenant saat omzetnya naik — sudah berjalan sejak awal lewat `subscriptions:compute-revenue` bulanan plus `resolveFor()` yang dihitung ulang tiap penerbitan tagihan; yang belum ada dan kini ada adalah arah sebaliknya, keluar dari tangga.

**Test:** 18 test baru di `tests/Feature/Subscription/AdaptiveApplicationTest.php`. Suite penuh 822 lulus.

---

### [ADDITION] Seat Tambahan Jadi Komponen Bulanan, dan Untuk Pertama Kalinya Bisa Dilepas (BL-053)

**Tanggal:** 2026-08-08 · **Area:** Langganan · **Menutup:** `[BL-053]` butir (a)+(b), `[BL-050]`

**Masalahnya.** `extra_seat_price` Rp 15.000 dipasang 2026-08-07 dengan maksud "per bulan", tapi yang dikerjakan kode adalah "sekali bayar": `requestSeatUpgrade()` menerbitkan satu tagihan `KIND_UPGRADE` senilai `harga × jumlah`, lalu seat-nya melekat permanen. Nilainya benar, satuannya salah — dan tiap seat yang dibeli selama itu jadi kesepakatan permanen yang jauh lebih murah daripada yang dimaksud. Tagihan langganan bulanan sendiri hanya memuat harga paket, tanpa komponen seat apa pun.

**Dasar tagihannya: yang dibeli, bukan yang dipakai.** Keputusan pemilik pertama 2026-08-07 mengarah ke puncak pemakaian (`seat_high_water − included_seats`); keputusan kedua di hari yang sama membatalkannya. Seat tambahan ditagih **karena dibeli, terpakai atau tidak** — paket memberi 3, tenant membeli 2, yang dipakai baru 4: tagihannya tetap 5 seat. Konsekuensinya kolom baru `subscriptions.purchased_extra_seats`, karena angka yang jadi dasar uang tidak boleh disimpulkan dari selisih `seats − included_seats` yang ikut bergerak tiap kali tenant berpindah paket.

`seat_high_water` **tidak dihapus**, berbeda dari yang diusulkan entri backlognya. Ia masih punya pembaca: `ActiveSeatsResolver` memakainya sebagai dimensi `active_seats` untuk aturan Harga Adaptif. Menghapus kolomnya akan mengubah pencocokan aturan harga, bukan sekadar membersihkan kolom mati. Yang dicabut adalah perannya di penagihan, bukan kolomnya.

**Yang berjalan sekarang:**

- `issueDuePeriodInvoices()` menagih `harga + seat tambahan × tarif seat`, dengan pecahannya dibekukan di `invoices.pricing_context.billing_breakdown`. Penjaga "tidak ada yang perlu ditagih" kini memeriksa **totalnya**, bukan tarif paketnya — tenant di paket Rp 0 yang membeli seat punya nominal yang benar-benar harus dibayar.
- Tarif per seat selalu dari **paket**, termasuk untuk tenant Adaptif. Yang didiskon jalur Adaptif adalah harga langganannya, bukan harga penggunanya: tenant Adaptif di `paid-1` membayar Rp 15.000/seat meski langganannya turun ke Rp 10.000.
- **Membeli** (`grantSeats()`): berlaku seketika, gratis sampai periode berjalan habis, masuk tagihan bulanan berikutnya. Tak ada lagi tagihan di tengah bulan, bukti transfer, atau antrean pemeriksaan untuk seat.
- **Melepas** (`releaseSeats()`), yang sebelumnya tidak ada sama sekali dan jadi wajib begitu tagihan mengikuti pembelian — tanpanya tenant terkunci membayar selamanya. Berlaku **satu periode penuh ke depan**, bukan akhir periode berjalan: tagihan periode berikutnya terbit `invoice_lead_days` sebelum periode berjalan habis dan sudah memuat seat itu, jadi melepasnya lebih awal akan menagih kursi yang sudah dicabut. Sekaligus menutup celah "beli tanggal 1, lepas tanggal 2" — tiap seat yang dibeli pasti tertagih sekali, tidak pernah nol kali.
- Seat yang masih diduduki staf aktif **ditolak** pelepasannya, dengan kalimat yang menyebut angkanya. Mematikan akun kasir di tengah jam kerja sebagai efek samping penghematan tagihan adalah kerugian yang jauh lebih besar daripada sebulan tagihan yang tertunda.
- Membeli lagi **membatalkan** pelepasan yang sedang menunggu — tenant yang berubah pikiran jelas tidak sedang meminta keduanya.

**Halaman langganan berhenti bicara soal pemakaian puncak.** Yang ditampilkan adalah hak beserta asalnya: "6 kursi — 3 dari paket Paid 1, 3 kursi tambahan yang Anda beli (Rp 15.000/kursi/bulan = Rp 45.000/bulan). Terpakai 4, tersisa 2." "Terpakai" tinggal jadi informasi — petunjuk kapan kursi sebaiknya dilepas — bukan angka yang memengaruhi tagihan.

**`[BL-050]` ikut tertutup tanpa menyentuh skemanya.** Batas "satu penambahan pengguna per bulan kalender" lahir sebagai efek samping indeks unik `(tenant_id, period, kind)`. Tanpa tagihan upgrade yang lahir, batas itu tidak punya objek lagi; `hasUpgradeInvoiceThisPeriod()` beserta prop `closed_for_period` dan kalimatnya di layar dibuang. Perubahan skema `period_key` yang diusulkan entri itu tidak jadi diperlukan.

**Yang sengaja ditinggalkan hidup.** `KIND_UPGRADE`, `applyProvisionalUpgrade()`, `revertUpgrade()`, `provisional_blocked`, dan `subscriptions:settle-free-upgrades` tetap ada — tagihan yang terbit sebelum hari ini dan belum selesai masih harus bisa diverifikasi, ditolak, dan dilunasi. Yang berhenti adalah penerbitannya. `UpgradeController::store()` menolak pembelian baru selama masih ada tagihan upgrade lama yang menggantung: melunasinya menulis `seats = grants_seats`, angka dari dunia lama yang akan menurunkan jatah tenant yang baru saja membeli.

**Backfill.** `purchased_extra_seats` diisi dari `max(0, seats − plan.included_seats)` — satu-satunya bukti yang ada untuk baris lama, dan persis angka yang selama ini ditegakkan kepada mereka. Dijalankan 2026-08-08: `Kopi Nusantara` dan `Kopi Story` masing-masing 3 seat tambahan. Tagihan 2026-08-17 keduanya menjadi **Rp 145.000** (Rp 100.000 + 3 × Rp 15.000), naik dari Rp 100.000.

**Yang belum, dan ke mana perginya.** Butir (c) — kuota AI yang bisa dibeli bulanan — jadi `[BL-069]`, lengkap dengan hitungan ongkos per analisis yang diukur dari `ai_analyses.tokens_used` (±1.800 token, ± Rp 9 pada `gpt-4o-mini`) beserta temuan bahwa yang menentukan untung-rugi fitur AI adalah pilihan modelnya, bukan harga kuotanya. Prorata pembelian seat di tengah periode jadi `[BL-070]`: keputusan pemilik memilih "gratis sisa periode" sebagai yang paling sederhana, bukan yang paling adil, dan entri itu menahan pertanyaannya untuk ditinjau ulang dengan data pemakaian nyata. Celah "beli lalu lepas tanpa pernah bayar" tidak ikut terbuka — ia ditutup dari sisi pelepasan, bukan dari sisi prorata.

---

### [ADDITION] Masa Tenggang Jadi Tangga Tiga Tahap — Kasir Berhenti Mati di Hari Pertama (BL-054)
- **Tanggal:** 2026-08-08
- **Fase Terkait:** Di Luar Fase — `[BL-054]`, penegak keputusan pemilik 2026-08-07
- **Dampak:** Model | Middleware | Frontend | Test
- **Breaking Change:** Tidak, tapi **kebijakannya berubah arah**: tenant yang kemarin tidak bisa menyimpan apa pun di hari pertama tenggat, hari ini bisa berjualan sampai hari ke-20.
- **Deskripsi:** Config-nya sudah dipasang 2026-08-07 (`grace_intensive_from_day` = 15, `grace_lock_from_day` = 20) dan **tidak ada satu pun yang membacanya**. Penegaknya masih yang lama: `EnsureSubscriptionActive` memblokir seluruh permintaan non-GET begitu status tenant jadi `grace`, artinya kasir tidak bisa menyimpan satu transaksi pun sejak hari pertama. Warung yang tidak bisa berjualan tidak punya uang untuk membayar — tekanannya merusak sumber pembayarannya sendiri. Yang mendarat sekarang adalah pembacanya.

- **Yang menentukan nasib tenant sekarang UMUR tenggatnya, bukan statusnya.** `Tenant::graceDay()` menghitungnya dari `current_period_end`, dan `graceStage()` menerjemahkannya jadi `soft` / `intensive` / `locked` lewat ambang di config. Tidak dihitung sekali lalu disimpan: kolom "hari tenggat" menuntut sesuatu memutakhirkannya tiap tengah malam, dan sesuatu itu pasti gagal pada hari penjadwalnya tidak jalan.
- **Hari pertama tenggat adalah hari SETELAH periode berakhir.** Hari periodenya habis masih hari terakhir yang dibayar. Menghitungnya sebagai hari pertama menggeser seluruh tangga sehari lebih awal — termasuk tanggal penangguhan yang sudah lebih dulu dipakai `suspensionDateFor()`, kartu Dashboard, dan pita langganan.
- **`isReadOnly()` berhenti jadi sinonim `grace`.** Ia kini berarti "tahap `locked`", dan `canWrite()` meloloskan tenggat sampai ambang kunci. Keduanya tetap satu-satunya pintu — middleware, pita, modal, dan halaman kunci semuanya bertanya ke sana, jadi kebijakan tenggat tidak pernah punya dua versi.
- **Langganan yang datanya bolong menghasilkan `soft`, bukan `locked`.** Tenant `grace` tanpa `current_period_end` tetap boleh berjualan. Data langganan yang tidak lengkap adalah masalah kami, dan menutup kasir orang karena masalah kami adalah cara terburuk menemukannya.
- **Membaca data lama tidak dicabut di tahap mana pun** — termasuk tahap 3, dan ini menyimpang dari tabel di `[BL-054]` yang menulis "menu lain → halaman selesaikan tagihan". Tabel itu berselisih dengan prinsip yang ditulis pemilik di hari yang sama di `config/subscription.php`; **pemilik memutuskan prinsipnya yang menang** (2026-08-08). Yang diganti halaman kunci hanya **layar POS**, karena layar itu ada semata-mata untuk berjualan — laporan, riwayat, stok, dan seluruh ekspor tetap terbuka.
- **Halaman kuncinya dirender di URL kasir yang asli, bukan pengalihan.** `/cashier/pos` tetap `/cashier/pos`; pengalihan diam-diam ke halaman langganan membuat pengguna mengira aplikasinya rusak, dan menekan Muat Ulang harus mengembalikan kasir ke pekerjaannya begitu tagihannya lunas.
- **Notifikasi punya dua tingkat, dan yang paling halus sengaja bernada `info`.** Pita merah sepanjang hari kerja hanya melatih orang mengabaikannya, sehingga tahap yang benar-benar mencabut sesuatu tiba tanpa ada yang membacanya. Modal baru hanya muncul sejak tahap `intensive`.
- **Instruksi bayar yang masih berlaku memadamkan modalnya — dan tidak menyentuh jam tenggatnya.** Menagih orang yang sudah membayar adalah cara tercepat kehilangan mereka; tapi kalau menerbitkan instruksi bayar ikut menunda hari ke-20, menerbitkannya lalu mendiamkannya jadi cara membeli waktu tanpa membayar, berulang kali. Yang padam notifikasinya; hitungan harinya jalan terus.
- **File Terdampak:**
  - `app/Models/Tenant.php` — `graceDay()`, `graceStage()`, `isInGrace()`, konstanta `GRACE_STAGE_*`; `canWrite()` dan `isReadOnly()` ditulis ulang.
  - `app/Http/Middleware/EnsureSubscriptionActive.php` — penolakan tulis bersandar pada tahap, plus `LOCKED_STAGE_PAGES` yang merender `Billing/Locked`.
  - `app/Http/Middleware/HandleInertiaRequests.php` — payload `auth.tenant.subscription` membawa `stage`, `grace_day`, `grace_days`, `lock_from_day`, `can_write`, `payment_pending`; ia kini terbit untuk **seluruh** tahap tenggat, bukan hanya yang sudah terkunci.
  - `resources/js/Components/SubscriptionBanner.vue` — tiga nada mengikuti tahap; `resources/js/Components/GraceModal.vue` — modal tahap `intensive`/`locked`, dipasang di shell owner dan kasir.
  - `resources/js/Pages/Billing/Locked.vue` — halaman "selesaikan tagihan" beserta jalan kembali ke riwayat transaksi.
  - `database/factories/TenantFactory.php` — `readOnly()` berganti nama jadi `inGrace()`; nama lamanya sudah tidak benar.
  - `tests/Feature/Subscription/GraceStagesTest.php` — 11 test, seluruh angkanya dibaca dari config. Test lama yang mengunci kebijakan lama ikut diperbaiki di `SubscriptionLifecycleTest`, `SelfOrderSubscriptionGateTest`, dan `KitchenQueueTest` — masing-masing kini punya pasangan "hari awal tenggat tidak menutup apa pun".
- **Yang masih terbuka:** `[BL-054]`(c) menyebut modal juga padam bila **pengajuan Adaptif** sudah dilakukan. Alurnya belum ada (`[BL-055]`), jadi pemadamnya baru satu: instruksi bayar yang masih berlaku. Sambungkan saat `[BL-055]` mendarat.

---

### [ADDITION] Masa Gratis Berakhir dengan Perpindahan, Bukan dengan Jatuh ke Tenggang (BL-052)
- **Tanggal:** 2026-08-07
- **Fase Terkait:** Di Luar Fase — `[BL-052]`, turunan keputusan struktur harga hari yang sama
- **Dampak:** Schema | Model | Service | Controller | Frontend | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** `trial_ends_at` ditulis di `startTrial()` dan **tidak pernah dibaca lagi oleh siapa pun**. Akibatnya paket `free` tidak bisa hidup: tarifnya Rp 0, penerbit tagihan menghitungnya sebagai "tidak ada yang perlu ditagih" lalu melewatinya **tanpa memperpanjang periode**, periodenya lewat, dan `advanceLifecycle()` menurunkan tenant ke masa tenggang. Tiap periode, selamanya.

  Keputusan pemilik 2026-08-07 menutupnya dengan membatasi masa gratis jadi dua bulan lalu memindahkan tenant ke `paid-1` — tapi pemindahan itu tidak punya penggerak. `changePlan()` sudah ada sejak `[BL-046]`; tak ada satu pun yang memanggilnya otomatis. Yang mendarat sekarang adalah penggeraknya.

- **Urutannya yang jadi intinya, bukan perpindahannya.** `graduateExpiredTrials()` berjalan **sebelum** `issueDuePeriodInvoices()`, dan keduanya tak terpisahkan di dalam `advanceLifecycle()` — bukan dua baris jadwal yang kebetulan berurutan. Terbalik, tagihan berbayar pertama dihitung dari paket Rp 0, dilewati, dan tenantnya jatuh ke tenggang tanpa pernah melihat angka: persis bug yang sedang ditutup, tapi kali ini dengan kode yang terlihat benar.
- **Dipindahkan H-7, bukan sehari setelah masa gratis habis.** Tagihan periode berikutnya terbit `invoice_lead_days` = 7 hari lebih awal; menunggu `trial_ends_at` benar-benar lewat berarti penerbit sudah melihat tenant ini seharga Rp 0 dan melewatinya. Jendela yang sama sekaligus menjawab `[BL-052]`(c): tagihan yang tiba sepekan lebih awal, dengan nama paket barunya tertulis di halaman langganan, **adalah** pemberitahuan sebelum hari-H.
- **Sisa hari gratisnya tidak berkurang.** Periode berjalan tidak disentuh — tenant tetap tidak ditagih sampai `current_period_end`. Yang berubah hanya paketnya, dan `paid-1` lebih longgar daripada `free` di kedua sisi yang terasa (3 pengguna vs 2, 15 analisis AI/hari vs 5). Bila kelak ada paket tujuan yang lebih sempit dari paket gratis, jendela ini harus dipikirkan ulang — bukan angkanya, melainkan arahnya.
- **Tujuannya penanda di `plans`, bukan slug `paid-1` di kode** (`[BL-052]`(b)). Pola `is_adaptive_fallback` sudah membuktikan bentuknya: pemilik SaaS menunjuknya dari `/platform/pricing-rules`, jadi mengganti paket masuk tidak menuntut deploy. Kedua peran eksklusif itu kini berbagi satu `setExclusiveRole()` di `Plan` — ketunggalan yang disalin akan bercabang begitu salah satunya diperbaiki, dan cabang yang lebih longgar jadi perilaku sebenarnya.
- **Tanpa paket tujuan, perpindahannya berhenti dan bersuara.** Tidak jatuh diam-diam ke paket termurah: menebak berarti memindahkan tenant ke tarif yang tak seorang pun putuskan, lalu menagihkannya. Tenantnya dihitung `stranded`, dicatat sebagai kejadian sensitif, dan diperingatkan di keluaran perintah **serta** di panel harga. Penunjukan yang menunjuk balik ke paket gratis ditolak dengan cara yang sama — ia akan "memindahkan" tenant ke tempat yang sama tiap hari, dan lingkaran yang berjalan mulus jauh lebih sulit dikenali daripada perpindahan yang mengeluh.
- **Yang sengaja tidak ikut pindah:** tenant yang paketnya bukan `free` (tarif Rp 0 karena keringanan bukan masa gratis yang habis — yang menentukan paketnya, bukan angkanya), langganan tanpa `trial_ends_at` (memindahkan tenant yang tak pernah dijanjikan tanggal berakhir berarti menagihnya karena datanya tidak lengkap), dan tenant `suspended` (mengikuti penerbit tagihan).
- **File Terdampak:**
  - `database/migrations/2026_08_07_135635_add_post_trial_target_to_plans_table.php` — kolom `is_post_trial_target`, **beserta penunjukan awalnya ke `paid-1`**. Membiarkannya kosong berarti keadaan awal pemasangan berbentuk persis sama dengan bug yang baru ditutup.
  - `app/Models/Plan.php` — `postTrialTarget()`, `setPostTrialTarget()`, dan `setExclusiveRole()` yang kini dipakai bersama `setAdaptiveFallback()`.
  - `app/Services/SubscriptionService.php` — `graduateExpiredTrials()`; `advanceLifecycle()` memanggilnya lebih dulu dan melaporkan `graduated` + `stranded`.
  - `app/Console/Commands/AdvanceSubscriptionLifecycle.php` — baris perpindahan dilaporkan pertama (urutan keluaran mengikuti urutan kejadian), dan `stranded` muncul sebagai peringatan.
  - `app/Http/Controllers/Platform/PricingRuleController.php` + `resources/js/Pages/Platform/PricingRules/Index.vue` — kotak centang, lencana, dan peringatan "belum ada paket tujuan".
  - `app/Http/Controllers/Billing/SubscriptionController.php` + `resources/js/Pages/Billing/Show.vue` — `post_trial_plan`; kalimat masa coba berhenti berjanji "Anda bisa memilih untuk berlangganan" dan menyebut paket serta tarif yang akan berlaku.
  - `tests/Feature/Subscription/TrialGraduationTest.php` — 14 test; `tests/Feature/Platform/PricingGrandfatherTest.php` — jalur panelnya.
- **Yang masih terbuka:** `[BL-053]` (seat & kuota AI jadi komponen bulanan) tidak tersentuh — tagihan yang terbit di sini masih murni harga paket.

---

### [ADDITION] Pembayaran Peragaan Berhasil Sendiri: Tombol "Bayar Penuh" Turun Jadi Alat Pengembangan
- **Tanggal:** 2026-08-07
- **Fase Terkait:** Di Luar Fase — lanjutan `[BL-059]`, atas permintaan pemilik setelah alurnya dicoba
- **Dampak:** Service | Controller | Frontend | Config | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** Alur bayar yang mendarat pagi ini menuntut seseorang menekan tombol bernama **"Bayar penuh"** di panel berlabel "Panel peragaan". Untuk pengembangan itu benar; untuk diperagakan di depan calon klien itu salah — yang seharusnya terlihat adalah orang membayar dari aplikasi banknya, bukan operator menekan tombol yang mengaku peragaan.

  Sekarang pembayarannya **datang sendiri**: instruksi terbit → penantian dengan penanda "Memeriksa pembayaran…" → layarnya berubah jadi lunas. Tidak ada yang perlu ditekan, dan tidak ada langkah yang perlu dijelaskan ke penonton.

- **Yang TIDAK berubah, dan itu intinya:** pelunasan otomatis memakai jalur yang sama persis — notifikasi JSON bertanda tangan HMAC, lewat `PaymentWebhookController`, dengan verifikasi tanda tangan, penjaga idempotensi, pemeriksaan nominal, dan tenggat yang sama. Yang berubah cuma **siapa yang memicunya**: waktu, bukan jari.
- **Kenapa dipicu dari halaman, bukan dari server saat halamannya dimuat.** Menumpangkannya di `show()` terlihat lebih rapi dan salah: `show()` adalah GET yang dipanggil berulang oleh polling **dan bisa ikut terpanggil prefetch Inertia**. GET yang melunasi tagihan berarti sekadar mengarahkan kursor ke tautannya sudah cukup untuk membayar. Alternatif lain — job tertunda — menuntut queue worker hidup, yang tidak bisa diandalkan saat peragaan. Yang dipilih memakai POST yang sudah ada, tidak butuh worker, dan tidak menaruh efek samping di GET mana pun.
- **Delapan detik, bukan dua.** Yang sedang diperagakan adalah orang membayar dari aplikasi lain; pembayaran yang masuk seketika justru terbaca sebagai tombol, bukan sebagai pembayaran. Bisa diubah lewat `PAYMENT_FAKE_AUTO_SETTLE_SECONDS`, dan `0` mematikannya — dipakai saat yang sedang diuji justru keadaan yang tidak berakhir berhasil.
- **File Terdampak:**
  - `config/subscription.php` — `payment.fake.auto_settle_seconds` (bawaan 8; nilai negatif dibaca sebagai mati, bukan sebagai pembayaran seketika).
  - `app/Services/Billing/Gateways/FakeGateway.php` — `autoSettleSeconds()`, dan `callbackRequest()` yang **memindahkan penyusunan badan notifikasi dari controller ke driver**. Bentuk kabelnya milik driver: begitu ada penyedia kedua, controller yang menyusun JSON-nya sendiri akan menyusun yang salah.
  - `app/Http/Controllers/Billing/PaymentController.php` — `simulate()` menyusut jadi satu panggilan; `show()` mengirim `auto_settle_seconds`, dan **selalu 0 untuk percobaan milik gateway lain** supaya tagihan lama tidak menghidupkan kembali pelunasan otomatis.
  - `resources/js/Pages/Billing/PaymentInstruction.vue` — timer otomatis, penanda "Memeriksa pembayaran…" yang berdenyut (tanpa itu penantian delapan detik terbaca seperti halaman macet, dan orang menekan muat ulang tepat sebelum pembayarannya masuk), dan **empat tombol lama pindah ke `<details>` tertutup bernama "Alat pengembangan"**.
  - `resources/js/Pages/Billing/Pay.vue` — kotak peringatan "Mode peragaan" turun jadi satu baris. Peragaannya tetap disebut; yang dibuang cuma bobot visualnya, karena kotak amber besar di atas layar bayar membuat seluruh alurnya terbaca sebagai maket.
- **Test:** 5 tes baru di `PaymentGatewayTest` (30 total di berkas itu) — jeda terkirim ke halaman, `0` mematikannya, nilai negatif dibaca sebagai mati, notifikasi otomatis adalah badan bertanda tangan yang sama dengan yang dikirim panel, dan gateway non-tiruan tidak pernah melaporkan jeda. Suite penuh lulus.
- **Diperbaiki saat gladi resik:** penanda "Memeriksa pembayaran…" masih tampil setelah tagihannya lunas — `autoSettleArmed` dinilai sekali saat setup dan tidak pernah ikut berubah bersama status. Kondisinya sekarang berpasangan dengan `isPending`. Cacat tampilan, bukan perilaku, tapi persis jenis yang membuat penonton bertanya "berarti belum selesai?" di detik yang salah.
- **Gladi resik pada data nyata (2026-08-08):** tagihan langganan Rp 100.000 untuk `Kopi Nusantara` periode `2026-08` diterbitkan, lalu dibayar lewat QRIS **tanpa satu pun tombol peragaan ditekan**. Hasilnya: `settled_via = gateway_fake`, `verified_by = null`, `price_locked` 0 → 100.000, periode maju 24 Agu → 24 Sep, tenant tetap `active`. Tagihan itu **berdampingan dengan tagihan `upgrade` periode yang sama** — yang berarti perbaikan `[BL-058]` ikut terbukti di jalur penerbitan, bukan cuma di test.
- **Catatan:** `[BL-060]` (penyedia sungguhan) diperbarui, bukan ditutup: pemilik mengonfirmasi **belum punya akun Sumopod maupun Xendit** untuk tagihan langganan, jadi entri itu kini menunggu keputusan komersial, bukan dokumentasi API. Ia tidak menahan apa pun — gateway tiruan sudah menyelesaikan kebutuhan development dan peragaan.

---

### [HOTFIX] Penjaga Periode-Ganda Menyaring `kind`, dan Tenant yang Dilewati Berhenti Menghilang dari Hitungan (BL-058)
- **Tanggal:** 2026-08-07
- **Fase Terkait:** Di Luar Fase — menutup `[BL-058]`
- **Dampak:** Service | Controller | Command | Test
- **Breaking Change:** Tidak. Bentuk kembalian `issueDuePeriodInvoices()` dan `advanceLifecycle()` bertambah satu kunci (`skipped`); tidak ada kunci yang hilang atau berubah arti.
- **Deskripsi:** Penjaga periode-ganda menolak menerbitkan tagihan bila tenant sudah punya tagihan **apa pun** untuk periode `Y-m` itu — tanpa menyaring `kind`. Tagihan penambahan seat memakai `period` yang sama dengan tagihan langganan, jadi **satu penambahan seat di bulan X membatalkan tagihan langganan bulan X**. Cacat yang sama ada di penerbit manual pemilik SaaS, dengan akibat yang lebih buruk: ia hanya melihat pesan "tagihan sudah ada" dan tidak punya satu pun jalan untuk menagih bulan itu.

  Yang membuatnya mahal adalah **diamnya**. `continue` terjadi sebelum harga dihitung, jadi tenant yang dilewati tidak masuk `issued`, `free`, maupun `unpriced`. Keluaran perintahnya terbaca normal. Satu-satunya cara menyadarinya adalah menghitung sendiri berapa tenant yang seharusnya ditagih.

  Perbaikannya bukan tambalan: ia **menyelaraskan penjaga aplikasi dengan indeks uniknya**, yang sejak awal sudah `(tenant_id, period, kind)`. Sebelum ini keduanya menjaga dua hal yang berbeda.
- **Terbukti pada data nyata, sebelum dan sesudah.** Kedua tenant (`Kopi Nusantara`, `Kopi Story`) sama-sama memegang tagihan `upgrade` untuk periode `2026-08` — yang satu Rp 0 sisa alur seat gratis, yang satu Rp 15.000 dari uji coba alur bayar `[BL-059]`. Dengan penjaga yang lama, dry-run pada 2026-08-17 akan menerbitkan **0** tagihan padahal keduanya jatuh tempo dengan tarif Rp 100.000. Sesudah perbaikan: `invoiced = 2, skipped = 0`.
- **Penghitung keempat.** `skipped` ditambahkan dan ditampilkan perintahnya. Tiga penghitung yang ada semuanya menjelaskan **kenapa** sebuah tagihan tidak terbit; yang ini satu-satunya yang tidak, dan justru itu yang membuat cacatnya tak terlihat selama ada. Angka > 0 biasanya wajar — tagihannya memang sudah pernah terbit — tapi sekarang ia bisa dicocokkan alih-alih disimpulkan.
- **File Terdampak:**
  - `app/Services/SubscriptionService.php` — `issueDuePeriodInvoices()`: penjaga menyaring `kind = KIND_SUBSCRIPTION`, penghitung `skipped` baru, ikut diteruskan `advanceLifecycle()`.
  - `app/Http/Controllers/Platform/InvoiceController.php` — `store()`: penjaga yang sama; pesan galatnya diperjelas jadi "tagihan **langganan** … sudah ada"; `kind` ditulis eksplisit saat membuat, karena penjaganya kini bergantung padanya.
  - `app/Console/Commands/AdvanceSubscriptionLifecycle.php` — baris keluaran untuk `skipped`.
  - `tests/Feature/Subscription/AutoInvoiceTest.php` — tiga tes baru: tagihan upgrade tidak lagi menelan tagihan langganan bulan yang sama, tenant yang sudah ditagih terhitung `skipped` (bukan hilang), dan pemilik SaaS tetap bisa mengetik tagihan langganan untuk bulan yang sudah punya upgrade.
- **Test:** 18 di `AutoInvoiceTest`, 325 di `Feature/Subscription` + `Feature/Platform`, dan suite penuh lulus.
- **Catatan:** `[BL-050]` (indeks yang membatasi upgrade jadi sekali sebulan) **tidak** disentuh. Arah keduanya berlawanan — yang itu soal penjaga yang terlalu ketat, yang ini soal penjaga yang terlalu longgar — dan menggabungkannya akan membuat keduanya sulit dibaca.

---

### [ADDITION] Halaman Bayar Berdiri di Atas Gateway Tiruan yang Bicara Seperti Gateway Sungguhan (BL-059)
- **Tanggal:** 2026-08-07
- **Fase Terkait:** Di Luar Fase — permintaan pemilik untuk peragaan, menutup `[BL-059]`
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config | Test
- **Breaking Change:** Tidak. Jalur bukti transfer manual dan tombol simulasi lama keduanya tetap utuh.
- **Deskripsi:** Tenant kini bisa membayar tagihannya sendiri: pilih kanal (QRIS / VA / e-wallet) → dapat instruksi bernomor transaksi dan bertenggat → bayar → akses pulih **tanpa seorang pun memeriksa apa pun**. Penyedianya masih tiruan, dan itu memang yang diminta untuk peragaan.

  **Keputusan yang menentukan seluruh bentuknya: gateway tiruan melunasi lewat webhook, bukan dengan memanggil `settle()`.** Tombol "Bayar penuh" di panel peragaan menyusun notifikasi JSON, menandatanganinya dengan HMAC yang sama, lalu menyerahkannya ke `PaymentWebhookController` — jalur yang sama persis dengan penyedia sungguhan nanti. Yang tidak ikut terlewati hanyalah routing dan CSRF.

  Alasannya bukan kerapian. Halaman bayar palsu yang tombolnya memanggil `settle()` langsung akan jadi **jalur uang ketiga** dengan bentuk yang sama sekali berbeda dari produksi: yang diperagakan besok hanya membuktikan bahwa jalur peragaan bekerja, dan hari pertama Sumopod dipasang adalah hari pertama jalur webhook-nya pernah dijalankan. Dengan bentuk kabel yang sudah benar sejak sekarang, `[BL-060]` tinggal menulis satu kelas yang mengisi kontrak `PaymentGateway`.

- **Tiga pertanyaan lama di `InvoiceSettlement` akhirnya punya jawaban di kode**, bukan di komentar:
  1. **Idempotensi** — unik `(gateway, external_id)` + `lockForUpdate()` + baca ulang di dalam transaksi. Notifikasi kedua tidak melunasi apa pun dan **tetap dijawab 200**: penyedia yang menerima galat akan mengulang selamanya sesuatu yang tidak akan pernah berubah.
  2. **Nominal** — selisih ≥ 1 sen menghentikan pelunasan dan menandai percobaan `mismatch`. Melunasi kurang bayar berarti `settle()` mengunci `price_locked` dari yang **ditagih**, yaitu diam-diam menetapkan tarif yang tak pernah disepakati sambil menutup selisihnya dari uang sendiri.
  3. **Keaslian** — tanda tangan diverifikasi atas **badan mentah**, bukan atas hasil parse; menandatangani array yang sudah di-decode berarti menandatangani tafsiran kita atas pesan itu, bukan pesannya.
- **Dua gerbang keamanan, keduanya diuji:**
  - **Driver tiruan gagal di-resolve di produksi** — exception, bukan diam-diam jatuh ke jalur manual. Yang kedua jauh lebih buruk: halamannya tetap terbuka, tombolnya tetap ada, dan tidak ada yang tahu bahwa yang barusan "lunas" tak pernah dibayar. Webhook-nya ikut hilang (404, bukan 403).
  - **`settled_via` dipisah per driver** (`gateway` vs `gateway_fake`). Satu nilai untuk keduanya membuat uang peragaan tak terbedakan dari uang sungguhan begitu drivernya diganti.
- **Keputusan yang sengaja diambil dan perlu ditinjau ulang saat penyedia sungguhan dipasang:** notifikasi **lunas yang datang setelah tenggat lewat tidak melunasi apa pun** — percobaannya ditandai `expired` dan meninggalkan jejak `payments.late` untuk diputuskan orang lewat verifikasi manual. Aman untuk gateway tiruan yang tenggatnya kita sendiri yang tentukan; untuk penyedia sungguhan, tanyakan lebih dulu apakah mereka bisa mengirim `paid` setelah kedaluwarsa.
- **File Terdampak:**
  - `database/migrations/2026_08_07_130328_create_payment_attempts_table.php` — tabel baru. Tabel sendiri, bukan kolom di `invoices`: satu tagihan wajar punya beberapa percobaan (kedaluwarsa → ganti kanal → lunas), dan `invoices` sudah unik per `(tenant_id, period, kind)`.
  - `app/Models/PaymentAttempt.php` + factory — **tanpa `TenantScope`**, mengikuti `Invoice`: baris ini dibaca dari halaman tenant (ada pengguna) dan dari webhook (tidak ada siapa-siapa), dan scope yang diam-diam mengosongkan hasil di dunia kedua membuat pembayaran sah terlihat seperti transaksi tak dikenal.
  - `app/Services/Billing/Gateways/` — `PaymentGateway` (kontrak), `FakeGateway`, `PaymentGatewayManager`, `CallbackResult`, `InvalidCallbackSignature`.
  - `app/Http/Controllers/Billing/PaymentController.php` — pilih kanal, terbitkan instruksi, halaman instruksi, panel peragaan. Instruksi yang masih hidup **selalu menang atas yang baru**: nomor VA kedua untuk tagihan yang sama adalah cara termudah membuat tenant mentransfer ke nomor yang tidak ditunggu siapa-siapa.
  - `app/Http/Controllers/Billing/PaymentWebhookController.php` — tenant ditentukan dari `payment_attempts`, bukan dari sesi.
  - `app/Services/Billing/InvoiceSettlement.php` — `SOURCE_GATEWAY` & `SOURCE_GATEWAY_FAKE`; docblock kerangka diganti dengan lokasi jawabannya.
  - `routes/web.php`, `bootstrap/app.php` — empat rute `billing.payment.*` (otomatis ikut `ALWAYS_ALLOWED`, jadi tenant yang ditangguhkan tetap punya jalan keluar) + webhook publik yang dikecualikan CSRF.
  - `config/subscription.php` — blok `payment` (driver, umur instruksi 60 menit, kunci tanda tangan tiruan).
  - `resources/js/Pages/Billing/Pay.vue`, `PaymentInstruction.vue` — pemilih kanal & halaman instruksi (QR peragaan, nomor VA yang bisa disalin, hitung mundur, `usePoll` yang berhenti sendiri saat keadaannya final). `Show.vue` — tombol "Bayar sekarang".
- **Test:** `tests/Feature/Subscription/PaymentGatewayTest.php`, 25 tes / 115 asersi, semuanya lulus; suite `tests/Feature/Subscription` 181 lulus. Yang diuji bukan bahwa tombolnya bekerja melainkan bahwa jalur ini **tidak punya cara** melunasi tagihan yang tidak dibayar: tanda tangan palsu, tanpa tanda tangan, notifikasi berulang, transaksi tak dikenal, nominal kurang, instruksi kedaluwarsa, tenant lain, kasir, dan driver tiruan yang tersasar ke produksi.
- **Catatan Migrasi:** `php artisan migrate` (sudah dijalankan di basis data pengembangan). Tidak ada dependensi baru. `PAYMENT_DRIVER` dan `PAYMENT_FAKE_SECRET` opsional — bawaannya `fake` dan kunci literal, dan `fake` **tidak akan** hidup di produksi.
- **Yang sengaja BELUM dikerjakan:** `[BL-061]` mencabut tombol simulasi lama (`SimulatedPaymentController`) — ditunda sampai sesudah peragaan; mencabut satu-satunya jalur yang sudah teruji sehari sebelum demo tidak ada untungnya. Dan `[BL-060]` Sumopod, yang menunggu dokumentasi serta kredensial sandbox.

---

### [DECISION] Struktur Harga Ditetapkan: Free Dua Bulan, Adaptif Jadi Diskon `paid-1`, dan Tenggat Berhenti Mematikan Kasir
- **Tanggal:** 2026-08-07
- **Fase Terkait:** Di Luar Fase — menutup `[BL-041]`(a) dan membubarkan kebuntuan `[BL-048]`
- **Dampak:** Config | Model | Service | Migrasi | Data | Test | Dokumentasi
- **Breaking Change:** Ya, dua. (1) `config('subscription.trial_days')` **dihapus**, diganti `trial_months`; `SubscriptionService::trialDays()` → `trialMonths()`. (2) Slug paket bawaan berganti `dasar` → `free`, berpasangan dengan `Plan::SLUG_DEFAULT`. Keduanya gagal keras bila tidak sinkron (`firstOrFail()`), bukan diam-diam salah.
- **Deskripsi:** Seluruh mesin penagihan sudah berdiri sejak Juli dan belum pernah menagih siapa pun, karena angkanya tidak pernah ditetapkan. Sesi ini menetapkannya. Yang menarik bukan angkanya, melainkan **tiga hal yang ditemukan saat menyusunnya, yang masing-masing mengubah keputusan sebelumnya**.

  **1. Bracket D ternyata memberi diskon 0%.** Pemilik mendefinisikan Harga Adaptif sebagai *`paid-1` yang didiskon menurut omset*. Begitu definisi itu ditulis, diskon tiap bracket bisa dihitung — dan bracket D (Rp 15–50 jt) berharga **sama persis** dengan `paid-1`, Rp 100.000. Tenant di rentang itu menyerahkan data penjualannya dan menerima nol rupiah: ongkos privasi tanpa imbalan. Tangga Adaptif sebenarnya berakhir di Rp 15 juta, bukan Rp 50 juta seperti yang diasumsikan `[BL-048]`. D turun ke **Rp 75.000**, dan tangganya jadi monoton 90/75/50/25/0 — yang juga membuat ambang Rp 50 juta akhirnya berarti sesuatu, karena sebelumnya melewatinya tidak mengubah tagihan sama sekali.

  **2. Alur pengajuan membubarkan kebuntuan privasi `[BL-048]`.** Entri itu macet di lingkaran: menilai kelayakan butuh data omset yang baru boleh dikumpulkan setelah masuk. Usulannya waktu itu — kelayakan sebagai pemberian manual pemilik SaaS — adalah jalan memutar. Pemilik memotongnya langsung: **consent diberikan saat MENGAJUKAN**, jadi pengukuran terjadi setelah tenant memintanya. Lingkarannya tidak pernah terbentuk, dan penilaian otomatis penuh jadi sah alih-alih kompromi. Usulan `[BL-048]`(a) dibatalkan; jangan menambahkan `subsidy_eligible_at`.

  **3. Masa tenggang ternyata sudah mematikan kasir sejak hari pertama.** `EnsureSubscriptionActive` memblokir seluruh permintaan non-GET begitu tenant masuk tenggat — artinya toko tidak bisa berjualan. Prinsip yang tertulis di config ("tenggat mencabut kemampuan MENAMBAH data, bukan MEMBACA") ternyata berarti persis itu, dan konsekuensinya tidak pernah diperiksa: warung yang tidak bisa berjualan tidak punya uang untuk membayar. Prinsipnya dicabut dan diganti tangga tiga tahap — notif halus hari 1–14, intensif 15–19, tulis dicabut 20–30, dengan dashboard tetap terbaca sepanjang tenggat. Config-nya dipasang sekarang; penegaknya `[BL-054]`.

- **Yang berubah di kode:**
  - `config/subscription.php` — `trial_days` → **`trial_months` = 2**; ditambah `grace_intensive_from_day` = 15 dan `grace_lock_from_day` = 20. Ketiganya berdampingan karena menggambarkan satu tangga; kebijakan tenggat harus bisa diubah tanpa membaca middleware. Benih `revenue_brackets` ikut disesuaikan (D = 75k, batas atas 50 jt) untuk pemasangan baru.
  - `SubscriptionService::startTrial()` — `addMonthsNoOverflow()`, bukan `addMonths()`: dua bulan dari 31 Desember tanpa penjaga luberan mendarat di 3 Maret dan **melewatkan Februari sama sekali**.
  - `SubscriptionService::startTrial()` — **`billing_anchor_day` diambil dari tanggal DAFTAR**, bukan tanggal masa gratis berakhir. Keduanya hampir selalu sama, kecuali saat akhir masa gratis terjepit bulan pendek: yang daftar 31 Desember berakhir 28 Februari, dan jangkar yang diambil dari situ mengunci tanggal tagihnya di **28 selamanya**. Mesin penjepitnya (`anchoredDateIn()`) sudah benar sejak `[BL-030]` — yang salah cuma sumber jangkarnya.
  - `Plan::SLUG_DEFAULT` `dasar` → `free`, berpasangan dengan migrasi `rename_default_plan_to_free`.
- **Migrasi:** `2026_08_07_110647_rename_default_plan_to_free` — rename slug/nama, dan **`included_seats` 1 → 2**. Yang terakhir bukan angka komersial melainkan bentuk produk: satu seat berarti pemilik toko satu-satunya yang bisa masuk, tanpa kasir. Untuk aplikasi POS itu bukan paket terbatas melainkan paket yang tidak bisa dipakai — dan selama dua bulan pertama, paket inilah wajah produknya. Harga dan kuota AI sengaja **tidak** disentuh migrasi: keduanya milik pemilik SaaS lewat panel, dan migrasi yang menimpanya menghapus keputusan komersial yang mungkin sudah berbeda di produksi.
- **Data yang dipasang (basis data pengembangan, lewat model & service yang sama dengan panel):**

  | Paket | Tarif | Seat | Seat tambahan | AI/hari |
  |---|---|---|---|---|
  | `free` | Rp 0 | 2 | Rp 20.000 | 5 |
  | `paid-1` | Rp 100.000 | 3 | Rp 15.000 | 15 |
  | `paid-2` | Rp 150.000 | 5 | Rp 12.500 | 30 |
  | `paid-3` | Rp 200.000 | 10 | Rp 10.000 | 60 |

  Bracket A–D: 10k / 25k / 50k / **75k**, D ditutup `< Rp 50 juta`. Diverifikasi: 20 jt & 49.999.999 → D Rp 75.000; 50 jt & 200 jt → tidak ada bracket. Jejak di `platform_audit_logs` (`plans.update` ×4, `pricing-rules.create` ×1), `platform_user_id` null — perubahan ini memang tidak lahir dari sesi pengguna platform, dan mencatatnya seolah-olah begitu akan berbohong.
- **Cacat data yang ikut ketahuan dan diperbaiki:** hanya `Premium 1` yang menyetel `ai_daily`; `Premium 2` dan `3` bernilai `null` sehingga ikut bawaan platform **5/hari**. Artinya Rp 200.000 memberi kuota AI yang sama persis dengan paket gratis, dan **separuh** dari paket Rp 100.000 di bawahnya — tangga harganya naik sementara nilainya turun. Keempat paket sekarang menyetelnya eksplisit.
- **Koreksi terhadap `[BL-041]`:** entri itu menyatakan *grandfathering* akan mempertahankan `price_locked` tenant lama. Penguncian harganya nyata (`InvoiceSettlement.php:91`), tapi **tidak ada pembaca di jalur penagihan** — `issueDuePeriodInvoices()` selalu menghitung ulang lewat `resolveFor()`. `price_locked` hanya dibaca dua tempat, keduanya untuk tampilan. Kebetulan itu justru perilaku yang diminta untuk harga manual (berlaku sebulan saja), jadi yang dulu terlihat sebagai cacat sekarang jadi separuh fitur — lihat `[BL-057]`.
- **Test:** `724 passed`. Empat tes ikut berubah premisnya, dan itu memang tandanya keputusan ini menyentuh perilaku: jangkar tagih (ditulis ulang untuk kasus 31 Desember), panjang masa gratis (×3), jatah seat paket bawaan, dan nama paket di jejak audit. Ditambah satu tes baru yang mengunci penutupan tangga Adaptif — 49.999.999 masih D, 50 juta tidak punya bracket sama sekali.
- **Yang sengaja BELUM dikerjakan**, masing-masing jadi entri sendiri: `[BL-052]` perpindahan otomatis di akhir masa gratis (**paket `free` masih Rp 0, jadi tenant yang duduk di sana tetap tidak tertagih dan jatuh ke tenggang**), `[BL-053]` seat & kuota AI jadi komponen bulanan (angkanya sudah dipasang, tapi penerbit masih menagihnya sekali — nilainya benar, satuannya salah), `[BL-054]` penegak tenggat bertingkat, `[BL-055]` alur pengajuan Adaptif, `[BL-056]` pengajuan berlaku bulan mana, `[BL-057]` alasan wajib pada tagihan manual.

---

### [HOTFIX] Masa Tenggang Ikut Ditagih, dan Aturan Tunggakan Bertahan Setelah Ditinjau
- **Tanggal:** 2026-08-07
- **Fase Terkait:** Di Luar Fase — menutup peninjauan yang dijanjikan `[BL-044]`/`[BL-030]`, dan menambal lubang yang ditemukan di dalamnya
- **Dampak:** Service | Test | Dokumentasi
- **Breaking Change:** Tidak. Tenant yang sudah punya tagihan untuk periodenya tidak menerima tagihan kedua — penjaga periode-ganda yang menolaknya, dan penjaga itu tidak berubah.
- **Deskripsi:** Dua hal, keduanya berangkat dari satu pertanyaan yang ditinggalkan `[BL-044]`(b): apakah aturan "tunggakan tidak ditumpuk" di `renewPeriod()` masih benar sekarang setelah tagihan terbit sendiri?

  **Jawabannya ya, dan alasannya sekarang tertulis.** Kekhawatirannya waktu itu: bila tiap periode punya tagihannya sendiri, maka `renewPeriod()` yang memajukan periode melewati bulan-bulan yang terlewat berarti memajukan tenant melewati **tagihan** yang belum dibayar. Ternyata tiap periode tidak pernah punya tagihannya sendiri. `current_period_end` hanya maju di dalam `renewPeriod()`, yang hanya berjalan saat tagihan dilunasi — jadi selama tenant belum membayar, kunci `Y-m` periodenya membeku dan penjaga periode-ganda menolak setiap penerbitan sesudahnya. Satu pelanggaran melahirkan tepat satu tagihan; satu pembayaran memulihkan tepat satu periode. Kedua aturan itu bertemu, bukan bertabrakan. Sekarang dipatok test yang menjalankan siklusnya empat bulan berturut-turut dan menuntut jumlah tagihannya tetap satu.

  **Lubang yang ditemukan sambil memeriksanya sudah ditambal.** Penerbit hanya menagih tenant `trial` dan `active`, dengan alasan "tenant di masa tenggang tagihannya sudah terbit saat ia masih aktif". Alasan itu benar untuk tenant yang memang sudah ditagih — dan justru tidak berlaku untuk tenant yang lewat **tanpa** tagihan, yaitu dua keadaan yang penerbitnya sendiri buat: tarif Rp 0 dan tarif `null`. Bagi mereka pengecualiannya permanen: periodenya beku sehingga mereka tak akan pernah kembali `active` sendiri, dan menetapkan tarifnya besok tidak menerbitkan apa pun. Janji "menyembuhkan dirinya sendiri" di dokumentasi butir (b) tidak berlaku untuk mereka. Kini `grace` ikut ditagih; yang menahan tagihan kedua adalah penjaga periode-ganda, yang memang sudah memegang alasan sebenarnya dan tidak peduli status tenantnya.
- **Alasan:** Bukan hipotesis. Per catatan `[BL-044]` 2026-08-06, `Kopi Story` beresolve ke Rp 0 dan akan turun ke masa tenggang **2026-08-25** tanpa satu pun tagihan. Sebelum perbaikan ini, menetapkan tarif Premium besok pun tidak akan menerbitkan apa-apa untuknya — ia menunggu di masa tenggang, lalu tertangguh, dan satu-satunya jalan keluarnya adalah pemilik SaaS mengetikkan tagihannya manual. Itu persis keadaan yang `[BL-044]` dibuat untuk menutup.
- **File Terdampak:**
  - `app/Services/SubscriptionService.php` — `issueDuePeriodInvoices()` menyertakan `STATUS_GRACE`; jatuh tempo tidak lagi bisa lahir di masa lalu; `renewPeriod()` mencatat hasil peninjauannya beserta syarat yang akan mematahkannya
  - `tests/Feature/Subscription/AutoInvoiceTest.php` — tiga test baru (tenant tenggang yang belum ditagih, tenant tertangguh yang tetap tidak ditagih, satu-pelanggaran-satu-tagihan); test masa tenggang yang lama kini benar-benar membuat tagihan pendahulunya alih-alih mengandaikannya
  - `docs/BACKLOG.md` — `[BL-051]` baru; catatan peninjauan `[BL-044]` ditutup
- **Keputusan yang Diambil:**
  - **`suspended` tetap di luar penerbitan.** Aksesnya sudah tertutup penuh, dan menagih bulan yang tak bisa dipakai berarti menumbuhkan utang yang tak pernah diminta siapa pun. Akibatnya tenant yang ingin kembali tetap harus lewat pemilik SaaS — itu keputusan produk, bukan kelalaian, dan dicatat sebagai `[BL-051]` supaya dipilih dengan sadar, bukan diwarisi.
  - **Jatuh tempo tagihan susulan = hari ini, bukan tanggal periodenya habis.** Tenant yang ditagih susulan di masa tenggang periodenya memang sudah lewat, tapi tagihan yang lahir sudah lewat tempo di hari yang sama membacanya seperti tunggakan yang ia abaikan — padahal hari itu barulah pertama kali ia melihat angkanya.
  - **Aturan `[BL-030]` tidak diubah sama sekali.** Peninjauan yang dijanjikan menghasilkan pembenaran, bukan penggantian. Yang ditambahkan hanyalah alasannya dan test yang menjaganya — karena alasan yang tidak tertulis akan ditinjau ulang dari nol oleh orang berikutnya.

---

### [HOTFIX] Tagihan Rp 0 Berhenti Meminta Bukti Transfer Nol Rupiah (BL-049 butir b & c)
- **Tanggal:** 2026-08-07
- **Fase Terkait:** Di Luar Fase — menutup butir (b) dan (c) `[BL-049]`; butir (a) tetap menunggu `[BL-041]`(a)
- **Dampak:** Service | Controller | Command | Frontend | Test
- **Breaking Change:** Tidak. Tagihan upgrade bernilai lebih dari nol menempuh alur bukti transfer persis seperti sebelumnya.
- **Deskripsi:** Paket `dasar` mematok `extra_seat_price = 0`, jadi panel "Tambah pengguna" menerbitkan tagihan **Rp 0** — lalu menahan seat barunya sampai tenant mengunggah bukti bahwa ia sudah mentransfer nol rupiah, dan meminta pemilik SaaS memeriksa bukti itu. Kodenya bekerja persis seperti dirancang; yang keliru adalah menuntut pembuktian atas sesuatu yang tidak pernah dibayarkan. Kini tagihan upgrade Rp 0 **lunas seketika** lewat `InvoiceSettlement::settleIfFree()`, seat-nya langsung berlaku, dan panelnya berkata apa adanya: "Tambah pengguna · gratis", tanpa menjanjikan tagihan maupun unggahan.
- **Alasan:** Tagihan yang menggantung ini bukan hipotesis. Per 2026-08-07 ada satu di basis data pengembangan — `Kopi Story`, seat 2 → 5, Rp 0, jatuh tempo **2026-08-08** — yang tidak akan pernah bisa diselesaikan siapa pun lewat jalur yang tersedia.
- **File Terdampak:**
  - `app/Services/Billing/InvoiceSettlement.php` — `settleIfFree()` + `SOURCE_ZERO_AMOUNT`
  - `app/Http/Controllers/Billing/UpgradeController.php` — pelunasan seketika & penjaga periode
  - `app/Services/SubscriptionService.php` — `hasUpgradeInvoiceThisPeriod()`
  - `app/Http/Controllers/Billing/SubscriptionController.php` — prop `upgrade.closed_for_period`
  - `app/Console/Commands/SettleFreeSeatUpgrades.php` — `subscriptions:settle-free-upgrades`
  - `resources/js/Pages/Billing/Show.vue` — `seatsAreFree`, label & keterangan panel
  - `tests/Feature/Subscription/ProvisionalUpgradeTest.php` — 6 test baru
- **Keputusan yang perlu diingat:**
  - **Tagihannya tetap terbit, yang dilewati adalah tuntutan membayarnya.** `grants_seats` dan `previous_seats` hidup di baris tagihan itu, dan itulah satu-satunya catatan bahwa seat pernah berpindah dari sekian ke sekian — `revertUpgrade()` pun bersandar padanya. Melompati penerbitannya akan menghemat satu baris dan menghapus jejaknya.
  - **Hanya tagihan `KIND_UPGRADE`, dan batas itu disengaja.** Tagihan langganan Rp 0 tidak pernah lahir sendiri: `issueDuePeriodInvoices()` menolak menerbitkannya selama tarifnya belum ditetapkan. Yang masih bisa melahirkannya hanya pemilik SaaS yang mengetiknya di panel (`min:0`), dan melunasinya otomatis akan menulis `price_locked = 0` lalu memperpanjang periodenya — diam-diam mewariskan tarif nol yang belum pernah diputuskan siapa pun (`[BL-041]`). Ada test yang menjaga batas ini.
  - **`provisional_blocked` sengaja tidak diperiksa di jalur gratis.** Penjaga itu menahan seat yang naik tanpa dibayar; ketika tak ada yang harus dibayar ia tidak menjaga apa pun, dan menegakkannya justru mengunci tenant di jalan buntu — bukti transfer nol rupiah tidak akan pernah ada yang bisa mengunggahnya.
  - **`settled_via = 'zero_amount'`, `verified_by` tetap null.** Sejalan dengan jalur simulasi: kolom itu menjawab "siapa yang memeriksa", dan tidak ada yang memeriksa apa pun di sini.
  - **Panelnya turunan dari harga, bukan saklar tersendiri.** Begitu `extra_seat_price` ditetapkan bukan nol, panel dan alurnya kembali sendiri ke tagihan + bukti transfer, tanpa satu baris kode pun berubah — pola yang sama dengan `issueDuePeriodInvoices()`.
- **Ditemukan saat mengerjakannya (sudah diperbaiki):** indeks unik `invoices (tenant_id, period, kind)` membatasi satu tagihan upgrade per tenant per bulan kalender. Selama tagihan upgrade tidak pernah selesai, batas itu tak pernah tersentuh — penjaga `openUpgradeInvoice()` menolak permintaan kedua lebih dulu. Begitu upgrade bisa rampung, permintaan kedua di bulan yang sama lolos penjaga itu lalu **menabrak indeksnya sebagai galat 500**. Cacat ini sudah ada sebelum perubahan ini (jalur bukti transfer + verifikasi juga merampungkan upgrade), hanya saja tarif Rp 0 membuatnya jauh lebih mudah dicapai. Ditutup dengan `hasUpgradeInvoiceThisPeriod()` yang menolaknya sebagai kalimat, bukan halaman galat. **Melonggarkan batasnya sendiri menuntut perubahan skema dan dicatat sebagai `[BL-050]`.**
- **Catatan Migrasi:** tidak ada migrasi. Tagihan Rp 0 yang terlanjur menggantung dibereskan sekali jalan dengan `php artisan subscriptions:settle-free-upgrades` (punya `--dry-run`). Sengaja perintah, bukan migrasi: ia mengubah seat tenant, dan perubahan semacam itu pantas dijalankan sadar-sadar alih-alih ikut terbawa `migrate` saat rilis. Tagihan berstatus `rejected` tidak disentuh — ada orang yang pernah memutuskannya.

---

### [ADDITION] Paket Kedua Akhirnya Bisa Dihuni: Tenant Bisa Dipindahkan, Seat Bayarnya Ikut (BL-046)
- **Tanggal:** 2026-08-06
- **Fase Terkait:** Di Luar Fase — menutup `[BL-046]`
- **Dampak:** Service | Controller | Route | Frontend | Test
- **Breaking Change:** Tidak. `plan_id` tetap ditulis `startTrial()` seperti biasa; yang bertambah hanyalah cara mengubahnya setelah itu. Tenant yang tidak pernah dipindahkan berperilaku persis seperti sebelumnya.
- **Deskripsi:** Sampai hari ini `plan_id` ditulis **sekali seumur hidup langganan** dan tidak ada satu pun jalur yang mengubahnya lagi. Akibatnya paket Premium yang sudah bisa dibuat dari `/platform/pricing-rules` hanya berupa baris data yang tak berpenghuni — dan keputusan pemilik 2026-08-01 ("tenant beromset tinggi hanya bisa Premium, tidak lagi berhak Adaptif") tidak punya cara ditegakkan sama sekali. Kini ada `PUT /platform/subscriptions/{subscription}/plan`: pemilik SaaS memindahkan tenant antar paket dari tab **Langganan** di rincian tenant, wajib beralasan, dan perpindahannya dicatat sensitif berikut paket, batas pengguna, dan kuota AI lama-barunya.
- **Alasan:** Aksi platform lebih dulu, bukan permintaan mandiri owner (`[BL-046]`(d)). Pemindahan ke Premium karena omset melewati batas adalah keputusan penyedia layanan — kalau satu-satunya jalannya adalah owner meminta sendiri, keputusan yang menolak seseorang bersandar pada persetujuan orang itu.
- **File Terdampak:**
  - `app/Services/SubscriptionService.php` — `changePlan()`, satu-satunya tempat `plan_id` berpindah
  - `app/Http/Controllers/Platform/SubscriptionController.php` — `updatePlan()` + `planSnapshot()`
  - `routes/web.php` — `platform.subscriptions.plan.update`, digerbang `platform.can:subscriptions`
  - `app/Services/Platform/AccountOverview.php` — `planCatalog()`, `plan_id`/`plan_base_price`/`plan_ai_daily_limit`
  - `resources/js/Pages/Platform/Tenants/Show.vue` — panel "Paket" + modal perpindahan
  - `tests/Feature/Platform/PlatformBillingTest.php` — 6 test baru
- **Keputusan yang perlu diingat:**
  - **Seat tambahan yang sudah dibayar ikut pindah.** Batas pengguna sebuah langganan adalah jatah paket **ditambah** seat yang dibelinya lewat tagihan `KIND_UPGRADE`. Menyalin `seats` apa adanya menelan jatah paket baru; menyetelnya ke jatah paket baru saja mencabut seat yang sudah dibayar. Yang dipertahankan adalah selisihnya — Dasar (jatah 1, batas 3) → Premium (jatah 5) menghasilkan **7**, bukan 5 dan bukan 3. Kekeliruan di sini baru terlihat berbulan-bulan kemudian, saat tenant menabrak batas yang tak pernah ia setujui.
  - **Tarif periode berjalan tidak disentuh.** `price_locked` sudah memegang harga yang disepakati dan `pricingAsOf()` menetapkan harga periode berikutnya dari aturan yang berdiri saat periodenya dibuka. Perpindahan di tengah periode karena itu berlaku pada tagihan berikutnya — dan kalimat itu ditulis apa adanya di pesan sukses, supaya tidak perlu ditebak.
  - **Digerbang `subscriptions`, bukan `pricing_rules`.** Yang diatur di sini bukan bentuk paketnya melainkan penempatan satu tenant. Staf yang boleh menyunting daftar paket tidak dengan sendirinya boleh memindahkan klien di antaranya.
  - **Hanya paket `is_active` yang jadi tujuan.** Memindahkan tenant ke paket yang sudah dihentikan berarti menaruhnya di tarif yang tidak lagi ditawarkan kepada siapa pun.
  - **Memilih paket yang sedang dihuni ditolak tanpa menulis jejak.** Jejak audit yang berisi "dipindahkan dari Dasar ke Dasar" hanya menambah baris yang harus dilewati saat mencari perpindahan yang benar-benar terjadi.
  - **Batas AI dikirim `null` apa adanya, bukan diselesaikan jadi angka bawaan** — di payload maupun di jejak audit. Bedanya sama seperti di halaman Aturan Harga: "10/hari karena paket ini" tidak boleh terbaca sama dengan "10/hari karena kebetulan itu bawaannya hari ini".
  - **Katalog paket hanya terkirim ke pemegang modul `subscriptions`.** Yang tidak boleh memindahkan tenant tidak menerima daftar tujuannya sama sekali — bukan menerimanya lalu disembunyikan di Vue.
  - **Permintaan naik paket mandiri oleh owner sengaja belum dibuat.** Pola `Invoice` `KIND_UPGRADE` sudah ada untuk itu kelak, tapi harganya menunggu `[BL-041]`(a) dan `[BL-049]`: hari ini `extra_seat_price` paket `dasar` masih Rp 0, jadi jalur mandiri apa pun akan menerbitkan tagihan nol rupiah.

---

### [ADDITION] Keadaan Langganan Terlihat di Setiap Layar, & Satu Pintu Menuju Aktif (BL-045)
- **Tanggal:** 2026-08-06
- **Fase Terkait:** Di Luar Fase — menutup `[BL-045]`
- **Dampak:** Schema | Middleware | Service | Controller | Frontend | Seeder | Test
- **Breaking Change:** Tidak. Penegakan hanya-baca tidak berubah sama sekali — yang bertambah hanya peringatannya. Jalur pelunasan pemilik SaaS berperilaku persis seperti sebelumnya; isinya pindah tempat, bukan berubah.
- **Deskripsi:** Dua hal yang hilang dari `[BL-045]`. **(1)** Peringatan keadaan langganan hanya hidup di kartu Dashboard, sehingga kasir yang membuka POS langsung — atau owner yang seharian di halaman Produk — tidak tahu apa-apa sampai ia menekan Simpan dan mendapat penolakan. Keadaan itu kini prop bersama di `HandleInertiaRequests` dan dirender sebagai pita di shell owner **dan** shell kasir. **(2)** Membayar tidak memulihkan akses sendiri. Kini ada tombol "Simulasikan pembayaran" di halaman Langganan yang melunasi tagihan tanpa bukti transfer, digerbang **dua** syarat sekaligus.
- **Alasan:** Peringatan sebelum tombol ditekan jauh lebih murah daripada penolakan sesudahnya — dan penolakan itu datang justru saat pembeli sedang menunggu di depan meja kasir.
- **Keputusan pemilik (2026-08-06):** simulasi digerbang **penanda tenant peragaan**, dengan maksud pemakaian di lingkungan non-produksi, plus kerangka untuk payment gateway nanti. Diterapkan sebagai **dua gerbang yang keduanya wajib** — `tenants.is_demo` **dan** lingkungan bukan produksi.
- **File Terdampak:**
  - `database/migrations/2026_08_05_192316_add_is_demo_to_tenants_table.php`, `2026_08_05_192711_add_settled_via_to_invoices_table.php`
  - `app/Services/Billing/InvoiceSettlement.php` — **baru**, satu-satunya pintu menuju `active`
  - `app/Http/Controllers/Billing/SimulatedPaymentController.php` — **baru**; rute `billing.simulate.store`
  - `app/Http/Middleware/HandleInertiaRequests.php` — `restrictionFor()`
  - `resources/js/Components/SubscriptionBanner.vue` — **baru**; dipasang di `OwnerLayout` & `CashierTopbar`
  - `app/Http/Controllers/Platform/InvoiceController.php` — `verify()` jadi pemanggil, bukan implementasi
  - `tests/Feature/Subscription/SubscriptionBannerTest.php` (6 test), `SimulatedPaymentTest.php` (10 test)
- **Keputusan yang perlu diingat:**
  - **Pita hanya lahir untuk `grace` dan `suspended`, dan itulah yang membuatnya gratis.** `status` sudah ada di baris tenant yang termuat, jadi mayoritas request tidak menyentuh query tambahan sama sekali. Peringatan *sebelum* periode lewat — tagihan sudah terbit tapi belum jatuh tempo — sengaja TIDAK ikut dan tetap di kartu Dashboard: mengetahuinya menuntut satu query tagihan pada setiap request, termasuk tiap ketukan di POS, dan itu ongkos yang tidak sepadan untuk keterangan yang belum mendesak.
  - **Pita dipasang di `CashierTopbar`, bukan di kelima halaman kasir.** Sisi kasir tidak punya layout bersama; memasangnya satu per satu berarti halaman keenam pasti akan melupakannya. Akar komponennya jadi `<div class="shrink-0">` yang membungkus pita + `<header>`.
  - **Kasir tidak ditawari tautan ke halaman Langganan.** Ia boleh membukanya, tapi setiap tombol di sana digerbang `role:owner` — mengarahkan staf ke halaman yang tak satu pun aksinya bisa ia tekan hanya memindahkan kebuntuan.
  - **Pita tidak bisa ditutup.** Ia bukan notifikasi yang lewat seperti `FlashMessage`, melainkan keadaan yang masih berlaku sedetik kemudian.
  - **`InvoiceSettlement` adalah satu-satunya tempat `Tenant::STATUS_ACTIVE` ditulis.** Isinya diangkat dari `InvoiceController::verify()` begitu ada cara kedua melunasi tagihan. Menyalinnya berarti menyalin juga aturan periode, penguncian harga, dan reset puncak seat — salinan yang bercabang di jalur uang adalah kesalahan yang paling lama tidak terlihat.
  - **Kerangka payment gateway.** Menambah gateway kelak = satu `SOURCE_*` baru + satu controller webhook yang memanggil `settle()`. Tiga hal sengaja belum dijawab dan didokumentasikan di docblock kelasnya: idempotensi webhook, selisih antara nominal yang ditagih dan yang benar-benar diterima, dan verifikasi tanda tangan panggilan. Ketiganya bergantung pada gateway yang dipilih.
  - **Dua gerbang, bukan satu.** Penanda `is_demo` saja tidak cukup: ia ikut terbawa bila basis data peragaan pernah disalin ke produksi, dan satu salah setel akan membuka jalur yang melunasi tagihan tanpa bukti — persis lubang yang `provisional_blocked` dibangun untuk menutup. Syarat lingkungan membuat jalurnya tidak pernah ADA di produksi.
  - **404, bukan 403.** Di luar tenant peragaan rute simulasi menjawab "tidak ada". Menjawab "terlarang" memberi tahu penanyanya bahwa ada sesuatu di sini yang bisa dibuka dalam keadaan lain.
  - **`verified_by` sengaja null untuk simulasi**, dan `invoices.settled_via` yang menjawab "lewat jalur mana". `verified_by` menjawab "siapa yang memeriksa" — dan tidak ada yang memeriksa apa pun di jalur ini. Jejak audit `invoices.simulate` tetap dicatat **sensitif**: tagihan berpindah ke lunas tanpa seorang pun memeriksa bukti, dan itu justru yang paling perlu terlihat.
  - **Belum ada UI untuk menyalakan `is_demo`.** Disetel seeder untuk kedua tenant peragaan. Toggle "tenant ini boleh melewati pembayaran" adalah permukaan risiko tersendiri, dan jalurnya toh sudah mati di produksi.

---

### [DECISION] Gambar Produk Pindah ke Disk Privat, Diseragamkan Ukurannya, dan Punya Cadangan Inisial
- **Tanggal:** 2026-08-06
- **Fase Terkait:** Di Luar Fase — permintaan pemilik
- **Dampak:** Service | Controller | Model | Route | Frontend | Test
- **Breaking Change:** Ya, untuk berkas lama. Path `public/storage/products/**` tidak lagi disajikan; gambar yang sudah tersimpan di sana perlu diunggah ulang. Disepakati pemilik (2026-08-06) karena storage produksi masih kosong. Kolom `products.image` tidak berubah bentuk — isinya tetap path relatif, hanya disknya yang berpindah.
- **Deskripsi:** Tiga hal sekaligus, karena ketiganya menyentuh berkas yang sama. (a) Gambar produk berhenti disajikan lewat symlink `public/storage` dan pindah ke disk privat, hanya keluar lewat rute ber-auth `GET /media/products/{product}/{size}`. (b) Setiap unggahan dinormalkan jadi WEBP persegi dalam dua ukuran tetap — 800×800 dan 200×200 — menggantikan konversi WEBP yang tidak pernah mengubah resolusi. (c) Produk tanpa gambar tidak lagi menampilkan ikon abu-abu yang seragam, melainkan inisial setiap kata namanya di atas warna yang diturunkan dari nama itu.
- **Alasan:** Symlink publik berarti setiap gambar terbuka untuk siapa pun yang tahu path-nya, termasuk toko lain — UUID memang sulit ditebak, tapi "sulit ditebak" bukan gerbang, dan sekali sebuah URL tersalin ke luar ia berlaku selamanya untuk semua orang. Soal ukuran: foto kamera ponsel tersimpan apa adanya, jadi kisi POS mengunduh berkas berukuran megabyte untuk kartu selebar 170 px — mahal di jaringan warung, dan tiap kartu punya rasio berbeda sehingga barisnya tampak jomplang. Soal inisial: ikon "gunung" yang sama untuk semua produk tak bergambar membuat sederet kotak kembar yang harus dibaca teksnya satu per satu; itu menghapus keuntungan utama tampilan kisi.
- **Temuan sampingan — unggah gambar selama ini SELALU gagal.** `ImageService` mengimpor `Intervention\Image\Laravel\Facades\Image`, facade milik paket `intervention/image-laravel` yang **tidak terpasang** (yang ada hanya paket inti `intervention/image`). Setiap unggahan berakhir 500 sejak fitur ini ditulis, dan tidak ada satu pun test yang menyentuh jalurnya. Diperbaiki dengan memakai `ImageManager::gd()` dari paket inti — tanpa menambah dependensi — dan jalurnya kini ditutup test.
- **File Terdampak:**
  - `app/Services/ImageService.php` — disk privat, dua rendition, `thumbPath()`/`pathFor()`; `url()` dihapus
  - `app/Http/Controllers/MediaController.php` (baru) — satu-satunya pintu keluar berkas tenant
  - `app/Models/Product.php` — `$appends` = `image_url`, `image_thumb_url`
  - `routes/web.php` — `media.product-image`, di bawah `auth` saja
  - `app/Http/Controllers/Owner/ProductController.php` — berhenti menyusun URL sendiri
  - `resources/js/Components/ProductImage.vue` (baru), `ProductCard.vue`, `Pages/Owner/Products/Index.vue`, `ImageUpload.vue`
  - `tests/Feature/Security/ProductImageAccessTest.php` (baru, 9 test), `tests/Feature/Owner/ProductTest.php` (4 test gambar)
- **Keputusan yang perlu diingat:**
  - **Rutenya di bawah `auth` SAJA, di luar grup `tenant`.** Tag `<img>` tidak bisa menampilkan halaman pengalihan: kalau rute ini ikut gerbang langganan atau verifikasi surel, gambar dijawab 302 ke HTML dan yang terlihat pengguna cuma ikon rusak. Pemisahan antar toko ditegakkan di controller — dan sekali lagi oleh `TenantScope` saat route-model binding mencari produknya.
  - **Milik toko lain dijawab 404, bukan 403.** Jawaban yang membedakan "bukan milikmu" dari "tidak ada" mengubah URL ini jadi alat menghitung produk toko sebelah.
  - **URL-nya relatif, bukan absolut.** URL absolut memakai `APP_URL`, yang di mesin pengembangan dan di belakang proxy kerap berbeda dari host yang sedang dipakai — dan gambar yang host-nya salah gagal diam-diam.
  - **Penanda versi diturunkan dari path berkas, bukan dari `filemtime`.** Mengganti gambar menghasilkan nama berkas UUID baru, jadi `?v=` ikut berganti tanpa perlu menyentuh disk untuk setiap baris yang dirender. Itulah yang membuat `Cache-Control: private, max-age=1 tahun, immutable` aman dipakai.
  - **URL gambar di-`$appends` pada model, tidak ditempel per controller.** Path di kolom `image` tidak boleh bocor ke browser; menempelkannya di satu tempat berarti tak ada halaman yang tergoda menyusun `"/storage/{$product->image}"` lagi — persis yang dulu dilakukan `ProductCard.vue`.
  - **Kisi POS memakai rendition 200 px, halaman produk memakai 800 px.** Beda pemakainya, beda anggarannya: kasir membuka puluhan kartu sekaligus di jaringan yang buruk, owner membuka satu layar penuh sambil menyunting.
  - **Inisialnya dipotong tiga huruf, dan ukurannya memakai satuan container query (`cqw`).** "Cafe Latte" jadi "CL" supaya tidak kembar dengan "Croissant"; lebih dari tiga huruf tak lagi terbaca sekilas di kartu kecil. `cqw` membuat hurufnya ikut skala kotak induk, jadi proporsinya sama di POS maupun daftar produk tanpa satu pun ukuran ditulis dua kali.
  - **Gambar dipotong PERSEGI, bukan diskalakan menurut rasio aslinya.** Semua permukaan sudah memakai `aspect-square` + `object-cover`, jadi pemotongan itu toh sudah terjadi di browser — melakukannya saat unggah berarti byte yang dibuang tidak ikut dikirim.

---

### [ADDITION] Tagihan Periode Terbit Sendiri Sebelum Aksesnya Menyempit (BL-044 butir b)
- **Tanggal:** 2026-08-06
- **Fase Terkait:** Di Luar Fase — menutup butir (b) `[BL-044]`; butir (c) sengaja ditunda
- **Dampak:** Service | Command | Config | Controller | Test
- **Breaking Change:** Tidak. Tidak ada tenant yang berpindah keadaan berbeda dari sebelumnya; yang bertambah hanya tagihan yang kini terbit sendiri. Penerbit manual di `/platform/invoices` tetap bekerja persis seperti dulu dan tetap menang atas penerbit otomatis untuk periode yang sama.
- **Deskripsi:** `advanceLifecycle()` memindahkan tenant `trial → grace → suspended` tanpa pernah menerbitkan satu pun tagihan. Tenant tidak pernah diberi tahu berapa yang harus dibayar — ia hanya menemukan aplikasinya berubah jadi hanya-baca. Satu-satunya jalan tagihan bulanan lahir adalah pemilik SaaS mengetiknya sendiri untuk tiap tenant, tiap bulan. Kini tagihan periode berikutnya terbit otomatis **7 hari sebelum** periode berjalan habis (`config/subscription.php` → `invoice_lead_days`), dengan jatuh tempo di hari periodenya habis.
- **Alasan:** Tagihan yang terbit tepat di hari periodenya habis sampai bersamaan dengan hilangnya kemampuan menulis — tenant membaca angkanya dan mendapati aplikasinya sudah setengah terkunci di menit yang sama. Menerbitkannya lebih awal membuat "berapa yang harus dibayar" tiba sebagai pemberitahuan, bukan sebagai penjelasan setelah kejadian.
- **Keputusan pemilik (2026-08-06):** tenant yang **tidak bisa ditagih tetap menempuh siklus hidupnya** — tidak ada tagihan yang terbit, tapi masa tenggang dan penangguhan berjalan seperti biasa. Dua alternatifnya ditolak: memperpanjang periode tenant bertarif Rp 0 akan membuat masa coba terbengkalai tidak pernah berakhir (bertentangan dengan "bulan kedua wajib bayar"), dan membekukan tenant di tempat sama-sama mencabut jaminan lama. Tarif yang kebetulan belum ditetapkan tidak boleh diam-diam mengubah aturan main.
- **File Terdampak:**
  - `app/Services/SubscriptionService.php` — `issueDuePeriodInvoices()`, `invoiceLeadDays()`, `pricingAsOf()`; `advanceLifecycle()` memanggilnya lebih dulu
  - `app/Console/Commands/AdvanceSubscriptionLifecycle.php` — tiga baris laporan baru, dua di antaranya peringatan
  - `config/subscription.php` — `invoice_lead_days = 7`
  - `app/Http/Controllers/Platform/InvoiceController.php` — `periodStart()` jadi pembungkus `SubscriptionService::pricingAsOf()`
  - `tests/Feature/Subscription/AutoInvoiceTest.php` — 12 test baru
- **Keputusan yang perlu diingat:**
  - **Penerbitannya menumpang di `advanceLifecycle()`, bukan jadi perintah terjadwal sendiri.** Ia harus berjalan sebelum tenant dipindahkan ke masa tenggang; sebagai dua baris jadwal, urutan itu bersandar pada kebetulan, dan jadwal yang kebetulan benar akan salah pada hari seseorang menggesernya.
  - **Satu resolver, satu titik waktu penetapan harga.** Nominalnya keluar dari `PricingService::resolveFor()` — resolver yang sama dengan penerbit manual — dan `pricingAsOf()` pindah ke service supaya keduanya tidak bisa memakai tanggal yang berbeda. Dua titik waktu berbeda melahirkan dua nominal untuk periode yang sama, dan yang menang tinggal soal siapa yang menekan tombol.
  - **Kunci periodenya adalah bulan tempat periode BERIKUTNYA dibuka.** Siklus berjangkar (`[BL-030]`) selalu membuka tepat satu periode per bulan kalender, jadi kunci `Y-m` tak pernah bertabrakan dengan dirinya sendiri. Penjaga duplikat yang sama dengan penerbit manual dipakai ulang, sehingga tagihan yang sudah diketik pemilik SaaS — mungkin dengan nominal keringanan — tidak pernah ditimpa.
  - **Tarif Rp 0 dan tarif yang tidak ada dipisah, karena obatnya berbeda.** Rp 0 berarti angkanya belum ditetapkan (`[BL-041]`(a)) — tidak dicatat ke jejak audit karena selama itu berlaku ia terjadi untuk setiap tenant setiap hari, dan jejak yang terisi hal yang sama akan menenggelamkan kejadian yang perlu terlihat. Tarif `null` berarti tenant Adaptif tanpa bracket cocok **dan** tanpa paket penampung — salah setel, bukan kebijakan, jadi ia dicatat sebagai kejadian sensitif `invoices.unpriced`. Keduanya juga dilaporkan sebagai peringatan terpisah oleh perintahnya.
  - **Menyembuhkan dirinya sendiri.** Begitu tarif paket ditetapkan di `/platform/pricing-rules`, tagihan mulai terbit tanpa satu baris kode pun berubah.
  - **Butir (c) `[BL-044]` — momen pilihan jalur di akhir masa coba — sengaja TIDAK dikerjakan.** Menawarkan dua jalur menuntut pagar kelayakan Harga Adaptif yang belum ada (`[BL-048]`, masih menunggu ambang omset dari pemilik). Menawarkannya sekarang berarti menyodorkan pilihan yang sistem belum bisa tolak — persis yang keputusan 2026-08-01 tutup.

---

### [DECISION] Tanggal Tagih Jadi Jangkar: Bulan Pendek Menjepit Sementara, Tidak Menggeser Selamanya (BL-030)
- **Tanggal:** 2026-08-05
- **Fase Terkait:** Di Luar Fase — menutup `[BL-030]`
- **Dampak:** Schema | Service | Model | Controller | Factory | Test
- **Breaking Change:** Ya untuk semantik penagihan, dan disengaja. Tiga perilaku berubah: periode tidak lagi meluber di bulan pendek, tanggal tagih tidak lagi bergeser saat tenant telat bayar, dan penjepitan bulan pendek tidak lagi permanen. Tidak ada tanggal tagih pelanggan yang bergerak hari ini — `invoices` masih kosong dan kedua langganan yang ada berjangkar tanggal 24.
- **Keputusan pemilik (2026-08-05), tiga butir:**
  1. **Langganan yang mulai 31 Januari jatuh tempo 28 Februari**, bukan 3 Maret (perilaku lama) dan bukan 1 Maret. Tenant tidak pernah ditagih untuk hari yang belum dilaluinya, dan tanggal tagih tetap di akhir bulan.
  2. **Periode berikutnya diturunkan dari jangkar tanggal tagih**, bukan dirantai dari akhir periode sebelumnya. 31 Jan → 28 Feb → **31** Mar.
  3. **Periode menyambung dari periode sebelumnya, bukan dari hari verifikasi bukti bayar.** Tenant yang telat bayar tetap membayar bulan yang sama.
- **Deskripsi:** `addMonth()` berarti "tanggal yang sama bulan depan, meluber bila tidak ada", sehingga periode yang mulai 31 Januari berakhir 3 Maret — 31 hari ditagih sebagai satu bulan, dan Februari dilewati sama sekali. Karena periode berikutnya dihitung dari akhir yang sudah meleset, pergeserannya menumpuk dan tidak pernah kembali. Terpisah dari itu, titik mulai periode adalah `now()` di `InvoiceController::verify()` — yaitu tanggal bukti bayar diperiksa, bukan awal periode langganan; inilah yang membuat tanggal 29/30/31 bisa menjadi titik mulai periode sama sekali, dan yang membuat tenant yang telat bayar lima hari menggeser tanggal tagihnya maju lima hari secara permanen.
- **Alasan:** Butir 1 dan 3 langsung menyangkut berapa yang ditagih, jadi tidak boleh diubah diam-diam sambil membetulkan hal lain — itu sebabnya `[BL-029]` sengaja tidak memborongnya. Butir 2 adalah yang membedakan perbaikan ini dari sekadar mengganti `addMonth()` jadi `addMonthNoOverflow()`: rantai tanpa jangkar tetap menggeser, hanya sekali dan permanen (31 → 28 → 28 → 28), yang persis keluhan "bergeser maju dan tidak pernah kembali".
- **File Terdampak:**
  - `database/migrations/2026_08_05_155739_add_billing_anchor_day_to_subscriptions_table.php` — kolom `billing_anchor_day` + backfill dari `current_period_end`
  - `app/Models/Subscription.php` — `billingAnchorDay()`, `anchoredDateIn()`, `nextAnchoredDateAfter()`
  - `app/Services/SubscriptionService.php` — `renewPeriod()` baru; `startTrial()` menulis jangkar; `canSwitchTrack()` & `trackSwitchAvailableAt()` tidak lagi meluber
  - `app/Http/Controllers/Platform/InvoiceController.php` — `verify()` memanggil `renewPeriod()`, tidak lagi menghitung tanggal sendiri
  - `database/migrations/2026_07_24_181638_add_subscription_columns_to_tenants_table.php` & `database/factories/SubscriptionFactory.php` — `addMonthNoOverflow()`
  - `tests/Feature/Subscription/SubscriptionBillingDateTest.php` — 12 test baru; `tests/Feature/Platform/PlatformBillingTest.php` — ekspektasi periode disesuaikan
- **Keputusan yang perlu diingat:**
  - **Jangkar harus DISIMPAN, tidak bisa disimpulkan.** Begitu sebuah periode berakhir di bulan pendek, tanggalnya sudah terjepit dan hari aslinya tidak bisa dipulihkan dari data mana pun. Karena itu `billing_anchor_day` ditulis sejak `startTrial()`. Ini menambah satu kolom yang `[BL-030]` semula perkirakan tidak perlu — konsekuensi langsung dari memilih jangkar, bukan rantai.
  - **`addMonthNoOverflow()` dipakai untuk berpindah bulan, bukan untuk menentukan tanggal.** Pindah bulan dari 31 Jan tanpa penjaga luberan mendarat di 3 Maret dan melewatkan Februari; tanggalnya kemudian ditentukan ulang oleh jangkar, sehingga penjepitan tidak menular.
  - **Tunggakan tidak ditumpuk.** Bila periode yang tersambung ternyata sudah lewat seluruhnya, periodenya dimajukan sampai berakhir di masa depan — satu pembayaran memulihkan satu periode ke depan, bukan menyeret tenant ke periode usai lalu langsung menangguhkannya lagi di hari yang sama. **Perlu ditinjau ulang saat `[BL-044]` dikerjakan:** begitu tagihan terbit otomatis tiap periode, tiap bulan yang terlewat punya tagihannya sendiri, dan "melompati" periode berarti melompati tagihan.
  - **Ditemukan saat mengerjakannya:** `canSwitchTrack()` mengurangi 3 bulan dari `now()` sementara `trackSwitchAvailableAt()` menambahkan 3 bulan ke `track_changed_at`. Setara dalam aritmetika biasa, **tidak** setara begitu penjaga luberan ikut bermain — 30 Nov + 3 bulan dijepit ke 28 Feb, tapi 28 Feb − 3 bulan mendarat di 28 Nov, sehingga layar menjanjikan 28 Februari sementara gerbangnya baru terbuka 2 Maret. `canSwitchTrack()` kini memanggil `trackSwitchAvailableAt()`; satu perhitungan, satu jawaban.
  - **`InvoiceController::periodStart()` sengaja TIDAK disentuh.** Ia memakai `startOfMonth()` dan memang benar — titik waktu penetapan harga, bukan awal periode langganan. Dua hal berbeda di berkas yang sama.

---

### [HOTFIX] Pengalihan Setelah Masuk Memilah Dua Dunia, Bukan Cuma Sebelum Masuk (BL-043)
- **Tanggal:** 2026-08-05
- **Fase Terkait:** Di Luar Fase — menutup `[BL-043]`
- **Dampak:** Bootstrap | Controller | Test
- **Breaking Change:** Tidak untuk akun platform. Ya dalam satu hal yang disengaja di sisi tenant: pengguna yang **sudah** masuk lalu membuka `/login` kini mendarat di areanya sendiri (owner → dashboard, kasir → POS), bukan di landing publik seperti sebelumnya.
- **Deskripsi:** Guard, broker kata sandi, dan pengalihan **tamu** sudah lama terpisah rapi antara platform dan tenant — yang tidak pernah dipisah adalah pengalihan **setelah** berhasil masuk. Dua jalur berbeda, dua gejala berbeda, keduanya berakhir di luar `/platform`: (a) akun platform yang sesinya masih hidup lalu membuka `/platform/login` dilempar ke `/`, yaitu landing publik yang tombol utamanya menuju login **tenant**; (b) login platform pada peramban yang pernah menyentuh area tenant dalam keadaan keluar dibajak oleh `url.intended` yang tertinggal, sehingga berakhir di halaman masuk pemilik usaha meski login-nya berhasil dan `login.success` sudah tercatat.
- **Alasan:** Cabang (a) terjadi karena `RedirectIfAuthenticated` bawaan framework mencari rute bernama persis `dashboard` lalu `home`; aplikasi ini tidak punya keduanya (rutenya `owner.dashboard` dan `platform.dashboard`), jadi jatuh ke `'/'`. Cabang (b) terjadi karena `url.intended` adalah **satu kunci untuk kedua guard** — sesinya satu — dan `intended()` polos memenangkan nilai itu atas argumen bawaannya. Gejala (b) bersyarat: sesi peramban yang bersih tidak pernah mengalaminya, dan itu sebabnya cacat ini lolos sekian lama — pengujian di jendela penyamaran yang baru akan selalu lulus.
- **File Terdampak:**
  - `bootstrap/app.php` — `redirectUsersTo()` dipasang berdampingan dengan `redirectGuestsTo()` yang sudah ada
  - `app/Http/Controllers/Platform/AuthController.php` — `intendedPlatformUrl()` menggantikan `intended()` polos
  - `tests/Feature/Platform/PlatformAuthTest.php` — 5 test baru (4 di antaranya gagal sebelum perbaikan ini)
- **Keputusan yang perlu diingat:**
  - **Kedua arah pengalihan ditaruh berdampingan di `bootstrap/app.php`**, memakai pemilahan prefiks yang sama persis. Yang membuat cacat ini mungkin adalah satu arah ditulis dan kembarannya tidak; menaruhnya bersebelahan membuat keduanya terbaca sebagai satu keputusan, sehingga arah yang hilang terlihat.
  - **`url.intended` disaring, bukan dihapus.** Menghapusnya akan menghukum akun platform yang mengklik tautan langsung ke `/platform/tenants/7` lalu diminta masuk — ia tetap layak dikembalikan ke sana. Yang dibuang hanya tujuan di luar prefiks `platform`.
  - **Dipakai `pull`, bukan `get`.** Tujuan yang ditolak harus ikut hangus; kalau hanya diabaikan, ia menunggu di sesi dan menyerang perpindahan halaman berikutnya yang kebetulan memakai `intended()`.
  - **Dicocokkan sebagai segmen utuh dan host ikut diperiksa.** `'/platformx'` bukan area platform, dan memeriksa path saja akan meloloskan URL absolut ke host lain yang kebetulan memuat prefiks yang sama. Hari ini kunci itu hanya ditulis middleware kita sendiri — pemeriksaan host ada supaya itu tidak perlu tetap benar selamanya.
  - **Sisi tenant ikut dibereskan karena callback-nya satu.** `redirectUsersTo` global, jadi cabang non-platform harus mengembalikan sesuatu; ia memilih berdasarkan peran persis seperti `Auth\AuthController::login()` setelah kata sandi cocok, alih-alih membuang keduanya ke landing.

---

### [DECISION] Seeder Transaksi Menambal Hari Kosong, Bukan Mereset atau Menumpuk
- **Tanggal:** 2026-08-01
- **Fase Terkait:** Di Luar Fase — perawatan data demo
- **Dampak:** Seeder | Dokumentasi | Test
- **Breaking Change:** Tidak untuk aplikasi. Ya untuk kebiasaan: `CafeStudyCaseSeeder` **tidak lagi menghapus** data transaksional Kopi Story sebelum menyemai, jadi ia bukan lagi cara mendapatkan data yang bersih. Untuk itu jalurnya `migrate:fresh --seed`.
- **Deskripsi:** Kedua seeder transaksi demo kini hanya menyemai **tanggal yang belum punya transaksi sama sekali**. `DemoTransactionSeeder` berhenti menumpuk (dulu menjalankannya dua kali menggandakan omzet Kopi Nusantara), dan `CafeStudyCaseSeeder` berhenti mereset (dulu ia menghapus seluruh transaksi & mutasi stok tenant lalu membangunnya ulang). Restock bulanan Kopi Story juga hanya ditambahkan untuk bulan yang belum punya restock, dan sesi kas hariannya hanya dibuat untuk hari yang penjualannya baru saja lahir dari seeder itu. Penomoran kode transaksi direset per hari.
- **Alasan:** Keduanya menyemai "sampai `now()`", jadi keduanya basi begitu dibiarkan beberapa hari — dan keduanya dijalankan ulang tepat pada hari demo, saat kesalahan paling mahal. Dua-duanya punya cara gagal masing-masing, dan dua-duanya buruk: yang menumpuk membuat omzet berlipat tanpa peringatan, yang mereset membuang transaksi yang baru saja dibuat lewat UI untuk keperluan demo itu sendiri. Menambal hari kosong tidak melakukan keduanya, dan tidak menuntut siapa pun mengingat seeder mana yang berperilaku bagaimana.
- **File Terdampak:**
  - `database/seeders/Concerns/FillsMissingSalesDays.php` — **baru**, satu-satunya pembaca kalender penjualan
  - `database/seeders/DemoTransactionSeeder.php` — jendela 90 hari, lewati hari terisi, penghitung kode per hari
  - `database/seeders/CafeStudyCaseSeeder.php` — `resetTransactionalData()` **dihapus**; restock per bulan yang kosong; stok berangkat dari nilai sekarang, bukan baseline
  - `tests/Feature/DemoSeederTest.php` — **baru**, 4 test
  - `PANDUAN-DEMO.md` — ditulis ulang menyeluruh (lihat catatan di bawah)
- **Keputusan yang perlu diingat:**
  - **Yang dipakai adalah tanggal efektif (`COALESCE(occurred_at, created_at)`), bukan `created_at` mentah.** Alasannya sama seperti seluruh laporan: penjualan offline disinkronkan setelah kejadiannya, dan hari yang sebenarnya sudah terisi akan tampak kosong bila dinilai dari waktu sinkronisasi.
  - **Hari yang isinya TIPIS tetap dianggap terisi.** Satu transaksi hasil uji coba membuat hari itu dilewati, dan di grafik 7 hari terakhir ia tampak sebagai lekukan. Ambang minimum sengaja tidak dipasang: angka berapa pun yang dipilih akan menimpa penjualan sungguhan pada hari yang di bawahnya, dan seeder yang boleh menambah baris ke hari yang sudah dipakai orang adalah seeder yang tidak bisa dipercaya menjelang demo.
  - **Penomoran kode direset tiap hari.** `transactions` unik pada `(tenant_id, code)` dan tanggalnya sudah ikut di dalam kodenya, jadi penomoran per hari tak pernah bertabrakan dengan hari lain. Penghitung yang berjalan lintas hari tidak menjamin itu — rentang yang disemai berbeda tiap kali dijalankan, sehingga hari yang sama bisa menerima nomor yang sama dua kali. Formatnya tetap 5 digit, berbeda dari 3 digit milik `TransactionService`, jadi kode seeder dan kode aplikasi tak pernah berebut nomor pada hari yang sama.
  - **Stok Kopi Story berangkat dari nilai yang tercatat sekarang, bukan dari baseline.** Mengembalikannya ke baseline tiap kali dijalankan berarti menghadiahkan stok yang sudah terjual — kekeliruan yang dulu tertutupi karena seluruh penjualannya memang ikut dihapus.
  - **Sesi kas hanya untuk hari yang benar-benar baru disemai.** Angka `expected_amount` dihitung dari kas yang dikumpulkan seeder pada hari itu; menerapkannya ke hari yang sudah berisi akan menimpa sesi kas yang sudah cocok dengan penjualan tunai yang tercatat di sana.
  - **`DatabaseSeeder` sengaja TIDAK diikutkan.** Ia memakai `Tenant::create()` dan memang bukan seeder yang dijalankan berulang; menjadikannya idempotent adalah pekerjaan tersendiri dengan pertanyaannya sendiri (apa yang terjadi pada menu yang sudah disunting owner?).
- **Catatan:** `PANDUAN-DEMO.md` terakhir ditulis 2026-07-25 dan sejak itu dua puluh dua commit mendarat. Yang paling salah bukan angkanya melainkan satu kalimatnya: panduan itu masih menyatakan layar antrean dapur "tidak ada halamannya", padahal `[BL-019]` menutupnya 2026-07-29. Penulisan ulangnya menambahkan bagian **§1 SAPI dalam Satu Halaman** — ringkasan fitur, enam pembeda, dan batas yang belum terpasang — supaya produk ini bisa dijelaskan tanpa membaca delapan bagian lainnya.

---

### [ADDITION] Aturan Tarif Bisa Disunting & Dihentikan, Paket Punya Batas AI dan Peran Penampung (BL-046, BL-047)
- **Tanggal:** 2026-08-01
- **Fase Terkait:** Di Luar Fase — kelanjutan `PHASE SAAS`, menutup sebagian `[BL-046]` & `[BL-047]`
- **Dampak:** Migrasi | Model | Service | Controller | Job | Frontend | Test
- **Breaking Change:** Tidak. `PricingService::resolveFor()` bertambah kunci `source`; kunci lama tidak berubah artinya.
- **Deskripsi:** Halaman `/platform/pricing-rules` sebelumnya hanya bisa MENERBITKAN aturan dan MEMBATALKAN yang belum berlaku. Kini ia punya empat aksi yang jelas namanya: **Ubah** (aturan yang belum berlaku, disunting di tempat berikut syaratnya), **Revisi** (aturan yang sudah berlaku — membuka form terisi dengan label & syarat yang sama dan tanggal berlaku besok), **Batalkan**, dan **Hentikan** (aturan yang sudah berlaku, lewat dialog konfirmasi yang menyebutkan ke mana tenantnya akan jatuh). Paket mendapat dua kolom baru: **batas analisis AI harian** dan penanda **paket penampung jalur Adaptif**. Dua kolom tabel yang tidak menjawab pertanyaan siapa pun — "Tarif pengguna tambahan" dan "Ketersediaan" — turun dari tabel; yang pertama tetap ada di form karena ia benar-benar menagih, yang kedua dicabut seluruhnya karena tidak menggerbangi apa pun. Ditambah dua panel penjelas di bawah tabel: **untuk apa prioritas** dan **apa yang terjadi bila tak ada aturan yang cocok**.
- **Alasan:** Tiga hal yang saling menahan. **(1)** Larangan menghapus aturan yang sudah berlaku benar alasannya — ia dasar harga periode yang sudah ditagihkan — tapi keliru kesimpulannya: pemilik SaaS jadi tidak punya cara APA PUN menghentikan aturan yang telanjur salah terbit, dan "terbitkan pengganti berlabel sama" tidak menolong bila yang diinginkan justru meniadakan kelompoknya. **(2)** Menghentikan aturan tanpa penampung berarti tenant kehilangan tarif sama sekali — `resolveFor()` mengembalikan `price = null`, dan tagihannya harus diketik manual tanpa dasar. **(3)** Batas AI hidup di `.env` sebagai satu angka untuk semua, sehingga paket Premium yang sudah bisa dibuat dari panel tidak punya satu pun pembeda yang berakibat pada perilaku aplikasi.
- **File Terdampak:**
  - `database/migrations/2026_08_01_024155_add_limits_and_fallback_to_plans_table.php` — **baru**: `plans.limits` (JSON), `plans.is_adaptive_fallback`
  - `database/migrations/2026_08_01_024156_add_soft_deletes_to_pricing_rules_table.php` — **baru**: `pricing_rules.deleted_at`
  - `app/Services/Ai/AiQuota.php` — **baru**: satu-satunya pembaca kuota AI
  - `app/Models/Plan.php` — `limit()`, `setLimit()`, `setAdaptiveFallback()`, `adaptiveFallback()`
  - `app/Models/PricingRule.php` — `SoftDeletes`
  - `app/Services/PricingService.php` — `reviseRule()`, `fallbackPlanFor()`, kunci `source` pada `resolveFor()`/`currentBracketFor()`
  - `app/Http/Controllers/Platform/PricingRuleController.php` — `updateRule()`, `destroyRule()` dirombak, validasi paket bertambah
  - `app/Http/Controllers/Platform/InvoiceController.php` — `suggestion()` mengirim `source`
  - `app/Jobs/RunAiAnalysisJob.php` — kuota lewat `AiQuota`; **perbaikan** `incrementUsage()`
  - `app/Http/Controllers/Owner/SettingsController.php` — angka kuota dibaca dari `AiQuota`
  - `routes/web.php` — `PUT /platform/pricing-rules/{rule}`
  - `resources/js/Pages/Platform/PricingRules/Index.vue`, `resources/js/Pages/Platform/Tenants/Show.vue`
  - `tests/Feature/Platform/PricingGrandfatherTest.php` (+7), `tests/Feature/Ai/RunAiAnalysisJobTest.php` (+4)
- **Keputusan yang perlu diingat:**
  - **Menghapus aturan = `SoftDeletes`, bukan `DELETE`.** `invoices.pricing_rule_id` ber-`nullOnDelete`: penghapusan sungguhan memutus tautan tagihan lama ke aturan yang menghasilkannya, dan pertanyaan "kenapa angkanya segini" jadi tak terjawab justru pada tagihan yang paling mungkin dipersoalkan. `deleted_at` memuaskan keduanya — berhenti dinilai seketika, barisnya tetap ada.
  - **Aturan yang sudah berlaku tetap TIDAK bisa disunting di tempat.** Yang ditambahkan adalah suntingan untuk aturan yang BELUM berlaku, plus jalan pintas "Revisi" yang mengisi form penerbitan dengan isi aturan lama. Grandfathering tidak dilonggarkan sedikit pun; yang hilang hanya keharusan mengetik ulang seluruh syarat demi memperbaiki satu angka.
  - **Paket penampung ditunjuk, tidak ditebak.** Bila belum ada yang ditunjuk, tenant jalur Adaptif yang tak cocok aturan mana pun tetap berakhir tanpa tarif. Menjatuhkannya ke paketnya sendiri terdengar lebih ramah, tapi paket tenant adaptif lazimnya paket dasar seharga Rp 0: satu aturan yang dihentikan akan diam-diam menggratiskan layanan bagi seluruh kelompoknya. Tarif kosong yang kelihatan lebih baik daripada tarif nol yang tidak.
  - **Jalur Harga Tetap jatuh ke paketnya sendiri, jalur Adaptif ke penampung.** Dua jawaban berbeda untuk pertanyaan yang sama, karena aturan adaptif memang tidak pernah ditujukan kepada tenant jalur tetap.
  - **`source` ditambahkan karena harga yang lahir dari aturan dan harga yang lahir dari ketiadaan aturan sama-sama berupa angka.** Tanpa penanda itu, keduanya mustahil dibedakan pemanggil — dan formulir tagihan akan menyodorkan tarif paket seolah hasil aturan.
  - **Batas paket sebagai SATU kolom JSON `limits`, bukan satu kolom per batas** (`[BL-046]`(b)). Kunci yang tidak ada berarti "ikut bawaan platform", bukan nol; `0` yang ditulis sendiri berarti paket ini tidak menyertakan AI. Bedanya ditampilkan apa adanya di tabel ("5 — bawaan" / "10" / "Tidak termasuk") supaya angka yang kebetulan sama dengan bawaan hari ini tidak tertukar dengan angka yang memang disetel.
  - **Kuota AI dibaca satu kelas** (`AiQuota`), dipakai `RunAiAnalysisJob` DAN halaman Pengaturan owner. Angka yang dibacakan ke owner dan angka yang menolak permintaannya wajib identik; selama keduanya menghitung sendiri-sendiri, cepat atau lambat layar menjanjikan sisa yang ditolak antrean.
  - **Kolom `plans.is_active` dicabut dari panel.** Ia tidak menggerbangi apa pun di seluruh aplikasi — tidak ada pemilih paket yang menyaringnya — sehingga "Ketersediaan" adalah kolom yang menjanjikan kendali yang tidak ada. Kolomnya tetap di basis data; yang dihapus adalah klaimnya di layar.
  - **Ditemukan sambil lewat: analisis AI KEDUA seorang tenant di hari yang sama selalu gagal.** `incrementUsage()` memakai `firstOrCreate` berkunci tanggal, padahal kolom `date` tersimpan sebagai datetime — barisnya tak pernah ketemu, lalu penyisipan keduanya ditolak indeks unik. Diperbaiki dengan `whereDate`, dengan test yang gagal sebelum perbaikannya.

---

### [ADDITION] Warna Khas per Jalur Harga, Jejak Persetujuan, & Perkiraan Tarif Adaptif
- **Tanggal:** 2026-08-01
- **Fase Terkait:** Di Luar Fase — kelanjutan `PHASE SAAS Tahap C`
- **Dampak:** Service | Controller | Frontend | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** Halaman `/langganan` mendapat tiga hal. **(1) Identitas warna per jalur harga** — Masa Coba ungu, Harga Tetap biru, Harga Adaptif hijau — berupa chip di sebelah judul dan kepala berwarna di kartu detail paket, yang kini juga menyebut tarif pengguna tambahan, akhir periode, dan bar pemakaian kursi. **(2) Jejak persetujuan** — status "Disetujui / Perlu disetujui ulang / Belum disetujui" sebagai chip, plus versi, tanggal, dan nama penyetujunya. **(3) Perkiraan tarif adaptif** untuk tenant jalur tetap: omzet bulan lalu miliknya sendiri, kelompok tarif yang ia masuki, perbandingan "tarif sekarang → perkiraan adaptif", dan CTA yang berubah jadi tombol hijau **"Pindah ke Harga Adaptif"** hanya bila memang lebih murah.
- **Alasan:** Ajakan pindah ke Harga Adaptif sebelumnya meminta tenant menyerahkan angka penjualannya demi tarif yang tak pernah ia lihat lebih dulu — tukar-menukar yang tidak seimbang, dan ajakan yang wajar diabaikan. Sementara itu "Sudah disetujui" tanpa versi, tanggal, atau nama menyuruh tenant memercayai catatan yang tidak bisa ia periksa, padahal seluruh rancangan persetujuan di fase SAAS justru dibangun agar bisa diperiksa. Warna jalur menyelesaikan hal ketiga: tiga model harga yang berbeda mendasar sebelumnya hanya dibedakan satu baris teks di tengah halaman.
- **File Terdampak:**
  - `app/Services/Pricing/SubsidyEstimator.php` — **baru**
  - `app/Http/Controllers/Billing/SubscriptionController.php` — `subsidy.estimate`, versi & jejak `consent`
  - `resources/js/Pages/Billing/Show.vue` — `trackIdentity`, `consentState`, `subsidyEstimate`, kartu paket & persetujuan dirombak
  - `tests/Feature/Subscription/SubsidyTrackTest.php` — 7 test baru
- **Keputusan yang perlu diingat:**
  - **Perkiraan dihitung on-the-fly dan TIDAK PERNAH disimpan.** Gerbang privasi `ComputeTenantMonthlyRevenue` menjaga **pandangan pengelola layanan**, bukan pandangan pemilik toko atas datanya sendiri — pemilik sudah bisa menjumlahkan angka yang sama dari Laporan Harian. Yang tidak boleh lahir adalah baris `tenant_monthly_metrics` yang bisa dibaca halaman platform, dan ada test yang menjaganya tetap nol setelah halaman dibuka.
  - **`SubsidyEstimator` sengaja BUKAN `DimensionResolver`.** Satu-satunya jalan dari data penjualan ke tagihan tetap `tenant_monthly_metrics`; itulah yang membuat batasnya bisa ditegakkan `PlatformArchTest`. Kelas ini hanya boleh dipakai menampilkan, tidak pernah menagih.
  - **Perkiraan memakai bulan yang sudah TUTUP, dengan aturan hitung yang sama persis dengan job sungguhan** (hanya `completed`, memakai tanggal efektif). Perkiraan yang aturannya berbeda akan meleset justru pada tenant yang paling perlu memercayainya. Bulan berjalan ditolak karena angkanya berubah tiap hari.
  - **Nol penjualan TIDAK diperlakukan sebagai omzet nol.** Tanpa penjagaan itu, tenant baru akan jatuh ke kelompok termurah dan disodori penghematan yang tidak nyata. Keadaan itu punya kalimatnya sendiri, begitu pula keadaan "belum ada kelompok tarif yang cocok".
  - **CTA hijau hanya muncul saat memang lebih murah.** Bila tidak, halamannya berkata terus terang bahwa Harga Tetap masih lebih menguntungkan dan tombolnya turun jadi tautan biasa. Mendorong tenant pindah jalur ke tarif yang lebih mahal — sambil meminta datanya — adalah ajakan yang merugikan orang yang menerimanya.
  - **Kelas warna Tailwind ditulis UTUH, bukan dirakit runtime.** `text-${warna}-600` tidak pernah ikut ter-generate karena Tailwind memindai berkas sebagai teks; warnanya akan hilang diam-diam di build produksi, bukan gagal ramai-ramai saat dikembangkan.
  - **Tiga keadaan persetujuan, bukan dua.** "Pernah setuju tapi teksnya sudah kami ganti" bukan hal yang sama dengan "belum pernah setuju" — yang pertama bukan kelalaian tenant, dan menyebutnya begitu menuduh orang atas perubahan yang kami sendiri yang melakukannya.
  - **Tindakan berbentuk tombol, bukan teks bergaris bawah.** "Baca dokumen persetujuan" dan "Cabut persetujuan Harga Adaptif" sebelumnya tautan telanjang di antara paragraf. Keduanya tindakan yang punya akibat — yang kedua bahkan menghapus data seketika — dan tindakan sebesar itu tidak pantas menyaru sebagai kalimat. Bobotnya dibedakan: persetujuan yang sudah ada dapat tombol bergaris netral, pencabutan dapat tombol bergaris merah.

---

### [DECISION] Halaman Langganan Masuk ke Shell Owner, dan Sidebar Mati Saat Ditangguhkan
- **Tanggal:** 2026-08-01
- **Fase Terkait:** Di Luar Fase — kelanjutan `[BL-040]`
- **Dampak:** Middleware | Frontend | Test
- **Breaking Change:** Tidak.
- **Deskripsi:** `/langganan` tidak lagi berdiri sendiri. Ia memakai `OwnerLayout` seperti halaman tenant lainnya, sehingga kehilangan kop "SAPI POS", judul mandiri, dan tautan "Kembali ke aplikasi"-nya — semuanya sudah disediakan cangkangnya. Sebagai gantinya `OwnerLayout` belajar satu keadaan baru: saat `auth.tenant.is_suspended`, seluruh item sidebar selain `/langganan` dirender sebagai `<span>` mati (`aria-disabled`, `cursor-not-allowed`, warna teredam), pintasan **Kasir** di topbar disembunyikan, dan sebuah keterangan singkat muncul di atas navigasi. Halaman **persetujuan** (`/langganan/persetujuan`) sengaja TIDAK ikut — lihat keputusan di bawah.
- **Alasan:** Keputusan sebelumnya (entri `[BL-040]`, 2026-07-31) menahan halaman ini di luar `OwnerLayout` dengan alasan yang sah: ia harus masuk akal bagi kasir dan bagi tenant yang ditangguhkan, dan sidebar penuh tautan yang semuanya memantul balik ke halaman yang sama lebih menyesatkan daripada tidak ada sidebar. Alasan itu tidak dibantah — ia dijawab. Keberatannya sebenarnya bukan tentang *cangkangnya*, melainkan tentang *navigasi yang berbohong*; begitu navigasinya jujur mengaku mati, satu-satunya sisa dari halaman mandiri adalah ketidakseragaman. Pemilik menempuh jalur yang sama seperti halaman lain, dan tidak ada lagi satu halaman tenant yang tampilannya beda sendiri.
- **File Terdampak:**
  - `app/Http/Middleware/HandleInertiaRequests.php` — `auth.tenant.is_suspended`
  - `resources/js/Layouts/OwnerLayout.vue` — `isSuspended`/`isLocked`, item nav jadi `<component :is>`, pintasan Kasir bersyarat, keterangan di atas nav
  - `resources/js/Pages/Billing/Show.vue` — `defineOptions({ layout: OwnerLayout })`, cangkang mandiri & `backHref` dilepas, blok `flashError` sebaris dihapus
  - `tests/Feature/Subscription/SubscriptionLifecycleTest.php` — 2 test baru
- **Keputusan yang perlu diingat:**
  - **Item nav DIMATIKAN, bukan disembunyikan.** Sidebar yang menyusut jadi satu baris membuat aplikasinya tampak hilang, bukan terkunci — dan kasir yang mendarat di sini justru perlu melihat bahwa yang dipegangnya masih ada, hanya sedang tertutup. Menyembunyikan juga menghapus satu-satunya penjelasan visual atas kenapa ia terlempar ke sini.
  - **`is_suspended` dibagikan sebagai skalar, BUKAN masuk `features`.** `features` menjawab "apakah outlet ini punya fitur X"; ini menjawab "apakah semua pintu lain sedang terkunci". Menumpangkannya ke `features` akan membuat `hasFeature()` menjawab pertanyaan yang bukan miliknya. Tenant-nya sudah dimuat, jadi tidak ada query tambahan.
  - **Gerbangnya tetap `EnsureSubscriptionActive`, sidebar hanya cermin.** Item mati tidak mengamankan apa pun; ia hanya berhenti berbohong. Prinsip yang sama sudah berlaku untuk penyaringan per-permission di layout ini.
  - **Rute `billing.show` tetap TIDAK digerbang `role:owner`.** Tidak ada yang berubah di sisi rute — kasir tetap boleh membukanya, dan kini ia mendarat di cangkang yang dikenalnya dengan navigasi yang jujur, bukan di halaman asing.
  - **`Consent.vue` tetap mandiri.** Ia alur baca-sampai-habis-lalu-setuju yang tombolnya sengaja baru hidup setelah teksnya digulir tuntas. Sidebar di sekelilingnya mengundang keluar dari alur yang justru dirancang untuk tidak bisa dilewati — persetujuan yang bisa ditinggalkan setengah jalan lewat menu bukan persetujuan yang lebih baik.
  - **Blok galat sebaris di halaman langganan dihapus, bukan dipertahankan berdampingan.** `OwnerLayout` sudah merender `FlashMessage`, dan pesan penangguhannya sendiri sudah dijelaskan panjang lebar oleh kartu keadaan (`state.suspended`). Yang tersisa hanya duplikasi.

---

### [ADDITION] Identitas Pesanan Bisa Diisi dari Kasir, & Nomor Panggil Lepas dari Papan Dapur (BL-026)
- **Tanggal:** 2026-08-01
- **Fase Terkait:** Di Luar Fase — `[BL-026]`
- **Dampak:** Schema | Model | Request | Service | Middleware | Frontend | Test
- **Breaking Change:** Tidak. Kolom baru berbawaan `none`, yang berarti perilaku hari ini persis seperti sebelumnya sampai ada owner yang memilih mode lain.
- **Deskripsi:** Owner kini memilih **satu** cara mengenali pesanannya di Pengaturan — `Tidak dipakai` / `Nama pelanggan` / `Nomor meja` / `Kode panggil (otomatis)`. Kasir mengisinya lewat **satu modal yang dipakai kedua jalur**, bayar-langsung maupun tunda-bayar, dengan bentuk input mengikuti mode: teks bebas untuk nama, papan angka untuk meja, dan tanpa modal sama sekali untuk kode (nomornya dialokasikan server). Identitasnya ikut tercetak di struk (layar dan thermal), tampil sebagai kartu besar di modal transaksi berhasil, dan jadi chip di riwayat kasir. Terpisah dari itu, **nomor panggil tidak lagi bersyarat papan dapur**.
- **Alasan:** Kolomnya (`queue_number`, `customer_name`, `table_number`), alokator nomor hariannya, dan tampilannya di papan antrian semuanya sudah berdiri, dan jalur self-order sudah mengirim ketiganya. Yang hilang persis satu lapis: cara kasir mengisinya. Di POS identitas hanya bisa diberikan lewat modal "Tunda Bayar" berupa nama bebas, sehingga transaksi bayar-langsung — justru yang perlu dipanggil saat siap — tidak bisa diberi identitas apa pun. `table_number` bahkan tidak lolos `StoreTransactionRequest`, jadi mustahil dikirim dari kasir sekalipun UI-nya dibuat.
- **File Terdampak:**
  - `database/migrations/2026_07_31_180043_add_order_identity_mode_to_tenants_table.php` — **baru**
  - `app/Models/Tenant.php` — empat konstanta mode, `orderIdentityModes()`, `usesCallNumber()`
  - `app/Http/Requests/StoreTransactionRequest.php` — `table_number` divalidasi
  - `app/Http/Requests/SyncOfflineTransactionsRequest.php` — identitas ikut payload outbox
  - `app/Services/TransactionService.php` — `$needsCallNumber` di `checkout()` & konfirmasi self-order, identitas di `commitOffline()`
  - `app/Http/Controllers/Owner/SettingsController.php` — mode dikirim & divalidasi
  - `app/Http/Middleware/HandleInertiaRequests.php` — `auth.tenant.order_identity_mode`
  - `resources/js/Components/OrderIdentityModal.vue` — **baru**, satu modal untuk kedua jalur
  - `resources/js/Pages/Cashier/POS.vue` — modal nama open-bill lama dihapus, kedua jalur lewat modal baru
  - `resources/js/Pages/Owner/Settings/Index.vue` — pemilih mode + keterangan per mode
  - `resources/js/Components/{ReceiptModal,TransactionSuccessModal}.vue`, `resources/js/services/escpos.js`, `resources/js/Pages/Cashier/TransactionHistory.vue` — identitas ditampilkan
  - `resources/js/composables/useOfflineQueue.js` — identitas menumpang outbox
  - `tests/Feature/OrderIdentityTest.php` — **baru**, 13 test
- **Keputusan yang perlu diingat:**
  - **Satu mode, bukan tiga saklar.** Outlet yang disodori nama + meja + kode sekaligus akan mengisi nol dari tiga, dan identitas yang kadang diisi kadang tidak lebih buruk daripada tidak punya identitas sama sekali — tidak ada yang bisa bersandar padanya.
  - **Nomor panggil lepas dari `kitchen_queue`, tapi `fulfillment_status` TIDAK ikut lepas.** Mode `code` memberi nomor tanpa memasukkan pesanan ke papan. Memanggil pelanggan dan menjalankan papan dapur adalah dua kebutuhan berbeda; warung yang hanya ingin memanggil tidak seharusnya dipaksa menyalakan papan yang tak akan pernah dilihat siapa pun. Ada test yang menjaga `queue_number != null && fulfillment_status == null`.
  - **Mode identitas tidak pernah menolak penjualan.** Modalnya selalu punya tombol **Lewati**, dan servernya menerima identitas kosong dalam mode apa pun. Ini alat bantu operasional, bukan syarat sah penjualan — prinsip yang sama sudah dipakai untuk `upsell_events`, dan `[BL-025]` tetap satu-satunya pengaturan yang boleh menahan tombol bayar.
  - **Tunda bayar SELALU ditanya, jatuh ke nama saat mode `none`.** Tagihan yang menunggu dibayar harus bisa dikenali lagi nanti, dan itu benar bahkan untuk outlet yang tidak memanggil pesanan. Bayar-langsung hanya ditanya bila owner memang memilih mode — outlet yang berjalan hari ini tidak mendapat satu ketukan tambahan tanpa ada yang memintanya.
  - **Nomor panggil TIDAK dialokasikan untuk penjualan offline.** Nama dan meja ikut menumpang outbox, tapi nomor berguna justru karena tercetak di struk yang dipegang pelanggan; nomor yang lahir saat sinkronisasi — berjam-jam setelah struknya dibawa pulang — tidak memanggil siapa pun. Ini batas yang sama seperti papan dapur yang memang sudah tidak aktif saat offline.
  - **`string`, bukan `enum`.** Menambah mode kelak tidak boleh memaksa SQLite membangun ulang tabelnya — ongkos yang sudah dibayar sekali di fase EDIT-TX.

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
