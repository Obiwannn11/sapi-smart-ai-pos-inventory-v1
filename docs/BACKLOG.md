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
- **Sumber:** Ditunda sadar-sadar saat mengerjakan Tahap B — lihat `[BL-005]` di Riwayat Selesai
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

### [BL-005] Platform Console — Panel Pemilik SaaS (Privacy-Preserving)
- **Ditemukan:** 2026-07-21
- **Sumber:** Permintaan pemilik SaaS — satu panel untuk melihat tenant, langganan, dan pembayaran **tanpa** melihat data operasional klien
- **Status:** Selesai (Tahap A 2026-07-21/22, Tahap B–D 2026-07-25) — lihat dua entri `[ADDITION] PHASE SAAS` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Empat tahap. **A:** guard `platform` terpisah + RBAC modul sendiri (spatie terbukti mustahil dipakai di sini) + daftar tenak read-only + jejak audit. **B:** model langganan, trial, masa tenggang hanya-baca, batas seat, consent jalur normal, panel pembayaran manual. **C:** jalur subsidi berikut job penghitung omzet, consent sendiri, dan tampilan bracket. **D:** aturan harga sebagai data dengan grandfathering.
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
