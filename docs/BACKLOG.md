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

> **Catatan pemilik 2026-08-01** — `[BL-043]`–`[BL-047]` berasal dari catatan menjalankan panel platform, dan sudah **diverifikasi terhadap kode**. Empat dari lima bukan "belum ada sama sekali" melainkan **setengah jadi**: gerbang hanya-baca sudah menegakkan dirinya tapi tak terlihat (`[BL-045]`), CRUD paket sudah ada tapi tak ada yang membacanya (`[BL-046]`), kuota AI sudah ada tapi tinggal di `.env` (`[BL-047]`), dan siklus hidup langganan sudah berpindah keadaan tapi tak pernah menerbitkan tagihan (`[BL-044]`). Hanya `[BL-043]` yang murni cacat. **Koreksi terhadap catatan 2026-07-31 (kedua) di atas:** butir (1) di sana — "pemisahan login platform vs login tenant sudah utuh" — benar untuk *guard, broker, dan pengalihan tamu*, tapi **tidak** untuk pengalihan **setelah** berhasil masuk; lihat `[BL-043]`.
>
> Urutan yang disarankan: ~~`[BL-043]` (cacat, berdiri sendiri, kecil)~~ → ~~`[BL-030]` (mumpung `invoices` masih kosong)~~ → `[BL-041]`(a) menetapkan angka → ~~`[BL-044]`~~ (butir b 2026-08-06, butir c 2026-08-15 — **tertutup**) → ~~`[BL-046]`~~ → ~~`[BL-047]`~~ (selesai 2026-08-13) → ~~`[BL-045]`~~. Yang dicoret selesai 2026-08-05 s.d. 2026-08-15. **Seluruh jalur ini kini tertutup:** `[BL-041]`(a) terjawab 2026-08-07 dan `[BL-048]` ditutup `[BL-055]` pada 2026-08-10.

> **Keputusan pemilik 2026-08-01 (struktur tarif)** — Rp 100k ditetapkan sebagai **puncak bracket Harga Adaptif**, sejajar dengan harga Premium; tenant beromset tinggi tidak lagi berhak atas Adaptif dan hanya bisa Premium; kelayakan Adaptif ditentukan pemilik SaaS. Rinciannya di blok "Keputusan pemilik" pada `[BL-041]`. Dampaknya menyebar ke tiga entri: `[BL-044]` (pertanyaan tarifnya terjawab), `[BL-046]` (Premium dapat pembeda kedua, dan jalur pindah paket berubah dari nyaman jadi wajib), dan satu entri baru **`[BL-048]`** — pagar kelayakan jalur Adaptif, yang ternyata **menabrak gerbang privasi**: menilai kelayakan masuk butuh data omset yang baru boleh dikumpulkan setelah masuk. Baca `[BL-048]` sebelum menyentuh `canSwitchTrack()`.
>
> **Keputusan pemilik 2026-08-01 (kedua)** — batas Adaptif diturunkan dari tangga harga Premium, bukan disetel sebagai angka tersendiri; Premium kelak bisa tiga tier dengan puncak Adaptif mendarat di tengahnya. Dua akibat yang menghemat pekerjaan: batas itu **sudah bisa dinyatakan tanpa kolom atau migrasi baru** — cukup satu baris syarat `lt` pada bracket D yang hari ini tidak punya batas atas (`[BL-048]`(b)) — dan permintaan soal harga seat tambahan (**tidak nullable, wajib ditulis walau 0**) ternyata **sudah dipenuhi kode hari ini**, hanya prefill formulirnya yang belum ada (`[BL-046]`).

> **Keputusan pemilik 2026-08-07 (struktur harga final) — angkanya SUDAH TERPASANG.** Ini keputusan yang menutup `[BL-041]`(a) dan mengubah bentuk `[BL-048]` dari pagar kelayakan menjadi alur pengajuan. Ringkasnya:
>
> | | |
> |---|---|
> | Paket | `free` (2 seat, 5 AI, Rp 0) · `paid-1` (3 seat, 15 AI, Rp 100k) · `paid-2` (5 seat, 30 AI, Rp 150k) · `paid-3` (10 seat, 60 AI, Rp 200k) |
> | Masa gratis | **2 bulan**, dihitung `addMonthsNoOverflow`, jangkar tagih = **tanggal daftar** |
> | Akhir masa gratis | dipaksa pindah ke `paid-1` |
> | Harga Adaptif | `paid-1` yang **didiskon**: A 10k (90%) · B 25k (75%) · C 50k (50%) · **D 75k (25%)**; ≥ Rp 50 jt tidak layak |
> | Kelayakan Adaptif | **diajukan tenant + consent, dinilai otomatis** — bukan diberikan manual |
> | Tenggat | 30 hari bertingkat: notif halus 1–14, intensif 15–19, tulis dicabut 20–30 |
> | Harga khusus | dropdown paket = mengunci ke depan · input manual = **satu bulan saja** + alasan wajib |
>
> **Tiga hal yang ditemukan saat menyusunnya, dan ketiganya mengubah keputusan sebelumnya — bukan sekadar melengkapinya:**
>
> 1. **Bracket D dulu memberi diskon 0%.** Karena Adaptif adalah `paid-1` yang didiskon, dan bracket D berharga sama persis dengan `paid-1` (Rp 100k), tenant di rentang Rp 15–50 jt menyerahkan data penjualannya dan menerima **nol rupiah** keringanan. Tangga Adaptif sebenarnya berakhir di Rp 15 juta, bukan Rp 50 juta. Karena itu D turun ke Rp 75.000 dan tangganya jadi monoton 90/75/50/25/0.
> 2. **Alur pengajuan membubarkan kebuntuan privasi `[BL-048]`.** Consent diberikan **saat mengajukan**, jadi data omset dikumpulkan setelah tenant memintanya — bukan sebelum. Lingkaran "butuh data yang baru boleh dikumpulkan setelah masuk" tidak pernah terbentuk, dan penilaian otomatis penuh jadi sah, bukan kompromi. Usulan lama (kelayakan sebagai pemberian manual) **dibatalkan**.
> 3. **Tenggat hari ini sudah mematikan kasir sejak hari pertama.** `EnsureSubscriptionActive` memblokir seluruh permintaan non-GET begitu tenant masuk tenggat. Prinsip lama di `config/subscription.php` ("tenggat mencabut kemampuan MENAMBAH data") ternyata berarti toko tidak bisa berjualan — dan toko yang tidak bisa berjualan tidak punya uang untuk membayar. Prinsipnya dicabut dan diganti tangga tiga tahap; ~~penegaknya belum ada (`[BL-054]`)~~ — penegaknya mendarat 2026-08-08.
>
> **Akibat terbesar yang harus disadari:** begitu pengajuan Adaptif otomatis dan bisa dilakukan sendiri, **tangga bracket adalah daftar harga yang sesungguhnya** — Rp 100.000 tinggal harga daftar bagi yang menolak membuka omset. ARPU realistis ada di Rp 25.000–50.000. Setel bracket seolah-olah itu daftar harganya, karena memang akan jadi itu.
>
> Yang sudah terpasang 2026-08-07: paket, bracket, `trial_months`, jangkar tanggal daftar, dan tangga tenggat di config. Yang **belum** dan jadi entri baru: ~~`[BL-052]` perpindahan otomatis akhir masa gratis~~ (selesai 2026-08-08), ~~`[BL-053]` seat & kuota AI jadi komponen bulanan~~ (bagian seat selesai 2026-08-08; kuota AI jadi `[BL-069]`), ~~`[BL-054]` penegak tenggat bertingkat~~ (selesai 2026-08-08), ~~`[BL-055]` alur pengajuan Adaptif~~ (selesai 2026-08-10; butir (g) jadi `[BL-073]`), `[BL-056]` pengajuan berlaku bulan mana, `[BL-057]` harga khusus per tenant.

> **Permintaan pemilik 2026-08-07 (pembayaran)** — halaman bayar "ala-ala" untuk development dan peragaan, dengan Sumopod payment gateway menyusul belakangan. Dipecah jadi dua: `[BL-059]` **(selesai hari yang sama)**, dan `[BL-060]` yang menunggu dokumentasi serta kredensial sandbox. Satu hal yang menentukan seluruh bentuk `[BL-059]`: gateway tiruannya **melunasi lewat webhook**, bukan dengan memanggil `settle()` langsung — supaya yang diperagakan besok adalah jalur yang sama dengan yang akan dipakai produksi, dan memasang Sumopod tinggal menukar satu driver. Catatan kecil yang mudah terlewat: nama "Sumopod" sudah dipakai di aplikasi ini untuk **gateway AI**, bukan pembayaran; kredensialnya jangan dipakai ulang.

> **Catatan pemilik 2026-08-08 (pasca-peragaan & laporan progres)** — tujuh saran dari sesi peragaan, sudah **diverifikasi terhadap kode** sebelum jadi entri. Yang perlu diketahui sebelum membacanya satu per satu:
>
> - **Satu saran ternyata sudah punya entri.** "Perbaiki include seats atau tambahan kuota akun" pada sisi **mekanika tagihannya** adalah `[BL-053]` (seat & kuota jadi komponen bulanan, plus jalan melepas seat) dan `[BL-049]` (tagihan Rp 0 saat menambah pengguna) — dan **tidak diduplikasi** di sini. Sisi seat-nya selesai 2026-08-08; sisa kuota AI-nya kini `[BL-069]`. Yang benar-benar belum tercatat hanyalah sisi **keterlihatannya**: berapa seat dan berapa kuota AI yang didapat sebuah paket tidak muncul di satu pun permukaan sebelum orang mendaftar. Itu yang jadi `[BL-067]`.
> - **Satu saran tidak punya kode sama sekali untuk diperbaiki.** PPN/pajak nol kata di seluruh `app/`, `config/`, dan migrasi; `transactions` hanya punya `total_amount`, dan struk hanya menampilkan Subtotal → TOTAL. Jadi `[BL-065]` bukan "perbaiki mode pajak", melainkan "belum ada pajaknya" — dan bentuk yang diminta (include vs dibebankan ke pelanggan) menentukan **kolom mana yang lahir**, bukan sekadar cara menampilkannya. Putuskan bentuknya sebelum ada baris kode.
> - **"Dynamic pricing" di saran ini berarti mesin harga langganan SaaS**, bukan diskon barang mendekati kedaluwarsa (`[BL-018]`). Keduanya sama-sama pernah disebut "harga dinamis" di proyek ini; `[BL-066]` memakai arti yang pertama, sesuai `docs/SAPI-Pitch-Fitur-Unggulan_v1.0.md` bagian 3.
> - **Saran cabang sengaja dipatok terakhir** atas permintaan pemilik sendiri ("pastiin yang lain berhasil semua dulu"). `[BL-068]` mencatat kenapa syarat itu tepat: tidak ada satu pun konsep cabang di kode hari ini, dan menambahkannya menyentuh hampir setiap tabel operasional.
>
> Urutan yang disarankan, termurah dulu: `[BL-067]` (keterlihatan seat/kuota) → `[BL-066]` (penjelasan Dynamic Pricing di landing) → `[BL-065]` (PPN, menunggu keputusan bentuk) → `[BL-068]` (cabang, paling akhir). Tiga yang paling murah — `[BL-062]` (kuota AI), `[BL-063]` (rekap bulanan), dan `[BL-064]` (grafik garis) — selesai 2026-08-13; ketiganya memang tidak menyentuh uang sama sekali.

> **Catatan pemilik 2026-08-13 (penyisiran backlog)** — pemilik menanyakan empat fitur yang dikiranya mungkin terlewat dicatat; seluruhnya sudah **diperiksa terhadap kode**, dan hasilnya dua sudah tercatat, dua memang terlewat. **Sudah ada:** offline + printer Bluetooth + lapisan native adalah `[BL-016]` (Capacitor ada di sana sebagai opsi 2, dan lingkupnya sudah dikunci Android saja), sedangkan upsell dari sinyal stok sudah **selesai** lewat `[BL-017]`/`[BL-025]` — sisanya hanya bundling berdiskon yang menunggu `[BL-018]`. **Benar-benar terlewat:** upsell yang **ditargetkan manual oleh owner** — tiga strategi yang ada semuanya menurunkan saran dari data dan tidak ada satu pun tempat bagi owner menuliskan targetnya sendiri — jadi `[BL-074]`; dan **foto bukti pembayaran non-tunai**, yang nol kode (`transaction_payments` hanya punya `reference_code`), jadi `[BL-075]`. Satu hal yang mudah menyesatkan dan sudah ditulis di dalam `[BL-075]`: `invoices.proof_path` yang sudah ada itu bukti tenant membayar langganan SaaS, **bukan** bukti pelanggan membayar di kasir. `[BL-076]` lahir dari keputusan pemilik di hari yang sama — penyimpanan `[BL-075]` untuk sementara di disk server, dan pemindahannya ke object storage dicatat terpisah supaya "sementara" tidak diam-diam jadi permanen.

> **Catatan pemilik 2026-08-21 (sesi tanya-jawab konsep)** — `[BL-084]`–`[BL-088]` berasal dari satu daftar pertanyaan pemilik dan sudah **diverifikasi terhadap kode** sebelum jadi entri; tiap entri menyebut berkas dan barisnya. Dua hal dari daftar itu sengaja TIDAK jadi entri karena ternyata sudah benar: (1) **transaksi void** sudah utuh — stok kembali, papan dapur dibersihkan dua lapis, omzet menyaring `completed` saja, dan jumlah void dilaporkan terpisah di rekap harian, bulanan, dan CSV; satu-satunya batas yang tersisa ("hanya transaksi hari ini", `app/Services/TransactionService.php:469`) ditulis sendiri sebagai MVP dan belum pernah dikeluhkan, jadi dibiarkan sampai ada yang menagihnya. (2) **pendaftaran toko baru** sudah punya seluruh alurnya — `AuthController::register()`, preset fitur per jenis usaha (`[BL-034]`), masa coba dibuka di transaksi yang sama sehingga tenant tak pernah lahir tanpa langganan, penandaan IP oleh `SignupGuardService`, lalu verifikasi surel. Yang belum ada adalah **bergabung ke toko yang sudah ada** (staf hari ini ditambahkan owner dari modul Staf, tidak mendaftar sendiri) dan **multi-cabang** — yang kedua sudah tercatat sebagai `[BL-068]`.
>
> **Satu koreksi terhadap dugaan pemilik, dan ia menentukan bentuk tiga entri kas di bawah:** yang berumur 24 jam lalu tersapu otomatis adalah **tagihan terbuka** (`[BL-031]`, `open-bills:expire`, tiap jam) — **bukan sesi kas**. Sesi kas hari ini tidak punya umur sama sekali, dan tidak ada satu pun tugas terjadwal yang menyentuh `cash_drawers`. Jadi `[BL-088]` adalah fitur baru, bukan perbaikan sesuatu yang sudah berjalan.
>
> Urutan yang disarankan, termurah dulu: `[BL-084]` → `[BL-085]` (keduanya satu berkas, tanpa skema) → `[BL-086]` (UI + pemecahan rute) → `[BL-088]` → `[BL-087]`. Dua yang terakhir menambah skema, dan `[BL-087]` mengubah rumus `expected_amount` — ia harus mendarat **sesudah** `[BL-086]`, kalau tidak layar tutup kas dibongkar dua kali.

### [BL-093] Pencatatan Uang Keluar Laci Belum Bisa Dilampiri Foto Struk
- **Ditemukan:** 2026-08-21 (dipecah dari `[BL-087]` saat fiturnya mendarat)
- **Sumber:** Saran di dalam pembahasan `[BL-087]` — foto struk sebagai pelengkap alasan tertulis, disetujui pemilik bersama bentuk fiturnya
- **Status:** Open — **terhalang keputusan retensi, bukan kodenya**
- **Prioritas:** Low — alasan tertulis sudah wajib dan sudah menutup sebagian besar gunanya; foto mengubah "katanya beli galon" jadi bisa diperiksa, dan itu berguna tapi bukan penentu
- **Area Terdampak:**
  - `app/Services/PaymentProofService.php` — pola unggah-lalu-klaim yang bisa ditiru (`storePending()` / `claim()`), tapi seluruh `pathFor()`-nya terikat `TransactionPayment`
  - `app/Services/ProofFileService.php` — penyimpanan, thumbnail, dan penghapusannya sudah umum dan bisa dipakai apa adanya
  - `database/migrations/..._create_cash_drawer_movements_table.php` — belum punya kolom `proof_path`
- **Deskripsi:** `[BL-087]` mewajibkan alasan tertulis, dan itu yang membedakan pencatatan dari uang yang hilang begitu saja. Yang belum ada: bukti yang bisa diperiksa. Untuk pengeluaran yang punya struk — galon, belanja bahan, parkir — foto mengubah keterangan jadi sesuatu yang bisa dicocokkan.
- **Kenapa tidak ikut mendarat bersama `[BL-087]`:** jalur unggahnya menuntut direktori sendiri, mekanisme klaim berkas, dan **kebijakan retensi**. Yang terakhir belum pernah diputuskan untuk foto bukti mana pun — `[BL-075]` mendarat tanpa retensi dan `[BL-076]` masih memegang persoalan penyimpanannya. Menyelipkan satu jalur unggah lagi berarti menambah satu tumpukan berkas yang tak seorang pun tahu kapan boleh dihapus.
- **Usulan Perbaikan:** kolom `proof_path` nullable pada `cash_drawer_movements`, tombol unggah opsional di modal pencatatan, dan pratinjaunya di daftar persetujuan pemilik — **setelah** retensi berkas bukti diputuskan di `[BL-076]`.

### [BL-094] `eager: true` Menyatukan 56 Halaman Vue Jadi Satu Bundel untuk Pengguna yang Sudah Masuk
- **Ditemukan:** 2026-08-24 (butir (c) `[BL-091]`, sengaja dipisahkan saat entri itu dikerjakan)
- **Sumber:** Butir (c) `[BL-091]` — "layak ditinjau terpisah, dan bukan bagian dari entri ini"
- **Status:** Open
- **Prioritas:** Low — sesudah `[BL-091]`, tak seorang pun yang belum punya akun menanggungnya lagi. Yang tersisa hanya ongkos muat pertama bagi pengguna yang memang akan memakai aplikasinya
- **Area Terdampak:**
  - `resources/js/app.js:8` — `import.meta.glob('./Pages/**/*.vue', { eager: true })`
  - `vite.config.js` — tidak ada pemecahan chunk yang disetel sendiri hari ini
  - `public/build/assets/app-*.js` — **1.117 KB** dalam satu berkas
- **Deskripsi:**
  `eager: true` membuat Vite mengompilasi seluruh 56 halaman ke dalam bundel entry alih-alih memecahnya jadi chunk per halaman. Kasir yang seharian hanya membuka satu layar tetap mengunduh panel platform, laporan, dan langganan pada muat pertama.

  Menggantinya dengan glob malas (`{ eager: false }` + `resolvePageComponent`) memecah bundelnya per halaman — keuntungan nyata, tapi ia mengubah **cara setiap halaman dimuat**: resolusi komponen jadi asinkron, dan tiap perpindahan halaman menambah satu permintaan jaringan yang sebelumnya tidak ada.
- **Kenapa dipisah dari `[BL-091]`:** yang di sana penghapusan tanpa risiko — satu argumen `@vite` dicabut, tidak ada perilaku yang berubah. Yang ini perubahan perilaku pemuatan pada **setiap** halaman aplikasi, dan pantas diuji sendiri alih-alih menumpang commit yang tidak menanggung risikonya.
- **Usulan Perbaikan:**
  **(a)** Pakai `resolvePageComponent` dari `laravel-vite-plugin/inertia-helpers` dengan glob malas, bukan merakit `import()` sendiri.
  **(b)** Ukur sesudahnya, jangan diasumsikan: catat ukuran entry dan jumlah chunk sebelum/sesudah. Pemecahan chunk yang menghasilkan 56 permintaan kecil pada sambungan lambat bisa lebih buruk daripada satu bundel besar yang sudah di-cache.
  **(c)** Perhatikan bilah kemajuan `[BL-037]` (`delay: 500`): resolusi asinkron menambah jeda yang sebelumnya nol, dan alasan bilah itu "jarang terlihat" ditulis dari keadaan yang sekarang akan berubah.

---

### [BL-081] Omzet Penentu Tarif Masih Terikat Bulan Kalender, Bukan Jendela yang Selalu Penuh
- **Ditemukan:** 2026-08-20 (saat memilih opsi butir (b) `[BL-080]`; pemilik sempat mencondong ke sini sebelum ongkos persetujuan ulangnya terlihat)
- **Sumber:** `[BL-080]` butir (b) opsi **(iv-b)**, sengaja tidak diambil dan dipisah supaya tidak hilang bersama entri yang ditutup
- **Status:** Open — **ditunda dengan sengaja**, bukan tertinggal. `[BL-080]` opsi (i) sudah menutup sisi uangnya; entri ini hanya soal masa siap tenant
- **Prioritas:** Low — tidak ada angka yang salah karenanya. Yang ditukar hanya kenyamanan, dan hanya bagi tenant Adaptif berjangkar tanggal 1–7
- **Area Terdampak:**
  - `database/migrations/2026_07_24_195639_create_tenant_monthly_metrics_table.php` — `period` `char(7)` `YYYY-MM`, unik `(tenant_id, period)`
  - `resources/consents/subsidized-v1.md` — dokumen yang SUDAH ditandatangani tenant
  - `app/Jobs/ComputeTenantMonthlyRevenue.php`, `app/Console/Commands/ComputeTenantRevenue.php` (`--period YYYY-MM`), `app/Console/Commands/PruneTenantMetrics.php`
  - `app/Services/Pricing/MonthlyMetricResolver.php` — `requiredPeriodFor()`, dipakai dua dimensi
  - `app/Http/Controllers/Platform/RevenueController.php` + `resources/js/Pages/Platform/Tenants/Show.vue`
  - `app/Services/Pricing/SubsidyEstimator.php` — perkiraan yang dilihat tenant, dihitung dari bulan kalender lalu
  - `resources/js/Pages/Billing/Adaptive.vue`, `resources/js/Pages/Billing/Show.vue`, `config/pricing-dimensions.php`, `README.md`
- **Deskripsi:**
  Tarif Adaptif dihitung dari omzet **bulan kalender** yang baru tutup. Karena siklus tagih tiap tenant berputar dari tanggal daftarnya, tenant berjangkar tanggal 1–7 sampai di hari penerbitan sebelum bulan itu tutup. `[BL-080]` opsi (i) menjawabnya dengan **menunggu**: tagihannya terbit belakangan, dengan masa siap 0–6 hari alih-alih 7.

  Opsi (iv-b) menjawabnya dengan cara lain: ambil omzet **30 hari terakhir per tanggal terbit**. Datanya selalu ada, selalu segar, dan masa siap 7 hari kembali utuh untuk semua tenant. Panjang jendelanya seragam, jadi perbandingan bracket antar-tenant tetap setara — keunggulan yang tidak dimiliki varian "ikut periode langganan", yang memberi tiap tenant jendela sepanjang berbeda.
- **Kenapa TIDAK dikerjakan sekarang (keputusan pemilik 2026-08-20):**
  Ongkosnya bukan di kode, melainkan di **persetujuan**. `resources/consents/subsidized-v1.md` yang sudah ditandatangani tenant menyatakan dengan kalimatnya sendiri: "Hanya dua angka **per bulan**", dan "**Sekali sebulan, setelah bulan berjalan tutup**, sistem menjumlahkan transaksi selesai Anda". Jendela 30 hari berjalan bukan itu. `config/subscription.php` sudah memperingatkan bahwa mengubah teks yang sudah disetujui membuat catatan persetujuannya menunjuk ke kalimat yang tak pernah mereka baca — jadi ini **versi consent baru dan persetujuan ulang dari setiap tenant Adaptif**.

  Meminta tanda tangan ulang atas pengumpulan data omzet demi memulihkan 1–6 hari pemberitahuan bukan pertukaran yang sepadan, apalagi persetujuan ulang punya risikonya sendiri: sebagian tenant tidak akan menyetujuinya lagi, dan mereka keluar dari keringanan yang justru dibuat untuk mereka.
- **Kapan ini layak dibuka lagi:**
  1. **Bila keluhan masa siap benar-benar muncul** dari tenant berjangkar awal bulan — bukan diperkirakan, melainkan terjadi. Saat itu alasan permintaan consent v2 bisa dijelaskan dengan jujur, dan itu jauh lebih mudah diterima daripada permintaan tanpa sebab yang terlihat.
  2. **Bila dokumen consent akan dinaikkan versinya untuk alasan lain.** Ongkos terbesar entri ini adalah persetujuan ulangnya; menumpangkannya pada versi yang memang sudah harus naik membuat ongkos itu nyaris hilang.
- **Usulan Perbaikan bila dikerjakan:**
  **(a)** Naikkan versi consent dan tuliskan jendelanya apa adanya — "30 hari terakhir sebelum tagihan Anda terbit", bukan "bulan lalu". Kalimat yang lebih rapi daripada perilakunya adalah kalimat yang menyesatkan pembacanya sendiri.
  **(b)** `SubsidyEstimator` **wajib** ikut berubah di commit yang sama. Ia menghitung perkiraan yang dilihat tenant langsung dari bulan kalender lalu; tertinggal sedikit saja, tenant melihat satu angka di layar lalu ditagih dari angka lain.
  **(c)** Pertahankan `MonthlyMetricResolver::requiredPeriodFor()` sebagai satu-satunya tempat jendelanya didefinisikan. `MetricReadiness` membacanya dari sana, dan dua definisi yang berselisih hanya akan terlihat pada tagihan tenant.
  **(d)** Penundaan `[BL-080]` opsi (i) **tidak dibuang** meski jadi hampir tak pernah aktif: ia tetap satu-satunya yang menahan tagihan saat penghitungnya gagal atau consent dicabut.

---

### [BL-079] `business_type` Masih Boleh Kosong Padahal Ia Sudah Jadi Dimensi Harga
- **Ditemukan:** 2026-08-15 (butir (d) `[BL-071]`, sengaja dipisahkan)
- **Sumber:** Butir (d) entri `[BL-071]` — "tinjau juga apakah `business_type` masih layak `nullable` sekarang setelah ia jadi dimensi harga sungguhan"
- **Status:** Open — **sebagian dijawab 2026-08-24 (opsi iii).** Tebakannya sudah dicabut; pertanyaan "wajibkan atau tidak" sengaja masih ditahan
- **Prioritas:** Low — tidak ada tagihan yang salah karenanya: yang kosong jatuh ke bawaan netral, bukan ke `null`, dan pemiliknya bisa memperbaikinya sendiri dari Pengaturan
- **Area Terdampak:**
  - `app/Http/Controllers/Auth/AuthController.php` — `register()`: `business_type` divalidasi `nullable`, dan yang kosong diisi `Tenant::BUSINESS_TYPE_DEFAULT`
  - `resources/js/Pages/Auth/Register.vue` — labelnya berbunyi "(opsional)", dengan pilihan "Belum ditentukan"
  - `config/pricing-dimensions.php` — `business_type` salah satu dimensi yang boleh jadi syarat aturan harga
  - `app/Services/Pricing/BusinessTypeResolver.php` — pencocokannya saat aturan harga dinilai
- **Deskripsi:**
  Waktu kolom ini dibuat, jawabannya boleh "belum jelas" karena tidak ada yang bergantung padanya. Sejak `pricing_rules` bisa bersyarat jenis usaha, tenant yang membiarkannya kosong dinilai sebagai jenis bawaan — dan itu jawaban yang **ditebakkan untuknya**, bukan yang ia pilih. Selama belum ada aturan harga yang benar-benar memakai dimensi ini, akibatnya nol; begitu ada, sebagian tenant masuk kelompok tarif karena kolom yang tak pernah mereka isi.
- **Yang perlu diputuskan sebelum dikerjakan:**
  1. **Wajibkan, atau biarkan opsional dengan bawaan yang terang-terangan.** Mewajibkan berarti menambah satu pertanyaan berjawab-wajib di alur pendaftaran, dan itu menaikkan gesekan pendaftaran demi hal yang bisa diperbaiki belakangan dari Pengaturan.
  2. **Nasib tenant yang sudah terlanjur berbawaan.** Mewajibkan di formulir tidak menyentuh yang sudah ada; kalau jawabannya penting, ia butuh permintaan susulan di aplikasi, bukan migrasi yang menebak.
- **Usulan Perbaikan:**
  **(a)** Jangan dikerjakan sebelum ada aturan harga yang benar-benar bersyarat `business_type` — sebelum itu ia menambah gesekan tanpa menukar apa pun.
  ~~**(b)** Bila dikerjakan, sebutkan **kenapa** ditanyakan tepat di formulirnya ("dipakai menentukan tarif"), jangan sekadar mencabut label "(opsional)". Pertanyaan wajib tanpa alasan yang terbaca justru ditebak-tebak jawabannya.~~ — **dikerjakan 2026-08-24.**
- **Keputusan pemilik 2026-08-24 (opsi iii) — apa yang sudah tertutup, dan apa yang sengaja belum:**
  **Tertutup:** layar pendaftaran berhenti menyembunyikan bawaannya. Pilihan kosongnya kini berbunyi "Belum yakin — disamakan dengan Lainnya", dan satu baris di atas pemilihnya menyebut kenapa pertanyaannya diajukan. Dikunci empat tes di `tests/Feature/Auth/BusinessTypeOptionalityTest.php`.
  **Belum, dan itu disengaja:** kolomnya TETAP `nullable`, karena usulan (a) di atas belum terpenuhi — **belum ada satu pun aturan di `pricing_rules` yang bersyarat `business_type`** (diperiksa ulang 2026-08-24). Dua pertanyaan asli entri ini masih berdiri apa adanya.
  **Satu hal yang ditemukan saat mengerjakannya dan belum pernah tercatat:** `app/Http/Controllers/Owner/Settings/BusinessProfileController.php` sudah memvalidasi `business_type` sebagai `required`, sementara pendaftaran menerima kosong — **wajib saat diubah, opsional saat dibuat.** Ketidakkonsistenan ini dibiarkan sadar, karena menyeragamkannya berarti menjawab pertanyaan yang sedang ditahan. Jangan "dirapikan" tanpa keputusan.
- **Entri terkait di `CHANGELOG.md`:** `[DECISION] Jenis Usaha Tetap Opsional, tapi Bawaannya Berhenti Ditebakkan Diam-diam (BL-079)`

### [BL-051] Tenant yang Sudah Ditangguhkan Tidak Punya Tagihan untuk Dibayar
- **Ditemukan:** 2026-08-07 (saat meninjau `renewPeriod()` × `[BL-044]`)
- **Sumber:** Telaah, bukan laporan — sisi lain dari lubang masa tenggang yang ditutup 2026-08-07
- **Status:** Open — **butuh keputusan pemilik**, bukan pekerjaan kode
- **Prioritas:** Low sekarang (belum ada satu pun tenant `suspended`); naik ke Medium begitu tenant pertama benar-benar tertangguh
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php` — `issueDuePeriodInvoices()`, daftar status yang ikut ditagih
  - `app/Services/SubscriptionService.php` — `renewPeriod()`, aturan "tunggakan tidak ditumpuk"
  - `app/Http/Controllers/Platform/InvoiceController.php` — `store()`, satu-satunya jalan keluar hari ini
- **Deskripsi:**
  Penerbit otomatis menagih tenant `trial`, `active`, dan (sejak 2026-08-07) `grace`. `suspended` sengaja di luar: aksesnya sudah tertutup penuh, dan menerbitkan tagihan atas bulan yang tidak bisa dipakai berarti menumbuhkan utang yang tidak pernah diminta siapa pun.
  Konsekuensinya baru terlihat dari sisi tenant yang ingin **kembali**. Sekali tertangguh tanpa tagihan terbuka — misalnya tagihannya pernah ditolak lalu kedaluwarsa, atau ia tertangguh sebelum tarifnya pernah ditetapkan — tidak ada apa pun yang bisa ia bayar untuk pulih. `current_period_end` beku, penerbit tidak menyentuhnya, dan satu-satunya pintu adalah pemilik SaaS mengetikkan tagihannya manual di `/platform/invoices`. Itu persis keadaan yang `[BL-044]` tutup, hanya bergeser satu status ke kanan.
  Yang membuat ini keputusan dan bukan cacat: kedua jawabannya masuk akal dan berbeda artinya. Menagih otomatis berarti tenant yang sudah pergi tetap menerima tagihan bulanan. Tidak menagih berarti tenant yang ingin kembali harus menghubungi manusia lebih dulu.
- **Usulan Perbaikan:**
  **(a)** Putuskan mana yang berlaku: (i) tetap manual — pemulihan memang lewat percakapan, dan itu wajar untuk basis tenant sekecil ini; (ii) tombol "aktifkan kembali" di halaman langganan yang menerbitkan **satu** tagihan pemulihan atas permintaan tenant sendiri; atau (iii) penerbitan otomatis penuh seperti `grace`. Opsi (ii) paling dekat dengan bentuk yang sudah ada — ia meminjam alur `UpgradeController`, dan tagihannya lahir karena tenant memintanya, bukan karena kalender.
  **(b)** Apa pun pilihannya, **jangan** menerbitkan satu tagihan per bulan yang terlewat. Aturan "tunggakan tidak ditumpuk" di `renewPeriod()` memulihkan tepat satu periode ke depan per pembayaran; begitu ada dua tagihan langganan terbuka untuk satu tenant, melompati periode berarti benar-benar melompati uang, dan kedua aturan itu mulai bertabrakan. Ditinjau ulang 2026-08-07 dan dinyatakan aman **justru karena** penerbit tidak pernah melahirkan tagihan kedua — lihat docblock `renewPeriod()`.

---

### [BL-073] Puncak Seat yang Tak Pernah Turun Menahan Tenant Musiman di Bracket Adaptif yang Lebih Mahal
- **Ditemukan:** 2026-08-10 (dipecah dari `[BL-055]` butir (g) saat butir (a)–(f) selesai)
- **Sumber:** Sisa hidup terakhir `[BL-049]`, dipindahkan ke `[BL-055]` 2026-08-10 lalu berdiri sendiri di sini
- **Status:** Open — **menunggu keputusan**, bukan menunggu kode
- **Prioritas:** Low — laten selama belum ada satu pun aturan harga yang memakai dimensi `active_seats`; naik jadi Medium pada hari pertama ada
- **Area Terdampak:**
  - `app/Services/Pricing/ActiveSeatsResolver.php:27` — `max(seat_high_water, activeNow)` sebagai nilai dimensi harga `active_seats`
  - `app/Models/Subscription.php` — `recordSeatUsage()`: puncaknya hanya naik, tidak pernah turun sendiri
  - `app/Services/Billing/InvoiceSettlement.php` — satu-satunya yang meresetnya, dan hanya saat tagihan **langganan** dilunasi
- **Deskripsi:**
  Tagihan seat sudah lepas dari angka ini sejak `[BL-053]` — dasarnya `purchased_extra_seats`. Yang tersisa murni soal **penetapan harga Adaptif**: warung yang sempat menambah kasir sebulan bisa tertahan di bracket yang lebih mahal meski kasirnya sudah dilepas, karena `seat_high_water` hanya turun saat tagihan langganan dilunasi.
  Hari ini dampaknya nol: tidak ada satu pun aturan di `pricing_rules` yang menyebut `active_seats`, jadi nilai dimensinya tidak pernah ikut dinilai. Ia menunggu aturan pertama yang memakainya — dan pada hari itu ia sudah salah sejak menit pertama, tanpa satu pun tanda.
- **Yang harus diputuskan lebih dulu, dan kenapa bukan sekadar `if`:**
  **Apa arti `active_seats` sebagai dasar harga** — puncak pemakaian, atau pemakaian sekarang? Keduanya sah. Puncak menutup akal-akalan "matikan kasir sehari sebelum tanggal tagih"; pemakaian sekarang menjawab warung musiman yang jujur. Yang tidak boleh adalah keduanya sekaligus, dan itulah keadaan hari ini: puncak yang di-reset oleh satu peristiwa yang sama sekali tidak berhubungan dengan harga.
- **Usulan Perbaikan (pilih satu, jangan gabungkan):**
  **(a)** `ActiveSeatsResolver` berhenti memakai puncak dan membaca pemakaian aktif apa adanya. Paling sederhana, dan konsisten dengan `[BL-053]` yang sudah mencabut peran puncak dari penagihan. Konsekuensinya harga bisa turun-naik tiap bulan mengikuti jumlah staf — yang memang maksudnya, tapi harus disengaja.
  **(b)** Puncaknya dipertahankan tapi diberi umur: diturunkan ke pemakaian aktif saat pemeriksaan omzet bulanan berjalan (`subscriptions:compute-revenue`), sehingga ia mengukur puncak SATU BULAN, bukan sepanjang masa. Menjaga penutup akal-akalannya sambil tetap memberi jalan turun.
  **Jangan** menambah reset kedua di tempat lain tanpa menghapus yang lama — dua penurun puncak yang berjalan di jadwal berbeda akan membuat harga tenant bergantung pada peristiwa mana yang kebetulan terjadi lebih dulu.

---

### [BL-060] Integrasi Sumopod Payment Gateway (Sandbox → Produksi) — Terhalang Dokumentasi & Kredensial
- **Ditemukan:** 2026-08-07
- **Sumber:** Permintaan pemilik 2026-08-07 — "nanti kalau sudah bagus dan lengkap, baru pakai Sumopod payment gateway-nya (minimal bisa demo, jadi masih pakai sandbox)"
- **Status:** Blocked — **belum ada akunnya sama sekali** (dikonfirmasi pemilik 2026-08-07), jadi ini bukan menunggu dokumentasi melainkan menunggu keputusan penyedia
- **Prioritas:** Low sampai akunnya dibuat; naik ke High begitu kredensial sandbox ada
- **Bergantung pada:** `[BL-059]` — **sudah selesai 2026-08-07**. Kontrak `PaymentGateway`, tabel `payment_attempts`, dan endpoint webhook sudah berdiri; yang tersisa di sini murni pekerjaan driver.
- **Area Terdampak:**
  - `app/Services/Billing/Gateways/` — driver `SumopodGateway` baru
  - `config/services.php:43` — hari ini `sumopod` hanya berisi `key` untuk **gateway AI** (`https://ai.sumopod.com/v1`, lihat `app/Services/Ai/SumoPodProvider.php`)
  - `.env.example:77` — `SUMOPOD_API_KEY` yang sudah ada adalah kunci AI
- **Deskripsi:**
  Yang sudah terpasang di aplikasi ini dengan nama Sumopod adalah **gateway AI** — satu API key untuk memanggil banyak model lewat `ai.sumopod.com`. Produk pembayarannya belum pernah disentuh di sini dan tidak bisa saya verifikasi dari repositori. **Jangan memakai ulang `SUMOPOD_API_KEY` untuk pembayaran**: dua produk berbeda, kemungkinan besar dua kredensial berbeda, dan menumpangkan keduanya pada satu variabel berarti mencabut satu akan mematikan yang lain tanpa alasan yang terlihat. Pakai nama tersendiri (mis. `SUMOPOD_PAY_*`).
- **Keadaan per 2026-08-07 (dikonfirmasi pemilik):** belum ada akun Sumopod maupun Xendit untuk pembayaran langganan. `XENDIT_SECRET_KEY` yang ada di `.env` bukan bantahan atas itu — ia milik jalur pembayaran **pelanggan di kasir/self-order** (`Api\XenditWebhookController`), bukan tagihan langganan, dan tidak ada akun aktif di belakangnya.
  Karena itu entri ini **tidak menahan apa pun**: gateway tiruan sudah menyelesaikan kebutuhan development dan peragaan (`[BL-059]`), dan kontraknya sudah berdiri. Yang tersisa murni menunggu keputusan komersial pemilik tentang penyedia mana yang dipakai.
- **Kalau nanti penyedianya bukan Sumopod:** entri ini tetap berlaku apa adanya. Yang dinamai "Sumopod" di judulnya cuma contoh; seluruh pertanyaan dan pekerjaan di bawah berlaku untuk penyedia mana pun, karena yang mengikatnya adalah kontrak `PaymentGateway`, bukan nama penyedianya.
- **Yang perlu dijawab pemilik sebelum kode ditulis:**
  1. Tautan dokumentasi API pembayarannya dan alamat dashboard merchant/sandbox.
  2. Kanal apa saja yang aktif di akun Anda (QRIS, VA bank mana saja, e-wallet).
  3. Bentuk autentikasinya (API key di header, HMAC, basic auth) dan **cara memverifikasi tanda tangan callback** — ini syarat masuk `[BL-059]`(f), bukan pelengkap.
  4. Apakah ada endpoint **cek status transaksi**. Kalau tidak ada, rekonsiliasi hanya bisa bergantung pada webhook, dan webhook yang hilang berarti tenant sudah membayar tapi aksesnya tidak pulih.
  5. **Biaya admin ditanggung siapa.** Kalau dipotong dari nominal yang diterima, jumlah yang masuk akan selalu lebih kecil dari yang ditagih dan aturan `mismatch` di `[BL-059]`(f) akan menahan **setiap** pembayaran. Keputusannya: tenant membayar nominal + biaya (tagih lebih), atau kita menerima toleransi selisih yang tertulis.
  6. Kebijakan pembatalan/refund, dan apa yang terjadi pada periode langganan bila terjadi refund.
- **Pekerjaan setelah pertanyaan terjawab:**
  **(a)** `SumopodGateway` mengisi kontrak yang sama; seluruh pemanggil di `[BL-059]` tidak berubah.
  **(b)** Tes memakai `Http::fake()` dengan contoh payload asli dari dokumentasinya — termasuk satu payload dengan tanda tangan salah dan satu payload duplikat.
  **(c)** Callback sandbox butuh URL publik HTTPS; siapkan tunnel untuk pengujian lokal, dan tanyakan apakah ada allowlist IP di sisi mereka.
  **(d)** Halaman platform untuk melihat `payment_attempts` per tenant — tanpa itu, kegagalan pembayaran hanya terlihat sebagai tagihan yang tidak kunjung lunas.
  **(e)** Peralihan produksi dilakukan per-flag config, dan **jalur bukti transfer manual tetap dipertahankan** sebagai cadangan.

---

### [BL-065] Pajak/PPN Belum Ada Sama Sekali — dan Bentuk yang Diminta Menentukan Kolomnya, Bukan Tampilannya
- **Ditemukan:** 2026-08-08
- **Sumber:** Saran pasca-peragaan — "pajak + PPN, mode munculkan include atau tidak untuk ke pelanggan (misal harga dijual include PPN atau ditanggung customer)"
- **Status:** Open — **butuh keputusan pemilik sebelum ada kode**
- **Prioritas:** Medium (naik ke High bila ada calon klien yang wajib memungut PPN)
- **Area Terdampak:**
  - `database/migrations/2026_03_06_000011_create_transactions_table.php:17` — `total_amount` saja; **tidak ada** `subtotal`, `tax_amount`, atau `service_charge`
  - `database/migrations/2026_03_06_000013_create_transaction_items_table.php` — `unit_price` + `subtotal`, tanpa penanda pajak
  - `resources/js/Components/ReceiptModal.vue:38-41,170-178` — struk menghitung "Subtotal" dengan menjumlahkan item di sisi klien, lalu langsung ke "TOTAL"; tidak ada baris di antaranya
  - Pencarian `tax|ppn|pajak` di seluruh `app/`, `config/`, dan `database/migrations`: **nol hasil**
- **Deskripsi:**
  Ini bukan fitur yang perlu diperbaiki, melainkan yang belum pernah ada. Tidak ada tarif pajak, tidak ada kolom, tidak ada baris di struk. Aplikasi hari ini mengasumsikan harga jual adalah angka final dan tidak punya cara menyatakan berapa bagian dari angka itu yang sebetulnya pajak.
  Yang membuatnya bukan sekadar pekerjaan tampilan adalah dua mode yang disebut di saran. **Include** berarti tarif katalog sudah mengandung pajak dan pajaknya *diurai ke belakang* dari total (`pajak = total × t/(1+t)`) — omzet tenant tidak naik, dan angka penjualan historis tetap sebanding. **Exclude/ditanggung pelanggan** berarti pajak *ditambahkan di depan* total — yang dibayar pelanggan naik, dan semua total transaksi setelah mode ini menyala tidak lagi sebanding dengan sebelumnya. Keduanya menyentuh angka uang yang sudah tercatat, jadi pilihan modenya bukan preferensi tampilan.
  Dan itu menjalar. Omzet tenant adalah dasar bracket Harga Adaptif (`ComputeTenantMonthlyRevenue`); kalau `total_amount` mulai memuat PPN yang dipungut untuk negara, tenant naik bracket karena uang yang bukan miliknya.
- **Yang harus diputuskan pemilik sebelum kode ditulis:**
  1. **Satu tarif per tenant, atau per produk/kategori?** Kafe umumnya satu (PJ/PB1 atau PPN); ritel bisa campur barang kena dan tidak kena. Yang kedua jauh lebih mahal — jangan dipilih tanpa alasan nyata.
  2. **Mode include atau exclude — bisa diubah kapan saja, atau terkunci setelah ada transaksi?** Saran saya: boleh diubah, tapi perubahannya berlaku ke depan dan tercatat, karena transaksi lama tidak boleh dihitung ulang.
  3. **Yang dimaksud PPN 11%/12% (negara) atau pajak restoran daerah 10% (PB1)?** Keduanya sering disebut "pajak" oleh pemilik toko, tapi dasar hukum dan pelaporannya berbeda.
  4. **Service charge ikut sekarang atau tidak?** Bila ya, urutannya harus ditetapkan — pajak dihitung sebelum atau sesudah service charge — karena hasilnya berbeda.
- **Usulan Perbaikan (setelah keputusan di atas):**
  **(a)** `transactions` mendapat `subtotal_amount` dan `tax_amount` (dan `service_charge_amount` bila dipakai), dengan `total_amount` tetap sebagai yang dibayar pelanggan — kolom yang sudah dibaca banyak tempat jangan berubah maknanya. Migrasi mengisi transaksi lama dengan `subtotal = total`, `tax = 0`, yang memang benar untuk masa sebelum pajak ada.
  **(b)** Tarif dan modenya tinggal di `tenants` (sejajar dengan `*_enabled` lain) dan **dibekukan per transaksi**, sama seperti `invoices.pricing_context` membekukan konteks tagihan. Struk lama harus tetap bisa dicetak ulang dengan angka yang sama walau tarifnya sudah berubah.
  **(c)** Struk menampilkan Subtotal → PPN (tarif%) → TOTAL untuk mode exclude, dan Subtotal → TOTAL dengan catatan "termasuk PPN Rp X" untuk mode include. Ini yang diminta saran itu, dan tanpa (a) tidak bisa ditulis dengan jujur.
  **(d)** **Putuskan omzet mana yang dipakai bracket Adaptif** — saran saya `subtotal_amount` (tanpa pajak), karena pajak bukan pendapatan tenant. Kalau tidak diputuskan sekarang, ia akan diputuskan diam-diam oleh baris `SUM(total_amount)` yang sudah ada.
  **(e)** Laporan harian/bulanan (`[BL-063]`) menampilkan omzet dan pajak terpungut sebagai dua angka. Pemilik toko yang memungut pajak butuh angka kedua itu untuk menyetorkannya.

### [BL-068] Multi-Cabang Belum Punya Wujud Apa Pun — Satu Tenant = Satu Outlet di Seluruh Basis Kode
- **Ditemukan:** 2026-08-08
- **Sumber:** Saran pasca-peragaan — "saran fitur cabang (pastiin yang lain berhasil semua dulu)"
- **Status:** Open — **sengaja ditahan atas syarat pemilik sendiri**
- **Prioritas:** Low sekarang; ini pekerjaan sebesar satu fase, bukan satu entri backlog
- **Area Terdampak (seluruhnya karena ketiadaan, bukan cacat):**
  - Pencarian `cabang|branch|outlet` di `app/Models`, `app/Http/Controllers`, `config/`, dan migrasi: **nol konsep**. Kata "outlet" hanya muncul di komentar yang berarti "tenant ini"
  - `app/Models/Tenant.php` — tenant memegang langsung identitas toko, jenis usaha, mode identitas pesanan, dan sakelar fitur; tidak ada lapisan di bawahnya
  - `transactions`, `cash_drawers`, `stocks`, `products`, `payment_methods`, `users` — semuanya bertumpu pada `tenant_id` sebagai satu-satunya sumbu pemisah
  - `app/Models/Scopes/TenantScope.php` — pemisahan data berhenti di tenant
  - `plans.included_seats` + penetapan harga — seluruh model bisnis dihargai per tenant, bukan per outlet
- **Deskripsi:**
  Tidak ada yang perlu "ditambahkan cabang"-nya; yang ada adalah asumsi satu-toko-satu-tenant yang tertanam di setiap tabel operasional. Menambahkan cabang berarti menyisipkan sumbu kedua (`branch_id`) di bawah `tenant_id` dan menjawabnya ulang di tiap tempat: stok per cabang atau bersama, katalog dan harga per cabang atau seragam, sesi kas jelas per cabang, staf terikat satu cabang atau bisa lintas, dan laporan default ke cabang mana.
  Hari ini seorang pemilik dua cabang bisa membuat dua tenant terpisah — dan itu **berhasil**, hanya tanpa laporan gabungan dan dengan dua tagihan. Untuk sebagian calon klien, itu sudah memadai; itu sebabnya menunda entri ini tidak menutup pintu penjualan.
- **Kenapa syarat pemilik ("pastikan yang lain berhasil dulu") memang tepat:** cabang menyentuh penetapan harga (`[BL-041]`), gerbang langganan, laporan (`[BL-063]`), dan sejak 2026-08-20 juga kuota AI yang dibeli per langganan (`[BL-069]`, selesai) sekaligus. Mengerjakannya sekarang berarti setiap entri di daftar itu harus ditulis dua kali — sekali untuk satu outlet, sekali untuk banyak; yang sudah selesai pun harus dibongkar ulang, karena haknya melekat pada langganan, bukan pada cabang.
- **Yang harus dijawab sebelum satu baris kode pun ditulis:**
  1. **Cabang menaikkan tagihan atau tidak?** Ini pertanyaan pertama, bukan terakhir — jawabannya menentukan apakah `branch_id` cukup jadi kolom, atau harus jadi entitas yang ikut dihitung penetapan harga. Kalau tiap cabang dibayar terpisah, dua tenant terpisah nyaris sama saja dan fitur ini kehilangan alasan komersialnya.
  2. **Stok per cabang atau satu kolam?** Ini yang paling menentukan besarnya pekerjaan. Stok per cabang berarti seluruh pergerakan stok, transfer antar cabang, dan opname harus tahu cabang.
  3. **Katalog & harga seragam atau per cabang?** Seragam jauh lebih murah dan cukup untuk sebagian besar kasus.
  4. **Staf terikat satu cabang, dan bagaimana hubungannya dengan RBAC yang sudah ada?** Peran hari ini berlaku se-tenant; "kasir di cabang A saja" adalah dimensi baru pada model izin, bukan peran baru.
  5. **Apa yang terjadi pada tenant yang sudah berjalan?** Jawaban yang paling murah dan paling aman: setiap tenant lahir dengan satu cabang bawaan, dan yang tidak pernah membuat cabang kedua tidak pernah melihat kata "cabang" di mana pun.
- **Catatan pengerjaan:** kalau nanti dikerjakan, buka sebagai **fase tersendiri** dengan dokumennya sendiri di `docs/phases-*`, bukan sebagai entri backlog. Ukurannya sekelas fase SAAS, dan menyelundupkannya sebagai "satu perbaikan" akan menghasilkan migrasi setengah jadi di tabel yang paling tidak boleh setengah jadi.

### [BL-070] Membeli Seat di Tengah Periode Gratis Sampai Periode Habis — Prorata Ditunda, Bukan Ditolak
- **Ditemukan:** 2026-08-08 (sisa `[BL-053]` butir (b)(2), sengaja dipisahkan untuk dipikirkan ulang)
- **Sumber:** Keputusan pemilik 2026-08-08 — "gratis sisa periode", dipilih sadar sebagai yang paling sederhana, bukan sebagai yang paling adil
- **Status:** Open — **bukan cacat.** Yang berjalan sekarang adalah pilihan yang diputuskan; entri ini menahan pertanyaannya supaya bisa ditinjau ulang dengan data pemakaian nyata
- **Prioritas:** Low — kerugiannya berbatas dan bisa dihitung; jangan dikerjakan sebelum ada bukti bahwa polanya benar-benar dipakai
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php` — `grantSeats()`: menaikkan `purchased_extra_seats` tanpa menagih apa pun untuk sisa periode
  - `app/Services/SubscriptionService.php` — `seatChargeFor()`: menghitung hak untuk periode yang ditagih, tanpa dimensi hari
  - `app/Services/SubscriptionService.php` — `releaseSeats()`: pasangannya, dan alasan celahnya tetap tertutup
- **Deskripsi:**
  Seat yang dibeli tanggal berapa pun dalam sebuah periode **gratis sampai periode itu habis**, lalu muncul utuh di tagihan berikutnya. Tenant yang membeli sehari setelah periodenya dibuka mendapat 29 hari cuma-cuma; yang membeli sehari sebelum periodenya habis mendapat 1 hari. Keduanya membayar sama.
  Itu diputuskan sadar dan alasannya kuat: menagih prorata berarti menghidupkan kembali tagihan di tengah bulan — persis yang keputusan "seat jadi komponen bulanan" singkirkan — atau menambah satu baris prorata di tagihan berikutnya yang harus dijelaskan tiap kali. Ongkos penjelasannya nyata, sementara kerugiannya paling banyak satu periode per seat, sekali seumur pembelian.
  **Celah "beli lalu lepas tanpa pernah bayar" TIDAK terbuka** — itu ditutup dari sisi lain: pelepasan berlaku satu periode penuh ke depan, jadi tiap seat yang dibeli pasti tertagih sekali. Yang tersisa murni soal keadilan waktu, bukan soal uang yang lolos.
- **Kenapa layak dipikir ulang, dan kapan:**
  Kerugiannya berbanding lurus dengan **seberapa sering seat dibeli di awal periode**. Selama pembelian tersebar merata sepanjang bulan, rata-ratanya setengah periode per seat dan itu ongkos akuisisi yang wajar. Yang membuatnya berubah sifat: kalau tenant belajar bahwa membeli tepat setelah tanggal tagih adalah yang paling menguntungkan, sebarannya berhenti merata dan mendekati "selalu di hari pertama". Itu bisa diukur — bandingkan tanggal `subscriptions.seats-granted` di `platform_audit_logs` terhadap `billing_anchor_day`.
  **Ambang yang masuk akal untuk meninjau ulang:** kalau lebih dari separuh pembelian jatuh di sepertiga awal periode, polanya bukan kebetulan lagi.
- **Tiga bentuk yang mungkin, kalau nanti diubah:**
  **(a) Prorata per hari, masuk tagihan berikutnya sebagai baris terpisah.** Paling adil, dan tidak menghidupkan tagihan tengah bulan. Ongkosnya: satu perhitungan tanggal yang harus benar di bulan pendek, dan satu baris tagihan yang harus dijelaskan. Kalau ini yang dipilih, rincian prorata **wajib** ikut `pricing_context.billing_breakdown` — jangan ulangi pola tagihan yang tidak bisa dijelaskan pecahannya.
  **(b) Prorata kasar setengah periode.** Seat yang dibeli di paruh pertama ditagih penuh, di paruh kedua gratis. Menutup sebagian besar kerugian dengan satu perbandingan tanggal, tanpa aritmetika hari. Lebih mudah dijelaskan ("dibeli sebelum tanggal 15, ditagih bulan ini juga") daripada prorata sesungguhnya.
  **(c) Biarkan.** Tetap pilihan yang sah, dan yang paling murah dijelaskan. Kalau angkanya menunjukkan sebaran pembelian memang merata, ini jawabannya.
- **Yang jangan dilakukan:** menagih seat dengan tagihan tersendiri di tengah periode. Itu mengembalikan `KIND_UPGRADE` beserta bukti transfer dan antrean pemeriksaannya, dan membatalkan alasan seluruh `[BL-053]` dikerjakan.

---

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

- **Pemutakhiran 2026-08-15 — tempat mendaratnya sudah ada, tinggal keputusannya.** `[BL-034]` selesai tanpa menunggu entri ini: mekanisme presetnya utuh dan berjalan untuk keempat jenis usaha yang ada. Yang berubah untuk entri ini adalah biaya menyelesaikannya. Dulu "mode bazar" berarti memutuskan definisinya **dan** membangun jalan agar ia sampai ke tenant baru. Sekarang jalannya sudah ada: `config/business-presets.php` memetakan jenis usaha ke daftar kapabilitas, dan pendaftar melihatnya sebagai daftar centang yang bisa ia ubah. Begitu daftar perbedaan mode bazar diputuskan dan diwujudkan sebagai flag di `Tenant::hasFeature()`, menyalakannya untuk tenant bazar baru adalah **satu baris tambahan di config** — bukan penyuntingan alur pendaftaran. Yang tersisa di entri ini murni pertanyaan produk, persis seperti judulnya bilang.

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

### [BL-076] Berkas Milik Tenant Masih Menumpang Disk Server — Belum Ada Jalan ke Object Storage
- **Ditemukan:** 2026-08-13
- **Sumber:** Keputusan pemilik saat menetapkan bentuk `[BL-075]` — "simpankan saja backlog untuk nanti (setelah ini selesai), untuk memindahkan ke storage server seperti s3"
- **Status:** Open — **sengaja dijadwalkan sesudah `[BL-075]`**, bukan sebelum
- **Prioritas:** Low sekarang; naik ke Medium begitu `[BL-075]` menyala di tenant sungguhan, karena sejak saat itu volume berkas tidak lagi tumbuh sebatas katalog produk
- **Area Terdampak:**
  - `app/Services/ImageService.php:31` — `public const DISK = 'local'`; disknya **konstanta**, bukan konfigurasi
  - `app/Http/Controllers/MediaController.php:39-48` — berkas dialirkan lewat PHP (`disk()->response()`) setelah pemeriksaan kepemilikan tenant
  - `config/filesystems.php:50` — disk `s3` **sudah terdefinisi** tapi tidak ada satu pun kode yang membacanya
  - `config/filesystems.php:16` — `FILESYSTEM_DISK` default `local`
- **Deskripsi:**
  Semua gambar tenant — hari ini foto produk, nanti ditambah bukti pembayaran dari `[BL-075]` — duduk di disk lokal server aplikasi. Selama satu server itu masih cukup, ini bekerja baik dan tidak perlu diubah. Yang belum ada adalah **jalan keluarnya**, dan itu baru terasa saat salah satu dari tiga hal terjadi: aplikasi dijalankan lebih dari satu instance (berkas yang diunggah instance A tidak terlihat oleh instance B), disk server penuh, atau pemulihan bencana dibutuhkan dan ternyata berkasnya tidak ikut cadangan basis data.
  Perlu jujur soal satu hal: disk `s3` di `config/filesystems.php:50` **bukan** tanda pekerjaan ini setengah jadi. Itu bawaan Laravel yang tidak pernah dipakai. Menghitungnya sebagai kemajuan akan membuat estimasi pekerjaan ini terlalu optimistis.
- **Usulan Perbaikan:**
  **(a) `ImageService::DISK` jadi nilai konfigurasi, bukan konstanta kelas.** Ini perubahan terkecil dan harus dilakukan lebih dulu, karena setelah itu peralihan tinggal soal `.env` per lingkungan — dan lingkungan pengembangan bisa tetap `local` tanpa perlu kredensial apa pun.
  **(b) Berkas yang sudah ada tidak ikut berpindah dengan sendirinya.** Menukar disk hanya mengubah ke mana berkas **baru** ditulis; gambar produk yang sudah ada akan mendadak 404. Perlu perintah pemindahan yang bisa dijalankan ulang dengan aman, dan tenggang waktu ketika pembacaan mencoba disk baru lalu jatuh ke disk lama.
  **(c) Keputusan yang paling menentukan: `MediaController` tetap mengalirkan byte, atau berpindah ke URL bertanda tangan sementara?** Mengalirkan byte lewat PHP mempertahankan pemeriksaan tenant apa adanya, tapi setiap gambar melewati server aplikasi dan sebagian besar keuntungan object storage hilang. URL bertanda tangan menghapus beban itu, tapi menukar pemeriksaan kepemilikan-per-permintaan dengan tautan berbatas waktu yang bisa diteruskan siapa saja selama masih berlaku. Untuk foto produk itu mungkin dapat diterima; untuk bukti pembayaran `[BL-075]` — yang bisa memuat nama dan nomor pelanggan — pertimbangannya berbeda. **Putuskan per jenis berkas, jangan satu kebijakan untuk semuanya.**
  **(d) Jangan longgarkan yang sudah dijaga.** Alasan berkas ini privat ditulis di `ImageService.php:23-27`: symlink publik berarti siapa pun yang tahu path bisa membuka gambar toko mana pun. Bucket dengan akses publik adalah bentuk yang sama persis dari kesalahan itu, hanya dengan nama host yang berbeda.

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

---

## Riwayat Selesai (Arsip)

Isi lengkap entri yang sudah selesai dipindahkan ke **`docs/BACKLOG-ARCHIVE.md`** (2026-07-31). Tabel di bawah adalah indeksnya — cukup untuk menjawab "sudah selesai belum?" dan "entri CHANGELOG mana yang menutupnya?" tanpa membuka arsipnya sama sekali.

| ID | Judul | Selesai | Entri penutup di `docs/CHANGELOG.md` |
|---|---|---|---|
| `BL-091` | Seluruh aplikasi Vue (1,1 MB, 56 halaman) dikirim ke setiap pengunjung halaman publik, lalu gagal mount | 2026-08-24 (butir (a) dan (d); butir (b) dibatalkan karena halaman publik tidak butuh JS terbundel sama sekali, butir (c) dipisah jadi `[BL-094]`) | `[HOTFIX] Halaman Depan Berhenti Mengunduh Seluruh Aplikasi Vue yang Tidak Dipakainya (BL-091)` |
| `BL-077` | Kompres/resize/WEBP otomatis baru ada di foto produk — tiga jalur gambar lain melewatinya | 2026-08-24 (butir (c); usulan plugin Vite dibatalkan dengan alasan — Vite tidak pernah memproses `public/`) | `[REFACTOR] Dua Aset Yatim Dibuang, dan Celah yang Selama Ini Ditambal Manusia Dijaga Test (BL-077)` |
| `BL-072` | Enam commit berturut-turut tidak bisa boot — `git bisect` dan `git revert` menyesatkan di rentang itu | 2026-08-24 (butir (d); butir (c) tetap ditolak, riwayatnya dibiarkan rusak dengan sengaja) | `[ADDITION] Riwayat yang Tidak Bisa Boot Dibiarkan, Peringatannya yang Dipindah ke Tempat Terbaca (BL-072)` |
| `BL-082` | Seluruh aplikasi berjalan di UTC padahal tokonya tidak — "hari ini" bergeser 7–8 jam dari hari toko | 2026-08-22 (keputusan pemilik: **satu zona untuk seluruh aplikasi**, `Asia/Makassar`/WITA, lewat `APP_TIMEZONE`) | `[DECISION] Seluruh Aplikasi Berjalan di Jam Toko (WITA), dan Satu Tempat Saja yang Menjawab "Hari Ini" (BL-082)` |
| `BL-087` | Tidak ada cara mencatat uang keluar atau setoran di tengah sesi kas | 2026-08-21 (bentuk C+E: kasir selalu mencatat, efeknya tertahan di atas ambang Rp 50.000 yang bisa diubah pemilik) | `[ADDITION] Uang Keluar Laci Punya Tempat Mencatatnya, dan Efeknya yang Ditahan — Bukan Pencatatannya (BL-087)` |
| `BL-092` | Saran jual hanya terlihat separuh oleh pemilik, dan penerimaan yang salah tidak bisa ditarik | 2026-08-21 (ketiga butirnya, plus event pada transaksi `voided` yang ikut terhitung) | `[HOTFIX] Saran yang Terlanjur Diterima Bisa Ditarik Lagi, dan Transaksi yang Di-void Berhenti Mengaku Berhasil (BL-092 butir 3)` + `[ADDITION] Owner Melihat Saran Otomatis, Aturannya Sendiri, dan Siapa yang Mengisi Tiga Slot Kasir (BL-092 butir 1-2)` |
| `BL-088` | Sesi kas tidak punya umur, tidak pernah ditutup sendiri, dan rekapnya terus membesar | 2026-08-21 (batas 24 jam, dari kalimat pemilik "masa hidup kas cuma sehari") | `[ADDITION] Sesi Kas Punya Umur, dan yang Lewat Ditutup Sistem Tanpa Mengaku Sudah Dihitung (BL-088)` |
| `BL-090` | Angka rekonsiliasi tetap dikirim ke kasir — penyembunyiannya baru di sisi klien | 2026-08-21 (pemilik memilih bentuk (2): mencatat, bukan mencegah) | `[ADDITION] Membuka Angka Seharusnya Meninggalkan Jejak yang Dibaca Pemilik (BL-090)` |
| `BL-086` | Layar tutup kas menyebutkan jawabannya sebelum kasir menghitung, dan dua alur berbeda menumpang satu halaman | 2026-08-21 (ketiga butirnya, plus satu lubang yang ditemukan saat mengerjakannya) | `[ADDITION] Angka yang Jadi Jawaban Disembunyikan Selama Sesi Berjalan (BL-086 butir 1)` + `[REFACTOR] Tutup Kas Punya Halamannya Sendiri, dan Angka yang Sudah Terbaca Tidak Bisa Disunting Diam-diam (BL-086 butir 2)` |
| `BL-089` | Klaim prediksi stok masih berdiri di tab demo, FAQ, dan label "Machine Learning" | 2026-08-20 (dijawab pemilik: tab diganti **Saran Jual**, FAQ diganti cara Badge Helper bekerja; butir (c) dikerjakan lebih dulu karena tidak menunggu keputusan) | `[HOTFIX] Tab Demo Ketiga Berhenti Meramal dan Jadi Saran Jual yang Memang Sudah Jalan (BL-089)` |
| `BL-085` | Grup "Keuangan" di sidebar menampung alat promosi bersama laporan uang | 2026-08-21 | `[REFACTOR] Aturan Saran Jual dan Diskon Keluar dari "Keuangan", Jadi Grup Sendiri (BL-085)` |
| `BL-083` | Kartu melayang di hero menjanjikan "Prediksi Stok Aman Hingga 14 Hari" yang tidak ada mesinnya | 2026-08-20 (dijawab pemilik: ganti jadi Stok Kritis; butir (b) dan (c) ikut dikerjakan, dan butir (c) memunculkan `[BL-089]`) | `[HOTFIX] Hero Berhenti Menjanjikan Ramalan Stok dan Tombol yang Tidak Punya Jalan (BL-083)` |
| `BL-084` | Sidebar owner menulis "SAPI", bukan nama toko yang sedang dibuka | 2026-08-21 (pertanyaan glyph-nya dijawab sekalian: inisialnya ikut nama toko) | `[HOTFIX] Sidebar Owner Menyebut Nama Toko yang Sedang Dibuka, Bukan Nama Produknya (BL-084)` |
| `BL-032` | Landing page tidak lagi menggambarkan produk yang sudah jadi | 2026-08-20 (butir (1)+(2) 2026-08-14; butir (3) ditutup 2026-08-20 setelah server MCP chrome-devtools tersambung dan bisa menulis berkas gambar) | `[ADDITION] Lima Tangkapan Layar Landing Diambil Ulang dari Aplikasi yang Berjalan, dan Jadi WebP (BL-032 butir 3)` |
| `BL-069` | Kuota AI tambahan belum bisa dibeli — tidak ada kolom, tidak ada harga, tidak ada layar | 2026-08-20 (butir (a)-(d) seluruhnya; prasyarat pembatasan `profit_by_item` ikut dikerjakan lebih dulu. Saran (1) paket kredit dan (2) tidak menjualnya ditolak sadar oleh keputusan pemilik 2026-08-19) | `[ADDITION] Kuota AI Tambahan Akhirnya Bisa Dibeli, dan Dilepas Lagi — Persis seperti Kursi (BL-069)` |
| `BL-078` | Testimoni di landing berdiri di atas nama dan potret yang tidak bisa dipertanggungjawabkan | 2026-08-20 (dijawab pemilik: memang karangan; ditempuh jalan kedua opsi (2) — diganti bagian "Untuk Siapa SAPI Dibuat", bukan dihapus) | `[DEPRECATE] Testimoni Karangan Dicabut, Diganti Bagian yang Tidak Mengaku Sebagai Kesaksian (BL-078)` |
| `BL-031` | Umur tagihan terbuka belum pernah diputuskan — sesi kas, per hari, atau sampai dilunasi | 2026-08-20 (dijawab pemilik 2026-08-19: per hari + kas negatif; keenam butir pelaksanaannya mendarat 2026-08-20, termasuk jalur penghapusan oleh pemilik tempat stok kembali) | `[ADDITION] Tagihan Terbuka Punya Umur, dan yang Lewat Jadi Kas Negatif yang Hanya Owner Bisa Bereskan (BL-031)` |
| `BL-080` | Tagihan terbit sebelum omzet bulan sebelumnya dihitung — tenant berjangkar tanggal 1–8 ditagih dari omzet dua bulan lalu | 2026-08-20 (butir (a) tukar jadwal; butir (b) dijawab pemilik: opsi (i) tunda penerbitan, keluarga (c)/jangkar ditolak; butir (c) ikut dikerjakan sebagai prasyarat. Opsi (iv-b) yang sempat dipertimbangkan dipisah jadi `[BL-081]`) | `[FIX] Tagihan Tenant Adaptif Menunggu Omzetnya, dan Berhenti Menebak Bulan (BL-080)` |
| `BL-056` | Pengajuan harga adaptif berlaku untuk bulan mana — bulan pengajuan atau bulan berikutnya | 2026-08-19 (dijawab: periode berikutnya, sebagai turunan model prabayar; cacat penjadwalan yang ikut terungkap dipisah jadi `[BL-080]`) | `[DECISION] Prabayar, dan Tarifnya dari Omzet Bulan Sebelumnya (BL-056)` |
| `BL-061` | Tombol simulasi lama kini jalur uang ketiga — dicabut setelah peragaan | 2026-08-19 (seluruh butir (a)–(d); `SOURCE_SIMULATION` sengaja ditahan sebagai peninggalan) | `[DEPRECATE] Tombol Simulasi Pembayaran Dicabut — Jalur Uang Ketiga Ditutup (BL-061)` |
| `BL-018` | Diskon dinamis barang mendekati habis/kedaluwarsa dengan penjaga margin | 2026-08-19 (poin 1–7; bundling berdiskon tetap pekerjaan tersendiri) | `[ADDITION] Diskon Jadi Entitas Tersendiri, dengan Lantai Untung yang Hanya Manusia Boleh Tembus (BL-018)` |
| `BL-013` | Akun platform belum punya 2FA | 2026-08-19 (TOTP + kode pemulihan + jejak audit; `email_verified_at` dan kode QR sengaja tidak ikut) | `[ADDITION] Akun Platform Punya Faktor Kedua, dan Kata Sandi yang Benar Tidak Lagi Berarti Masuk (BL-013)` |
| `BL-074` | Saran jual hanya bisa ditemukan mesin — owner belum punya cara menargetkan sendiri | 2026-08-19 (butir (a)–(f); batas tampil kasir ikut naik 2 → 3 atas permintaan pemilik) | `[ADDITION] Owner Akhirnya Bisa Menargetkan Saran Jualnya Sendiri, dan Aturannya Selalu Menang Slot (BL-074)` |
| `BL-075` | Foto bukti pembayaran non-tunai belum ada — dan harus bisa dimatikan per toko | 2026-08-19 (seluruh butir; modal EDIT transaksi sengaja tidak ikut, alasannya di arsip) | `[ADDITION] Pembayaran Non-Tunai Bisa Difoto, dan Sinkronisasi Offline Ternyata Tidak Perlu Ikut Berubah (BL-075)` |
| `BL-034` | Pendaftaran belum menentukan paket/fitur — tipe usaha hanya dipakai harga | 2026-08-15 (mekanismenya utuh untuk keempat jenis usaha; preset `bazar` menunggu `[BL-035]` dan masuk sebagai satu baris config) | `[ADDITION] Jenis Usaha Akhirnya Menentukan Fitur, Bukan Cuma Harga (BL-034)` |
| `BL-037` | Perpindahan halaman hanya ditandai progress bar — belum ada skeleton | 2026-08-13 (komponen + 4 halaman pertama), 2026-08-15 (sisa tabel pelacakan + bilah kemajuan) | `[ADDITION] Halaman Menunjukkan Bentuknya Sebelum Datanya Sampai (BL-037)` |
| `BL-044` | Trial habis tanpa ada yang menerbitkan tagihan — bulan kedua tidak pernah menagih | 2026-08-01 (butir (a)), 2026-08-06 (butir (b)), 2026-08-15 (butir (c)) | `[ADDITION] Tagihan Periode Terbit Sendiri Sebelum Aksesnya Menyempit (BL-044 butir b)` + `[ADDITION] Masa Coba Berakhir dengan Pertanyaan, Bukan dengan Tagihan (BL-044 butir c)` |
| `BL-071` | Alur pendaftaran tidak pernah ikut berubah — orang menandatangani masa gratis yang berakhir dengan tagihan tanpa diberi tahu | 2026-08-15 (butir (a)–(c); butir (d) dipisah jadi `[BL-079]`) | `[ADDITION] Halaman Daftar Menyebut Masa Gratis yang Berakhir dengan Tagihan (BL-071)` |
| `BL-041` | Tarif belum ditetapkan, dan halaman harga publik belum dinamis | 2026-08-07 (butir (a)), 2026-08-14 (butir (b) dan (c)) | `[ADDITION] Halaman /harga Membacakan Kedua Jalur Tarif kepada Orang yang Belum Mendaftar (BL-041 butir c)` + `[ADDITION] Tenant Jalur Harga Tetap Akhirnya Bisa Melihat Kelasnya Sendiri (BL-041 butir b)` |
| `BL-066` | Cara kerja Harga Adaptif tidak terjelaskan di satu pun permukaan publik | 2026-08-14 (butir (a)–(e); butir (b) butir ketiga dibatalkan karena klaimnya tidak benar) | `[ADDITION] Landing Menjelaskan Cara Tarif Dihitung — dan Berhenti Menjanjikan Penguncian yang Tidak Pernah Ada (BL-066)` |
| `BL-067` | Isi paket — berapa seat, berapa kuota AI — tidak terlihat sebelum orang mendaftar | 2026-08-14 (butir (a)–(e)) | `[ADDITION] Isi Paket Terlihat Sebelum Orang Mendaftar, dan Berhenti Terpecah Dua di Dalam Aplikasi (BL-067)` |
| `BL-057` | Harga khusus per tenant: alasan wajib pada penerbit tagihan manual | 2026-08-14 (butir (a) dan (b); butir (c) memang tidak menuntut kode) | `[ADDITION] Nominal yang Menyimpang dari Aturan Wajib Beralasan, dan Alasannya Dibaca Tenant yang Ditagih (BL-057)` |
| `BL-039` | "Profil Usaha" mencampur merek, aturan kerja, kapabilitas, dan kredensial | 2026-08-14 (seluruh butir; unggah logo struk ditahan menunggu `BL-076`) | `[REFACTOR] "Profil Usaha" Pecah Jadi Tiga Halaman dan Tiga Endpoint — Satu Tombol Simpan Tidak Lagi Menulis Merek, Tarif, Modul, dan Kunci API Sekaligus (BL-039)` |
| `BL-038` | Halaman staf tidak menunjukkan modul efektif per orang | 2026-08-14 (seluruh butir + baris owner) | `[ADDITION] Halaman Staf Menjawab "Orang Ini Bisa Buka Apa Saja", dan Baris Owner Mengaku Melewati Seluruh Pemeriksaan (BL-038)` |
| `BL-033` | Tiga permukaan publik belum satu keluarga — desain, aset, dan wordmark | 2026-08-14 | `[DECISION] Tiga Permukaan Publik Jadi Satu Keluarga: `SAPI POS` Resmi, Palet Tunggal, dan Tailwind CDN Dilepas (BL-033)` |
| `BL-047` | Kuota AI gratis terkunci di `.env` — bukan kebijakan yang bisa diatur pemilik SaaS | 2026-08-01 (butir (b)), 2026-08-13 (butir (a), (c), (d)) | `[ADDITION] Kuota AI Berhenti Tinggal di `.env`: Kebijakan Berjangka Waktu, Promo, dan Tombol Mengembalikan Jatah Hari Ini (BL-047)` |
| `BL-064` | Satu-satunya grafik ada di dashboard — laporan tidak punya grafik sama sekali | 2026-08-13 (butir (a),(b),(d) + butir (c) bagian rekap bulanan) | `[ADDITION] Grafik Kedua Aplikasi Ini: Garis Tren di Rekap Bulanan (BL-064)` |
| `BL-062` | Sisa kuota AI hanya terlihat di Pengaturan — bukan di halaman yang menghabiskannya | 2026-08-13 (butir (a)–(d); butir (e) tidak dikerjakan, perlu diperjelas lebih dulu) | `[ADDITION] Sisa Kuota AI Pindah ke Halaman yang Membelanjakannya, dan Penolakannya Pindah dari Antrean ke Layar (BL-062)` |
| `BL-063` | Laporan hanya ada per satu tanggal — belum ada rekap bulanan | 2026-08-13 (butir (a)–(d)) | `[ADDITION] Laporan Bulanan: Satu Bulan Kalender, Diagregasi di Basis Data, dengan Unduhan CSV (BL-063)` |
| `BL-048` | Jalur Harga Adaptif bisa dipilih siapa saja — belum ada pagar kelayakan, dan pagarnya menabrak gerbang privasi | 2026-08-10 (butir (a) dibatalkan 2026-08-07; butir (b) lewat `BL-055`) | `[ADDITION] Pengajuan Harga Adaptif Punya Halaman, Dinilai Seketika, dan Berujung pada Ambang (BL-055)` |
| `BL-055` | Pengajuan Harga Adaptif belum punya wujud — halaman, penilaian otomatis, dan pemindahan saat melewati ambang | 2026-08-10 (butir (a)–(f); butir (g) dipecah ke `BL-073`) | `[ADDITION] Pengajuan Harga Adaptif Punya Halaman, Dinilai Seketika, dan Berujung pada Ambang (BL-055)` |
| `BL-049` | "Tambah pengguna" menerbitkan tagihan Rp 0 dan meminta bukti transfernya, dan kursi tidak pernah bisa turun | 2026-08-10 (butir (b)+(c) 2026-08-07; butir (a)+(d) tertutup oleh `BL-041`(a) dan `BL-053`, tanpa kode baru) | `[HOTFIX] Tagihan Rp 0 Berhenti Meminta Bukti Transfer Nol Rupiah (BL-049 butir b & c)` + `[ADDITION] Seat Tambahan Jadi Komponen Bulanan, dan Untuk Pertama Kalinya Bisa Dilepas (BL-053)` |
| `BL-053` | Seat tambahan dan kuota AI masih biaya sekali bayar — keputusan pemilik menyebutnya bulanan | 2026-08-08 (butir (a)+(b); butir (c) kuota AI dipecah ke `BL-069`) | `[ADDITION] Seat Tambahan Jadi Komponen Bulanan, dan Untuk Pertama Kalinya Bisa Dilepas (BL-053)` |
| `BL-050` | Satu tenant hanya bisa menambah pengguna sekali per bulan kalender | 2026-08-08 (tertutup oleh `BL-053`, tanpa perubahan skema) | `[ADDITION] Seat Tambahan Jadi Komponen Bulanan, dan Untuk Pertama Kalinya Bisa Dilepas (BL-053)` |
| `BL-054` | Masa tenggang belum bertingkat — kasir mati sejak hari pertama, padahal keputusan barunya tiga tahap | 2026-08-08 (butir (c) sebagian; pemadam kedua terpasang 2026-08-10 lewat `BL-055`) | `[ADDITION] Masa Tenggang Jadi Tangga Tiga Tahap — Kasir Berhenti Mati di Hari Pertama (BL-054)` |
| `BL-052` | Masa gratis habis tanpa perpindahan ke `paid-1` — tenant bertarif Rp 0 tidak pernah ditagih, ia jatuh ke tenggang | 2026-08-08 (butir (d) 2026-08-07) | `[ADDITION] Masa Gratis Berakhir dengan Perpindahan, Bukan dengan Jatuh ke Tenggang (BL-052)` |
| `BL-058` | Tagihan upgrade seat menelan tagihan langganan di bulan yang sama | 2026-08-07 | `[HOTFIX] Penjaga Periode-Ganda Menyaring kind, dan Tenant yang Dilewati Berhenti Menghilang dari Hitungan (BL-058)` |
| `BL-059` | Belum ada halaman bayar — tagihan hanya bisa lunas lewat bukti transfer manual atau tombol peragaan satu klik | 2026-08-07 (butir (i) ditunda jadi `BL-061`) | `[ADDITION] Halaman Bayar Berdiri di Atas Gateway Tiruan yang Bicara Seperti Gateway Sungguhan (BL-059)` |
| `BL-046` | Paket kedua tidak punya tempat berpijak — CRUD-nya ada, pembacanya tidak | 2026-08-01 (butir 1), 2026-08-06 (butir 2) | `[ADDITION] Aturan Tarif Bisa Disunting & Dihentikan…`, `[ADDITION] Paket Kedua Akhirnya Bisa Dihuni: Tenant Bisa Dipindahkan, Seat Bayarnya Ikut (BL-046)` |
| `BL-045` | Keadaan hanya-baca tak terlihat, dan membayar belum memulihkan akses | 2026-08-06 | `[ADDITION] Keadaan Langganan Terlihat di Setiap Layar, & Satu Pintu Menuju Aktif (BL-045)` |
| `BL-043` | Login platform berhasil, lalu mendarat di area tenant | 2026-08-05 | `[HOTFIX] Pengalihan Setelah Masuk Memilah Dua Dunia, Bukan Cuma Sebelum Masuk (BL-043)` |
| `BL-030` | Tanggal jatuh tempo langganan ikut meluber di bulan pendek | 2026-08-05 | `[DECISION] Tanggal Tagih Jadi Jangkar: Bulan Pendek Menjepit Sementara, Tidak Menggeser Selamanya (BL-030)` |
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
