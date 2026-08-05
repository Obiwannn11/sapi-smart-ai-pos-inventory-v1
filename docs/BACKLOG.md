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
> Urutan yang disarankan: ~~`[BL-043]` (cacat, berdiri sendiri, kecil)~~ → ~~`[BL-030]` (mumpung `invoices` masih kosong)~~ → `[BL-041]`(a) menetapkan angka → `[BL-044]` → `[BL-046]` → `[BL-047]` → `[BL-045]`. Yang dicoret selesai 2026-08-05.

> **Keputusan pemilik 2026-08-01 (struktur tarif)** — Rp 100k ditetapkan sebagai **puncak bracket Harga Adaptif**, sejajar dengan harga Premium; tenant beromset tinggi tidak lagi berhak atas Adaptif dan hanya bisa Premium; kelayakan Adaptif ditentukan pemilik SaaS. Rinciannya di blok "Keputusan pemilik" pada `[BL-041]`. Dampaknya menyebar ke tiga entri: `[BL-044]` (pertanyaan tarifnya terjawab), `[BL-046]` (Premium dapat pembeda kedua, dan jalur pindah paket berubah dari nyaman jadi wajib), dan satu entri baru **`[BL-048]`** — pagar kelayakan jalur Adaptif, yang ternyata **menabrak gerbang privasi**: menilai kelayakan masuk butuh data omset yang baru boleh dikumpulkan setelah masuk. Baca `[BL-048]` sebelum menyentuh `canSwitchTrack()`.
>
> **Keputusan pemilik 2026-08-01 (kedua)** — batas Adaptif diturunkan dari tangga harga Premium, bukan disetel sebagai angka tersendiri; Premium kelak bisa tiga tier dengan puncak Adaptif mendarat di tengahnya. Dua akibat yang menghemat pekerjaan: batas itu **sudah bisa dinyatakan tanpa kolom atau migrasi baru** — cukup satu baris syarat `lt` pada bracket D yang hari ini tidak punya batas atas (`[BL-048]`(b)) — dan permintaan soal harga seat tambahan (**tidak nullable, wajib ditulis walau 0**) ternyata **sudah dipenuhi kode hari ini**, hanya prefill formulirnya yang belum ada (`[BL-046]`).

### [BL-044] Trial Habis Tanpa Ada yang Menerbitkan Tagihan — Bulan Kedua Tidak Pernah Menagih
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik — "tambahan status jika akun masih gratis, untuk bulan pertama tetapkan full gratis, tapi jika sudah masuk bulan kedua wajib melakukan ajukan subsidi atau kena tagihan biaya normal yaitu 100 k"
- **Status:** Open — **butir (b) SELESAI 2026-08-06**; sisa butir (c), terhalang `[BL-048]`
- **Prioritas:** Medium (turun dari High: tagihan sudah terbit sendiri, yang tersisa adalah momen pilihan jalurnya)
- **Area Terdampak:**
  - `config/subscription.php:26` — `trial_days = 30`; `:28` — `grace_days = 30`
  - `app/Services/SubscriptionService.php:81-96` — `startTrial()`: `current_period_end = trial_ends_at`
  - `app/Services/SubscriptionService.php:333-384` — `advanceLifecycle()`: memindahkan **status**, tidak menerbitkan tagihan apa pun
  - `app/Console/Commands/AdvanceSubscriptionLifecycle.php` — satu-satunya perintah terjadwal untuk langganan
  - `app/Http/Controllers/Platform/InvoiceController.php:136` — **satu-satunya** `Invoice::create` untuk tagihan bulanan, dipicu manual oleh pemilik SaaS
  - `app/Services/SubscriptionService.php:220` — `Invoice::create` yang kedua, khusus `KIND_UPGRADE` (tambah seat), bukan tagihan periode
- **Deskripsi:**
  Yang diminta catatan sudah **separuh** ada. "Bulan pertama gratis" sudah benar apa adanya: `trial_days = 30` dan tenant baru lahir di `STATUS_TRIAL` (`Tenant.php:75`). Yang tidak ada adalah bulan keduanya. `advanceLifecycle()` memindahkan tenant `trial → grace` begitu periodenya lewat, dan `grace → suspended` 30 hari kemudian — tapi di sepanjang jalan itu **tidak ada satu pun tagihan yang terbit**. Tenant tidak pernah diberi tahu berapa yang harus dibayar; ia hanya menemukan aplikasinya berubah jadi hanya-baca. Satu-satunya jalan tagihan bulanan lahir hari ini adalah pemilik SaaS mengetiknya sendiri di `/platform/invoices` untuk tiap tenant, tiap bulan.
  Konsekuensi kedua: catatan menyebut "wajib ajukan subsidi **atau** kena tagihan normal", yang mengandaikan tenant pernah **ditanya**. Hari ini tidak ada percabangan itu. Jalur harga sudah dipilih saat pendaftaran (selalu `normal`, `SubscriptionService.php:88`) dan pindah jalur hanya terjadi bila tenant sendiri membuka halaman Langganan dan menyetujui dokumen subsidi — tidak ada momen di akhir trial yang menyodorkan dua pilihan itu.
- **Keadaan data per 2026-08-01 (query, bukan dugaan):** 2 tenant, `invoices` **kosong**, paket satu-satunya `Dasar` dengan `base_price = 0.00`. Jadi bahkan bila penerbit otomatis dipasang hari ini, nominal yang terbit adalah nol.
- **Usulan Perbaikan:**
  Berurutan. **(a)** ~~Tetapkan angkanya lebih dulu~~ — **terjawab 2026-08-01: Rp 100k adalah puncak bracket Harga Adaptif, bukan tarif dasar Harga Tetap.** Akibatnya untuk entri ini: kalimat catatan "wajib ajukan subsidi **atau** kena tagihan normal" ternyata **bukan dua pilihan bebas**. Jalur Adaptif kini berpagar kelayakan (`[BL-048]`), jadi yang disodorkan di akhir trial berbeda per tenant — tenant yang layak melihat dua pilihan, yang tidak layak hanya melihat satu. Butir (c) di bawah harus dibaca ulang dengan itu. Angka jalur Harga Tetap/Premium sendiri masih menunggu `[BL-041]`(a). **(b)** Perluas `advanceLifecycle()` (atau perintah terjadwal berdampingan) agar menerbitkan `Invoice` `KIND_SUBSCRIPTION` untuk periode berikutnya **sebelum** memindahkan tenant ke `grace`, memakai `PricingService::resolveFor()` yang sudah dipakai `InvoiceController` — supaya tagihan otomatis dan tagihan manual lahir dari resolver yang sama, bukan dua perhitungan yang akan bercabang. Jaga idempotensinya: `InvoiceController:114-118` sudah menolak periode ganda, dan penerbit otomatis harus tunduk pada penjaga yang sama. **(c)** Baru setelah itu tambahkan momen pilihan di akhir trial (mis. peringatan H-7 di dashboard yang menawarkan dua jalur), karena tanpa (b) pilihan itu tidak bermuara ke tagihan apa pun.
  ~~**Kerjakan `[BL-030]` sebelum (b).**~~ — **selesai 2026-08-05, jendelanya terpakai.**
- **Pemutakhiran 2026-08-06 — butir (b) SELESAI.** Lihat entri CHANGELOG *"Tagihan Periode Terbit Sendiri Sebelum Aksesnya Menyempit (BL-044 butir b)"*. Yang sekarang berjalan:
  - `SubscriptionService::issueDuePeriodInvoices()` — dipanggil `advanceLifecycle()` **sebelum** perpindahan status, jadi urutannya tidak bersandar pada dua baris jadwal yang kebetulan berurutan. Tagihan terbit `invoice_lead_days` (7) hari sebelum periode habis, jatuh tempo di hari periodenya habis.
  - Nominalnya dari `PricingService::resolveFor()`, dan `pricingAsOf()` pindah ke service supaya penerbit otomatis & manual tidak bisa memakai titik waktu penetapan harga yang berbeda. Penjaga periode-ganda yang sama dipakai ulang, jadi tagihan yang sudah diketik pemilik SaaS tidak pernah ditimpa.
  - **Keputusan pemilik 2026-08-06:** tenant yang tak bisa ditagih **tetap** menempuh masa tenggang dan penangguhan. Tarif Rp 0 dilaporkan sebagai peringatan perintah; tarif `null` (Adaptif tanpa bracket & tanpa paket penampung) dicatat sebagai kejadian sensitif `invoices.unpriced`.
  - Keadaan nyata per 2026-08-06 (query): kedua tenant berperiode `2026-08-24`, jadi penerbitan pertamanya jatuh **2026-08-17**. `Kopi Nusantara` (Adaptif, jatuh ke paket penampung `Premium 1`) akan ditagih **Rp 100.000**; `Kopi Story` (Harga Tetap, paket `Dasar`) resolve ke **Rp 0** dan tidak akan ditagih apa pun — ia akan turun ke masa tenggang 2026-08-25. Itu bukan cacat kode, itu `[BL-041]`(a) yang belum diputuskan, dan sekarang angkanya terlihat di keluaran perintah.
  - **Sisa yang belum: butir (c).** Ditunda atas keputusan pemilik 2026-08-06 karena menawarkan dua jalur menuntut pagar kelayakan `[BL-048]` yang belum ada — menyodorkan pilihan yang sistem belum bisa tolak persis yang keputusan 2026-08-01 tutup. Kartu langganan di dashboard (`[BL-040]`) sudah menampilkan tagihan terbuka, jadi tenant tetap tahu berapa yang harus dibayar.
  - **Yang perlu ditinjau ulang saat (c) atau tunggakan disentuh:** `renewPeriod()` memakai aturan "tunggakan tidak ditumpuk" (`[BL-030]`) — satu pembayaran memulihkan satu periode ke depan. Sekarang tiap periode punya tagihannya sendiri, jadi memajukan periode melewati bulan yang terlewat berarti melewati tagihannya juga.

### [BL-045] Keadaan Hanya-Baca Sudah Ditegakkan tapi Tak Terlihat, dan Membayar Belum Memulihkan Akses Sendiri
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik — "buat peringatan dalam dashboard owner jika misal sudah harus membayar atau melewati waktunya ... tidak bisa transaksi atau melakukan apa pun selain read saja ... bisa melakukan aksi di bagian pembayaran ... maka akan refresh web dan memperbarui akses dari read only menjadi normal kembali"
- **Status:** Open — **sebagian sudah ada**
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Http/Middleware/EnsureSubscriptionActive.php:62-68` — penegakan hanya-baca **sudah ada**: `grace` + method tidak aman → ditolak
  - `app/Http/Middleware/EnsureSubscriptionActive.php:54-60` — `suspended` → dialihkan ke halaman langganan
  - `resources/js/Pages/Owner/Dashboard.vue:66-94,136-182` — kartu ringkasan langganan **sudah ada** (hasil `[BL-040]`, arsip 2026-07-31)
  - `app/Http/Controllers/Platform/InvoiceController.php:173+` — `verify()`: satu-satunya tempat tenant kembali `active`, dijalankan **pemilik SaaS**
  - `routes/web.php:53-75` — grup `billing.*`: owner mengunggah bukti bayar, tidak melunasi apa pun sendiri
- **Deskripsi:**
  Tiga dari empat hal yang diminta catatan ini **sudah benar-benar berjalan** dan tidak perlu dibangun ulang: data tidak dihapus, tulisan ditolak sementara baca tetap terbuka, dan halaman langganan/tagihan selalu bisa dibuka lewat `ALWAYS_ALLOWED` (`EnsureSubscriptionActive.php:31-44`). Dashboard owner pun sudah memajang keadaan langganan beserta tagihan terbuka.
  Dua hal yang benar-benar belum ada. **(1) Peringatannya hanya di satu layar.** Kartu itu hidup di Dashboard; kasir yang membuka POS langsung, atau owner yang seharian di halaman Produk, tidak melihat apa-apa sampai ia menekan Simpan dan mendapat `back()->with('error', ...)`. Penolakannya benar, tapi kejutan. **(2) Membayar tidak memulihkan akses sendiri.** Alurnya hari ini: owner unggah bukti → tagihan jadi `awaiting_verification` → **pemilik SaaS memeriksa manual** → `verify()` → tenant kembali `active`. Tidak ada jalur yang membuat akses pulih segera setelah tenant membayar, jadi "refresh lalu normal kembali" yang diminta catatan belum ada wujudnya.
- **Usulan Perbaikan:**
  **(1)** Naikkan keadaan langganan dari prop halaman Dashboard menjadi prop bersama di `HandleInertiaRequests`, lalu render satu pita peringatan di layout owner **dan** kasir bila `status` ∈ {`grace`, `suspended`}. Isinya menyebut tanggal penangguhan (`suspensionDateFor()` sudah menghitungnya) dan menaut ke halaman Langganan. Peringatan sebelum tombol ditekan jauh lebih murah daripada penolakan sesudahnya.
  **(2)** Catatan meminta simulasi ("buatkan saja ala ala kalau membayar"). Simulasi itu **hanya boleh untuk demo, dengan gerbang yang tegas** — mis. hanya aktif di lingkungan non-produksi, atau lewat penanda tenant demo — karena jalur yang melunasi tagihan tanpa bukti adalah persis lubang yang `provisional_blocked` (`SubscriptionService.php:283-314`) dibangun untuk menutup. Jangan menambah aksi baru: pakai kembali `InvoiceController::verify()` sebagai satu-satunya tempat tenant kembali `active`, dan biarkan tombol simulasi memanggilnya. Satu jalan masuk ke keadaan `active` lebih mudah dipertanggungjawabkan daripada dua.
  Untuk produksi, yang sebenarnya dibutuhkan adalah payment gateway; catat itu sebagai keputusan terpisah, bukan diselundupkan lewat tombol simulasi.

### [BL-046] Paket Kedua Tidak Punya Tempat Berpijak — CRUD-nya Ada, Pembacanya Tidak
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik — "nanti ada 2 paket, 1 dasar dengan golongan gratis, dan ada premium, bedanya adalah bisa menaikkan setup limit, dari 5 perhari jadi 10 per hari"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `database/migrations/2026_07_24_181634_create_plans_table.php:25-32` — kolom paket: `base_price`, `included_seats`, `extra_seat_price`, `is_active`. **Tidak ada kolom batas apa pun selain seat.**
  - `app/Http/Controllers/Platform/PricingRuleController.php:180-197` (`storePlan`) & `:150-178` (`updatePlan`) — paket **sudah bisa** dibuat & disunting dari `/platform/pricing-rules`
  - `app/Services/SubscriptionService.php:83-87` — `startTrial()` **selalu** `Plan::default()` (slug `dasar`)
  - Seluruh `app/`: `plan_id` hanya disebut di `Subscription.php:28` (`$fillable`) dan `SubscriptionService.php:87` — **tidak ada satu pun jalur yang memindahkan tenant antar paket**
  - `app/Models/Subscription.php` — `hasSeatAvailable()`; `SubscriptionService.php:110-128` — satu-satunya batas yang benar-benar dibaca dari paket
- **Deskripsi:**
  Wadahnya sudah berdiri lebih jauh dari dugaan: tabel `plans` ada, dan pemilik SaaS **sudah bisa** membuat paket "Premium" hari ini dari halaman Aturan Harga. Yang belum ada tiga, dan tanpa ketiganya paket baru itu hanya baris data yang tak berakibat apa-apa:
  **(1) Tidak ada kolom untuk perbedaannya.** Satu-satunya atribut paket yang mengubah perilaku aplikasi adalah `included_seats`/`extra_seat_price`. Batas yang disebut catatan — kuota AI 5/hari → 10/hari — tidak punya kolom, dan pembacanya (`RunAiAnalysisJob.php:92`) membaca config, bukan paket. Lihat `[BL-047]`.
  **(2) Tidak ada jalan pindah paket.** `plan_id` ditulis sekali seumur hidup langganan, saat trial dibuka. Tidak ada aksi owner ("naikkan ke Premium") maupun aksi platform ("pindahkan tenant ini") yang mengubahnya. `UpgradeController` yang sudah ada hanya menambah **seat**, bukan mengganti paket.
  **(3) Belum diputuskan apa lagi bedanya.** Catatan menyebut satu pembeda. Paket dengan satu pembeda tunggal biasanya berumur pendek — pertanyaan berikutnya ("Premium dapat antrian dapur? pesan mandiri?") akan datang, dan `hasFeature()` (`Tenant.php:94-102`) hari ini membaca kolom per-tenant yang bisa dinyalakan owner sendiri dari Pengaturan, bukan dari paket. Bila kapabilitas kelak ikut jadi pembeda paket, dua sumber kebenaran itu harus didamaikan lebih dulu — jangan sampai owner bisa menyalakan sendiri fitur yang seharusnya berbayar.
- **Usulan Perbaikan:**
  **(a)** Putuskan daftar pembedanya (butir 3) — keputusan bisnis, dan menentukan bentuk kolomnya. **(b)** Tambahkan batas paket sebagai **satu kolom JSON `limits`** pada `plans`, bukan satu kolom per batas: batas berikutnya pasti menyusul, dan tiap batas baru yang berarti satu migrasi akan mengundang orang membuat `if` di kode sebagai gantinya. **(c)** Baca batasnya lewat satu titik (mis. `Subscription::limit('ai_daily')` yang jatuh ke bawaan config bila paket tidak menyetelnya) supaya penambahan batas tidak menyentuh kelas pembacanya. **(d)** Terakhir, jalur pindah paket: aksi platform lebih dulu (pemilik SaaS memindahkan tenant, tercatat di audit log) karena itu yang paling sedikit permukaannya; permintaan naik paket mandiri oleh owner bisa menyusul memakai pola `Invoice` `KIND_UPGRADE` yang sudah ada.
  **Bergantung pada `[BL-041]`(a)** untuk harga Premium, dan mendahului `[BL-047]` butir (c).
- **Pemutakhiran 2026-08-01 — Premium naik pangkat, butir (2) jadi yang terpenting.** Keputusan pemilik (lihat blok di `[BL-041]`) memberi Premium pembeda kedua yang jauh lebih berat daripada kuota AI: **Premium adalah satu-satunya jalur bagi tenant beromset tinggi**, yang tidak lagi berhak atas Harga Adaptif. Tiga akibatnya untuk entri ini:
  1. Kekhawatiran di butir (3) — "paket dengan satu pembeda tunggal biasanya berumur pendek" — **terjawab**: pembedanya bukan lagi 5→10 permintaan AI per hari, melainkan jalur harga yang boleh ditempuh. Kuota AI turun jadi pemanis.
  2. **Butir (2) berubah dari nyaman jadi wajib.** Selama `plan_id` hanya ditulis sekali saat trial, tidak ada cara memindahkan tenant beromset tinggi ke Premium sama sekali — dan keputusan pemilik menjadi tidak bisa ditegakkan. Kerjakan usulan (d) lebih awal, jangan terakhir.
  3. Urutan yang disarankan di butir (d) — **aksi platform lebih dulu** — sekarang bukan sekadar "permukaan paling sedikit": pemindahan ke Premium karena omset adalah keputusan pemilik SaaS, bukan permintaan owner toko. Aksi mandiri owner boleh menyusul, tapi tidak boleh jadi satu-satunya jalan.
  Pintu keluar dari jalur Adaptif ke Premium dirinci di **`[BL-048]`**(b), yang bergantung pada entri ini.
- **Pemutakhiran 2026-08-01 (kedua) — harga seat tambahan: yang diminta sudah berlaku.** Permintaan pemilik ("tidak nullable, mau diisi 0 pun tetap valid asal wajib ditulis 0 sendiri") **sudah dipenuhi kode hari ini**, jadi tidak ada pekerjaan di situ:
  - `create_plans_table.php:29` — `decimal('extra_seat_price', 12, 2)->default(0)`, **bukan** `nullable()`
  - `PricingRuleController.php:187` (`storePlan`) dan `:150` (`updatePlan`) — keduanya `['required', 'numeric', 'min:0']`. `required` menolak field kosong, sementara `min:0` menerima `0` yang diketik — persis "wajib ditulis sendiri, 0 tetap sah"
  Yang benar-benar belum ada tinggal satu, dan lingkupnya jauh lebih kecil daripada kelihatannya: **nilai bawaan yang bisa diatur dari dashboard**. Karena `storePlan` sudah mewajibkan fieldnya, "default" di sini **hanya mengisi awal formulir** — ia tidak pernah menjadi nilai yang tersimpan diam-diam tanpa dilihat orang. Kerjakan sebagai prefill formulir saja; jangan jadikan kolom yang ikut dibaca saat menghitung tagihan, karena itu akan melahirkan sumber kebenaran kedua di samping `plans.extra_seat_price` — dan begitu keduanya berselisih, yang menang adalah yang tidak pernah dilihat siapa pun.
  Catatan `->default(0)` di migrasi tetap relevan untuk baris yang lahir di luar formulir (factory, seeder, `Plan::create()` langsung). Itu wajar dan tidak perlu diubah.
- **Pemutakhiran 2026-08-01 (ketiga) — butir (1) SELESAI, butir (2) tetap terbuka dan kini satu-satunya penghalang.** Lihat entri CHANGELOG *"Aturan Tarif Bisa Disunting & Dihentikan, Paket Punya Batas AI dan Peran Penampung"*. Yang sudah dikerjakan persis seperti usulan (b) dan (c):
  - `plans.limits` — **satu kolom JSON**, bukan satu kolom per batas. Kunci pertamanya `ai_daily`.
  - `Plan::limit()` / `setLimit()` — satu-satunya pintu ke kolom itu; batas berikutnya menambah pemanggil, bukan kolom.
  - `App\Services\Ai\AiQuota` — pembaca tunggal, dipakai `RunAiAnalysisJob` dan `SettingsController` sekaligus (usulan (c) di `[BL-047]`(b)).
  - `plans.is_adaptive_fallback` — paket penampung jalur Adaptif, ditunjuk dari panel dan tunggal (`Plan::setAdaptiveFallback()`).
  Butir (3) juga sudah terjawab sejak pemutakhiran pertama. **Yang tersisa hanyalah butir (2): tidak ada jalur pindah paket.** `plan_id` masih ditulis sekali seumur hidup langganan di `SubscriptionService::startTrial()`. Akibatnya, paket penampung yang kini ada baru menentukan **tarif** tenant adaptif tanpa aturan yang cocok — ia belum benar-benar **memindahkan** tenant itu ke Premium, dan batas seat serta kuota AI-nya masih mengikuti paket lamanya. Kerjakan usulan (d): aksi platform lebih dulu, tercatat di `PlatformAuditLog`.

### [BL-047] Kuota AI Gratis Terkunci di `.env` — Bukan Kebijakan yang Bisa Diatur Pemilik SaaS
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik — "atur kuota gratis umum ... apakah per hari, di reset semua, atau ada promo dalam waktu tertentu ... jadi sudah tidak statis hard coded 5 request per hari, bisa di atur di platform account"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `config/ai.php:15` — `'daily_limit' => env('AI_FREE_TIER_DAILY_LIMIT', 5)`
  - `app/Jobs/RunAiAnalysisJob.php:86-95` — `assertQuota()`: satu-satunya penegakan, membaca config
  - `app/Jobs/RunAiAnalysisJob.php:97-105` — `incrementUsage()`: satu baris `ai_usages` per (tenant, tanggal)
  - `app/Http/Controllers/Owner/SettingsController.php:25,78-81` — angka yang sama dibacakan ke owner sebagai `daily_limit`/`remaining`
  - `app/Services/Ai/AiProviderFactory.php:16` — kuota hanya berlaku saat tenant memakai kunci bersama; kunci sendiri = tanpa batas
  - Platform console: **tidak ada** halaman apa pun yang menyentuh kuota AI (`php artisan route:list --path=platform`)
- **Deskripsi:**
  Catatan ini akurat, dengan satu koreksi istilah: angkanya bukan *hard-coded* melainkan *env-coded* — sudah lewat `config()`, jadi bisa diubah tanpa menyunting kelas, tapi tetap menuntut akses server dan `config:clear`. Bagi pemilik SaaS yang duduk di panel, jaraknya sama saja dengan hard-coded.
  Yang membuatnya lebih dari sekadar "pindahkan ke tabel": angka itu hari ini adalah **satu angka untuk semua** dan **hanya berbentuk harian**. Ketiga bentuk yang disebut catatan tidak muat di dalamnya — kuota per paket (`[BL-046]`), reset serentak, dan promo berjangka waktu masing-masing menuntut yang berbeda. Reset serentak butuh cara membatalkan hitungan berjalan (`ai_usages` disimpan per tanggal, jadi "reset semua hari ini" = menghapus baris tanggal itu, bukan menyetel ulang sebuah angka). Promo berjangka butuh masa berlaku, dan tanpa `effective_from`/`effective_until` ia akan berakhir sebagai angka yang lupa dikembalikan.
  Catatan juga menyentuh "api key ai bisa di setup gratisan": itu **sudah** berjalan — `AiProviderFactory:16` memakai kunci bersama bila tenant tidak mengisi kuncinya sendiri, dan kuota inilah yang menjaga tagihan kunci bersama itu.
- **Usulan Perbaikan:**
  **(a)** Pindahkan kebijakannya ke data dengan bentuk yang sama seperti `pricing_rules` — berlaku sejak kapan sampai kapan — bukan satu kolom pengaturan tunggal. Pola yang sudah terbukti di repo ini: `pricing_rules` + `effective_from`, dengan `PricingService` sebagai satu-satunya pembaca. Promo berjangka jadi baris biasa yang kedaluwarsa sendiri, bukan angka yang harus diingat untuk dikembalikan.
  **(b)** Urutan pembacaan yang jelas dan tunggal: kuota paket (`[BL-046]`) → kebijakan/promo berlaku → bawaan `config/ai.php`. Tulis di satu kelas, jangan disebar; `assertQuota()` dan `SettingsController` harus memanggil kelas yang sama, karena angka yang dibacakan ke owner dan angka yang menolak permintaannya wajib identik.
  **(c)** Halaman platform untuk mengaturnya, digerbang modul sendiri lewat `platform.can:` seperti modul lain, dan perubahannya tercatat di `PlatformAuditLog` — menaikkan kuota bersama berarti menaikkan tagihan kunci bersama, jadi jejaknya perlu ada.
  **(d)** "Reset semua" ditulis sebagai aksi tersendiri (menghapus baris `ai_usages` tanggal berjalan), bukan sebagai efek samping mengubah angka kuota. Dua hal yang berbeda artinya jangan dijadikan satu tombol.
- **Pemutakhiran 2026-08-01 — butir (b) SELESAI, dan kuota per paket sudah bisa diatur dari panel.** Lihat entri CHANGELOG *"Aturan Tarif Bisa Disunting & Dihentikan, Paket Punya Batas AI dan Peran Penampung"*. Yang berubah:
  - `App\Services\Ai\AiQuota` — **pembaca tunggal**, persis usulan (b). Urutannya hari ini: **batas paket (`plans.limits.ai_daily`) → bawaan `config/ai.php`**. `RunAiAnalysisJob::assertQuota()` dan `Owner\SettingsController` memanggil kelas yang sama.
  - Kuota per paket (`[BL-046]`(1)) sudah bisa diatur dari `/platform/pricing-rules` → **Ubah paket → "Analisis AI per hari"**. Kosong = ikut bawaan platform, `0` = paket tidak menyertakan AI.
  - Jejak audit `plans.update` kini mencatat `ai_daily_limit` sebelum & sesudah — sebagian dari usulan (c).
  **Yang tersisa: (a), sisa (c), dan (d).** Kebijakan berjangka waktu (promo, masa berlaku) belum ada bentuknya; tempatnya kelak **di antara** kedua lapis yang sudah ada di `AiQuota` — sisipkan di situ, jangan tambahkan pembaca kedua. Halaman platform khusus kuota bersama dan aksi "reset semua" juga belum ada. Bawaan platform masih di `.env`, dan bagi pemilik SaaS yang duduk di panel jaraknya masih sama seperti sebelumnya — yang berubah, ia kini bisa dilampaui per paket tanpa menyentuh server.
- **Ditemukan saat mengerjakannya (sudah diperbaiki):** `incrementUsage()` memakai `firstOrCreate` berkunci tanggal, padahal kolom `ai_usages.date` tersimpan sebagai datetime. Barisnya tak pernah ketemu, lalu penyisipan keduanya ditolak indeks unik — **analisis KEDUA seorang tenant di hari yang sama selalu gagal**, padahal jatahnya masih ada. Diperbaiki dengan `whereDate`, dengan test yang gagal sebelum perbaikannya.

### [BL-048] Jalur Harga Adaptif Bisa Dipilih Siapa Saja — Belum Ada Pagar Kelayakan, dan Pagarnya Menabrak Gerbang Privasi
- **Ditemukan:** 2026-08-01
- **Sumber:** Keputusan pemilik 2026-08-01 — "khusus yang memiliki omset cukup tinggi sudah hanya bisa bayar premium, tanpa subsidi atau harga adaptif lagi, adaptif khusus omset rendah atau yang saya tentukan baru bisa dapat"
- **Status:** Open — **butuh keputusan pemilik (ambang omset) sebelum bisa dikerjakan**
- **Prioritas:** High
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php:145-151` — `canSwitchTrack()`: **satu-satunya** penjaga perpindahan jalur, dan ia hanya memeriksa **jarak waktu** (`track_switch_minimum_months`), bukan kelayakan apa pun
  - `app/Http/Controllers/Billing/ConsentController.php:84,129` — owner menyetujui dokumen subsidi → `switchToSubsidized()` langsung dipanggil; tidak ada pemeriksaan lain di antaranya
  - `app/Services/SubscriptionService.php:171-182` — `switchToSubsidized()`: menulis jalur, tanpa syarat
  - `app/Jobs/ComputeTenantMonthlyRevenue.php:43-54` — **gerbang privasi dua lapis**: omset HANYA dihitung untuk tenant yang sudah di jalur `subsidized` **dan** consent-nya masih berlaku
  - `app/Services/Pricing/DimensionRegistry.php:68-80` — dimensi ber-`requires_consent` padam sendiri untuk tenant tanpa persetujuan
  - `app/Http/Controllers/Billing/SubscriptionController.php:68` — `bracket` hanya diisi bila `isSubsidized()`
- **Deskripsi:**
  Hari ini **setiap** tenant bisa memilih jalur Harga Adaptif kapan pun ia mau. Yang berdiri di depannya cuma dua hal: dokumen persetujuan yang harus dibaca, dan jarak 3 bulan dari perpindahan sebelumnya. Tidak ada apa pun yang menanyakan berapa omsetnya. Warung beromset besar bisa menyeberang ke Adaptif hari ini juga — dan justru itu yang keputusan 2026-08-01 tutup.
  **Masalah sebenarnya bukan menambah `if`.** Untuk menolak tenant karena omsetnya terlalu tinggi, sistem harus **tahu** omsetnya. Tapi omset hanya dihitung untuk tenant yang **sudah** di jalur Adaptif dengan consent aktif (`ComputeTenantMonthlyRevenue:43-54`) — gerbang privasi yang dibangun dengan sengaja dan didokumentasikan di dokumen consent jalur normal: jalur Harga Tetap **tidak membuka data penjualan sama sekali**. Jadi:
  > Untuk menilai kelayakan masuk, dibutuhkan data yang baru boleh dikumpulkan setelah masuk.
  Ini melingkar, dan tidak bisa dipecahkan dengan diam-diam menghitung omset semua tenant — itu membatalkan janji tertulis di `resources/consents/normal-v1.md` dan berlaku surut bagi tenant yang sudah menyetujuinya.
  Arah sebaliknya **tidak** bermasalah dan sudah punya datanya: tenant yang sudah di jalur Adaptif lalu tumbuh melewati ambang sudah terlihat omsetnya (ia consent), jadi memindahkannya keluar ke Premium bisa dikerjakan tanpa menyentuh privasi siapa pun.
- **Usulan Perbaikan:**
  Frasa **"atau yang saya tentukan"** di catatan pemilik ternyata bukan pelengkap — itu jalan keluar dari lingkaran di atas, dan sebaiknya jadi **mekanisme utamanya**, bukan cadangan:
  **(a) Kelayakan sebagai pemberian, bukan perhitungan.** Tambahkan penanda kelayakan per tenant (mis. `subsidy_eligible_at` + alasan) yang **hanya** bisa disetel pemilik SaaS dari platform console, tercatat di `PlatformAuditLog`. `canSwitchTrack()` memeriksanya sebagai syarat tambahan. Tidak ada data omset yang perlu dikumpulkan lebih dulu, tidak ada janji consent yang dilanggar, dan pemilik SaaS tetap memegang kendali penuh yang ia minta. Ini juga cocok dengan kenyataan lapangan: keringanan untuk UMKM biasanya diberikan setelah dilihat kasusnya, bukan dihitung mesin.
  **(b) Ambang omset sebagai pintu keluar, bukan pintu masuk.** Untuk tenant yang **sudah** di jalur Adaptif, datanya ada. Tandai tenant yang melewati ambang untuk dipindahkan ke Premium pada periode berikutnya — dengan **pemberitahuan lebih dulu**, bukan kejutan di tagihan. Jangan pindahkan di tengah periode: `price_locked` dan `invoices.pricing_context` dibangun supaya harga yang sedang berjalan bisa dipertanggungjawabkan.
  **Ambangnya TIDAK perlu jadi angka baru — cukup satu baris syarat.** Keputusan pemilik 2026-08-01 (kedua) meminta batas Adaptif diturunkan dari harga Premium, bukan disetel terpisah. Ternyata model datanya sudah bisa menyatakan itu apa adanya. Keadaan seeded hari ini (query 2026-08-01):
  | Bracket | Syarat | Harga |
  |---|---|---|
  | A | `monthly_revenue >= 0` **dan** `< 2.000.000` | 10.000 |
  | B | `>= 2.000.000` **dan** `< 5.000.000` | 25.000 |
  | C | `>= 5.000.000` **dan** `< 15.000.000` | 50.000 |
  | D | `>= 15.000.000` — **tanpa batas atas** | 100.000 |
  Bracket D adalah satu-satunya yang tidak punya pasangan `lt`, jadi ia menelan **setiap** tenant beromset tinggi selamanya di Rp 100k. Menutup batas Adaptif = menambahkan satu `PricingRuleCondition` `monthly_revenue lt <ambang>` pada bracket D. Tidak ada kolom baru, tidak ada migrasi, dan pemilik SaaS bisa melakukannya sendiri dari `/platform/pricing-rules`.
  Yang membuatnya cocok dengan rencana **tiga tier Premium**: Adaptif dan Premium adalah dua tangga harga yang berdampingan, bukan dua wilayah yang harus dipisah garis. Puncak Adaptif boleh sejajar dengan Premium tier ke-2 di tengah tangga — yang menentukan batasnya bukan "di mana Premium mulai", melainkan **di mana tangga Adaptif berhenti**. Menambah atau menggeser tier Premium tidak menuntut menyentuh bracket sama sekali.
  **Peringatan yang harus dipegang saat mengerjakannya:** `PricingService::resolveFor()` mengembalikan `price = null` ketika tak ada aturan yang cocok (`PricingService.php:104`), dan null itu hari ini **ambigu — tiga sebab berbeda menghasilkan nilai yang sama**: (1) omset di atas bracket teratas — yang justru sinyal yang kita inginkan; (2) ada lubang di tabel bracket karena salah setel; (3) tenant tak punya data omset sama sekali karena jalur normal, sebab `DimensionRegistry:80` memadamkan dimensi ber-consent. Memperlakukan null begitu saja sebagai "tidak layak Adaptif" berarti **satu bracket yang salah ketik akan diam-diam mendorong seluruh tenant ke Premium**. Kelayakan harus memeriksa secara eksplisit "omsetnya di atas nilai `lt` tertinggi di tabel", bukan sekadar "tidak ada yang cocok".
- **Diuji langsung 2026-08-01 — batasnya bekerja, dan ambiguitasnya nyata.** Bracket D diterbitkan ulang dari `/platform/pricing-rules` dengan syarat tambahan `monthly_revenue lt 50.000.000`, tanpa satu baris kode pun. Hasil `PricingService::bracketFor()`:
  | Omzet | Bracket | Harga |
  |---|---|---|
  | 0 / 1,5 jt | A | 10.000 |
  | 3 jt | B | 25.000 |
  | 8 jt | C | 50.000 |
  | 20 jt / 49 jt | D | 100.000 |
  | **50 jt / 75 jt / 200 jt** | **tidak ada** | **null** |
  Batas itu **tertutup rapi persis di angka yang diminta**, dan aturan lama yang tak berbatas tetap tersimpan sebagai riwayat (`unique()` per label di `matchContext` membuat yang terbaru menang). Jejak audit `pricing-rules.create` mencatat kedua syaratnya lengkap.
  **Ambiguitas null pun terbukti, bukan kekhawatiran teoretis:** `resolveFor()` atas dua tenant nyata yang keduanya di jalur `normal` mengembalikan `label = TIDAK ADA, price = null` — **sinyal yang identik** dengan tenant beromset Rp 200 jt. Di tingkat `price`, keduanya mustahil dibedakan.
  **Tapi pengujian yang sama menemukan pembedanya, dan letaknya bukan di `price`.** `resolveFor()` juga mengembalikan `context`, dan di sanalah keduanya berpisah tegas:
  - tenant jalur normal → `context['monthly_revenue'] === null` (dimensinya dipadamkan gerbang consent)
  - tenant Adaptif beromset tinggi → `context['monthly_revenue']` berisi **angka nyata** yang di atas `lt` tertinggi
  Jadi pemeriksaan kelayakan yang benar adalah: **`context['monthly_revenue'] !== null` DAN nilainya ≥ `lt` tertinggi di tabel bracket** — bukan `price === null`. Rumusan itu kebal terhadap ketiga sebab sekaligus: tenant tanpa consent tersaring oleh syarat pertama, dan lubang salah-setel di tengah tabel tidak lolos karena angkanya tidak akan ≥ `lt` tertinggi. Pakai rumusan ini, jangan yang lebih pendek.
  **(c) Halaman Langganan harus jujur soal pagarnya.** Tenant yang tidak layak jangan disodori tombol yang akan menolaknya. `SubscriptionController:62` sudah mengirim `can_switch`; perluas jadi menyertakan **alasan** (belum layak / masih dalam jarak 3 bulan / omset di atas ambang) supaya penolakannya bisa dijelaskan di layar, bukan cuma tombol yang mati.
  **Yang perlu Anda putuskan sebelum (b) bisa dikerjakan:** nilai `lt` yang menutup bracket D (turunan dari tangga harga Premium, lihat `[BL-041]`), dan apakah tenant yang terlanjur di Adaptif dengan omset di atas ambang dipindahkan atau di-*grandfather*.
  **Bergantung pada `[BL-046]`** — pintu keluar di (b) baru ada artinya setelah paket Premium benar-benar ada dan tenant bisa dipindahkan antar paket.

### [BL-049] "Tambah Pengguna" Menerbitkan Tagihan Rp 0 dan Meminta Bukti Transfernya — Harganya Belum Ada, dan Kursi Tidak Pernah Bisa Turun
- **Ditemukan:** 2026-08-01
- **Sumber:** Permintaan pemilik saat merapikan halaman Langganan — "tambah pengguna masih under development pricing-nya"
- **Status:** Open — **terhalang `[BL-046]` dan `[BL-041]`(a)**; mekanismenya sudah jadi, angkanya yang belum
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php:215-231` — `requestSeatUpgrade()`: `amount = plan->extra_seat_price × additional_seats`
  - `app/Services/SubscriptionService.php:75-90` — `startTrial()`: **setiap** tenant lahir di paket `dasar`
  - `app/Http/Controllers/Billing/UpgradeController.php:44-76` — `storeProof()`: bukti transfer wajib sebelum seat berlaku
  - `resources/js/Pages/Billing/Show.vue` — panel "Tambah pengguna", tombol "Terbitkan tagihan · Rp 0"
  - `app/Models/Subscription.php:94-95` & `app/Services/Pricing/ActiveSeatsResolver.php:27` — `seat_high_water` hanya naik
- **Deskripsi:**
  Query paket 2026-08-01: `dasar` = `base_price 0`, `included_seats 1`, `extra_seat_price 0`. Paket Premium sudah punya angka nyata (`premium-1/2/3` → seat tambahan 15.000 / 12.500 / 10.000), tapi **tidak ada tenant yang bisa berada di sana** — itu `[BL-046]`. Karena setiap tenant lahir di `dasar` dan tidak punya jalan pindah paket, `extra_seat_price` yang benar-benar dipakai hari ini selalu **nol**.
  Akibatnya panel "Tambah pengguna" di halaman langganan menawarkan tombol **"Terbitkan tagihan · Rp 0"**, dan alurnya tetap berjalan penuh sesudah itu: tagihan `KIND_UPGRADE` senilai Rp 0 terbit dengan jatuh tempo 7 hari, lalu seat baru **baru aktif setelah tenant mengunggah bukti transfer** (`storeProof` → `applyProvisionalUpgrade`). Tenant diminta membuktikan bahwa ia sudah mentransfer nol rupiah, dan pemilik SaaS diminta memeriksa bukti itu. Bukan cacat kode — kodenya justru bekerja persis seperti dirancang — melainkan **kebijakan harga yang belum ada** yang muncul ke permukaan sebagai alur yang menggelikan.
  **Temuan kedua, terpisah dan lebih berumur panjang: kursi tidak pernah bisa turun.** Tidak ada satu pun jalur di kode yang menurunkan `seats`. `seat_high_water` hanya naik (`Subscription.php:94-95`), dan `ActiveSeatsResolver` sengaja memakai nilai tertinggi itu, bukan cacah aktif hari ini. Untuk tenant jalur Harga Adaptif ini berarti **satu kasir yang pernah dipekerjakan sebulan akan terus menaikkan tarif selamanya**, karena `active_seats` adalah dimensi harga. Warung musiman — yang justru paling mungkin jadi sasaran Harga Adaptif — paling dirugikan olehnya. Perilaku "high water" itu disengaja untuk mencegah tenant memangkas seat sesaat sebelum penagihan, jadi memperbaikinya bukan sekadar mengizinkan pengurangan.
- **Usulan Perbaikan:**
  **(a) Tetapkan `extra_seat_price` paket `dasar` lebih dulu** — bagian dari `[BL-041]`(a). Bila jawabannya memang **0** (paket dasar dipatok satu pengguna, penambahan hanya lewat naik paket), maka panelnya jangan menawarkan tagihan sama sekali: ganti jadi ajakan naik ke Premium. Tombol yang menerbitkan tagihan nol rupiah bukan jawaban yang benar untuk harga nol.
  **(b) Lewati keharusan bukti untuk tagihan Rp 0.** Selama masih mungkin ada tagihan bernilai nol, `storeProof` tidak boleh jadi satu-satunya pintu ke `applyProvisionalUpgrade()`. Tagihan Rp 0 semestinya langsung lunas dan seat-nya langsung berlaku — tanpa unggahan, tanpa antrean pemeriksaan yang tidak memeriksa apa pun.
  **(c) Sembunyikan atau tandai panelnya sampai (a) terjawab.** Pilihan paling jujur untuk sekarang: tampilkan panel dengan keterangan bahwa penambahan pengguna sedang disiapkan, alih-alih tombol yang bekerja tapi menghasilkan tagihan tak bermakna.
  **(d) Putuskan cara kursi bisa turun.** Bukan dengan membuang `seat_high_water`, melainkan memberinya batas waktu: mis. nilai tertinggi **dalam periode berjalan**, yang direset tiap penerbitan tagihan. Itu tetap menutup celah "pangkas seat sesaat sebelum ditagih" — celah yang jadi alasan keberadaannya — sambil membiarkan tenant yang benar-benar mengecil ikut mengecil tagihannya. Berkaitan dengan `[BL-044]` karena reset-nya menempel di momen penerbitan tagihan.
  **Bergantung pada `[BL-046]`** (paket kedua harus bisa dihuni) dan **`[BL-041]`(a)** (angkanya).

---

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
- **Status:** Open — **sebagian butuh keputusan pemilik (angka tarif)**; struktur tarif sudah diputuskan 2026-08-01, lihat blok di bawah
- **Prioritas:** High
- **Keputusan pemilik 2026-08-01 — struktur tarif:**
  Menjawab pertanyaan yang menggantung di butir (a) dan di `[BL-044]`:
  1. **Rp 100k adalah puncak bracket Harga Adaptif**, bukan tarif dasar jalur Harga Tetap. Bracket D di `config/subscription.php:89` sudah memakai angka itu, jadi benih migrasi `pricing_rules` **tidak perlu diubah** untuk keputusan ini.
  2. **Puncak Adaptif sejajar dengan harga Premium.** Artinya kedua jalur bertemu di ujung atas: tenant yang omsetnya sampai di bracket D membayar sama besar dengan tenant Premium, sehingga tidak ada insentif menahan diri di jalur Adaptif hanya demi harga.
  3. **Omset tinggi tidak lagi berhak atas Harga Adaptif** — di atas ambang tertentu, satu-satunya jalur adalah Premium. Adaptif menyempit jadi apa yang memang dimaksudkannya sejak awal: keringanan untuk usaha beromset rendah.
  4. **Kelayakan Adaptif ditentukan pemilik SaaS**, bukan dipilih sendiri oleh tenant — baik lewat ambang omset maupun penetapan manual ("atau yang saya tentukan baru bisa dapat").
  Butir 3 dan 4 **bukan sekadar angka**: keduanya menuntut pagar kelayakan yang hari ini tidak ada sama sekali, dan pagar itu menabrak batas privasi yang sudah dibangun. Dipisah jadi `[BL-048]` supaya entri ini tetap tentang tarif.
- **Keputusan pemilik 2026-08-01 (kedua) — bentuk tangga harga:**
  Menjawab tiga pertanyaan yang saya gantung di blok sebelumnya, dan mengubah dua di antaranya dari "angka yang harus ditetapkan" jadi "angka yang tidak perlu ada":
  5. **Premium mungkin persis sejajar dengan puncak Adaptif** (Rp 100k) — belum dikunci, dan sengaja dibiarkan terbuka.
  6. **Ambang pemisah Adaptif↔Premium tidak disetel terpisah.** Ia diturunkan dari tangga harga Premium: Adaptif berlaku sampai sebelum angka Premium. Satu angka lebih sedikit untuk dijaga tetap sinkron.
  7. **Premium kelak bisa lebih dari satu tier** (rencana: tiga), dan puncak Adaptif boleh mendarat di **tengah** tangga itu — mis. sejajar tier ke-2, bukan di ujungnya.
  Butir 7 membatalkan cara berpikir "Adaptif dan Premium adalah dua wilayah yang dipisah satu garis". Yang benar: keduanya **dua tangga harga berdampingan yang boleh tumpang tindih**, dan batasnya bukan "di mana Premium mulai" melainkan **di mana tangga Adaptif berhenti**. Konsekuensi praktisnya bagus — menambah atau menggeser tier Premium tidak menuntut menyentuh bracket Adaptif sama sekali.
  **Temuan yang mendukungnya (query, bukan dugaan):** batas itu ternyata **sudah bisa dinyatakan tanpa kolom baru**. Bracket A–C masing-masing punya sepasang syarat `gte`+`lt`; bracket D hanya punya `gte 15.000.000` **tanpa batas atas**, sehingga ia menelan setiap tenant beromset tinggi selamanya di Rp 100k. Menutup tangga Adaptif = menambah satu baris syarat `lt` pada bracket D, dari `/platform/pricing-rules`, tanpa migrasi. Rinciannya beserta satu jebakan yang harus dihindari ada di **`[BL-048]`**(b).
  **Yang masih menunggu keputusan:** nominal tiap tier Premium (dan berapa tier yang benar-benar akan dibuka di awal — tiga tier di hari pertama jauh lebih mahal dijelaskan ke calon pelanggan daripada satu), serta nilai `lt` yang menutup bracket D. Harga seat tambahan **sudah terjawab**, lihat `[BL-046]`.
- **Angka percobaan sudah TERPASANG di basis data pengembangan 2026-08-01 — bukan usulan di atas kertas.** Atas permintaan pemilik, tarifnya diisi langsung lewat `/platform/pricing-rules` untuk diuji:
  | Paket | Tarif bulanan | Pengguna termasuk | Seat tambahan |
  |---|---|---|---|
  | Dasar | Rp 0 | 1 | Rp 0 |
  | Premium 1 | Rp 100.000 | 3 | Rp 15.000 |
  | Premium 2 | Rp 150.000 | 5 | Rp 12.500 |
  | Premium 3 | Rp 200.000 | 10 | Rp 10.000 |
  Bracket D ditutup di `< Rp 50.000.000` (lihat `[BL-048]`). **Dua angka di sini adalah asumsi saya, bukan keputusan pemilik, dan perlu ditinjau:** (1) **`included_seats` 3/5/10 dan seat tambahan 15k/12,5k/10k** — dipilih supaya tangganya masuk akal (makin tinggi tier, makin murah seat tambahannya), tapi tidak pernah diminta siapa pun; (2) **ambang Rp 50 jt** — dipilih mengikuti pola rasio bracket sebelumnya (2 jt → 5 jt → 15 jt, kelipatan 2,5–3×), bukan dari data pasar.
  Perlu disadari juga: `Premium 1` bertarif **sama persis** dengan puncak Adaptif (Rp 100.000). Itu memang yang diminta butir 2, tapi konsekuensinya harus disengaja — tenant di bracket D dan tenant Premium 1 membayar sama, sehingga satu-satunya pembeda keduanya tinggal seat dan kuota, bukan harga. Bila itu tidak diinginkan, yang digeser sebaiknya harga Premium 1, bukan bracket D.
  Semua perubahan tercatat di `platform_audit_logs` (`plans.create` ×3, `pricing-rules.create` ×1). **Ini basis data lokal pengembangan**, bukan produksi.
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
- ~~**Jendela yang akan tertutup:**~~ **terpakai 2026-08-05.** `[BL-030]` sudah ditutup selagi `invoices` masih kosong, jadi tidak ada tanggal tagih siapa pun yang bergeser. Butir (a) di bawah kini bebas dijalankan kapan pun tanpa menyeret pekerjaan data.
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