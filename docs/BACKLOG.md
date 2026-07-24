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

### [BL-014] Pendaftaran Berulang Demi Trial Gratis Baru
- **Ditemukan:** 2026-07-25
- **Sumber:** Ditunda sadar-sadar saat mengerjakan Tahap B — lihat `[BL-005]`
- **Status:** Open
- **Prioritas:** Medium (belum menggigit selama tenant sedikit; jadi masalah nyata begitu pendaftaran ramai)
- **Area Terdampak:**
  - `app/Http/Controllers/Auth/AuthController.php:26` (`register`) — tak ada penyaring apa pun
  - `users.email_verified_at` — kolomnya ada sejak migrasi bawaan Laravel, **tidak pernah dipakai**
- **Deskripsi:**
  Registrasi self-serve memberi trial 1 bulan tanpa syarat. Siapa pun bisa mendaftar berulang kali dengan alamat surel baru dan memakai layanan gratis selamanya. Tahap B sengaja tidak menanganinya supaya lingkupnya tetap pada model langganan.
- **Usulan Perbaikan:**
  1. Verifikasi surel wajib sebelum tenant bisa dipakai. Kontrak `MustVerifyEmail` sudah tersedia di Laravel dan kolomnya sudah ada — yang belum ada notifikasi, halaman, middleware, dan penanganan tenant yang tak pernah verifikasi.
  2. Pertimbangkan verifikasi nomor telepon; untuk UMKM Indonesia, nomor jauh lebih mahal dibuat berulang daripada alamat surel.
  3. Penandaan identitas serupa (nama usaha + telepon) untuk ditinjau manual, bukan diblokir otomatis — memblokir otomatis akan menjegal warung yang benar-benar punya dua cabang.

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

### [BL-011] Belum Ada Peringatan Saat Percobaan Masuk Gagal Menumpuk
- **Ditemukan:** 2026-07-22
- **Sumber:** Sisa usulan `[BL-007]` yang sengaja tidak dikerjakan di sana, dan `[BL-009]` yang menyiapkan fondasinya
- **Status:** Open
- **Prioritas:** Low (penahanannya sudah ada — ini lapisan **kesadaran**, bukan pertahanan)
- **Area Terdampak:**
  - `app/Models/PlatformAuditLog.php` — `login.failed` sudah bertanda `sensitive`, tinggal disaring
  - Belum ada kanal notifikasi apa pun di proyek (email/Telegram) untuk peristiwa operasional
- **Deskripsi:**
  Percobaan masuk yang gagal sudah dibatasi lajunya (`[BL-007]`) dan sudah tercatat rapi dengan derajat `sensitive` (`[BL-009]`), tapi **tak seorang pun diberi tahu** saat percobaan itu menumpuk. Artinya serangan yang berjalan pelan — di bawah ambang throttle, tersebar berjam-jam — akan terekam lengkap dan tetap tak terlihat sampai ada yang kebetulan membuka halaman jejak audit.
- **Usulan Perbaikan:**
  1. Perintah terjadwal yang menghitung `login.failed` per rentang waktu; bila melewati ambang, kirim notifikasi ke pemilik SaaS.
  2. Tentukan kanalnya dulu — proyek belum punya satu pun. Telegram sudah dipakai untuk self-order lewat n8n, jadi kemungkinan itu jalur termurah.
  3. Bedakan dua pola: banyak gagal pada **satu email** (penebakan kata sandi) versus banyak gagal pada **banyak email dari satu IP** (penebakan akun). Keduanya perlu ambang sendiri.

### [BL-006] Sistem Langganan Dua Jalur — Harga Normal (Privasi Penuh) & Subsidi UMKM (Berbasis Omset)
- **Ditemukan:** 2026-07-21
- **Sumber:** Permintaan pemilik SaaS — butuh sistem subscription normal **plus** skema bantu UMKM di mana harga mengikuti omset tenant, serta harga mengikuti jumlah pengguna/karyawan
- **Status:** Open — sudah di-plan di `docs/phases-2/PHASE-SAAS_Platform-Console-Subscription.md` (Tahap B–D), belum dieksekusi
- **Prioritas:** Medium (satu paket dengan BL-005; tidak bisa jalan tanpa fondasi platform console)
- **Area Terdampak:** (tabel langganannya semua baru; berkas di bawah sudah ada dan akan disentuh) *(rujukan baris diverifikasi 2026-07-22)*
  - `database/migrations/` — belum ada `plans`, `pricing_rules`, `subscriptions`, `invoices`, `tenant_consents`, `tenant_monthly_metrics`
  - `app/Models/Tenant.php` — perlu penanda jalur harga (`pricing_track`) & status consent
  - `app/Http/Controllers/Owner/StaffController.php:37` (`store`) — titik penegakan batas jumlah user/seat
  - `app/Models/Transaction.php` — sumber angka omset (hanya untuk tenant yang menyetujui); **tidak boleh** diakses untuk tenant jalur normal
- **Deskripsi:**
  Ada **dua jalur harga yang hidup berdampingan**, dan keduanya punya konsekuensi privasi yang berbeda:

  **Jalur A — Harga Normal (default).** Tenant bayar tarif publik. Pemilik SaaS **tidak bisa melihat omset** sama sekali. Ini menjaga janji privasi di `[BL-005]` tanpa kompromi.

  **Jalur B — Subsidi UMKM (opt-in).** Tenant setuju membuka data omsetnya, dan sebagai gantinya dapat harga yang menyesuaikan kemampuan bayar. Contoh dari pemilik: omset ±1 jt/bulan → harga 10k. Jadi omset bukan sekadar informasi, tapi **input penentu harga**.

  Selain omset, harga juga dipengaruhi **jumlah seat**: paket dasar hanya untuk 1 user; menambah karyawan di dalam satu tenant menaikkan harga. Semua aturan ini (bracket omset, tarif per bracket, harga per seat tambahan) harus **bisa diatur sendiri oleh pemilik SaaS lewat dashboard**, bukan hard-code di kode.

  Karena Jalur B membuka data yang di `[BL-005]` dinyatakan rahasia, wajib ada **halaman persetujuan khusus** yang benar-benar dibaca & disepakati tenant sebelum data omset mengalir.

  > **Ketegangan inti yang harus diselesaikan sadar-sadar:** `[BL-005]` berjanji "saya tidak melihat data bisnis Anda", sementara Jalur B justru butuh melihatnya. Ini **tidak otomatis bertentangan** — asal pembukaan data itu (1) sukarela, (2) terbatas pada angka yang benar-benar dibutuhkan, (3) tercatat persetujuannya, dan (4) bisa dicabut. Kalau keempat syarat itu tidak dipenuhi, janji privasi di BL-005 jadi kosong.
- **Usulan Perbaikan (garis besar — detail menyusul di dokumen plan tersendiri):**

  **1. Omset dihitung otomatis, dan angka persisnya terlihat oleh pemilik SaaS.** *(diputuskan 2026-07-21)*
  Omset dihitung sistem dari `transactions` — bukan dilaporkan sendiri oleh tenant — supaya tidak bisa diakali, dan angka persisnya (bukan sekadar bracket) tersedia di dashboard platform supaya penetapan harga bisa dibuktikan bila tenant menyanggah.

  > **Catatan atas alasannya — perlu diketahui saat menulis plan.** Sifat anti-akal-akalan itu datang dari **"dihitung otomatis"**, bukan dari **"ditampilkan persis"**. Begitu angka dihitung sistem, tenant sudah tidak bisa memanipulasinya — mau layar menampilkan `Rp 3.847.200` atau `Bracket B`, celahnya sama-sama tertutup. Jadi menampilkan angka persis **tidak menambah** perlindungan dari kecurangan.
  > Yang benar-benar ia tambahkan adalah **kemampuan membuktikan** saat ada sengketa — terutama untuk tenant yang omsetnya mepet batas bracket. Itu alasan yang sah, dan cukup untuk mendukung keputusan ini; hanya saja alasannya "pembuktian", bukan "anti-celah".
  >
  > **Penyempurnaan yang layak dipertimbangkan (belum diputuskan):** tampilkan **bracket** di halaman daftar tenant, dan sediakan angka persis lewat aksi "lihat rincian" yang tercatat di audit log. Pembuktian saat sengketa tetap bisa, tapi omset persis semua klien tidak jadi pemandangan sehari-hari di layar. Bedanya halus tapi nyata: yang pertama membuka data saat dibutuhkan, yang kedua membukanya terus-menerus.

  **2. Konsekuensi yang harus diterima.** Dengan keputusan di atas, untuk tenant jalur subsidi pemilik SaaS **memang melihat omset persis**. Ini bukan masalah selama tidak diklaim sebaliknya — maka: (a) halaman consent jalur subsidi wajib menyebut ini dengan kalimat lugas, tanpa eufemisme; (b) semua materi pemasaran/landing page tidak boleh memuat klaim umum seperti "kami tidak pernah melihat data bisnis Anda" tanpa pengecualian yang jelas. Tenant jalur normal tetap tertutup penuh, dan itulah yang boleh dinyatakan tanpa syarat.

  **Cakupan persisnya — final** *(diputuskan 2026-07-21)*: **hanya omset dalam angka rupiah**. Tidak ada margin/laba, tidak ada detail transaksi, tidak ada katalog produk.

  > **Latar keputusan:** sempat dipertimbangkan menambahkan keuntungan dalam bentuk persentase, tapi dibatalkan karena tidak mencapai tujuannya — selama omset sudah berupa angka persis, margin persen membuat laba rupiah bisa dihitung dengan satu perkalian (`Rp 3.847.200 × 22% = Rp 846.384`), jadi efeknya sama dengan membuka laba penuh. Karena untuk penetapan harga berbasis kemampuan bayar **omset saja sudah cukup**, margin hanya akan menambah data yang harus dijaga tanpa menambah dasar penetapan harga.
  > Konsekuensi praktis: `tenant_monthly_metrics` **tidak menyimpan kolom laba/HPP sama sekali**. Bukan disimpan lalu disembunyikan — memang tidak pernah dihitung.

  **3. Tabel ringkasan bulanan, bukan query langsung.** Buat `tenant_monthly_metrics` (tenant_id, periode, bracket, dihitung_pada) yang diisi scheduled job. Dashboard platform **hanya** membaca tabel ini — tidak pernah menyentuh `transactions`. Ini sekaligus menyelesaikan dua hal: performa (tidak SUM ratusan ribu baris tiap buka halaman) dan privasi (jalur akses ke data mentah cuma satu, mudah diaudit). Job hanya memproses tenant berjalur B.

  **4. Penanda jalur harga menegakkan batas secara struktural.** Kolom `tenants.pricing_track` (`normal` / `subsidized`). Job penghitung dan query dashboard **wajib** memfilter `pricing_track = 'subsidized'`. Tenant jalur normal tidak punya baris di `tenant_monthly_metrics` sama sekali — jadi datanya bukan "disembunyikan di UI", tapi memang tidak pernah ada.

  **5. Halaman persetujuan (consent) yang bermakna, bukan formalitas — dan terpisah per jalur.** *(diputuskan 2026-07-21: dua dokumen consent berbeda, subsidi & normal)*
  - **Dua dokumen terpisah**, bukan satu dokumen dengan pasal bersyarat. Consent jalur subsidi menyatakan bahwa omset dihitung otomatis dan **angka persisnya dilihat** pemilik SaaS untuk penetapan harga. Consent jalur normal menyatakan sebaliknya: data bisnis tidak dibuka sama sekali. Memisahkannya membuat masing-masing pendek, jujur, dan benar-benar terbaca — dokumen bersyarat justru mengaburkan hal terpenting.
  - Isi wajib: **apa persisnya** yang dibagikan, **seberapa sering**, **untuk apa** (penentuan harga), **berapa lama disimpan**, **cara mencabut**, dan **apa akibatnya kalau dicabut**.
  - Hindari dark pattern: checkbox **tidak boleh** ter-centang duluan; wajib scroll sampai bawah sebelum tombol setuju aktif; ringkasan bahasa manusia di atas, detail di bawah. Ini justru sejalan dengan niat Anda "agar betul-betul dibaca".
  - Simpan bukti di `tenant_consents`: `tenant_id`, `user_id` yang menyetujui, `consent_version`, `disetujui_pada`, `ip`. **Versi teks wajib disimpan** — teks consent pasti berubah, dan "dia dulu setuju" harus bisa dibuktikan merujuk teks yang mana.
  - Persetujuan harus datang dari **owner tenant**, bukan staf.

  **6. Pencabutan consent perlu kebijakan eksplisit.** Kalau tenant mencabut, apakah diskon langsung hilang, atau berlaku sampai akhir periode berjalan lalu kembali ke harga normal? Saya sarankan **berlaku sampai akhir periode** — pencabutan yang langsung menaikkan tagihan terasa seperti hukuman dan membuat orang takut mencabut (yang berarti consent-nya jadi semu). Apa pun keputusannya, **harus tertulis di halaman consent sejak awal**, bukan kejutan belakangan.

  **7. Aturan harga sebagai data, bukan kode.** `plans` (nama, tarif dasar, batas seat) + `pricing_rules` (bracket omset → tarif, harga per seat tambahan) + kemungkinan override harga khusus per tenant. Semua CRUD dari platform console.
  - **Perubahan tarif jangan diam-diam menagih ulang tenant lama.** Perlu `effective_date` / grandfathering: tenant yang sudah jalan tetap di tarif lamanya sampai periode berikutnya. Tanpa ini, satu kali edit angka bisa mengubah tagihan semua orang seketika.
  - Setiap perubahan tarif masuk audit log (`[BL-005]` poin 6).

  **8. Seat / batas pengguna — penegakan keras.** *(diputuskan 2026-07-21)*
  Menambah staf melebihi batas paket **ditolak** sampai tenant upgrade, dengan pesan yang menjelaskan batasnya dan menawarkan jalan upgrade. Alasannya: bagi UMKM, tagihan mendadak jauh lebih menyakitkan daripada tombol yang menolak dengan sopan.
  - Titik penegakan: `StaffController@store` (baris 37), sebelum user dibuat. Menghitung seat **tidak butuh data bisnis sama sekali** — cukup `count()` di tabel `users` — jadi bagian ini aman untuk semua jalur harga.
  - Interaksi dengan Phase RBAC: peran/role tidak dibatasi, yang dibatasi jumlah **user**. Wajib dipastikan tidak ada celah lewat jalur lain yang membuat user (seeder, API mobile, registrasi).
  - Masih perlu diputuskan: apa definisi seat — semua user, atau hanya yang aktif? Apakah menonaktifkan staf membebaskan seat? (Kalau ya, perlu jaga-jaga terhadap pola aktif–nonaktif bergantian untuk menghindari biaya.)

  **9. Trial 1 bulan gratis sebagai periode awal.** *(diputuskan 2026-07-21)*
  Semua tenant baru mulai dengan trial gratis 1 bulan. Di akhir trial, tenant memilih: **bayar tarif normal** untuk lanjut, atau **mengajukan diskon subsidi** dengan menyetujui pembukaan data omset.
  - **Sifat yang menguntungkan:** bulan trial sekaligus menjadi **periode pengukuran**. Saat tenant mengajukan subsidi di akhir trial, datanya sudah ada — masalah ayam-telur selesai dengan sendirinya, tanpa perlu tarif sementara atau perkiraan bracket.
  - **Tapi ini menimbulkan syarat pada consent:** omset yang dipakai berasal dari transaksi **sebelum** tenant menyetujui apa pun. Jadi dokumen consent subsidi harus menyatakan lugas bahwa perhitungan mencakup data periode trial yang sudah lewat, bukan hanya ke depan. Kalau tidak disebut, tenant berhak merasa datanya dipakai tanpa izin.
  - Masih perlu diputuskan: apa yang terjadi bila di akhir trial tenant **tidak memilih apa-apa**? (dibekukan, jadi read-only, atau tetap jalan sampai ditagih?) Dan berapa lama datanya disimpan sebelum boleh dihapus.
  - **Risiko penyalahgunaan trial:** dengan signup self-serve, orang bisa mendaftar berulang kali untuk trial gratis baru. Perlu mitigasi (verifikasi nomor telepon/email, penandaan perangkat, atau persetujuan manual untuk tenant kedua dengan identitas serupa).

  **10. Upgrade provisional dengan bukti transfer.** *(diputuskan 2026-07-21)*
  Saat tenant mentok batas seat dan ingin upgrade, dia mengunggah bukti transfer dan paketnya **langsung aktif** tanpa menunggu verifikasi manual. Pemilik SaaS memverifikasi belakangan.
  - Perlu diputuskan: **apa yang terjadi kalau bukti ditolak** (palsu, nominal kurang, atau salah unggah)? Saat itu tenant kemungkinan sudah menambahkan staf. Turunkan paketnya lalu staf mana yang dinonaktifkan? Saran: beri tenggang (mis. 3×24 jam) berisi pemberitahuan agar tenant memperbaiki, dan bila tetap gagal, kunci penambahan staf baru **tanpa** menonaktifkan staf yang sudah terlanjur dibuat — menonaktifkan akun yang sedang dipakai bekerja jauh lebih merusak kepercayaan daripada sekadar menahan penambahan berikutnya.
  - Perlu batas wajar agar tidak jadi celah: mis. hanya boleh satu upgrade provisional yang belum terverifikasi dalam satu waktu, dan tenant yang pernah gagal verifikasi tidak lagi dapat fasilitas ini.

  **11. Yang masih perlu diputuskan sebelum plan final:**
  - Apakah bracket ditampilkan di daftar dan angka persis hanya lewat aksi tercatat? — lihat poin 1.
  - Apa yang terjadi bila tenant tidak memilih apa pun di akhir trial? — lihat poin 9.
  - Mitigasi pendaftaran trial berulang — lihat poin 9.
  - Penanganan bukti transfer yang ditolak setelah upgrade provisional aktif — lihat poin 10.
  - Pencabutan consent: diskon langsung hilang atau berlaku sampai akhir periode? (saran: akhir periode)
  - Apakah tenant boleh pindah jalur normal ↔ subsidi kapan saja, atau ada periode minimum?
  - Definisi seat aktif — lihat poin 8.
  - Saat signup self-serve, apakah calon tenant memilih jalur harga di halaman itu juga, dan bagaimana mencegah semua orang memilih subsidi?

### [BL-005] Platform Console — Panel Pemilik SaaS (Privacy-Preserving)
- **Ditemukan:** 2026-07-21
- **Sumber:** Permintaan pemilik SaaS — butuh satu panel untuk melihat siapa saja tenant yang terdaftar, status langganan, dan riwayat pembayaran, **tanpa** bisa melihat data operasional klien
- **Status:** In Progress — **Tahap A selesai 2026-07-21**, **Tahap B selesai 2026-07-25** (lihat dua entri `[ADDITION]` terkait di `docs/CHANGELOG.md`). Sisa Tahap C–D di `docs/phases-2/PHASE-SAAS_Platform-Console-Subscription.md`
- **Prioritas:** Medium (belum menghambat operasional tenant, tapi jadi blocker begitu tenant berbayar pertama masuk — tanpa ini penagihan & pencatatan langganan manual)
- **Area Terdampak:** *(diperbarui 2026-07-22 — Tahap A sudah mendarat, daftar di bawah dipisah agar tidak menyesatkan)*

  **Sudah ada (Tahap A):**
  - `app/Models/PlatformUser.php` + guard `platform` — tingkat ketiga di atas tenant, sudah berdiri
  - `platform_users`, `platform_user_modules`, `platform_audit_logs`, `platform_password_reset_tokens`
  - `bootstrap/app.php` — alias `platform.can` & `platform.owner`, plus `redirectGuestsTo()` bersyarat
  - `routes/web.php` — grup `/platform` di luar middleware `tenant`
  - Panel: beranda, daftar tenant (read-only), jejak audit, manajemen akun

  **Belum ada (Tahap B–D):**
  - `app/Models/Tenant.php` — belum punya konsep plan/status langganan sama sekali
  - `database/migrations/` — belum ada `plans`, `pricing_rules`, `subscriptions`, `invoices`, `tenant_consents`, `tenant_monthly_metrics`
- **Deskripsi:**
  Saat ini aplikasi hanya mengenal dua tingkat: **tenant** dan **user di dalam tenant** (owner/cashier + RBAC modul dari Phase RBAC). Tidak ada tingkat ketiga: **pemilik platform/SaaS** yang berdiri di atas semua tenant. Akibatnya tidak ada cara melihat daftar tenant, status berbayar/gratis, atau riwayat pembayaran selain query database manual.

  Kebutuhan intinya bukan sekadar "admin bisa lihat semua", tapi justru sebaliknya: **panel yang sengaja dibatasi** supaya kerahasiaan data klien tetap terjaga. Pemilik SaaS perlu data *administratif & komersial*, bukan data *operasional*.

  **Yang BOLEH dilihat (data administratif/komersial):**
  1. Daftar tenant terdaftar — nama usaha, slug, tanggal daftar, status aktif/suspend
  2. Identitas owner tenant — nama & email owner (kontak penagihan)
  3. Daftar akun di bawah tenant — jumlah user, nama/email/peran, kapan terakhir login (untuk hitung seat & dukung support)
  4. Status langganan — Free / Paid, nama paket, tanggal mulai, tanggal jatuh tempo, status trial
  5. Biaya per tenant — tarif paket, diskon/harga khusus bila ada, MRR per tenant
  6. Riwayat pembayaran — daftar invoice, nominal, tanggal bayar, metode, status (lunas/pending/gagal)
  7. Metrik kesehatan/pemakaian **agregat** (angka saja, bukan isi) — mis. jumlah transaksi bulan ini, jumlah produk, kuota AI terpakai (`ai_usages`), tanggal aktivitas terakhir. Berguna untuk deteksi tenant yang mau churn & kapasitas server.
  8. Status fitur per tenant — flag yang aktif (nanti nyambung ke `PHASE-FEATURE-FLAGS`: `ai_enabled`, `self_order_enabled`, dst.)
  9. Log tiket/catatan internal per tenant (opsional) — catatan manual pemilik SaaS

  **Yang TIDAK BOLEH dilihat (data rahasia klien):**
  - Isi transaksi (item apa, harga berapa, siapa kasirnya), omzet & laba per tenant
    → **satu-satunya pengecualian:** tenant yang secara sukarela memilih jalur subsidi UMKM di `[BL-006]`. Untuk mereka, omset dihitung otomatis dan **angka persisnya terlihat** oleh pemilik SaaS *(diputuskan 2026-07-21)* — dinyatakan terang-terangan di dokumen consent jalur subsidi. **Laba/margin tetap tertutup** bahkan untuk jalur ini, begitu pula isi transaksi dan katalog. Tenant jalur harga normal tertutup penuh tanpa kecuali.
  - Katalog produk, harga jual, resep/modifier, data stok
  - Isi laporan, hasil AI Analysis, dan konteks bisnis yang dikirim ke AI
  - `ai_api_key` tenant (sudah `encrypted` + `$hidden` di `Tenant.php:15-22` — jangan sampai bocor lewat panel ini)
  - Password/kredensial user tenant

  > Catatan penting: poin 7 (metrik agregat) adalah **garis abu-abu**. "Jumlah transaksi" = metadata pemakaian (wajar untuk billing berbasis volume). "Total omzet" = data bisnis rahasia. Perlu diputuskan sadar di tahap plan, di mana garisnya ditarik — dan konsisten di semua tampilan.
- **Dugaan Penyebab / Kenapa belum ada:**
  Aplikasi dibangun dari sudut pandang satu tenant ke bawah. Semua middleware & query berbasis scope tenant, dan monetisasi belum pernah dimodelkan di database — jadi tidak ada tempat untuk menyimpan "tenant ini bayar berapa".
- **Usulan Perbaikan (garis besar — detail menyusul di dokumen plan tersendiri):**

  > **Status poin-poin di bawah** *(disegarkan 2026-07-22)*: poin **1, 2, 4, 5, 6, 8** sudah **dikerjakan** di Tahap A — dibiarkan tertulis sebagai catatan alasan, bukan pekerjaan tersisa. Poin **3, 7, 9, 10, 11** menunggu Tahap B–D. Poin **12** benar-benar masih terbuka.

  1. **Identitas platform admin terpisah, bukan menumpang `users.role`.** ✅ *Dikerjakan:* dipilih opsi (b) — tabel `platform_users` + guard `platform`. Ternyata pilihan ini punya sisi tajam yang tak terduga di tahap plan: `TenantScope` tidak aktif di job/command/seeder, jadi isolasi tidak boleh bersandar padanya (lihat poin 5).
  2. **Rute & layout terpisah** — mis. prefix `/platform` (atau subdomain), **di luar** middleware `tenant`, dengan layout `PlatformLayout.vue` sendiri agar tidak tercampur dengan `OwnerLayout`.
  3. **Model data langganan baru:** `plans` (nama, harga, kuota), `subscriptions` (tenant_id, plan_id, status, periode), `invoices` + `payments` (nominal, tanggal, metode, bukti). Mulai **manual/pencatatan dulu** (owner input pembayaran yang masuk), integrasi payment gateway belakangan.
  4. **Privasi ditegakkan di lapisan query, bukan hanya UI.** ✅ *Dikerjakan:* `App\Http\Resources\Platform\TenantResource` memakai **daftar putih** eksplisit (bukan `parent::toArray()`), sehingga kolom baru di tabel `tenants` tidak ikut bocor dengan sendirinya. Resource khusus platform hanya meng-expose field yang diizinkan — jangan pernah kirim model `Tenant`/`User` mentah ke Inertia. Menyembunyikan kolom di Vue saja tidak cukup: datanya tetap terkirim di payload dan bisa dibaca lewat DevTools.
  5. **Larang akses lintas-tenant secara struktural.** Controller platform tidak boleh menyentuh `Transaction`, `Product`, `AiAnalysis`, dll. Ide penegakan: test arsitektur (Pest `arch()`) yang gagal bila namespace `Platform` mengimpor model operasional. Metrik agregat cukup lewat query `count()`/`sum()` terbatas atau tabel ringkasan harian.
     **Pengecualian yang disengaja:** job penghitung metrik di `[BL-006]` poin 3 memang harus membaca `Transaction`. Itu boleh — asal job-nya berada **di luar** namespace `Platform` dan hasilnya hanya ditulis ke tabel ringkasan. Aturannya: yang dilarang menyentuh data operasional adalah **controller/halaman platform**, bukan job terjadwal. Dengan begitu tetap ada satu-satunya pintu ke data mentah, dan pintu itu mudah diaudit.
  6. **Audit log.** Setiap akses platform admin ke data tenant dicatat (`platform_audit_logs`). Ini yang membuat janji "saya tidak melihat data Anda" bisa dibuktikan, bukan sekadar klaim.
  7. **Impersonation: default TIDAK ADA.** Kalau nanti dibutuhkan untuk support, wajib berbasis izin eksplisit dari owner tenant + berjangka waktu + tercatat di audit log. Jangan dibuat di iterasi pertama.
  8. **RBAC platform: pakai ulang pola modul + centang dari Phase RBAC.** *(diputuskan 2026-07-21)*
     Panel platform punya RBAC sendiri, dengan pemilik SaaS sebagai user pertama (dan untuk sekarang satu-satunya). Polanya **sama persis** dengan yang sudah jalan di sisi tenant, jadi tidak perlu menemukan konsep baru:
     - katalog modul di config (sisi tenant: `config/rbac.php` → `modules`), satu entri = satu permission "boleh membuka modul ini";
     - owner membuat role lalu tinggal **mencentang** modul mana yang ikut (sisi tenant: `Owner/RoleController@store:47-52` — validasi `Rule::in(array_keys(config('rbac.modules')))` lalu `syncPermissions()`);
     - nav & halaman difilter dari permission yang sama.

     **Hasil sebenarnya** *(terjawab pasti 2026-07-21)*: katalog terpisah `config/platform-rbac.php` memang dipakai — tapi **spatie tidak bisa dipakai sama sekali** di sisi platform. Pivot `model_has_roles` menuntut `tenant_id` non-null karena kolom itu bagian dari primary key-nya, sedangkan akun platform tak punya tenant. Ini bukan "perlu diperiksa" melainkan mustahil secara struktur. Diganti tabel sendiri `platform_user_modules`; pola UI modul+centang tetap sama. Manajemen akunnya sendiri dijaga penanda `is_owner`, bukan modul grantable — kalau grantable, staf platform bisa mencentangkan `revenue_data` untuk dirinya sendiri.

  9. **v1 tidak bisa read-only.** *(diputuskan 2026-07-21)* Rencana awal mengusulkan panel read-only di v1 demi keamanan, tapi `[BL-006]` mensyaratkan pemilik SaaS mengatur sendiri aturan harga, bracket, dan tarif seat dari dashboard. Jadi v1 **harus** punya kemampuan tulis, minimal untuk `plans`/`pricing_rules`/pencatatan pembayaran. Konsekuensinya audit log (poin 6) naik dari "bagus untuk dimiliki" menjadi **wajib ada sejak v1**.

  10. **Pendaftaran self-serve.** *(diputuskan 2026-07-21)* Calon tenant mendaftar sendiri lewat halaman publik, bukan dibuat manual oleh pemilik SaaS. Konsekuensi untuk scope v1: butuh alur signup + verifikasi email + pemilihan jalur harga + halaman consent yang sesuai jalurnya, plus penanganan tenant terbengkalai (daftar lalu tidak pernah dipakai) supaya tidak mengotori daftar tenant dan metrik.

  11. **Pembayaran dicatat manual di v1.** *(diputuskan 2026-07-21)* Pemilik SaaS mencatat sendiri pembayaran masuk beserta buktinya; belum ada integrasi payment gateway. Perhatikan interaksinya dengan blokir seat keras — lihat `[BL-006]` poin 9(b).

  12. **Yang masih perlu diputuskan sebelum plan final:**
     - Garis metrik agregat untuk tenant **jalur normal** — jumlah transaksi, jumlah produk, kuota AI: boleh terlihat atau tidak? (Omset sudah terjawab di `[BL-006]`, tapi ini belum.)
     - Apakah pemilik SaaS boleh menangguhkan (suspend) tenant yang menunggak, dan apa yang terjadi pada data tenant selama masa suspend?
     - Dengan signup self-serve, siapa yang boleh mendaftar — terbuka untuk umum, atau perlu persetujuan Anda dulu sebelum tenant aktif?

### [BL-004] Pesan Validasi Masih Bahasa Inggris di UI Berbahasa Indonesia
- **Ditemukan:** 2026-07-21
- **Sumber:** Validasi live blackbox fitur RBAC — form "Tambah Staf" (`/owner/staff`), saat sengaja mengirim email duplikat + password < 8 karakter
- **Status:** Open
- **Prioritas:** Low (kosmetik/UX, tidak mengubah data atau keamanan — validasi **berfungsi benar**, hanya bahasanya)
- **Area Terdampak:**
  - Seluruh aplikasi (bukan hanya RBAC) — tidak ada folder `lang/`, `config/app.php:81` → `'locale' => env('APP_LOCALE', 'en')`
  - Teramati langsung di `resources/js/Pages/Owner/Staff/Index.vue` (modal tambah staf)
  - **Bertambah 2026-07-21:** form login panel platform (`Platform/AuthController@login`) ikut terdampak — pesan gagal autentikasi sudah bahasa Indonesia, tapi pesan validasi field masih bawaan Laravel. Tiap permukaan baru akan menambah daftar ini selama locale global belum dibereskan.
- **Deskripsi:**
  Pesan validasi yang tampil ke pengguna memakai teks bawaan Laravel dalam bahasa Inggris, padahal seluruh UI berbahasa Indonesia. Contoh nyata yang teramati: `"The email has already been taken."` dan `"The password field must be at least 8 characters."`. Inkonsistensi ini muncul di **semua form** aplikasi, bukan hanya form staf/role — jadi ini **bukan regresi** dari Phase RBAC, melainkan hutang lokalisasi yang sudah ada sejak awal dan baru terdokumentasi sekarang.
- **Dugaan Penyebab:**
  Proyek tidak pernah mem-publish file bahasa (`php artisan lang:publish`) dan `APP_LOCALE` tetap `en`, sehingga Laravel memakai pesan validasi default bahasa Inggris.
- **Usulan Perbaikan:**
  1. `php artisan lang:publish` lalu buat `lang/id/validation.php` berisi terjemahan Indonesia, dan set `APP_LOCALE=id` (atau `app.fallback_locale` sesuai kebutuhan).
  2. Alternatif ringan bila tak ingin mengubah locale global: tambahkan `messages()` / `attributes()` kustom di masing-masing FormRequest/`validate()` untuk form yang menghadap pengguna.
  3. Sertakan `attributes()` agar nama field ikut diterjemahkan (`email` → "Email", `password` → "Kata Sandi"), bukan hanya kalimat pesannya.

### [BL-003] Mobile API — Permissions RBAC di Auth & Gating Endpoint
- **Ditemukan:** 2026-07-21
- **Sumber:** Keputusan C plan RBAC (`docs/phases-2/PHASE-RBAC_Module-Access-Control.md`, Bagian 8)
- **Status:** Open (ditunda by design)
- **Prioritas:** Low (ditunda sampai fitur utama RBAC web selesai penuh)
- **Area Terdampak:**
  - `app/Http/Controllers/Api/MobileAuthController.php` — respons auth belum menyertakan `permissions`
  - Endpoint mobile per modul — belum digerbang `permission:` versi API (JSON 403)
- **Deskripsi:** Fitur ini disetujui **tetap dibuat**, tetapi sengaja ditunda sampai bagian utama RBAC (web: Bagian 2–9 di plan RBAC) selesai penuh. Setelah itu: sertakan array `permissions` di payload auth `MobileAuthController` (owner → `['*']`, staf → daftar modul), dan gerbang endpoint mobile per modul.
- **Usulan Perbaikan:** Pakai pola `permission:` versi API yang mengembalikan JSON 403 — mengikuti pola `feature.api` di `PHASE-FEATURE-FLAGS`. Reuse katalog permission yang sama dengan web (Bagian 4 plan RBAC) agar satu sumber kebenaran.

---

## Riwayat Selesai

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
