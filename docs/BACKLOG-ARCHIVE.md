# SAPI — Arsip Backlog Selesai

**Format:** Arsip entri `docs/BACKLOG.md` yang statusnya sudah `Selesai`. Dipisahkan dari file utama pada 2026-07-31 karena bagian ini memakan lebih dari separuh isi file padahal hampir tidak pernah perlu dibaca saat bekerja.

> **Cara membaca file ini:** jangan dibaca utuh. Indeks ringkas seluruh entri di sini ada di `docs/BACKLOG.md` bagian "Riwayat Selesai (Arsip)" — cari ID-nya di sana lebih dulu. Bila isi lengkap sebuah entri memang dibutuhkan, cari headingnya (`grep -n '^### \[BL-0xx\]' docs/BACKLOG-ARCHIVE.md`) lalu baca potongan itu saja.
>
> Entri baru **tidak** ditulis di sini. Entri ditulis di `docs/BACKLOG.md`, dan dipindahkan ke sini setelah statusnya `Selesai`.

---

## Daftar Entri

### [BL-047] Kuota AI Gratis Terkunci di `.env` — Bukan Kebijakan yang Bisa Diatur Pemilik SaaS
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik — "atur kuota gratis umum ... apakah per hari, di reset semua, atau ada promo dalam waktu tertentu ... jadi sudah tidak statis hard coded 5 request per hari, bisa di atur di platform account"
- **Status:** Selesai (2026-08-13) — butir (b) mendarat 2026-08-01; butir (a), (c), dan (d) mendarat 2026-08-13. Butir (a) dikerjakan dengan satu penyimpangan yang disengaja: promo TIDAK jadi mata rantai di urutan fallback melainkan lapis penambah di atasnya, sebab setiap paket menyetel `limits.ai_daily` sendiri sehingga lapis pengisi-kekosongan akan lahir sebagai kode mati. Lihat `[ADDITION] Kuota AI Berhenti Tinggal di `.env`: Kebijakan Berjangka Waktu, Promo, dan Tombol Mengembalikan Jatah Hari Ini (BL-047)` di `docs/CHANGELOG.md`
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
- **Pemutakhiran 2026-08-13 — SELESAI.** `ai_quota_policies` (dua mode: `baseline` untuk kuota bawaan platform, `bonus` untuk promo), halaman `/platform/ai-quota` di balik modul `ai_quota`, dan tombol "Reset Semua" yang berdiri sendiri. `config/ai.php` sengaja TIDAK dihapus — ia tetap lapis terakhir, sehingga tabel yang lahir kosong berarti tak ada satu pun tenant yang jatahnya berubah oleh pemasangan ini. Yang tidak termasuk dan tetap terbuka: kuota tambahan yang bisa DIBELI tenant = `[BL-069]`, masih terhalang keputusan harga.

---

### [BL-064] Satu-satunya Grafik di Aplikasi Ini Ada di Dashboard — Batang, Tujuh Hari, dan Laporan Tidak Punya Grafik Sama Sekali
- **Ditemukan:** 2026-08-08
- **Sumber:** Saran pasca-peragaan — "grafik dan line chart"
- **Status:** Selesai (2026-08-13) — butir (a), (b), dan (d) mendarat utuh; butir (c) dikerjakan pada bagian yang wajib ("pasang di rekap bulanan lebih dulu"), sementara kalimat keduanya ("pertimbangkan tren per jam di halaman harian") memang sebuah pertimbangan dan sengaja tidak dikerjakan. Lihat `[ADDITION] Grafik Kedua Aplikasi Ini: Garis Tren di Rekap Bulanan (BL-064)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/Components/DailyChart.vue` — satu-satunya komponen grafik; `import { Bar } from 'vue-chartjs'`, dan hanya `BarElement` yang diregistrasi
  - `resources/js/Pages/Owner/Dashboard.vue:262` — **satu-satunya** pemakainya di seluruh `resources/js`
  - `resources/js/Pages/Owner/Reports/Daily.vue`, `Reports/Upsell.vue` — nol grafik; semuanya tabel dan kartu angka
  - `resources/js/Pages/Owner/Reports/Monthly.vue` (2026-08-13) — **tempat pasang pertamanya sudah berdiri**; prop `dailySeries` berisi `[{ date, count, revenue, voided }]` untuk SETIAP tanggal di bulan itu, termasuk hari nol, jadi garisnya tidak perlu diinterpolasi. Hari ini deret itu dibaca lewat tabel "Rincian Harian"
  - `package.json:20,23` — `chart.js` ^4.5.1 dan `vue-chartjs` ^5.3.3 **sudah terpasang**
- **Deskripsi:**
  Pustaka grafiknya sudah ada di proyek dan sudah dipakai sekali. Yang belum ada adalah jenis grafik kedua dan pemakai kedua. Halaman Laporan — tempat orang justru datang untuk melihat pola — seluruhnya berupa tabel; sementara dashboard, tempat orang hanya melirik, adalah satu-satunya yang punya grafik.
  Batang cocok untuk membandingkan hari yang berdiri sendiri, dan itu sebabnya `DailyChart` memilihnya. Untuk deret panjang seperti rekap bulanan (`[BL-063]`), batang jadi ramai dan garis tren jauh lebih terbaca — jadi ini bukan mengganti yang sudah ada, melainkan menambah bentuk kedua untuk data yang bentuknya memang lain.
- **Usulan Perbaikan:**
  **(a)** Komponen `TrendChart.vue` berbasis `Line`, dengan `PointElement` + `LineElement` diregistrasi. **Jangan mengubah `DailyChart` jadi serba-bisa** lewat prop `type` — dua grafik dengan sumbu dan tujuan berbeda yang dipaksa satu komponen akan penuh percabangan sebelum pemakai ketiga muncul.
  **(b)** Ikuti pola warna `DailyChart`: baca `--color-primary`/`--color-brand` dari CSS variable, jangan mematok heksadesimal. Itu yang membuat grafiknya ikut tema, dan grafik kedua yang mematok warna sendiri akan langsung terlihat asing.
  **(c)** Pasang di rekap bulanan lebih dulu — itu data yang paling butuh garis. Halamannya sudah ada sejak `[BL-063]` selesai (2026-08-13) beserta deret hariannya, jadi yang tersisa hanya komponennya dan satu baris pemasangan di atas tabel "Rincian Harian". Baru sesudahnya pertimbangkan tren di halaman harian (mis. per jam).
  **(d)** Beri keadaan kosong yang jelas. Tenant baru yang membuka laporan dan melihat kanvas kosong tanpa keterangan akan menganggapnya rusak.
- **Catatan penutup (2026-08-13):**
  Satu hal yang tidak tertulis di entri ini tapi jadi bagian tersulitnya: warna tema proyek ini ditulis dalam `oklch()`, dan arsiran di bawah garis butuh versi tembus pandangnya. Alpha tidak bisa ditempelkan ke string `oklch()`, jadi warnanya dilukis ke kanvas 1×1 lalu pikselnya dibaca kembali — butir (b) tetap ditegakkan (nol heksadesimal dipatok) tanpa memaksa tema pindah format warna.
  Tren per jam di laporan harian tetap tidak ada. Ia butuh agregasi per jam yang belum ada di `daily()` dan menjawab pertanyaan yang berbeda ("jam berapa toko ramai"); buka entri sendiri bila memang dibutuhkan, jangan diselundupkan sebagai sisa entri ini.

---

### [BL-062] Sisa Kuota AI Hanya Terlihat di Pengaturan — Bukan di Halaman yang Menghabiskannya
- **Ditemukan:** 2026-08-08
- **Sumber:** Saran pasca-peragaan — "tambahan menu ai di analysis, coba pindahkan kuotanya ke AI Analysis (bukan di profil saja)"
- **Status:** Selesai (2026-08-13) — usulan (a)–(d) mendarat: blok kuota dikirim dari `index()` **dan** `show()`, meternya berdiri sebaris dengan tombol yang kini mati saat jatahnya nol (dan penolakannya pindah dari antrean ke `store()`), tenant ber-BYOK melihat keterangan lain alih-alih angka nol, dan versi lengkapnya tetap tinggal di Pengaturan. Butir (e) TIDAK dikerjakan — "menu turunan" di AI Analysis masih perlu diperjelas lebih dulu. Lihat `[ADDITION] Sisa Kuota AI Pindah ke Halaman yang Membelanjakannya, dan Penolakannya Pindah dari Antrean ke Layar (BL-062)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Http/Controllers/Owner/SettingsController.php:26,80-81` — satu-satunya pembaca `AiQuota` untuk layar: `daily_limit` + `remaining`
  - `resources/js/Pages/Owner/Settings/Index.vue:282-290` — "Sisa kuota gratis hari ini: N dari M", dan hanya tampil bila kunci BYOK belum diisi
  - `app/Http/Controllers/Owner/AiAnalysisController.php:15-20` — `index()` hanya mengirim `analyses`; **tidak menyentuh `AiQuota` sama sekali**
  - `app/Jobs/RunAiAnalysisJob.php:92-99` — penolakan karena kuota habis terjadi **di antrean**, setelah tombol ditekan
  - `app/Services/Ai/AiQuota.php` — sudah jadi satu-satunya pembaca kuota, lengkap dengan alasan tertulisnya
- **Deskripsi:**
  Kuota dibelanjakan di `/ai-analysis` tapi hanya bisa dilihat di `/pengaturan`. Akibatnya bukan sekadar tidak nyaman: karena penolakan kuota terjadi di dalam job, owner menekan "Analisis", melihat statusnya `pending`, lalu beberapa detik kemudian menemukan analisisnya gagal — tanpa pernah diberi tahu di layar itu bahwa jatahnya memang sudah nol sejak awal. Angka yang bisa mencegah itu sudah dihitung dan sudah punya kelas sendiri; ia hanya tidak pernah dikirim ke halaman yang membutuhkannya.
  Yang perlu dicatat sebagai batasan: `AiQuota` menjawab "berapa jatahnya", bukan "apakah ia perlu dijatah" — pemeriksaan BYOK tinggal di pemanggil. Jadi memindahkan tampilannya tanpa ikut memindahkan syarat itu akan memasang "sisa 0 dari 5" di layar tenant yang justru sedang memakai kunci sendiri dan tak berbatas.
- **Usulan Perbaikan:**
  **(a)** Kirim blok kuota yang sama dari `AiAnalysisController::index()` **dan** `show()` — keduanya me-render komponen yang sama, jadi melewatkan salah satunya membuat angkanya hilang begitu satu analisis dibuka.
  **(b)** Tampilkan di dekat tombol kirim, dan **matikan tombolnya saat sisa nol** dengan alasan yang terbaca. Menolak di layar jauh lebih murah daripada menolak di antrean.
  **(c)** Tenant ber-BYOK melihat keterangan lain ("memakai kunci sendiri — tanpa batas harian"), bukan angka kuota. Pakai syarat yang sama dengan `Settings/Index.vue:282`.
  **(d)** **Jangan** mencabutnya dari Pengaturan. Di sana ia konteks untuk keputusan BYOK; di AI Analysis ia peringatan sebelum bertindak. Dua pembaca, dua maksud — dan `AiQuota` memang dibuat supaya keduanya tidak bisa berselisih.
  **(e)** Saran ini menyinggung "menu AI di analysis". Menu `/ai-analysis` sudah ada di `OwnerLayout`; kalau yang dimaksud adalah menu **turunan** (mis. riwayat vs buat baru), itu permintaan terpisah yang perlu diperjelas lebih dulu.

---

### [BL-063] Laporan Hanya Ada Per Satu Tanggal — Belum Ada Rekap Bulanan
- **Ditemukan:** 2026-08-08
- **Sumber:** Saran pasca-peragaan — "grafik laporan dalam bulanan"
- **Status:** Selesai (2026-08-13) — seluruh usulan (a)–(d) mendarat: rute `reports.monthly`, agregasi penuh di basis data, bulan kalender, dan unduhan CSV. Lihat `[ADDITION] Laporan Bulanan: Satu Bulan Kalender, Diagregasi di Basis Data, dengan Unduhan CSV (BL-063)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Http/Controllers/Owner/ReportController.php:22-83` — `daily()`: seluruh isinya disaring `whereEffectiveDate($date)`, satu hari saja
  - `routes/web.php:168-172` — hanya `reports.daily` dan `reports.upsell`; tidak ada rute rekap periode
  - `app/Http/Controllers/Owner/DashboardController.php:59-66` — satu-satunya agregasi lintas hari yang ada, dan dipatok 7 hari terakhir
  - `app/Models/Transaction.php` — `effectiveDateSql()` + `whereEffectiveFrom()`: **bahan yang dibutuhkan sudah ada**
- **Deskripsi:**
  Semua laporan di aplikasi ini menjawab pertanyaan "hari ini bagaimana". Tidak ada satu pun permukaan yang menjawab "bulan ini bagaimana", padahal itu satuan yang dipakai pemilik toko saat menghitung sewa, gaji, dan setoran. Yang paling dekat adalah tren 7 hari di dashboard — terlalu pendek untuk melihat pola akhir pekan, apalagi tanggal muda vs tanggal tua.
  Kabar baiknya, bagian yang biasanya paling sulit sudah beres: laporan sudah memakai **tanggal efektif**, bukan `created_at`, jadi penjualan offline yang baru tersinkron esok hari tetap masuk ke bulan yang benar. Rekap bulanan yang dibangun di atas `effectiveDateSql()` tidak akan mewarisi cacat itu.
- **Usulan Perbaikan:**
  **(a)** Satu rute `reports.monthly` dengan parameter `month` (`Y-m`), isinya sejajar dengan harian: omzet, jumlah transaksi, void, rekap metode bayar, produk terlaris — plus satu deret harian untuk grafiknya (`[BL-064]`).
  **(b)** Agregasi di database, jangan menarik seluruh transaksi sebulan ke memori lalu menjumlahkannya di PHP. `daily()` boleh melakukan itu karena sehari muat; sebulan di tenant yang ramai tidak.
  **(c)** **Putuskan dulu: bulan kalender atau periode langganan?** Keduanya masuk akal dan hasilnya berbeda — periode langganan tenant berjangkar di tanggal daftar (keputusan 2026-08-07), jadi "bulan ini" versi tagihan bukan 1–31. Saran saya bulan kalender untuk laporan operasional, karena itu yang dipakai pemilik toko menghitung sewa dan gaji; jangan campur keduanya di satu layar.
  **(d)** Sekalian sediakan unduhan CSV-nya. Rekap bulanan yang tidak bisa dibawa ke spreadsheet akan tetap disalin manual.
- **Catatan penutup (2026-08-13):**
  Butir (c) diputuskan sesuai saran entri ini: **bulan kalender**, dan periode langganan sengaja tidak muncul di layar yang sama. Butir (a) ditambah tiga hal yang tidak tertulis di sini tapi memang yang dicari pemilik saat membuka rekap bulanan — pembanding terhadap bulan sebelumnya, "hari berjualan" sebagai penyebut rata-rata harian, dan hari teramai. Grafiknya sengaja belum ikut; `dailySeries` sudah berbentuk siap-pakai untuk `[BL-064]`.
  Laba kotor tidak diikutkan meski `ProfitService` sudah ada: COGS-nya memakai `cost_price` yang tidak semua tenant isi, dan margin 100% palsu lebih buruk daripada tidak ada angka margin.

---

### [BL-048] Jalur Harga Adaptif Bisa Dipilih Siapa Saja — Belum Ada Pagar Kelayakan, dan Pagarnya Menabrak Gerbang Privasi
- **Ditemukan:** 2026-08-01
- **Sumber:** Keputusan pemilik 2026-08-01 — "khusus yang memiliki omset cukup tinggi sudah hanya bisa bayar premium, tanpa subsidi atau harga adaptif lagi, adaptif khusus omset rendah atau yang saya tentukan baru bisa dapat"
- **Status:** Selesai (2026-08-10) — kedua penerusnya sudah mendarat: `[BL-052]` (2026-08-08) dan `[BL-055]` (2026-08-10). Butir (a) dibatalkan dan sengaja TIDAK dikerjakan; butir (b) — ambang sebagai pintu keluar — kini berjalan sebagai `SubscriptionService::reviewAdaptiveCeiling()`, dengan peringatan `price === null` di kaki entri ini ditegakkan sebagai kode di `PricingService::adaptiveCeiling()`. Lihat `[ADDITION] Pengajuan Harga Adaptif Punya Halaman, Dinilai Seketika, dan Berujung pada Ambang (BL-055)` di `docs/CHANGELOG.md`
- **Prioritas:** High
- **Pemutakhiran 2026-08-07 — usulan (a) DIBATALKAN, dan penyebabnya membubarkan seluruh kebuntuan entri ini.**
  Entri ini macet di satu lingkaran: *untuk menilai kelayakan dibutuhkan data omset yang baru boleh dikumpulkan setelah masuk jalur Adaptif*. Usulan (a) — kelayakan sebagai pemberian manual pemilik SaaS — adalah jalan memutarnya, bukan jawabannya.
  Alur yang diputuskan pemilik memotong lingkarannya langsung: **consent diberikan pada saat MENGAJUKAN.** Tenant yang tidak sanggup membayar `paid-1` menekan "ajukan keringanan", mencentang persetujuan membuka omset, dan barulah sistem berhak mengukurnya. Tidak ada data yang dikumpulkan dari orang yang tidak meminta apa pun, tidak ada janji di `resources/consents/normal-v1.md` yang dilanggar, dan tidak ada yang berlaku surut. Lingkarannya tidak pernah terbentuk.
  **Akibatnya penilaian otomatis penuh menjadi sah, bukan kompromi.** Verifikasi manual admin tidak membeli privasi — privasinya sudah aman lewat consent. Yang dibelinya cuma kontrol penipuan, dan itu pun sedikit: omset dihitung dari transaksi yang tercatat di POS, jadi tenant yang mengincar harga murah cukup tidak mencatat sebagian penjualannya, dan admin yang menatap angka di layar tidak bisa membedakannya. Yang benar-benar menahan itu dua hal, keduanya bukan verifikasi manual: mencatat lebih rendah **merusak laporan dan stok tenant sendiri**, dan **pemeriksaan ulang berkala** menaikkan harganya begitu omsetnya naik. Karena itu pemilik memilih otomatis, dengan override manual untuk kasus khusus.
- **Pemutakhiran 2026-08-07 (kedua) — Adaptif akhirnya punya definisi, dan definisi itu menelanjangi bracket D.**
  Selama ini "Harga Adaptif" tidak pernah didefinisikan relatif terhadap apa pun. Pemilik menetapkannya: **Adaptif = `paid-1` dengan harga didiskon menurut omset yang disepakati consent-nya.** Tenant Adaptif duduk di paket `paid-1` — 3 seat, 15 analisis AI — dan yang berbeda hanya nominal tagihannya.
  Begitu itu ditulis, diskon tiap bracket bisa dihitung, dan hasilnya mengejutkan:

  | Bracket | Omset | Harga lama | Diskon lama | Harga baru | Diskon baru |
  |---|---|---|---|---|---|
  | A | 0–2 jt | 10.000 | 90% | 10.000 | 90% |
  | B | 2–5 jt | 25.000 | 75% | 25.000 | 75% |
  | C | 5–15 jt | 50.000 | 50% | 50.000 | 50% |
  | D | 15–50 jt | 100.000 | **0%** | **75.000** | **25%** |

  **Tangga Adaptif sebenarnya berakhir di Rp 15 juta, bukan Rp 50 juta.** Di rentang Rp 15–50 jt, tenant menyerahkan data penjualannya dan menerima nol rupiah — ongkos privasi tanpa imbalan, dan hal pertama yang akan disadari pelanggan yang teliti. Ambang Rp 50 juta pun jadi pemberitahuan kosong: melewatinya tidak mengubah tagihan sama sekali, karena bracket D dan `paid-1` sama-sama Rp 100.000.
  Dengan D di Rp 75.000, tangganya jadi monoton **90/75/50/25/0** — mudah dipajang di halaman harga, dan ambang Rp 50 juta akhirnya berarti sesuatu (Rp 75.000 → Rp 100.000).
- **Yang sudah terpasang 2026-08-07 (bukan rencana):** bracket D diterbitkan ulang seharga Rp 75.000 dengan syarat `gte 15.000.000` + `lt 50.000.000`, berlaku sejak 2026-08-07, lewat `PricingService::publishRule()`. Revisi berlabel sama, bukan sunting di tempat — dua aturan D sebelumnya tetap tersimpan sebagai riwayat, dan `unique()` per label di `matchContext()` memenangkan yang terbaru. Diverifikasi: 1,5 jt → A · 3 jt → B · 9 jt → C · 20 jt & 49.999.999 → **D Rp 75.000** · 50 jt & 200 jt → **tidak ada bracket**. Jejak di `platform_audit_logs` (`pricing-rules.create`).
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php:672-684` — `canSwitchTrack()`: **satu-satunya** penjaga perpindahan jalur, dan ia hanya memeriksa **jarak waktu** (`track_switch_minimum_months`), bukan kelayakan apa pun
  - `app/Http/Controllers/Billing/ConsentController.php:84,129` — owner menyetujui dokumen subsidi → `switchToSubsidized()` langsung dipanggil; tidak ada pemeriksaan lain di antaranya
  - `app/Services/SubscriptionService.php:707-718` — `switchToSubsidized()`: menulis jalur, tanpa syarat
  - `app/Jobs/ComputeTenantMonthlyRevenue.php:34-45` — **gerbang privasi dua lapis**: omset HANYA dihitung untuk tenant yang sudah di jalur `subsidized` **dan** consent-nya masih berlaku
  - `app/Services/Pricing/DimensionRegistry.php:68-80` — dimensi ber-`requires_consent` padam sendiri untuk tenant tanpa persetujuan
  - `app/Http/Controllers/Billing/SubscriptionController.php:108` — `bracket` hanya diisi bila `isSubsidized()`
- **Deskripsi:**
  Hari ini **setiap** tenant bisa memilih jalur Harga Adaptif kapan pun ia mau. Yang berdiri di depannya cuma dua hal: dokumen persetujuan yang harus dibaca, dan jarak 3 bulan dari perpindahan sebelumnya. Tidak ada apa pun yang menanyakan berapa omsetnya. Warung beromset besar bisa menyeberang ke Adaptif hari ini juga — dan justru itu yang keputusan 2026-08-01 tutup.
  **Masalah sebenarnya bukan menambah `if`.** Untuk menolak tenant karena omsetnya terlalu tinggi, sistem harus **tahu** omsetnya. Tapi omset hanya dihitung untuk tenant yang **sudah** di jalur Adaptif dengan consent aktif (`ComputeTenantMonthlyRevenue:43-54`) — gerbang privasi yang dibangun dengan sengaja dan didokumentasikan di dokumen consent jalur normal: jalur Harga Tetap **tidak membuka data penjualan sama sekali**. Jadi:
  > Untuk menilai kelayakan masuk, dibutuhkan data yang baru boleh dikumpulkan setelah masuk.
  Ini melingkar, dan tidak bisa dipecahkan dengan diam-diam menghitung omset semua tenant — itu membatalkan janji tertulis di `resources/consents/normal-v1.md` dan berlaku surut bagi tenant yang sudah menyetujuinya.
  Arah sebaliknya **tidak** bermasalah dan sudah punya datanya: tenant yang sudah di jalur Adaptif lalu tumbuh melewati ambang sudah terlihat omsetnya (ia consent), jadi memindahkannya keluar ke Premium bisa dikerjakan tanpa menyentuh privasi siapa pun.
- **Usulan Perbaikan:**
  Frasa **"atau yang saya tentukan"** di catatan pemilik ternyata bukan pelengkap — itu jalan keluar dari lingkaran di atas, dan sebaiknya jadi **mekanisme utamanya**, bukan cadangan:
  ~~**(a) Kelayakan sebagai pemberian, bukan perhitungan.** Tambahkan penanda kelayakan per tenant (mis. `subsidy_eligible_at` + alasan) yang **hanya** bisa disetel pemilik SaaS dari platform console.~~ — **DIBATALKAN 2026-08-07.** Jalan memutar ini tidak diperlukan lagi: consent yang diberikan saat mengajukan membuat pengukuran omset sah tanpa penanda apa pun. Yang menggantikannya adalah `[BL-055]`. Jangan dikerjakan; menambah `subsidy_eligible_at` sekarang berarti membangun gerbang kedua di depan gerbang yang sudah benar.
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
  **Peringatan yang harus dipegang saat mengerjakannya — dan bentuknya BERUBAH sejak ada paket penampung (ditinjau ulang 2026-08-08).** Dulu `resolveFor()` mengembalikan `price = null` begitu tak ada aturan yang cocok. Hari ini tidak lagi: ia jatuh ke **paket penampung** dan mengembalikan harga paket itu berikut `source = plan` (`PricingService.php:190-196`). `price = null` kini hanya tersisa untuk satu keadaan sempit — tak ada aturan **dan** tak ada paket penampung sekaligus (`source = none`).
  **Ambiguitasnya tidak hilang, ia pindah tempat.** Ketiga sebab ini hari ini menghasilkan keluaran yang sama persis — `source = plan` berikut harga paket, bukan null: (1) omset di atas bracket teratas — yang justru sinyal yang kita inginkan; (2) ada lubang di tabel bracket karena salah setel; (3) tenant tak punya data omset sama sekali karena jalur normal, sebab `DimensionRegistry:80` memadamkan dimensi ber-consent. `source` memisahkan "harga dari aturan" dari "harga dari paket", tapi **tidak** memisahkan ketiganya satu sama lain.
  Akibatnya peringatan lamanya jadi **lebih** keras, bukan lebih longgar: `price === null` sekarang nyaris tak pernah berbunyi sehingga uji kelayakan yang bersandar padanya akan meloloskan semua orang, sementara memakai `source = plan` sebagai gantinya berarti **satu bracket yang salah ketik akan diam-diam mendorong seluruh tenant ke Premium**. Kelayakan harus memeriksa secara eksplisit "omsetnya di atas nilai `lt` tertinggi di tabel", bukan sekadar "tidak ada yang cocok".
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
  **(c) Halaman Langganan harus jujur soal pagarnya.** Tenant yang tidak layak jangan disodori tombol yang akan menolaknya. `SubscriptionController:102` sudah mengirim `can_switch`; perluas jadi menyertakan **alasan** (belum layak / masih dalam jarak 3 bulan / omset di atas ambang) supaya penolakannya bisa dijelaskan di layar, bukan cuma tombol yang mati.
  ~~**Yang perlu Anda putuskan sebelum (b) bisa dikerjakan:** nilai `lt` yang menutup bracket D, dan apakah tenant yang terlanjur di Adaptif dengan omset di atas ambang dipindahkan atau di-*grandfather*.~~ — **Terjawab 2026-08-07.** Ambangnya **Rp 50 juta** dan sudah terpasang. Tenant yang melewatinya **dipindahkan**, bukan di-*grandfather*: ia diberi notifikasi bahwa omsetnya terdeteksi melewati batas tier, lalu diarahkan ke `paid-1`. Pemindahannya berlaku pada periode berikutnya, bukan di tengah periode berjalan. Sisa pekerjaan (b) — penandaan dan pemindahan otomatisnya — pindah ke `[BL-055]`.
  **Sudah terlihat pada data nyata, bukan skenario.** Query 2026-08-07 atas `Kopi Nusantara` (jalur `subsidized`, consent aktif): omset bulanan **Rp 105.946.000**, jauh di atas ambang. `resolveFor()` mengembalikan `source = plan`, `label = Paid 1`, `price = Rp 100.000` — tepat seperti yang dirancang: tidak ada bracket yang cocok, jadi ia jatuh ke paket penampung dan membayar harga penuh. Yang belum terjadi otomatis hanyalah **membalik `pricing_track`-nya ke `normal`**; hari ini ia masih tercatat di jalur Adaptif sambil membayar harga non-Adaptif.
  ~~**Bergantung pada `[BL-046]`**~~ — **terjawab 2026-08-06.** `SubscriptionService::changePlan()` dan aksi platform `PUT /platform/subscriptions/{subscription}/plan` sudah ada, jadi pintu keluar di (b) kini punya tujuan yang benar-benar bisa dihuni. Yang tersisa untuk (b) adalah **penandaan otomatisnya** — mengenali tenant Adaptif yang melewati ambang lalu memindahkannya pada periode berikutnya dengan pemberitahuan lebih dulu — dan itu masih menunggu nilai `lt` yang menutup bracket D. Pemindahan manualnya sendiri sudah bisa dilakukan pemilik SaaS hari ini.

### [BL-055] Pengajuan Harga Adaptif Belum Punya Wujud — Halaman, Penilaian Otomatis, dan Pemindahan Saat Melewati Ambang
- **Ditemukan:** 2026-08-07
- **Sumber:** Keputusan pemilik 2026-08-07 — alur "bayar `paid-1` atau ajukan diskon", dinilai otomatis
- **Status:** Selesai (2026-08-10) — butir (a)–(f). Butir (g) berdiri sendiri sebagai `[BL-073]`, karena ia soal dimensi `active_seats` dan menunggu keputusan, bukan kode. Lihat `[ADDITION] Pengajuan Harga Adaptif Punya Halaman, Dinilai Seketika, dan Berujung pada Ambang (BL-055)` di `docs/CHANGELOG.md`
- **Prioritas:** High — **ini jembatan satu-satunya** dari Rp 0 ke Rp 100.000
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php` — `canSwitchTrack()`, `switchToSubsidized()`: sudah ada, belum punya pemanggil dari sisi tenant
  - `app/Http/Controllers/Billing/ConsentController.php` — consent sudah tercatat; ia yang jadi pintu pengajuan
  - `app/Jobs/ComputeTenantMonthlyRevenue.php` — gerbang privasi dua lapis, tetap dihormati apa adanya
  - `app/Services/PricingService.php` — `resolveFor()`: `context['monthly_revenue']` inilah pembeda yang benar, bukan `price`
  - `app/Http/Controllers/Billing/SubscriptionController.php:62` — `can_switch` dikirim tanpa alasan
- **Deskripsi:**
  Sejak paket berbayar termurah adalah Rp 100.000, tenant yang tidak sanggup membayarnya tidak punya jalan lain selain jalur Adaptif — dan jalur itu hari ini hanya bisa dimasuki lewat halaman consent yang tidak pernah menyebut dirinya sebagai pengajuan keringanan. **Adaptif naik pangkat jadi tier masuk de facto**, dan taruhannya jauh lebih tinggi daripada saat `[BL-048]` ditulis: kalau jalannya lambat atau tidak terlihat, tenant menabrak dinding tepat di bulan ketiga, setelah datanya terlanjur pindah ke aplikasi ini.
- **Usulan Perbaikan:**
  **(a) Halaman pengajuan**, yang isinya dari data — bukan HTML: harga yang berlaku baginya, kapan terakhir diterapkan, dan tangga bracket lengkap. Kerjakan bersama `[BL-041]`(c); keduanya halaman yang sama.
  **(b) Penilaian otomatis, tanpa antrean admin.** Consent dicentang → omset boleh dihitung → bracket ditetapkan → harga berlaku. Override manual tetap disediakan untuk kasus khusus, tapi ia bukan jalur utama. Alasannya di `[BL-048]` pemutakhiran pertama.
  **(c) Layar tenant menyatakan hasilnya apa adanya.** Bila omsetnya di atas ambang, jangan diam — katakan bahwa omsetnya terlalu tinggi untuk keringanan dan arahkan ke `paid-1`. `can_switch` diperluas dengan **alasan** (belum layak / masih dalam jarak 3 bulan / omset di atas ambang) supaya penolakannya bisa dijelaskan, bukan cuma tombol yang mati.
  **(d) Pemeriksaan ulang berkala, dan ini bukan pelengkap.** Tenant yang baru selesai masa gratis punya omset tercatat yang masih kecil, jadi **hampir semua tenant baru mendarat di bracket A atau B**. Tanpa pemeriksaan ulang yang menaikkan mereka saat omsetnya naik, semua orang membeku di harga termurah yang pernah mereka dapat.
  **(e) Melewati Rp 50 juta → notifikasi + pindah ke `paid-1`** pada periode berikutnya, bukan di tengah periode: `price_locked` dan `invoices.pricing_context` dibangun supaya harga berjalan bisa dipertanggungjawabkan. `changePlan()` dan aksi platformnya sudah ada sejak 2026-08-06.
  **(f) Peringatan yang harus dipegang:** kelayakan diperiksa lewat **`context['monthly_revenue'] !== null` DAN nilainya ≥ `lt` tertinggi**, bukan `price === null`. Tiga sebab berbeda menghasilkan `price = null`, dan memperlakukannya sama berarti **satu bracket yang salah ketik akan diam-diam mendorong seluruh tenant ke `paid-1`**. Sudah dibuktikan pada data nyata — lihat `[BL-048]`.
  ~~**(g) Puncak seat yang tak pernah turun masih menahan tenant musiman di bracket yang lebih mahal.** Dipindahkan ke sini 2026-08-10 saat `[BL-049]` ditutup — ini satu-satunya sisa hidupnya. `ActiveSeatsResolver:27` memakai `max(seat_high_water, activeNow)` sebagai dimensi harga `active_seats`, dan `seat_high_water` hanya direset saat tagihan **langganan** dilunasi. Tagihan seat sendiri sudah lepas dari angka ini sejak `[BL-053]` (dasarnya `purchased_extra_seats`), jadi yang tersisa **murni soal penetapan harga Adaptif**: warung yang sempat menambah kasir sebulan bisa tertahan di bracket yang lebih mahal meski kasirnya sudah dilepas. Putuskan bersama (d) — pemeriksaan ulang berkala adalah tempat paling wajar untuk menurunkannya kembali.~~ — **DIPINDAHKAN 2026-08-10 ke `[BL-073]`.**

---

### [BL-049] "Tambah Pengguna" Menerbitkan Tagihan Rp 0 dan Meminta Bukti Transfernya — Harganya Belum Ada, dan Kursi Tidak Pernah Bisa Turun
- **Ditemukan:** 2026-08-01
- **Sumber:** Permintaan pemilik saat merapikan halaman Langganan — "tambah pengguna masih under development pricing-nya"
- **Status:** Selesai (2026-08-10) — butir (b) & (c) 2026-08-07; butir (a) & (d) tertutup oleh `[BL-041]`(a) dan `[BL-053]`, tanpa perubahan kode tersendiri. Lihat `[HOTFIX] Tagihan Rp 0 Berhenti Meminta Bukti Transfer Nol Rupiah (BL-049 butir b & c)` dan `[ADDITION] Seat Tambahan Jadi Komponen Bulanan, dan Untuk Pertama Kalinya Bisa Dilepas (BL-053)` di `docs/CHANGELOG.md`
- **Prioritas:** Low (turun dari Medium: alur menggelikannya sudah tidak ada; yang tersisa keputusan angka)
- **Area Terdampak (keadaan saat entri ditulis, 2026-08-01 — sebagian sudah tidak ada lagi):**
  - `app/Services/SubscriptionService.php:215-231` — `requestSeatUpgrade()`: `amount = plan->extra_seat_price × additional_seats`
  - `app/Services/SubscriptionService.php:75-90` — `startTrial()`: **setiap** tenant lahir di paket `dasar`
  - `app/Http/Controllers/Billing/UpgradeController.php:44-76` — `storeProof()`: bukti transfer wajib sebelum seat berlaku
  - `resources/js/Pages/Billing/Show.vue` — panel "Tambah pengguna", tombol "Terbitkan tagihan · Rp 0"
  - `app/Models/Subscription.php:94-95` & `app/Services/Pricing/ActiveSeatsResolver.php:27` — `seat_high_water` hanya naik
- **Deskripsi:**
  Query paket 2026-08-01: `dasar` = `base_price 0`, `included_seats 1`, `extra_seat_price 0`. Paket Premium sudah punya angka nyata (`premium-1/2/3` → seat tambahan 15.000 / 12.500 / 10.000), tapi **tidak ada tenant yang bisa berada di sana** — itu `[BL-046]`. Karena setiap tenant lahir di `dasar` dan tidak punya jalan pindah paket, `extra_seat_price` yang benar-benar dipakai hari ini selalu **nol**.
  Akibatnya panel "Tambah pengguna" di halaman langganan menawarkan tombol **"Terbitkan tagihan · Rp 0"**, dan alurnya tetap berjalan penuh sesudah itu: tagihan `KIND_UPGRADE` senilai Rp 0 terbit dengan jatuh tempo 7 hari, lalu seat baru **baru aktif setelah tenant mengunggah bukti transfer** (`storeProof` → `applyProvisionalUpgrade`). Tenant diminta membuktikan bahwa ia sudah mentransfer nol rupiah, dan pemilik SaaS diminta memeriksa bukti itu. Bukan cacat kode — kodenya justru bekerja persis seperti dirancang — melainkan **kebijakan harga yang belum ada** yang muncul ke permukaan sebagai alur yang menggelikan.
  **Temuan kedua, terpisah dan lebih berumur panjang: kursi tidak pernah bisa turun.** Tidak ada satu pun jalur di kode yang menurunkan `seats`. `seat_high_water` hanya naik (`Subscription.php:94-95`), dan `ActiveSeatsResolver` sengaja memakai nilai tertinggi itu, bukan cacah aktif hari ini. Untuk tenant jalur Harga Adaptif ini berarti **satu kasir yang pernah dipekerjakan sebulan akan terus menaikkan tarif selamanya**, karena `active_seats` adalah dimensi harga. Warung musiman — yang justru paling mungkin jadi sasaran Harga Adaptif — paling dirugikan olehnya. Perilaku "high water" itu disengaja untuk mencegah tenant memangkas seat sesaat sebelum penagihan, jadi memperbaikinya bukan sekadar mengizinkan pengurangan.
- **Usulan Perbaikan (asli):**
  **(a) Tetapkan `extra_seat_price` paket `dasar` lebih dulu** — bagian dari `[BL-041]`(a). Bila jawabannya memang **0** (paket dasar dipatok satu pengguna, penambahan hanya lewat naik paket), maka panelnya jangan menawarkan tagihan sama sekali: ganti jadi ajakan naik ke Premium. Tombol yang menerbitkan tagihan nol rupiah bukan jawaban yang benar untuk harga nol.
  **(b) Lewati keharusan bukti untuk tagihan Rp 0.** Selama masih mungkin ada tagihan bernilai nol, `storeProof` tidak boleh jadi satu-satunya pintu ke `applyProvisionalUpgrade()`. Tagihan Rp 0 semestinya langsung lunas dan seat-nya langsung berlaku — tanpa unggahan, tanpa antrean pemeriksaan yang tidak memeriksa apa pun.
  **(c) Sembunyikan atau tandai panelnya sampai (a) terjawab.** Pilihan paling jujur untuk sekarang: tampilkan panel dengan keterangan bahwa penambahan pengguna sedang disiapkan, alih-alih tombol yang bekerja tapi menghasilkan tagihan tak bermakna.
  **(d) Putuskan cara kursi bisa turun.** Bukan dengan membuang `seat_high_water`, melainkan memberinya batas waktu: mis. nilai tertinggi **dalam periode berjalan**, yang direset tiap penerbitan tagihan. Itu tetap menutup celah "pangkas seat sesaat sebelum ditagih" — celah yang jadi alasan keberadaannya — sambil membiarkan tenant yang benar-benar mengecil ikut mengecil tagihannya. Berkaitan dengan `[BL-044]` karena reset-nya menempel di momen penerbitan tagihan.
  ~~**Bergantung pada `[BL-046]`** (paket kedua harus bisa dihuni)~~ — **terjawab 2026-08-06:** tenant sudah bisa dipindahkan ke Premium dari panel platform, jadi `extra_seat_price` yang bukan nol kini bisa benar-benar berlaku bagi seseorang.
- **Pemutakhiran 2026-08-07 — butir (b) & (c) SELESAI.** Lihat entri CHANGELOG *"Tagihan Rp 0 Berhenti Meminta Bukti Transfer Nol Rupiah (BL-049 butir b & c)"*. Yang berjalan sejak itu:
  - `InvoiceSettlement::settleIfFree()` melunasi tagihan upgrade Rp 0 seketika (`settled_via = 'zero_amount'`, `verified_by` null), jadi seat-nya langsung berlaku tanpa unggahan. **Hanya `KIND_UPGRADE`** — tagihan langganan Rp 0 sengaja tidak ikut, karena melunasinya akan menulis `price_locked = 0` lalu memperpanjang periodenya, mewariskan tarif nol yang belum pernah diputuskan (`[BL-041]`).
  - Panel "Tambah pengguna" membaca harganya: selama Rp 0 ia berbunyi "Tambah pengguna · gratis". Turunan dari harga, bukan saklar.
  - Tagihan yang terlanjur menggantung dibereskan `php artisan subscriptions:settle-free-upgrades`. Dijalankan 2026-08-07: satu tagihan (`Kopi Story`, seat 2 → 5) lunas.
- **Koreksi terhadap butir (d) — sebagian besar sudah terjawab sebelum entri ini ditutup.** Usul "beri `seat_high_water` batas periode" **sudah terpasang sejak 2026-08-06** (commit `50428e1`, entri `[BL-045]`): `InvoiceSettlement::settle()` menulis `'seat_high_water' => $subscription->activeSeatsUsed()` tiap kali tagihan **langganan** dilunasi, jadi puncaknya direset tiap periode.
- **Bagaimana ditutup (2026-08-10):**
  Ditutup **tanpa satu baris kode baru** — keempat butirnya sudah tidak punya objek lagi. Diverifikasi terhadap kode dan basis data, bukan disimpulkan dari catatan:
  **(a) Terjawab oleh `[BL-041]`(a), 2026-08-07.** Paket `free` kini berharga seat tambahan **Rp 20.000** (query `plans`: `free` 2 seat/Rp 20.000 · `paid-1` 3/Rp 15.000 · `paid-2` 5/Rp 12.500 · `paid-3` 10/Rp 10.000). Tidak ada lagi paket ber-`extra_seat_price` nol, jadi pertanyaan "berapa harganya" tidak lagi menggantung.
  **(b) & (c) Premisnya hilang seluruhnya, bukan cuma diperbaiki.** Sejak `[BL-053]`, `requestSeatUpgrade()` **dihapus** dan digantikan `SubscriptionService::grantSeats()`, yang menaikkan seat seketika **tanpa menerbitkan tagihan apa pun**. Tidak ada lagi tagihan `KIND_UPGRADE` yang lahir, jadi tidak ada tagihan Rp 0 yang bisa meminta bukti transfer. `storeProof()` tetap ada tapi kini melayani tagihan **langganan** saja — dinyatakan eksplisit di docblock `UpgradeController`.
  **(d) Judul entri ini terjawab langsung: kursi sudah bisa turun.** `SubscriptionService::releaseSeats()` menjadwalkan pelepasan yang berlaku satu periode penuh ke depan, dengan dua penjaga (tidak boleh melebihi yang dibeli, dan tidak boleh mencabut kursi yang masih diduduki staf aktif). Panel "Lepas pengguna tambahan" ada di `Billing/Show.vue`.
  **Keadaan data saat ditutup:** `invoices` berisi 2 baris `kind = upgrade`, **keduanya lunas** — tidak ada peninggalan yang menggantung. Penjaga peninggalan di `UpgradeController::store()` (menolak pembelian baru selagi ada tagihan upgrade lama terbuka) sengaja **dipertahankan**: melunasi tagihan dunia-lama menulis `seats = grants_seats`, dan angka itu akan menurunkan jatah tenant yang baru saja membeli lewat jalur baru.
  **Satu sisa yang TIDAK ikut ditutup di sini, dan sudah dipindahkan:** `ActiveSeatsResolver:27` masih memakai `max(seat_high_water, activeNow)` sebagai dimensi harga `active_seats`, jadi puncak yang tak pernah turun masih bisa menahan tenant musiman di bracket Adaptif yang lebih mahal. Itu soal **penetapan harga**, bukan soal tagihan seat, dan tempatnya di `[BL-055]` — sudah dicatat di sana pada 2026-08-10 supaya tidak ikut terkubur di arsip ini.

---

### [BL-054] Masa Tenggang Belum Bertingkat — Kasir Mati Sejak Hari Pertama, Padahal Keputusan Barunya Tiga Tahap
- **Ditemukan:** 2026-08-07
- **Sumber:** Keputusan pemilik 2026-08-07 — "selama tenggat masih boleh jualan"; tangga notif 1–14 / 15–19 / 20–30
- **Status:** Selesai (2026-08-08) — lihat `[ADDITION] Masa Tenggang Jadi Tangga Tiga Tahap — Kasir Berhenti Mati di Hari Pertama (BL-054)` di `docs/CHANGELOG.md`
- **Prioritas:** High
- **Area Terdampak:**
  - `app/Http/Middleware/EnsureSubscriptionActive.php:62` — `isReadOnly() && ! isMethodSafe()` → memblokir **semua** tulis sejak hari pertama tenggat
  - `app/Models/Tenant.php` — `canWrite()`, `isReadOnly()`: hanya mengenal dua keadaan, tanpa umur tenggat
  - `config/subscription.php` — `grace_intensive_from_day` = 15, `grace_lock_from_day` = 20 (**sudah dipasang 2026-08-07**, belum ada pembacanya)
  - `app/Http/Middleware/HandleInertiaRequests.php` — prop yang menggerakkan tampilan tenggat
  - `resources/js/Layouts/OwnerLayout.vue:176-177` — `isSuspended` + `isLocked`, satu-satunya penguncian menu yang ada
- **Deskripsi:**
  Tenggat hari ini bukan "aplikasi masih jalan, dashboard mati". Ia memblokir **seluruh permintaan non-GET** sejak hari pertama — kasir tidak bisa menyimpan satu transaksi pun, artinya toko tidak bisa berjualan. Prinsip lama di config ("tenggat mencabut kemampuan MENAMBAH data, bukan MEMBACA") ternyata berarti persis itu, dan konsekuensinya tidak pernah diperiksa: warung yang tidak bisa berjualan tidak punya uang untuk membayar, jadi tekanannya merusak sumber pembayarannya sendiri.
  Keputusan pemilik menggantinya dengan tangga tiga tahap. Config-nya sudah dipasang; middleware, prop, dan tampilannya belum.
- **Bentuk yang diputuskan:**

  | Hari tenggat | Jualan (POS) | Dashboard analitik | Menu lain | Notifikasi |
  |---|---|---|---|---|
  | 1–14 | boleh | terbuka | normal | halus |
  | 15–19 | boleh | terbuka | normal | intensif, mengganggu |
  | 20–30 | **berhenti** | terbuka, baca saja | halaman "selesaikan tagihan" + CTA ke pembayaran | paksa |
  | > 30 | ditangguhkan | tertutup | tertutup | — |

- **Bagaimana ditutup (2026-08-08):**
  **(a) Dikerjakan.** `Tenant::graceDay()` + `graceStage()`; ambangnya dibaca dari config lewat `SubscriptionService`, tidak satu pun angka tinggal di middleware. `canWrite()` meloloskan tenggat sampai `grace_lock_from_day`, dan `isReadOnly()` berhenti jadi sinonim `grace` — ia kini berarti tahap `locked`.
  **(b) Dikerjakan, dengan satu penyimpangan yang disengaja.** Halaman `Billing/Locked` dirender di URL kasir yang asli, bukan pengalihan — persis seperti diminta. Yang menyimpang: **cakupannya.** Tabel di entri ini menulis "menu lain → halaman selesaikan tagihan", tapi itu berselisih dengan prinsip yang ditulis pemilik di hari yang sama di `config/subscription.php` ("yang TIDAK pernah dicabut di tahap mana pun: membaca data yang sudah ada"). **Pemilik memutuskan prinsipnya yang menang** (2026-08-08): yang diganti halaman kunci hanya layar POS — layar yang ada semata-mata untuk berjualan — sementara laporan, riwayat, stok, dan ekspor tetap terbuka sepanjang tenggat.
  **(c) Sebagian.** Penanda di topbar (`SubscriptionBanner`, tiga nada) dan modalnya (`GraceModal`, sejak tahap `intensive`) berdiri. Pemadamnya baru satu: instruksi bayar yang masih berlaku (`payment_pending`). Pemadam kedua — pengajuan Adaptif — menunggu `[BL-055]`; alurnya belum ada untuk disambungkan.
  **(d) Dijaga, dan diuji.** `GraceStagesTest` menguji langsung bahwa instruksi bayar yang masih berlaku memadamkan notifikasinya **tanpa** memundurkan tahapnya: `payment_pending = true` sementara `stage` tetap `locked` dan `can_write` tetap `false`. Jam tenggatnya dihitung dari `current_period_end`, yang tidak disentuh oleh percobaan pembayaran mana pun.
  **(e) Dihormati.** Prinsip di config tidak dihidupkan kembali sebagian — lihat catatan di (b) soal mana yang menang saat tabel dan prinsipnya berselisih.
- **Usulan Perbaikan:**
  **(a)** `Tenant` mendapat umur tenggat (`graceDay()`), dan `EnsureSubscriptionActive` memakainya: tulis diblokir hanya sejak `grace_lock_from_day`, tidak sejak hari pertama. Jangan menaruh angkanya di middleware — ketiganya sudah di config supaya kebijakan tenggat bisa diubah tanpa membaca kelas mana pun.
  **(b)** Tahap 3 **bukan pengalihan otomatis.** Menu yang terkunci menampilkan halaman pesan sendiri ("selesaikan tagihan untuk membuka halaman ini") dengan tombol ke pembayaran — pengalihan diam-diam membuat pengguna mengira aplikasinya rusak.
  **(c)** Notifikasi: satu penanda di topbar, plus modal. **Modal-nya padam begitu pembayaran sedang diproses atau pengajuan Adaptif sudah dilakukan** — menagih orang yang sudah membayar adalah cara tercepat kehilangan mereka.
  **(d) Jebakan yang harus dijaga:** menyenyapkan notifikasi **tidak boleh menghentikan jam tenggat**. Kalau mengunggah bukti bayar ikut menunda hari ke-20, mengunggah gambar kosong membeli 10 hari gratis, berulang kali. Unggahan mematikan notifikasinya; hitungan harinya jalan terus. Bukti yang ditolak menyalakannya kembali di hari yang seharusnya, bukan mundur.
  **(e)** Prinsip lama di `config/subscription.php` sudah ditulis ulang 2026-08-07 supaya tidak ada dua kebijakan yang berselisih di satu berkas. Jangan menghidupkannya kembali sebagian.

---

### [BL-052] Masa Gratis Habis Tanpa Perpindahan ke `paid-1` — Tenant Bertarif Rp 0 Tidak Pernah Ditagih, Ia Jatuh ke Tenggang
- **Ditemukan:** 2026-08-07 (saat memasang keputusan struktur harga)
- **Sumber:** Keputusan pemilik 2026-08-07 — "jika user sudah lewat dari masa paket free itu sistem akan otomatis memaksa pindah ke paid 1"
- **Status:** Selesai (2026-08-08) — lihat `[ADDITION] Masa Gratis Berakhir dengan Perpindahan, Bukan dengan Jatuh ke Tenggang (BL-052)` di `docs/CHANGELOG.md`
- **Prioritas:** High
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php` — `issueDuePeriodInvoices()`: `price <= 0` dihitung sebagai `free` lalu `continue`
  - `app/Services/SubscriptionService.php` — `startTrial()`: menulis `trial_ends_at`, tapi tak ada yang membacanya saat masa itu habis
  - `app/Services/SubscriptionService.php` — `advanceLifecycle()`: memindahkan keadaan tenant, tidak pernah memindahkan paketnya
  - `app/Services/SubscriptionService.php` — `changePlan()`: mekanisme pemindahannya **sudah ada**, tinggal tak ada yang memanggilnya otomatis
- **Deskripsi:**
  Paket `free` berharga Rp 0, dan itu memang disengaja. Tapi penerbit tagihan memperlakukan harga nol sebagai "tidak ada yang perlu ditagih" lalu melewatinya — **tanpa menerbitkan tagihan dan tanpa memperpanjang periodenya**. Periode itu kemudian lewat, dan `advanceLifecycle()` menurunkan tenant ke masa tenggang.
  Artinya paket gratis **tidak bisa hidup** di sistem ini: tenant yang duduk di `free` akan jatuh ke tenggang tiap periode, selamanya. Keputusan pemilik menutupnya dengan membatasi masa gratis jadi dua bulan lalu memindahkan tenant ke `paid-1` — tapi **pemindahan itu belum ada penggeraknya.** `trial_ends_at` ditulis dan tidak pernah dibaca lagi.
- **Keadaan nyata per 2026-08-07 (query, bukan dugaan):** `Kopi Story` — jalur `normal`, paket `free`, `resolveFor()` mengembalikan `source = plan`, `price = Rp 0`. Periodenya berakhir 2026-08-24, penerbitan jatuh 2026-08-17. Ia **tidak akan ditagih apa pun**, lalu turun ke masa tenggang 2026-08-25. Kedua tenant yang ada juga ber-`trial_ends_at = null` — mereka `active` di paket gratis secara permanen, keadaan yang tidak lagi punya tempat dalam struktur baru.
- **Usulan Perbaikan:**
  ~~**(a)** Di `advanceLifecycle()`, sebelum penerbitan tagihan: tenant yang `trial_ends_at`-nya sudah lewat dan masih di paket `free` dipindahkan ke `Plan` bawaan berbayar lewat `changePlan()`, dengan jejak `PlatformAuditLog`. Urutannya penting — pindah dulu, baru tagih, supaya tagihan pertamanya sudah memakai harga paket barunya.~~ — **Dikerjakan 2026-08-08 sebagai `graduateExpiredTrials()`, dengan satu penyesuaian.** Pemicunya bukan "`trial_ends_at` sudah lewat" melainkan "`trial_ends_at` ≤ hari ini + `invoice_lead_days`". Usulan aslinya masih meninggalkan lubang yang sama: tagihan periode berikutnya terbit **tujuh hari lebih awal**, jadi menunggu masa gratisnya benar-benar lewat berarti penerbit sudah melihat tenant itu seharga Rp 0 dan melewatinya. Urutan "pindah dulu, baru tagih" tetap seperti yang diusulkan, dan dijaga di dalam `advanceLifecycle()` — bukan sebagai dua baris jadwal yang kebetulan berurutan.
  ~~**(b)** Tujuannya jangan di-*hardcode* ke slug `paid-1`. Pola `is_adaptive_fallback` sudah membuktikan bentuk yang benar: satu penanda "paket tujuan setelah masa gratis" yang ditunjuk pemilik SaaS dari panel, sehingga mengganti paket masuk tidak menuntut deploy.~~ — **Dikerjakan.** Kolom `plans.is_post_trial_target`, di-CRUD dari `/platform/pricing-rules`, dengan ketunggalan peran yang kini dibagi bersama `is_adaptive_fallback` lewat `Plan::setExclusiveRole()`. Migrasinya menunjuk `paid-1` sebagai keadaan awal: penanda yang lahir kosong membuat pemasangan baru berbentuk persis seperti bug yang baru ditutup. Tanpa penunjukan, perpindahannya berhenti dan **bersuara** — `stranded` di keluaran perintah, kejadian sensitif di jejak audit, dan peringatan di panel harga; ia tidak pernah menebak paket termurah.
  ~~**(c)** Beri tahu tenant **sebelum** hari-H, bukan lewat tagihan yang tiba-tiba muncul. `invoice_lead_days` = 7 sudah menyediakan jendelanya.~~ — **Dikerjakan lewat jendela yang sama.** Halaman langganan menyebut nama dan tarif paket tujuan selama masa coba (`post_trial_plan`), dan kalimat "Menjelang tanggal itu Anda bisa memilih untuk berlangganan" dicabut — ia sudah tidak benar sejak masa gratis berakhir dengan perpindahan otomatis.
  ~~**(d)** Dua tenant lama (`trial_ends_at = null`, paket `free`) harus diputuskan terpisah.~~ — **Dikerjakan 2026-08-07 atas keputusan pemilik.** Keduanya dipindahkan ke `paid-1` lewat `changePlan()`, seat bayarannya ikut: `Kopi Nusantara` 4 → 5, `Kopi Story` 5 → 6. `Kopi Nusantara` juga dijadwalkan keluar dari jalur Adaptif pada 2026-08-24 (omset Rp 105.946.000, di atas ambang) — `track_reverts_at` disetel langsung, **bukan** lewat `scheduleTrackRevert()`, karena metode itu menghapus `tenant_monthly_metrics` (ia dibangun untuk consent yang dicabut) sementara di sini omsetnya justru bukti yang membenarkan pemindahannya. Jejaknya di `platform_audit_logs`. Keduanya kini resolve ke Rp 100.000. (Catatan sebelumnya di baris ini menyebut salah satunya tidak akan tertagih karena `[BL-058]` — **sudah tidak berlaku**: entri itu selesai 2026-08-07, dan dry-run 2026-08-17 kini menerbitkan tagihan untuk keduanya.)
- **Yang sengaja dibiarkan terbuka:** langganan berpaket `free` yang `trial_ends_at`-nya `null` — hasil seeder, impor, atau pembuatan manual — tidak ikut berpindah. Memindahkan tenant yang tak pernah dijanjikan tanggal berakhir berarti menagihnya karena datanya tidak lengkap. `startTrial()` selalu menulis kolom itu, jadi keadaan ini tidak lahir dari jalur normal mana pun.

---

### [BL-058] Tagihan Upgrade Seat Menelan Tagihan Langganan di Bulan yang Sama — Tenant Lolos Sebulan Tanpa Terlihat
- **Ditemukan:** 2026-08-07 (dry-run penerbitan setelah dua tenant dipindahkan ke `paid-1`)
- **Sumber:** Telaah — dry-run pada 2026-08-17 menerbitkan **1** tagihan padahal dua tenant sama-sama jatuh tempo
- **Status:** Selesai (2026-08-07) — lihat `[HOTFIX] Penjaga Periode-Ganda Menyaring kind, dan Tenant yang Dilewati Berhenti Menghilang dari Hitungan (BL-058)` di `docs/CHANGELOG.md`
- **Prioritas:** High
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php` — `issueDuePeriodInvoices()`: `where('tenant_id')->where('period')->exists()`, **tanpa menyaring `kind`**
  - `app/Http/Controllers/Platform/InvoiceController.php` — `store()`: penjaga yang sama, cacat yang sama
  - `database/migrations/...` — indeks unik `(tenant_id, period, kind)` **sudah** menyaring `kind`; penjaga aplikasinya yang tidak
- **Deskripsi:**
  Penjaga periode-ganda menolak penerbitan bila tenant sudah punya tagihan **apa pun** untuk periode `Y-m` itu. Tagihan penambahan seat (`KIND_UPGRADE`) memakai `period` yang sama dengan tagihan langganan, jadi satu penambahan seat di bulan X **membatalkan tagihan langganan bulan X** — diam-diam.
  Diamnya yang paling mahal: `continue` terjadi **sebelum** harga dihitung, jadi tenant itu tidak masuk hitungan `issued`, `free`, maupun `unpriced`. Keluaran perintah terlihat normal. Satu-satunya cara menyadarinya adalah menghitung sendiri berapa tenant yang seharusnya ditagih.
- **Terbukti pada data nyata:** `Kopi Story` memegang tagihan `upgrade` Rp 0 untuk periode `2026-08` (sisa alur seat gratis sebelum `[BL-049]` ditutup). Dry-run `issueDuePeriodInvoices()` pada 2026-08-17 menghasilkan `terbit=1, gratis=0, tanpa-tarif=0` — padahal **dua** tenant jatuh tempo dengan tarif Rp 100.000. Tenant itu mendapat sebulan gratis tanpa satu baris pun yang mencatatnya.
- **Usulan Perbaikan:**
  **(a)** Kedua penjaga menyaring `kind = KIND_SUBSCRIPTION`. Itu bukan sekadar tambalan — ia **menyelaraskan penjaga aplikasi dengan indeks uniknya**, yang sejak awal sudah memakai `(tenant_id, period, kind)`. Hari ini keduanya menjaga dua hal yang berbeda, persis yang diperingatkan `[BL-050]`.
  **(b)** Tambahkan test yang menerbitkan tagihan langganan untuk tenant yang sudah punya tagihan `upgrade` di periode yang sama. Tanpa itu perbaikannya akan hilang lagi pada penulisan ulang berikutnya.
  **(c)** Pertimbangkan menghitung tenant yang dilewati penjaga sebagai counter tersendiri di keluaran perintah. Tiga counter yang ada semuanya menjelaskan **kenapa** sebuah tagihan tidak terbit; yang keempat ini satu-satunya yang tidak, dan itulah yang membuatnya tak terlihat.
  **(d)** Perbaikan ini **tidak** menunggu `[BL-050]`. Entri itu soal indeks yang membatasi upgrade jadi sekali sebulan; yang ini soal penjaga aplikasi yang menyaring terlalu longgar. Arah keduanya berlawanan, dan yang ini jauh lebih mahal.

### [BL-059] Belum Ada Halaman Bayar — Tagihan Hanya Bisa Lunas lewat Bukti Transfer Manual atau Tombol Peragaan Satu Klik
- **Ditemukan:** 2026-08-07
- **Sumber:** Permintaan pemilik 2026-08-07 — "tambahkan payment gateway atau proses trigger pembayaran... untuk development dan demo besok, buatkan saja halaman payment ala-ala nya"
- **Status:** Selesai (2026-08-07) — lihat `[ADDITION] Halaman Bayar Berdiri di Atas Gateway Tiruan yang Bicara Seperti Gateway Sungguhan (BL-059)` di `docs/CHANGELOG.md`. Butir (i) sengaja tidak ikut dikerjakan dan jadi `[BL-061]`.
- **Prioritas:** High (jalur uang, dan dipakai untuk peragaan ke calon klien)
- **Area Terdampak:**
  - `app/Services/Billing/InvoiceSettlement.php:20-36` — kerangka untuk gateway sudah ditulis beserta tiga pertanyaan yang sengaja belum dijawab (idempotensi, nominal diterima, keaslian panggilan)
  - `app/Http/Controllers/Billing/SimulatedPaymentController.php` — pelunasan peragaan **satu klik, tanpa halaman**; tidak menyerupai alur bayar mana pun
  - `routes/web.php:97-98` — `billing.simulate.store`
  - `resources/js/Pages/Billing/Show.vue:660-700` — daftar tagihan hanya menawarkan "Unggah bukti transfer" dan tombol simulasi
  - `app/Http/Controllers/Billing/UpgradeController.php` — `storeProof()`, jalur bukti transfer manual
  - `database/migrations/2026_07_24_181636_create_invoices_table.php:10-12` — komentarnya masih menyatakan "belum ada payment gateway"
- **Deskripsi:**
  Hari ini sebuah tagihan hanya bisa berpindah ke lunas lewat dua jalan: tenant mengunggah bukti transfer lalu pemilik SaaS memverifikasinya di panel, atau tombol peragaan yang melunasi seketika tanpa bukti apa pun (dikunci `is_demo` + bukan produksi). Tidak ada satu pun yang menyerupai pengalaman membayar: tidak ada pilihan kanal, tidak ada nomor VA/QR, tidak ada status "menunggu pembayaran", tidak ada kedaluwarsa. Untuk peragaan besok yang dibutuhkan adalah alurnya — **pilih kanal → dapat instruksi → bayar → akses pulih sendiri** — bukan uangnya.
  Godaan yang harus ditolak di sini: membuat "halaman bayar palsu" yang tombolnya memanggil `settle()` langsung. Itu menambah **jalur uang ketiga** yang bentuknya sama sekali berbeda dari jalur produksi nanti, sehingga yang diuji besok bukan yang akan dipakai — dan `InvoiceSettlement` justru dibuat untuk mencegah percabangan semacam itu. Halaman ala-ala yang benar adalah **gateway tiruan yang bicara persis seperti gateway sungguhan**: ia menerbitkan tagihan ke penyedia, mengembalikan instruksi, lalu memanggil balik lewat webhook. Bila bentuk kabelnya sudah benar sejak sekarang, memasang Sumopod (`[BL-060]`) tinggal menukar satu driver.
- **Usulan Perbaikan:**
  **(a) Kontrak + dua driver.** `app/Services/Billing/Gateways/PaymentGateway` dengan dua method saja: `createCharge(Invoice $invoice, string $channel): PaymentAttempt` dan `verifyCallback(Request $request): CallbackResult`. Implementasi pertama `FakeGateway`; `SumopodGateway` menyusul di `[BL-060]` tanpa menyentuh pemanggilnya. Driver dipilih dari config — cukup kunci `payment` di `config/subscription.php`, tidak perlu berkas config baru.
  **(b) Tabel `payment_attempts`.** `invoice_id`, `gateway`, `channel`, `external_id`, `amount`, `status` (`pending`/`paid`/`expired`/`failed`/`mismatch`), `expires_at`, `paid_at`, `payload` (json), unik `(gateway, external_id)`. Jangan menempelkan kolom gateway ke `invoices`: satu tagihan wajar punya beberapa percobaan (kedaluwarsa lalu diulang, ganti kanal), dan `invoices` sudah unik per `(tenant_id, period)` sehingga tidak bisa menampung banyak percobaan.
  **(c) Tiga halaman/rute tenant.** `GET /langganan/tagihan/{invoice}/bayar` (pilih kanal: QRIS, VA, e-wallet) → `POST` membuat charge → `GET /langganan/pembayaran/{attempt}` halaman instruksi: nomor VA/QR tiruan, hitung mundur kedaluwarsa, dan status yang menyegar sendiri (polling Inertia v2, bukan reload manual). Semua di bawah `role:owner` seperti jalur bukti bayar, dan `billing.*` sudah masuk `ALWAYS_ALLOWED` di `EnsureSubscriptionActive` sehingga tenant yang ditangguhkan tetap bisa membukanya — periksa ulang bahwa rute baru ikut terdaftar di sana, kalau tidak tenant terkunci dari satu-satunya jalan keluarnya.
  **(d) Panel peragaan memanggil webhook, bukan `settle()`.** Di halaman instruksi, driver fake menampilkan "Tandai Lunas / Gagal / Kedaluwarsa". Tombol itu mengirim callback ke `POST /webhook/payment/{gateway}` — endpoint yang sama, tanda tangan yang sama (HMAC dengan secret fake), penanganan yang sama seperti gateway sungguhan. Inilah yang membuat peragaan besok bernilai sebagai uji jalur produksi.
  **(e) Webhook.** Di luar grup `auth`/`tenant`, dikecualikan CSRF. **Tenant harus di-resolve eksplisit dari `external_id` → `payment_attempts` → `invoice`**: `TenantScope` tidak aktif di luar permintaan bertenant (pelajaran yang sama dari job platform), jadi query model bertenant di webhook akan bocor lintas tenant kalau diandalkan begitu saja. Jawab **200 untuk semua notifikasi yang bentuknya sah**, termasuk yang berulang, supaya gateway berhenti mengirim ulang; tolak dengan 4xx hanya saat tanda tangannya salah.
  **(f) Tiga jawaban atas pertanyaan di `InvoiceSettlement`.** *Idempotensi:* unik `(gateway, external_id)` + penjaga `isPaid()` yang sudah ada; notifikasi kedua tidak melunasi apa pun dan tetap dijawab 200. *Nominal:* bila jumlah diterima ≠ `invoice->amount`, **jangan lunasi** — tandai attempt `mismatch` dan munculkan di panel platform untuk diputuskan orang. *Keaslian:* verifikasi tanda tangan adalah syarat masuk; `settle()` mempercayai pemanggilnya sepenuhnya, jadi tidak ada tempat kedua yang memeriksa.
  **(g) Sumber pelunasan dipisah per driver.** Tambahkan `SOURCE_GATEWAY` dan `SOURCE_GATEWAY_FAKE` yang berbeda, bukan satu nilai `gateway`. Pelunasan tiruan tidak boleh bisa menyamar sebagai pembayaran sungguhan di laporan pendapatan mana pun.
  **(h) Driver fake haram di produksi.** Gerbangnya di tempat driver di-resolve: bila `app()->environment('production')` dan driver bernilai `fake`, lempar exception saat boot — bukan diam-diam jatuh ke manual. Alasannya sama persis dengan `canSimulate()`: gateway palsu yang bisa melunasi tagihan adalah lubang yang `provisional_blocked` dibangun untuk menutup, dan salah setel satu variabel `.env` tidak boleh cukup untuk membukanya.
  **(i) Nasib tombol simulasi lama.** Setelah (a)–(h) berdiri, `SimulatedPaymentController` jadi jalur ketiga yang mubazir. **Jangan dicabut sebelum peragaan** — mencabut satu-satunya jalur yang sudah teruji sehari sebelum demo tidak ada untungnya. Cabut setelah jalur fake gateway lulus tes, dan catat pencabutannya di `CHANGELOG.md`.
  **(j) Jalur bukti transfer manual tetap hidup.** Ia bukan peninggalan yang digantikan gateway: banyak calon pengguna memang membayar dengan transfer biasa, dan ia satu-satunya jalur yang tetap jalan ketika gateway sedang mati.
  **(k) Tes (Pest, feature).** Halaman bayar menolak tagihan tenant lain (403); attempt kedua tidak menerbitkan charge ganda selama yang pertama masih `pending`; webhook dengan tanda tangan salah ditolak dan tagihan tetap `unpaid`; notifikasi lunas yang sama dua kali hanya melunasi sekali dan keduanya 200; nominal beda → `mismatch`, tagihan tetap `unpaid`; attempt kedaluwarsa tidak bisa dilunasi; tagihan upgrade (bukan hanya langganan) juga bisa dibayar; driver fake gagal di-boot saat environment produksi.
- **Catatan urutan:** (a)+(b)+(c)+(d) sudah cukup untuk peragaan besok. (e)–(h) sebaiknya menyusul di sesi yang sama karena justru di situ letak nilainya bagi `[BL-060]`.

### [BL-046] Paket Kedua Tidak Punya Tempat Berpijak — CRUD-nya Ada, Pembacanya Tidak
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik — "nanti ada 2 paket, 1 dasar dengan golongan gratis, dan ada premium, bedanya adalah bisa menaikkan setup limit, dari 5 perhari jadi 10 per hari"
- **Status:** Selesai (butir 1 & 3: 2026-08-01; butir 2 & d: 2026-08-06) — lihat `[ADDITION] Paket Kedua Akhirnya Bisa Dihuni: Tenant Bisa Dipindahkan, Seat Bayarnya Ikut (BL-046)` di `docs/CHANGELOG.md`
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
- **Pemutakhiran 2026-08-06 — butir (2) SELESAI, entri ditutup.** Lihat entri CHANGELOG *"Paket Kedua Akhirnya Bisa Dihuni: Tenant Bisa Dipindahkan, Seat Bayarnya Ikut (BL-046)"*. Yang sekarang berjalan:
  - `SubscriptionService::changePlan()` — satu-satunya tempat `plan_id` berpindah setelah `startTrial()`. Dipanggil `Platform\SubscriptionController::updatePlan()` lewat `PUT /platform/subscriptions/{subscription}/plan`, digerbang `platform.can:subscriptions`, wajib beralasan, dan tercatat `subscriptions.plan.update` (sensitif) berikut paket, batas pengguna, dan kuota AI lama-barunya.
  - **Seat tambahan yang sudah dibayar ikut pindah**: batas barunya = jatah paket tujuan + selisih yang sudah dibeli tenant lewat tagihan `KIND_UPGRADE`. Dasar (jatah 1, batas 3) → Premium (jatah 5) menghasilkan 7.
  - Tarif periode berjalan tidak disentuh — `price_locked` sudah memegangnya, dan paket barunya berlaku pada tagihan berikutnya.
  - Panel "Paket" di tab Langganan rincian tenant memajang tarif dasar, jatah pengguna, dan kuota AI paket yang sedang dihuni; katalog tujuannya hanya terkirim ke pemegang modul `subscriptions`.
  - **Yang sengaja TIDAK dibuat:** permintaan naik paket mandiri oleh owner. Usulan (d) menyebutnya boleh menyusul, dan hari ini ia belum berarti apa-apa — `extra_seat_price` paket `dasar` masih Rp 0 (`[BL-049]`) dan tarif Premium belum ditetapkan (`[BL-041]`(a)), jadi jalur mandiri apa pun akan menerbitkan tagihan nol rupiah. Bila kelak dibuat, ia harus **menambah** jalur ini, bukan menggantikannya: pemindahan karena omset adalah keputusan pemilik SaaS.

### [BL-045] Keadaan Hanya-Baca Sudah Ditegakkan tapi Tak Terlihat, dan Membayar Belum Memulihkan Akses Sendiri
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik — "buat peringatan dalam dashboard owner jika misal sudah harus membayar atau melewati waktunya ... tidak bisa transaksi atau melakukan apa pun selain read saja ... bisa melakukan aksi di bagian pembayaran ... maka akan refresh web dan memperbarui akses dari read only menjadi normal kembali"
- **Status:** Selesai (2026-08-06) — lihat `[ADDITION] Keadaan Langganan Terlihat di Setiap Layar, & Satu Pintu Menuju Aktif (BL-045)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Http/Middleware/EnsureSubscriptionActive.php:62-68` — penegakan hanya-baca **sudah ada**: `grace` + method tidak aman → ditolak
  - `app/Http/Middleware/EnsureSubscriptionActive.php:54-60` — `suspended` → dialihkan ke halaman langganan
  - `resources/js/Pages/Owner/Dashboard.vue:66-94,136-182` — kartu ringkasan langganan **sudah ada** (hasil `[BL-040]`, arsip 2026-07-31)
  - `app/Http/Controllers/Platform/InvoiceController.php:173+` — `verify()`: satu-satunya tempat tenant kembali `active`, dijalankan **pemilik SaaS**
  - `routes/web.php:53-75` — grup `billing.*`: owner mengunggah bukti bayar, tidak melunasi apa pun sendiri
- **Deskripsi:**
  Tiga dari empat hal yang diminta catatan ini **sudah benar-benar berjalan** dan tidak perlu dibangun ulang: data tidak dihapus, tulisan ditolak sementara baca tetap terbuka, dan halaman langganan/tagihan selalu bisa dibuka lewat `ALWAYS_ALLOWED`. Dashboard owner pun sudah memajang keadaan langganan beserta tagihan terbuka.
  Dua hal yang benar-benar belum ada. **(1) Peringatannya hanya di satu layar.** Kartu itu hidup di Dashboard; kasir yang membuka POS langsung, atau owner yang seharian di halaman Produk, tidak melihat apa-apa sampai ia menekan Simpan dan mendapat `back()->with('error', ...)`. Penolakannya benar, tapi kejutan. **(2) Membayar tidak memulihkan akses sendiri.** Tidak ada jalur yang membuat akses pulih segera setelah tenant membayar, jadi "refresh lalu normal kembali" yang diminta catatan belum ada wujudnya.
- **Keputusan pemilik 2026-08-06 (butir 2):** simulasi digerbang **penanda tenant peragaan**, dengan maksud pemakaian di lingkungan non-produksi, plus kerangka payment gateway untuk nanti. Diterapkan sebagai dua gerbang yang keduanya wajib — `tenants.is_demo` DAN lingkungan bukan produksi.
- **Catatan saat dikerjakan (yang usulan tidak sebutkan):**
  - **Prop bersamanya sengaja `null` untuk tenant yang tidak dibatasi.** Usulan (1) hanya menyebut "naikkan jadi prop bersama"; yang tidak disebut adalah ongkosnya. Menggerbangnya pada `status` — yang sudah termuat di baris tenant — membuat mayoritas request tidak menyentuh query tambahan sama sekali. Konsekuensinya: peringatan *sebelum* periode lewat tidak ikut di pita dan tetap di kartu Dashboard.
  - **Sisi kasir tidak punya layout bersama.** Pitanya menumpang di `CashierTopbar`, satu-satunya hal yang dipakai kelima halaman kasir; akar komponennya berubah jadi pembungkus `shrink-0`.
  - **Kasir tidak ditautkan ke halaman Langganan** — setiap tombol di sana `role:owner`.
  - **`InvoiceSettlement` lahir dari usulan (2).** Usulan meminta "pakai kembali `verify()`", tapi `verify()` hidup di controller ber-`auth:platform` yang mustahil dipanggil dari sisi tenant. Isinya diangkat jadi service; `verify()` kini pemanggil. Itu sekaligus kerangka payment gateway yang diminta.
  - **Kolom baru `invoices.settled_via`.** Begitu ada lebih dari satu cara melunasi, `verified_by` yang kosong jadi ambigu — simulasi, webhook, atau baris lama.
  - **Ditemukan saat mengerjakannya:** menguji gerbang produksi lewat HTTP request tidak membuktikan apa pun — berpindah ke lingkungan `production` sekalian menyalakan penjaga CSRF, sehingga requestnya tertolak 419 sebelum gerbangnya sempat dinilai. Diuji di tingkat `canSimulate()`.
  - **Belum ada UI untuk menyalakan `is_demo`** — disetel seeder. Toggle "tenant ini boleh melewati pembayaran" adalah permukaan risiko tersendiri.
  - Untuk produksi, yang sebenarnya dibutuhkan tetap payment gateway; kerangkanya ada di `InvoiceSettlement`, keputusan memasangnya belum diambil.

### [BL-030] Tanggal Jatuh Tempo Langganan Ikut Meluber di Bulan Pendek
- **Ditemukan:** 2026-07-31
- **Sumber:** Sisa penyisiran `[BL-029]` — sengaja TIDAK ikut diperbaiki di sana karena menyangkut semantik penagihan, bukan sekadar salah turunan periode
- **Status:** Selesai (2026-08-05) — lihat `[DECISION] Tanggal Tagih Jadi Jangkar: Bulan Pendek Menjepit Sementara, Tidak Menggeser Selamanya (BL-030)` di `docs/CHANGELOG.md`
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
- **Keputusan pemilik 2026-08-05 (menjawab poin 1):** jatuh tempo **28 Februari**; periode berikutnya diturunkan dari **jangkar tanggal tagih**, bukan dirantai dari akhir periode sebelumnya; dan periode **menyambung dari periode sebelumnya**, bukan mulai dari hari verifikasi bukti bayar. Rinciannya di entri CHANGELOG penutup.
- **Catatan saat dikerjakan (yang usulan tidak sebutkan):**
  - **Poin 2 usulan ("ganti ke `addMonthNoOverflow()`") tidak cukup sendirian, dan pemilik memilih yang lebih jauh.** Rantai tanpa jangkar tetap menggeser — 31 → 28 → 28 → 28 — hanya sekali dan permanen, yang persis keluhan "tidak pernah kembali". Karena itu ditambahkan kolom `subscriptions.billing_anchor_day`. **Ini membatalkan perkiraan "tanpa migrasi" di entri ini:** jangkar harus disimpan, tidak bisa disimpulkan, karena begitu sebuah periode terjepit di bulan pendek hari aslinya hilang dari data mana pun.
  - **Poin 3 kosong biayanya seperti diperkirakan:** kedua langganan yang ada berjangkar tanggal 24 dan belum pernah melewati bulan pendek, jadi backfill migrasi cukup membaca `current_period_end` apa adanya.
  - **Ditemukan saat mengerjakannya:** `canSwitchTrack()` dan `trackSwitchAvailableAt()` menghitung jarak 3 bulan dari dua arah berlawanan, yang berhenti setara begitu penjaga luberan dipasang — layar menjanjikan 28 Februari sementara gerbangnya baru terbuka 2 Maret. Disatukan jadi satu perhitungan.
  - **Sisa yang sengaja ditinggalkan:** aturan "tunggakan tidak ditumpuk" (satu pembayaran memulihkan satu periode ke depan) perlu ditinjau ulang saat `[BL-044]` dikerjakan, karena penerbit tagihan otomatis akan memberi tiap bulan terlewat tagihannya sendiri.

### [BL-043] Login Platform Berhasil, Lalu Mendarat di Area Tenant
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik saat menjalankan panel platform — "fix redirecting ketika login sebagai platform account, masalahnya adalah mengarahkan ke login, ke `/` dan tidak mengarahkan ke `/platform`"
- **Status:** Selesai (2026-08-05) — lihat `[HOTFIX] Pengalihan Setelah Masuk Memilah Dua Dunia, Bukan Cuma Sebelum Masuk (BL-043)` di `docs/CHANGELOG.md`
- **Prioritas:** High
- **Area Terdampak:**
  - `bootstrap/app.php:80-82` — `redirectGuestsTo()` dipasang; **`redirectUsersTo()` tidak pernah dipasang**
  - `routes/web.php:260` — grup `guest:platform` membungkus `/platform/login` dan seluruh alur reset kata sandi
  - `app/Http/Controllers/Platform/AuthController.php:48` — `redirect()->intended(route('platform.dashboard'))`
  - `app/Http/Middleware/EnsureTenant.php:15` — `redirect()->route('login')` untuk pengunjung tanpa tenant
  - `routes/web.php:410` — `/` adalah landing publik (`Public\LandingController@index`), bukan pengalih
- **Deskripsi:**
  Dua jalur berbeda, dua gejala berbeda, keduanya berakhir di luar `/platform` — persis dua tujuan yang disebut catatan.
- **Terbukti langsung di browser 2026-08-01.** Cabang (a) direproduksi: masuk sebagai `platform@sapi.test`, mendarat benar di `/platform`, lalu membuka `http://localhost:8001/platform/login` → berakhir di `http://localhost:8001/` dengan judul halaman "SAPI - Smart AI POS & Inventory untuk UMKM", yaitu landing publik. Bukan dugaan.
  Satu penyempurnaan dari pengujian itu: cabang (b) **tidak selalu muncul**. Login pada sesi peramban yang bersih mendarat benar di `platform.dashboard`, karena `url.intended` memang belum pernah terisi. Jadi gejalanya bersyarat — ia hanya menyerang peramban yang pernah menyentuh area tenant dalam keadaan keluar. Itu menjelaskan mengapa cacat ini bisa lolos: pengujian di jendela penyamaran yang baru akan selalu lulus.
- **Dugaan Penyebab — sudah ditelusuri, bukan dugaan lagi:**
  **(a) Mendarat di `/`.** `guest:platform` memakai `RedirectIfAuthenticated` bawaan framework. Tujuannya berasal dari `redirectTo()` → `defaultRedirectUri()`, yang mencari rute bernama persis `dashboard` lalu `home`. Aplikasi ini tidak punya keduanya — rutenya bernama `owner.dashboard` dan `platform.dashboard` (dipastikan lewat `php artisan route:list --name=dashboard`), jadi jatuh ke `return '/'`. Akibatnya: akun platform yang sesinya masih hidup lalu membuka `/platform/login` (bookmark, tombol Back, atau mengetik ulang) dilempar ke landing publik — halaman yang tombol utamanya "Masuk" ke login **tenant**. `redirectGuestsTo` di `bootstrap/app.php:80` sudah memilah tamu dengan benar; kembarannya untuk pengguna yang **sudah** masuk tidak pernah ditulis.
  **(b) Mendarat di `login` tenant.** `redirect()->intended()` membaca kunci sesi `url.intended`, dan sesi itu **satu** untuk kedua guard. Urutan yang sangat mungkin terjadi di satu peramban: buka `/owner/dashboard` dalam keadaan keluar → `Authenticate` menyimpan `url.intended = /owner/dashboard` → pindah ke `/platform/login` dan masuk sebagai akun platform → `intended()` menang atas argumen bawaannya dan mengirim ke `/owner/dashboard` → `EnsureTenant` melihat pengguna tanpa tenant → `redirect()->route('login')`. Login berhasil, audit log mencatat `login.success`, tapi layar yang muncul adalah halaman masuk pemilik usaha.
- **Cara Perbaikan (yang benar-benar dikerjakan):**
  **(a)** `$middleware->redirectUsersTo(...)` dipasang di `bootstrap/app.php` bersebelahan dengan `redirectGuestsTo`, memakai pemilahan prefiks yang sama.
  **(b)** `Platform\AuthController::login()` tidak lagi memakai `intended()` polos: `url.intended` di-`pull` lalu **disaring** — hanya dihormati bila host-nya milik aplikasi ini dan path-nya benar-benar berada di bawah segmen `platform`. Dipilih penyaringan, bukan penghapusan, supaya tautan langsung ke `/platform/tenants/7` tetap mengantar ke sana setelah masuk.
- **Catatan saat dikerjakan (yang usulan di atas tidak sebutkan):**
  - **Sisi tenant ikut berubah, dan itu tidak bisa dihindari.** `redirectUsersTo` adalah satu callback global, jadi cabang non-platform harus mengembalikan sesuatu. Ia memilih berdasarkan peran (owner → `owner.dashboard`, kasir → `cashier.pos`) persis seperti `Auth\AuthController::login()`, alih-alih membuang keduanya ke landing seperti perilaku lama.
  - **`pull` bukan `get`**, supaya tujuan yang ditolak ikut hangus dan tidak menunggu di sesi untuk menyerang perpindahan halaman berikutnya.
  - **Prefiks dicocokkan sebagai segmen utuh** (`platform` atau `platform/...`), bukan awalan string — `'/platformx'` bukan area platform.
  - Dari 5 test yang ditambahkan, **4 gagal sebelum perbaikan ini**. Satu yang sudah lulus sejak awal (`tujuan platform tetap dihormati`) sengaja tetap ditulis: ia menjaga agar perbaikannya tidak berlebihan.

### [BL-026] Identitas Pesanan (Nama / No. Meja / Kode Panggil) Belum Bisa Diisi dari Kasir
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "buatkan menu untuk menambahkan sistem nama, sistem nomor meja, atau sistem kode (misal mau pakai untuk pemanggilan)"
- **Status:** Selesai (2026-08-01) — lihat `[ADDITION] Identitas Pesanan Bisa Diisi dari Kasir, & Nomor Panggil Lepas dari Papan Dapur (BL-026)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Models/Transaction.php:59-60` — `queue_number`, `customer_name`, `table_number` **sudah ada** dan fillable
  - `app/Services/TransactionService.php:106-107` & `:191-192` — checkout & self-order sudah menulis keduanya
  - `app/Services/TransactionService.php:85-92` — `queue_number` hanya dialokasikan bila fitur `kitchen_queue` aktif
  - `app/Http/Requests/StoreTransactionRequest.php:66` — hanya `customer_name` yang divalidasi; **`table_number` tidak ada**, jadi mustahil dikirim dari POS
  - `resources/js/Pages/Cashier/POS.vue:1004-1046` — modal nama pelanggan hanya muncul di jalur "Tunda Bayar"
  - `app/Http/Resources/QueueCardResource.php:30-36` — papan antrian sudah menampilkan ketiganya
- **Yang SUDAH ada (jangan dibangun ulang):** kolomnya, alokator nomor harian (`DailySequenceAllocator`), dan tampilannya di papan antrian sudah berdiri. Jalur API self-order & mobile sudah mengirim `customer_name` + `table_number`. **Yang hilang persis satu lapis: cara kasir mengisinya.**
- **Deskripsi:** Di POS, identitas hanya bisa diberikan lewat modal "Tunda Bayar", dan hanya berupa nama bebas. Transaksi bayar-langsung tidak bisa diberi identitas apa pun — padahal justru pesanan inilah yang perlu dipanggil saat siap. `table_number` bahkan tidak lolos validasi request, jadi tidak ada jalan mengirimnya dari kasir sekalipun UI-nya dibuat.
- **Usulan Perbaikan:**
  1. Setting `order_identity_mode` di Owner: `none` / `name` / `table` / `code`. Satu mode aktif per outlet — warung yang memakai ketiganya sekaligus akan mengisi nol dari tiga.
  2. **Satu modal identitas dipakai kedua jalur** (bayar langsung & tunda bayar), bukan hanya open bill. Bentuk input mengikuti mode: teks bebas untuk nama, papan angka untuk meja, dan untuk `code` cukup tampilkan nomor yang dialokasikan sistem.
  3. Tambahkan `table_number` ke `StoreTransactionRequest`.
  4. **Lepaskan `queue_number` dari syarat `kitchen_queue`.** Nomor panggil dan papan dapur adalah dua kebutuhan berbeda — warung yang hanya ingin memanggil pelanggan tidak seharusnya dipaksa menyalakan papan dapur.
  5. Identitas harus ikut tercetak di struk dan tampil di riwayat; identitas yang hanya hidup di layar kasir tidak menolong siapa pun saat pesanan siap.
- **Catatan saat dikerjakan (yang usulan di atas tidak sebutkan):**
  - **Tunda bayar tetap SELALU ditanya**, jatuh ke nama saat mode `none`. Usulan poin 1 kalau dibaca harfiah akan menghapus prompt nama yang sudah bekerja pada outlet yang belum memilih mode — tagihan yang menunggu dibayar tetap harus bisa dikenali lagi nanti.
  - **Identitas ikut menumpang outbox offline** (`useOfflineQueue` → `SyncOfflineTransactionsRequest` → `commitOffline`). Tanpa itu, pesanan yang sudah dinamai kasir kehilangan namanya begitu tersinkron. **Nomor panggil sengaja tidak ikut** — nomor yang lahir berjam-jam setelah struknya dibawa pulang tidak memanggil siapa pun.
  - Poin 4 dikerjakan **tanpa** ikut melepas `fulfillment_status`. Nomor panggil tidak memasukkan pesanan ke papan; kalau ikut dilepas, outlet yang hanya ingin memanggil akan menumbuhkan papan yang tidak pernah dibuka — persis timbunan yang dibersihkan migrasi `backfill_stale_fulfillment_status`.

### [BL-042] Daftar Tenant Memajang Kolom Informasi, Bukan Jalan Masuk ke Rincian — dan Tautan yang Ada Bisa Berujung 403
- **Ditemukan:** 2026-08-01
- **Sumber:** Permintaan pemilik — "pada halaman daftar tenant, ganti informasi kolom yang terdaftar menjadi action button untuk melihat detail... di dalam detail barulah kita bisa liat informasi detailnya... bisa menggunakan konsep nav tab"
- **Status:** Selesai (2026-08-01) — lihat `[ADDITION] Rincian Tenant Bertab, dan Daftarnya Kembali Jadi Daftar (BL-042)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/Pages/Platform/Tenants/Index.vue:15-21` — lima kolom (`name`, `owner`, `business_type`, `users`, `registered`) tanpa satu pun kolom aksi
  - `resources/js/Pages/Platform/Tenants/Index.vue:46-51` — satu-satunya jalan ke rincian adalah nama tenant yang diam-diam menaut ke `/platform/subscriptions/{id}`; tidak ada penanda visual bahwa ia bisa diklik
  - `routes/web.php:288-291` vs `routes/web.php:299-308` — daftarnya digerbang `platform.can:tenants`, rincian yang ditautnya digerbang `platform.can:subscriptions,payments`
  - `app/Http/Middleware/EnsurePlatformModule.php:29-34` — gerbangnya `abort(403)`; staf yang hanya dipegangi modul `tenants` akan menabrak halaman error saat menekan nama tenant
  - `resources/js/Pages/Platform/Subscriptions/Show.vue:187-479` — halaman rincian sudah ada, tapi satu gulungan panjang: ringkasan → akun → langganan → riwayat tagihan → omzet
  - `app/Services/Platform/AccountOverview.php:39-54` — payload rincian tidak memuat kapabilitas kasir tenant sama sekali
  - `app/Models/Tenant.php:94-102` — `hasFeature()` sudah menjadi satu-satunya pintu untuk `kitchen_queue`, `self_order`, `ai`; nilainya tidak pernah dikirim ke panel platform
- **Deskripsi:**
  Halaman `/platform/tenants` menjawab pertanyaan "siapa saja klien kita" dengan memipihkan tiap tenant jadi lima kolom, lalu berhenti di situ. Rincian yang diminta sebenarnya **sebagian besar sudah dibangun** — `Platform/Subscriptions/Show.vue` sudah menampilkan paket, tarif berjalan (`price_locked`), batas pengguna beserta asal-usulnya, periode, label jalur harga (`Harga Tetap` / `Harga Adaptif` lewat `PRICING_TRACK` di `resources/js/support/platform.js:71-81`), kelompok harga bracket, dan seluruh riwayat tagihan dengan nominalnya. Masalahnya ada tiga:

  **(1) Jalan masuknya tidak terlihat dan tidak selalu sah.** Rinciannya hidup di bawah modul *Langganan & Tagihan*, bukan di bawah *Daftar Tenant*. Bagi pemilik SaaS yang memegang semua modul ini tidak terasa, tapi seluruh gerbang modul di panel ini dibuat justru supaya staf bisa diberi sebagian — dan staf yang hanya diberi `tenants` mendapat halaman yang setiap barisnya menaut ke 403.

  **(2) Rinciannya satu gulungan.** Empat urusan yang berbeda umur dan berbeda kepekaan (identitas akun, langganan, riwayat tagihan, omzet) berbaris vertikal tanpa pemisah. Riwayat tagihan tidak dipotong (`AccountOverview.php:127-136` sengaja mengambil seluruhnya), jadi tenant berumur setahun mendorong panel omzet keluar layar.

  **(3) "Benefit sistem kasir" memang belum pernah ada datanya.** Yang ditanyakan pemilik — fitur apa saja yang menyala untuk kasir tenant ini — tidak bisa dijawab halaman mana pun sekarang. `kitchen_queue_enabled`, `self_order_enabled`, dan `ai_enabled` tersimpan di tabel `tenants` dan dipakai di seluruh sisi tenant, tapi tidak pernah ikut di payload platform. Ini satu-satunya bagian permintaan yang benar-benar butuh data baru; sisanya soal penataan.
- **Usulan Perbaikan:**
  **(a)** Pindahkan kepemilikan rincian ke *Daftar Tenant*: rute `GET /platform/tenants/{tenant}` di bawah `platform.can:tenants`, dirakit dari `AccountOverview` yang sudah ada (ia sudah menyaring isi per modul penglihatnya, jadi staf ber-`tenants`-saja akan menerima `subscription`/`invoices` bernilai `null` — bukan 403). Alamat lama `/platform/subscriptions/{tenant}` tetap hidup, mengikuti pola pengalihan yang sudah dipakai `routes/web.php:354`.
  **(b)** Di daftarnya, sisakan kolom yang benar-benar membedakan satu baris dari yang lain (nama + slug, pemilik, status/tanda) dan ganti sisanya dengan satu kolom aksi **Lihat detail** di ujung kanan. Jenis usaha, jumlah akun, dan tanggal terdaftar pindah ke dalam rincian.
  **(c)** Beri rinciannya nav tab — usul: **Ikhtisar** (identitas, status, pemilik, jenis usaha, jumlah akun) · **Langganan** (paket, jalur harga tetap/adaptif beserta penjelasannya, kelompok harga, batas pengguna) · **Tagihan** (riwayat + nominal + tindakan verifikasi) · **Kapabilitas** (fitur kasir yang menyala) · **Omzet** (tetap di balik tautan beraudit tersendiri, tab-nya hanya mengantar ke sana). Tab yang modulnya tidak dipegang penglihatnya **tidak dirender sama sekali**, sejalan dengan prinsip `AccountOverview`: yang tidak boleh dilihat tidak ikut terkirim.
  **(d)** Tambahkan kapabilitas ke `AccountOverview::tenantPayload()` dengan membaca lewat `Tenant::hasFeature()`, bukan menyentuh kolomnya langsung — supaya menambah fitur baru kelak tetap satu tempat. **Keputusan pemilik 2026-08-01: tab ini HANYA MEMBACA.** Tidak ada tombol menyalakan/mematikan fitur kasir dari sisi platform, mengikuti alasan yang sama seperti pencabutan kuasa ubah tipe usaha di `[BL-015]`: mengubah cara kerja usaha orang tanpa sepengetahuannya bukan kewenangan penyedia layanan. Yang diberikan halaman ini adalah kuasa MENGETAHUI — pemilik SaaS tetap perlu tahu fitur apa yang menyala saat menjawab keluhan atau menjelaskan tarif.
  **(e)** Perlu diperhatikan saat mengerjakan: `PlatformArchTest` melarang controller platform mengimpor model operasional tenant, dan tab Kapabilitas tidak melanggarnya selama nilainya dibaca dari `Tenant` sendiri.
- **Koreksi saat dikerjakan:**
  Butir (a) menulis rute rincian digerbang `platform.can:tenants` saja — **itu keliru**, dan keliruan yang sama persis dengan yang sedang diperbaiki, hanya terbalik arahnya. Daftar langganan juga bermuara ke halaman ini, jadi menyempitkan gerbangnya ke satu modul akan membuat pemegang modul tagihan menabrak 403 di tautan tenant-nya. Yang dipakai: `platform.can:tenants,subscriptions,payments` ("salah satu cukup"), dengan isi tetap disaring per modul di `AccountOverview`. Karena itu pula `can.tenants` ikut dikirim — tombol "kembali" harus menunjuk daftar yang pembacanya memang berhak membukanya.
  Rute omzet ikut pindah (`/platform/tenants/{tenant}/revenue`) meski butir (a) hanya menyebut rincian akun: ia merender komponen yang sama dan sekarang mendarat di tab Omzet, jadi meninggalkannya di bawah `/subscriptions` hanya akan menyisakan satu alamat yang tidak lagi menggambarkan isinya. Keduanya mengalihkan dari alamat lama.

### [BL-040] Halaman Langganan & Tagihan Tidak Punya Pintu Masuk dari Dashboard
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "sampai bisa melihat tagihan dan payment akun yang terhubung itu masuk kategori apa, pembayaran selanjutnya, berapa mau di bayar, kapan membayar, apakah status subsidi dll, langsung dalam owner dashboard"
- **Status:** Selesai (2026-07-31) — lihat `[ADDITION] Pintu Masuk Langganan & Ringkasan Tagihan di Dashboard (BL-040)` di `docs/CHANGELOG.md`
- **Prioritas:** High (halamannya sudah jadi; yang hilang cuma jalan menuju ke sana)
- **Area Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue:100-138` — daftar navigasi lengkap; **tidak ada** entri langganan/tagihan
  - `resources/js/Pages/Owner/Dashboard.vue` — tidak menyebut langganan sama sekali
  - `app/Http/Controllers/Billing/SubscriptionController.php:36-97` — sudah menyiapkan nama paket, harga, jalur harga, seat terpakai, akhir periode, tanggal suspend, status subsidi berikut bracket-nya, dan 12 tagihan terakhir dengan jatuh tempo serta status buktinya
- **Deskripsi:**
  Hampir seluruh yang diminta pemilik **sudah dirender** di `/langganan` — kategori jalur, nominal, jatuh tempo, status subsidi, unggah bukti bayar. Yang tidak ada adalah cara menemukannya: pencarian `/langganan` di seluruh `resources/js` hanya menemukan rujukan dari dalam halaman Billing itu sendiri. Praktis, owner baru sampai ke sana kalau mengetik URL-nya atau kalau langganannya sudah bermasalah dan middleware melemparnya ke sana — persis kebalikan dari yang diinginkan.
- **Usulan Perbaikan:**
  Tambahkan entri "Langganan & Tagihan" di grup "Pengaturan" pada `OwnerLayout` (`ownerOnly: true`). Di dashboard, tambahkan satu kartu ringkas — jalur harga, tagihan berjalan beserta nominal dan jatuh temponya — yang menaut ke halaman penuh. Munculkan kartu itu lebih menonjol saat status tenant `grace`.
- **Koreksi saat dikerjakan:**
  Usulan di atas menulis "`ownerOnly: true`, sejalan dengan `role:owner` di rutenya" — **itu keliru**. Rute `/langganan` justru sengaja TIDAK digerbang `role:owner`; ia terbuka untuk semua pengguna tenant supaya kasir yang terlempar ke sana saat tenant ditangguhkan tidak mendarat di 403. Yang `ownerOnly` hanya pintu masuk tetapnya, dengan alasan yang berbeda: tagihan adalah urusan owner dengan penyedia layanan, bukan bagian dari pekerjaan kasir.

### [BL-025] Saran Jual Belum Bisa Diwajibkan, dan Tombol Bayar Nonaktif Tanpa Penjelasan
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "pada menu saran, buatkan setup untuk bisa diatur apakah wajib dilakukan penawaran atau bisa di abaikan (di setting pada menu Owner), tidak akan bisa bayar sampai penawaran itu diselesaikan … serta aktifkan component alert pemberitahuan error nya mengapa bayar button disabled"
- **Status:** Selesai (2026-07-31) — lihat `[ADDITION] Penawaran Wajib Diselesaikan & Status "Ditolak" Terpisah (BL-025)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/composables/useUpsell.js:129` — `dismiss()` bebas tanpa syarat apa pun
  - `resources/js/Components/UpsellStrip.vue:94` — label tombol masih "Tawarkan"
  - `resources/js/Components/UpsellStrip.vue:100` — tombol × bermakna "tutup saran", bukan "pelanggan menolak"
  - `resources/js/Pages/Cashier/POS.vue:952` — BAYAR hanya nonaktif karena keranjang kosong / `processing`
  - `resources/js/Components/PaymentModal.vue:317` — tombol Bayar di modal nonaktif tanpa keterangan penyebab
  - `app/Http/Controllers/Owner/SettingsController.php:41-46` — daftar `features` hanya `kitchen_queue`, `self_order`, `ai`
- **Deskripsi:** Fiturnya **belum ada sama sekali** — ini penambahan, bukan perbaikan. Saat ini saran bisa ditutup begitu saja dan checkout tetap jalan, sehingga owner tidak punya cara memastikan penawaran benar-benar disampaikan. Terpisah dari itu, tombol bayar yang nonaktif tidak pernah menjelaskan sebabnya, baik di POS maupun di modal pembayaran.
- **Usulan Perbaikan:**
  1. Kolom `upsell_mandatory` di `tenants` + toggle di Settings Owner, mengikuti pola `features` yang sudah ada.
  2. `useUpsell` mengekspos `unresolved` — saran yang belum di-*accept* maupun ditolak. BAYAR nonaktif selama `mandatory && unresolved.length > 0`.
  3. **Komponen alert di atas tombol** yang menyebut saran mana yang menahan, bukan sekadar tombol kelabu. Berlaku juga untuk sebab lain (keranjang kosong, sedang memproses) — alasan tombol mati seharusnya selalu terbaca.
  4. Ganti makna dan label kedua tombol: × = **"Ditolak pelanggan"**, tombol utama = **"Ditawarkan"**. Keduanya sah menyelesaikan saran; yang dilarang hanya melewatinya tanpa keputusan.
  5. **Catat `rejected` terpisah dari `ignored`.** Kalau penolakan yang disengaja dicampur dengan saran yang cuma lewat, angka konversi jadi tidak berarti — persis kekhawatiran yang sudah ditulis di docblock `useUpsell`.
- **Catatan:** penegakan wajib ini **hanya di sisi klien** sudah memadai — ini disiplin kerja, bukan batas keamanan. Jangan sampai kewajiban menawarkan berubah jadi alasan server menolak penjualan yang sah (prinsip yang sama sudah dipakai untuk `upsell_events` di `StoreTransactionRequest`).

### [BL-023] Tagihan Terbuka Menumpang di Dalam Keranjang, Belum Punya Tempat Sendiri
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "buatkan topbar terpisah untuk pending pembayaran atau open bill, bukan dalam input keranjang"
- **Status:** Selesai (2026-07-31) — lihat `[ADDITION] Tagihan Terbuka Pindah ke Topbar Kasir (BL-023)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `resources/js/Pages/Cashier/POS.vue:862-905` — panel "Tagihan Terbuka" berada **di dalam area scroll item keranjang**
  - `app/Http/Controllers/Cashier/POSController.php:60-63` — `openBills` dikirim sebagai props POS saja
  - `resources/js/Components/CashierTopbar.vue:45-57` — `navItems` (tempat tombolnya seharusnya berada)
- **Deskripsi:** Daftar tagihan terbuka ikut tergeser saat keranjang panjang, dan bercampur dengan barang yang sedang diinput — dua hal yang tidak berhubungan berbagi satu ruang gulir. Akibat praktisnya: tagihan yang menunggu dibayar bisa hilang dari pandangan justru saat kasir paling sibuk. Selain itu daftarnya **hanya ada di halaman POS**; kasir yang sedang membuka Riwayat atau Kas tidak melihat ada tagihan menggantung.
- **Usulan Perbaikan:**
  1. Jadikan tombol + badge jumlah di `CashierTopbar` (bersebelahan dengan "Antrian"/"Riwayat"), isinya dibuka sebagai panel/drawer tersendiri.
  2. Pindahkan `openBills` dari props POS ke shared data (`HandleInertiaRequests`) supaya topbar bisa membacanya di **semua** halaman kasir. Kirim ringkasannya saja (kode, nama, total, jumlah) — daftar item lengkap cukup dimuat saat panel dibuka.
  3. Hapus panel lama dari badan keranjang di perubahan yang sama; dua tempat untuk hal yang sama akan berbeda isinya cepat atau lambat.

### [BL-029] Satu Test Subsidi Selalu Gagal di Tanggal 29–31 (Carbon Month Overflow)
- **Ditemukan:** 2026-07-31
- **Sumber:** Menjalankan suite penuh saat mengerjakan `[BL-028]`; diverifikasi pre-existing dengan men-`stash` seluruh perubahan sesi itu
- **Status:** Selesai (2026-07-31) — lihat `[HOTFIX] Turunan Periode Bulanan Meluber di Bulan Pendek (BL-029)` di `docs/CHANGELOG.md`. Penyisiran yang disebut di Catatan **sudah dilakukan** dan menemukan tiga bug produksi (job penghitung omzet, prune retensi, laporan command); sisanya yang menyangkut jatuh tempo langganan dipisah jadi `[BL-030]`.
- **Prioritas:** Low untuk dampak produksi (**nol** — kode aplikasi tidak terlibat), Medium untuk kepercayaan pada suite: ia merah di tiap tanggal 31 tanpa ada yang rusak
- **Area Terdampak:**
  - `tests/Feature/Subscription/SubsidyTrackTest.php:131-135` — `foreach ([1, 2, 3] as $bulanLalu) ... now()->subMonths($bulanLalu)->format('Y-m')`
  - `database/factories/TenantMonthlyMetricFactory.php:23` — `now()->subMonth()` bawaannya punya jebakan yang sama
- **Deskripsi:** `mencabut consent menghapus ringkasan omzet seketika` gagal dengan `UNIQUE constraint failed: tenant_monthly_metrics.tenant_id, tenant_monthly_metrics.period`. Penyebabnya bukan aplikasi melainkan `Carbon::subMonths()` yang **meluber** saat tanggal asalnya tidak ada di bulan tujuan. Diverifikasi pada 2026-07-31:

  | offset | tanggal hasil | period |
  |---|---|---|
  | `subMonths(1)` | 2026-07-01 | `2026-07` |
  | `subMonths(2)` | 2026-05-31 | `2026-05` |
  | `subMonths(3)` | 2026-05-01 | `2026-05` |

  April tidak punya tanggal 31, jadi `subMonths(3)` meluber ke 1 Mei — bertabrakan dengan `subMonths(2)`. Test bermaksud membuat tiga periode berbeda, tapi membuat dua baris untuk `2026-05`.
- **Dugaan Penyebab:** `subMonths()` dipakai seolah artinya "bulan ke-N sebelum ini", padahal artinya "tanggal yang sama N bulan lalu, meluber bila tak ada". Perbedaannya tak pernah terlihat 28 hari sebulan.
- **Usulan Perbaikan:** pakai `now()->startOfMonth()->subMonths($n)` (atau `subMonthsNoOverflow()`) di test **dan** di factory. `startOfMonth()` lebih disukai karena menyatakan maksudnya — yang dibandingkan memang bulannya, bukan tanggalnya.
- **Catatan:**
  - **Kode produksi tidak terkena.** `ComputeTenantMonthlyRevenue` menerima satu `$period` eksplisit dan tidak pernah menurunkan beberapa periode dari satu `now()`. Cacatnya murni di data uji.
  - Layak disisir sekali: pemakaian `subMonths`/`subMonth`/`addMonths` lain yang menurunkan **beberapa** periode dari satu titik waktu punya jebakan yang sama.

### [BL-027] Riwayat Kasir Menampilkan Seluruh Riwayat, Bukan Hari & Sesi Berjalan
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "pada riwayat kasir, munculkan data hari ini saja dan di session dia saja"
- **Status:** Selesai (2026-07-31) — lihat `[HOTFIX] Riwayat Kasir Dibatasi ke Sesi Kas Berjalan (BL-027)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Http/Controllers/Cashier/POSController.php:195` — sudah menyaring `user_id = Auth::id()`
  - `app/Http/Controllers/Cashier/POSController.php:205` — filter tanggal **opsional**, tanpa nilai bawaan
  - `app/Http/Controllers/Cashier/POSController.php:216` — `$openDrawer` sudah diambil di method yang sama, tapi hanya dipakai menentukan hak edit
  - `resources/js/Pages/Cashier/TransactionHistory.vue:26` — `dateFilter` bawaan `''`
- **Deskripsi:** Bagian "sesi dia saja" **sudah setengah terpenuhi** — query sudah per-`user_id`, jadi kasir tidak melihat transaksi rekannya. Yang belum: tanpa filter tanggal, halaman ini membuka **seluruh riwayat sejak akun dibuat**, dan tidak ada batas sesi laci sama sekali. Selain bising, ini juga memperlebar permukaan data yang terlihat dari mesin kasir yang dipakai bergantian.
- **Usulan Perbaikan:**
  1. Bawaannya dibatasi ke **sesi laci terbuka milik user** (`created_at >= $openDrawer->opened_at`). Objeknya sudah tersedia di method yang sama — tinggal dipakai sebagai batas query, bukan hanya penentu hak edit.
  2. Bila tidak ada sesi terbuka (owner, atau kasir yang lacinya sudah ditutup), jatuh ke `whereDate(today())`.
  3. Filter tanggal manual tetap ada, tapi **hanya untuk owner**. Kasir yang bisa menyetel tanggal sendiri membuat pembatasan di poin 1 jadi sekadar hiasan.
  4. Beri label di UI bahwa yang tampil adalah sesi berjalan — daftar yang diam-diam terpotong lebih buruk daripada daftar panjang.

### [BL-022] Checkout Memvalidasi Cukup-Bayar Memakai Harga Kiriman Klien, Bukan Harga DB
- **Ditemukan:** 2026-07-31
- **Sumber:** Temuan tambahan saat memverifikasi catatan pemilik tentang split bill
- **Status:** Selesai (2026-07-31) — lihat `[HOTFIX] Split Bill` di `docs/CHANGELOG.md`
- **Prioritas:** High — jalurnya sempit, tapi akibatnya transaksi kurang bayar tercatat `completed`
- **Area Terdampak:**
  - `app/Http/Requests/StoreTransactionRequest.php:118-131` — `$totalBelanja` dijumlahkan dari `items.*.unit_price` **kiriman klien**
  - `app/Services/TransactionService.php:119-123` — total sebenarnya dihitung ulang dari harga DB
  - `app/Services/TransactionService.php:141-142` — `changeAmount` memakai total DB, tapi **tidak ada penjagaan kurang bayar**
  - `app/Services/TransactionService.php:319` — `payOpenBill()` **sudah** punya penjagaan itu
- **Deskripsi:** Validasi "total bayar ≥ total belanja" berjalan di `FormRequest`, memakai harga yang dikirim klien. `TransactionService::checkout()` kemudian menghitung ulang total dari harga DB — dan bila hasilnya lebih besar dari yang dibayar, transaksi **tetap diselesaikan** sebagai `completed` dengan `change_amount = 0`. Tidak ada satu pun exception yang dilempar.
  Jalur nyatanya bukan serangan, melainkan katalog basi: perangkat yang memakai snapshot offline (`useCatalogCache`) bisa mengirim harga lama setelah owner menaikkan harga. Kasir melihat "lunas", server mencatat kurang bayar.
- **Dugaan Penyebab:** penjagaan diletakkan di lapisan validasi request, di mana harga DB belum tersedia. `payOpenBill()` menjaganya di service — **pola yang benar sudah ada di file yang sama**, hanya belum diterapkan ke `checkout()`.
- **Usulan Perbaikan:** pindahkan penjagaan cukup-bayar ke `checkout()`, tepat setelah `$totalAmount` diketahui dari DB (setelah baris 119) dan sebelum pembayaran disimpan — meniru `payOpenBill():319`. Validasi di `FormRequest` boleh tetap ada sebagai penyaring awal yang murah, tapi bukan lagi satu-satunya. Sertakan selisihnya di pesan error supaya kasir tahu harganya berubah, bukan sekadar "gagal".
- **Catatan:** bersinggungan dengan `[BL-016]` (jaminan offline) dan `[BL-018]` (harga diskon yang sah berbeda dari harga katalog). Perbaikan di sini **jangan** dibuat sebagai "harga klien harus persis sama dengan DB" — itu akan menabrak diskon begitu `[BL-018]` dikerjakan. Yang dijaga cukup: **yang dibayar tidak boleh kurang dari yang ditagih.**

### [BL-021] Split Bill: Nominal Non-Tunai Jadi Basi & Tombol Uang Cepat Mati Diam-Diam
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "fitur split bill akan refresh state nominal setiap inputan data baru", "split bill membuat fitur menu bayaran cash dll itu otomatis harus di input manual", "perbaikan flow saat melakukan split bill"
- **Status:** Selesai (2026-07-31) — lihat `[HOTFIX] Split Bill` di `docs/CHANGELOG.md`
- **Prioritas:** High — nominal per metode pembayaran tercatat salah, dan salahnya menjalar ke rekap kas & laporan
- **Area Terdampak:**
  - `resources/js/Components/PaymentModal.vue:55-66` — `onMethodChange()` mengisi nominal non-tunai **sekali saja**
  - `resources/js/Components/PaymentModal.vue:124-129` — `payExact()` dijaga `payments.length === 1`
  - `resources/js/Components/PaymentModal.vue:229` — tombol denominasi hanya dirender untuk `idx === 0`
  - `resources/js/Components/PaymentModal.vue:132-139` — `quickCash()` selalu menyasar `payments[0]`
  - `resources/js/Components/PaymentModal.vue:96-98` — `change` dihitung dari seluruh pembayaran, tanpa memisahkan porsi tunai
  - `resources/js/Pages/Cashier/POS.vue:973-988` — komponen yang sama dipakai POS **dan** pembayaran open bill
  - `tests/Feature/Cashier/` — **tidak ada satu pun test untuk jalur split**
- **Deskripsi — tiga cacat yang saling memperburuk:**
  1. **Nominal non-tunai tidak pernah dihitung ulang.** `onMethodChange()` mengisi `total − Σ baris lain` pada saat metode dipilih, lalu angka itu dibekukan. Reproduksi: total 100.000 → baris 1 Cash diisi 50.000 → baris 2 QRIS terisi otomatis 50.000 → kasir mengoreksi baris 1 jadi 60.000 → **QRIS tetap 50.000**. Total bayar terbaca 110.000 dan modal menawarkan "kembalian 10.000" — padahal QRIS tidak mengenal kembalian. Yang tersimpan di `transaction_payments` salah per metode, sehingga rekap per metode di `CashDrawerController::summary()` dan expected kas ikut salah. Inilah yang dilaporkan sebagai "state nominal ter-refresh".
  2. **Tombol uang cepat mati diam-diam.** Tombol "Uang Pas / +20rb / +50rb / +100rb" tetap **terlihat** setelah baris kedua ditambahkan (dirender untuk `idx === 0`), tetapi `payExact()` menolak bekerja karena `payments.length !== 1`. Kasir menekan tombol yang tidak melakukan apa-apa, tanpa pesan apa pun. Baris tunai kedua tidak punya tombol cepat sama sekali — inilah "cash jadi harus diinput manual".
  3. **Kembalian dihitung dari seluruh pembayaran.** `change = Σ semua − total`, sehingga kelebihan yang berasal dari baris non-tunai ikut ditawarkan sebagai uang kembali dari laci.
- **Dugaan Penyebab:** modal ini jelas dirancang untuk kasus satu baris pembayaran, lalu split ditambahkan belakangan sebagai tombol "+ Split Pembayaran" tanpa meninjau ulang asumsi "baris pertama = satu-satunya baris". Tiga penjaga yang tersisa (`idx === 0`, `length === 1`, pengisian satu arah) adalah sisa asumsi itu.
- **Usulan Perbaikan:**
  1. **Nominal non-tunai jadi turunan, bukan nilai yang disimpan.** Hitung sebagai `computed`: `total − Σ baris lain`, dievaluasi ulang tiap perubahan. Bila ada lebih dari satu baris non-tunai, kunci semuanya kecuali satu baris penyeimbang — dua baris yang sama-sama "otomatis" tidak punya jawaban tunggal.
  2. **Tombol cepat mengikuti baris yang sedang aktif,** bukan `idx === 0`. Hapus penjagaan `length === 1` di `payExact()`, dan tambahkan tombol **"Sisa"** per baris tunai yang mengisi tepat kekurangannya — ini yang menghapus keharusan mengetik manual.
  3. **Kembalian hanya dari porsi tunai:** `change = max(0, Σ tunai − (total − Σ non-tunai))`.
  4. **Tulis test-nya lebih dulu.** Jalur ini menyentuh uang dan sama sekali belum punya test: tunai+QRIS, koreksi baris setelah baris lain terisi, kurang bayar, dan pembayaran open bill (komponen yang sama, jalur server berbeda).
- **Catatan:** `PaymentModal` dipakai **dua kali** di `POS.vue` — checkout biasa dan pelunasan open bill. Perbaikan apa pun otomatis berlaku untuk keduanya, jadi keduanya wajib ikut diuji; jangan sampai open bill baru ketahuan rusak setelah dipakai.

### [BL-019] Rencana Antrian Dapur (PHASE-QUEUE) Perlu Ditinjau Ulang & Ditulis Ulang Sebelum Diimplementasi
- **Ditemukan:** 2026-07-28
- **Sumber:** Peninjauan teknis `docs/phases-2/PHASE-QUEUE_Kitchen-Order-Queue.md` terhadap keadaan kode, dilakukan **sebelum** implementasi dimulai
- **Status:** Selesai (2026-07-29) — lihat `[ADDITION] Papan Antrian Dapur (BL-019)` dan `[ADDITION] Fondasi Capability Flags & Gerbang Tersinkron` di `docs/CHANGELOG.md`
- **Prioritas:** High untuk peninjauannya
- **Jalannya:** Tiga langkah persis seperti yang disyaratkan entri ini — tinjau tujuan → tulis ulang dokumen → baru kerjakan. Penulisan ulang kedua dokumen selesai 2026-07-28; implementasinya 2026-07-29.
- **Perbaikan:**
  Ternyata **dua fase**, bukan satu. Baris pertama checklist `PHASE-QUEUE` adalah fondasi capability flags, dan fondasi itu belum ada sama sekali di kode — tidak ada `hasFeature()`, tidak ada kolom flag, tidak ada middleware `feature:`. Atas keputusan pemilik, `PHASE-FEATURE-FLAGS` dikerjakan **penuh** lebih dulu (termasuk gerbang self-order, AI, MCP, dan Settings-nya), baru papan antrian di atasnya.
- **Seluruh penghalang keras di bagian A entri ini tertutup:**
  1. **`business_type` dicabut sepenuhnya** — mode antrian murni toggle Settings (`kitchen_queue_enabled`), tidak menyentuh dimensi harga sama sekali.
  2. **Transaksi offline tidak masuk papan** — bukan lagi kelalaian melainkan batas lingkup yang dinyatakan, dikunci test, dan ditampilkan sebagai pita di UI serta di deskripsi toggle.
  3. **Void membersihkan papan** — dua lapis: `void()` menolkan `fulfillment_status`, dan query papan tetap mengecualikan `STATUS_VOIDED`.
  4. **Masa tenggang tidak lagi membekukan dapur** — `cashier.queue.*` masuk `ALWAYS_ALLOWED`, dengan test yang memastikan pengecualian itu tidak melebar jadi pintu belakang untuk transaksi baru.
- **Sepuluh koreksi di bagian B dikerjakan apa adanya:** `expected_from` wajib, `id` sebagai pemecah seri, `refresh()` sebelum tukar posisi, satu `update()` alih-alih tiga, batas hari lewat `effectiveDate()`, dan blok alias `PHASE-FEATURE-FLAGS` yang salah fakta sudah dikoreksi sebelum disalin — `tenant`/`tenant.api` tetap grup, dan lima alias yang sudah ada tidak terhapus.
- **Bagian C — yang dikerjakan dan yang tidak:** poin 11 (*Dahulukan* satu-tap), 12 (BELUM BAYAR + konfirmasi), 13 (nomor sampai ke pelanggan lewat struk, cetak termal, dan respons webhook), 15 (`ready` tidak bisa diurutkan ulang), dan 18 (migrasi backfill + sumbernya ditutup) **selesai**. Poin 14 (batal dari papan), 16 (batching), dan 17 (fulfillment parsial) **sengaja di luar lingkup** sesuai Keputusan 2 pemilik.
- **Temuan baru saat pengerjaan — bukan di entri asli.** `TenantFactory` mewarisi default kolom, sehingga `self_order_enabled` bawaannya `false` di test. Lima test permukaan self-order yang sudah ada karenanya gagal, dan dua di antaranya sebelumnya lulus **karena alasan yang salah**. Semuanya kini menyalakan flag secara eksplisit, yang sekaligus mendokumentasikan ketergantungannya.
- **Yang BELUM diverifikasi:** papan belum dibuka di peramban — basis data pengembangan (MySQL) tidak berjalan saat pengerjaan. Backend tertutup 35 test dan `npm run build` membuktikan Vue-nya terkompilasi, tapi tampilan kartunya belum pernah dilihat manusia.
- **Catatan penutup atas ketegangan dengan `[BL-020]`:** entri ini dan BL-020 mendorong `EnsureSubscriptionActive` ke dua arah berlawanan. BL-020 diselesaikan lebih dulu tanpa menyentuh middleware itu sama sekali, sehingga keputusan `ALWAYS_ALLOWED` benar-benar tinggal di sini — dan masing-masing arah punya testnya sendiri, syarat yang diminta kedua entri.

### [BL-020] Grup API Self-Order Tidak Melewati Gerbang Langganan
- **Ditemukan:** 2026-07-28
- **Sumber:** Terlihat saat menulis ulang `PHASE-FEATURE-FLAGS` — memetakan middleware tiap permukaan
- **Status:** Selesai (2026-07-29) — lihat `[HOTFIX] Gerbang Langganan di Jalur Self-Order (BL-020)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **TERBUKTI NYATA.** Entri ini ditulis dengan peringatan "belum diverifikasi, buktikan dengan test lebih dulu". Testnya ditulis lebih dulu (`SelfOrderSubscriptionGateTest`) dan **tiga dari lima ekspektasi awalnya gagal**, persis seperti yang diduga:
  - `suspended` → `POST /api/v1/orders` menjawab **422** (validasi controller), bukan 403 — permintaan lolos gerbang
  - `grace` → `POST /api/v1/orders` menjawab **422** — sama, padahal `grace` hanya-baca
  - `suspended` → `GET /api/v1/products` menjawab **200** — katalog tetap terbuka
- **Perbaikan:**
  Usulan 2 dijalankan apa adanya: gerbang langganan **dipisahkan** dari verifikasi surel lewat alias baru `subscription`, bukan dengan menempelkan grup `tenant.api`. Token mesin kini tunduk pada siklus hidup langganan tanpa dituntut membuktikan alamat surel yang memang tidak dimilikinya — dan ada test yang menjaga batas itu, supaya tidak ada yang "merapikan"-nya jadi `tenant.api` di kemudian hari.
- **Usulan 3 dikerjakan dan hasilnya bersih:** `php artisan route:list --path=api` menunjukkan seluruh grup mobile sudah memakai `tenant.api`. Self-order satu-satunya yang bocor.
- **Keputusan yang tidak ada di entri asli — rute fulfillment sengaja DI LUAR gerbang.** `PATCH /orders/{id}/fulfillment` ikut di grup yang sama, tapi memajukan status pesanan menyelesaikan kewajiban yang uangnya sudah diterima, bukan membuka layanan baru. Menggerbangnya akan menghentikan dapur di tengah antrean pada hari langganan lewat jatuh tempo — persis yang diperingatkan poin 4 `[BL-019]`. Rutenya dipisah di `routes/api.php`, **bukan** dengan menambah pengecualian ke `ALWAYS_ALLOWED`.
- **Catatan penutup atas ketegangan dengan `[BL-019]`:** entri ini memperingatkan bahwa keduanya mendorong gerbang yang sama ke dua arah berbeda dan "sebaiknya tidak dikerjakan bersamaan tanpa test yang memisahkannya". Syarat itu dipenuhi — ada test untuk masing-masing arah — dan `EnsureSubscriptionActive` **tidak disentuh sama sekali**, sehingga kebijakan antrean seutuhnya tetap milik `[BL-019]` yang masih terbuka.

### [BL-017] Peringatan Stok Belum Berkembang Jadi Upsell (FITUR BESAR)
- **Ditemukan:** 2026-07-25
- **Sumber:** Saran yang diterima setelah sesi pitching — "sudah ada peringatan stok dll, kalau bisa kembangkan fiturnya jadi upsell"
- **Status:** Selesai (2026-07-27) — lihat `[ADDITION] Saran Jual dari Sinyal Stok (BL-017)` di `docs/CHANGELOG.md` dan `docs/phases-2/PHASE-UPSELL_Stock-Signal-To-Upsell.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Enam usulan di entri ini dikerjakan apa adanya, dengan satu bagian sengaja ditahan.
  **Usulan 1 dijalankan penuh:** `BadgeHelperService` **tidak disentuh sama sekali** — dashboard owner tetap persis seperti sebelumnya. Mesin rekomendasi berdiri terpisah di `app/Services/Upsell/`, dengan penjaga kandidat di satu tempat (`SellableVariantQuery`) dan tiga strategi di baliknya.
  **Usulan 2 dibedakan sungguhan:** `attach` (ko-okurensi modifier), `pressed_stock` (barang tertekan), dan `upsize` (naik ukuran) punya kandidat, alasan, dan skornya masing-masing.
  **Usulan 3, 4 diuji, bukan sekadar dipatuhi:** varian `expired`, stok nol, dan produk nonaktif tidak pernah lolos jadi kandidat, dan jumlah saran dibatasi `upsell.max_per_transaction` (default 2).
  **Usulan 5 dikerjakan PERTAMA, bukan terakhir** — tabel `upsell_events` lahir sebelum permukaannya, tepat karena periode awal tidak bisa ditambal belakangan.
  **Usulan 6 diambil sebagai permukaan kedua**, bukan pengganti POS: `POST /api/v1/upsell/suggestions` plus `upsell_events` opsional di `POST /api/v1/orders`.
- **KOREKSI atas usulan 2.** Kalimat "perlu penanda urutan varian yang belum ada di `product_variants`" ternyata keliru. `price` **sudah** mendefinisikan urutan yang dimaksud "naik ukuran", dan menambah kolom urutan yang harus diisi manual justru akan membuat fiturnya mati diam-diam pada tenant yang tidak pernah mengisinya. Tidak ada kolom baru di `product_variants`.
- **Keputusan yang tidak ada di entri asli — saran dihitung di server, dipilih di client.** Indeks saran ikut props POS (bukan endpoint per perubahan keranjang) supaya ia ikut ter-snapshot `useCatalogCache` dan **tetap hidup saat perangkat offline**. Endpoint per-keranjang akan mati justru di warung yang sinyalnya paling buruk — properti offline yang sudah dibayar mahal di PHASE PWA tidak boleh dirusak fitur baru.
- **Yang DITAHAN, sesuai catatan penutup entri ini:** bundling **berdiskon**. `pressed_stock` hari ini menawarkan barang tertekan pada **harga katalog, tanpa potongan** — itu yang bisa dilakukan tanpa tempat sah untuk harga di bawah katalog. Versi berdiskonnya menunggu `[BL-018]`; `upsell_events.extra_amount` dan `reason` sudah disiapkan supaya tinggal disambung.
- **Batas yang diterima sadar:** event ikut payload checkout, jadi saran pada keranjang yang **dibatalkan** tidak tercatat. Yang diukur adalah "dari saran yang muncul pada transaksi yang jadi, berapa yang diambil". Endpoint terpisah bisa menghitung keranjang batal, tapi tidak akan selamat melewati mode offline.

### [BL-015] Dimensi Penetapan Harga Masih Terbatas Omzet & Seat
- **Ditemukan:** 2026-07-25
- **Sumber:** Permintaan pemilik SaaS — "harga akan beda-beda berdasarkan omzet **dan berbagai kategori lainnya**, dan nilainya harus bisa diubah dari panel platform, bukan tertanam di kode/data bawaan"
- **Status:** Selesai (2026-07-27) — lihat `[SCHEMA] Dimensi Harga Bebas — Aturan Berkriteria (BL-015)` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Ditempuh lewat **usulan 1**: `min_revenue`/`max_revenue` dibuang, dan aturan kini punya banyak syarat (`dimensi`, `operator`, `nilai`) plus `priority`. Bracket lama **dipindahkan** oleh migration, bukan disemai ulang dari config — menyemai ulang akan membuang tarif yang sudah disunting pemilik SaaS lewat panel sejak Tahap D.
  **Usulan 2 terbukti benar dan dijalankan apa adanya:** katalog dimensi tinggal di `config/pricing-dimensions.php` karena tiap dimensi butuh sumber angkanya. Yang bebas sepenuhnya adalah ambang dan tarifnya.
  **Usulan 3 tidak jadi dipisah.** Rencananya dimensi atribut digarap belakangan, tapi pemilik memilih **tipe usaha** ikut di jalur pertama — jadi kolom `tenants.business_type`, pertanyaan di form pendaftaran, dan pengubahnya di panel dikerjakan sekaligus.
- **Empat penjaga usulan 4 tetap utuh dan masih diuji.** Saran "simpan jejak aturan yang menang" di poin yang sama **ikut dikerjakan**, dan ternyata bukan sekadar pelengkap: dengan banyak syarat per aturan, "aturan mana yang berlaku waktu itu" tak lagi bisa direka ulang dari satu angka omzet. `invoices.pricing_rule_id` + `pricing_context` menjawabnya.
- **Batas privasi usulan 5 ditegakkan di kode, bukan di kedisiplinan panel:** `DimensionRegistry` jadi satu-satunya pintu ke nilai dimensi dan menolak menghitung yang consent-nya tidak aktif — sehingga aturan berdimensi omzet tak pernah cocok untuk tenant jalur normal.
- **KOREKSI atas usulan 5.** Kalimat "teks consent naik versi" di sana keliru bila dibaca sebagai syarat pemakaian data. Memakai pemeriksaan versi-terkini sebagai gerbang berarti satu kali menaikkan versi teks akan memadamkan dimensi itu bagi **seluruh** tenant sekaligus — harga mereka berubah diam-diam pada hari revisi terbit, tanpa satu pun dari mereka melakukan apa-apa. Gerbangnya adalah persetujuan yang **masih aktif**; kenaikan versi tetap memicu permintaan menyetujui ulang, tapi itu urusan UI, bukan urusan kelayakan data. Kenaikan versi tetap wajib untuk dimensi yang benar-benar membuka data baru.
- **Keputusan pemilik SaaS (2026-07-27)** yang menjawab catatan penutup entri ini: dimensi jalur pertama adalah **volume transaksi**, **jumlah pengguna aktif**, dan **tipe usaha**. Dua yang pertama nol biaya privasi — `transaction_count` sudah dikumpulkan dan sudah tercakup consent subsidi yang berlaku, dan seat hanya `count()` di `users`.
- **Temuan yang mengubah bentuk pekerjaan ini:** mesin harga ternyata **tidak pernah menentukan tagihan siapa pun**. `bracketFor()` hanya dipanggil tiga permukaan tampilan; nominal tagihan diketik tangan di `InvoiceController`. Karena itu menambah dimensi saja tidak akan terasa, dan atas keputusan pemilik hasilnya kini **mengisi otomatis** kolom nominal — tetap bisa diketik ulang, dan aturannya hanya dicatat bila nominal yang terbit benar-benar sama dengan tarif aturannya.
- **Yang TIDAK dikerjakan (sengaja):** dimensi **wilayah**, **jumlah outlet**, dan **durasi langganan**. Outlet belum punya tabelnya; durasi menuntut keputusan siklus penagihan tersendiri karena periode satu bulan tertanam di `current_period_end` dan beberapa tempat lain.

### [BL-003] Mobile API — Permissions RBAC di Auth & Gating Endpoint
- **Ditemukan:** 2026-07-21
- **Sumber:** Keputusan C plan RBAC (`docs/phases-2/PHASE-RBAC_Module-Access-Control.md`, Bagian 8)
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Permissions RBAC di Mobile API (BL-003)` di `docs/CHANGELOG.md`
- **Prioritas:** Low
- **Perbaikan:**
  Bagian pertama dikerjakan penuh: `permissions` kini ada di payload login **dan** di `/mobile/tenant/profile` — yang kedua supaya aplikasi bisa menyegarkan izin tanpa login ulang, sebab token mobile bertahan berminggu-minggu sementara owner bisa mengubah role kapan saja. Perhitungannya dipindah ke `User::modulePermissions()`, satu sumber untuk web dan mobile.
- **Bagian kedua ("gerbang endpoint per modul") ternyata belum punya sasaran.** Tidak satu pun endpoint mobile yang ada saat ini punya padanan bergerbang modul di web: semuanya POS, laci kas, dan riwayat kasir, yang di web sengaja hanya digerbang `role:cashier,owner`. Middleware `permission.api` tetap dibuat dan diuji, siap untuk endpoint bermodul berikutnya.
- **Keputusan pemilik SaaS (2026-07-25):** `pos` dan `cash_drawer` tetap **penanda menu**, bukan gerbang rute — di web maupun mobile. Menggerbangnya di mobile saja akan mengunci staf "Tanpa role (POS saja)" sekaligus membuat orang yang sama ditolak aplikasi tapi diterima peramban.
- **Lubang yang tersingkap dan sengaja dibiarkan:** kasir ber-role "Gudang" (hanya `stock`) masih bisa mengetik `/cashier/pos` di peramban dan berjualan. Menutupnya mengubah perilaku staf yang sudah ada, jadi diputuskan tidak sekarang.

### [BL-004] Pesan Validasi Masih Bahasa Inggris di UI Berbahasa Indonesia
- **Ditemukan:** 2026-07-21
- **Sumber:** Validasi live blackbox fitur RBAC — form "Tambah Staf", email duplikat + kata sandi < 8 karakter
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Pesan Validasi Berbahasa Indonesia (BL-004)` di `docs/CHANGELOG.md`
- **Prioritas:** Low
- **Perbaikan:**
  Ditempuh lewat **usulan 1** (locale global), bukan usulan 2 (`messages()` per FormRequest): sekali kerja untuk semua form, dan form baru ikut terjemahan dengan sendirinya. `lang/id/{validation,auth,passwords,pagination}.php` lengkap, `APP_LOCALE=id`, dengan fallback tetap `en` supaya kunci yang terlewat muncul sebagai kalimat Inggris — bukan sebagai `validation.required`.
- **Usulan 3 (`attributes`) ikut dikerjakan, dan ternyata bagian terpentingnya.** Tanpa daftar nama kolom, hasilnya berbunyi "Kolom business_name wajib diisi" — separuh jadi, dan justru lebih janggal daripada tidak diterjemahkan sama sekali. Daftarnya disusun dari kolom yang benar-benar divalidasi aplikasi ini.
- **Dua permukaan yang dicatat di entri ini sudah diverifikasi langsung:** form tambah staf (skenario asli backlog) lewat test, dan form login lewat peramban — keduanya kini menyebut "Kolom Email" dan "Kolom Kata Sandi".
- **Efek samping yang menguntungkan:** nama bulan pada tanggal yang dirender `translatedFormat()` ikut berbahasa Indonesia.

### [BL-014] Pendaftaran Berulang Demi Trial Gratis Baru
- **Ditemukan:** 2026-07-25
- **Sumber:** Ditunda sadar-sadar saat mengerjakan PHASE SAAS Tahap B
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Peringatan Login Gagal & Verifikasi Email` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Dua lapis. **Verifikasi surel wajib** untuk owner yang mendaftar sendiri — inilah penghalang utamanya, karena akun tanpa alamat yang benar-benar bisa dibuka tidak berguna untuk apa pun. Staf buatan owner dikecualikan (owner yang menjaminnya, dan kasir warung kecil kerap tak punya surel sendiri). **Penandaan pendaftaran berulang** dari satu IP sebagai lapis kedua, untuk pola yang lolos dari lapis pertama.
- **Keputusan yang layak diingat:** ditandai, **bukan diblokir**. Satu IP publik bisa dipakai bersama satu kompleks pertokoan — memblokir otomatis akan menjegal warung sebelah yang tidak melakukan kesalahan apa pun.
- **Yang TIDAK dikerjakan (sengaja):** verifikasi nomor telepon. Untuk UMKM Indonesia nomor jauh lebih mahal dibuat berulang daripada alamat surel, jadi ia sinyal yang lebih kuat — tapi formulir pendaftaran belum meminta nomor sama sekali, dan memintanya adalah perubahan alur tersendiri berikut kebutuhan penyedia SMS.

### [BL-011] Belum Ada Peringatan Saat Percobaan Masuk Gagal Menumpuk
- **Ditemukan:** 2026-07-22
- **Sumber:** Sisa usulan `[BL-007]`, dengan fondasi dari `[BL-009]`
- **Status:** Selesai (2026-07-25) — lihat `[ADDITION] Peringatan Login Gagal & Verifikasi Email` di `docs/CHANGELOG.md`
- **Prioritas:** Low
- **Perbaikan:**
  Perintah terjadwal per jam yang menyaring `login.failed` dari jejak audit. Dua pola dengan ambang sendiri sesuai usulan aslinya: banyak kegagalan pada **satu alamat** (penebakan kata sandi) versus banyak alamat berbeda dari **satu IP** (penebakan akun, yang lolos dari kunci per-email justru karena tiap alamat dicoba sedikit). Peringatan sejenis dijeda supaya satu serangan semalaman tidak menghasilkan satu surel tiap jam.
- **KOREKSI atas isi entri ini:** poin 2 usulan aslinya menyebut *"tentukan kanalnya dulu — proyek belum punya satu pun"* dan menyarankan Telegram lewat n8n. **Itu sudah tidak benar sejak `[BL-010]`/`[BL-012]`**: surel sudah jadi kanal yang bekerja lewat notifikasi pemulihan kata sandi. Jadi tidak ada kanal baru yang perlu dibangun.
- **Cakupan yang perlu disadari:** hanya percobaan masuk ke **panel platform**. Login tenant yang gagal belum dicatat di mana pun karena sisi tenant tidak punya tabel audit sendiri — membuatnya adalah pekerjaan tersendiri yang belum ada di backlog.

### [BL-005] Platform Console — Panel Pemilik SaaS (Privacy-Preserving)
- **Ditemukan:** 2026-07-21
- **Sumber:** Permintaan pemilik SaaS — satu panel untuk melihat tenant, langganan, dan pembayaran **tanpa** melihat data operasional klien
- **Status:** Selesai (Tahap A 2026-07-21/22, Tahap B–D 2026-07-25) — lihat dua entri `[ADDITION] PHASE SAAS` di `docs/CHANGELOG.md`
- **Prioritas:** Medium
- **Perbaikan:**
  Empat tahap. **A:** guard `platform` terpisah + RBAC modul sendiri (spatie terbukti mustahil dipakai di sini) + daftar tenant read-only + jejak audit. **B:** model langganan, trial, masa tenggang hanya-baca, batas seat, consent jalur normal, panel pembayaran manual. **C:** jalur subsidi berikut job penghitung omzet, consent sendiri, dan tampilan bracket. **D:** aturan harga sebagai data dengan grandfathering.
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

### [BL-053] Seat Tambahan dan Kuota AI Masih Biaya Sekali Bayar — Keputusan Pemilik Menyebutnya Bulanan
- **Ditemukan:** 2026-08-07
- **Sumber:** Keputusan pemilik 2026-08-07 — "seat tambahan include dalam bulanan, begitu juga untuk nanti ketika mau nambah kuota ai, bayar bulanan begitu"
- **Status:** Selesai (2026-08-08) — butir (a)+(b) beserta keputusan pemilik kedua. Lihat `[ADDITION] Seat Tambahan Jadi Komponen Bulanan, dan Untuk Pertama Kalinya Bisa Dilepas (BL-053)` di `docs/CHANGELOG.md`. **Butir (c) — kuota AI — dipecah ke `[BL-069]`, belum dikerjakan.**
- **Prioritas:** High — angkanya sudah terpasang, jadi satuannya salah **sejak sekarang**, bukan nanti
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php` — `issueDuePeriodInvoices()`: `'amount' => $price`, tanpa komponen seat apa pun
  - `app/Services/SubscriptionService.php` — `requestSeatUpgrade()`: `amount = extra_seat_price × jumlah`, tagihan `KIND_UPGRADE` sekali bayar
  - `app/Models/Subscription.php` — `seat_high_water` + `recordSeatUsage()`: **sudah ada dan memang untuk ini**
- **Deskripsi:**
  Tagihan langganan otomatis hanya memuat harga paket. Seat tambahan ditagih **sekali** lewat invoice `KIND_UPGRADE`, lalu seat-nya melekat permanen. Artinya `extra_seat_price` Rp 20.000 hari ini berbunyi *"Rp 20.000 sekali, seat itu milik Anda selamanya"* — bukan Rp 20.000 per bulan.
  Angka 20k/15k/12,5k/10k sudah dipasang 2026-08-07 dengan maksud bulanan. Sampai komponen bulanannya ada, **nilainya benar tapi satuannya salah**, dan tiap seat yang dibeli sekarang jadi kesepakatan permanen yang jauh lebih murah daripada yang dimaksud.
- **Yang membuatnya lebih murah dari perkiraan:** model datanya sudah menunggu. `seat_high_water` ada beserta alasannya yang tertulis — *"Puncak inilah, bukan jumlah aktif saat penagihan, yang menjadi dasar tagihan periode berjalan"* — dengan penjaga akal-akalan yang sudah dipikirkan: menonaktifkan staf sehari sebelum tanggal tagih tidak boleh menghemat sebulan penuh. Yang belum ada hanya komponennya di penerbit.
- **Usulan Perbaikan:**
  **(a)** `amount` tagihan langganan jadi `harga + (seat_high_water − included_seats) × extra_seat_price`, dengan rinciannya ikut dibekukan di `pricing_context` — tagihan yang tidak bisa dijelaskan pecahannya akan jadi tiket dukungan pertama.
  **(b)** **Putuskan tiga hal yang mengikutinya, jangan disimpulkan saat menulis kode:** (1) seat yang terlanjur diberikan seharga Rp 0 — kedua tenant hari ini memegangnya — mulai ditagih atau di-*grandfather*; (2) penambahan di tengah periode: prorata sisa periode lalu masuk tagihan bulanan, atau tunggu periode berikutnya; (3) **tenant Adaptif harganya dari bracket, tapi harga seat-nya dari paket** — dan sejak 2026-08-07 paketnya `paid-1`, jadi seat-nya Rp 15.000 sementara langganannya bisa Rp 10.000. Itu konsisten dengan "Adaptif = `paid-1` yang didiskon", tapi harus disengaja.
  **(c)** Kuota AI tambahan belum punya bentuk sama sekali — tidak ada kolom, tidak ada alur beli. Kerjakan setelah (a), dan pakai pola yang sama; `Plan::limits` sudah berupa JSON, jadi penambahan kuota per langganan sebaiknya hidup di `subscriptions`, bukan melahirkan paket baru per tenant.
- **Keputusan pemilik 2026-08-07 (kedua) — dasar tagihan seat bukan pemakaian puncak, melainkan seat yang dibeli.** Ini **mengoreksi butir (a) dan (b)(2) di atas**, jangan dikerjakan menurut bunyi lamanya.
  Yang diminta: seat tambahan ditagih **karena dibeli**, terpakai atau tidak. Contoh yang diberikan pemilik — paket memberi 3 seat, tenant membeli 2 seat tambahan, tapi yang benar-benar dipakai baru 4 orang (1 seat menganggur): tagihannya tetap 5 seat. Sekali dibeli untuk bulan berjalan, langganannya jalan terus sampai seat itu **dilepas dengan sengaja**.
  **Akibatnya untuk (a):** rumusnya bukan `seat_high_water − included_seats`, melainkan `purchased_extra_seats` — angka yang dimiliki langganan itu sendiri, bukan angka yang disimpulkan dari pemakaian. `seat_high_water` **tidak lagi jadi dasar harga**; ia paling banter tinggal jadi penjaga *agar tenant tidak memakai lebih banyak seat daripada yang dibelinya*, dan bahkan itu sudah dijaga oleh `seats` biasa. Kalau ternyata tidak ada lagi pembacanya sesudah (a), hapus kolomnya lewat migrasi, jangan tinggalkan sebagai kolom mati yang menyesatkan pembaca berikutnya.
  **Akibatnya untuk UI:** istilah "puncak"/"pemakaian tertinggi" **dihapus dari halaman langganan**. Yang ditampilkan adalah hak dan asalnya, bukan statistik pemakaian:
  > Pengguna: **5 seat** — 3 dari paket Premium 1, **2 seat tambahan yang Anda beli** (@ Rp 15.000/bulan = Rp 30.000/bulan). Terpakai 4, tersisa 1.
  "Terpakai 4" boleh tampil sebagai **informasi**, tapi tidak boleh terbaca seolah memengaruhi tagihan — justru sebaliknya, ia jadi petunjuk kapan tenant sebaiknya melepas seat. Sebutan untuk komponennya: **"seat tambahan"** (dibeli sendiri), lawan dari **"seat bawaan paket"**.
  **Yang jadi wajib karena keputusan ini, dan belum ada sama sekali: jalan untuk melepas seat.** Selama tagihannya mengikuti pemakaian puncak, tenant yang mengecil ikut mengecil sendiri. Begitu tagihannya mengikuti pembelian, tenant **terkunci membayar selamanya** kecuali ada tombol pengurangan. Ini menggantikan usul `[BL-046]`(d) ("beri batas waktu pada `seat_high_water`") — masalahnya sekarang bukan puncak yang tidak pernah turun, melainkan pembelian yang tidak pernah bisa dibatalkan.
  **Tiga hal yang masih harus diputuskan sebelum ditulis kodenya:**
  (1) **Pelepasan berlaku kapan** — seketika (dan tagihan bulan depan turun) atau di akhir periode berjalan? Yang kedua lebih jujur karena bulan berjalan sudah dibayar, dan menutup celah "beli tanggal 1, lepas tanggal 2".
  (2) **Melepas seat yang masih diduduki staf aktif** — ditolak sampai stafnya dinonaktifkan lebih dulu, atau dibolehkan dan stafnya ikut terkunci? Menolak lebih aman; jangan sampai pelepasan seat diam-diam mematikan akun kasir di tengah jam kerja.
  (3) **Prorata saat membeli di tengah periode** — masih butir (b)(2) yang lama dan masih terbuka; keputusan ini tidak menjawabnya.
- **Catatan penutup 2026-08-08 — apa yang berubah dari usul di atas.**
  - Butir (a) **tidak** dikerjakan menurut rumus aslinya. Keputusan pemilik kedua menggantinya: dasarnya `purchased_extra_seats`, kolom baru, bukan `seat_high_water − included_seats`.
  - Usul menghapus `seat_high_water` **ditolak setelah diperiksa**: `ActiveSeatsResolver` masih membacanya sebagai dimensi `active_seats` untuk aturan Harga Adaptif. Kolomnya tetap; yang dicabut perannya di penagihan.
  - Ketiga pertanyaan terbuka dijawab pemilik 2026-08-08: pelepasan berlaku satu periode penuh ke depan (bukan akhir periode berjalan — tagihan berikutnya sudah terbit sebelum itu); seat yang diduduki staf aktif ditolak pelepasannya; pembelian di tengah periode tetap gratis sampai periode habis.
  - Butir (b)(1) — seat yang terlanjur gratis — dijawab "ikut ditagih". Backfill 2026-08-08 memberi `Kopi Nusantara` dan `Kopi Story` masing-masing 3 seat tambahan; tagihan keduanya naik dari Rp 100.000 ke Rp 145.000.

---

### [BL-050] Satu Tenant Hanya Bisa Menambah Pengguna Sekali per Bulan Kalender
- **Ditemukan:** 2026-08-07 (saat mengerjakan `[BL-049]`)
- **Sumber:** Test yang gagal dengan galat SQL, bukan telaah — permintaan upgrade kedua di bulan yang sama menabrak indeks unik
- **Status:** Selesai (2026-08-08) — **tertutup tanpa menyentuh skemanya.** Lihat `[ADDITION] Seat Tambahan Jadi Komponen Bulanan, dan Untuk Pertama Kalinya Bisa Dilepas (BL-053)` di `docs/CHANGELOG.md`
- **Prioritas:** Low sekarang; naik jadi Medium begitu `extra_seat_price` bukan nol lagi dan penambahan pengguna jadi jalur berbayar yang sungguhan
- **Area Terdampak:**
  - `database/migrations/2026_07_24_190001_add_upgrade_columns_to_invoices_table.php:34` — `unique(['tenant_id', 'period', 'kind'])`
  - `app/Services/SubscriptionService.php` — `hasUpgradeInvoiceThisPeriod()`, penjaga yang menahannya sekarang
  - `app/Http/Controllers/Billing/UpgradeController.php` — tempat penjaga itu dipanggil
  - `app/Http/Controllers/Billing/SubscriptionController.php` — prop `upgrade.closed_for_period`
  - `resources/js/Pages/Billing/Show.vue` — kalimat penggantinya di panel
- **Deskripsi:**
  Indeks unik `(tenant_id, period, kind)` lahir sebagai penjaga tagihan-langganan-ganda: satu tenant tidak boleh ditagih dua kali untuk bulan yang sama. Efek sampingnya tidak pernah dimaksudkan — karena tagihan upgrade juga memakai kolom `period` berformat `Y-m`, tenant hanya bisa punya **satu tagihan penambahan pengguna per bulan kalender**.
  Selama tagihan upgrade tidak pernah selesai, batas itu tidak pernah tersentuh: `openUpgradeInvoice()` sudah menolak permintaan kedua lebih dulu, dengan kalimat yang masuk akal. Begitu upgrade bisa rampung — gratis seketika (`[BL-049]`) atau lewat verifikasi bukti — permintaan kedua di bulan yang sama lolos penjaga itu dan menabrak indeksnya sebagai **galat 500**. Sudah ditutup 2026-08-07 dengan penjaga yang menolaknya sebagai kalimat, jadi yang tersisa bukan cacat melainkan batasnya sendiri.
  Kenapa batas itu tetap layak dicabut: warung yang mempekerjakan dua orang di bulan yang sama adalah kejadian biasa, bukan kasus tepi. Jalan memutarnya ada — ajukan sekaligus dalam satu permintaan, `max:20` — tapi itu menuntut tenant tahu lebih dulu berapa orang yang akan ia rekrut sebulan ke depan.
- **Usulan Perbaikan:**
  Uniknya sebenarnya hanya dibutuhkan untuk `KIND_SUBSCRIPTION`; tagihan upgrade tidak butuh keunikan apa pun. MySQL tidak punya indeks unik parsial, jadi pola yang portabel adalah **kolom kunci turunan yang null untuk upgrade** — mis. `period_key` berisi `period` untuk tagihan langganan dan `NULL` untuk upgrade, dengan `unique(['tenant_id', 'period_key'])`. MySQL maupun SQLite sama-sama mengabaikan baris ber-NULL pada indeks unik, jadi penjaga tagihan-ganda tetap utuh sementara upgrade bebas berulang.
  **Yang wajib ikut diperiksa saat mengerjakannya:** `issueDuePeriodInvoices()` dan `Platform\InvoiceController::store()` sama-sama memakai `where('period', ...)->exists()` sebagai penjaga periode-ganda, bukan indeksnya. Keduanya harus ikut berpindah ke kunci yang baru, kalau tidak penjaga aplikasinya dan penjaga basis datanya akan menjaga dua hal yang berbeda.
  Setelah itu, `hasUpgradeInvoiceThisPeriod()` beserta prop `closed_for_period` dan kalimatnya di `Show.vue` dibuang — ketiganya ada semata-mata untuk membungkus batas ini dengan sopan.
- **Catatan penutup 2026-08-08 — usulnya tidak jadi dipakai, dan itu bukan kelalaian.**
  Perubahan skema `period_key` yang diusulkan di atas **tidak diperlukan**. Sejak seat tambahan jadi komponen tagihan bulanan (`[BL-053]`), tak ada lagi tagihan `KIND_UPGRADE` yang lahir — jadi indeks unik `(tenant_id, period, kind)` tidak pernah lagi disentuh oleh penambahan pengguna, dan batas "sekali per bulan kalender" kehilangan objeknya. `hasUpgradeInvoiceThisPeriod()`, prop `closed_for_period`, dan kalimatnya di `Show.vue` dibuang persis seperti yang diminta paragraf terakhir entri ini; yang tidak jadi ditulis hanyalah migrasinya.
  Indeks uniknya sendiri **tetap berguna** dan sengaja dibiarkan: ia masih menjaga tagihan langganan ganda, yang memang alasan ia lahir.

---
