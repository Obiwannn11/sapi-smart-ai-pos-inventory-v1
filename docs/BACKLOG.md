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

### [BL-004] Pesan Validasi Masih Bahasa Inggris di UI Berbahasa Indonesia
- **Ditemukan:** 2026-07-21
- **Sumber:** Validasi live blackbox fitur RBAC — form "Tambah Staf" (`/owner/staff`), saat sengaja mengirim email duplikat + password < 8 karakter
- **Status:** Open
- **Prioritas:** Low (kosmetik/UX, tidak mengubah data atau keamanan — validasi **berfungsi benar**, hanya bahasanya)
- **Area Terdampak:**
  - Seluruh aplikasi (bukan hanya RBAC) — tidak ada folder `lang/`, `config/app.php:81` → `'locale' => env('APP_LOCALE', 'en')`
  - Teramati langsung di `resources/js/Pages/Owner/Staff/Index.vue` (modal tambah staf)
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
