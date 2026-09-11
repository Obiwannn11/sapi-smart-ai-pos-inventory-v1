# SAPI — Arsip Backlog Selesai

**Format:** Arsip entri `docs/BACKLOG.md` yang statusnya sudah `Selesai`. Dipisahkan dari file utama pada 2026-07-31 karena bagian ini memakan lebih dari separuh isi file padahal hampir tidak pernah perlu dibaca saat bekerja.

> **Cara membaca file ini:** jangan dibaca utuh. Indeks ringkas seluruh entri di sini ada di `docs/BACKLOG.md` bagian "Riwayat Selesai (Arsip)" — cari ID-nya di sana lebih dulu. Bila isi lengkap sebuah entri memang dibutuhkan, cari headingnya (`grep -n '^### \[BL-0xx\]' docs/BACKLOG-ARCHIVE.md`) lalu baca potongan itu saja.
>
> Entri baru **tidak** ditulis di sini. Entri ditulis di `docs/BACKLOG.md`, dan dipindahkan ke sini setelah statusnya `Selesai`.

---

## Daftar Entri

### [BL-105] Penyelamat Stok Tidak Pernah Menyebut Angkanya — Rantainya Sudah Utuh, Hasilnya Berhenti Jadi Satu Baris Tabel
- **Ditemukan:** 2026-09-07
- **Sumber:** Pertanyaan pemilik — *"fitur apa yang bisa saya maksimalkan dan menjadi keunikan dari semua POS yang ada? saya mau unggulkan 1 fitur"* — dijawab dengan penyisiran seluruh sistem, bukan dengan usulan fitur baru.
- **Status:** **Selesai 2026-09-09.** Butir 1 & 2 mendarat 2026-09-07, pencatat hariannya (`stock:record-expired`, tabel `expired_stock_records`) 2026-09-08, butir 3 & 4 di hari yang sama. Butir terakhir — **pembaca angka periode itu** — mendarat 2026-09-09 sebagai sakelar Hari Ini / Bulan Ini pada kartu Penyelamat Stok di beranda, bersama perbaikan tautan "Lihat di Stok" yang selama ini mendarat di `near_expiry` dan karena itu menjatuhkan seluruh barang dead stock. **Ia dikerjakan lebih awal daripada yang ditahan entri ini,** atas permintaan pemilik. Keberatannya (layar yang memajang Rp 0 untuk periode yang datanya belum terkumpul) tidak dibuang melainkan dipindahkan ke kalimat di bawah angkanya: `recordingStartedOn()` membedakan "tidak ada yang basi" dari "pencatatnya baru berjalan sejak 8 September". Yang berbahaya bukan angka muda, melainkan angka muda yang tidak mengaku muda.
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Services/Upsell/Strategies/PressedStockStrategy.php` — penghasil sarannya
  - `app/Models/DiscountRule.php:26` — trigger `near_expiry`, potongan yang mendalam ke arah hari kedaluwarsa
  - `database/migrations/2026_07_27_100000_create_upsell_events_table.php` — nasib tiap saran, termasuk `extra_amount`
  - `app/Http/Controllers/Owner/ReportController.php:762` — `upsell()`, tempat angkanya hari ini berhenti sebagai satu baris rekap
  - `app/Services/BadgeHelperService.php:97-120` — Badge 4 "Sudah Expired": tahu varian mana, tidak pernah tahu berapa rupiah
  - `app/Models/StockMovement.php:17-25` — lima jenis mutasi, tidak satu pun berarti "dibuang"

- **Deskripsi:**
  Ini bukan temuan cacat. Rantai lengkapnya sudah berdiri dan berjalan:

  ```
  sinyal stok  →  diskon otomatis  →  mulut kasir  →  hasilnya diukur
  (near expiry)   (makin dalam)      (strip saran)   (upsell_events)
  ```

  Keempat mata rantai itu ada, dan tidak satu pun POS pembanding yang disebut di `PRODUCT.md` punya keempatnya sekaligus — yang lain berhenti di mata rantai pertama, yaitu **memberi tahu** bahwa stok akan basi. Yang tidak ada di sini cuma satu hal: **angkanya tidak pernah diucapkan.** `pressed_stock` hari ini hanyalah satu baris berlabel "Barang tertekan" di tabel rekap per jenis pada Laporan Saran Jual. Pemilik tidak pernah membaca kalimat yang akan membuatnya menceritakan SAPI ke orang lain:

  > Bulan ini SAPI menyelamatkan Rp 1.240.000 barang yang tadinya akan basi.

  Bahannya sudah tersimpan sejak 2026-07-27 (`upsell_events.type = pressed_stock`, `status`, `extra_amount`). Yang kurang murni penyajian.

- **Yang perlu dikerjakan, empat butir:**
  1. **Angka "diselamatkan"** — `SUM(extra_amount)` atas `type = pressed_stock` yang diterima, dinaikkan jadi angka utama, bukan sel tabel. Tidak ada skema baru.
  2. **Lawan tandingnya, "terlanjur basi"** — varian yang lewat `expiry_date` dengan `stock > 0`, dinilai pada `cost_price`. Belum ada valuasi kerugian di mana pun di `app/`; Badge 4 menghitung varian, tidak pernah rupiah.
  3. **Sinyal pagi** — barang tertekan hari ini hanya muncul kalau kasir kebetulan sudah punya keranjang berisi (`PressedStockStrategy` hidup di `cart_level`). Owner tidak pernah diberi tahu *"hari ini ada 6 barang yang harus keluar"*.
  4. **Penamaan.** Fitur ini dijual sebagai "Saran Jual (Upsell)" — istilah yang menerangkan mekanismenya kepada orang yang sudah paham, dan tidak menerangkan apa pun kepada yang belum. Nama yang diusulkan: **Penyelamat Stok**, dengan kalimat pembeda *"POS lain memberi tahu stok Anda mau basi. SAPI menjualnya."*

- **Angka butir 2 bisa membaik karena sebab yang salah — lihat `[BL-108]`.** Uji jalur nyata 2026-09-08 membuktikan POS masih bisa menjual barang yang sudah kedaluwarsa, dan setiap penjualan seperti itu **menurunkan** "modal mati di rak" (terukur: Rp 180.000 → Rp 170.000). Angka yang seharusnya mengukur kerugian jadi membaik tepat ketika hal terburuk terjadi, tanpa apa pun di layar yang menjelaskannya. Potret hari ini yang terdampak; `expired_stock_records` tidak ikut rusak karena ia menstempel sekali saat varian melewati kedaluwarsa dan tidak pernah menyentuh barisnya lagi. Perbaikannya milik `[BL-108]`, bukan entri ini.

- **KOREKSI atas butir 4, ditemukan saat mengerjakannya (2026-09-08). Usulan aslinya salah, dan mengikutinya akan membuat aplikasi berbohong.**
  Butir 4 di atas berbunyi seolah "Saran Jual" dan "Penyelamat Stok" adalah dua nama untuk satu barang yang sama. Mereka bukan. `Saran Jual` memuat **empat** jenis saran, dan **tiga di antaranya tidak menyelamatkan stok apa pun**: `attach` (ko-okurensi add-on), `upsize` (naik ukuran), dan `manual` (aturan tulisan owner). Peragaan Saran Jual di landing bahkan memakai contoh murni upsell — *"Espresso Single — tawarkan Double"* (`resources/views/public/landing.blade.php:1089`). Mengganti nama wadahnya jadi "Penyelamat Stok" akan membuat nama itu berbohong tentang tiga perempat isinya.

  Yang dikerjakan karena itu **bukan penggantian nama, melainkan pemberian nama**: rantai barang tertekan mendapat namanya sendiri **di dalam** Saran Jual, dan nama itu dipakai konsisten di tiga layar tempat owner menemuinya — kartu dashboard, bagian di Laporan Saran Jual, dan halaman Aturan Diskon yang selama ini tidak pernah mengaku sebagai tahap *memasang* pada rantai yang sama. "Saran Jual" tetap jadi nama wadahnya, dan itu benar.

  **Label kasir sengaja tidak disentuh.** `UpsellStrip.vue` menyebut jenis ini "Dorong" — kata kerja untuk orang yang sedang diburu waktu. Nama fitur tidak menolongnya sama sekali.

- **Kalimat pembeda di landing DITAHAN, dan penahannya `[BL-107]`.**
  *"POS lain memberi tahu stok Anda mau basi. SAPI menjualnya."* benar sebagai deskripsi mesin, tapi sebagai janji publik ia mendahului kenyataan: 1 dari 42 varian punya `expiry_date`, jadi separuh mesinnya gelap untuk hampir setiap tenant. Memasangnya sekarang mengulang persis apa yang `[BL-083]` dan `[BL-032]` sudah cabut dari halaman yang sama — janji yang aplikasinya belum tepati. Ia dipasang **setelah** angka itu bergerak, bukan sebelum.

- **Batas yang harus disadari sebelum butir 2 dikerjakan — dan ia yang menentukan bentuknya.**
  "Terlanjur basi" **tidak bisa dijawab per periode** dengan data yang ada hari ini. `stock` adalah nilai SEKARANG, bukan sejarah: begitu owner membuang barang kedaluwarsa dan menyesuaikan stoknya jadi nol, kerugian itu lenyap dari basis data tanpa meninggalkan jejak. `stock_movements` tidak menolong — kelima jenisnya (`sale`, `restock`, `adjustment`, `void`, `edit`) tidak ada yang berarti "dibuang", jadi pembuangan tersamar sebagai `adjustment` bersama koreksi hitung dan barang pecah.

  Karena itu butir 2 mendarat sebagai **potret hari ini**, bukan angka periode: *"sekarang ada Rp Y barang kedaluwarsa di rak Anda"*. Itu jujur, dan tetap angka yang bisa ditindaklanjuti. Yang **tidak boleh** dilakukan adalah menyandingkannya dengan angka butir 1 seolah keduanya satu perbandingan berperiode sama — satu angka 30 hari di sebelah satu potret hari ini, dengan label yang tidak membedakannya, adalah grafik yang berbohong. Labelnya yang memikul beban ini, dan karena itu ia bagian dari pekerjaannya, bukan hiasan di atasnya.

  **Angka periodenya menuntut pencatat, dan pencatat itu tidak bisa ditambal mundur** — persis alasan `upsell_events` dulu lahir sebelum permukaannya (`[BL-017]` usulan 5). Bentuk yang disarankan: tugas harian yang menstempel satu baris saat sebuah varian melewati `expiry_date` dengan sisa stok, dinilai pada `cost_price` saat itu. Selama pencatat itu belum ada, tiap bulan yang lewat adalah bulan yang angkanya hilang selamanya. **Ia layak didahulukan atas butir 3 dan 4** justru karena keduanya bisa menyusul kapan saja tanpa kehilangan apa pun.

- **Yang sengaja TIDAK masuk entri ini:** ramalan permintaan berbasis ML. `about.md` masih menjanjikannya sebagai diferensiator Fase 2, dan `[BL-083]` sudah mencabut janji itu dari hero landing pada 2026-08-20. Perbedaannya bukan selera: "menyelamatkan barang yang mau basi" adalah janji yang datanya sudah ada hari ini; "meramal permintaan 30 hari" menuntut tiga bulan data nyata yang belum terkumpul. Menghidupkannya lagi berarti mengulang persis yang sudah dicabut `[BL-083]`.

### [BL-109] Rekap Metode Pembayaran Menjumlahkan Uang yang DISERAHKAN, Bukan yang Dibayarkan — Kembalian Ikut Terhitung Jadi Omzet Tunai
- **Ditemukan:** 2026-09-08, saat verifikasi visual beranda sesudah rekap pembayaran mendapat sakelar Hari Ini / Bulan Ini
- **Sumber:** Bukan dari membaca kode. Angka di layar yang tidak masuk akal: beranda menulis omzet hari ini Rp 39.000 sementara rekap tunai di kartu tepat di bawahnya menulis Rp 89.000.
- **Status:** **Selesai 2026-09-09** — dikerjakan sebagai satu pembaca bersama (`app/Services/PaymentMethodRecap.php`), persis bentuk yang disarankan di bawah. Perilaku kelirunya sudah ada jauh sebelum sakelar bulanan; yang baru hanyalah bahwa ia sempat terbaca sebulan penuh, bukan sehari.
- **Prioritas:** Medium-High — ia angka uang di layar pemilik, dan salahnya selalu ke atas.
- **Area Terdampak:**
  - `app/Http/Controllers/Owner/DashboardController.php` (`paymentMethodTotals()`) — rekap Hari Ini dan Bulan Ini di beranda
  - `app/Http/Controllers/Owner/ReportController.php` — `paymentSummaryFor()` (bulanan) dan rekap harian di `daily()`
  - `app/Http/Controllers/Owner/ReportController.php` (`monthlyExport()`) — blok METODE PEMBAYARAN di CSV
  - `app/Http/Controllers/Api/V1/Mobile/MobileCashDrawerController.php:122` (`summary()`) — sudah diperiksa 2026-09-09: **ikut keliru**, ia mengembalikan `payment_summary` mentah. Tapi `close()` di berkas yang sama (baris 86-88) justru **sudah benar** — ia mengurangkan `SUM(change_amount)` persis seperti rekonsiliasi web. Jadi berkas ini disentuh dua kali dengan sikap berbeda: `summary()` dibetulkan, `close()` jangan diapa-apakan.

- **Yang TIDAK salah, dan ini yang menentukan bentuk perbaikannya.**
  `transaction_payments.amount` menyimpan uang yang **diserahkan pelanggan**, dan itu memang disengaja. Pasangannya ada: `transactions.change_amount`. `CashDrawerReconciliation::for()` memakai keduanya dengan benar —
  `expected_amount = opening + cash_in - change_out + movement_net` (`app/Services/CashDrawerReconciliation.php:99`).
  Jadi modelnya utuh dan rekonsiliasi kas **tidak** terpengaruh. Yang keliru adalah empat pembaca lain yang menjumlahkan suku pertama tanpa pernah mengurangkan suku kedua.

- **Bukti.**
  `TRX-20260908-001` (id 25113): `total_amount` Rp 39.000, satu baris pembayaran tunai Rp 89.000, `change_amount` Rp 50.000. Ketiganya konsisten; rekap di beranda hanya membaca yang tengah.

  Sepanjang riwayat tenant 1 (4.020 transaksi selesai):

  | | |
  |---|---|
  | `SUM(transactions.total_amount)` | Rp 525.441.000 |
  | `SUM(transaction_payments.amount)` | Rp 527.865.000 |
  | Selisih yang dilaporkan berlebih | **Rp 2.424.000** (0,46%) |
  | `SUM(transactions.change_amount)` | Rp 2.424.000 |

  Kembalian menjelaskan **seluruh** kelebihannya, sampai rupiah terakhir: dibayar − kembalian = Rp 525.441.000, sama persis dengan total penjualan. Bukan cuma cocok di agregat — diperiksa baris per baris, **nol dari 4.020** transaksi selesai yang `dibayar − kembalian ≠ total`. Jadi tidak ada kebocoran kedua di balik yang ini: perbaikannya cukup mengurangkan kembalian, dan angkanya akan benar-benar cocok.

- **Kenapa ia tidak pernah terlihat sampai sekarang.**
  Rekapnya berdiri sendiri di layar, tanpa angka lain yang sebanding di dekatnya. Begitu kartu "Omzet Hari Ini" berdiri tepat di atasnya (2026-09-08), selisih Rp 39.000 vs Rp 89.000 jadi terbaca dalam satu pandangan. Sakelar Bulan Ini kemudian memperbesar taruhannya: yang tadinya keliru sebesar kembalian sehari kini keliru sebesar kembalian sebulan.

- **Bentuk perbaikan yang disarankan (belum diputuskan pemilik).**
  Kembalian hanya lahir dari porsi TUNAI — `PaymentModal.vue:130` menghitungnya begitu, dan QRIS maupun transfer tidak mengenal kembalian. Jadi koreksinya tidak boleh disebar rata ke semua metode: ia dikurangkan dari baris bertipe `cash` saja, persis seperti `sumOfType($paymentSummary, cash: true)` di rekonsiliasi.
  Karena rumus yang sama akan ditulis di empat tempat, sebaiknya ia lahir sebagai **satu pembaca bersama** sejak awal — bukan empat salinan yang suatu hari akan berbeda di salah satunya.

- **Verifikasi ulang 2026-09-09 (permintaan pemilik).** Seluruh entri diperiksa lagi terhadap kode dan basis data; masalah intinya utuh dan belum satu baris pun disentuh. Dua hal berubah dari tulisan aslinya:
  1. **Satu angka salah catat, dan ia sempat mengarang pekerjaan yang tidak ada.** `SUM(total_amount)` ditulis Rp 525.599.000; yang benar Rp 525.441.000. Dari situ lahir kesimpulan "sisa Rp 158.000 belum ditelusuri" beserta perintah bahwa perbaikan harus menjawabnya — padahal Rp 158.000 itu persis sebesar kesalahan catatnya sendiri, bukan kebocoran di data. Tabel dan paragrafnya sudah diganti di atas. Dicatat di sini, bukan dihapus diam-diam, karena angka lama itu sempat berdiri sehari dan siapa pun yang terlanjur membacanya perlu tahu ia batal.
  2. **Pertanyaan mobile terjawab** — lihat Area Terdampak. Jumlah pembaca yang keliru tetap empat; yang berubah, kekeliruan mobile sekarang fakta, bukan dugaan.

- **Catatan penomoran:** `[BL-109]` diambil pada 2026-09-08 saat sesi lain sedang menyunting berkas ini. Kalau ternyata bentrok, entri inilah yang dipindah. Diperiksa 2026-09-09: tidak bentrok, nomornya aman.
- **Penutup 2026-09-09.** Keempat pembaca memanggil `PaymentMethodRecap` — kembalian dikurangkan dari baris tunai saja, periodenya diturunkan dari kueri transaksi yang dioper pemanggil (kueri yang sama dengan sumber angka omzet di layar yang sama), dan barisnya dikelompokkan per `payment_methods.id`. Diverifikasi terhadap data tenant 1: ketiga barisnya berjumlah Rp 525.472.000, sama persis dengan `SUM(total_amount)`. Enam pengujian di `tests/Feature/PaymentMethodRecapTest.php`. Entri penutup di CHANGELOG: `[HOTFIX] Kembalian Berhenti Terhitung Sebagai Omzet Tunai — Empat Rekap Metode Pembayaran Jadi Satu Pembaca (BL-109)`.

  **Yang sengaja ditinggalkan, dan bukan karena terlewat:** layar tutup kas kasir (`resources/js/Pages/Cashier/CashDrawerSummary.vue`) menampilkan "Pendapatan per Metode Pembayaran" dari `payment_summary` milik `CashDrawerReconciliation`, yang masih bruto — cacat yang sama, permukaan kelima yang tidak tercatat di entri ini saat ditulis. Ia tidak ikut diperbaiki karena membetulkannya berarti memutuskan apakah baris itu boleh berbeda dari `cash_in` di layar yang sama, dan itu keputusan tampilan milik pemilik. `expected_amount` di sebelahnya tetap benar apa pun jawabannya.

### [BL-093] Pencatatan Uang Keluar Laci Belum Bisa Dilampiri Foto Struk
- **Ditemukan:** 2026-08-21 (dipecah dari `[BL-087]` saat fiturnya mendarat)
- **Sumber:** Saran di dalam pembahasan `[BL-087]` — foto struk sebagai pelengkap alasan tertulis, disetujui pemilik bersama bentuk fiturnya
- **Status:** **Selesai 2026-09-06** — kolom `proof_path`, unggahan ikut permintaan yang sama, dan pratinjaunya di dua layar. Lihat `[ADDITION] Uang Keluar Laci Bisa Dilampiri Foto Struk — Opsional, dan Tanpa Langkah Kedua (BL-093)` di `docs/CHANGELOG.md`.
- **Prioritas:** Low — alasan tertulis sudah wajib dan sudah menutup sebagian besar gunanya; foto mengubah "katanya beli galon" jadi bisa diperiksa, dan itu berguna tapi bukan penentu
- **Area Terdampak:**
  - `app/Services/PaymentProofService.php` — pola unggah-lalu-klaim yang bisa ditiru (`storePending()` / `claim()`), tapi seluruh `pathFor()`-nya terikat `TransactionPayment`
  - `app/Services/ProofFileService.php` — penyimpanan, thumbnail, dan penghapusannya sudah umum dan bisa dipakai apa adanya
  - `database/migrations/..._create_cash_drawer_movements_table.php` — belum punya kolom `proof_path`
- **Deskripsi:** `[BL-087]` mewajibkan alasan tertulis, dan itu yang membedakan pencatatan dari uang yang hilang begitu saja. Yang belum ada: bukti yang bisa diperiksa. Untuk pengeluaran yang punya struk — galon, belanja bahan, parkir — foto mengubah keterangan jadi sesuatu yang bisa dicocokkan.
- **Kenapa tidak ikut mendarat bersama `[BL-087]`:** jalur unggahnya menuntut direktori sendiri dan mekanisme klaim berkas — pekerjaan yang berdiri sendiri dan tidak ada hubungannya dengan rumus `expected_amount` yang jadi inti `[BL-087]`. Menyelipkannya berarti menahan fitur yang sudah selesai demi pelengkap yang tidak menentukan.
- **KOREKSI 2026-08-31 — penghalang yang semula tercatat di sini tidak pernah ada.** Entri ini dibuat dengan alasan "kebijakan retensi belum pernah diputuskan untuk foto bukti mana pun" dan menunggu keputusan itu turun di `[BL-076]`. Dua-duanya keliru, dan keduanya sudah keliru sejak entri ini ditulis:
  1. **Retensinya sudah diputuskan** — keputusan pemilik 2026-08-19 di dalam `[BL-075]` (`docs/BACKLOG-ARCHIVE.md`, "KEPUTUSAN PEMILIK 2026-08-19" butir 1): **tanpa batas untuk sekarang**, tidak ada pembersihan otomatis untuk foto yang sudah melekat. Yang punya perintah pembersih hanya berkas TERTUNDA yang tak pernah diklaim (`payment-proofs:prune-unclaimed`, harian, batas 24 jam), dan di sana ditulis tegas bahwa itu kebersihan disk, **bukan** kebijakan retensi.
  2. **`[BL-076]` tidak memegang persoalan retensi.** Keempat usulannya soal **lokasi** berkas — `DISK` jadi konfigurasi, pemindahan berkas lama, alirkan byte vs URL bertanda tangan, dan larangan melonggarkan privasi. Tidak ada satu baris pun tentang berapa lama berkas disimpan. Menunggu retensi diputuskan di sana berarti menunggu selamanya.

  Yang **memang** bertahan sebagai singgungan dengan `[BL-076]`: butir (c)-nya, "putuskan per jenis berkas, jangan satu kebijakan untuk semuanya". Foto struk galon tidak memuat nama dan nomor pelanggan seperti tangkapan layar e-wallet, jadi ia lebih dekat ke foto produk daripada ke bukti bayar `[BL-075]`. Itu keputusan saat `[BL-076]` dikerjakan, **bukan** syarat untuk menambah kolom di sini.
- **Usulan Perbaikan:** kolom `proof_path` nullable pada `cash_drawer_movements`, tombol unggah opsional di modal pencatatan, dan pratinjaunya di daftar persetujuan pemilik. Presedennya sudah lengkap dan tinggal ditiru apa adanya: direktori sendiri di bawah disk privat, pola unggah-lalu-klaim, perintah pembersih untuk `pending/`-nya sendiri, dan retensi mengikuti kebijakan yang sama — tanpa batas untuk foto yang sudah melekat.
- **Catatan penutup — usulan "tiru presedennya apa adanya" TIDAK diikuti, dan itu perbaikan bukan penyimpangan.** Entri ini menyarankan menyalin pola unggah-lalu-klaim `PaymentProofService` lengkap dengan direktori `pending/`, token, langkah klaim, dan perintah pembersihnya sendiri. Docblock preseden itu menuliskan sendiri kenapa dua langkah ada: checkout POS mengirim JSON BERSARANG, dan menyelipkan berkas ke dalamnya memaksa seluruh payload pindah ke multipart. Formulir mutasi kas datar, jadi syarat itu tidak pernah berlaku di sini. Bersama langkah kedua hilang pula seluruh kelas masalahnya — tidak ada berkas terlantar, jadi tidak ada perintah pembersih kedua yang harus ditulis dan dijaga.
- **Yang justru butuh keputusan ternyata bukan retensinya, melainkan WAJIB atau TIDAK.** Fotonya dibuat opsional: mewajibkannya mengulang persis kesalahan yang `[BL-087]` hindari dari sisi lain — kasir yang tidak bisa mencatat karena struknya tidak ada tetap mengeluarkan uangnya, dan yang hilang bukan fotonya melainkan seluruh keterangan uangnya.

### [BL-100] Nama Varian di Hasil AI Belum Bisa Ditelusuri — Owner Membacanya, Lalu Mencarinya Sendiri
- **Ditemukan:** 2026-09-03 (saat merapikan tampilan & prompt AI Analysis)
- **Sumber:** Permintaan pemilik di sesi yang sama — "tambahkan agar user bisa direferensi atau clickable barangnya, mungkin nanti entah dengan url params atau bagaimana cocoknya"
- **Status:** **Selesai 2026-09-06** — ketiga tahapnya mendarat di hari yang sama. Lihat `[ADDITION] Katalog Produk Akhirnya Bisa Dicari — dan Kata Kuncinya Boleh Datang dari URL (BL-100 Tahap 1)` dan `[ADDITION] Nama Varian di Hasil AI Jadi Bisa Diklik — dan yang Barangnya Sudah Hilang Ditandai (BL-100 Tahap 2 & 3)` di `docs/CHANGELOG.md`.
- **Prioritas:** Medium — hasil analisis kini WAJIB menyebut nama varian persis seperti di data (aturan isi nomor 2 pada prompt yang baru), jadi jumlah nama yang muncul di layar naik, sementara jalan dari nama itu ke barangnya masih nol
- **Area Terdampak:**
  - `app/Services/AiContextService.php:36-45,62-70` — `top_products` dan `profit_by_item` dikelompokkan `variant_name`, dan **hanya nama** yang dikirim; `product_variants.id` maupun `products.id` tidak pernah ikut ke dalam payload
  - `app/Services/ProfitService.php:119,128` — `selectRaw('transaction_items.variant_name')` + `groupBy('transaction_items.variant_name')`; sumber pengelompokannya kolom terdenormalisasi di `transaction_items`, bukan relasi ke variannya
  - `resources/js/Pages/Owner/AiAnalysis/Index.vue` — `renderMarkdown()` menghasilkan HTML dari teks model; hari ini tidak ada satu pun `<a>` yang bisa lahir darinya
  - ~~`app/Http/Controllers/Owner/ProductController.php` — `index()` tidak membaca satu pun query string~~ → **selesai tahap 1**: ia membaca `?q=` dan mengirimnya sebagai prop `filters`
  - ~~`resources/js/Pages/Owner/Products/Index.vue` — tidak ada pencarian nama, dan tidak ada state yang dibaca dari URL~~ → **selesai tahap 1**: ada kotak pencarian yang mencocokkan nama produk, kategori, dan varian, dan nilai awalnya datang dari `filters.q`. `?variant=` sengaja belum dibuat — bentuk tautannya baru diputuskan di tahap 2
- **Deskripsi:**
  Hasil analisis menyebut varian dengan namanya — "margin `Iced` 70%", "`Croissant Plain` sudah kedaluwarsa" — dan di situ jejaknya berhenti. Untuk menindaklanjutinya owner harus membuka Produk di tab lain, menggulir katalognya, dan mencocokkan nama dengan mata. Analisis yang menyuruh bertindak tapi tidak mengantar ke tempat bertindak menyisakan pekerjaan manual yang justru paling sering membuat sarannya tidak dikerjakan.
- **Dugaan Penyebab — dan ini yang membuatnya bukan pekerjaan sepele:**
  1. **Payload-nya memang tidak membawa id.** Konteks LLM dikelompokkan berdasarkan `variant_name`, kolom terdenormalisasi yang disalin ke `transaction_items` saat transaksi dibuat. Model tidak pernah melihat id, jadi ia tidak bisa menuliskannya walau diminta.
  2. **Namanya tidak dijamin unik, dan tidak dijamin masih ada.** Dua produk berbeda boleh punya varian bernama sama (`Iced`), dan varian yang sudah dihapus tetap hidup sebagai teks di `transaction_items`. Pemetaan nama → satu id karena itu bisa mengembalikan nol, satu, atau banyak — ketiganya perlu punya perilaku sendiri, dan yang "banyak" tidak boleh diam-diam memilih yang pertama.
  3. ~~**Tujuannya belum ada.**~~ **Sudah ada sejak 2026-09-06.** `owner/products` menerima `?q=` dan punya kotak pencarian yang ikut membaca nama varian — justru nama yang paling sering disebut hasil analisis. Yang belum ada tinggal pemetaan nama → id (butir 1 dan 2 di atas masih berlaku utuh).
- **Usulan Perbaikan (urut, tiap tahap berdiri sendiri):**
  1. ~~**Buat tujuannya lebih dulu**~~ — **SELESAI 2026-09-06.** `ProductController::index()` membaca `?q=` dan halaman Vue-nya memakainya sebagai keadaan awal kotak pencarian. Pencariannya berjalan di client (katalognya memang sudah dikirim utuh), jadi URL hanya menyalakannya. `?variant=` tidak dibuat: hari ini belum ada yang melahirkannya, dan bentuknya baru diputuskan di tahap 2 — parameter yang tidak dipanggil siapa pun persis bentuk yang `[BL-098]` baru saja hapus.
  2. **Petakan nama → varian di server, bukan di teks model.** Setelah analisis selesai, cocokkan `variant_name` yang dikenal (dari konteks yang dipakai) dengan katalog aktif, dan kirim hasilnya sebagai peta terpisah di samping `result` — mis. `{ "Iced": { "product_id": 12, "variant_id": 30 } }`. Nama yang berpasangan lebih dari satu varian aktif **tidak masuk peta** dan tetap tampil sebagai teks biasa. ~~Begitu pula yang tidak berpasangan sama sekali~~ — **diubah oleh keputusan pemilik 2026-09-06 di bawah**: nama yang variannya sudah tidak ada masuk peta dengan keadaan tersendiri, bukan dibuang.
  3. **Baru sesudah itu tautkan di renderer.** `renderMarkdown()` menyisipkan `<a>` hanya untuk nama yang ada di peta. Menyuruh model menulis markdown link sendiri jangan dipilih: ia akan mengarang tujuan untuk nama yang tidak ada, dan hasilnya tautan mati yang terlihat sah.
- **Keputusan pemilik 2026-09-06 — nama yang produknya sudah terhapus DITANDAI, bukan dibiarkan jadi teks biasa.** "Produk ini sudah tidak ada di katalog" memang informasi yang dicari, dan menyembunyikannya membuat owner mengira sarannya belum ditindaklanjuti padahal barangnya sudah tidak ada.
- **Yang ikut ditentukan keputusan itu, dan harus dikerjakan bersamanya:** petanya kini punya TIGA keluaran, bukan dua — `tertaut` (tepat satu varian aktif), `hilang` (pernah ada di `transaction_items`, tidak punya varian aktif), dan tidak-di-peta (nama yang berpasangan lebih dari satu varian aktif; tetap teks biasa, karena menebak salah satunya lebih buruk daripada diam).
- **Satu jebakan yang lahir dari keputusan ini:** penanda "sudah dihapus" hanya boleh dipasang pada nama yang BENAR-BENAR ada di payload konteks yang dipakai analisis itu. Nama karangan model — yang dilarang aturan isi nomor 2 pada prompt, tapi larangan bukan jaminan — juga tidak punya varian aktif, dan menandainya "sudah dihapus" mengubah halusinasi jadi pernyataan yang terlihat berwenang. Tanpa penjaga ini, tahap 3 membuat hasil AI lebih dipercaya justru di tempat ia paling salah.
- **Catatan penutup — penjaga terhadap halusinasi tidak ada di rancangan awal, dan ia yang menentukan bentuk kolomnya.** Entri ini semula membayangkan satu pemetaan nama → id. Begitu keputusan pemilik menambahkan keadaan `missing`, muncul lubang yang tidak kelihatan sebelumnya: nama KARANGAN model juga tidak punya varian aktif, jadi ia akan ditandai "sudah dihapus" — karangan yang naik pangkat jadi pernyataan berwenang. Karena itu yang disimpan saat analisis dibuat adalah DAFTAR NAMA yang benar-benar masuk konteks (`ai_analyses.context_variants`), bukan hasil pemetaannya, dan renderer memakai perpotongan dua daftar. Konsekuensi lain dari pilihan itu: pemetaan dikerjakan saat DIBACA, jadi tautannya selalu menunjuk katalog hari ini, bukan katalog saat analisisnya dibuat.
- **`?variant=` yang tahap 1 tolak untuk dibuat akhirnya lahir di sini,** bersama satu-satunya pemakainya. Urutan itu disengaja dan terbukti benar: bentuk tautannya baru bisa diputuskan setelah diketahui apa yang menghasilkannya.

### [BL-099] Saklar Per-Jenis Saran Jual Hanya Ada di `config/upsell.php` — Owner Tak Punya Jalan ke Sana
- **Ditemukan:** 2026-09-03 (saat merombak tampilan tiga halaman Saran Jual & Aturan)
- **Sumber:** Keterangan di halaman Saran Jual yang berbunyi "Jenis yang tak pernah diterima bisa dimatikan di `config/upsell.php`" — kalimat yang menyuruh pemilik warung menyunting berkas PHP, dan dibuang saat perombakan
- **Status:** **Selesai 2026-09-06** — empat kolom boolean per-tenant, dibaca sebagai lapisan di ATAS config. Lihat `[ADDITION] Saklar Per-Jenis Saran Jual Pindah dari Berkas PHP ke Layar Owner — dan Config Tetap Menang (BL-099)` di `docs/CHANGELOG.md`.
- **Prioritas:** Medium — bukan cacat, tapi lubang di sebuah lingkaran yang sudah hampir tertutup: laporannya memisahkan angka per jenis SUPAYA jenis yang tak pernah laku bisa dimatikan, lalu tidak menyediakan tempat mematikannya
- **Area Terdampak:**
  - `config/upsell.php` — array `types` (`attach`, `pressed_stock`, `upsize`, `manual`); komentarnya sendiri menulis "jenis yang terbukti tidak pernah diterima dimatikan di sini, bukan ditebak"
  - `app/Http/Controllers/Owner/UpsellRuleController.php` — `disabledTypes()`; sisi bacanya sudah ada dan sudah tampil di halaman Aturan Saran Jual sebagai peringatan kuning
  - `resources/js/Pages/Owner/Reports/Upsell.vue` — tabel "Per Jenis Saran", tempat kesimpulannya diambil
  - `resources/js/Pages/Owner/Settings/Operations.vue` + `app/Http/Controllers/Owner/Settings/SystemBehaviorController.php` — tempat saklarnya semestinya berada, bersama `upsell_mandatory` yang sudah per-tenant
- **Deskripsi:**
  Laporan Saran Jual memecah angkanya per jenis saran, dan alasan pemecahan itu ditulis terang-terangan di `config/upsell.php`: jenis yang tak pernah diterima sebaiknya dimatikan berdasarkan bukti, bukan tebakan. Kesimpulannya bisa diambil owner dari layar; tindakannya tidak — satu-satunya saklar ada di berkas PHP yang hanya bisa disentuh orang dengan akses server.
  Sisi bacanya sudah lengkap: `disabledTypes()` mengirim jenis yang mati ke layar, dan halaman Aturan Saran Jual menampilkannya sebagai peringatan kuning. Yang belum ada hanya sisi tulisnya.
- **Yang membuatnya lebih mahal daripada kelihatannya:** `upsell.types` adalah konfigurasi **global**, satu nilai untuk seluruh tenant, sedangkan saklar yang berguna bagi owner harus **per-tenant**. Setelan per-tenant di aplikasi ini berbentuk kolom nyata di tabel `tenants` (`kitchen_queue_enabled`, `upsell_mandatory`, `min_margin_percent`, …), bukan satu kolom JSON serba guna — jadi ongkosnya migrasi berisi empat kolom boolean baru (atau satu kolom JSON yang memutus pola yang sudah ada), bukan sekadar menambah tiga checkbox di layar Setelan.
- **Usulan Perbaikan:** empat kolom boolean per-tenant dengan bawaan `true`, dibaca `UpsellIndexBuilder` sebagai lapisan di ATAS `config/upsell.php` — config tetap jadi saklar darurat global, tenant hanya boleh mematikan yang masih hidup secara global, tidak sebaliknya. Saklarnya diletakkan di Setelan → Cara Kerja Sistem bersama `upsell_mandatory`, dan diberi tautan dari tabel "Per Jenis Saran" di laporan supaya kesimpulan dan tindakannya bersebelahan.
- **Yang JANGAN dilakukan:** menghidupkan lagi kalimat "dimatikan di `config/upsell.php`" di layar mana pun. Menyebut jalan yang tidak bisa ditempuh pembacanya lebih buruk daripada diam, karena ia terbaca seperti izin.
- **Catatan penutup — larangan "jangan sebut `config/upsell.php` di layar" ternyata menuntut DUA daftar, bukan satu.** Sebelum ini `disabled_types` adalah satu daftar datar. Begitu ada saklar tenant, satu daftar memaksa layarnya memilih antara menawarkan tautan untuk jenis yang tidak punya tombol, atau diam soal jenis yang punya — keduanya melanggar entri ini. Jadi halaman Aturan kini menerima `disabled_types` (dimatikan owner, bertautan ke Setelan) dan `unavailable_types` (mati global, tanpa tautan dan tanpa menyebut berkasnya). Laporan justru menggabungkan keduanya, dengan sengaja: di sana pertanyaannya cuma "nol ini gagal atau mati", dan asal matinya tidak menentukan apa pun.

### [BL-101] Satu Barang Bisa Mengisi Dua Slot Kasir Sekaligus Lewat Dua Jenis Saran Berbeda
- **Ditemukan:** 2026-09-04 (saat memeriksa pratinjau slot secara visual, dengan aturan uji buatan sendiri)
- **Sumber:** Baris pratinjau "Espresso - Single" menampilkan **Espresso - Double dua kali** — sekali sebagai `Pilihan pemilik`, sekali sebagai `Naik ukuran` — dan keduanya menang slot
- **Status:** **Selesai 2026-09-06** — dedup kedua mendarat di `rankForCart()` DAN di `useUpsell.js`. Lihat `[HOTFIX] Satu Barang Berhenti Memakan Dua Slot Kasir Lewat Dua Jenis Saran Berbeda (BL-101)` di `docs/CHANGELOG.md`.
- **Prioritas:** Medium — tidak ada uang yang salah, tapi ia memakan slot yang paling langka di fitur ini. Kasir hanya punya tiga, dan dua di antaranya bisa terpakai untuk barang yang sama
- **Area Terdampak:**
  - `app/Services/Upsell/Suggestion.php` — `key()`, bentuknya `type:pemicu:varian`
  - `app/Services/Upsell/UpsellIndexBuilder.php` — `rankForCart()`, tempat dedup satu-satunya terjadi (`$picked[$suggestion['key']]`)
  - `resources/js/Components/UpsellStrip.vue` — tempat akibatnya terlihat kasir
- **Deskripsi:**
  Dedup di `rankForCart()` memakai `key()`, dan `key()` mengandung **jenis** sarannya. Dua strategi yang kebetulan menunjuk varian yang sama karena itu menghasilkan dua kunci berbeda dan lolos berdua.
  Cara paling mudah menemuinya: owner menulis aturan "dorong Espresso Double" (tanpa pemicu), sementara mesin sudah menyarankan Espresso Double sebagai naik ukuran dari Espresso Single. Begitu Espresso Single masuk keranjang, strip kasir memuat Espresso Double dua kali, dengan dua lencana berbeda dan dua alasan berbeda, memakan dua dari tiga slot.
  **Ini perilaku lama, bukan bawaan perombakan tampilan 2026-09-03/04.** Yang berubah hanyalah kemudahan menemuinya: sebelum `[BL-074]` seluruh saran datang dari mesin, dan dua strategi mesin jarang menunjuk varian yang sama. Begitu owner bisa menuliskan targetnya sendiri, tabrakan ini jadi wajar — orang mendorong barang yang memang layak didorong, dan mesin sudah lebih dulu memikirkan hal yang sama.
- **Kenapa `key()` TIDAK boleh sekadar dibuang jenisnya:** kunci itu dipakai dua hal lain — client mengingat saran mana yang sudah ditutup kasir, dan checkout mencocokkan event ke saran yang tepat. Menyamakan kunci dua saran berbeda akan membuat laporan kehilangan kemampuan memisahkan `attach` dari `upsize`, yang justru inti tabel "Per Jenis Saran".
- **Usulan Perbaikan:** dedup **kedua** di `rankForCart()`, setelah pengurutan skor dan sebelum pemotongan slot: buang saran yang `suggested_variant_id`-nya sudah dipakai saran berskor lebih tinggi. Yang bertahan otomatis yang paling mendesak, dan aturan manual menang karena lantai skornya — jadi "Pilihan pemilik" yang tampil, bukan "Naik ukuran". `key()` tidak perlu disentuh sama sekali.
- **Yang perlu diperiksa saat mengerjakannya:** saran ber-modifier (`attach`) menunjuk `suggested_modifier_id`, bukan varian; dedup per varian tidak boleh diam-diam membuang dua add-on berbeda pada produk yang sama.
- **Catatan penutup — entrinya kurang satu tempat, dan itu tempat yang paling penting.** Usulan perbaikan di atas hanya menyebut `rankForCart()`. Kasir tidak memanggilnya: ia memilih slotnya sendiri di `resources/js/composables/useUpsell.js`, di atas indeks yang sama. Menambal server saja akan membersihkan pratinjau owner sementara layar kasir — tempat cacatnya dilaporkan — tetap memuat barang yang sama dua kali. Dedupnya karena itu mendarat di kedua sisi, dengan satu test yang membaca sumber `useUpsell.js` supaya keduanya tidak diam-diam bercabang.

### [BL-098] `Platform\InvoiceController::index()` Merender Halaman yang Sudah Tidak Ada, dan Tidak Ada Rute yang Bisa Memanggilnya
- **Ditemukan:** 2026-09-02 (saat menambahkan asersi `->component()` untuk seluruh halaman Platform)
- **Sumber:** Penyisiran cakupan tes lapisan tampilan — `Platform/Invoices/Index` satu-satunya halaman Platform yang tidak bisa dipatok namanya, dan sebabnya ternyata bukan pada tesnya
- **Status:** **Selesai 2026-09-06** — `index()` dan tiga impor yatimnya dihapus; sebuah penjaga baru menyisir seluruh `Inertia::render()`. Lihat entri **[DEPRECATE] `Platform\InvoiceController::index()` Dihapus — 30 Baris yang Terbaca seperti Fitur Hidup (BL-098)** di `docs/CHANGELOG.md`.
- **Prioritas:** Low — hari ini tidak ada yang rusak justru karena tidak ada yang bisa memanggilnya. Yang dipertaruhkan kejujuran kode: 30 baris yang terbaca seperti fitur hidup, lengkap dengan query, paginasi, dan pencatatan audit
- **Area Terdampak:**
  - `app/Http/Controllers/Platform/InvoiceController.php:41-70` — `index()`, merender `Platform/Invoices/Index`
  - `routes/web.php:628-629` — `Route::redirect('/invoices', '/platform/subscriptions')->name('invoices.index')`; alamat lamanya kini pengalihan, dan **tidak ada rute lain** yang menunjuk `index()`
  - `resources/js/Pages/Platform/Invoices/` — **sudah tidak ada**, terhapus di `fc284f0` (2026-08-01, "give tenants a tabbed detail page, and the console one shape")
- **Deskripsi:**
  Daftar tagihan pindah ke halaman Langganan saat konsol platform diseragamkan; alamat lamanya sengaja dipertahankan sebagai pengalihan, dan komentar di `routes/web.php` menuliskan alasannya. Yang tertinggal adalah `index()`-nya sendiri.
  Ia mati dua lapis sekaligus, dan itu yang membuatnya tidak berbahaya sekaligus tidak jujur: **tidak ada rute** yang memanggilnya, dan seandainya ada, komponen Vue yang direndernya **sudah tidak ada di disk** sehingga permintaannya berakhir 500. Satu-satunya cara menemukannya adalah membandingkan daftar `Inertia::render()` di controller dengan daftar halaman yang benar-benar punya rute — bukan sesuatu yang dilakukan siapa pun dalam pekerjaan sehari-hari.
  Dua hal yang ikut mati bersamanya dan mudah terlewat saat menghapus: `PlatformAuditLog::recordRoutine('invoices.index')` (`InvoiceController.php:56`) adalah aksi audit yang tidak akan pernah tercatat lagi, dan `use Inertia\Inertia` + `use Inertia\Response` (`:21-22`) tidak punya pemakai lain di berkas itu — `index()` satu-satunya method yang merender halaman di sana.
- **Yang JANGAN ikut dihapus, dan sudah diperiksa satu per satu:**
  1. **Rute pengalihannya tetap.** Ia melayani tautan dan bookmark lama, dan alasannya sudah ditulis di tempatnya. Nama rutenya (`platform.invoices.index`) memang tidak dipakai `route()` di mana pun — hanya definisinya sendiri — tapi itu bukan alasan menghapus pengalihan URL-nya.
  2. **`InvoiceResource` tetap hidup.** `app/Services/Platform/AccountOverview.php:264` memakainya, jadi menghapus `index()` tidak menyeret resource-nya ikut mati.
  3. **`Tenant` tetap dipakai** di `store()` (`:87`), jadi importnya tidak ikut gugur.
- **Usulan Perbaikan:** hapus `index()` beserta dua import Inertia yang jadi yatim, sisakan seluruh rute dan method lain apa adanya. Bila suatu saat halaman daftar tagihan tersendiri diinginkan lagi, itu fitur baru dengan komponennya sendiri — bukan menghidupkan kembali method ini, yang bentuk datanya sudah tidak cocok dengan halaman mana pun yang ada sekarang.

### [BL-051] Tenant yang Sudah Ditangguhkan Tidak Punya Tagihan untuk Dibayar
- **Ditemukan:** 2026-08-07 (saat meninjau `renewPeriod()` × `[BL-044]`)
- **Sumber:** Telaah, bukan laporan — sisi lain dari lubang masa tenggang yang ditutup 2026-08-07
- **Status:** **Selesai 2026-08-31** — keputusan pemilik jatuh pada **opsi (ii)**. Lihat `[ADDITION] Tenant yang Ditangguhkan Punya Jalan Pulang — Satu Tagihan Pemulihan, Diminta Sendiri (BL-051)` di `docs/CHANGELOG.md`.
- **Prioritas saat dibuka:** Low (belum ada satu pun tenant `suspended`); akan naik ke Medium begitu tenant pertama benar-benar tertangguh. Dikerjakan sebelum hari itu tiba — justru karena hari itu adalah hari terburuk untuk menemukan bahwa jalan pulangnya tidak ada.
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
- **Keputusan pemilik 2026-08-31 — opsi (ii), dan apa yang ikut diputuskan bersamanya:**
  **Yang dipilih:** tombol "Minta tagihan pemulihan" di halaman langganan, hanya untuk owner, hanya saat tenant berstatus `suspended`. Tagihannya lahir karena tenant memintanya, bukan karena kalender — itu seluruh perbedaannya dari opsi (iii), yang akan membuat tenant yang **sudah pergi** terus menerima tagihan bulanan seumur hidup.

  **Butir (b) ditegakkan tanpa penjaga baru.** Tagihan pemulihan memakai `current_period_end` yang beku, jadi kunci `(tenant_id, period, kind)`-nya sama persis dengan yang sudah dijaga penerbit massal — penjaga periode-ganda yang ada menolak tagihan kedua tanpa perlu tahu dari mana yang pertama datang. Satu pelanggaran tetap satu tagihan; satu pembayaran tetap satu periode. Docblock `renewPeriod()` diperbarui: ia dulu menyebut "penerbitan yang ikut berjalan untuk tenant `suspended`" sebagai salah satu dari dua hal yang bisa mematahkan aturan "tunggakan tidak ditumpuk", dan sekarang menjelaskan kenapa yang ini justru tidak.

  **Opsi (i) tidak dibuang — ia jadi jalur untuk tiga keadaan yang memang bukan soal uang.** Tombolnya menolak, dengan kalimatnya masing-masing, saat tarifnya tak bisa dihitung, totalnya nol, atau ringkasan omzet penentu tarif Adaptif belum ada. Tenant di keadaan itu tidak punya angka apa pun untuk ditransfer, jadi tak ada tagihan yang bisa menjawabnya; ia diarahkan menghubungi pengelola. Yang **tidak** dilakukan: menerbitkan tagihan dari paket penampung supaya tombolnya selalu berhasil — itu menagih tenant subsidi dengan tarif termahal karena sebuah cron gagal.

  **Daftar status di `issueDuePeriodInvoices()` TIDAK dilonggarkan,** dan itu disengaja. Alasan aslinya masih berlaku sepenuhnya: menerbitkan tagihan atas bulan yang tidak bisa dipakai berarti menumbuhkan utang yang tidak pernah diminta siapa pun. Menambahkan `Tenant::STATUS_SUSPENDED` ke saringan itu akan mengembalikan tepat keadaan yang entri ini tolak, dan docblock-nya kini mengatakannya dengan kalimat itu juga.

  **Satu refactor yang dituntut oleh lahirnya penerbit kedua:** urutan tarif → seat → kuota AI → pecahan `billing_breakdown` diangkat jadi `draftSubscriptionInvoice()`, dipakai kedua penerbit. Dua penerbit yang menyalin urutan itu pasti bercabang begitu salah satunya diperbaiki, dan cabang di jalur uang adalah jenis kesalahan yang paling lama tidak terlihat.

  **Belum pernah dilihat di layar sungguhan** — tidak ada tenant `suspended` di basis data dev, persis alasan entri ini berprioritas Low sejak dibuka. Yang membuktikannya sepuluh tes di `tests/Feature/Subscription/ReactivationTest.php`, termasuk lingkaran penuhnya: minta → bayar → `active` dengan periode yang mendarat di masa depan.
- **Entri terkait di `CHANGELOG.md`:** `[ADDITION] Tenant yang Ditangguhkan Punya Jalan Pulang — Satu Tagihan Pemulihan, Diminta Sendiri (BL-051)`

---

### [BL-094] `eager: true` Menyatukan 56 Halaman Vue Jadi Satu Bundel untuk Pengguna yang Sudah Masuk
- **Ditemukan:** 2026-08-24 (butir (c) `[BL-091]`, sengaja dipisahkan saat entri itu dikerjakan)
- **Sumber:** Butir (c) `[BL-091]` — "layak ditinjau terpisah, dan bukan bagian dari entri ini"
- **Status:** **Selesai 2026-08-31** — butir (a), (b), dan (c) seluruhnya dikerjakan. Lihat `[DECISION] Halaman Vue Berhenti Dikirim Berombongan — Satu Bundel 1.136 KB Jadi Chunk per Halaman (BL-094)` di `docs/CHANGELOG.md`.
  > **Hasilnya, diukur pada build nyata:** entry 1.136 KB → **264 KB** (−77%), 56 chunk halaman. Halaman terberat (`Owner/Dashboard`) 493 KB / 15 permintaan; median 309 KB / 11; rata-rata 9,6 permintaan.
  >
  > **Butir (b) menjawab kekhawatirannya sendiri:** "56 permintaan kecil" tidak terjadi — paling banyak 15, dimuat paralel lewat `__vitePreload`. Halaman terberat pun hanya 43% dari bundel lama.
  >
  > **Butir (c) diperiksa dan `delay: 500` sengaja TIDAK diubah.** Inertia menunggu `resolve` sebelum menukar halaman, jadi unduhan chunk kini di dalam jendela bilah — pada cache dingin kerangka pemuatan tidak lagi yang pertama sampai. Kesimpulannya tetap: puluhan milidetik pada sambungan wajar, dan pada sambungan lambat bilah itu memang yang dibutuhkan. Yang diubah komentarnya, supaya alasan "jarang terlihat" tidak bertumpu pada keadaan yang sudah tidak berlaku.
  >
  > **Mode offline diperiksa terpisah dan tidak rusak** — `sw.js` cache-first `/build/assets/**` tanpa precache, jadi chunk POS ikut ter-cache pada muat online yang sama dengan HTML-nya. `CACHE_VERSION` tidak dinaikkan.
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

### [BL-065] Pajak/PPN — Dari Nol Kata di Basis Kode Sampai Terpungut, Tercetak, Terlapor, dan Bisa Dibuka Kuncinya
- **Ditemukan:** 2026-08-08
- **Sumber:** Saran pasca-peragaan — "pajak + PPN, mode munculkan include atau tidak untuk ke pelanggan (misal harga dijual include PPN atau ditanggung customer)"
- **Status:** **Selesai 2026-08-29** — seluruh butir (a)–(f) dan kedelapan keputusannya terpasang. Diarsipkan 2026-08-30, setelah service charge dipindah jadi entrinya sendiri (`[BL-097]`) — satu-satunya yang tersisa, dan ia bukan tentang pajak.
  > **SELESAI 2026-08-28 — kedelapan keputusan di bawah sudah terpasang.** Lihat `[SCHEMA] Pajak Masuk ke Kasir — Uang Transaksi Berhenti Jadi Satu Angka (BL-065)` di `docs/CHANGELOG.md`. Tenant bisa menyalakan pajak (bawaan mati), memilih mode sekali, dan struknya mencetak pembagiannya — layar, termal, dan mobile.
  >
  > **SELESAI 2026-08-29 — butir (e) mendarat.** Lihat `[ADDITION] Pajak Terpungut Punya Angkanya Sendiri di Laporan (BL-065 Butir e)` di `docs/CHANGELOG.md`. Laporan harian dan bulanan memisahkan omzet sebelum pajak, pajak terpungut, dan yang dibayar pelanggan; unduhan CSV bulanan mendapat kolom pajak per tanggal. Labelnya diambil dari transaksinya, bukan dari setelan tenant hari ini.
  >
  > **DIPUTUSKAN 2026-08-29 — dasar margin `ProfitService`.** Lihat `[DECISION] Margin Diukur terhadap Pendapatan Toko, Bukan terhadap Pajak yang Menumpang di Atasnya (BL-065)` di `docs/CHANGELOG.md`. Catatan di entri ini keliru menyebut hanya mode exclusive yang terpengaruh; **kedua mode salah sebesar `tax_amount`**, dan inclusive yang lebih berbahaya karena angkanya tidak bergerak sama sekali saat pajak dinyalakan.
  >
  > **SELESAI 2026-08-29 — jalan buka kunci butir 4.** Lihat `[ADDITION] Kunci Pajak Punya Jalan Bukanya — Operator Membuka Kuncinya, Bukan Setelannya (BL-065 Butir 4)` di `docs/CHANGELOG.md`. Operator membuka lewat rincian tenant di konsol platform dengan alasan tertulis; jendelanya tujuh hari dan habis sekali pakai. Yang dibuka adalah kuncinya — panel platform tidak pernah menulis setelan pajak siapa pun.
  >
  > **DIARSIPKAN 2026-08-30.** Pajaknya selesai seluruhnya; yang menahan entri ini tetap terbuka bukan lagi pajak, melainkan service charge yang menempel padanya karena kebetulan terlihat saat mengerjakannya. Ia dipindah jadi `[BL-097]` supaya "Open" tidak kehilangan artinya dengan menahan pekerjaan yang sudah tuntas.
- **Prioritas:** Medium (naik ke High bila ada calon klien yang wajib memungut PPN)
- **Batas lingkup — ditegaskan pemilik 2026-08-28:**
  Entri ini **hanya** tentang pajak pada transaksi **tenant → pembeli di kasir**. Pajak atas tagihan **platform → tenant** (`invoices`, langganan SaaS) **tidak termasuk** dan sengaja tidak dibahas: fiturnya diutamakan ada di klien, bukan di akun platform. Jangan menyelundupkan PPN langganan ke dalam pekerjaan ini — dasar hukum, siapa yang memungut, dan tabelnya semuanya berbeda.
- **Area Terdampak:**
  - `database/migrations/2026_03_06_000011_create_transactions_table.php:17` — `total_amount` saja; **tidak ada** `subtotal_amount`, `tax_amount`, atau `service_charge_amount`
  - `database/migrations/2026_03_06_000013_create_transaction_items_table.php` — `unit_price` + `subtotal`, tanpa penanda pajak
  - `resources/js/Components/ReceiptModal.vue:38-41,170-178` — struk menghitung "Subtotal" dengan menjumlahkan item di sisi klien, lalu langsung ke "TOTAL"; tidak ada baris di antaranya
  - **Tujuh tempat yang menghitung total keranjang dan wajib sepakat sampai rupiah terakhir:** `app/Services/TransactionService.php`, `app/Services/TransactionEditService.php:225-264`, `app/Http/Controllers/Api/V1/Mobile/MobileTransactionController.php`, `resources/js/Pages/Cashier/POS.vue`, `resources/js/Components/ReceiptModal.vue`, `resources/js/Components/TransactionSuccessModal.vue`, `resources/js/services/escpos.js`
  - `app/Http/Controllers/Owner/Settings/SystemBehaviorController.php:34-82` — sakelar fitur hari ini **self-serve pemilik tenant**; di sinilah penguncian mode harus berlaku
  - Pencarian `tax|ppn|pajak` di seluruh `app/`, `config/`, dan `database/migrations`: **nol hasil**
- **Deskripsi:**
  Ini bukan fitur yang perlu diperbaiki, melainkan yang belum pernah ada. Tidak ada tarif pajak, tidak ada kolom, tidak ada baris di struk. Aplikasi hari ini mengasumsikan harga jual adalah angka final dan tidak punya cara menyatakan berapa bagian dari angka itu yang sebetulnya pajak.
  Yang membuatnya bukan sekadar pekerjaan tampilan adalah dua mode yang disebut di saran. **Inclusive** berarti tarif katalog sudah mengandung pajak dan pajaknya *diurai ke belakang* dari total — omzet pemilik turun ~9,9% pada tarif 11% padahal yang dibayar pelanggan tidak berubah sepeser pun. **Exclusive/dibebankan ke pembeli** berarti pajak *ditambahkan di depan* total — yang dibayar pelanggan naik, sementara pendapatan pemilik tetap. Keduanya menyentuh angka uang yang sudah tercatat, jadi pilihan modenya bukan preferensi tampilan.
- **Duduk perkara pajaknya (diverifikasi terhadap sumber resmi, 2026-08-27):**
  Tiga pungutan berbeda sering sama-sama disebut "pajak" oleh pemilik toko, dan hanya dua yang urusan kasir:
  1. **PPN — tarif efektif 11%** (12% dikali DPP nilai lain 11/12; 12% penuh hanya untuk barang/jasa mewah, PMK 131/2024). Wajib dipungut setelah omzet melewati **Rp 4,8 miliar/tahun**; di bawah itu tenant berstatus *pengusaha kecil* dan **tidak wajib memungut**. **Urusan kasir.**
  2. **PBJT makanan/minuman (eks Pajak Restoran/PB1)** — maksimum **10%**, tarif dan ambang omzet pengecualiannya ditetapkan **Perda** masing-masing daerah (UU HKPD Pasal 51 ayat 2). Penyerahan makanan/minuman di restoran/kafe **bukan objek PPN**; PBJT menggantikannya. **Urusan kasir** — dan inilah yang berlaku untuk tenant kafe, bukan PPN.
  3. **PPh final UMKM 0,5%** — Rp 500 juta pertama per tahun bebas untuk wajib pajak orang pribadi (PP 20/2026; ambang itu tidak ikut berubah). **Bukan urusan kasir**: tidak pernah dibebankan ke pembeli, dihitung dari omzet dan dibayar pemilik. Tempatnya di laporan (`[BL-063]`) sebagai angka bantu, **bukan** di sakelar pajak.

  Catatan koreksi: angka "60–100 juta" yang sempat disebut sebagai ambang bebas pajak **tidak ada dasarnya**. Yang mendekati adalah PTKP Rp 54 juta/tahun, dan itu pajak penghasilan pribadi yang tidak ada hubungannya dengan POS.
- **Keputusan pemilik 2026-08-28 — delapan butir, beserta alasan menolak alternatifnya:**
  1. **Satu tarif per tenant, bukan per produk.** Aritmetika keranjang hidup di tujuh tempat (lihat Area Terdampak), tiga di antaranya JavaScript dan satu berjalan **offline** — kasir offline sudah menyerahkan struk sebelum server melihat transaksinya, jadi selisih satu rupiah berarti kertas di tangan pelanggan bertentangan dengan catatan. Satu perkalian yang ditulis tujuh kali masih mudah dibuat sama; *group-by-tarif* yang ditulis tujuh kali tidak.
     **Kasus tandingannya nyata dan diakui:** minimarket PKP — sembako dibebaskan PPN sementara barang lain tidak, dan tarif rata berarti memungut pajak yang tidak terutang atas beras. Yang membuat penundaan tetap aman: (a) minimarket di bawah Rp 4,8 M tidak memungut PPN sama sekali, jadi masalahnya belum lahir; (b) **arahnya tidak simetris** — menambah `products.tax_exempt` nullable kelak bersifat *menambah*, `null` berarti "pakai tarif tenant", dan tidak ada baris lama yang berubah arti karena tarifnya sudah dibekukan per transaksi. Sebaliknya, membangun per-produk lebih dulu meninggalkan ratusan klasifikasi isian pemilik yang harus diputuskan nasibnya satu per satu kalau kelak disederhanakan.
     Alasannya **bukan** bahwa per-produk lebih buruk, melainkan bahwa ia bisa ditunda tanpa denda sementara kebalikannya tidak.
  2. **`tax_enabled` mati → nyala: bebas, kapan pun, self-serve.** Tenant yang tembus Rp 4,8 M **wajib** mulai memungut PPN pada tahun buku berikutnya. Aplikasi yang menghalanginya berarti aplikasi yang menolak membiarkan penggunanya patuh hukum — dan jalan keluarnya lebih buruk dari penyakitnya: tenant membuat akun baru dan kehilangan seluruh riwayatnya.
  3. **`tax_enabled` nyala → mati: TERKUNCI**, sederajat dengan mode. Kalau bebas, tenant bisa membuat lubang di tengah riwayat pajaknya — persis pola yang penguncian ini hindari.
  4. **`tax_mode` (inclusive ↔ exclusive): TERKUNCI setelah transaksi berpajak pertama.** Bebas diubah selama belum ada satu pun. Ini satu-satunya dari tiga pengaturan yang mengubah *arti* angka uang, jadi satu-satunya yang layak dikunci. Yang dibelinya: laporan tanpa patahan, dan hilangnya jebakan di `TransactionEditService.php:225-264` yang menghitung ulang total dari barisnya saat transaksi lama diedit.
     **Jalan buka:** lewat konsol platform dengan jejak audit — **bukan** self-serve di `SystemBehaviorController`, dan bukan pula "hubungi kami" tanpa mekanisme. Setelah transaksi berpajak pertama, kendalinya berhenti muncul di setelan pemilik tenant.
     **Catatan kejujuran:** satu dari tiga alasan asli penguncian **gugur** begitu butir 7 diputuskan — perlindungan dari penyalahgunaan bracket tidak lagi dibeli oleh penguncian. Dua alasan sisanya berdiri sendiri, dan itu yang menopang keputusan ini.
  5. **`tax_rate` TIDAK dikunci.** Tarif harus bisa berubah: PPN pernah naik 10% → 11%, dan tarif PBJT berbeda tiap Perda serta bisa berubah. Tarif yang berubah tidak membuat angka lama tidak sebanding — hanya pengali yang berbeda. Berlaku **maju**, dan tetap **dibekukan per transaksi**.
  6. **`tax_label` ditanyakan, bukan ditebak.** Label (`PPN` vs `PB1`/`PBJT`) disimpan sebagai kolom karena struk harus mencetak kata yang benar; kafe yang mencetak "PPN 10%" untuk pungutan yang sebenarnya PBJT sedang salah menyebut dasar hukum di dokumen yang dipegang pelanggan. **Jangan menurunkannya dari `business_type`** — itu mengulangi persis kesalahan yang baru diperbaiki `[BL-079]` (commit `654572d`, *"stop guessing the business type for people who did not answer"*), dengan taruhan lebih tinggi karena hasil tebakannya dicetak. Boleh **mengusulkan** nilai bawaan dari `business_type`, tapi tetap harus dijawab.
  7. **Bracket Harga Adaptif memakai `total_amount`, bukan `subtotal_amount`.**
     Yang dibeli: nol perubahan pada 12 tempat penjumlahan yang sudah ada, dan nol risiko migrasi pada angka yang menentukan tagihan.
     **Efek samping yang menguntungkan:** celah penyalahgunaan bracket **lenyap sendiri**. Mode inclusive tidak mengubah `total_amount` sama sekali (pelanggan tetap bayar 10.000), sehingga tenant tidak bisa menyalakan pajak inclusive untuk turun bracket tanpa menurunkan harga. Itu sebabnya butir 4 kehilangan satu alasannya.
     **Ongkos yang diterima sadar:** tenant mode **exclusive** ditagih atas ~11% uang yang bukan miliknya. Kafe beromzet Rp 100 juta/bulan yang menyalakan exclusive akan tercatat Rp 111 juta padahal pendapatannya tidak berubah. Ini **tidak** dianggap cacat — ini harga yang dipilih demi kesederhanaan, dan tenant yang menemukannya berhak mengeluh.
  8. **Pembulatan: `subtotal + pajak = total` wajib tepat dalam rupiah tersimpan.** Caranya: bulatkan **tepat satu** angka — selalu **pajak** — lalu turunkan angka ketiga dengan pengurangan. Jangkarnya berbeda per mode, dan itu memang benar:
     - **Exclusive:** jangkar `subtotal_amount` (harga katalog itu nyata) → bulatkan pajak → `total = subtotal + pajak`
     - **Inclusive:** jangkar `total_amount` (uang yang berpindah tangan itu nyata) → bulatkan pajak → `subtotal = total − pajak`

     Membulatkan per item ditolak: pelanggan memverifikasi struk dari angka yang **tercetak**, jadi tarif dikali subtotal tercetak harus sama persis dengan pajak tercetak. Tidak membulatkan sama sekali ditolak di kasir, bukan di kode — total berakhir di Rp 14.985 dan laci tidak punya uangnya.
- **Usulan Perbaikan** — (a)-(d) dan (f) **mendarat 2026-08-28**, (e) menyusul **2026-08-29**; seluruhnya selesai:
  **(a)** `transactions` mendapat `subtotal_amount` dan `tax_amount`, dengan `total_amount` **tetap** berarti "yang dibayar pelanggan" — kolom yang sudah dibaca 12 tempat jangan berubah maknanya. Migrasi mengisi transaksi lama dengan `subtotal_amount = total_amount`, `tax_amount = 0`, yang memang benar untuk masa sebelum pajak ada.
  **(b)** `tenants` mendapat `tax_enabled` (default **false**), `tax_mode`, `tax_rate`, `tax_label` — sejajar dengan `*_enabled` lain — dan keempatnya **dibekukan per transaksi**, sama seperti `invoices.pricing_context` membekukan konteks tagihan. Struk lama harus tetap bisa dicetak ulang dengan angka yang sama walau tarifnya sudah berubah.
  **(c)** Struk menampilkan Subtotal → *label* (tarif%) → TOTAL untuk mode exclusive, dan Subtotal → TOTAL dengan catatan "termasuk *label* Rp X" untuk mode inclusive.
  **(d)** Pajak dihitung **setelah** diskon. Diskon per item sudah ada (`transaction_items.discount_amount`, `[BL-018]`); urutannya ditulis, bukan diasumsikan.
  **(e)** Laporan harian/bulanan (`[BL-063]`) menampilkan omzet dan pajak terpungut sebagai **dua angka**. Pemilik toko yang memungut pajak butuh angka kedua itu untuk menyetorkannya. — **selesai 2026-08-29**; ditampilkan sebagai tiga angka (omzet sebelum pajak, pajak terpungut, dibayar pelanggan) karena angka ketiga yang membuat dua yang pertama bisa diperiksa.
  **(f)** Struk POS **bukan** faktur pajak. Tenant PKP tetap wajib menerbitkan e-Faktur terpisah; fitur ini tidak boleh dijual seolah menggantikannya.
- **Jebakan yang sudah diketahui — jangan ditemukan ulang dengan cara mahal:**
  - **`app/Jobs/ComputeTenantMonthlyRevenue.php:122` memakai `updateOrCreate` berkunci tenant+periode.** Kalau dasar bracket kelak dipindah ke `subtotal_amount` — dan itu **bisa** dibalik tanpa migrasi, karena kolomnya sudah ditulis demi struk — maka menjalankan ulang job untuk bulan lampau akan **menimpa** metrik historis dengan dasar baru. Kalau diganti, ganti **maju saja**.
  - **`app/Services/TransactionEditService.php:225-264` menghitung ulang total dari barisnya.** Wajib memakai tarif dan mode yang **dibekukan di transaksi itu**, bukan yang berlaku hari ini. Penguncian mode memperkecil jebakan ini tapi tidak menghapusnya — tarif tetap bisa berubah (butir 5).
  - **`app/Services/CashDrawerReconciliation.php:99` harus tetap `total_amount`.** Uang fisik di laci memang sejumlah itu, termasuk pajaknya. Ini satu-satunya penjumlahan yang tidak boleh ikut pindah kalau dasar apa pun kelak dipindah.
- **Yang sengaja dibiarkan terbuka:**
  - **Service charge ditunda** → dipindah jadi `[BL-097]` pada 2026-08-30. Aman ditunda justru karena tarif dibekukan per transaksi: kolom yang lahir belakangan dengan default 0 tidak merusak transaksi lama.
  - ~~**`app/Services/ProfitService.php:37` belum diputuskan.**~~ **Terjawab 2026-08-29:** margin dihitung dari `subtotal_amount`, dengan `revenue` tetap berarti yang dibayar pelanggan dan `net_revenue` ditambahkan di sebelahnya. Rincian per produk ikut dikoreksi untuk mode inclusive. Dijawab **berbeda** dari dasar penagihan (butir 7), persis seperti yang dibolehkan di sini.
  - **`ProfitService` masih memakai harga modal HARI INI**, bukan `transaction_items.cost_price_at_sale` yang sudah dibekukan per baris sejak `[BL-018]`. Terlihat saat dasar margin diputuskan 2026-08-29 dan sengaja **tidak** dijadikan entri sendiri (keputusan pemilik 2026-08-30): rumahnya adalah docblock `ProfitService`, yang sudah mencatatnya sebagai keterbatasan yang diketahui.

### [BL-095] Cold Start Offline Membuka POS yang Tidak Pernah Bisa Menjual — Katalog Tertahan Selamanya di Kerangka
- **Ditemukan:** 2026-08-26 (spike Tahap 1 `[BL-016]`)
- **Sumber:** Gerbang keputusan Tahap 1 `[BL-016]` D2. **Diuji, bukan disimpulkan:** Chrome sungguhan, service worker terpasang, lalu server benar-benar dimatikan sampai `curl` menjawab *connection refused*
- **Status:** **Selesai 2026-08-26** — lihat `[HOTFIX] Kasir Offline Berhenti Menunggu Katalog yang Tidak Akan Pernah Datang (BL-095, BL-096)`
- **Status semula:** Open
- **Yang mendarat, dan satu butir yang sengaja TIDAK dikerjakan:** butir (a), (c), dan (d) dikerjakan seperti tertulis. Butir **(b) ditolak sadar** — menggantungkan `catalogReady` pada ketersediaan snapshot akan membuat katalog basi terlihat oleh kasir yang **sedang online** selagi prop tertundanya masih di jalan, yaitu menukar satu cacat dengan cacat yang lebih berbahaya: harga basi. Yang benar ternyata membiarkan `catalogReady` apa adanya dan **membuat `isOnline` jujur**
- **Kebutuhan yang baru terlihat saat dikerjakan:** penandaan offline yang dilakukan sendiri **tidak punya jalan pulang**. `markOnline()` sudah diekspor sejak lama tapi tak pernah dipanggil dari mana pun, dan peristiwa `online` milik peramban tidak menyala karena antarmuka jaringan memang tidak pernah putus — yang tadi mati cuma servernya. Karena itu ikut mendarat pendengar `success` dan penyelidik berkala 60 detik (`router.reload({ only: ['products', 'upsell'] })`). Tanpa keduanya, perbaikan ini justru akan mengunci kasir di mode tunai sampai ia kebetulan berpindah halaman
- **Prioritas:** High — ia mematikan satu-satunya janji yang membuat POS berguna saat internet putus, dan ia berlaku pada **PWA hari ini**, bukan hanya pada rencana Capacitor
- **Area Terdampak:**
  - `resources/js/Pages/Cashier/POS.vue:78-80` — `catalogReady` memilih sumber katalog dari `isOnline`
  - `resources/js/Pages/Cashier/POS.vue:103` — `loadSnapshot()` dipanggil saat mount **hanya jika** sudah offline
  - `resources/js/Pages/Cashier/POS.vue:129-136` — `watch(isOnline)` yang seharusnya menarik snapshot tidak pernah menyala
  - `resources/js/composables/useOnlineStatus.js:16` — `isOnline` bersandar pada `navigator.onLine`
  - `app/Http/Controllers/Cashier/POSController.php:83` — `products` dikirim sebagai `Inertia::defer()`
- **Deskripsi:** Saat aplikasi dibuka dari keadaan mati sementara server tidak terjangkau, service worker **berhasil** menyajikan dokumen POS dari cache — cangkang, kategori, keranjang, tombol BAYAR, semuanya tampil. Yang tidak pernah datang adalah produknya: rak menampilkan "Memuat katalog produk…" selamanya, jadi kasir memandang POS yang kelihatan hidup tapi tidak bisa dipakai menjual satu gelas pun.

  Rantai sebabnya melibatkan tiga keputusan yang masing-masing benar sendiri-sendiri:
  1. `products` adalah **prop tertunda** (`Inertia::defer()`, konsekuensi `[BL-037]`), jadi ia **tidak ikut** di dalam dokumen yang disimpan service worker. Dokumen cache hanya membawa `deferredProps: { default: ["products","upsell"] }`.
  2. Permintaan susulan untuk prop tertunda itu gagal — server memang tidak ada.
  3. **Tidak ada yang memberi tahu aplikasi bahwa ia offline.** `navigator.onLine` tetap `true` (antarmuka jaringan hidup; yang mati cuma servernya), jadi `isOnline` tetap `true`, `catalogReady` jatuh ke cabang `Array.isArray(props.products)` yang bernilai `false`, `watch(isOnline)` tak pernah menyala, dan `loadSnapshot()` tak pernah dipanggil.

  **Snapshot katalognya sendiri baik-baik saja.** IndexedDB berisi satu record lengkap dengan keempat produk. Ia hanya tidak pernah dibaca. Dibuktikan dengan memaksa satu peristiwa `offline` ke `window`: seketika itu juga spanduk "Mode Offline · Katalog per 17.28 — stok indikatif, hanya tunai." muncul dan seluruh produk terpasang. Jadi seluruh mesin offline sudah benar; yang putus hanya pemicunya.
- **Ironi yang layak dicatat:** `useOnlineStatus.js` sudah menuliskan sebabnya sebagai peringatan — *"it says nothing about whether our server is reachable… Treat it as a hint for UI, never as proof a request will succeed"* — lengkap dengan `markOffline()` sebagai jalan keluarnya. Jalur prop tertunda hanyalah satu-satunya pengirim permintaan yang tidak pernah memanggilnya.
- **Usulan Perbaikan:**
  **(a)** Panggil `markOffline()` saat permintaan prop tertunda gagal. Ini perbaikan terkecil yang menutup lubangnya, dan ia memakai mekanisme yang sudah ada.
  **(b)** Jangan gantungkan `catalogReady` pada `isOnline` sendirian. Snapshot yang ada di IndexedDB selalu sah dipakai begitu prop-nya tidak datang — apa pun kata `navigator.onLine`.
  **(c)** Pertimbangkan memanggil `loadSnapshot()` tanpa syarat saat mount. Ongkosnya satu pembacaan IndexedDB; imbalannya katalog tidak pernah bergantung pada tebakan konektivitas.
  **(d)** Uji regresinya dengan server yang benar-benar mati, bukan dengan `navigator.onLine` yang dipalsukan — justru selisih antara keduanya yang melahirkan cacat ini.

### [BL-096] Cold Start Offline di `/` Berujung Halaman Buntu — Tidak Ada Jalan Menuju POS
- **Ditemukan:** 2026-08-26 (spike Tahap 1 `[BL-016]`)
- **Sumber:** Gerbang keputusan Tahap 1 `[BL-016]` D2, diuji bersama `[BL-095]`
- **Status:** **Selesai 2026-08-26** — lihat `[HOTFIX] Kasir Offline Berhenti Menunggu Katalog yang Tidak Akan Pernah Datang (BL-095, BL-096)`
- **Status semula:** Open
- **Yang mendarat:** butir (a). Butir **(c) tidak dikerjakan** — sesudah (a) ada, mengalihkan navigasi akar dari dalam `sw.js` hanya menghemat satu ketukan sambil menambah cabang pada berkas yang paling sulit diuji di proyek ini. Butir (b) bukan pekerjaan kode; ia catatan yang menunggu `[BL-016]` benar-benar dikerjakan
- **Satu hal yang nyaris terlewat, dan ia menentukan apakah perbaikan ini sampai ke siapa pun:** `offline.html` terdaftar di `SHELL_ASSETS`, dan shell hanya diprecache ulang ketika `CACHE_VERSION` berubah. Tanpa menaikkannya ke `v4`, setiap pemasangan yang sudah ada akan terus menyajikan halaman buntu yang lama — selamanya, tanpa satu pun pesan yang terlihat
- **Prioritas:** Medium untuk PWA; **naik jadi prasyarat** begitu `[BL-016]` dikerjakan, karena `server.url` Capacitor menentukan alamat mana yang dibuka saat aplikasi dinyalakan
- **Area Terdampak:**
  - `public/sw.js:52` — `OFFLINE_CAPABLE_ROUTES = ['/cashier/pos']`, hanya satu rute
  - `public/sw.js:141-147` — cadangan terakhir selalu `/offline.html`
  - `public/offline.html:47` — satu-satunya tombolnya `location.reload()`
- **Deskripsi:** Dibuka offline di `/cashier/pos`, POS tersaji dari cache. Dibuka offline di `/` — yang merupakan **alamat bawaan** kalau seseorang menaruh URL server apa adanya — yang muncul adalah `offline.html`: "Anda sedang offline", dengan satu tombol "Coba lagi" yang hanya memuat ulang halaman yang sama. Tidak ada tautan menuju POS, padahal POS-nya ada di cache dan siap dibuka. Kasir yang tidak hafal alamatnya berhenti di situ.
- **Kenapa ini penting justru untuk `[BL-016]`:** rencana D1.1 memilih cangkang yang **menunjuk `server.url`, bukan membundel aset**. Kalau `server.url` diisi akar situs, setiap penyalaan aplikasi dalam keadaan offline mendarat di halaman buntu ini — dan itu persis kegagalan yang gerbang keputusan Tahap 1 diminta mengawasi.
- **Usulan Perbaikan:**
  **(a)** Beri `offline.html` satu tautan ke `/cashier/pos`. Ini perbaikan termurah dan menolong pengguna PWA hari ini juga.
  **(b)** Saat `[BL-016]` dikerjakan, isi `server.url` Capacitor sampai ke `/cashier/pos`, jangan berhenti di akar.
  **(c)** Pertimbangkan agar service worker mengalihkan navigasi akar ke salinan POS yang ada di cache ketika jaringan gagal — sedikit lebih rumit dari (a), tapi ia menutup jalur mana pun yang dipakai orang untuk masuk.

### [BL-091] Seluruh Aplikasi Vue (1,1 MB, 56 Halaman) Dikirim ke Setiap Pengunjung Halaman Publik, lalu Gagal Mount
- **Ditemukan:** 2026-08-20 (saat memverifikasi `[BL-089]` di peramban — dua error konsol muncul di landing padahal perubahannya seluruhnya Blade)
- **Sumber:** Pengamatan langsung di konsol peramban, lalu ditelusuri ke `app.js` dan manifes build
- **Status:** **Selesai 2026-08-24** — lihat `[HOTFIX] Halaman Depan Berhenti Mengunduh Seluruh Aplikasi Vue yang Tidak Dipakainya (BL-091)`
- **Status semula:** Open — butuh keputusan pemilik: memisahkan bundel atau membiarkannya sebagai ongkos yang disadari
- **Keputusan pemilik 2026-08-24, menjawab kedua pertanyaan di bawah:** (1) halaman publik **tidak** butuh JavaScript apa pun dari `app.js` — landing punya skripnya sendiri di dalam Blade dan Alpine dari CDN, jadi entri ini selesai dengan mencabut entry point, bukan mengoptimalkan bundel; (2) `app.css` **tetap**, karena gaya Tailwind-nya memang dipakai halaman publik. `welcome.blade.php` **dihapus** — tidak ada satu pun rute, controller, atau test yang merujuknya
- **Yang ternyata berbeda dari entri saat dikerjakan:** dari empat Blade yang terdaftar, hanya **dua** yang masih memuat `app.js` — `api-docs.blade.php` dan `docs/layout.blade.php` sudah bersih sendiri di `9fd2fd5`/`e93ba1a` tanpa pernah tercatat. Yang tersisa hanya `landing.blade.php` (satu argumen `@vite`) dan `welcome.blade.php` (dihapus). Butir **(b)** karenanya **dibatalkan** — tidak ada satu baris pun JS terbundel yang dibutuhkan halaman publik, jadi entry point `public.js` akan lahir kosong. Butir **(c)** dipisah jadi `[BL-094]` persis seperti yang diperintahkan entri ini sendiri
- **Yang ditemukan saat menulis penjaganya (butir d):** penjaga HTML-nya mula-mula **lulus padahal `app.js` sengaja dikembalikan** — karena `public/hot` ada, Vite merender URL dev server (`.../resources/js/app.js`) alih-alih berkas build ber-hash yang dicari regex-nya. Penjaga yang hanya tahu satu dari dua bentuk itu akan diam di lingkungan yang lain. Versi yang mendarat memeriksa keduanya, dan kedua penjaga sudah dibuktikan gagal lebih dulu terhadap kondisi yang dijaganya
- **Prioritas:** Medium — tidak ada angka yang salah dan tidak ada data yang bocor, tapi ia menyentuh **halaman pertama yang dilihat calon klien**, dan produk ini dijual ke UMKM yang sebagian besar membukanya lewat ponsel dan kuota
- **Area Terdampak:**
  - `resources/views/public/landing.blade.php:19` — `@vite(['resources/css/app.css', 'resources/js/app.js'])`
  - `resources/views/public/api-docs.blade.php`, `resources/views/public/docs/layout.blade.php`, `resources/views/welcome.blade.php` — ketiganya sama
  - `resources/js/app.js:5` — `createInertiaApp({ … })` dijalankan tanpa syarat begitu berkasnya dimuat
  - `resources/js/app.js:8` — `import.meta.glob('./Pages/**/*.vue', { eager: true })`
  - `public/build/assets/app-*.js` — **1.104,9 KB** dalam satu berkas
- **Deskripsi:**
  Keempat halaman Blade publik memuat `app.js`, dan **tak satu pun punya elemen `#app`**. `createInertiaApp` tetap berjalan, tidak menemukan tempat mount, lalu melempar `TypeError: Cannot read properties of null (reading 'component')` — dua kali per kunjungan. Halamannya sendiri tetap tampil karena ia Blade murni; yang gagal hanya lapisan yang memang tidak punya urusan di sana.

  Error konsolnya sebenarnya gejala yang paling ringan. Yang mahal adalah muatannya: `import.meta.glob` dipanggil dengan `eager: true`, sehingga **seluruh 56 halaman Vue** — dashboard owner, panel platform, kasir, langganan, laporan — dikompilasi menjadi satu bundel 1,1 MB. Bundel itu diunduh, diurai, dan dijalankan oleh setiap orang yang membuka halaman depan, termasuk yang belum punya akun dan tidak akan pernah melihat satu pun halaman di dalamnya.

  Ironi yang membuatnya layak dicatat sekarang: `[BL-032]` dan `[BL-077]` menghabiskan pekerjaan nyata untuk menurunkan gambar landing dari 531 KB jadi 294 KB. Satu berkas JavaScript yang tidak dipakai halaman itu sama sekali berukuran **hampir empat kali lipat** seluruh penghematan tersebut.
- **Kenapa ini belum pernah ketahuan:** halamannya tidak rusak. Tidak ada yang hilang, tidak ada tata letak yang bergeser, dan errornya hanya terlihat bila konsol dibuka. Satu-satunya yang mengeluh adalah pengunjung berkuota tipis, dan mereka tidak melapor — mereka pergi.
- **Yang perlu diputuskan:**
  1. **Apakah halaman publik butuh JavaScript dari `app.js` sama sekali?** Landing punya skrip sendiri di dalam Blade-nya (peragaan POS, tab demo, FAQ, `IntersectionObserver`) dan tidak memanggil apa pun dari bundel Inertia. Bila jawabannya tidak, entri ini selesai dengan memisahkan entry point — bukan dengan mengoptimalkan bundel.
  2. **`app.css` ikut atau tidak?** Berbeda dengan JS, gaya Tailwind-nya memang dipakai halaman publik. Memisahkan JS tanpa menyeret CSS adalah bagian yang harus disengaja, bukan diasumsikan.
- **Usulan Perbaikan:**
  **(a)** **Cabut `resources/js/app.js` dari keempat Blade publik**, pertahankan `app.css`. Ini menutup error konsol dan seluruh 1,1 MB sekaligus, dan tidak menyentuh satu baris pun kode aplikasi. Kerjakan ini lebih dulu dan sendirian — sisanya perbaikan, yang ini penghapusan.
  **(b)** Bila suatu saat halaman publik memang butuh sedikit JS terbundel, beri ia **entry point sendiri** di `vite.config.js` (mis. `resources/js/public.js`), jangan menumpang entry aplikasi.
  **(c)** **`eager: true` layak ditinjau terpisah**, dan bukan bagian dari entri ini. Menggantinya dengan glob malas memecah bundel per halaman untuk pengguna yang sudah masuk juga — keuntungan nyata, tapi ia mengubah cara setiap halaman dimuat dan pantas diuji sendiri. Jangan digabung dengan (a): yang satu penghapusan tanpa risiko, yang lain perubahan perilaku pemuatan.
  **(d)** Tambahkan penjaga sesudahnya — sebuah test yang memastikan HTML landing tidak memuat entry aplikasi. Tanpa itu, satu `@vite` yang disalin dari layout lain akan mengembalikannya tanpa ada yang menagih, persis seperti yang sudah terjadi pada `[BL-083]` dan `[BL-089]`.
- **Catatan:** `welcome.blade.php` ikut terdaftar di atas, tapi periksa dulu apakah ia masih dirujuk rute mana pun. Bila tidak, ia berkas bawaan Laravel yang tertinggal dan lebih tepat dihapus daripada diperbaiki.

---

### [BL-077] Kompres/Resize/WEBP Otomatis Baru Ada di Foto Produk — Tiga Jalur Gambar Lain Melewatinya
- **Ditemukan:** 2026-08-14
- **Sumber:** Pertanyaan pemilik — "apakah ada auto compress resize dan convert ke webp untuk gambar" — lalu ditelusuri ke seluruh jalur gambar di basis kode
- **Status:** **Selesai 2026-08-24** — lihat `[REFACTOR] Dua Aset Yatim Dibuang, dan Celah yang Selama Ini Ditambal Manusia Dijaga Test (BL-077)`
- **Status semula:** Open — butir (a) dan (b) selesai 2026-08-19; yang tersisa hanya butir (c): pipeline aset statis di waktu build
- **Prioritas:** Low — tidak ada angka yang salah dan tidak ada data yang bocor karenanya; yang terkena hanya biaya jaringan dan disk. Turun tetap di Low sejak 2026-08-19: dua jalur gambar yang benar-benar dipakai pengguna sudah tertutup, dan yang tersisa hanya disiplin memasukkan aset baru
- **Area Terdampak:**
  - `app/Services/ImageService.php:57-64` — **sudah ada dan sudah benar**: `cover(800,800)` + `cover(200,200)`, `toWebp(quality: 80)`, dua rendition, disk privat
  - `app/Http/Controllers/Owner/ProductController.php:52,97` — satu-satunya pemanggil `ImageService::upload()`
  - `app/Http/Controllers/Billing/UpgradeController.php:113,123` — bukti transfer langganan: `->store('proofs','local')` **apa adanya**, tanpa resize, tanpa konversi, sampai 4 MB per berkas
  - `app/Http/Requests/StoreProductRequest.php:22`, `UpdateProductRequest.php:22` — batas unggah 5 MB; seluruh 5 MB itu tetap menyeberangi jaringan sebelum dikecilkan di server
  - `vite.config.js:7-14` — tidak ada plugin gambar; aset statis dipakai apa adanya
  - `public/Stock-Management.png` (160 KB), `public/Dashboard-owner.png` (140 KB), `public/sapi-logo.png` (92 KB), `public/Product-List.png` (96 KB) — tangkapan layar landing masih PNG
  - `resources/js/` — **tidak ada** `canvas`/`toBlob`/`createImageBitmap` di mana pun; kompresi sisi peramban belum pernah ditulis
- **Deskripsi:**
  Jawaban singkatnya: **ada, tapi hanya untuk foto produk.** `ImageService` melakukan ketiganya sekaligus — potong persegi 800px, turunkan thumbnail 200px, konversi WEBP kualitas 80 — dan itu berjalan otomatis pada setiap simpan/ubah produk. Yang perlu diluruskan adalah anggapan bahwa itu berlaku menyeluruh; ia tidak. Tiga jalur gambar lain tidak menyentuhnya sama sekali:
  1. **Bukti transfer langganan.** `UpgradeController` menyimpan berkas mentah. Validasinya menerima `pdf` di samping `jpg|jpeg|png`, jadi ini **bukan** kasus "tinggal panggil `ImageService`" — sebuah PDF tidak bisa dilewatkan ke encoder WEBP, dan mengubahnya jadi gambar berarti kehilangan berkas aslinya. Jalur ini butuh percabangan berdasarkan tipe berkas, bukan penambalan satu baris.
  2. **Sisi peramban, semua unggahan.** Foto 12 MP dari kamera ponsel dikirim utuh lebih dulu, baru dikecilkan setelah sampai. Untuk owner yang mengunggah katalog sambil online ini masih dapat diterima. Untuk `[BL-075]` butir (e) ia **tidak** dapat diterima, dan di sana alasannya sudah ditulis panjang: gambar masuk outbox IndexedDB sebelum ada server yang bisa mengecilkannya.
  3. **Aset statis landing.** Tidak ada pipeline sama sekali. Bukti bahwa ini terasa: perbaikan avatar testimoni 1,8 MB → 5 KB pada `[BL-032]` dikerjakan **manual sekali jalan**; tidak ada yang mencegah berkas berat berikutnya masuk dengan cara yang sama.
- **Usulan Perbaikan:**
  ~~**(a) Kerjakan sisi peramban lebih dulu, bukan sisi server.** Kompresi sebelum unggah menguntungkan ketiga jalur sekaligus dan merupakan prasyarat `[BL-075]`, sementara dua sisanya hanya merapikan yang sudah bekerja. Bentuknya satu composable `useImageCompressor` di atas `createImageBitmap` + `canvas.toBlob('image/webp')`, dipakai `ProductForm` sekarang dan `PaymentModal` nanti.~~ — **selesai 2026-08-19.** Composable-nya ada di `resources/js/composables/useImageCompressor.js` dan dipakai `ImageUpload.vue`; `PaymentModal` menyusul bersama `[BL-075]`. Satu berkas yang belum ikut: pemilih bukti transfer di `Billing/Show.vue`, karena berkas itu sedang punya perubahan `[BL-061]` yang belum di-commit.
  ~~**(b) Jangan sentuh `ImageService` untuk mendukung bukti transfer.** Kelas itu tegas: produk, persegi, dua rendition, disk privat. Bukti bayar bukan persegi dan bisa berupa PDF. Percabangannya di pemanggil — bila `mime` gambar, kompresi; bila PDF, simpan apa adanya.~~ — **selesai 2026-08-19**, tapi percabangannya berakhir di `app/Services/ProofFileService.php`, **bukan** di pemanggil. Alasannya: `[BL-075]` akan jadi pemanggil kedua, dan aturan "PDF disalin apa adanya" yang ditulis dua kali adalah aturan yang suatu saat akan berbeda di satu tempat. Kelas itu memakai `scaleDown()` (rasio dijaga, tidak memotong), bukan `cover()`.
  **(c) Aset statis diselesaikan di waktu build, bukan dengan disiplin manusia.** Satu plugin Vite pengonversi gambar menutup celahnya permanen; menambahkannya berarti mengubah dependensi, jadi butuh persetujuan lebih dulu.
  **(d) Yang sengaja TIDAK diusulkan:** AVIF, `srcset` multi-lebar, dan rendition ketiga. WEBP 800/200 sudah memadai untuk kisi POS dan kartu produk; menambah format berarti menambah cabang penyajian di `MediaController` demi keuntungan yang belum ada yang mengeluhkan ketiadaannya.
- **Catatan:** `QUALITY`, `MAIN_SIZE`, dan `THUMB_SIZE` adalah konstanta kelas (`ImageService.php:33-37`), bukan konfigurasi — sama seperti `DISK` pada `[BL-076]`(a). Bila suatu saat ketiganya perlu berbeda per lingkungan, kerjakan bersama entri itu, jangan sendiri-sendiri.
- **SELESAI 2026-08-24 — seluruh butirnya tertutup.** Butir (a) dan (b) mendarat 2026-08-19; butir (c) ditutup hari ini, tapi **tidak dengan bentuk yang diusulkan**. Usulan plugin Vite DIBATALKAN: Vite hanya memproses aset yang di-`import` lewat bundel, sedangkan berkas di `public/` disalin apa adanya dan tidak pernah disentuhnya — plugin itu akan menambah dependensi yang tidak menyentuh satu pun berkas yang jadi alasan entri ini ditulis. Penggantinya `tests/Feature/Public/StaticAssetBudgetTest.php` (3 penjaga, tanpa dependensi baru). Dua berkas yatim dihapus, dan `sapi-logo.png` ternyata masih terdaftar di `SHELL_ASSETS` service worker — nyaris dihapus sebagai yatim, yang akan menggagalkan seluruh install SW. Entri penutup: `[REFACTOR] Dua Aset Yatim Dibuang, dan Celah yang Selama Ini Ditambal Manusia Dijaga Test (BL-077)`.

---

### [BL-072] Enam Commit Berturut-turut Tidak Bisa Boot — `git bisect` dan `git revert` Menyesatkan di Rentang Itu
- **Ditemukan:** 2026-08-08
- **Sumber:** Percobaan menulis ulang riwayat jadi commit atomik; ditemukan karena commit hasil pecahannya gagal menjalankan tes dengan sebab yang bukan berasal dari pecahannya
- **Status:** **Selesai 2026-08-24** — lihat `[ADDITION] Riwayat yang Tidak Bisa Boot Dibiarkan, Peringatannya yang Dipindah ke Tempat Terbaca (BL-072)`
- **Status semula:** Open — cacat riwayat, bukan cacat kode. `HEAD` sehat: 879 tes lulus (diverifikasi ulang 2026-08-14; 786 saat entri ini ditulis 2026-08-08; 1.210 pada 2026-08-24)
- **Prioritas:** Low selama tak ada yang menyusuri riwayat; **High begitu ada yang perlu `bisect` atau `revert` di rentang ini**
- **Area Terdampak:**
  - Commit `2ffd393` sampai `329f592` (enam commit berurutan). Sembuh di `342082c`.
  - `app/Http/Middleware/HandleInertiaRequests.php` — memanggil `App\Models\PaymentAttempt`
  - `app/Services/SubscriptionService.php` — meng-*import* `App\Services\Pricing\AdaptiveEligibility`
  - `app/Http/Controllers/Billing/SubscriptionController.php` — men-*type-hint* `App\Services\Billing\Gateways\PaymentGatewayManager`
- **Deskripsi:**
  Enam commit berturut-turut memanggil kelas yang berkasnya belum ada. Ketiganya baru lahir bersamaan di `342082c`, yang pesannya sendiri mengakuinya: *"ship the payment gateway and eligibility service HEAD already imports"*.

  | Commit | `PaymentAttempt` | `AdaptiveEligibility` | `PaymentGatewayManager` |
  |---|---|---|---|
  | `b3a0686` | ok | ok | ok |
  | `2ffd393` | **menggantung** | ok | ok |
  | `c0add23` | **menggantung** | ok | ok |
  | `18553be` | **menggantung** | **menggantung** | **menggantung** |
  | `bc24576` | **menggantung** | **menggantung** | **menggantung** |
  | `f963d94` | **menggantung** | **menggantung** | **menggantung** |
  | `329f592` | **menggantung** | **menggantung** | **menggantung** |
  | `342082c` | ok | ok | ok |

  Karena `HandleInertiaRequests` dipakai SETIAP halaman, akibatnya bukan sekadar satu fitur mati: di seluruh rentang itu tidak ada satu pun halaman Inertia yang bisa dirender. Diverifikasi, bukan disimpulkan — checkout ke `c0add23` lalu menjalankan `tests/Feature/Subscription` menghasilkan 25 kegagalan, semuanya `Error: Class "App\Models\PaymentAttempt" not found`.
- **Kenapa ini berbahaya justru karena tidak terlihat:** `HEAD` hijau, jadi tidak ada yang menagih. Yang menabraknya adalah orang yang datang belakangan dengan pertanyaan wajar — "commit mana yang memecahkan ini?" — lalu `git bisect` menjawab dengan menunjuk commit yang salah, karena setiap commit di rentang itu gagal untuk sebab yang sama sekali berbeda dari yang sedang dicari. `git revert 342082c` juga akan **mematikan `HEAD`**, bukan sekadar mencabut payment gateway: ia membawa pergi tiga kelas yang dipanggil commit-commit di bawahnya.
- **Sebabnya, supaya tidak berulang:** commit dibuat per "sesi kerja", bukan per perubahan yang berdiri sendiri — pemakai sebuah kelas ikut ter-*commit* lebih dulu daripada kelasnya. Dua commit teratas juga mencampur beberapa concern: `342082c` menggabungkan payment gateway `[BL-059]` dengan `AdaptiveEligibility` dan `PricingService` (24 berkas), dan `a6b45d1` menggabungkan seat bulanan `[BL-053]`, perbaikan `[BL-058]`, serta dokumentasi.
- **Usulan Perbaikan:**
  **(a)** **Jangan `bisect` melintasi rentang ini.** Pakai `git bisect skip` untuk `2ffd393`..`329f592`, atau batasi rentangnya ke `342082c..HEAD`.
  **(b)** **Jangan `revert 342082c`.** Bila payment gateway memang perlu dicabut, cabut lewat commit baru yang membuang pemakainya lebih dulu, bukan dengan membalik commit yang memuat kelasnya.
  **(c)** Merapikannya berarti menulis ulang **8 commit** dengan basis `b3a0686` — sudah dicoba dan dihentikan 2026-08-08 atas keputusan pemilik. Alasannya: sebagian besar isinya pekerjaan sesi lain, dan menyusun keadaan antaranya menuntut menafsirkan maksud tiap hunk milik orang lain. Riwayat yang ditulis ulang berdasarkan tafsiran bukan riwayat yang lebih bisa dipercaya. Tetap layak dikerjakan bila suatu saat rentang ini benar-benar perlu ditelusuri.
  **(d)** Aturan ke depan, dan inilah yang sebenarnya menutup entri ini: **satu commit = satu perubahan yang bisa boot sendiri.** Kelas dan pemakainya masuk di commit yang sama, atau kelasnya lebih dulu. Uji cepatnya satu perintah — `git stash && php artisan route:list` sebelum `commit`.
- **SELESAI 2026-08-24 — ditutup lewat butir (d), bukan (c).** Butir (c) (menulis ulang 8 commit) **tetap ditolak** sesuai keputusan pemilik 2026-08-08. Peringatan butir (a) dan (b) pindah ke `CLAUDE.md` supaya terbaca oleh orang yang belum tahu harus mencarinya, dan aturan butir (d) kini punya pemeriksa: `composer run check:boot` → `tests/Feature/CommitBootabilityTest.php`. **Satu koreksi terhadap entri ini sendiri:** uji cepat yang disarankannya, `php artisan route:list`, DICOBA dan tidak menangkap cacatnya — sebuah `use` hanyalah alias di waktu kompilasi dan tidak pernah memicu autoloader. Penjaganya karena itu menyisir impor, bukan menjalankan aplikasi. Riwayatnya sendiri tetap rusak selamanya; yang berubah hanya bahwa orang berikutnya diperingatkan sebelum tersesat. Entri penutup: `[ADDITION] Riwayat yang Tidak Bisa Boot Dibiarkan, Peringatannya yang Dipindah ke Tempat Terbaca (BL-072)`.

---

### [BL-082] Seluruh Aplikasi Berjalan di UTC Padahal Tokonya Tidak — "Hari Ini" Bergeser 7–8 Jam dari Hari Toko
- **Ditemukan:** 2026-08-20 (saat mengambil ulang tangkapan layar `[BL-032]` butir (3); terlihat karena topbar menulis "Jumat, 21 Agustus" sementara pemilih tanggal Laporan Harian di layar yang sama default ke "Kamis, 20 Agustus")
- **Sumber:** Pengamatan langsung di aplikasi berjalan, lalu ditelusuri ke konfigurasinya
- **Status:** **Selesai 2026-08-22** — lihat `[DECISION] Seluruh Aplikasi Berjalan di Jam Toko (WITA), dan Satu Tempat Saja yang Menjawab "Hari Ini" (BL-082)`
- **Status semula:** Open — butuh keputusan pemilik lebih dulu: satu zona untuk seluruh aplikasi, atau satu zona per tenant
- **Keputusan pemilik 2026-08-22, menjawab ketiga pertanyaan di bawah:** (1) **satu zona untuk seluruh aplikasi** — `Asia/Makassar` (WITA), dibaca dari `APP_TIMEZONE`; (2) **angka riwayat tidak dikelompokkan ulang**: kolom datetime menyimpan waktu polos, jadi baris lama tinggal dibaca sebagai WITA dan tidak satu pun angka laporan lama berubah; (3) karena itu `tenant_monthly_metrics` **tidak** perlu dihitung ulang dan `PruneTenantMetrics` tidak perlu dijalankan bersamanya
- **Prioritas:** Medium sekarang (belum ada tenant sungguhan, dan data demo disemai per tanggal server sehingga selalu konsisten dengan dirinya sendiri); **High pada hari pertama ada toko yang buka sebelum pukul 08.00 waktu setempat**
- **Area Terdampak:**
  - `config/app.php:68` — `'timezone' => 'UTC'`, **ditulis mati**, bukan dari `env()`. Tidak ada `APP_TIMEZONE` di `.env`
  - `app/Models/Transaction.php:161` — `effectiveDateSql()`: `COALESCE(occurred_at, created_at)`, keduanya tersimpan UTC
  - `app/Http/Controllers/Owner/ReportController.php` — `daily()` dan `monthly()` mengelompokkan per tanggal dari SQL di atas
  - `app/Http/Controllers/Owner/DashboardController.php` — kartu "Pendapatan Hari Ini" / "Transaksi Hari Ini"
  - `app/Jobs/ComputeTenantMonthlyRevenue.php` + `app/Services/Pricing/MonthlyMetricResolver.php` — periode `YYYY-MM` yang jadi dasar bracket Harga Adaptif
  - `database/seeders/DemoTransactionSeeder.php`, `database/seeders/CafeStudyCaseSeeder.php` — `Carbon::now()` juga UTC
  - `resources/js/` — sisi peramban memakai tanggal **lokal peramban**; di situlah selisihnya jadi terlihat
  - Tabel `tenants` — **tidak punya kolom zona waktu sama sekali**
- **Deskripsi:**
  Server berjalan di UTC dan tidak ada satu baris pun di `app/` yang mengonversi ke zona mana pun — pencarian `timezone`/`setTimezone`/`Asia/Jakarta` di seluruh `app/` mengembalikan nol hasil. Artinya "hari ini" yang dipakai laporan, dashboard, dan rekap bulanan adalah **hari UTC**, sementara tokonya hidup di WIB/WITA/WIT.

  Akibat yang paling mudah dihitung: batas hari UTC jatuh pukul **07.00 WIB / 08.00 WITA / 09.00 WIT**. Penjualan antara tengah malam dan jam-jam itu masuk ke **laporan hari sebelumnya**. Untuk kafe yang buka pukul 07.00, itu berarti transaksi jam pertama tiap hari tercatat di hari yang salah — dan Laporan Harian adalah angka yang dipakai pemilik menutup harinya.

  Gejalanya sudah terlihat tanpa perlu dicari: pada satu layar yang sama, topbar menulis "Jumat, 21 Agustus 2026" (tanggal lokal peramban) sedangkan pemilih tanggal Laporan Harian default ke "Kamis, 20 Agustus 2026" (tanggal server). Dua tanggal untuk satu saat yang sama, berselisih satu hari.
- **Yang TIDAK terkena, dan alasannya — supaya lingkupnya tidak ditaksir terlalu besar:**
  1. **Rekonsiliasi kas (`[BL-028]`)** memakai jendela sesi `opened_at`–`closed_at`, yaitu perbandingan antar-timestamp. Perbandingan timestamp tidak peduli zona.
  2. **Umur tagihan terbuka 24 jam (`[BL-031]`)** berbasis durasi, bukan batas hari. Juga tidak peduli zona.
  3. **Penjadwalan langganan** memakai `addMonthsNoOverflow` dari jangkar tanggal daftar — bergeser paling banyak beberapa jam, dan tidak melewati batas yang menentukan uang.

  Yang benar-benar terkena adalah segala sesuatu yang **mengelompokkan per hari atau per bulan**: Laporan Harian, Laporan Bulanan, kartu "hari ini" di dashboard, dan `tenant_monthly_metrics` yang jadi dasar bracket Harga Adaptif.
- **Yang perlu diputuskan sebelum ada kode:**
  1. **Satu zona untuk seluruh aplikasi, atau satu zona per tenant?** `APP_TIMEZONE=Asia/Jakarta` adalah satu baris dan menutup sebagian besar kasus — tapi Indonesia punya tiga zona, dan produk ini dijual ke seluruh Indonesia. Tenant di Makassar akan salah satu jam, tenant di Jayapura dua jam. Kolom `tenants.timezone` menjawabnya dengan benar tapi menyeret setiap query pengelompokan harian untuk mengonversi lebih dulu.
  2. **Nasib angka yang sudah tercatat.** Mengubah zona **mengelompokkan ulang riwayat yang sudah ada** — Laporan Harian kemarin bisa berubah angkanya sesudah perubahan ini mendarat. Untuk data demo itu tidak apa-apa; untuk tenant yang sudah menutup pembukuannya, itu perlu diberitahukan, bukan didiamkan.
  3. **`tenant_monthly_metrics` ikut bergeser**, dan itu menyentuh harga. Periode `YYYY-MM` yang dihitung ulang dengan batas bulan bergeser 7–8 jam bisa memindahkan tenant ke bracket lain. Bila perubahannya dilakukan, penghitung ulang dan `PruneTenantMetrics` harus dijalankan bersama, bukan dibiarkan bercampur.
- **Usulan Perbaikan:**
  **(a)** Apa pun pilihannya, jadikan `config/app.php` membaca `env('APP_TIMEZONE', …)` lebih dulu. Nilai yang ditulis mati membuat lingkungan produksi tidak bisa berbeda dari lokal tanpa mengubah kode.
  **(b)** **Satu tempat saja yang boleh menjawab "hari ini milik tenant ini"** — sebuah helper di sisi PHP, dipakai bersama oleh laporan, dashboard, dan penghitung metrik. Aturan ini sudah terbukti pada `Transaction::effectiveDateSql()` dan `Transaction::openBillCutoff()`; zona waktu punya bentuk masalah yang sama persis, dan dua definisi yang berselisih hanya akan terlihat pada angka laporan.
  **(c)** **Sisi peramban ikut, di commit yang sama.** Selisih yang terlihat hari ini lahir justru karena satu sisi sudah lokal dan sisi lain belum. Memperbaiki server saja akan menukar arah selisihnya, bukan menghapusnya.
  **(d)** **Jangan** menambal dengan mengurangi 7 jam di satu-dua query. Itu memperbaiki layar yang sedang dilihat dan meninggalkan sisanya berselisih dengan layar itu.

---

### [BL-087] Tidak Ada Cara Mencatat Uang Keluar atau Setoran di Tengah Sesi Kas
- **Ditemukan:** 2026-08-21
- **Sumber:** Catatan pemilik — "membuat button untuk meminta uang yang ada pada saat kas aktif sebelum tertutup"
- **Status:** **Selesai 2026-08-21** — lihat `[ADDITION] Uang Keluar Laci Punya Tempat Mencatatnya, dan Efeknya yang Ditahan — Bukan Pencatatannya (BL-087)`
- **Status semula:** Open — butuh keputusan pemilik soal bentuknya sebelum ada baris kode
- **Keputusan pemilik 2026-08-21, menjawab ketiga pertanyaan di bawah:** (a) **kasir** yang mencatat, selalu; (b) persetujuan **berambang** — di bawah Rp 50.000 langsung berlaku, di atasnya tercatat tapi belum menggerakkan `expected_amount` sampai pemilik menyetujuinya, dan **angkanya bisa diubah dari dashboard** (`tenants.cash_payout_approval_threshold`); (c) setoran **tidak menutup sesi** — menutup kas tetap tindakan tersendiri di halamannya sendiri (`[BL-086]`). Saran foto struk diterima tapi dipisah jadi `[BL-093]` karena retensinya belum diputuskan.
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Models/CashDrawer.php:14` — `$fillable` hanya mengenal modal awal, uang tutup, selisih, dan catatan; tidak ada tempat bagi uang yang keluar-masuk di tengah sesi
  - `app/Services/CashDrawerReconciliation.php:73` — rumusnya `opening + cash_in − change_out`, dan ketiganya diturunkan dari transaksi penjualan; uang yang diambil pemilik dari laci tidak punya jalan masuk ke rumus ini
  - `resources/js/Pages/Cashier/CashDrawer.vue` — tidak ada tombol apa pun selain "Lanjut ke POS" dan "Tutup Kas"
- **Deskripsi:** Selama sesi berjalan, uang bisa keluar dari laci karena hal yang bukan kembalian — pemilik mengambil setoran, kasir membeli galon, uang kecil ditukar. Hari ini tidak ada tempat mencatatnya, sehingga uangnya menghilang sebagai **selisih kurang** di akhir shift dan kasir yang menanggung tuduhannya. Kebalikannya juga berlaku: menambah uang receh ke laci muncul sebagai selisih lebih.
- **Usulan Perbaikan:** tabel `cash_drawer_movements` (`cash_drawer_id`, `type` = `payout`/`deposit`, `amount`, `reason`, `user_id`, `created_at`), tombolnya di halaman sesi kas, dan `expected_amount` jadi `opening + cash_in − change_out − payout + deposit`.
- **Pertanyaan yang harus dijawab pemilik lebih dulu:** (a) siapa yang boleh mencatat pengeluaran — kasir sendiri, atau hanya owner? (b) apakah butuh persetujuan, atau cukup alasan tertulis? (c) apakah setoran ke pemilik **menutup** sesi, atau membiarkannya berjalan dengan modal berkurang? Jawaban (a) menentukan apakah ini fitur kas atau fitur pengawasan, dan itu perbedaan yang tidak bisa dibetulkan belakangan tanpa migrasi.

### [BL-088] Sesi Kas Tidak Punya Umur, Tidak Pernah Ditutup Sendiri, dan Rekapnya Terus Membesar
- **Ditemukan:** 2026-08-21
- **Sumber:** Catatan pemilik — "masa hidup kas cuma sehari dan auto close dan perlu perbaikan ketika lewat"
- **Status:** **Selesai 2026-08-21** — keempat butirnya. Lihat `[ADDITION] Sesi Kas Punya Umur, dan yang Lewat Ditutup Sistem Tanpa Mengaku Sudah Dihitung (BL-088)`
- **Status semula:** Open
- **Keputusan pemilik 2026-08-21:** batasnya **24 jam**, diambil dari kalimat pemilik sendiri di sumber entri ini ("masa hidup kas cuma sehari"). Ia tinggal di `CashDrawer::MAX_SESSION_HOURS`, mengikuti `Transaction::OPEN_BILL_LIFETIME_HOURS` — satu keputusan yang berlaku untuk seluruh aplikasi, bukan setelan per pemasangan.
- **Prioritas:** Medium — belum merusak angka mana pun, tapi ia yang membuat `[BL-086]` tidak cukup sendirian: kasir yang lupa menutup kas tidak akan pernah sampai ke layar tutup kas, sebagus apa pun layar itu dibuat
- **Area Terdampak:**
  - `routes/console.php` — **tidak ada** satu pun jadwal yang menyentuh `cash_drawers`; yang berjalan tiap jam adalah `open-bills:expire`, dan itu tagihan terbuka, bukan sesi kas
  - `app/Http/Controllers/Cashier/CashDrawerController.php:83` — satu-satunya penutup sesi adalah kasir menekan tombol
  - `app/Services/CashDrawerReconciliation.php:190` — jendela sesi memakai `closed_at ?? Carbon::now()`, jadi sesi yang tak pernah ditutup menyerap seluruh penjualan hari-hari berikutnya
  - `app/Http/Controllers/Cashier/CashDrawerController.php:63` — kasir hanya boleh punya satu sesi terbuka, sehingga sesi yang menggantung **memblokir** pembukaan kas keesokan harinya
- **Deskripsi:** Sesi kas hidup sampai ada yang menutupnya, tanpa batas. Kasir yang pulang tanpa menekan "Tutup Kas" meninggalkan sesi yang esok paginya menolak dibuka lagi ("Anda masih memiliki sesi kas yang terbuka"), sementara rekonsiliasinya diam-diam menghitung penjualan dua hari sebagai isi satu laci. Selisih yang muncul di akhir bukan lagi selisih kas, melainkan selisih akumulasi — dan tidak ada tanda apa pun di layar yang mengatakan sesi itu sudah lewat hari.
- **Dugaan Penyebab:** `cash_drawers` lahir dengan asumsi satu shift = satu hari kerja yang selalu ditutup manual (lihat `[SCHEMA] Penambahan Tabel cash_drawers`, 2026-03-06). Asumsi itu tidak pernah ditulis dan tidak punya penegak.
- **Usulan Perbaikan:**
  1. Command `cash-drawers:expire` terjadwal, menutup paksa sesi yang lewat batas umur. **Batasnya keputusan pemilik**, bukan angka yang boleh ditebak di sini — dan kalau dipilih 24 jam, jadwalnya harus **tiap jam** dengan alasan yang sama persis seperti `open-bills:expire`: sapuan harian membuat batas 24 jam berarti "antara satu dan dua hari".
  2. Sesi yang ditutup sistem **tidak boleh mengaku sudah dihitung**: `closing_amount` dan `difference` dibiarkan `null` dengan penanda tersendiri (mis. `closed_by_system`), bukan diisi `expected_amount` supaya selisihnya nol. Selisih nol yang dikarang adalah kebohongan yang persis sama dengan yang dilarang `[BL-086]`.
  3. Sesi yang lewat umur muncul di daftar Sesi Kas owner sebagai butuh ditinjau.
  4. Peringatan di layar kasir **sebelum** batasnya lewat, bukan sesudah — sesi yang terlanjur ditutup sistem tidak bisa lagi dihitung uangnya.
- **Catatan:** menutup paksa berarti uang fisiknya tidak pernah dihitung siapa pun. Itu kerugian yang diterima secara sadar sebagai ganti sesi yang menggantung selamanya, dan justru karena itu butir 2 tidak boleh dilonggarkan.

### [BL-092] Saran Jual Hanya Terlihat Separuh oleh Pemilik, dan Penerimaan yang Salah Tidak Bisa Ditarik
- **Ditemukan:** 2026-08-21 (dilaporkan pemilik saat memakai menu Saran Jual)
- **Status:** **Selesai 2026-08-21** — ketiga butirnya, plus satu lubang yang ditemukan saat mengerjakannya (transaksi `voided`)
- **Butir yang dilaporkan:**
  1. Pemilik ingin melihat aturan yang aktif **otomatis** (dari stok) berdampingan dengan aturan yang ia pasang **manual** — halaman Aturan Saran Jual hanya memperlihatkan yang kedua.
  2. Laporan Saran Jual harus menunjukkan performa **gabungan** dan masing-masing sumber, serta apa saja yang **dapat muncul** mengingat batas 3 saran per penjualan.
  3. Saran yang terlanjur ditekan "Diterima" tidak bisa ditarik. Kasir yang salah pencet, atau yang pelanggannya membatalkan, terpaksa menghapus barisnya lalu menambah ulang — dan datanya jadi dobel.
- **Temuan saat dikerjakan:** butir 3 punya dua bentuk, dan yang kedua hidup di server. `acceptedByKey` tidak pernah tahu barisnya dihapus, jadi `collectEvents()` tetap mengirim `accepted` beserta `extra_amount`; dan event yang menempel pada transaksi yang sudah di-**void** tetap ikut dihitung di laporan, sehingga transaksi yang dibatalkan lalu dimasukkan ulang menghitung saran yang sama dua kali.
- **Ditutup oleh:** `[HOTFIX] Saran yang Terlanjur Diterima Bisa Ditarik Lagi, dan Transaksi yang Di-void Berhenti Mengaku Berhasil (BL-092 butir 3)` dan `[ADDITION] Owner Melihat Saran Otomatis, Aturannya Sendiri, dan Siapa yang Mengisi Tiga Slot Kasir (BL-092 butir 1-2)` di `docs/CHANGELOG.md`

### [BL-090] Angka Rekonsiliasi Tetap Dikirim ke Kasir — Penyembunyiannya Baru di Sisi Klien
- **Ditemukan:** 2026-08-21 (dipecah dari `[BL-086]` saat ketiga butirnya selesai)
- **Sumber:** Catatan di dalam `[BL-086]` sendiri — "butir 1 tidak boleh dikerjakan sebagai penyembunyian di sisi klien saja kalau tujuannya penegakan sungguhan"
- **Status:** **Selesai 2026-08-21** — pemilik memilih bentuk (2). Lihat `[ADDITION] Membuka Angka Seharusnya Meninggalkan Jejak yang Dibaca Pemilik (BL-090)`
- **Status semula:** Open — butuh keputusan pemilik lebih dulu
- **Keputusan pemilik 2026-08-21:** bentuk **(2)**, mencatat alih-alih mencegah. Bentuk (1) ditolak karena ia mematikan tombol peragaan yang pemilik sendiri minta di `[BL-086]`.
- **Prioritas:** Low — bukan karena lubangnya kecil, melainkan karena orang yang mampu membuka devtools di tablet kasir bukan lagi persoalan yang bisa dijawab satu layar
- **Area Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php:41` — `index()` mengirim `reconciliation` penuh ke setiap kasir yang membuka halaman kas
  - `resources/js/Pages/Cashier/CashDrawer.vue` — `showExpected` hanya menahannya dari layar, bukan dari props
- **Deskripsi:** `[BL-086]` menutup angka "seharusnya di laci" di layar, dan itu cukup untuk peragaan serta untuk menghilangkan godaan sehari-hari. Yang TIDAK berubah: angkanya tetap ikut props Inertia setiap kali halaman kas dibuka, jadi ia terbaca dari devtools peramban tanpa satu pun izin tambahan.
- **Usulan Perbaikan:** `index()` berhenti mengirim `expected_amount`, `cash_in`, dan `change_out` sampai hitungan fisik disetorkan. Dua bentuk yang mungkin, dan keduanya menukar sesuatu:
  1. **Endpoint terpisah** yang mengembalikan angkanya hanya setelah hitungan fisik dikirim. Paling ketat, tapi ia mematikan tombol "Tampilkan uang seharusnya" yang justru diminta pemilik untuk peragaan.
  2. **Tetap dikirim, tapi pengungkapannya dicatat** — satu baris jejak "kasir X membuka angka seharusnya pada jam Y, sebelum menghitung". Tidak mencegah apa pun, tapi membuatnya terlihat, dan itu yang benar-benar dibutuhkan pemilik yang ingin tahu.
- **Catatan:** bentuk (2) lebih dekat dengan cara masalah ini biasanya diselesaikan di kasir sungguhan — penyimpangan tidak diblokir, ia dicatat. Jangan pilih (1) hanya karena ia terdengar lebih aman: layar yang tidak bisa menunjukkan angkanya sama sekali akan membuat pemilik meminta jalan pintasnya kembali di peragaan berikutnya.

### [BL-086] Layar Tutup Kas Menyebutkan Jawabannya Sebelum Kasir Menghitung, dan Dua Alur Berbeda Menumpang Satu Halaman
- **Ditemukan:** 2026-08-21
- **Sumber:** Catatan pemilik — "ada keliatan uang yang seharusnya di laci padahal itu owner saja yang liat, kasir sisa input yang nyata (agar tidak manipulatif), dan seharusnya ada flow tersendiri ketika mau tutup kas"
- **Status:** **Selesai 2026-08-21** — ketiga butirnya, plus satu lubang yang ditemukan saat mengerjakannya. Sisa penegakan sisi servernya dipecah jadi `[BL-090]`
- **Status semula:** In Progress — butir 1 & 3 selesai 2026-08-21 (lihat `[ADDITION] Angka yang Jadi Jawaban Disembunyikan Selama Sesi Berjalan (BL-086 butir 1)`); **butir 2 tersisa**
- **Prioritas:** High — bukan soal tampilan: selama angkanya terbaca lebih dulu, seluruh rekonsiliasi kas tidak membuktikan apa pun
- **Area Terdampak:**
  - `resources/js/Pages/Cashier/CashDrawer.vue:203-206` — "Seharusnya di laci" tampil di panel sesi aktif, **sebelum** kasir menyentuh kolom uang fisik
  - `resources/js/Pages/Cashier/CashDrawer.vue:288-292` — angka yang sama diulang di layar ringkasan
  - `resources/js/Pages/Cashier/CashDrawer.vue:20` — `step = 1 | 2` menampung dua alur berbeda (buka kas dan tutup kas) dalam satu halaman dan satu rute
  - `app/Http/Controllers/Cashier/CashDrawerController.php:36` — `index()` mengirim `reconciliation` penuh ke kasir tanpa syarat
- **Deskripsi:** Kasir membuka halaman kas, membaca "Seharusnya di laci Rp 740.000", lalu mengetik Rp 740.000 di kolom uang fisik. Selisihnya selalu nol, dan laci yang benar-benar kurang Rp 50.000 tidak akan pernah ketahuan. Penghitungan buta (*blind count*) adalah satu-satunya alasan rekonsiliasi kas ada; membocorkan angkanya lebih dulu membuat seluruh mesin di `CashDrawerReconciliation` menghasilkan angka yang tidak membuktikan apa pun. Terpisah dari itu, satu halaman memegang dua pekerjaan yang berbeda hari dan berbeda niat — membuka kas di pagi hari, dan mempertanggungjawabkannya di malam hari.
- **Dugaan Penyebab:** ini **akibat langsung** dari `[BL-028]` Tahap A butir 4, yang meminta rincian ditampilkan sebelum tombol tutup ditekan. Niatnya benar dan masih berlaku — kasir tidak boleh mencari uang QRIS di dalam laci — tapi butir itu tidak memisahkan **rincian yang membantu** (tunai masuk, kembalian keluar, non-tunai yang tidak masuk laci) dari **jawaban yang tidak boleh dibocorkan** (total seharusnya di laci). Keduanya dipasang bersamaan.
- **Usulan Perbaikan:**
  1. ~~"Seharusnya di laci" **tersembunyi secara bawaan**, dengan tombol "Tampilkan uang seharusnya" — pemilik meminta bentuk ini secara eksplisit untuk keperluan peragaan.~~ **Selesai 2026-08-21.** Satu koreksi terhadap usulan ini saat dikerjakan: yang disembunyikan bukan hanya totalnya melainkan **ketiga angka pembentuknya** (modal + tunai masuk − kembalian keluar) — menyembunyikan total sambil memajang penjumlahnya bukan penghitungan buta, itu soal hitungan. Gagasan "tombolnya baru hidup setelah kolom uang fisik terisi" **tidak** diambil: pemilik meminta bentuk yang bisa diperagakan, dan syarat itu membuat angkanya tak bisa ditunjukkan saat peragaan tanpa mengarang hitungan lebih dulu.
  2. Pecah jadi dua rute: `cash-drawer` (keadaan sesi + jalan ke POS) dan `cash-drawer/close` (alur tutup kas), dengan modal konfirmasi sebelum sesi benar-benar tertutup. **Belum dikerjakan.**
  3. ~~Rincian yang tidak membocorkan jawaban — tunai masuk, kembalian keluar, non-tunai yang ditandai "tidak masuk laci", kas negatif `[BL-031]` — **tetap** ditampilkan.~~ **Selesai 2026-08-21**, dengan batasnya diperjelas: rincian itu tetap utuh di **ringkasan tutup kas**, yaitu sesudah hitungan fisik disetorkan. Di panel sesi berjalan ia ikut tertutup, karena di sana ia berfungsi sebagai bocoran, bukan sebagai bantuan.
- **Catatan:** butir 1 tidak boleh dikerjakan sebagai penyembunyian di sisi klien saja kalau tujuannya penegakan sungguhan; angkanya tetap ada di props Inertia dan terbaca dari devtools. Untuk peragaan itu cukup; untuk benar-benar mencegah manipulasi, `index()` harus berhenti mengirimkannya sampai hitungan fisik disetorkan. **Yang terpasang 2026-08-21 adalah versi peragaannya**, dan penegakan sisi servernya masih terbuka.
- **Lubang kedua, ditemukan saat mengerjakan butir 1 dan belum ditutup:** dari layar ringkasan, kasir bisa menekan "Kembali" dan **mengubah angka uang fisiknya setelah membaca selisih**. Penghitungan butanya karena itu masih bisa dibatalkan dalam dua klik. Penutupnya murah — kolom uang fisik dikunci begitu ringkasan pernah dibuka, dan mengubahnya menuntut tombol "Hitung ulang" yang mengosongkan kolomnya, sehingga revisi jadi tindakan yang disengaja dan terlihat — tapi ia mengubah alur, jadi tempatnya bersama butir 2, bukan disisipkan ke butir 1.

### [BL-089] Klaim Prediksi Stok Masih Berdiri di Tab Demo, FAQ, dan Label "Machine Learning"
- **Ditemukan:** 2026-08-20 (butir (c) `[BL-083]` — menyisir hero ternyata membuka bahwa klaimnya jauh melampaui hero)
- **Sumber:** Kelanjutan `[BL-083]`. Hero sudah dibersihkan; entri ini memuat sisanya, yang lingkupnya berbeda kelas
- **Status:** **Selesai 2026-08-20** — dijawab pemilik: tab ketiga diganti **Saran Jual**, dan FAQ diganti penjelasan cara Badge Helper bekerja
- **Status semula:** Open — butuh keputusan pemilik, dan keputusannya lebih besar daripada `[BL-078]` maupun `[BL-083]`: yang dipertaruhkan satu dari tiga pilar bagian Demo
- **Prioritas:** Medium — sama seperti `[BL-083]`, tapi permukaannya jauh lebih luas dan salah satunya menyebut teknologi tertentu dengan nama
- **Area Terdampak:**
  - `resources/views/public/landing.blade.php:569` — judul panel: **"Prediksi Stok (Machine Learning)"**
  - `resources/views/public/landing.blade.php:514` — tab demo ketiga berlabel "Prediksi Stok"
  - `resources/views/public/landing.blade.php:574` — tombol "Jalankan Ulang Prediksi AI"
  - `resources/views/public/landing.blade.php:1025-1029` — FAQ "Bagaimana cara AI memprediksi stok saya?" beserta jawabannya
  - `resources/views/public/landing.blade.php:~1066` — `predictData`: stok sekarang, **stok terprediksi**, dan **sisa hari** per produk
  - `resources/views/public/landing.blade.php:~1060` — `badgeTemplates`: "Habis dalam 2-3 hari", plus tombol "Pesan ke Supplier", "Promo Diskon", "Buat Bundle"
  - `app/Services/BadgeHelperService.php` — apa yang sebenarnya ada
  - `app/Jobs/RunAiAnalysisJob.php:142` — satu-satunya yang menyerempet proyeksi, dan itu **profit**, bukan stok
- **Deskripsi:**
  `[BL-083]` menutup dua kartu melayang di hero dengan asumsi itulah sisanya. Ternyata bukan. Sebutan ramalan stok berdiri di **empat tempat lain**, dan tiga di antaranya lebih tegas daripada kartu yang baru dicabut:

  1. **"Machine Learning" disebut dengan nama.** Tidak ada model, tidak ada pelatihan, tidak ada pustaka ML di seluruh `composer.json`. Yang ada penyedia LLM (Anthropic, Gemini, OpenAI, SumoPod) untuk analisis teks. Menyebut teknologi tertentu adalah klaim yang paling mudah diperiksa orang luar, dan paling mahal bila keliru.
  2. **Tab demonya interaktif dan memperagakan angka ramalan.** `predictData` memberi tiap produk "stok terprediksi" dan "sisa hari", dengan tombol "Jalankan Ulang Prediksi AI" yang membuat peragaannya terasa seperti perhitungan sungguhan. Ini bukan kalimat yang bisa diganti — ini satu dari tiga tab, dengan data dan interaksinya sendiri.
  3. **FAQ menjelaskan CARA kerjanya.** "AI kami menganalisis data transaksi historis toko Anda selama 90 hari terakhir untuk menemukan pola musiman dan tren harian unik toko Anda." Kalimat itu menjelaskan mekanisme yang tidak ada — dan penjelasan mekanisme jauh lebih meyakinkan, karenanya jauh lebih menyesatkan, daripada slogan.
  4. **Tombol aksi di peragaan badge** — "Pesan ke Supplier", "Promo Diskon", "Buat Bundle" — sama persis dengan "Eksekusi Sekarang" yang baru dicabut `[BL-083]` butir (b): tidak ada jalan satu tekan untuk satu pun dari ketiganya.
- **Yang perlu diputuskan pemilik, dan kenapa ini bukan sekadar sunting teks:**
  1. **Apakah prediksi stok akan dibangun?** Bila ya, ini bukan entri pemasaran melainkan entri fitur, dan halaman itu boleh tetap berdiri sampai fiturnya menyusul — asalkan diberi tanda "segera hadir", bukan dibiarkan tampak sudah jalan. Bila tidak, tab ketiga harus diganti dengan pilar yang benar-benar ada.
  2. **Apa penggantinya bila dibongkar?** Tiga tab hari ini: Simulasi Kasir, Badge Helper AI, Prediksi Stok. Kandidat pengganti yang sudah nyata dan belum punya peragaan: Saran Jual berbasis aturan (`[BL-074]`), diskon dinamis dengan lantai untung (`[BL-018]`), atau tagihan terbuka berumur (`[BL-031]`).
- **Usulan Perbaikan:**
  **(a)** Apa pun keputusannya, **"(Machine Learning)" dicabut lebih dulu dan sendirian.** Ia klaim paling tegas, paling murah dibuang, dan tidak menunggu keputusan tentang tab.
  **(b)** Jawaban FAQ diganti dengan yang benar-benar dikerjakan `BadgeHelperService`: ambang stok, dead stock 30 hari, dan kedaluwarsa. Pertanyaannya ikut berubah, karena "bagaimana cara AI memprediksi stok saya" tidak punya jawaban jujur.
  **(c)** Tombol aksi di peragaan badge diturunkan jadi label, sama seperti `[BL-083]` butir (b) — atau dibangun jalannya. Keduanya sah; yang tidak sah adalah membiarkannya tampak bisa ditekan.
  **(d)** **Jangan** menyimpan kata "prediksi" sambil membuang "Machine Learning". Yang menyesatkan bukan nama teknologinya melainkan janji horizonnya.

---
- **Hasil:**
  **(a)** "(Machine Learning)" hilang bersama tabnya. Tab ketiga kini **Saran Jual** — satu-satunya pilar besar yang sudah jalan tapi belum pernah punya peragaan. Empat barisnya memetakan satu-satu ke strategi yang benar-benar ada di `app/Services/Upsell/Strategies/`, dan kode alasannya (`price_step`, `cooccurrence`, `dead_stock`, `owner_rule`) diambil dari konstanta `REASON_*` pada `UpsellEvent`, bukan istilah karangan. Bentuk barisnya mengikuti `Suggestion`: label, catatan alasan, dan tambahan rupiah.
  **(b)** FAQ "Bagaimana cara AI memprediksi stok saya?" jadi "Bagaimana SAPI tahu stok saya bermasalah?", dan jawabannya menyebut keempat aturan `BadgeHelperService` apa adanya — di bawah ambang, habis, tak terjual 30 hari, lewat kedaluwarsa — lalu menutup dengan menyatakan terus terang bahwa ia bukan ramalan berapa hari lagi stok habis.
  **(c)** Ketiga tombol aksi peragaan badge diturunkan jadi pil hitungan, bentuk yang memang dipakai `BadgeCard.vue`. Dikerjakan lebih dulu karena ia satu-satunya butir yang tidak menunggu keputusan.
- **Butir (c) ternyata memuat tiga cacat, bukan satu.** Selain tombolnya: badge pertama menulis "Habis dalam 2-3 hari" — ramalan yang sama persis dengan yang baru dicabut `[BL-083]`; warnanya memakai merah/kuning padahal `severityClasses` di `BadgeCard.vue` memberi stok kritis `warning` (amber) dan dead stock `info`; dan badge ketiga berjudul **"Upsell"**, jenis yang tidak pernah dihasilkan `BadgeHelperService` sama sekali.
- **Dua fitur yang disebut tombolnya ternyata nol kode, bukan sekadar tanpa jalan pintas.** Pencarian `supplier`/`purchase_order` dan `bundle` di seluruh `app/` dan `database/migrations/` mengembalikan **nol hasil**. "Pesan ke Supplier" dan "Buat Bundle" bukan tombol yang belum disambungkan; keduanya menyebut fitur yang tidak ada. Hanya "Promo Diskon" yang menunjuk sesuatu yang nyata (`owner/discount-rules`) tanpa jalan satu tekan.
- **Satu klaim nyaris lahir lagi saat memperbaikinya, dan ini ketiga kalinya.** Contoh upsell sempat ditulis "sering dibeli bersama" — lalu diperiksa, dan ternyata keempat strategi yang ada adalah `UpsizeVariant`, `AttachModifier`, `PressedStock`, dan `ManualRule`. Diganti "tawarkan Double", yang memetakan ke `UpsizeVariantStrategy`. **Catatan yang menarik:** `UpsellEvent::REASON_COOCCURRENCE` ternyata memang ada, jadi ko-okurensi bukan karangan — yang keliru adalah menganggapnya strategi tersendiri. Pola yang sama sudah muncul di `[BL-078]` dan `[BL-083]`: penggantinya harus diperiksa ke kode dengan disiplin yang sama seperti yang diganti.
- **Komentar penjelasnya harus jadi komentar Blade, bukan komentar JavaScript, dan itu ditemukan oleh testnya sendiri.** Rasional perbaikan mula-mula ditulis sebagai `/* … */` di dalam `<script>` — yang berarti frasa "Pesan ke Supplier" dan "Buat Bundle" tetap terkirim ke peramban dan test barunya gagal. Diubah jadi `{{-- … --}}` sehingga dibuang di server. Penjelasan ditujukan untuk pembaca kode, bukan untuk pengunjung.
- **Testnya sekarang menjaga seluruh halaman, bukan hanya hero.** Catatan pada test `[BL-083]` yang menyatakan "sengaja hanya mengunci hero sampai `[BL-089]` diputuskan" ikut diperbarui, supaya tidak ada yang membaca batasan yang sudah tidak berlaku.
- **Entri penutup di `docs/CHANGELOG.md`:** `[HOTFIX] Tab Demo Ketiga Berhenti Meramal dan Jadi Saran Jual yang Memang Sudah Jalan (BL-089)`

### [BL-085] Grup "Keuangan" di Sidebar Menampung Alat Promosi Bersama Laporan Uang
- **Ditemukan:** 2026-08-21
- **Sumber:** Catatan pemilik — "saya rasa perlu mengatur menu untuk upsell dan diskon ini lainnya, karena menu nya tercampur sebagai keuangan dan saya rasa kurang cocok"
- **Status:** **Selesai 2026-08-21** — lihat `[REFACTOR] Aturan Saran Jual dan Diskon Keluar dari "Keuangan", Jadi Grup Sendiri (BL-085)`
- **Status semula:** Open
- **Prioritas:** Low — tidak ada angka yang salah, tapi ia satu-satunya grup dengan sepuluh item dan dua di antaranya bukan keuangan
- **Area Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue:125-137` — grup `Keuangan` berisi Laporan Harian, Laporan Bulanan, Saran Jual, Aturan Saran Jual, Aturan Diskon, Transaksi, Sesi Kas, Koreksi Offline, Pembayaran, AI Analysis
- **Deskripsi:** "Aturan Saran Jual" (`[BL-074]`) dan "Aturan Diskon" (`[BL-018]`) adalah tempat owner **menyusun cara berjualan** — keduanya menulis aturan yang berlaku ke depan, bukan melaporkan apa yang sudah terjadi. Menaruhnya di antara Laporan Harian dan Sesi Kas membuat orang mencarinya di tempat yang salah, dan membuat Keuangan jadi grup terpanjang di sidebar.
- **Usulan Perbaikan:** grup baru **"Penjualan & Promosi"** berisi Saran Jual, Aturan Saran Jual, Aturan Diskon; Keuangan menyisakan Laporan Harian, Laporan Bulanan, Transaksi, Sesi Kas, Koreksi Offline, Pembayaran, AI Analysis. Murni penyusunan ulang array `navGroups` — gerbang `perm`/`ownerOnly` tiap item ikut pindah apa adanya, tidak ada satu pun hak akses yang berubah.
- **Hasil:** ditempuh persis seperti usulan. Satu hal ditemukan saat memindahkan dan tidak ada di entri ini: isi grup dibatasi `max-h-96` (384px) sementara sepuluh item Keuangan mengukur **380px** — item kesebelas akan hilang tanpa satu pun tanda. Batasnya tidak diubah; ia tetap ranjau bagi grup mana pun yang tumbuh melewati sepuluh item.

### [BL-083] Kartu Melayang di Hero Menjanjikan "Prediksi Stok Aman Hingga 14 Hari" yang Tidak Ada Mesinnya
- **Ditemukan:** 2026-08-20 (saat memasang tangkapan layar baru `[BL-032]` butir (3) — kartunya melayang tepat di atas gambar yang sedang diganti)
- **Sumber:** Kelanjutan langsung `[BL-032]` butir (1). Empat klaim karangan dibuang 2026-08-14, tapi penyisirannya berhenti pada teks bagian isi dan **tidak menyentuh dua kartu melayang di hero**
- **Status:** **Selesai 2026-08-20** — ketiga butir dikerjakan. Dijawab pemilik: kartunya diganti **Stok Kritis**
- **Status semula:** Open — butuh keputusan pemilik, sama seperti `[BL-078]`: ini teks pemasaran, bukan cacat teknis
- **Prioritas:** Medium — tidak ada angka yang salah, tapi ia berdiri di **layar pertama**, di atas lipatan, dan ia klaim yang bisa diperiksa ke kode. Persis kategori yang `[BL-032]`(1) bersihkan
- **Area Terdampak:**
  - `resources/views/public/landing.blade.php:196-197` — kartu "PREDIKSI STOK / Aman Hingga 14 Hari"
  - `resources/views/public/landing.blade.php:209-212` — kartu "Badge Helper" dengan kutipan dan tombol "Eksekusi Sekarang"
  - `app/Services/BadgeHelperService.php:26-110` — apa yang sebenarnya dihitung
- **Deskripsi:**
  **Kartu pertama menjanjikan ramalan; yang ada ambang tetap.** `BadgeHelperService` menghitung empat hal, semuanya perbandingan sederhana terhadap keadaan sekarang: stok ≤ 5 (Stok Kritis), stok ≤ 0 (Stok Habis), nol penjualan dalam 30 hari (Dead Stock), dan `expiry_date` yang sudah lewat. Tidak ada satu pun yang memproyeksikan berapa lama stok akan bertahan. "Aman Hingga 14 Hari" menyebut **horizon** — angka yang tidak pernah dihitung di mana pun.

  **Kartu kedua jauh lebih dekat dengan kenyataan, dan sengaja dipisahkan dari yang pertama.** "Kopi Susu Gula Aren mulai sepi, beri diskon 15%?" kira-kira sama dengan badge Dead Stock, dan sejak `[BL-018]` diskon memang entitas sungguhan serta `[BL-074]` memberi owner cara menargetkan sarannya sendiri. Yang belum diperiksa adalah tombol "Eksekusi Sekarang": apakah benar ada satu jalan dari saran ke diskon terpasang dalam satu tekan. Bila tidak ada, yang perlu diubah tombolnya, bukan kalimatnya.
- **Kenapa tidak dikerjakan sekalian:** menggantinya berarti menulis kalimat pemasaran baru, dan kalimat pengganti yang dikarang sendiri adalah cara paling halus mengulang kesalahan yang sedang diperbaiki — pelajaran yang baru saja terbukti di `[BL-078]`, ketika "prediksi stok" dan "barcode" nyaris ikut masuk ke bagian yang justru dibuat untuk berhenti mengarang.
- **Usulan Perbaikan:**
  **(a)** Ganti kartu pertama dengan yang benar-benar dihitung. Yang paling dekat dan tetap terdengar kuat: **"STOK KRITIS — 3 varian mendekati habis"**, karena itulah badge `low_stock` apa adanya. Bentuknya tidak berubah; janjinya berubah dari ramalan jadi peringatan.
  **(b)** Periksa tombol "Eksekusi Sekarang" terhadap kode sebelum kartu kedua dinyatakan aman. Bila jalannya belum satu tekan, turunkan jadi label yang tidak menjanjikan tindakan.
  **(c)** Sisir **seluruh** hero sekali lagi, bukan hanya dua kartu ini. `[BL-032]`(1) berhenti di teks bagian isi, dan itulah sebabnya keduanya bertahan enam hari lebih lama daripada empat klaim yang sudah dibuang.

---
- **Hasil:**
  **(a)** Kartu pertama jadi "STOK KRITIS / 3 varian mendekati habis" — kalimatnya meminjam pesan badge `low_stock` apa adanya, dan warnanya turun dari hijau ke amber mengikuti `severity: warning` milik badge itu sendiri. Ikon centang diganti segitiga peringatan: kartu yang memperingatkan tidak boleh berwajah kabar baik.
  **(b)** Tombol "Eksekusi Sekarang" **dicabut**, bukan diganti kata. Diperiksa lebih dulu dan hasilnya tegas: `BadgeCard.vue` hanya membuka-tutup, tidak ada rute yang mengubah saran jadi diskon, dan aturan diskon punya layarnya sendiri (`owner/discount-rules`). Penggantinya keterangan tempat — "Muncul di dashboard Anda" — yang benar dan tidak menjanjikan tindakan.
  **(c)** Sisiran hero menemukan yang ketiga, dan ia lebih menonjol daripada kedua kartu: **paragraf utama** berbunyi "SAPI bisa memprediksi stok Anda". Diganti dengan yang benar-benar dikerjakan — menandai stok menipis dan barang yang berhenti laku, menganalisis pola penjualan, dan menurunkan saran jual yang menyebut barangnya.
- **Butir (c) juga membuktikan asumsi entri ini keliru, dan itu hasil terpentingnya.** Entri ini ditulis seolah persoalannya dua kartu melayang. Ternyata sebutan ramalan stok berdiri di empat tempat lain — tab demo interaktif berjudul "Prediksi Stok (Machine Learning)", tombol "Jalankan Ulang Prediksi AI", jawaban FAQ yang menjelaskan mekanismenya, dan `predictData` yang memperagakan sisa hari per produk. Itu satu dari tiga pilar bagian Demo, jadi ia **tidak** dibongkar di sini dan jadi `[BL-089]`.
- **Testnya sengaja hanya mengunci hero.** Menyapu seluruh sebutan "prediksi" akan membuat test ini gagal sampai `[BL-089]` dikerjakan — padahal `[BL-089]` menunggu keputusan pemilik. Test yang menuntut pekerjaan yang belum diputuskan adalah test yang akan dilewati orang, bukan test yang menjaga.
- **Entri penutup di `docs/CHANGELOG.md`:** `[HOTFIX] Hero Berhenti Menjanjikan Ramalan Stok dan Tombol yang Tidak Punya Jalan (BL-083)`

### [BL-084] Sidebar Owner Menulis "SAPI", Bukan Nama Toko yang Sedang Dibuka
- **Ditemukan:** 2026-08-21
- **Sumber:** Catatan pemilik — "sidebar mengapa menampilkan tulisan sapi, bukannya disitu tertulis jadi nama toko owner begitu di sidebar nya?"
- **Status:** **Selesai 2026-08-21** — lihat `[HOTFIX] Sidebar Owner Menyebut Nama Toko yang Sedang Dibuka, Bukan Nama Produknya (BL-084)`
- **Status semula:** Open
- **Prioritas:** Low
- **Area Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue:299` — literal `SAPI`, tidak membaca prop mana pun
  - `resources/js/Components/CashierTopbar.vue:133` — sisi kasir **sudah** memakai `page.props.auth?.tenant?.name` dengan cadangan `'SAPI POS'`; polanya tinggal ditiru
  - `app/Http/Middleware/HandleInertiaRequests.php:55` — `auth.tenant.name` sudah dibagikan ke setiap halaman, tanpa query tambahan
- **Deskripsi:** Shell owner menyebut nama produk di tempat yang seharusnya menyebut nama usaha penggunanya. Kasir di aplikasi yang sama sudah melihat nama tokonya sendiri di topbar; hanya sidebar owner yang tidak ikut.
- **Dugaan Penyebab:** `[DECISION] Tiga Permukaan Publik Jadi Satu Keluarga: SAPI POS Resmi (BL-033)` menetapkan penyebutan merek yang seragam — tapi keputusan itu tentang **permukaan publik** (landing, login, halaman galat), tempat pembacanya memang belum punya toko. Shell owner ikut terbawa padahal pembacanya sudah jelas berada di dalam satu toko.
- **Usulan Perbaikan:** `auth.tenant?.name` dengan cadangan `'SAPI POS'`, persis pola `CashierTopbar.vue`. Yang perlu diputuskan pemilik: glyph `S` di sebelahnya ikut jadi inisial toko, atau tetap penanda produk.
- **Hasil:** ditempuh seperti usulan, dan pertanyaan glyph-nya **dijawab ikut nama toko** — `S` di sebelah "Kopi Nusantara" akan terbaca sebagai merek yang salah, bukan sebagai penanda produk. Satu baris `computed`, gampang dikembalikan kalau pemilik memutuskan sebaliknya.

### [BL-032] Landing Page Tidak Lagi Menggambarkan Produk yang Sudah Jadi
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "landing perbaiki menyesuaikan sekarang", "perbaiki gambar2 di landing page"
- **Status:** **Selesai 2026-08-20** — butir (3) ditutup, dan dengan itu seluruh entri
- **Status semula:** Open — butir (1) dan (2) SELESAI 2026-08-14, butir (3) separuh (avatar beres, tangkapan layar belum)
- **Prioritas:** Turun ke **Low** sejak 2026-08-14 — yang tersisa tinggal kesegaran gambar. Seluruh klaim yang salah sudah dibuang dan harganya sudah dari data, jadi halaman ini tidak lagi menyesatkan siapa pun; yang tertinggal hanya lima tangkapan layar bertanggal Mei
- **Area Terdampak:**
  - ~~`resources/views/public/landing.blade.php:686-745` — bagian harga menuliskan dua paket "Core POS Rp 149k" dan "Smart SAPI Rp 299k" yang **tidak ada di sistem**~~ — **beres 2026-08-14**, kartunya kini dibangun dari `plans` lewat `PublicPricing`
  - ~~`resources/views/public/landing.blade.php` — CTA di luar bagian harga ("Daftar Gratis", "Mulai Sekarang" di hero, nav seluler, dan footer) **masih** menunjuk `/login`, padahal `/register` ada dan itulah tujuan yang dimaksud~~ — **beres 2026-08-15** bersama `[BL-071]`: kelima CTA pendaftaran menunjuk `route('register')` dan berlabel "Coba Gratis N Bulan"; yang menunjuk `/login` tinggal tombol berlabel "Login" dan "Masuk", dijaga sebuah test di `tests/Feature/Public/LandingPricingTest.php`
  - ~~`public/avatar_andi.png`, `public/avatar_budi.png`, `public/avatar_santi.png` — masing-masing ±600 KB untuk dirender `w-12 h-12` (48 px); tiga berkas ini saja ±1,8 MB~~ — **beres 2026-08-14**: WebP 96 px, ±2 KB masing-masing, PNG lamanya dihapus. Ternyata ketiganya JPEG berekstensi `.png`
  - `public/Dashboard-owner.png`, `POS-Interface.png`, `Reports-Daily.png`, `Stock-Management.png`, `Product-List.png` — **satu-satunya sisa entri ini.** Tertanggal 25 Mei, sebelum topbar tagihan terbuka, papan antrian, dan saran jual ada. Kelimanya juga masih PNG tak terkompresi, jadi mengambilnya ulang sekalian menutup sebagian `[BL-077]`
- **Deskripsi:**
  Dihitung dari isi berkasnya, halaman ini tidak menyebut satu pun dari yang sudah dikirim sejak Mei: "open bill" 0 kali, "antrian" 0, "pesan mandiri/self-order" 0, "MCP" 0, "subsidi" 0, "modifier" 0, "sesi kas" 0. Yang tersisa hanya "offline" (1) dan "saran jual" (1). Sementara harga yang dipajang bukan sekadar usang — angkanya tidak pernah ada: paket bawaan sistem satu-satunya bernama `Dasar` dengan `base_price = 0` (`database/migrations/2026_07_24_181634_create_plans_table.php:38-47`), dan jalur subsidi memakai bracket Rp 10k–100k (`config/subscription.php:85-90`). Calon klien yang membaca "Rp 299k/bulan" lalu mendaftar akan mendapati tagihan yang sama sekali lain.
- **Usulan Perbaikan:**
  Tiga hal terpisah, boleh dikerjakan bertahap: ~~(1) tulis ulang daftar fitur dari kapabilitas yang benar-benar ada~~ **selesai 2026-08-14**; ~~(2) tarik harga dari `pricing_rules`/`plans` alih-alih menuliskannya di HTML — lihat `[BL-041]`~~ **selesai 2026-08-14**; (3) ambil ulang tangkapan layar dari seeder demo — **belum**, dan ~~turunkan avatar ke ±10 KB (WebP 96 px)~~ **selesai 2026-08-14**.
- **Catatan 2026-08-14 — daftar fitur per paket ikut hilang saat butir (2) mendarat, dan itu disengaja.** Dua kartu lama memajang bullet fitur ("Transaksi Kasir & Multi-payment", "AI Prediksi Stok & Badge Helper") yang menempel pada paket karangan. Begitu paketnya datang dari `plans`, tidak ada satu pun sumber data yang menjawab "paket ini dapat fitur apa" — `plans.limits` hanya memuat batas, bukan daftar kapabilitas. Menuliskannya kembali dengan tangan berarti mengulang kesalahan yang sama pada kolom yang berbeda. Kartunya sekarang memajang nama, harga, dan tombol saja; isi paket yang benar-benar berbasis data (seat bawaan, kuota AI) datang lewat `[BL-067]`, dan daftar kapabilitasnya lewat butir (1) entri ini.
- **Catatan 2026-08-14 — butir (1) ternyata bukan soal kelengkapan, melainkan soal kebenaran.** Entrinya berbunyi "tulis ulang daftar fitur dari kapabilitas yang benar-benar ada", yang terbaca sebagai menambah yang kurang. Yang ditemukan saat mengerjakannya: empat klaim di halaman itu **tidak pernah ada wujudnya**, dan masing-masing diperiksa ke kode lebih dulu sebelum dibuang — (a) tombol "Lanjutkan dengan Google" lengkap dengan logonya, padahal tidak ada Socialite, tidak ada rute OAuth, dan `AuthController@register` hanya menerima email + kata sandi; (b) "SAPI otomatis membuatkan kategori produk, daftar menu populer, dan saran stok awal", padahal `register()` membuat tepat tiga hal — tenant, langganan masa coba, user owner; (c) "AI SAPI akan langsung mengenali kebutuhan bisnis Anda" dari kategori usaha, padahal `business_type` tidak menyentuh satu pun `*_enabled` (lihat `[BL-034]`); (d) "digunakan ribuan UMKM", padahal basis datanya berisi dua tenant demo. Lihat `[HOTFIX] Landing Berhenti Menjanjikan Login Google, Katalog Otomatis, dan "Ribuan UMKM" (BL-032 butir 1)` di `docs/CHANGELOG.md`.
- **Kapabilitas berbentuk API disebut sebagai API, bukan sebagai layar.** Pesan mandiri (`api/orders`), POS mobile (`api/mobile/*`), dan MCP semuanya nyata dan tidak satu pun punya halaman bawaan. Entri ini semula mengeluh landing tidak menyebutnya sama sekali; menyebutnya seolah fitur yang tinggal dibuka akan mengulang persis kesalahan yang butir (1) bersihkan.
- ~~**Testimoni tidak ikut dibersihkan, dan itu keputusan yang disengaja.** Tiga kutipan bernama di atas potret stok kemungkinan karangan juga, tapi menghapusnya keputusan pemasaran pemilik, bukan konsekuensi teknis. Dipisah jadi `[BL-078]`.~~ — **selesai 2026-08-20**: dijawab pemilik memang karangan, dan bagiannya diganti "Untuk Siapa SAPI Dibuat". Lihat `[BL-078]` di arsip.
- **Masih belum bisa diambil (2026-08-19), dan penghalangnya kini MURNI permukaan pemotretnya.** Basis data sudah menyala dan aplikasinya berjalan — `php artisan serve` di :8001 memuat halaman dengan benar. Yang tidak ada adalah alat yang bisa **menulis berkas PNG**: panel Browser dalam-aplikasi memuat halaman tapi tidak menggambar piksel selama panelnya tidak ditampilkan, dan alat itu memang hanya mengembalikan gambar ke agen, bukan ke disk; server `chrome-devtools` (satu-satunya yang bisa menulis berkas) terputus di sesi ini; ekstensi Claude in Chrome — yang punya `save_to_disk` — tidak menyambungkan satu peramban pun (`list_connected_browsers` mengembalikan daftar kosong).
  **Tiga jalan keluar, urut dari yang paling murah:** (1) pemilik memotret sendiri kelima halaman selagi server menyala; (2) menyambungkan ekstensi Claude in Chrome; (3) menyambungkan kembali server MCP `chrome-devtools`. Setelah berkasnya ada, sisanya — konversi WebP lewat GD, penyamaan lebar, pemasangan rujukan, uji — tinggal dikerjakan. Konversi kelima PNG lama sengaja TIDAK dikerjakan lebih dulu: berkasnya memang akan diganti, jadi mengerjakannya sekarang berarti mengerjakannya dua kali.

- **Kenapa tangkapan layarnya belum diambil (2026-08-14).** Bukan karena aksesnya kurang — pemilik sudah login dan `/owner/dashboard` terbukti termuat. Tiga permukaan yang bisa memotret sedang tidak bisa dipakai bersamaan: server `chrome-devtools` (satu-satunya yang bisa menulis berkas gambar) terputus; panel Browser dalam-aplikasi memuat halaman tapi tidak menggambar piksel apa pun sehingga screenshot selalu timeout, dan alat itu memang tidak bisa menulis berkas; `computer-use` bisa menulis berkas tapi jendela aplikasinya tidak tampak di monitor utama; ekstensi Claude in Chrome tidak tersambung. **Yang sengaja TIDAK ditempuh:** membaca profil Chrome pemilik untuk mengambil cookie sesi, dan menambahkan rute login tembus khusus lingkungan lokal — keduanya menyelesaikan masalah ini dan keduanya meninggalkan lubang yang tidak sebanding dengan lima gambar. Pemilik akan mengambilnya sendiri; sisanya (konversi WebP lewat GD, penyamaan lebar, pemasangan rujukan, uji) tinggal dikerjakan setelah berkasnya ada.
- **Hasil:** Kelima tangkapan layar diambil ulang dari aplikasi yang benar-benar berjalan pada 1440x900 DPR 1,5 — menghasilkan 2160x1350, dimensi yang sama persis dengan berkas Mei yang digantikannya — lalu dikonversi WebP q80. Total turun dari 531 KB (PNG lama) jadi 294 KB, meski isinya jauh lebih padat. PNG lamanya dihapus, dan keenam rujukan di landing menunjuk `.webp` dengan `width`/`height` serta `loading="lazy"` untuk kelima yang di bawah lipatan.
- **Penghalangnya ternyata hilang sendiri, dan sebabnya perlu dicatat supaya tidak dicari lagi.** Catatan 2026-08-14 dan 2026-08-19 menyimpulkan bahwa tak satu pun permukaan bisa menulis berkas gambar. Yang berubah 2026-08-20 hanyalah **server MCP `chrome-devtools` tersambung** — persis "jalan keluar (3)" yang sudah tertulis di entri ini. Alatnya punya parameter `filePath`, dan `emulate` menerima `<lebar>x<tinggi>x<DPR>`, sehingga ukuran 2x tercapai tanpa memperbesar gambar. Dua jalan pintas yang dulu sengaja ditolak — membaca cookie profil Chrome pemilik dan menambah rute login lokal — tetap tidak ditempuh: pemilik login sendiri di jendela yang dibuka MCP.
- **Datanya disegarkan lebih dulu, dan itu memunculkan cacat yang berdiri sendiri.** `DemoTransactionSeeder` dijalankan supaya "hari ini" tidak nol. Ia melewatkan hari ini justru karena ada satu tagihan terbuka sisa uji `[BL-031]` — penjaganya menghitung transaksi apa pun sebagai penjualan. Diperbaiki di commit tersendiri; lihat `[HOTFIX] Seeder Demo Melewatkan Hari yang Hanya Berisi Tagihan Terbuka` di `docs/CHANGELOG.md`.
- **Dua hal yang terlihat saat memotret dan sengaja TIDAK dibereskan di sini,** karena keduanya bukan soal kesegaran gambar: aplikasi berjalan di UTC sementara tokonya tidak (`[BL-082]`), dan dua kartu melayang di hero masih menjanjikan prediksi stok yang tidak ada mesinnya (`[BL-083]`). Yang kedua lahir dari penyisiran butir (1) entri ini yang berhenti di teks bagian isi.
- **Yang masih kurang dan diketahui:** tidak ada satu pun produk demo yang punya foto, di kedua tenant — jadi kartu produk memakai penampung berinisial. Tangkapan layar barunya tetap lebih baik daripada yang lama (yang memajang ikon gambar rusak), tapi katalog berfoto akan membuatnya jauh lebih meyakinkan. Berkas yatim `public/Feature-Showcase.png` (44 KB, 25 Mei) tidak dirujuk dari mana pun dan dibiarkan — menghapusnya keputusan pemilik.
- **Entri penutup di `docs/CHANGELOG.md`:** `[ADDITION] Lima Tangkapan Layar Landing Diambil Ulang dari Aplikasi yang Berjalan, dan Jadi WebP (BL-032 butir 3)`

### [BL-069] Kuota AI Tambahan Belum Bisa Dibeli — Tidak Ada Kolom, Tidak Ada Harga, Tidak Ada Layar
- **Ditemukan:** 2026-08-08 (dipecah dari `[BL-053]` butir (c) saat butir (a)+(b) selesai)
- **Sumber:** Keputusan pemilik 2026-08-07 — "begitu juga untuk nanti ketika mau nambah kuota ai, bayar bulanan begitu"
- **Status:** **Selesai 2026-08-20.** Seluruh butir (a)-(d) mendarat, beserta prasyarat pembatasan `profit_by_item` yang entri ini sendiri tuntut dikerjakan lebih dulu
- **Status semula:** Open — angkanya sudah ditetapkan 2026-08-19, kodenya belum ada
- **Prioritas:** Medium — tidak mendesak seperti seat (satuannya tidak sedang salah, ia memang belum ada sama sekali), tapi sudah dijanjikan pemilik dan sudah disebut di `[BL-067]`(e) sebagai hal yang **tidak boleh** dijanjikan di landing sebelum ada wujudnya
- **Area Terdampak:**
  - `app/Models/Plan.php` — `limits.ai_daily` menetapkan jatah harian per paket (2/5, 3/15, 5/30, 10/60); tak ada satu pun kolom untuk tambahan per langganan
  - `app/Services/Ai/AiQuota.php` — sejak `[BL-047]` selesai (2026-08-13) urutannya: batas paket → kebijakan bawaan platform (`ai_quota_policies` mode `baseline`) → `config/ai.php`, lalu promo (mode `bonus`) menambah di atas hasilnya. Masih tanpa celah untuk kuota yang DIBELI langganan; tempatnya satu tingkat di atas paket, sebelum promo dijumlahkan
  - `app/Services/SubscriptionService.php` — `seatChargeFor()` + `issueDuePeriodInvoices()`: polanya sudah ada dan tinggal ditiru
  - `resources/js/Pages/Billing/Show.vue` — panel beli/lepas kursi; kuota AI belum punya panel apa pun
  - `app/Http/Controllers/Platform/PricingRuleController.php` — form paket menyetel `ai_daily_limit`, tapi tak ada harga per satuan kuota
- **Deskripsi:**
  Seat tambahan sudah jadi komponen bulanan yang bisa dibeli dan dilepas (`[BL-053]`). Kuota AI diputuskan mengikuti pola yang sama di hari yang sama, tapi **belum punya bentuk apa pun**: tidak ada `extra_ai_price` di `plans`, tidak ada kolom penampung di `subscriptions`, tidak ada alur beli, dan `AiQuota::dailyLimitFor()` tidak punya tempat untuk membacanya.
  Yang benar-benar menghalangi bukan kodenya — polanya sudah terbukti dan tinggal ditiru — melainkan **harganya**. Berapa rupiah per berapa analisis, dan per hari atau per bulan, belum pernah diputuskan siapa pun. Menuliskan angka tebakan di migrasi berarti menetapkan harga tanpa ada yang memutuskannya, persis yang entri asalnya larang.
- **Yang harus diputuskan sebelum satu baris kode pun ditulis:**
  1. **Satuan yang dijual.** "+10 analisis/hari" (menaikkan plafon harian, sejalan dengan `ai_daily`) atau "+100 analisis sekali pakai" (kredit yang habis)? Keduanya menuntut mekanisme berbeda: yang pertama cukup satu angka tambahan yang dibaca `AiQuota`, yang kedua menuntut saldo yang berkurang dan karena itu tabel tersendiri.
  2. **Harga per satuannya**, dan apakah ia per paket seperti `extra_seat_price` (makin tinggi paketnya makin murah) atau seragam. **Lihat hitungan ongkosnya di bawah** — angkanya sudah ada, yang belum keputusannya.
  3. **Apa yang terjadi saat dilepas atau saat tenant turun paket** — kuota yang dibeli mengikuti pola seat (berlaku satu periode penuh ke depan), atau berhenti seketika.
#### KEPUTUSAN PEMILIK 2026-08-19 — ini yang membuka entri ini

| | |
|---|---|
| Satuan yang dijual | **plafon harian**, +5 analisis/hari |
| Harga | **Rp 15.000 per bulan**, berulang, seragam antar paket |
| Pola | **persis seat** — komponen bulanan di `issueDuePeriodInvoices()`, panel beli/lepas di halaman langganan, pelepasan berlaku satu periode penuh ke depan |
| Model AI | **tetap bawaan** (`gpt-4o-mini` via SumoPod). Tidak ada kuota yang berbeda per model |

Ini menjawab ketiga pertanyaan di atas sekaligus: satuannya plafon harian (bukan kredit), harganya Rp 15.000 seragam (bukan tangga per paket), dan pelepasannya mengikuti pola seat (bukan berhenti seketika).

**Keputusan ini menolak saran (1) di bawah, dan penolakannya sadar.** Saran itu menganjurkan paket kredit karena plafon bulanan menanggung paparan 30× untuk kebutuhan yang cuma muncul beberapa hari. Pemilik memilih keseragaman pola pembelian — satu panel, satu cara melepas, satu komponen tagihan — di atas penghematan itu. Yang harus disadari dan tidak boleh dilupakan saat menulis kalimat di layarnya: **sebagian pembeli akan membayar Rp 15.000 untuk kapasitas yang mereka pakai beberapa hari saja.**

**Marginnya tetap aman, dan itu yang membuat penolakan di atas tidak berbahaya.** 150 analisis/bulan × ± Rp 9 = ± Rp 1.350 ongkos atas Rp 15.000 pendapatan — 91% bahkan bila kuotanya dihabiskan tiap hari. **Tapi hanya pada model sekelas `gpt-4o-mini`;** lihat tabel ongkos di bawah sebelum mengganti `AI_SUMOPOD_MODEL`.

**Yang sengaja TIDAK dibangun, atas pertimbangan pemilik bahwa ia berlebihan:** penghitungan pemakaian yang berbeda per model. Satu satuan ("satu analisis") dipertahankan apa adanya, apa pun model yang kebetulan dipakai di belakang. Akibatnya seluruh risiko perubahan model ditanggung sisi kita, bukan tenant — itu benar untuk tenant, dan justru karena itu batas modelnya harus dijaga di sisi kita.

**Sisa pekerjaan yang disebut pemilik dan belum dikerjakan:** menuliskan daftar model AI yang tersedia. Nama-nama yang beredar di percakapan belum diverifikasi terhadap katalog SumoPod maupun harga resminya; sampai daftar itu ada dan ongkos tiap barisnya dihitung dengan cara yang sama seperti tabel di bawah, `config/ai.php` tetap satu model per provider. Menuliskan nama model yang belum diperiksa ke config berarti menawarkan pilihan yang bisa gagal di panggilan pertama.

- **Usulan Perbaikan:**
  **(a)** Ikuti pola seat apa adanya, jangan menemukan pola kedua: kolom hak di `subscriptions` (bukan paket baru per tenant — `Plan::limits` sudah JSON, tapi melahirkan paket per tenant akan meledakkan tabel paket), komponen tambahan di `issueDuePeriodInvoices()` yang ikut masuk `pricing_context.billing_breakdown`, dan panel beli/lepas di halaman langganan.
  **(b)** `AiQuota::dailyLimitFor()` mendapat satu tingkat baru **di atas** paket: kuota yang dibeli langganan, lalu batas paket, lalu bawaan platform. Urutan pembacaannya sudah tunggal dan tetap (`[BL-047]`(b)) — tambahkan tingkatnya di sana, jangan bikin pembaca kedua.
  **(c)** Sebutkan konsekuensinya di layar seperti yang diminta `[BL-067]`(d): apa yang terjadi saat kuota habis (analisis ditolak sampai besok), dan bahwa BYOK melepas batasnya sama sekali.
  **(d) Jangan menjanjikannya di landing sebelum (a) ada** — `[BL-067]`(e).

#### Ongkos sebenarnya — diukur, bukan diperkirakan (2026-08-08)

SumoPod meneruskan harga API resmi tiap provider (perannya perantara, bukan penjual paket), jadi ongkos per analisis bisa dihitung persis dari pemakaian yang sudah tercatat.

**Pengukuran.** `ai_analyses.tokens_used` menyimpan `usage.total_tokens` untuk 8 analisis yang pernah selesai: **1.487–1.932 token, rata-rata ±1.800**. Dari panjang `result` (523–2.315 karakter) pecahannya kira-kira **1.300 masukan / 550 keluaran**.

**Yang paling penting dari pengukuran itu:** token TIDAK tumbuh mengikuti jumlah transaksi. `Kopi Nusantara` (3.367 transaksi, 4 produk) dan `Kopi Story` (4.476 transaksi, 20 produk) sama-sama di kisaran 1.850 token, karena `AiContextService` mengirim data yang **sudah diagregasi** — `top_products` di-`take(10)`, `daily_trend` sepanjang periode, bukan transaksi mentah.
Yang **tumbuh** adalah `profit_by_item`: satu baris per produk, tanpa batas. Selisih 4 → 20 produk hampir tak terasa karena prompt tetapnya mendominasi, tapi tenant dengan 200 produk akan menambah kira-kira 5.000–8.000 token masukan — **tiga sampai empat kali lipat**. Kalau butir (a) dikerjakan, batasi `profit_by_item` lebih dulu; kalau tidak, harga yang ditetapkan hari ini akan salah untuk tenant terbesar, yaitu justru yang paling mungkin membeli.

**Ongkos per analisis** pada bentuk hari ini (1.300 masuk / 550 keluar), kurs asumsi Rp 16.500/USD:

| Model | $/1M masuk | $/1M keluar | per analisis |
|---|---|---|---|
| `qwen3.7-flash` | 0,03 | 0,13 | **± Rp 2** |
| `gpt-5-nano` | 0,05 | 0,40 | ± Rp 5 |
| `deepseek-v4-flash` | 0,14 | 0,28 | ± Rp 6 |
| **`gpt-4o-mini` (dipakai sekarang)** | 0,15 | 0,60 | **± Rp 9** |
| `gemini-3.1-flash-lite` | 0,25 | 1,50 | ± Rp 19 |
| `gpt-5-mini` | 0,25 | 2,00 | ± Rp 24 |
| `claude-haiku-4-5` | 1,00 | 5,00 | ± Rp 67 |
| `claude-sonnet-5` | 2,00 | 10,00 | ± Rp 134 |

**Temuan yang paling menentukan, dan tidak ada hubungannya dengan harga jual kuota: yang menentukan untung-rugi fitur AI adalah PILIHAN MODEL, bukan harga kuotanya.** Rentangnya 60× dari ujung ke ujung. Paparan maksimum tiap paket bila jatah hariannya dipakai habis 30 hari:

| Paket | Jatah | Maks/bulan | @ `gpt-4o-mini` | @ `claude-sonnet-5` |
|---|---|---|---|---|
| `free` (Rp 0) | 5/hari | 150 | Rp 1.350 | Rp 20.100 |
| `paid-1` (Rp 100.000) | 15/hari | 450 | Rp 4.050 (4%) | Rp 60.300 (60%) |
| `paid-2` (Rp 150.000) | 30/hari | 900 | Rp 8.100 (5%) | Rp 120.600 (80%) |
| `paid-3` (Rp 200.000) | 60/hari | 1.800 | Rp 16.200 (8%) | **Rp 241.200 — melebihi langganannya** |

Pada model sekarang jatah yang sudah terpasang aman di semua paket. Pada model kelas Sonnet, `paid-3` merugi meski tak seorang pun membeli kuota tambahan. **Periksa ini sebelum mengganti `AI_SUMOPOD_MODEL`,** bukan sesudahnya.

#### Saran bentuk, kalau butir (a) jadi dikerjakan

**(1) Jual PAKET KREDIT, jangan menaikkan plafon harian.** Menjual "+10/hari" berlangganan bulanan berarti menanggung paparan 30× plafonnya sementara hampir semua tenant memakainya beberapa hari saja — jadi harganya harus dipatok untuk kasus terburuk dan semua orang kemahalan. Paket kredit ("+100 analisis, berlaku sebulan") berbiaya persis sebanyak yang terpakai.
Alasan kedua lebih kuat: plafon harian **tidak menjawab masalahnya**. Tenant menabrak batas pada SATU hari sibuk — tutup bulan, rapat dengan pemodal — bukan tiap hari. Menaikkan plafon 30 hari untuk menyelamatkan satu hari adalah bentuk yang salah.

**(2) Pertimbangkan serius untuk TIDAK menjualnya sama sekali.** Dengan ongkos ± Rp 9/analisis, paket 100 analisis berongkos ± Rp 900; dijual Rp 10.000 marginnya 90% tapi **pendapatannya nyaris nol** — sepuluh tenant yang membeli tiap bulan menghasilkan Rp 100.000, kurang dari satu langganan. Yang dibayar untuk itu: kolom baru, komponen tagihan, alur beli, jalan melepas, layar, dan tes.
Jatah AI per paket (5/15/30/60) **sudah** jadi pembeda paket yang bekerja — tenant yang kurang kuota punya alasan naik ke `paid-2`, dan itu Rp 50.000, bukan Rp 10.000. Menjual kuota eceran justru **melemahkan** tangga itu.
Kalau tetap dijual, jual sebagai kenyamanan (satu paket kecil untuk keadaan mendesak), bukan sebagai lini pendapatan — dan jangan naikkan prioritasnya di atas entri yang menyentuh uang sungguhan.

**(3) Kalau dijual, harga yang masuk akal:** paket 100 analisis Rp 10.000–15.000 sekali beli, berlaku sampai akhir periode berjalan. Seragam antar paket, tidak perlu tangga seperti `extra_seat_price` — ongkosnya tidak berbeda per paket, dan tangga yang tak berdasar hanya menambah angka yang harus dijelaskan.

**Catatan kurs.** Seluruh rupiah di atas memakai asumsi Rp 16.500/USD dan **tidak terkunci**. Harga jual berdenominasi rupiah di atas ongkos berdenominasi dolar berarti marginnya menipis sendiri saat rupiah melemah. Pada margin 90% itu tidak berbahaya; pada model kelas Sonnet, di mana marginnya sudah negatif, kurs memperburuk yang sudah rusak.

- **Catatan penutup 2026-08-20 — apa yang benar-benar dikerjakan, dan apa yang tidak.**
  Butir (a) sampai (d) semuanya mendarat: tiga kolom di `subscriptions` yang mencerminkan seat satu lawan satu, komponen bulanan di `issueDuePeriodInvoices()` beserta pecahannya di `pricing_context.billing_breakdown`, satu lapis baru di `AiQuota::baseLimitFor()` tepat di atas paket, panel beli/lepas di halaman langganan, dan konsekuensi kuota habis + jalan keluar BYOK ditulis di layar yang menjual kapasitasnya.

  **Saran bentuk (1) dan (2) di atas tidak dipakai, dan penolakannya bukan kelalaian.** Pemilik memilih plafon harian di atas paket kredit pada 2026-08-19, sadar bahwa sebagian pembeli akan membayar sebulan penuh untuk kapasitas beberapa hari — dan kalimat di panelnya menyebutkan itu apa adanya, bukan menyembunyikannya. Saran (2) ("pertimbangkan untuk tidak menjualnya sama sekali") ikut ditolak oleh keputusan yang sama; yang benar dari saran itu tetap benar, yaitu bahwa pendapatannya kecil, dan karena itu ia dikerjakan sebagai kenyamanan, bukan sebagai lini pendapatan.

  **Prasyarat `profit_by_item` dikerjakan LEBIH DULU, seperti yang entri ini tuntut.** Sekarang berhenti di 20 baris (`ai.context.profit_by_item_limit`), sisanya jadi satu baris agregat. Tanpa itu, harga Rp 15.000 ditetapkan atas ongkos yang tidak punya atap untuk tenant terbesar.

  **Dua batas yang tidak disebut entri ini tapi ikut lahir:** atap `max_blocks` (20), karena plafon harian yang dibeli tidak pernah ditinjau ulang siapa pun sesudahnya dan satu salah ketik akan menetap sampai ada yang menyadarinya; dan `purchasedAiDailyQuota()` sebagai satu-satunya tempat yang tahu satu blok berarti berapa analisis, supaya mengubah `block_size` tidak mengubah arti angka yang sudah tersimpan.

  **Yang TETAP terbuka dan bukan bagian entri ini:** daftar model AI yang tersedia belum diverifikasi ke katalog SumoPod. Tabel ongkos di atas tetap berlaku dan tetap harus dibaca sebelum `AI_SUMOPOD_MODEL` diganti — bukan sesudahnya. Pada model kelas Sonnet, `paid-3` merugi meski tak seorang pun membeli kuota tambahan, dan harga Rp 15.000/+5 hanya sah selama modelnya sekelas `gpt-4o-mini`.

---

### [BL-078] Testimoni di Landing Berdiri di Atas Nama dan Potret yang Tidak Bisa Dipertanggungjawabkan
- **Ditemukan:** 2026-08-14
- **Sumber:** Temuan saat mengerjakan `[BL-032]` butir (1) — empat klaim lain di halaman yang sama terbukti karangan dan dibuang; bagian ini sengaja ditahan karena keputusannya bukan teknis
- **Status:** **Selesai 2026-08-20.** Dijawab pemilik: ketiga kutipan itu **karangan**, dan jalan yang dipilih adalah opsi (2) yang kedua — bagiannya diganti, bukan dihapus
- **Status semula:** Open — **butuh keputusan pemilik**, bukan butuh implementasi
- **Prioritas:** Medium — tidak ada angka yang salah dan tidak ada yang bocor, tapi ia satu-satunya sisa karangan di permukaan pertama yang dilihat calon klien
- **Area Terdampak:**
  - `resources/views/public/landing.blade.php` — tiga blok testimoni: Andi (kafe), Santi, Budi, masing-masing dengan kutipan bertanda kutip
  - `public/avatar_{andi,budi,santi}.webp` — potretnya; sudah dikecilkan jadi ±2 KB oleh `[BL-032]`(3), tapi bobot bukan persoalan entri ini
- **Deskripsi:**
  Tiga kutipan bernama lengkap dengan wajah, dan tidak ada satu pun pelanggan bernama itu. Basis data berisi dua tenant, keduanya demo (`Kopi Nusantara`, `Kopi Story`). Potretnya foto stok — bukan orang yang pernah memakai aplikasi ini.
  Kenapa ini dipisah dari `[BL-032]`: empat klaim lain di halaman yang sama (login Google, katalog otomatis, "AI mengenali kebutuhan bisnis", "ribuan UMKM") dibuang karena **bisa diperiksa ke kode** — ada atau tidak ada, dan jawabannya tidak ada. Testimoni tidak begitu. Ia bisa saja mewakili percakapan nyata dengan calon klien yang namanya disamarkan, dan itu praktik yang lazim. Yang tidak bisa saya tentukan sendiri adalah mana dari keduanya.
- **Yang perlu diputuskan pemilik:**
  1. **Apakah ketiga kutipan itu berasal dari orang nyata?** Bila ya, cukup ganti potret stoknya dengan sesuatu yang tidak mengaku-aku wajah orang — inisial, ilustrasi, atau logo usahanya — dan tambahkan keterangan bahwa namanya disamarkan.
  2. **Bila tidak**, ada dua jalan jujur: hapus bagiannya sampai ada pengguna sungguhan yang bersedia dikutip, atau ganti jadi bagian yang tidak mengaku sebagai kesaksian — misalnya "untuk siapa aplikasi ini dibuat", yang menyampaikan hal yang sama tanpa mengarang orang.
- **Kenapa tidak dikerjakan sekalian:** menghapus testimoni adalah keputusan pemasaran, dan menggantinya dengan karangan yang lebih halus justru memperburuk. Keduanya milik pemilik, bukan konsekuensi teknis dari entri mana pun.
- **Hasil:** Bagian "Testimoni — Cerita Sukses Bersama SAPI" dicabut seluruhnya dan digantikan **"Untuk Siapa SAPI Dibuat"**: tiga kartu persona (Kafe & Kedai Kopi, Toko Kelontong & Retail, Usaha dengan Beberapa Kasir) yang menyebut kemampuan yang benar-benar ada, tanpa satu pun nama orang, wajah, atau tanda kutip. Ketiga potret stoknya dihapus dari `public/`.
- **Satu hal yang berubah saat dikerjakan, dan bukan bagian dari keputusan pemasarannya:** dua kemampuan yang hampir ikut ditulis di kartu persona ternyata **tidak ada wujudnya** — "prediksi stok menipis" (yang ada ambang tetap: `BadgeHelperService` menandai stok ≤ 5, habis, dead stock 30 hari, dan kedaluwarsa — bukan ramalan) dan "barcode" (`product_variants` punya `sku`, dan pencarian POS hanya mencocokkan nama produk & varian). Keduanya diganti sebutan yang jujur sebelum mendarat. Bagian yang dibuat untuk berhenti mengarang nyaris lahir membawa karangan barunya sendiri.
- **Entri penutup di `docs/CHANGELOG.md`:** `[DEPRECATE] Testimoni Karangan Dicabut, Diganti Bagian yang Tidak Mengaku Sebagai Kesaksian (BL-078)`

### [BL-031] Umur Tagihan Terbuka Belum Pernah Diputuskan — Sesi Kas, Per Hari, atau Sampai Dilunasi?
- **Ditemukan:** 2026-07-31
- **Sumber:** Pertanyaan pemilik saat `[BL-023]` selesai — "apakah tagihan atau open bill itu hidup berdasarkan waktu hidup kas / shift kasir atau per hari atau sampai diselesaikan"
- **Status:** **Selesai 2026-08-20.** Keputusan pemilik 2026-08-19 (per hari + kas negatif) dilaksanakan seluruhnya: keenam butir "yang harus ikut dibangun" mendarat. Satu catatan di teks lama sengaja TIDAK diikuti — butir 2 "Usulan Perbaikan" (*pemulihan stok wajib pada jalur pembatalan mana pun*) memang tidak berlaku untuk jalur 24 jam, persis seperti keputusan pemilik menyatakannya; stok baru kembali di jalur penghapusan oleh pemilik, dan jalur itu ikut dibangun di sini
- **Status semula:** Open — **keputusannya SUDAH diambil 2026-08-19 (per hari + kas negatif), implementasinya belum ada.** Yang tersisa murni pekerjaan kode
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
#### KEPUTUSAN PEMILIK 2026-08-19 — per hari, dan yang lewat jadi kas negatif

Umur tagihan terbuka ditetapkan **per hari**: 24 jam setelah transaksinya tercatat, tagihan yang belum dilunasi berhenti menjadi tagihan hidup dan **dicatat sebagai kas negatif**. Yang boleh membereskannya **hanya owner**, dan hanya dari **dashboard transaksi owner** — bukan dari menu log transaksi kasir.

**"Jadi kas negatif" bukan sama dengan "dibatalkan", dan perbedaannya mengubah satu catatan di entri ini.** Membatalkan akan memulihkan stok dan menghapus jejak uangnya — bersih di pembukuan, tapi bohong: barangnya sudah keluar dan dibawa pelanggan. Karena itu **stok TIDAK dipulihkan** pada jalur ini, dan butir 2 "Usulan Perbaikan" di bawah (*"pemulihan stok wajib ikut pada jalur pembatalan mana pun"*) **tidak berlaku untuk jalur 24 jam ini** — ia ditulis dengan asumsi jalurnya pembatalan. Butir itu tetap berlaku bila kelak ada jalur pembatalan sungguhan, yaitu ketika owner memutuskan sebuah tagihan memang tak akan pernah dibayar; di sanalah stok kembali, dan di sana pula kas negatifnya ditutup.

**Kenapa hanya owner.** Kas negatif adalah selisih yang harus dipertanggungjawabkan; membiarkan kasir menyuntingnya berarti orang yang bertanggung jawab atas selisih itu juga yang bisa merapikannya. Ini melanjutkan garis yang sudah ada — `canEditTransaction()` sudah menolak kasir menyunting transaksi di luar sesi lacinya yang terbuka — dan yang ditambahkan keputusan ini adalah **tempat** suntingan itu boleh terjadi.

**Yang harus ikut dibangun, dan belum ada satu pun:**
1. Tugas terjadwal di `routes/console.php` yang memindahkan tagihan >24 jam ke kas negatif. Hari ini tidak ada satu pun tugas yang menyentuh transaksi `pending`.
2. **Tugas itu wajib sekaligus melepaskan tagihannya dari papan dapur.** Kalau tidak, timbunan `fulfillment_status = waiting` cuma berpindah sumber — persis yang pernah dibersihkan migrasi `backfill_stale_fulfillment_status`.
3. Penampung kas negatif, dan tempatnya muncul di rekonsiliasi kas (`CashDrawerReconciliation`).
4. Jalur sunting di dashboard transaksi owner.
5. Pembatas umur di `HandleInertiaRequests::openBillsFor()`, yang sekarang menyaring `user_id` + `status pending` tanpa batas waktu apa pun.
6. Kalimat di UI kasir yang menyebut tagihannya bertahan sampai kapan — butir 4 "Usulan Perbaikan" di bawah, yang keputusan ini justru menjadikannya wajib.

**Satu hal yang harus diputuskan di kodenya, bukan disimpulkan dari sini:** 24 jam dihitung dari `occurred_at`, bukan `created_at`. `[BL-028]` cacat #3 sudah membuktikan `created_at` melempar penjualan offline ke hari sinkronisasinya, dan `Transaction::scopeWhereEffectiveBetween()` sudah ada untuk itu — tapi itu perlu tertulis di kodenya.

**Yang TIDAK berubah:** uangnya tetap milik laci yang MELUNASI (`[BL-028]`). Yang berubah hanya jendela hidupnya — sesudah 24 jam tidak ada laci mana pun yang akan menerimanya.

- **Usulan Perbaikan:**
  1. **Putuskan dulu, catat di `CHANGELOG.md` sebagai `[DECISION]`.** Ini aturan operasional, bukan detail teknis — pilihannya menentukan apakah stok bisa tersandera semalaman.
  2. Apa pun pilihannya, **pemulihan stok wajib ikut** pada jalur pembatalan mana pun. Membatalkan tagihan tanpa mengembalikan stok menukar satu masalah dengan masalah yang lebih sulit dilihat.
  3. Kalau jatuhnya "per sesi kas", sambungkan ke `[BL-028]`: tutup kas adalah tempat paling wajar untuk memaksa keputusan atas tagihan yang menggantung.
  4. Pertimbangkan menyatakan ini di UI apa pun keputusannya — kasir yang menekan "Tunda Bayar" berhak tahu tagihannya bertahan sampai kapan.
- **Catatan:** per 2026-07-31 database **tidak punya satu pun transaksi `pending`**, jadi keputusan ini masih bisa diambil tanpa memigrasikan apa pun. Jendela itu akan tertutup begitu fitur Tunda Bayar benar-benar dipakai.
### [BL-080] Tagihan Terbit Sebelum Omzet Bulan Sebelumnya Dihitung — Tenant Berjangkar Tanggal 1–8 Ditagih dari Omzet Dua Bulan Lalu
- **Ditemukan:** 2026-08-19 (saat menuliskan keputusan `[BL-056]`; bukan dilaporkan, melainkan terlihat begitu ada aturan untuk mengukurnya)
- **Sumber:** Turunan `[BL-056]`. Keputusan "tarif periode P dari omzet bulan sebelum P" bisa diperiksa terhadap kode, dan hasilnya: ia hanya berlaku untuk sebagian tenant
- **Status:** **Selesai 2026-08-20.** Butir (a) menukar urutan jadwal; butir (b) dijawab pemilik dengan **opsi (i)** — tunda penerbitan sampai ringkasannya ada — dan butir (c) ikut dikerjakan karena opsi (i) menjadikannya prasyarat, bukan pertimbangan. Keluarga jangkar (opsi (v)/(vi)) **ditolak pemilik seluruhnya**: tanggal tagih tidak digeser dari tanggal daftar. Opsi **(iv-b)** yang sempat dicondongi dipisah jadi  setelah terlihat ia menuntut versi consent baru dan persetujuan ulang setiap tenant Adaptif. Teks di bawah dipertahankan apa adanya sebagai riwayat pertimbangannya.
- **Status semula:** Open — **butir (a) SELESAI 2026-08-20**, butir (b) butuh keputusan pemilik dan butir (c) menunggu (b). Daftar pilihan butir (b) **diperluas 2026-08-20** dari tiga jadi delapan, setelah pemilik menunjuk akar yang belum dinamai entri ini (lihat "Akar sebenarnya" di bawah); tiga yang lama semuanya ternyata satu keluarga. Sengaja dipisahkan dari `[BL-056]` supaya keputusan komersial di butir (b) tidak menyelundup di balik perbaikan penjadwalan di butir (a)
- **Prioritas:** **High** — ini angka uang yang salah, bukan tampilan. Berbeda dari `[BL-056]` yang cuma butuh jawaban, ini butuh kode. Meredam sendiri hanya karena belum ada tenant Adaptif berbayar; tidak ada apa pun yang akan memberi tanda pada hari pertama ada. Sesudah butir (a), yang tersisa menyempit ke jangkar tanggal **1–7** saja — tanggal 8 sudah ikut benar
- **Area Terdampak:**
  - `routes/console.php:26` — `subscriptions:advance-lifecycle` harian **03:30**
  - `routes/console.php:45` — `subscriptions:compute-revenue` tanggal 1 **02:40** sejak butir (a); sebelumnya **04:00**, tiga puluh menit SESUDAHNYA
  - `app/Services/SubscriptionService.php` — `advanceLifecycle()` memanggil `issueDuePeriodInvoices()` di dalamnya
  - `app/Services/SubscriptionService.php:85` — `pricingAsOf()`: awal bulan periode tagihan
  - `app/Services/Pricing/MonthlyRevenueResolver.php:32` — `metricFor()`: ringkasan **terbaru** dengan `period <= asOf`, bukan ringkasan bulan tertentu
  - `config/subscription.php:74` — `invoice_lead_days = 7`
  - `app/Models/Subscription.php:197` — `billingAnchorDay()`: jangkar diturunkan dari tanggal daftar; `anchoredDateIn()`/`nextAnchoredDateAfter()` memutar periodenya
  - `database/migrations/2026_07_24_195639_create_tenant_monthly_metrics_table.php` — `period` `char(7)` `YYYY-MM`, unik `(tenant_id, period)`, retensi dipangkas dalam satuan BULAN
  - `app/Jobs/ComputeTenantMonthlyRevenue.php:68` — `recordFor()`: jalur hitung SATU tenant seketika, sudah ada sejak `[BL-055]`(b)
- **Deskripsi — dua hal bertumpuk, dan masing-masing tidak cukup untuk menimbulkannya sendiri:**
  1. **Urutan jadwal terbalik.** Penerbit tagihan tinggal di dalam `advanceLifecycle()` (03:30 harian). Penghitung omzet berjalan 04:00 tanggal 1. Setiap tanggal 1, penerbit berjalan **sebelum** omzet bulan yang baru tutup pernah ditulis ke `tenant_monthly_metrics`.
  2. **Tagihan terbit H-7, sementara jangkar tagih diambil dari tanggal daftar.** Tenant berjangkar tanggal 7 mendapat tagihan periode Septembernya diterbitkan **31 Agustus** — saat Agustus bahkan belum tutup, jadi ringkasannya tidak mungkin ada.

  Karena `metricFor()` mengambil ringkasan **terbaru** yang periodenya tidak melewati bulan itu (bukan ringkasan bulan tertentu), yang didapat bukan error melainkan **angka bulan sebelumnya lagi** — diam-diam, tanpa satu pun tanda.

  | Jangkar tagih | Tagihan periode Sep terbit | Ringkasan Agustus sudah ada? | Omzet yang dipakai |
  |---|---|---|---|
  | tanggal 1 | 25 Agustus | belum | **Juli** |
  | tanggal 7 | 31 Agustus | belum | **Juli** |
  | tanggal 8 | 1 Sep, 03:30 | ~~belum (ditulis 04:00)~~ → sudah (ditulis 02:40) | ~~**Juli**~~ → Agustus ✓ sejak butir (a) |
  | tanggal 9 | 2 September | sudah | Agustus ✓ |
  | tanggal 20 | 13 September | sudah | Agustus ✓ |

  Jadi tenant berjangkar **tanggal 1–8** ditagih dari omzet **dua bulan** sebelumnya; tanggal 9 ke atas benar. Tidak ada satu pun tempat di kode yang menyatakan pembedaan ini, dan tidak ada yang pernah memutuskannya. **Sejak butir (a) selesai (2026-08-20), jangkar tanggal 8 ikut benar** — sisanya, tanggal 1–7, tidak tersembuhkan oleh urutan jadwal dan menunggu butir (b).
- **Kenapa ini menggigit lebih keras daripada kelihatannya:** contoh yang dipakai pemilik sendiri saat memutuskan `[BL-056]` — **daftar 7 Juli** — mendarat tepat di jendela yang salah. Tagihan Septembernya memakai omzet Juli, dan bagi tenant itu Juli bahkan **bukan bulan penuh**: ia baru berjualan sejak tanggal 7. Omzetnya jadi terlalu rendah karena dua sebab sekaligus, bracket Adaptifnya terlalu murah, dan pada tangga 90/75/50/25% selisih satu bracket adalah **Rp 25.000/bulan**.
- **Arahnya bisa dua-duanya, dan itu penting:** bulan yang lebih tua bisa lebih rendah (tenant baru yang sedang tumbuh → kita menagih kurang) maupun lebih tinggi (tenant yang penjualannya turun → tenant menagih lebih dari yang seharusnya, dan itu yang akan diadukan).
- **Dugaan Penyebab:** `invoice_lead_days` dan jadwal penghitung omzet ditetapkan pada waktu berbeda untuk alasan berbeda, dan tidak ada satu pun tempat yang menyatakan hubungan antar keduanya. `config/subscription.php` sudah menyatakan satu hubungan semacam itu secara eksplisit (`trial_choice_lead_days` **wajib lebih besar dari** `invoice_lead_days`, lengkap dengan alasannya) — hubungan yang satu ini tidak pernah ikut ditulis.
- **Akar sebenarnya — DUA JAM yang tidak pernah disuruh sepakat (ditunjuk pemilik 2026-08-20):** entri ini semula menulis penyebabnya sebagai dua jadwal yang salah urutan. Itu benar tapi terlalu dangkal. Yang sesungguhnya bertabrakan adalah dua penanggalan:
  - **Jam tenant — berputar, per-tenant.** `billingAnchorDay()` diturunkan dari tanggal daftar; periode berjalan dari tanggal D bulan M ke tanggal D bulan M+1; tagihan terbit H-7 dari ujung itu. Jadi H-7 **memang** dihitung dari tanggal daftar, persis seperti dugaan pemilik.
  - **Jam kalender — serentak, global.** `tenant_monthly_metrics.period` berformat `YYYY-MM`, ditulis sekali sebulan atas bulan yang baru tutup, dan dipangkas dengan retensi dalam satuan bulan.

  Kalimat `[BL-056]` — "tarif periode P dari omzet bulan sebelum P" — ditulis dalam bahasa **kalender**, tapi dieksekusi pada momen yang ditentukan jam **tenant**. Untuk jangkar tanggal 9+ keduanya kebetulan berimpit; untuk 1–7 tidak, dan tidak akan pernah, berapa pun jamnya digeser. Inilah sebabnya butir (a) menyembuhkan jangkar tanggal 8 tapi berhenti di situ.

  Pentingnya bagi butir (b): tiga pilihan yang semula ditulis di sini — (i), (ii), (iii) — **seluruhnya satu keluarga**, yaitu "biarkan dua jam itu, geser sambungannya". Keluarga lain yang menyamakan jamnya tidak pernah ikut dipertimbangkan.
- **Angka populasi per 2026-08-20, karena ia mengubah ongkos beberapa pilihan:** basis data berisi **2 tenant, 1 di jalur Adaptif, 1 baris `tenant_monthly_metrics`, 2 tagihan langganan**. Belum ada tenant Adaptif berbayar yang terdampak. Pilihan yang bersifat "perbaiki ke depan" (terutama **(vi)**) tidak akan pernah semurah hari ini — tiap tenant Adaptif berbayar yang mendaftar sejak sekarang menaikkan harganya, dan kenaikan itu tidak bisa ditarik kembali.
- **Usulan Perbaikan:**
  **(a) ~~Tukar urutan jadwalnya.~~ SELESAI 2026-08-20** — `compute-revenue` digeser ke tanggal 1 pukul **02:40**, lima puluh menit sebelum `advance-lifecycle`; ketergantungannya ditulis sebagai komentar di kedua sisi `routes/console.php`, dan dijaga `tests/Feature/Subscription/ScheduleOrderTest.php` (4 tes). Ongkos yang ikut tercatat di sana: jarak dari tengah malam menyusut dari empat jam ke 2 jam 40 menit, jadi sinkronisasi offline yang lebih larut dari itu terlewat dan butuh `--period`. Rencana aslinya, untuk rujukan: `subscriptions:compute-revenue` harus berjalan **sebelum** `subscriptions:advance-lifecycle`, bukan sesudah. Ini cacat murni tanpa sisi komersial: geser penghitung omzet ke sebelum 03:30 (mis. tanggal 1 pukul 02:40), atau geser `advance-lifecycle` ke sesudah 04:00. Yang **pertama** lebih baik — `advance-lifecycle` sengaja berjalan sebelum jam buka warung supaya tenant yang jatuh ke tenggat mengetahuinya di awal hari, dan alasan itu tidak boleh dibuang untuk memperbaiki yang lain. Sertakan komentar yang menyebut ketergantungannya, seperti yang sudah dilakukan `config/subscription.php` untuk `trial_choice_lead_days`; urutan yang benar tanpa alasan tertulis adalah urutan yang akan digeser lagi oleh orang berikutnya.
  **(b) Butuh keputusan pemilik: apa yang terjadi pada tenant berjangkar tanggal 1–7.** Butir (a) sendiri **tidak** menyelesaikannya — untuk jangkar tanggal 1–7 tagihannya memang terbit sebelum bulan sebelumnya tutup, jam berapa pun penghitungnya berjalan. Delapan pilihan dalam **empat keluarga**; keluarga menentukan APA yang ditukar, pilihan di dalamnya menentukan seberapa jauh.

  ***Keluarga 1 — biarkan dua jamnya, geser sambungannya.*** Yang termurah, dan semuanya menyisakan akarnya utuh.
  - **(i) Tunda penerbitan sampai ringkasannya ada.** Tagihan tetap memakai omzet bulan sebelumnya sebagaimana `[BL-056]`. Paling jujur terhadap aturan harganya; ongkosnya waktu bersiap tenant. **Angkanya, dihitung dari kode 2026-08-20** — `due_date` = hari periode berjalan habis, dan penundaan TIDAK menggesernya, jadi yang menyusut hanya masa siap: jangkar 1 → **0 hari** (tagihan tiba 03:30 di hari yang sama ia jatuh tempo, dan tanggal 2 tenant sudah masuk masa tenggang), jangkar 2 → 1 hari, … jangkar 7 → 6 hari, jangkar 8+ → tetap 7. Empat syarat yang membuatnya tidak berbahaya, dan opsi ini **tidak boleh dikerjakan tanpa keempatnya**:
    1. **Butir (c) jadi WAJIB, bukan opsional.** Tanpa resolver yang tegas soal bulan, kode ini tidak punya cara membedakan "ringkasan Agustus belum ada" dari "ini ringkasan Juli" — jadi tidak ada sinyal untuk memicu penundaan sama sekali.
    2. **Harus ada batas waktu.** "Tunda sampai ada" berubah jadi "tidak pernah ditagih" bila ringkasannya tidak pernah datang. Jendelanya nyata: gerbang consent di `ComputeTenantMonthlyRevenue` berhenti menulis ringkasan begitu consent dicabut, sementara kolom `pricing_track` baru berubah di akhir periode — di sela itu tenant masih Adaptif tapi metriknya sudah mati. Bila sampai hari tenggat ringkasannya belum ada, terbitkan dengan aturan cadangan tertulis, atau naikkan jadi hal yang menuntut tindakan manusia; `unpriced` yang cuma masuk log tidak ditonton siapa pun.
    3. **Penundaannya harus BERSYARAT.** Hanya tenant jalur Adaptif yang butuh ringkasan itu. Menunda semua tenant mencabut masa siap tenant jalur normal tanpa menukar apa pun.
    4. **Perhatikan penumpukan.** Seluruh tagihan jangkar 1–7 jadi terbit dalam satu jalannya tanggal 1, bareng batch jangkar 8.

    Yang perlu diakui terang-terangan bila ini dipilih: ketidakadilan berbasis tanggal daftar **tidak hilang**, ia pindah dari "omzet bulan mana" ke "berapa hari peringatan". Lebih ringan, tapi bukan nol. Peredamnya nyata — masa tenggang di sini bertingkat dan lunak (kasir hidup sampai hari ke-20) — jadi telat sehari bukan warung mati. Satu hal yang menguntungkan dan mudah terlewat: menerbitkan LEBIH LAMBAT dari H-7 justru **melebarkan** jarak `trial_choice_lead_days` > `invoice_lead_days`, jadi opsi ini tidak bisa merusak invariant itu.
  - **(ii) Pakai omzet bulan sebelumnya lagi, dan NYATAKAN itu.** Tidak ada kode yang berubah selain dokumentasi dan kalimat di layar. Paling murah, tapi berarti aturan `[BL-056]` punya pengecualian permanen yang bergantung tanggal daftar — sesuatu yang tenant tidak pilih dan tidak bisa ubah.
  - **(iii) Turunkan `invoice_lead_days`** sehingga tak ada tagihan yang pernah terbit sebelum bulan sebelumnya tutup. Menyeragamkan semua tenant, tapi menyentuh `trial_choice_lead_days` yang wajib lebih besar darinya — jadi ia menggeser dua angka, bukan satu.

  ***Keluarga 2 — samakan jamnya di sisi METRIK.*** Satu-satunya keluarga yang menghapus akarnya, bukan menambalnya.
  - **(iv) Metrik mengikuti periode langganan, bukan bulan kalender.** Yang dihitung bukan "omzet Agustus" melainkan "omzet periode langganan yang baru tutup" — 7 Juli s/d 7 Agustus bagi tenant jangkar 7 — dan dihitung saat dibutuhkan, bukan menunggu tanggal 1. Ini yang paling setia pada makna prabayar: tenant membayar periode berikutnya berdasarkan periode sebelumnya **miliknya sendiri**, bukan berdasarkan potongan kalender yang kebetulan berdekatan. Semua tenant dapat 7 hari penuh, tak ada yang ditagih dari data basi, tak ada pengecualian. **Lebih murah dari kelihatannya:** jalur hitung-satu-tenant-seketika sudah ada — `recordFor()`, dibuat untuk `[BL-055]`(b) supaya penilaian pengajuan Adaptif tidak menunggu jadwal bulanan. Ongkosnya jujur dan tidak kecil: kunci unik `(tenant_id, period)` kehilangan maknanya karena `period` bukan lagi label bulan; pemangkasan retensi 24 bulan dan `--period YYYY-MM` sama-sama berbicara bahasa bulan; halaman platform membandingkan tenant lewat ringkasan bulanan, dan bila tiap tenant punya "bulan" sendiri perbandingan itu jadi apel lawan jeruk — padahal bracket Adaptif ADALAH perbandingan; dan jendelanya sedikit lebih banyak mengungkap ritme tenant (kapan ia daftar), meski angkanya tetap agregat.
  - **(iv-b) Varian ringan: jendela 30 hari yang selalu penuh.** Tarif diambil dari 30 hari terakhir per tanggal terbit — bagi jangkar 1 yang terbit 25 Agustus, jendelanya 26 Juli–25 Agustus. Selalu ada, selalu segar, masa siap 7 hari untuk semua orang, dan perbandingan antar-tenant tidak rusak separah (iv) karena panjang jendelanya seragam. Tapi ia **mengubah kalimat aturannya**: "bulan sebelumnya" jadi "30 hari terakhir". Itu keputusan komersial yang mengubah `[BL-056]`, bukan perbaikan teknis, dan harus diperlakukan begitu.

  ***Keluarga 3 — samakan jamnya di sisi JANGKAR.***
  - **(v) Pindahkan semua jangkar ke tanggal aman (mis. 10), dengan proata periode pertama.** Satu jam untuk semua, paling bersih secara teknis, dan **paling tidak disarankan**: `trial_months` sengaja dihitung dalam BULAN justru untuk menjaga jangkar tetap di tanggal daftar (alasannya tertulis di `config/subscription.php`), proata belum ada sama sekali, dan menumpuk seluruh tagihan di satu hari memaksimalkan beban dukungan.
  - **(vi) Batasi hanya jangkar BARU — pendaftar tanggal 1–7 diberi jangkar tanggal 8.** Tidak menyentuh tenant lama, tidak menyentuh proata, tidak menyentuh bentuk metrik. Satu aturan di jalur pendaftaran menutup masalahnya permanen ke depan. Lihat angka populasi di atas: **hari ini ongkosnya praktis nol, dan itu tidak akan bertahan.** Sisi jeleknya, tanggal tagih jadi bergeser dari tanggal daftar bagi sebagian pendaftar — kecil, tapi harus dikatakan di halaman pendaftaran, bukan ditemukan sendiri oleh tenant. Perlu ditegaskan: ini **tidak** menyembuhkan tenant yang sudah ada, jadi ia pasangan bagi salah satu pilihan lain, bukan pengganti.

  ***Keluarga 4 — lepaskan penetapan harga dari saat penerbitan.***
  - **(vii) Terbitkan H-7 dengan nominal SEMENTARA, kunci saat ringkasannya tiba.** Menjaga masa siap 7 hari untuk semua orang. **Dicatat demi kelengkapan, bukan sebagai kandidat:** ia melawan hal yang sudah diputuskan dengan sengaja — nominal dibekukan saat terbit, dan seluruh alasan `trial_choice_lead_days` lebih besar dari `invoice_lead_days` adalah supaya tenant memutuskan sebelum angkanya mengeras. Angka yang bisa berubah setelah terbit merusak keduanya sekaligus.
  - **(viii) Bagian Adaptifnya ditagih di BELAKANG.** Tarif dasar tetap prabayar; selisih akibat bracket omzet masuk sebagai baris koreksi di tagihan berikutnya. Ketergantungan waktunya hilang total — tagihan tidak pernah lagi butuh data yang belum ada. Ongkosnya: tagihan jadi punya baris "koreksi bulan lalu", dan itu pertanyaan dukungan pertama yang akan masuk.

  ***Ringkasan perbandingan:***

  | Opsi | Menyembuhkan jangkar 1–7? | Ongkos utama |
  |---|---|---|
  | (i) tunda terbit | ya | masa siap 0–6 hari; wajib (c) + batas waktu + bersyarat |
  | (ii) pakai bulan lalu lagi, dinyatakan | tidak — dilegalkan | pengecualian permanen per tanggal daftar |
  | (iii) turunkan lead days | ya | menggeser dua angka config, bukan satu |
  | **(iv) metrik ikut periode langganan** | **ya, tanpa pengecualian** | kunci unik, retensi, komparabilitas platform |
  | (iv-b) jendela 30 hari | ya | mengubah kalimat aturan `[BL-056]` |
  | (v) semua jangkar dipindah | ya | proata; melawan alasan `trial_months` |
  | **(vi) jangkar baru dibatasi ≥8** | ke depan saja | tanggal tagih ≠ tanggal daftar; bukan pengganti |
  | (vii) nominal sementara | ya | melawan pembekuan nominal — tidak disarankan |
  | (viii) Adaptif ditagih di belakang | ya | baris koreksi di tagihan |

  ***Arah yang disarankan bila pemilik tidak ingin memilih satu saja:*** **(vi)** dikerjakan sekarang selagi masih gratis untuk menutup kasus baru, lalu **(i)** atau **(iv)** dipilih dengan tenang untuk sisa kasusnya. Yang tidak boleh terjadi adalah menunda keduanya sampai ada tenant Adaptif berbayar — sejak hari itu setiap pilihan jadi lebih mahal, dan (vi) kehilangan seluruh keunggulannya.
  **(c) Apa pun pilihan (b), pertimbangkan menjadikan `MonthlyRevenueResolver` TEGAS soal bulan yang diminta.** Hari ini ia mengambil "yang terbaru sampai `asOf`", dan itulah yang mengubah ringkasan yang hilang menjadi angka yang salah alih-alih ketiadaan yang terlihat. Mengembalikan `null` untuk bulan yang tidak ada akan membuat tagihannya terhitung `unpriced` — tercatat di `PlatformAuditLog` `invoices.unpriced` dan bisa ditindak — alih-alih terbit dengan angka yang tak seorang pun tahu berasal dari bulan lain. **Jangan kerjakan butir ini sebelum (b) diputuskan:** tanpa (b), ia mengubah tagihan yang salah menjadi tagihan yang tidak terbit sama sekali, dan itu belum tentu perbaikan. Perlu dibaca bersama daftar (b): bila pilihannya jatuh ke **(i)**, butir ini berhenti jadi pertimbangan dan menjadi **prasyarat** — opsi (i) tidak punya sinyal apa pun untuk memicu penundaan sampai resolvernya bisa membedakan "belum ada" dari "ada tapi bulan lain".
- **Catatan:** bagian "Prabayar, dan harganya dari bulan lalu" di `README.md` sudah menyatakan aturannya sebagai satu baris tanpa pengecualian. Bila (b) jatuh ke opsi **(ii)**, **(iv-b)**, atau **(viii)**, bagian itu **harus** ikut diperbaiki — aturan yang ditulis lebih bersih daripada perilakunya adalah dokumentasi yang menyesatkan pembacanya sendiri.

---

### [BL-018] Diskon Dinamis Barang Mendekati Habis/Kedaluwarsa dengan Penjaga Margin
- **Ditemukan:** 2026-07-25
- **Sumber:** Saran yang diterima setelah sesi pitching — diskon yang "harganya ditentukan dan menyesuaikan sembari tetap untung, melihat harga modal dan harga jual"
- **Status:** **Selesai 2026-08-19** — poin 1–7 seluruhnya, termasuk seluruh konsekuensi keputusan pemilik 2026-07-29
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

- **CATATAN PENUTUP 2026-08-19 — bentuk yang mendarat.**
  Diskon jadi tabel tersendiri (`discount_rules`), bukan pengubah `product_variants.price`. Lantai harga dihitung `cost_price × (1 + tenants.min_margin_percent/100)` dan dibulatkan KE ATAS ke kelipatan 500. `transaction_items` menyimpan `original_unit_price`, `discount_amount`, `discount_rule_id`, `discount_reason`, `cost_price_at_sale`, `margin_floor_at_sale`, dan `below_floor_approved_by` — enam kolom terakhir itulah yang membuat tiap potongan bisa dipertanggungjawabkan berbulan-bulan kemudian.
- **CATATAN PENUTUP 2026-08-19 — jalur harga ternyata EMPAT, bukan tiga.**
  Poin 7 menyebut tiga (pengeditan transaksi, sinkronisasi offline, katalog offline). Yang keempat: **props POS sendiri**. Server menghitung ulang harganya saat checkout, jadi layar yang menampilkan harga katalog akan menyebut satu angka lalu menagih angka lain — kasir tidak punya cara menjelaskan selisihnya, dan struknya tidak cocok dengan yang barusan ia sebutkan. `effective_price` karena itu ikut di props katalog.
- **CATATAN PENUTUP 2026-08-19 — satu penjaga lama ternyata tidak menjaga apa pun.**
  `StoreTransactionRequest` menjumlahkan total belanja dari `items.*.unit_price` KIRIMAN KLIEN, sehingga klien yang mengirim harga kecil ikut menurunkan ambang yang harus ia bayar. Sekarang dihitung dari harga server. Akibatnya satu test lama berpindah lapisan (galat validasi `payments`, bukan galat flash service) — penolakannya kini terjadi sebelum satu baris pun ditulis.
- **YANG TERSISA, dan ia entri tersendiri: bundling berdiskon — kini `[BL-103]` (dibuka 2026-09-06).**
  Catatan di atas menyebut entri ini sebagai satu-satunya penghalangnya. Penghalang itu hilang: `PressedStockStrategy` kini punya tempat sah untuk harga di bawah katalog. Menyambungkan potongan harga ke kandidat yang sudah ada adalah pekerjaan berikutnya, dan lantai margin **wajib** ikut ditegakkan di sisi saran saat itu dikerjakan — bukan hanya di sisi harga. Entrinya baru dibuat 2026-09-06, empat belas hari sesudah entri ini ditutup: pekerjaan yang disebut tiga kali sebagai "tersendiri" ternyata tidak pernah dibuatkan tempatnya, dan begitu entri ini diarsipkan ia tidak punya rumah di mana pun. `[BL-103]` juga mencatat dua komentar kode yang sampai saat itu masih menunggu entri ini.
- **Entri penutup di `docs/CHANGELOG.md`:** `[ADDITION] Diskon Jadi Entitas Tersendiri, dengan Lantai Untung yang Hanya Manusia Boleh Tembus (BL-018)`

---

### [BL-013] Akun Platform Belum Punya 2FA
- **Ditemukan:** 2026-07-22 (dipisahkan dari `[BL-010]` yang sudah ditutup)
- **Sumber:** Poin ketiga `[BL-010]`, sejak awal ditandai "catat sebagai target, jangan dikerjakan sekarang"
- **Status:** **Selesai 2026-08-19** — TOTP, kode pemulihan, dan pencatatan `sensitive`-nya; `email_verified_at` sengaja tidak ikut
- **Prioritas:** Low (target jangka menengah — bukan penghalang operasional)
- **Area Terdampak:**
  - `app/Models/PlatformUser.php` — tanpa `email_verified_at`, tanpa kolom rahasia TOTP
  - `app/Http/Controllers/Platform/AuthController.php` — alur masuk masih satu langkah
- **Deskripsi:**
  Satu akun platform memegang data administratif seluruh klien; satu faktor terasa tipis untuk kewenangan sebesar itu. Sekarang lapisannya sudah lebih baik daripada saat dicatat pertama kali — ada throttle (`[BL-007]`), jejak audit yang bisa dibaca (`[BL-009]`), dan pemulihan kata sandi yang tidak membocorkan keberadaan akun (`[BL-010]`) — tapi tetap: siapa pun yang memegang kata sandinya langsung masuk.
- **Usulan Perbaikan:**
  TOTP (aplikasi authenticator) lebih tepat daripada OTP surel di sini, karena surel justru jalur pemulihan kata sandinya — kalau kotak masuk jebol, dua-duanya jebol sekaligus. Sertakan kode pemulihan sekali-pakai, dan catat pengaktifan/penonaktifannya sebagai kejadian `sensitive`.

- **CATATAN PENUTUP 2026-08-19 — `email_verified_at` sengaja TIDAK ikut.**
  Ia disebut di Area Terdampak bersama kolom rahasia TOTP, tapi keduanya menjawab hal berbeda: verifikasi kepemilikan alamat surel bukan faktor kedua. Menambahkannya di sini berarti mengubah alur pembuatan akun platform sebagai efek samping sebuah entri keamanan login. Bila diinginkan, ia entri tersendiri.
- **CATATAN PENUTUP 2026-08-19 — tanpa kode QR, dan itu konsekuensi larangan menambah dependensi.**
  Merender QR butuh pustaka. Halaman pendaftaran menampilkan kuncinya dalam potongan empat huruf plus tautan `otpauth://` yang bisa disalin; setiap authenticator arus utama menerima pemasukan manual, dan panel ini hanya dipakai segelintir akun internal. Bila QR diinginkan, itu penambahan dependensi tersendiri yang perlu persetujuan lebih dulu.
- **CATATAN PENUTUP 2026-08-19 — TOTP-nya ditulis sendiri, bukan lewat paket.**
  Seluruh algoritmanya muat dalam `app/Services/Platform/TotpService.php`: HMAC-SHA1 atas nomor langkah waktu, pemotongan dinamis RFC 4226 §5.3, enam digit, toleransi ±1 langkah. Kriptografinya tetap milik PHP (`hash_hmac`, `hash_equals`); yang ditulis manual hanya base32, karena PHP memang tidak punya fungsi bawaannya.
- **Entri penutup di `docs/CHANGELOG.md`:** `[ADDITION] Akun Platform Punya Faktor Kedua, dan Kata Sandi yang Benar Tidak Lagi Berarti Masuk (BL-013)`

---

### [BL-074] Saran Jual Hanya Bisa Ditemukan Mesin — Owner Belum Punya Cara Menargetkan Sendiri
- **Ditemukan:** 2026-08-13
- **Sumber:** Pertanyaan pemilik saat menyisir backlog — "fitur untuk upsell yang terintegrasi stock (otomatis) atau di targetkan (manual by user)". Sisi **otomatis**-nya sudah ada dan selesai (`[BL-017]`, 2026-07-27); sisi **manual**-nya ternyata tidak pernah tercatat di mana pun.
- **Status:** **Selesai 2026-08-19** — butir (a)–(f) seluruhnya, dan kedua pertanyaan terbuka sudah dijawab pemilik
- **Prioritas:** Medium — bukan cacat, melainkan setengah fitur. Mesinnya bekerja, tapi owner yang paling tahu barangnya sendiri belum punya tempat menaruh pengetahuan itu.
- **Area Terdampak:**
  - `app/Services/Upsell/UpsellIndexBuilder.php:23-28` — tiga strategi disuntikkan di konstruktor; tidak ada jalur keempat untuk aturan buatan manusia
  - `app/Services/Upsell/Strategies/` — `AttachModifierStrategy`, `UpsizeVariantStrategy`, `PressedStockStrategy`; ketiganya menurunkan saran dari data, bukan dari perintah
  - `config/upsell.php:38-42` — `types` hanya bisa menyalakan/mematikan **jenis** saran; tidak ada tempat menuliskan "kalau beli A, tawarkan B"
  - `app/Services/Upsell/SellableVariantQuery.php` — penjaga kandidat tunggal (stok, kedaluwarsa, produk nonaktif)
  - `database/migrations/2026_07_27_100000_create_upsell_events_table.php:28` — `type` hanya mengenal `attach | pressed_stock | upsize`
  - `app/Services/Upsell/Suggestion.php:17-30` — bentuk satu saran; sudah cukup umum untuk menampung aturan manual tanpa diubah
- **Deskripsi:**
  Seluruh saran jual hari ini **ditemukan mesin**: ko-okurensi modifier dari riwayat 30 hari, barang yang tertekan stok/kedaluwarsa, dan naik ukuran berdasarkan selisih harga. Owner tidak punya satu pun cara mengatakan "bulan ini dorong kopi susu botol" atau "setiap yang beli nasi goreng, tawarkan teh manis" — padahal dialah yang paling tahu barang mana yang sedang perlu didorong dan kenapa.
  Yang tersedia hanya saklar tingkat konfigurasi (`config/upsell.php`), dan itu pun bukan permukaan owner: ia berkas kode, bukan halaman. Satu-satunya pengaturan upsell yang benar-benar bisa disentuh owner adalah `upsell_mandatory` (`database/migrations/2026_07_31_023207_add_upsell_mandatory_to_tenants_table.php:18`) — dan itu mengatur **apakah saran wajib diselesaikan**, bukan **apa yang disarankan**.
  Perlu ditegaskan supaya tidak dicatat dua kali: ini **bukan** `[BL-018]`. `[BL-018]` soal saran barang tertekan yang boleh **berdiskon**; entri ini soal siapa yang **memilih** barangnya. Keduanya bisa dikerjakan terpisah, dan aturan manual justru lebih murah karena tidak menyentuh harga sama sekali.
- **Usulan Perbaikan:**
  **(a) Strategi keempat, bukan mesin kedua.** Kontrak `SuggestionStrategy` sudah ada dan `UpsellIndexBuilder` sudah merakit banyak strategi jadi satu indeks. Aturan manual paling murah masuk sebagai `ManualRuleStrategy` yang membaca tabel baru `upsell_rules` (tenant, pemicu, yang disarankan, catatan, jendela berlaku, prioritas). Dengan begitu **tidak ada** perubahan pada bentuk props POS, `UpsellStrip.vue`, maupun pencatatan event.
  **(b) `type: 'manual'` sebagai nilai keempat** di `upsell_events.type` dan di `config/upsell.php` `types`. Ini bukan formalitas: laporan konversi memisahkan angka per jenis, jadi inilah satu-satunya cara owner bisa tahu apakah tebakannya sendiri mengalahkan tebakan mesin. Tanpa ini, aturan manual jadi fitur yang tidak pernah bisa dievaluasi.
  **(c) Aturan manual harus menang saat berebut slot.** `max_per_transaction` default 2 (`config/upsell.php:25`). Kalau aturan manual hanya diberi skor lalu diadu dengan skor mesin, saran yang dipasang owner bisa tergeser diam-diam oleh angka yang tidak pernah ia lihat — dan ia akan menyimpulkan fiturnya rusak. Beri lantai skor atau satu slot yang dicadangkan; putuskan yang mana sebelum menulis kodenya.
  **(d) Penjaga kandidat tetap berlaku, tanpa pengecualian.** Aturan manual **tidak boleh** melewati `SellableVariantQuery`: varian kedaluwarsa, stok nol, dan produk nonaktif harus tetap gugur walaupun owner sendiri yang menuliskannya. `[BL-017]` menulis test khusus untuk ini; jalur manual yang menerobos akan menghidupkan kembali persis bug yang test itu jaga.
  **(e) Offline ikut gratis, tapi ada jebakan yang sudah dikenal.** Karena aturan ikut indeks di props POS, ia ikut ter-snapshot `useCatalogCache` dan hidup offline tanpa kode tambahan (`UpsellIndexBuilder.php:16-20`). Konsekuensinya sama dengan yang sudah tertulis di `[BL-018]` poin 7: **jendela berlaku sebuah aturan bisa kedaluwarsa di dalam snapshot** tanpa diketahui perangkatnya. Perangkat yang seharian offline akan menawarkan promo yang sudah berakhir semalam. Putuskan apakah itu diterima (kemungkinan besar ya, karena harganya tetap harga katalog) atau perlu tanggal kedaluwarsa yang dibaca client.
  **(f) Halamannya berdiri sendiri, jangan ditambahkan ke Pengaturan.** Editor aturan butuh tabel, pencarian produk, dan jendela tanggal — dan `[BL-039]` sudah mencatat bahwa "Profil Usaha" kelebihan muatan. Tempatnya di grup yang sama dengan laporan upsell, bukan di formulir pengaturan.
- **Yang belum diputuskan dan menentukan bentuk tabelnya — jawab dulu sebelum ada migrasi:**
  1. **Pemicunya selevel apa?** Varian tertentu, produk (semua variannya), atau kategori. Ketiganya bentuk kolom yang berbeda, dan yang paling longgar paling mahal di sisi penyaringan client.
  2. **Apakah ada aturan tanpa pemicu** — "selalu tawarkan ini di setiap transaksi"? Itu masuk ke `cart_level` (lewat `CartLevelStrategy` yang sudah ada), bukan ke `by_variant`, jadi jawabannya menentukan aturan itu dirakit di mana.

- **KEPUTUSAN PEMILIK 2026-08-19 — dua pertanyaan penentu bentuk tabel, terjawab:**
  1. **Pemicunya selevel VARIAN, ditambah aturan tanpa pemicu.** Produk dan kategori tidak ikut. Yang paling longgar paling mahal di sisi penyaringan client, dan penyaringan itu berjalan tiap klik di perangkat kasir yang paling lemah.
  2. **Ya, ada aturan tanpa pemicu** — "selalu tawarkan ini di setiap transaksi". Ia dirakit lewat `CartLevelStrategy`, dan `ManualRuleStrategy` karena itu mengimplementasikan **kedua** kontrak sekaligus.
- **KEPUTUSAN PEMILIK 2026-08-19 tentang butir (c) — bukan lantai skor ATAU slot cadangan, melainkan keduanya diganti hal lain.**
  Yang diminta pemilik: tiap aturan bisa dinyalakan/dimatikan sendiri dan dijadwalkan dari jauh hari ("promo setiap tanggal kembar, disetting hari-hari sebelumnya"), dan **batas tampil dinaikkan jadi 3** supaya saran mesin tidak tergeser habis begitu owner mulai memasang aturannya. Yang terpasang di kode: lantai skor `1000 + priority` (skor mesin tertinggi 100), kolom `is_active`, dan jendela `starts_on`/`ends_on`. Pemilik meminta angka 3 **diverifikasi langsung di layar kasir** — bila strip-nya terlalu ramai, `config/upsell.php` `max_per_transaction` yang diturunkan, bukan fiturnya yang dicabut.
- **Butir (e) tetap berlaku apa adanya dan diterima sebagai keterbatasan.** Jendela berlaku sebuah aturan memang bisa kedaluwarsa di dalam snapshot `useCatalogCache`; perangkat yang seharian offline akan menawarkan promo yang berakhir semalam. Diterima karena **harganya tetap harga katalog**. Di `[BL-018]` konsekuensi yang sama jauh lebih serius, karena di sana yang ikut basi adalah potongan harganya.
- **Entri penutup di `docs/CHANGELOG.md`:** `[ADDITION] Owner Akhirnya Bisa Menargetkan Saran Jualnya Sendiri, dan Aturannya Selalu Menang Slot (BL-074)`

---

### [BL-056] Pengajuan Harga Adaptif Berlaku untuk Bulan Mana — Bulan Pengajuan atau Bulan Berikutnya?
- **Ditemukan:** 2026-08-07
- **Sumber:** Pemilik saat menutup keputusan struktur harga — "apakah ajukan itu untuk bulan pengajuan itu, atau bulan depan... catat saja dulu"
- **Status:** **Selesai 2026-08-19** — dijawab pemilik: berlaku **periode berikutnya** (opsi (i)), sebagai turunan dari model prabayar. Satu cacat yang baru terungkap saat keputusannya ditulis dipisahkan jadi `[BL-080]`
- **Prioritas:** **High sejak 2026-08-10** — `[BL-055]` sudah mendarat dan memilih opsi **(i) berlaku periode berikutnya**, mengikuti apa yang sudah dijanjikan kalimat sukses consent sejak awal. Itu bukan jawaban atas entri ini, melainkan keadaan bawaan yang dipertahankan supaya tidak ada keputusan pemilik yang diambil diam-diam. Bila jawabannya (ii), yang berubah adalah penerbitan ulang tagihan terbuka — bukan alur pengajuannya
- **Area Terdampak:**
  - `app/Services/SubscriptionService.php` — `pricingAsOf()`: harga ditetapkan dari awal bulan periode tagihan
  - `app/Models/Invoice.php` — `amount` dan `pricing_context` dibekukan saat tagihan terbit
- **Deskripsi:**
  Tagihan periode berjalan sudah terbit dengan nominal tetap dan konteks harga yang dibekukan. Tidak ada apa pun hari ini yang menghitung ulang tagihan **terbuka** ketika jalur harga tenant berubah.
  Akibatnya, dalam alur tenggat yang menawarkan "bayar `paid-1` atau ajukan diskon", tenant bisa mengajukan, disetujui, lalu **tetap melihat nominal lama** di layar — putus tepat di titik yang paling menentukan.
  Yang sudah aman dan tidak perlu dikhawatirkan: kekhawatiran pemilik bahwa sistem akan memeriksa omset **bulan berjalan** padahal tunggakannya dari bulan lalu. `pricingAsOf()` memakai awal bulan periode tagihan, dan `current_period_end` membeku selama tenant belum membayar — jadi omset yang dipakai memang omset periode yang tertunggak.
- **Usulan Perbaikan:**
  Putuskan salah satu, lalu `[BL-055]` mengikutinya: **(i)** berlaku bulan berikutnya — paling sederhana, tidak menyentuh tagihan yang sudah terbit, tapi tenant yang sedang terjepit harus membayar penuh dulu; **(ii)** berlaku untuk bulan pengajuan — tagihan terbuka diterbitkan ulang atau disesuaikan, dengan tagihan lama dibatalkan bukan dihapus. Opsi (ii) menjawab alur tenggat, tapi menuntut aturan tegas soal tagihan yang sudah sebagian dibayar.
  **Jangan pilih (ii) tanpa penjaga:** pengajuan yang bisa memotong tunggakan berjalan adalah jalan keluar dari tagihan mana pun. Penjaga alaminya sudah ada — harganya dihitung dari penjualan yang tenant catat sendiri, jadi menekannya merusak datanya sendiri — tapi itu perlu dinyatakan, bukan diandalkan diam-diam.

---

### [BL-061] Tombol Simulasi Lama Kini Jalur Uang Ketiga — Dicabut Setelah Peragaan
- **Ditemukan:** 2026-08-07
- **Sumber:** Konsekuensi `[BL-059]`, sudah diantisipasi di butir (i) entri itu
- **Status:** **Selesai 2026-08-19** — peragaan sudah berjalan, seluruh butir (a)–(d) dikerjakan
- **Prioritas:** Low
- **Area Terdampak:**
  - `app/Http/Controllers/Billing/SimulatedPaymentController.php` — pelunasan peragaan satu klik
  - `routes/web.php` — `billing.simulate.store`
  - `resources/js/Pages/Billing/Show.vue` — tombol "Simulasikan pembayaran" dan prop `simulation`
  - `app/Http/Controllers/Billing/SubscriptionController.php` — prop `simulation.enabled`
  - `app/Services/Billing/InvoiceSettlement.php` — `SOURCE_SIMULATION` dan `canSimulate()`
  - `tests/Feature/Subscription/SimulatedPaymentTest.php` — tesnya ikut, kecuali dua tes terakhir yang menguji jalur pemilik SaaS dan harus **dipindahkan**, bukan dihapus
- **Deskripsi:**
  Setelah `[BL-059]`, ada tiga cara sebuah tagihan berpindah ke lunas tanpa uang sungguhan diperiksa: bukti transfer manual (sah, tetap dipertahankan), gateway tiruan lewat webhook (jalur baru), dan tombol simulasi satu klik (peninggalan `[BL-045]`). Yang ketiga sekarang mubazir — ia melunasi dengan caranya sendiri, tidak lewat webhook, dan karena itu tidak membuktikan apa pun tentang jalur yang akan dipakai produksi.
  Gerbangnya memang masih benar (`is_demo` + bukan produksi), jadi ini bukan lubang keamanan. Yang menjadikannya utang adalah jumlahnya: tiap jalur menuju `active` adalah satu tempat lagi yang harus ikut dipikirkan setiap kali aturan pelunasan berubah.
- **Kenapa belum dicabut:** peragaan ke calon klien dijadwalkan sehari setelah `[BL-059]` mendarat, dan mencabut satu-satunya jalur yang sudah pernah dipakai di depan orang tepat sebelum itu tidak ada untungnya.
- **Usulan Perbaikan:**
  **(a)** Cabut controller, rute, tombol, dan prop `simulation` setelah peragaan berjalan mulus.
  **(b)** `SOURCE_SIMULATION` **jangan dihapus** — nilai itu mungkin sudah tertulis di kolom `settled_via` beberapa tagihan, dan konstanta yang hilang membuat riwayatnya tak terbaca. Beri catatan bahwa ia peninggalan.
  **(c)** `canSimulate()` ikut dicabut bila tidak ada pemanggil lain yang tersisa.
  **(d)** Pindahkan dua tes terakhir di `SimulatedPaymentTest` (jalur verifikasi pemilik SaaS lewat `InvoiceSettlement`) ke berkas tes yang bukan tentang simulasi — keduanya menguji jalur yang tetap hidup.

---

### [BL-075] Foto Bukti Pembayaran Non-Tunai Belum Ada — dan Harus Bisa Dimatikan per Toko
- **Ditemukan:** 2026-08-13
- **Sumber:** Pertanyaan pemilik saat menyisir backlog — "fitur untuk take gambar ketika pembayaran (biasanya untuk yang non tunai seperti qris, tf, dll)", disusul keputusan bentuknya di hari yang sama
- **Status:** **Selesai 2026-08-19** — kecuali satu sisa yang sengaja dibiarkan terbuka, dicatat di bagian paling bawah
- **Prioritas:** Medium — tidak ada uang yang tercatat salah karenanya, tapi tanpa ini perselisihan "katanya sudah transfer" tidak punya alat bukti apa pun di sisi toko
- **Area Terdampak:**
  - `database/migrations/2026_03_06_000012_create_transaction_payments_table.php:11-19` — satu-satunya jejak pembayaran non-tunai adalah `reference_code` bertipe string; tidak ada kolom berkas
  - `app/Http/Controllers/Cashier/POSController.php:178-181` — validasi checkout hanya menerima `payment_method_id`, `amount`, `reference_code`
  - `app/Services/TransactionService.php:180-183`, `:364-367`, `:628-629` — **tiga** tempat membuat baris pembayaran (checkout, tagihan terbuka, sinkronisasi offline); ketiganya harus ikut berubah bersama
  - `database/migrations/2026_03_06_000010_create_payment_methods_table.php:15` — `type` enum sudah membedakan `cash` dari `qris_static | qris_dynamic | bank_transfer`, jadi definisi "non-tunai" **sudah ada** dan tidak perlu kolom baru
  - `app/Services/ImageService.php:29-31` — pola penyimpanan gambar yang sudah terbukti: disk privat, WEBP, dua ukuran
  - `app/Http/Controllers/MediaController.php:33-49` — gerbang berkas ber-auth dengan pemeriksaan tenant, 404 (bukan 403) untuk milik tenant lain
  - `resources/js/Components/PaymentModal.vue` — permukaan tempat tombol kamera akan hidup
  - `resources/js/services/offlineDb.js`, `resources/js/composables/useOfflineQueue.js:85,138` — outbox IndexedDB dan flush-nya
  - `database/migrations/2026_07_29_141632_add_capability_flags_to_tenants_table.php:13-15` — pola saklar kapabilitas per tenant
- **Deskripsi:**
  Sama sekali belum ada. Tidak ada kolom, tidak ada unggahan, tidak ada tombol. Kasir yang menerima QRIS atau transfer hanya bisa mengetik kode referensi — dan kode yang diketik tangan tidak membuktikan apa pun soal uangnya masuk.
  **Satu kemiripan yang menyesatkan dan wajib dibaca sebelum menyentuh kode:** `invoices.proof_path` **sudah ada** (`database/migrations/2026_07_24_181636_create_invoices_table.php:35`). Itu bukti transfer **tenant membayar langganan SaaS**, alur platform, tabel lain, penonton lain. Ia bukan preseden yang bisa dipakai ulang begitu saja untuk pembayaran pelanggan di kasir, dan jangan sampai ada yang menyimpulkan fitur ini "setengah ada" karena melihatnya.
  Yang paling dekat menyinggung di backlog hanyalah `[BL-028]` poin 4 — non-tunai ditandai "tidak masuk laci" — tapi itu soal menampilkan angka, bukan menyimpan buktinya.
- **KEPUTUSAN PEMILIK 2026-08-13 — tiga hal yang sudah tidak perlu ditanyakan lagi:**
  1. **Opsional, bisa dinyalakan/dimatikan per toko.** Sebagian toko memang perlu foto bukti bayar non-tunai, sebagian tidak. Jadi ini **bukan** langkah wajib yang ditambahkan ke setiap penjualan non-tunai di semua tenant.
  2. **Penyimpanannya untuk sementara di disk server**, mengikuti pola yang sudah ada.
  3. **Pemindahan ke object storage (S3 dan sekerabatnya) dicatat terpisah sebagai pekerjaan berikutnya** — lihat `[BL-076]`, yang sengaja dibuat supaya keputusan (2) tidak terbaca sebagai keputusan permanen.
- **Usulan Perbaikan:**
  **(a) Saklarnya kolom tenant, `default false`.** `payment_proof_enabled` sejajar dengan `kitchen_queue_enabled`/`self_order_enabled`/`ai_enabled`, disunting di halaman pengaturan yang sama (`app/Http/Controllers/Owner/SettingsController.php:55-56,130-131`). **Default `false` bukan detail** — menyalakannya untuk semua orang berarti menambah satu langkah ke setiap penjualan non-tunai di setiap toko, termasuk toko yang tidak pernah memintanya, dan itu langsung terasa di antrean kasir.
  **(b) Berlakunya berdasarkan `payment_methods.type`, bukan daftar baru.** Kamera muncul hanya untuk metode ber-`type` selain `cash`. Definisinya sudah ada di enum; tidak perlu kolom, tidak perlu konfigurasi tambahan. Kalau nanti ada toko yang ingin QRIS difoto tapi transfer tidak, barulah itu jadi kolom opt-out per metode — jangan dibangun sebelum ada yang memintanya.
  **(c) Kolomnya di `transaction_payments`, bukan di `transactions`.** Satu transaksi bisa dibayar beberapa metode sekaligus (split bill), dan buktinya melekat pada **pembayarannya**, bukan pada penjualannya. `proof_path` nullable di `transaction_payments` menjawab keduanya sekaligus.
  **(d) Ikuti `ImageService` apa adanya, jangan tulis penyimpanan kedua.** Disk **privat** (`storage/app/private`), dinormalkan jadi WEBP, dan **tidak pernah** lewat symlink publik. Alasannya di sini lebih kuat daripada untuk foto produk: tangkapan layar aplikasi e-wallet kerap memuat nama dan nomor telepon pelanggan. Penyajiannya lewat rute ber-auth seperti `MediaController`, dengan pemeriksaan tenant yang sama dan 404 yang sama.
  **(e) Ukuran gambar dikecilkan di PERANGKAT, bukan hanya di server.** `ImageService` mengecilkan setelah berkas sampai (`ImageService.php:57-60`) — cukup untuk foto produk yang diunggah owner sambil online, tapi **tidak cukup di sini**. Alasannya ada di bagian offline di bawah.
- **OFFLINE — bagian yang paling mudah salah, dan alasan butir (e) di atas ada:**
  1. **Kameranya sendiri tidak butuh jaringan.** Mengambil foto offline bukan masalah; yang menunggu hanya pengirimannya. Jadi kasir **tetap bisa berjualan dan tetap bisa memotret** saat sinyal mati — dan memang harus begitu.
  2. **Yang jadi masalah adalah antreannya.** Outbox hidup di IndexedDB (`offlineDb.js`) dan hari ini hanya berisi JSON penjualan yang beberapa kilobyte. Satu foto kamera ponsel 12MP berukuran 3–5 MB; disimpan sebagai base64 ia membengkak sekitar sepertiga lagi. Sehari berjualan offline bisa berarti ratusan megabyte di penyimpanan yang — menurut `[BL-016]` Bagian B — **masih bisa diusir peramban kapan saja**. Karena itu kompresi wajib terjadi sebelum masuk antrean, dan simpanlah sebagai **Blob**, bukan string base64.
  3. **`navigator.storage.persist()` naik dari "perbaikan murah" jadi prasyarat.** Ia sudah tercatat di `[BL-016]` Bagian B sebagai salah satu dari dua perbaikan yang tidak menunggu lapisan native. Begitu antrean berisi gambar, mengerjakannya **sebelum** fitur ini menyala adalah urutan yang benar, bukan pelengkap.
  4. **Unggahan foto harus jadi langkah yang bisa diulang sendiri, terpisah dari pengiriman penjualannya.** Idempotensi outbox bersandar pada `client_uuid`. Kalau foto dibundel sedemikian rupa sehingga kegagalan unggah memaksa penjualannya dikirim ulang, jaminan anti-duplikat itu ikut dipertaruhkan demi hal yang jauh lebih sepele daripada uang. Kirim penjualannya dulu, lalu foto menyusul dengan `client_uuid` sebagai penunjuknya — dan bila fotonya hilang selamanya, penjualannya tetap sah.
  5. **Jangan pernah jadikan foto sebagai syarat menyelesaikan penjualan offline.** Pelajarannya sudah dibayar di `[BL-025]`/`UpsellIndexBuilder.php:38-42`: aturan yang mengikat saat online tapi bisa dilewati saat offline adalah aturan yang tidak berarti apa-apa — dan kebalikannya, aturan yang mengunci kasir saat sinyal mati akan dimatikan owner di hari pertama. Bila fotonya gagal, tandai pembayarannya "bukti belum ada" dan biarkan penjualannya lewat.
  6. **Sinkronisasi offline adalah salah satu dari tiga jalur pembuat pembayaran** (`TransactionService.php:628-629`). Ia mudah terlewat justru karena tidak pernah tersentuh saat pengujian manual di meja yang sinyalnya penuh.
- **Yang belum diputuskan — jawab sebelum fiturnya menyala di tenant sungguhan:**
  1. **Berapa lama fotonya disimpan?** Ini yang menentukan pertumbuhan disk, dan ia keputusan produk, bukan keputusan teknis. Bukti pembayaran berguna selama sengketa masih mungkin — bukan selamanya. Tanpa jawaban, disk server tumbuh tanpa batas dan `[BL-076]` berubah dari peningkatan jadi keadaan darurat.
  2. **Wajib atau opsional saat saklarnya menyala?** Toko yang menyalakannya mungkin ingin kasir tidak bisa melewatkannya — tapi baca butir offline (5) di atas sebelum memutuskan "wajib".

- **CATATAN PENUTUP 2026-08-19 — bagian OFFLINE di atas menjawab masalah yang belum ada.**
  Dibiarkan tertulis apa adanya karena ia akan berlaku persis seperti itu begitu penjualan non-tunai offline dibuka, tapi **hari ini tidak satu pun butirnya perlu dikerjakan**. Alasannya ditemukan saat mengerjakan entri ini: `TransactionService::assertCashOnly()` menolak setiap pembayaran non-tunai pada jalur sinkronisasi, dan `resources/js/Pages/Cashier/POS.vue` bahkan sudah menyaring daftar metode jadi tunai saja saat perangkat offline. Non-tunai tidak pernah terjadi offline, jadi tidak pernah ada foto yang perlu masuk antrean IndexedDB — dan keputusan pemilik "wajib saat online, dilewati saat offline" tidak butuh satu baris kode pun untuk ditegakkan.
- **CATATAN PENUTUP 2026-08-19 — jalur pembuat pembayarannya EMPAT, bukan tiga.**
  `app/Services/TransactionEditService.php:87-97` menghapus lalu membangun ulang seluruh baris pembayaran dari kiriman client, dan client edit tidak pernah mengirim bukti. Tanpa penanganan, mengoreksi jumlah item pada penjualan QRIS menghapus buktinya sebagai efek samping — tanpa galat, tanpa jejak. Sekarang buktinya diselamatkan lewat `payment_method_id`.
- **KEPUTUSAN PEMILIK 2026-08-19 — dua pertanyaan yang tertinggal, terjawab:**
  1. **Retensi: tanpa batas untuk sekarang.** Tidak ada pembersihan otomatis untuk foto yang sudah melekat pada pembayaran. Konsekuensi yang sudah tertulis di atas tetap berlaku dan kini benar-benar berjalan: disk server tumbuh tanpa batas, dan **`[BL-076]` berubah dari peningkatan jadi pekerjaan mendesak**. Yang punya perintah pembersih hanya berkas TERTUNDA yang tidak pernah diklaim (`payment-proofs:prune-unclaimed`, harian, batas 24 jam) — itu kebersihan disk, bukan kebijakan retensi, dan keduanya sengaja tidak digabung.
  2. **Wajib saat saklarnya menyala.** Ditegakkan di server pada kedua jalur online (checkout dan pelunasan tagihan terbuka), bukan sekadar dengan menahan tombol di layar.
- ~~**SISA YANG SENGAJA DIBIARKAN TERBUKA — modal EDIT transaksi belum punya tombol kamera.**~~ — **selesai 2026-09-09.**
  Akibatnya sebuah pembayaran yang **diubah** jadi non-tunai lewat pengeditan bisa lolos tanpa bukti. Menutupnya dengan menolak edit semacam itu akan menciptakan jalan buntu di layar yang tidak punya cara memotret — persis yang butir offline (5) larang. Yang menahan risikonya sampai saat itu: pengeditan sudah dibatasi (owner kapan saja, kasir hanya dalam shift laci terbuka) dan seluruhnya teraudit.

  **Yang akhirnya mendarat, dan kenapa bentuknya begitu.** Tempatnya memang `resources/js/Components/TransactionEditModal.vue`, dan tombolnya memang tidak perlu ditulis ulang — modal itu sudah memakai ulang `PaymentModal` sejak awal, jadi yang kurang hanya propnya. Yang butuh keputusan justru **siapa yang diwajibkan**, dan "semua baris non-tunai" adalah jawaban yang akan menciptakan jalan buntu yang sama dari arah lain: metode yang **sudah ada** pada transaksinya dikecualikan (QRIS kemarin tidak punya apa pun untuk dipotret hari ini; kameranya tetap ditawarkan, berlabel opsional), sementara baris yang **baru** jadi non-tunai lewat pengeditan tetap wajib — itu pembayaran yang lahir hari ini dan bisa difoto hari ini. Daftar pengecualiannya diambil dari basis data, bukan dari kiriman client. Memotret ulang mengganti foto lama dan membuang berkasnya. Lihat `[ADDITION] Layar Edit Transaksi Akhirnya Punya Kamera — dan Kewajibannya Berhenti pada Metode yang Sudah Ada (BL-075 Sisa)` di `docs/CHANGELOG.md`.

  **Pratinjau bukti yang sudah melekat ikut mendarat di hari yang sama.** Semula ditulis sebagai pekerjaan tersendiri karena `PaymentModal` mengosongkan barisnya setiap kali dibuka — dan mengubah itu terdengar seperti menyentuh jalur kasir. Ternyata tidak: begitu dibentuk sebagai prop `initialPayments` yang **kosong di kasir**, POS dan pelunasan tagihan terbuka tidak berubah sedikit pun, dan hanya modal edit yang mengisinya. Sekaligus hilang satu hal yang selama ini menyusahkan tanpa ada yang mencatatnya: menekan "Atur" di layar edit tidak lagi menghapus nominal dan kode referensi yang sudah tersimpan.
- **Entri penutup di `docs/CHANGELOG.md`:** `[ADDITION] Pembayaran Non-Tunai Bisa Difoto, dan Sinkronisasi Offline Ternyata Tidak Perlu Ikut Berubah (BL-075)`

---

### [BL-034] Pendaftaran Belum Menentukan Paket/Fitur — Tipe Usaha Hanya Dipakai Harga
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "perbaiki dan kasih jelas alur pendaftaran, ... pakai paket kategori, misal bazar maka aktif itu cuma kasir dan stock biasa, kalau cafe maka akan aktif open bill dll"
- **Status:** **Selesai 2026-08-15** — mekanisme presetnya utuh untuk keempat jenis usaha yang ada; preset `bazar` sengaja tidak ikut, ia menunggu `[BL-035]` dan masuk kelak sebagai satu baris config
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
- **Catatan penutup 2026-08-15 — apa yang benar-benar dikerjakan, dan apa yang tidak.**
  Yang dibangun persis seperti usulan di atas: peta `business_type → daftar fitur` di `config/business-presets.php`, dibaca satu kelas (`BusinessPresetService`), dengan daftar centang di formulir daftar yang tetap bisa diubah sebelum lanjut. `kuliner` menyalakan antrian dapur; `retail`, `jasa`, dan `lainnya` tidak.
  **Tiga hal yang ditemukan saat mengerjakannya, dan tidak satu pun tertulis di usulan awal:**
  1. **Yang dikirim formulir harus hasil centangnya, bukan nama presetnya.** Mengirim `business_type` lalu membiarkan server menyimpulkan fiturnya membuat daftar centang itu jadi hiasan — pendaftar yang melepas satu centang tetap mendapatkannya, dan tidak ada tempat kekeliruan itu terlihat.
  2. **Daftar kosong tidak boleh jatuh ke preset.** `ai_enabled` bawaannya **menyala** di kolom database, jadi memperlakukan `[]` sebagai "tidak dijawab" berarti pendaftar yang melepas semua centang tetap mendapat AI yang baru saja ia tolak. Karena itu pemeriksaannya `array_key_exists`, dan penerapannya selalu menyebut setiap kolom, bukan hanya yang menyala.
  3. **`self_order` tidak masuk preset mana pun.** Menyalakannya membuka tautan pemesanan yang bisa diakses siapa saja; menyimpulkan itu dari "orang ini memilih Kuliner" adalah kesimpulan yang tidak dibayar datanya.
  **Yang sengaja tidak ikut:** `upsell_mandatory` dan `order_identity_mode`. Keduanya duduk di halaman Pengaturan yang sama dan gampang terbawa, tapi keduanya aturan kerja — tidak menggerbangi rute apa pun — jadi menebaknya dari jenis usaha berarti menebak cara orang bekerja, bukan modul apa yang ia butuhkan.
  **Batas dari "Koreksi 2026-08-01" di atas ditegakkan dengan tes, bukan dengan niat:** `BusinessProfileController` tidak menyentuh satu pun kolom `*_enabled`, dan ada tes yang gagal bila seseorang menambahkannya.
  Lihat `[ADDITION] Jenis Usaha Akhirnya Menentukan Fitur, Bukan Cuma Harga (BL-034)` di `docs/CHANGELOG.md`.

---

### [BL-037] Perpindahan Halaman Hanya Ditandai Progress Bar — Belum Ada Skeleton
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "render skeleton, hilangkan progress bar yang mengganggu dan keliatan aplikasi lambat loading, terutama di kasir dan owner dashboard"
- **Status:** **Selesai 2026-08-15** — komponen kerangka lahir 2026-08-13 bersama empat halaman pertama; sisa tabel "Pelacakan Penerapan" habis 2026-08-15, berikut bilah kemajuannya
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

- **Pemutakhiran 2026-08-13 — komponennya sudah ada, dan itu yang mengubah sisa pekerjaan ini jadi pekerjaan mekanis.**
  Delapan komponen di `resources/js/Components/Skeleton/` menggantikan rencana "kerangka `animate-pulse` per halaman": `Skeleton.vue` (primitif, satu-satunya tempat warna/radius/denyut didefinisikan), `SkeletonText`, `SkeletonPanel`, `SkeletonCard`, `SkeletonGrid`, `SkeletonTable`, `SkeletonList`, `SkeletonChart`. Aturan pemakaiannya dicatat di **`DESIGN.md` §6 "Loading States (Skeletons)"** — termasuk Aturan Ruang yang Sama, Aturan Pasangan (satu kerangka = satu `Inertia::defer()`, tidak boleh sebelah saja), dan larangan menulis blok `animate-pulse` lepas di dalam halaman. Tanpa catatan itu, kerangka berikutnya akan lahir sebagai dialek kedua.
  Metode bayar di POS **tidak** ikut ditunda seperti usulan awal: kueri-nya satu baris pendek, dan kasir bisa menekan Bayar sebelum permintaan lanjutan sampai. Yang ditunda di sana katalog produk dan indeks upsell — keduanya dalam satu grup, karena `useCatalogCache` menyimpan katalog beserta sarannya dalam satu snapshot offline.
  Satu jebakan yang ikut ditambal di `POS.vue`: panen snapshot offline dulu berjalan `onMounted`. Dengan katalog yang ditunda, saat itu propnya masih `undefined` — dan menyimpan saat itu akan **menimpa snapshot yang masih bagus dengan katalog kosong**. Panennya sekarang menunggu propnya datang (`watch` + penjaga `Array.isArray`).
  Ringkasan `Owner/Reports/Daily` juga ikut berubah cara hitungnya: dulu ia menjumlahkan koleksi yang sudah dimuat, sekarang agregat `SUM`/`COUNT` — sebab koleksinya sudah tidak ada lagi di respons pertama. Angkanya sama, dan sekarang ada tesnya.
  Progress bar di `app.js` **belum** disentuh, sesuai catatan di atas: ia masih satu-satunya penanda untuk halaman yang belum punya kerangka. Ia baru layak dikecilkan atau dimatikan setelah tabel di bawah ini habis.

- **Pelacakan Penerapan** (kolom "Prop yang ditunda" adalah pekerjaan sisi server yang harus ikut; kerangka tanpa `defer` tidak akan pernah tampil)

| Halaman | Prop yang ditunda | Kerangka | Prioritas | Status |
|---|---|---|---|---|
| `Cashier/POS` | `products`, `upsell` | `SkeletonGrid` + `SkeletonCard media` | High | **Selesai 2026-08-13** |
| `Owner/Dashboard` | `dailyTrend`, `recentTransactions`, `badges` (grup terpisah) | `SkeletonPanel` + `SkeletonChart` / `SkeletonList` / `SkeletonGrid` | High | **Selesai 2026-08-13** |
| `Owner/Reports/Daily` | `transactions`, `paymentSummary`, `topProducts` | `SkeletonPanel` + `SkeletonTable` / `SkeletonList` | High | **Selesai 2026-08-13** |
| `Owner/Transactions/Index` | `transactions` (paginated) | `SkeletonTable` | High | **Selesai 2026-08-13** |
| `Cashier/TransactionHistory` | `transactions` | `SkeletonGrid` + `SkeletonCard` (daftarnya kartu, bukan tabel); kerangka modal pindah ke `SkeletonText` | High | **Selesai 2026-08-15** |
| `Owner/Products/Index` | `products` | `SkeletonGrid` + `SkeletonCard media` (kartu bergambar, bukan tabel) | High | **Selesai 2026-08-15** |
| `Owner/Reports/Monthly` | `paymentSummary`, `topProducts` (satu grup). **`dailySeries` sengaja tetap eager** — ringkasan di atasnya diturunkan dari deret itu, jadi kuerinya sudah jalan untuk cat pertama; menundanya hanya memindahkan 31 baris kecil ke permintaan kedua dan membuat grafiknya berkedip untuk data yang sudah ada di tangan | `SkeletonPanel` + `SkeletonTable` | Medium | **Selesai 2026-08-15** |
| `Owner/Stock/Index` | `products` | `SkeletonGrid` + `SkeletonCard` (tumpukan kartu produk yang bisa dibuka) | Medium | **Selesai 2026-08-15** |
| `Owner/Stock/Movements`, `Owner/Stock/History` | `movements` | `SkeletonTable` | Medium | **Selesai 2026-08-15** |
| `Owner/Reports/Upsell` | `byType` + `bySurface` (satu grup), `topSuggestions` | `SkeletonPanel` + `SkeletonTable` | Medium | **Selesai 2026-08-15** |
| `Owner/CashDrawers/Index` | `cashDrawers` | `SkeletonTable` | Medium | **Selesai 2026-08-15** |
| `Owner/OfflineReview/Index` | `transactions` (`negativeVariants` tetap eager — itulah tindakan yang bisa dimulai sambil menunggu) | `SkeletonPanel` + `SkeletonList` | Medium | **Selesai 2026-08-15** |
| `Owner/AiAnalysis/Index` | tidak ada — yang ditunggu di sana pekerjaan antrean, bukan prop Inertia | `SkeletonText` (migrasi dari blok `animate-pulse` sendiri) | Medium | **Selesai 2026-08-15** |
| `Owner/Transactions/Detail` | `products` + `paymentMethods` (sudah ditunda sejak 2026-08-13) | ikut migrasi kerangka `TransactionEditModal` ke `SkeletonText` | Medium | **Selesai 2026-08-15** |
| `Cashier/Queue` | `queue` — polling memakai permintaan parsial yang menyebut propnya, jadi papan lama tetap terpasang sampai papan baru datang dan kerangkanya hanya tampil di pemuatan pertama | `SkeletonGrid` + `SkeletonCard icon` | Medium | **Selesai 2026-08-15** |
| `Platform/Tenants/Index`, `Platform/Subscriptions/Index` (daftarnya + `seat_usage`/`billing`/`brackets` — satu grup berbagi satu hasil yang dihitung sekali), `Platform/AuditLogs/Index` | daftarnya | `SkeletonTable` | Medium | **Selesai 2026-08-15** |
| Daftar master pendek: `Owner/Categories/Index`, `Owner/Modifiers/Index`, `Owner/PaymentMethods/Index`, `Owner/Staff/Index` (`staff` + `owners`, satu grup), `Owner/Roles/Index` | daftarnya | `SkeletonTable`; `SkeletonGrid` + `SkeletonCard` untuk Modifier yang memang grid kartu | Low | **Selesai 2026-08-15** |

  **Yang sengaja TIDAK masuk daftar:** halaman autentikasi (`Auth/*`), halaman formulir (`Owner/Products/Form`, `Owner/Settings/Index`), halaman tagihan bertahap (`Billing/*`), dan halaman galat (`Errors/*`). Semuanya ringan, propnya kecil, dan kerangka di sana hanya menambah satu kedipan sebelum isi yang sebetulnya sudah siap. Menerapkan kerangka ke seluruh 48 halaman bukan tujuan entri ini.

- **Pemutakhiran 2026-08-15 — tabelnya habis, dan bilah kemajuannya tidak dimatikan.**
  Sisa 20 halaman selesai dalam satu putaran. Tiga hal berbeda dari rencana awal, dan ketiganya dicatat di sini karena tak satu pun terbaca dari kodenya:
  1. **`Platform/Dashboard` dikeluarkan dari daftar.** Controllernya hanya mengirim dua cacah (`Tenant::count()`, `User::count()`). Tidak ada yang bisa ditunda di sana tanpa mengubah dua bilangan bulat menjadi permintaan HTTP kedua.
  2. **`dailySeries` di rekap bulanan tetap eager.** Alasannya ada di baris tabelnya. Ini satu-satunya tempat di entri ini di mana usulan awal ditolak karena akan membuat halamannya lebih lambat, bukan lebih cepat.
  3. **Sebagian "SkeletonTable" di usulan awal menjadi `SkeletonGrid` + `SkeletonCard`.** Riwayat kasir, daftar produk, stok, modifier, dan papan dapur bukan tabel melainkan tumpukan kartu — Aturan Ruang yang Sama menang atas apa yang tertulis di kolom usulan.

  Bilah kemajuan di `resources/js/app.js` **tidak dihapus**, hanya diberi `delay: 500`. Ia masih satu-satunya umpan balik untuk dua hal yang secara struktur tidak bisa dijawab kerangka: pengiriman formulir (POST/PUT tidak punya prop tertunda, jadi tidak ada tempat kerangka berdiri) dan sambungan yang benar-benar lambat. Halaman yang sengaja tidak masuk daftar — autentikasi, formulir, tagihan bertahap, galat — juga masih bergantung padanya. Aturan Bilah Kemajuan di `DESIGN.md` §6 ditulis ulang menjadi larangan menghapusnya, bukan lagi janji akan menghapusnya.

  Pembagian eager/tertunda tiap halaman diuji di `tests/Feature/DeferredPageDataTest.php`; halaman yang sudah punya berkas ujinya sendiri (riwayat kasir, papan dapur, staf, dan tiga halaman platform) diperiksa di berkas itu. Dua uji kebocoran data platform ikut diperkuat: sejak daftarnya ditunda, respons pertamanya memang kosong — keduanya kini memeriksa permintaan parsial yang benar-benar membawa datanya, dan lebih dulu memastikan payload itu tidak kosong supaya permintaan yang salah alamat tidak lulus hanya karena tidak berisi apa pun.

### [BL-044] Trial Habis Tanpa Ada yang Menerbitkan Tagihan — Bulan Kedua Tidak Pernah Menagih
- **Ditemukan:** 2026-08-01
- **Sumber:** Catatan pemilik — "tambahan status jika akun masih gratis, untuk bulan pertama tetapkan full gratis, tapi jika sudah masuk bulan kedua wajib melakukan ajukan subsidi atau kena tagihan biaya normal yaitu 100 k"
- **Status:** **Selesai 2026-08-15** — butir (a) terjawab 2026-08-01 (keputusan tarif), butir (b) 2026-08-06 (tagihan periode terbit sendiri), butir (c) 2026-08-15 (momen pilihan jalur di akhir masa coba). Penghalang butir (c), `[BL-048]`, ditutup `[BL-055]` pada 2026-08-10.
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
  - ~~**Yang perlu ditinjau ulang saat (c) atau tunggakan disentuh:** `renewPeriod()` memakai aturan "tunggakan tidak ditumpuk" (`[BL-030]`).~~ — **ditinjau 2026-08-07, aturannya tetap.** Tiap periode TIDAK punya tagihannya sendiri: `current_period_end` tidak pernah maju selama tenant belum membayar, jadi kunci `Y-m` periodenya membeku dan penjaga periode-ganda menolak penerbitan sesudahnya. Satu pelanggaran = satu tagihan, satu pembayaran = satu periode; keduanya bertemu, bukan bertabrakan. Dipatok test *"a lapse only ever produces one invoice, and one payment clears it"*. Syarat yang mematahkannya dicatat di `[BL-051]`.
  - **Ditemukan saat meninjaunya (sudah diperbaiki 2026-08-07):** tenant yang turun ke masa tenggang **tanpa pernah ditagih** — tarifnya masih Rp 0 atau `null` saat periodenya habis — dikecualikan penerbit selamanya, jadi menetapkan tarifnya besok tidak menerbitkan apa pun. Itu persis nasib yang menunggu `Kopi Story` pada 2026-08-25. Lihat entri CHANGELOG *"Masa Tenggang Ikut Ditagih, dan Aturan Tunggakan Bertahan Setelah Ditinjau"*.
- **Pemutakhiran 2026-08-15 — butir (c) SELESAI, entri ini tertutup.** Lihat entri CHANGELOG *"Masa Coba Berakhir dengan Pertanyaan, Bukan dengan Tagihan (BL-044 butir c)"*. Yang sekarang berjalan:
  - `SubscriptionService::trialChoice()` — kartu dua jalur di dashboard owner, terbuka `trial_choice_lead_days` (14) hari sebelum masa coba habis. **Tujuh hari lebih awal daripada penerbitan tagihan, dan selisih itu inti butirnya:** tagihan pertama terbit H-7 dan nominalnya beku di sana, jadi pilihan yang tiba di hari yang sama tidak bermuara ke mana pun. Sesudah H-7 kartunya tidak hilang — `first_invoice_issued` berbalik dan kalimatnya berganti jadi "berlakunya pada tagihan berikutnya".
  - Kelayakannya ditanyakan ke `adaptiveVerdict()`, bukan diperiksa ulang: tenant yang layak melihat dua jalur berikut perkiraan tarif adaptifnya, yang tidak layak melihat satu jalur berikut sebabnya. Kartunya tetap muncul bagi keduanya — tenant yang tidak layak justru paling perlu tahu bahwa masa gratisnya berujung tagihan.
  - **Cacat yang ditemukan saat mengerjakannya dan ikut diperbaiki:** perkiraan Harga Adaptif dibandingkan terhadap `effectivePrice()`, yang bagi tenant masa coba adalah **Rp 0** — sehingga setiap tenant masa coba dijawab "Harga Tetap masih lebih menguntungkan". Pembandingnya kini `comparisonPriceFor()` (tarif paket tujuan setelah masa coba), diperbaiki serentak di dashboard, `/langganan`, dan `/langganan/harga-adaptif`.
  - **Yang tersisa dan sengaja di luar entri ini:** tarif jalur Harga Tetap masih menunggu `[BL-041]`(a); pemulihan tenant `suspended` masih terbuka di `[BL-051]` — **ditutup 2026-08-31 dengan opsi (ii)**, tombol tagihan pemulihan yang diminta tenant sendiri. Kartunya sendiri belum pernah dilihat di layar sungguhan — tidak ada tenant berstatus `trial` di basis data dev.

---

### [BL-071] Alur Pendaftaran Tidak Pernah Ikut Berubah — Orang Menandatangani Masa Gratis yang Berakhir dengan Tagihan Tanpa Diberi Tahu
- **Ditemukan:** 2026-08-08 (saat menutup `[BL-052]`)
- **Sumber:** Permintaan pemilik 2026-08-08 — "saya mau simpan proses pendaftaran dengan alur yang baru juga mengingat banyak yang sudah berubah"
- **Status:** **Selesai 2026-08-15** — butir (a)–(c) dikerjakan setelah ketiga keputusan pemilik dijawab 2026-08-15; butir (d) (`business_type` masih `nullable`) dipisah jadi `[BL-079]`.
- **Prioritas:** High — bukan soal tampilan. Sejak `[BL-052]` masa gratis **berakhir dengan perpindahan ke paket berbayar**, dan orang yang mendaftar hari ini tidak diberi tahu itu di layar mana pun sebelum ia menekan "Daftar"
- **Area Terdampak:**
  - `app/Http/Controllers/Auth/AuthController.php` — `register()`: memvalidasi lima field, membuat tenant, memanggil `startTrial()`, selesai. Tidak ada satu pun kalimat soal panjang masa gratis, tarif sesudahnya, atau paket tujuannya
  - `resources/js/Pages/Auth/Register.vue` — **tidak memuat satu pun kata** "gratis", "coba", "bulan", "paket", "harga", atau "Rp" (dicek 2026-08-08)
  - `app/Services/SubscriptionService.php` — `startTrial()`: satu-satunya tempat masa gratis dibuka; `trial_months` = 2 hanya hidup di config
  - `app/Models/Plan.php` — `postTrialTarget()`: paket tujuannya sudah bisa ditanyakan, tinggal tak ada yang menanyakannya di halaman daftar
  - `app/Http/Controllers/Billing/ConsentController.php` — persetujuan jalur harga hidup TERPISAH dari pendaftaran, di `/langganan/persetujuan`
- **Deskripsi:**
  Alur pendaftarannya ditulis ketika paket `free` masih tier termurah yang bisa dihuni selamanya. Sejak keputusan pemilik 2026-08-07 dan penutupan `[BL-052]`, ia bukan itu lagi: masa gratis dua bulan, lalu tenant **dipindahkan otomatis** ke paket berbayar, dan tagihan pertamanya terbit tujuh hari sebelum masa gratisnya habis.
  Halaman langganan sudah mengatakan itu (`post_trial_plan`) — tapi baru **setelah** orang punya akun. Di titik keputusan yang sebenarnya, yaitu halaman daftar, tidak ada apa pun. Yang mendaftar hari ini menerima "Daftar Gratis" dari landing, mengisi lima kolom, dan baru mengetahui ada tarif menunggunya ketika ia membuka halaman langganan atas kemauannya sendiri. Sebagian tidak akan membukanya sampai tagihan pertamanya datang.
- **Kenapa ini bukan pekerjaan menyalin kalimat:**
  1. **Angkanya tidak boleh di-*hardcode*.** `trial_months` ada di config dan paket tujuannya penanda di `plans` yang bisa dipindah pemilik SaaS dari panel — keduanya justru dibuat begitu supaya tidak menuntut deploy. Halaman daftar yang menulis "2 bulan, lalu Rp 100.000" sebagai teks mati akan berbohong pada hari salah satunya diubah. Ia harus membaca `Plan::postTrialTarget()` dan `SubscriptionService::trialMonths()`, sama seperti `SubscriptionController` sudah melakukannya.
  2. **Keadaan "belum ada paket tujuan" harus punya jawaban.** Panel bisa saja belum menandai paket mana pun. Halaman daftar tidak boleh menampilkan kalimat setengah jadi, dan juga tidak boleh diam-diam menjanjikan gratis selamanya.
  3. **Hubungannya dengan persetujuan belum diputuskan.** Persetujuan jalur harga hari ini terpisah dan menyusul setelah akun jadi. Apakah pemberitahuan masa gratis cukup sebagai pemberitahuan, atau ia harus jadi kotak centang yang tercatat seperti `TenantConsent` — itu keputusan, bukan detail implementasi.
- **Yang harus diputuskan pemilik sebelum dikerjakan:**
  1. **Seberapa keras pemberitahuannya**: kalimat informatif di bawah tombol, atau kotak centang wajib yang tidak bisa dilewati.
  2. **Apakah calon tenant memilih paket tujuannya saat mendaftar**, atau semua masuk lewat satu paket bawaan dan bisa pindah belakangan. Ini bersinggungan dengan `[BL-067]` (isi paket tidak terlihat sebelum orang mendaftar) — sebaiknya dijawab sekali untuk keduanya.
  3. **Apakah "Daftar Gratis" di landing masih kalimat yang benar.** Ia tidak salah — dua bulan memang gratis — tapi ia menyembunyikan bagian yang paling menentukan.
- **Usulan Perbaikan:**
  **(a)** Kerjakan **setelah** ketiga keputusan di atas dijawab; kalau tidak, yang dihasilkan cuma kalimat yang harus ditulis ulang.
  **(b)** Apa pun bentuknya, sumber angkanya `SubscriptionService::trialMonths()` + `Plan::postTrialTarget()`, dikirim sebagai prop dari `showRegister()`. Jangan menyalin angka ke Vue.
  **(c)** Satu test yang mengunci ini: ubah paket tujuan lewat panel, lalu pastikan halaman daftar ikut menyebut paket yang baru. Tanpa itu, penanda yang bisa dipindah dari panel akan kembali jadi teks mati pada penulisan ulang berikutnya.
  **(d)** Tinjau juga apakah `business_type` masih layak `nullable` sekarang setelah ia jadi dimensi harga sungguhan — di luar lingkup entri ini kalau ternyata besar, tapi ia ada di formulir yang sama.
- **Keputusan pemilik 2026-08-15, ketiganya:**
  1. **Kalimat informatif**, bukan kotak centang wajib. Tidak ada yang dicatat; persetujuan jalur harga tetap terpisah di `/langganan/persetujuan`.
  2. **Paket tidak dipilih saat mendaftar.** Semua tetap masuk lewat masa gratis, dan halaman daftar hanya memberitahu paket tujuannya — `is_post_trial_target` karenanya tetap penanda global, bukan pilihan per tenant.
  3. **"Daftar Gratis" diganti** jadi "Coba Gratis N Bulan", angkanya dari `trial_months`.
- **Hasil:** `PublicPricing::trialNotice()` jadi satu-satunya sumber angkanya, dikirim `showRegister()` sebagai prop `trial`. Butir (c) terjaga oleh test yang mengubah penanda paket tujuan lewat endpoint panel lalu memastikan halaman daftar ikut berubah. Sisa `[BL-032]` (empat CTA landing yang masih menunjuk `/login`) ikut ditutup karena barisnya memang sedang disentuh. Rinciannya di entri CHANGELOG `[ADDITION] Halaman Daftar Menyebut Masa Gratis yang Berakhir dengan Tagihan (BL-071)`.

---

### [BL-041] Tarif Belum Ditetapkan, dan Halaman Harga Publik Belum Dinamis
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "Pemberian Kelas, dan sesuai Omset / Sesuaikan Tarif kembali", "berikan halaman pricing dan dynamic, perjelas batasan batasan dari owner yang membayar full, subsidi dan lain lain"
- **Status:** **Selesai 2026-08-14** — butir (a) 2026-08-07 (angkanya ditetapkan dan terpasang), butir (c) 2026-08-14 (halaman `/harga` berdiri), butir (b) 2026-08-14 (kelas harga jalur Harga Tetap).
- **Prioritas:** Medium — turun dari High: yang tersisa bukan lagi penghalang penagihan, melainkan penjelasan harga di layar
- **Butir (a) terjawab 2026-08-07 — angkanya ditetapkan, dan dua di antaranya bukan yang diusulkan siapa pun sebelumnya.**
  Tabel final ada di blok "Keputusan pemilik 2026-08-07" di kepala berkas ini. Yang perlu dicatat di sini adalah **apa yang berubah dari rencana**, karena itulah yang tidak bisa disimpulkan dari tabelnya:
  - **Paket `dasar` mati sebagai tier berbayar.** Ia berganti nama jadi `free` dan berubah sifat: bukan lagi tempat tinggal termurah, melainkan **masa gratis dua bulan** yang berakhir dengan perpindahan paksa ke `paid-1`. Usulan sebelumnya (memberi `dasar` tarif Rp 49.000) dibatalkan pemilik setelah ditimbang ulang. Akibatnya tangga harganya jadi Rp 0 → 100k → 150k → 200k, tanpa anak tangga di lompatan terberatnya — dan **jalur Adaptif-lah jembatannya**, yang menaikkan taruhan `[BL-055]` jauh di atas perkiraan awal.
  - **Bracket D turun dari Rp 100.000 ke Rp 75.000.** Bukan penyesuaian pasar. Karena Adaptif didefinisikan sebagai `paid-1` yang didiskon, D yang berharga sama dengan `paid-1` berarti **diskon 0%** — tenant menyerahkan data penjualannya dan menerima nol. Itu bukan sekadar sia-sia, itu ongkos privasi tanpa imbalan. Rinciannya di `[BL-048]`.
  - **Jatah seat `free` naik 1 → 2**, lewat migrasi, bukan lewat panel. Satu seat berarti pemilik toko satu-satunya yang bisa masuk — tidak ada kasir. Untuk aplikasi POS itu bukan paket terbatas melainkan paket yang tidak bisa dipakai, dan selama dua bulan pertama paket inilah wajah produknya.
  - **Kuota AI diisi eksplisit di keempat paket (5/15/30/60).** Sebelumnya hanya `Premium 1` yang menyetelnya; `Premium 2` dan `3` bernilai `null` sehingga ikut bawaan platform **5/hari** — artinya Rp 200.000 memberi kuota AI yang sama persis dengan paket gratis, dan **separuh** dari paket Rp 100.000 di bawahnya. Tangga harganya naik sementara nilainya turun. Ini cacat data, bukan keputusan, dan sudah diperbaiki berbarengan.
  - ~~**Yang TIDAK ikut ditetapkan:** harga seat tambahan sebagai biaya **bulanan**.~~ **Beres 2026-08-08** (`[BL-053]`): keempat angka itu (20k/15k/12,5k/10k) kini benar-benar ditagih per bulan sebagai komponen tagihan langganan. Nilainya dan satuannya sudah sejalan.
  - **Keadaan terpasang per 2026-08-07 (query, bukan dugaan):** `free` Rp 0 / 2 seat / seat+ Rp 20.000 / AI 5 · `paid-1` Rp 100.000 / 3 / Rp 15.000 / AI 15 / **penampung adaptif** · `paid-2` Rp 150.000 / 5 / Rp 12.500 / AI 30 · `paid-3` Rp 200.000 / 10 / Rp 10.000 / AI 60. Bracket A–D 10k/25k/50k/**75k**, D ditutup di `< 50.000.000`. Jejaknya di `platform_audit_logs` (`plans.update` ×4, `pricing-rules.create` ×1). **Basis data lokal pengembangan**, bukan produksi.
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
  - ~~`database/migrations/2026_07_24_181634_create_plans_table.php:38-47` — paket `dasar`: `base_price = 0`, `included_seats = 1`, `extra_seat_price = 0`~~ — di-rename `free` dengan 2 seat oleh `2026_08_07_110647_rename_default_plan_to_free`
  - ~~`config/subscription.php` — benih bracket A–D: Rp 10k / 25k / 50k / 100k~~ — bracket D jadi Rp 75k berbatas Rp 50 jt, 2026-08-07
  - `resources/views/public/landing.blade.php:686-745` — yang dipajang publik: Rp 149k dan Rp 299k
  - `app/Http/Controllers/Billing/SubscriptionController.php:68-70` — `bracket` diisi **hanya** bila `isSubsidized()`; tenant jalur Harga Tetap tidak pernah melihat kelas apa pun
  - ~~`routes/web.php:390-398` — rute publik hanya `/`, `/api-docs`, `/dokumentasi`; tidak ada halaman harga tersendiri~~ — **beres 2026-08-14**, `/harga` berdiri lewat `Public\PricingController`
- **Kosakata (pemutakhiran 2026-08-01):** sejak `[DECISION] Jenis Usaha Berpindah ke Pemilik Toko` (2026-07-31), istilah yang dibaca orang adalah **Harga Tetap** (dulu "normal") dan **Harga Adaptif** (dulu "subsidi"). Nilai di basis data **tidak** ikut berganti — `pricing_track` tetap `normal`/`subsidized`, begitu pula `TenantConsent::TYPE_SUBSIDIZED`. Entri ini memakai istilah barunya untuk hal yang dilihat pengguna dan nama kolom aslinya untuk hal yang menyentuh kode.
- **Deskripsi:**
  Mesin "kelas sesuai omzet" yang diminta sebenarnya **sudah ada dan sudah berjalan**: `pricing_rules` bisa di-CRUD dari `/platform/pricing-rules`, dimensinya bebas (omzet, jumlah transaksi, seat aktif, tipe usaha), dan bracket A–D sudah tertanam sejak migrasi. Tiga hal yang belum ada: (1) **angkanya** — jalur Harga Tetap memakai paket ber-`base_price` 0, jadi tenant yang membayar penuh secara harfiah tertagih nol; (2) **kelas untuk jalur Harga Tetap** — bracket hanya dihitung untuk tenant Harga Adaptif, sehingga tenant bayar-penuh tidak punya penjelasan mengapa tarifnya sekian; (3) **halaman harga publik** yang membacakan aturan yang berlaku, bukan HTML yang ditulis tangan.
- **Keadaan data per 2026-08-01 (hasil query, bukan dugaan):** 2 tenant, keduanya paket `Dasar` dengan `base_price = 0.00`, `extra_seat_price = 0.00`, dan `price_locked = 0.00`; keduanya di jalur `normal`. Tabel `invoices` **kosong — belum pernah ada satu tagihan pun terbit**. `pricing_rules` berisi 4 baris: bracket A–D bawaan migrasi, yang hanya terpakai di jalur Harga Adaptif, sementara **0 tenant** ada di jalur itu. Artinya seluruh mesin penagihan berdiri lengkap dan belum pernah menagih siapa pun — dan itu bukan keadaan yang bisa dilihat dari layar mana pun.
- ~~**Jendela yang akan tertutup:**~~ **terpakai 2026-08-05.** `[BL-030]` sudah ditutup selagi `invoices` masih kosong, jadi tidak ada tanggal tagih siapa pun yang bergeser. Butir (a) di bawah kini bebas dijalankan kapan pun tanpa menyeret pekerjaan data.
- **Usulan Perbaikan:**
  ~~**(a)** Pemilik menetapkan tarif jalur Harga Tetap dan meninjau ulang bracket Harga Adaptif.~~ **Selesai 2026-08-07**, lihat blok di atas. Dua yang tersisa:
  **(b)** Perluas `bracket` di `SubscriptionController` agar juga terisi untuk jalur Harga Tetap memakai dimensi yang tidak butuh consent (seat aktif, tipe usaha), sehingga tiap owner bisa melihat kelasnya sendiri tanpa membuka data penjualan yang tidak pernah ia setujui — batas privasi di `config/pricing-dimensions.php` harus tetap dihormati.
  ~~**(c)** Buat `/harga` yang merender aturan berlaku dari `PricingService`, dengan tabel perbandingan tegas antara Harga Tetap dan Harga Adaptif.~~ **Selesai 2026-08-14** — lihat `[ADDITION] Halaman /harga Membacakan Kedua Jalur Tarif kepada Orang yang Belum Mendaftar (BL-041 butir c)` di `docs/CHANGELOG.md`.
  **Pemutakhiran 2026-08-10 — separuhnya sudah berdiri, dan yang tersisa justru bagian publiknya.** `[BL-055]` mendarat dengan `/langganan/harga-adaptif`: tangga bracket, ambang, tarif berjalan, dan tanggal penerapannya, semuanya dibaca dari `pricing_rules` lewat `PricingService::adaptiveLadder()`. Yang belum ada tinggal **halaman `/harga` publik** — dan komponen datanya kini tidak perlu ditulis dua kali: `adaptiveLadder()` dan `adaptiveCeiling()` tidak menyentuh tenant sama sekali, jadi keduanya bisa dipanggil dari halaman tanpa sesi apa adanya. Yang masih harus diputuskan untuk halaman publik adalah tabel perbandingan jalur, bukan angkanya.
- **Koreksi 2026-08-07 — klaim *grandfathering* di entri ini SALAH, dan koreksinya penting.**
  Entri ini dulu menyatakan `price_locked` "akan mempertahankan angka nol itu apa adanya". Penguncian harganya memang nyata — `InvoiceSettlement` menulisnya dari nominal yang benar-benar dibayar (`InvoiceSettlement.php:91`) — tapi **tidak ada satu pun pembaca di jalur penagihan.** `issueDuePeriodInvoices()` selalu menghitung ulang lewat `resolveFor()` dan memakai hasilnya apa adanya (`SubscriptionService.php:317`); `price_locked` hanya dibaca dua tempat, keduanya untuk tampilan layar (`Billing\SubscriptionController:38`, `AccountOverview:181`).
  Artinya nominal manual **tidak pernah bertahan ke bulan berikutnya** — tagihan otomatis kembali ke harga aturan. Kebetulan itu justru **persis perilaku yang diminta** keputusan 2026-08-07 untuk input manual (berlaku sebulan saja), jadi yang dulu terlihat sebagai cacat sekarang jadi separuh fitur. Yang belum ada tinggal kolom alasan wajibnya — `[BL-057]`.
- **Dua tenant lama memegang `price_locked = 0.00`, dan itu belum diputuskan.** — **Diputuskan 2026-08-10: nol adalah cacat data, bukan grandfathering.** Yang membuatnya mendesak bukan barisnya melainkan layarnya: `price_locked ?? base_price` tidak pernah jatuh ke tarif paket karena kolomnya desimal, jadi `Kopi Story` (kini `paid-1`) membaca "Rp 0/bulan" di `/langganan` sementara penerbit menyiapkan Rp 100.000. `InvoiceSettlement::settle()` berhenti menulis nol, dan `Subscription::effectivePrice()` membacanya sebagai kosong. Baris nol yang sudah ada sengaja tidak dinormalkan lewat migrasi — sejak pembacaannya benar, ia tidak menyesatkan siapa pun lagi. Lihat `[HOTFIX] price_locked Nol Berhenti Dibaca Sebagai Tarif Rp 0 (BL-041)` di `docs/CHANGELOG.md`. Keduanya tenant demo/awal, bukan kesepakatan yang perlu dihormati selamanya. Karena penagih tidak membaca `price_locked`, angka nol itu **tidak** akan menular ke tagihan berikutnya — yang menentukan nasib mereka adalah paketnya, dan keduanya masih di `free` seharga Rp 0. Lihat `[BL-052]`. — **Sudah tidak berlaku sejak 2026-08-07**: keduanya dipindahkan ke `paid-1` dan kini resolve ke Rp 100.000; `[BL-052]` selesai 2026-08-08, jadi tenant gratis berikutnya berpindah sendiri di akhir masa gratisnya. Yang tersisa dari catatan ini hanya `price_locked = 0.00` yang masih menganggur di kedua baris itu.
- **Catatan penutup butir (b), 2026-08-14.** Mesinnya ternyata sudah ada seluruhnya: sejak `[BL-015]`, `resolveFor()` mencocokkan SELURUH dimensi, dan `DimensionRegistry::valueFor()` sudah memadamkan dimensi ber-consent bagi tenant yang tidak menyetujuinya — sehingga aturan berdimensi seat/tipe usaha memang sudah berlaku untuk tenant jalur Harga Tetap. Yang tidak ada hanyalah jalan membacanya ke layar: `SubscriptionController` mengunci `bracket` di balik `isSubsidized()`. Yang ditambahkan karenanya bukan mesin baru melainkan `PricingService::classificationFor()` dan satu kartu di `/langganan`. Kunci `bracket` **tidak** dipakai ulang meski entri ini menulis "perluas `bracket`": bentuknya memuat `period` dan `revenue` yang tidak berlaku di jalur Harga Tetap, dan halaman itu membacanya sebagai "Dihitung dari omzet ...". Kunci barunya `classification`, dan hanya terisi untuk tenant jalur Harga Tetap.

### [BL-066] Cara Kerja Dynamic Pricing Tidak Terjelaskan di Satu Pun Permukaan Publik
- **Ditemukan:** 2026-08-08
- **Sumber:** Saran pasca-peragaan — "perbaiki penjelasan dari sistem dynamic pricing lagi, masukkan dalam landing page cara kerjanya"
- **Status:** **Selesai 2026-08-14** — butir (a), (c), (d), (e) dikerjakan utuh; butir (b) dikerjakan dua dari tiga (lihat catatan penutup).
- **Prioritas:** **High sejak 2026-08-10** — `[BL-055]` sudah mendarat, jadi tenant kini benar-benar bisa mengajukan Adaptif sendiri. Halaman `/langganan/harga-adaptif` menjelaskannya kepada yang **sudah masuk**; yang belum ada adalah penjelasan bagi yang belum mendaftar
- **Bergantung pada:** `[BL-032]` (landing sudah harus ditulis ulang) dan angka final `[BL-041]` — keduanya menyentuh bagian halaman yang sama
- **Area Terdampak:**
  - `resources/views/public/landing.blade.php` — pencarian `dynamic|dinamis|adaptif|subsidi`: **nol hasil**. Bagian harga masih memajang dua paket karangan (lihat `[BL-032]`)
  - `docs/SAPI-Pitch-Fitur-Unggulan_v1.0.md:90-127` — penjelasan yang benar dan lengkap **sudah ditulis**, tapi dokumen internal
  - `config/docs.php:52-55` — halaman panduan `langganan` sudah terdaftar dengan ringkasan yang menyebut jalur Harga Adaptif
  - `resources/docs/panduan/langganan.md` — isinya perlu diperiksa ulang terhadap keputusan 2026-08-07 (dua bulan gratis, tangga 90/75/50/25, ambang Rp 50 jt)
  - `config/subscription.php` — bracket A–D beserta angkanya, sumber kebenaran yang harus dirujuk penjelasannya
- **Deskripsi:**
  Calon klien yang membuka landing page tidak menemukan satu kata pun tentang mekanisme yang justru jadi pembeda produk ini: tarif langganan yang mengikuti omzet, dihitung otomatis dari transaksi dan **bukan dilaporkan sendiri**, sebagai imbalan atas consent yang eksplisit. Penjelasannya sudah ada dan sudah bagus — tapi hidup di dokumen pitching internal, bukan di halaman yang dibaca orang.
  Yang membuat ini lebih dari sekadar salinan-tempel: begitu `[BL-055]` mendarat, tenant mengajukan Adaptif **sendiri** sambil menyerahkan consent atas data penjualannya. Meminta orang menyetujui itu tanpa halaman publik yang menjelaskan apa yang dilihat, untuk apa, dan apa yang tidak dilihat — adalah cara tercepat membuat pengajuan itu ditolak atau, lebih buruk, disetujui tanpa dipahami.
- **Usulan Perbaikan:**
  **(a)** Satu bagian "Bagaimana harga Anda dihitung" di landing, tiga langkah dan bukan paragraf: omzet bulan lalu dihitung otomatis → jatuh ke salah satu bracket → tarif bulan itu. Sebutkan angkanya apa adanya (10k/25k/50k/75k, dan ≥ Rp 50 jt tidak layak) — tangga bracket itu **memang daftar harga sesungguhnya**, sesuai catatan 2026-08-07, jadi menyembunyikannya tidak ada gunanya.
  **(b)** Tulis eksplisit apa yang **tidak** terjadi: platform tidak mengintip transaksi per item, tidak ada laporan mandiri yang bisa dicurangi, dan tarif yang sudah dibayar terkunci (`price_locked`). Tiga kalimat itu menjawab keberatan yang pasti muncul lebih baik daripada satu halaman fitur.
  **(c)** **Angkanya ditarik dari `pricing_rules`, jangan diketik di HTML** — ini syarat yang sama dengan `[BL-032]`(2), dan alasannya sama: halaman harga yang berbeda dari tagihan sungguhan adalah cacat terburuk yang bisa dimiliki halaman harga.
  **(d)** Perdalam `resources/docs/panduan/langganan.md` sebagai versi panjangnya, dan tautkan dari landing. Landing menjawab "kira-kira saya bayar berapa"; panduan menjawab "kalau omzet saya turun bulan depan, bagaimana".
  **(e)** Periksa istilahnya konsisten. Dokumen internal memakai "Dynamic Pricing" (nama mesinnya), UI memakai "Harga Adaptif" vs "Harga Tetap" (nama jalurnya), dan `[BL-018]` memakai "harga dinamis" untuk hal yang sama sekali berbeda — diskon barang mendekati kedaluwarsa. **Jangan pakai "dynamic pricing" di permukaan tenant** sebelum tabrakan istilah itu diselesaikan.
- **Catatan penutup 2026-08-14 — satu klaim di butir (b) DIBATALKAN karena tidak benar.** Butir itu meminta menuliskan "tarif yang sudah dibayar terkunci (`price_locked`)". Diperiksa terhadap kode: penerbit tagihan tidak pernah membaca `price_locked` — `issueDuePeriodInvoices()` selalu menghitung ulang lewat `PricingService::resolveFor()` (`SubscriptionService.php:486`), dan `price_locked` hanya dibaca untuk tampilan. `[BL-041]` sendiri sudah mengoreksi klaim *grandfathering* ini pada 2026-08-07; entri ini rupanya ditulis sebelum koreksi itu dan tidak ikut diperbarui. Menuliskannya di landing berarti menjanjikan yang tidak dilakukan sistem — tenant yang kelasnya naik bulan depan **akan** ditagih tarif kelas barunya. Yang ditulis sebagai gantinya adalah pernyataan yang benar: tagihan yang **sudah terbit** membekukan dasar perhitungannya di `invoices.pricing_context`, jadi ia tidak berubah surut. Ada test yang menjaga janji penguncian itu tidak masuk kembali.

### [BL-067] Isi Paket — Berapa Seat, Berapa Kuota AI — Tidak Terlihat Sebelum Orang Mendaftar
- **Ditemukan:** 2026-08-08
- **Sumber:** Saran pasca-peragaan — "perbaiki juga include seats atau tambahan kuota akun"
- **Status:** **Selesai 2026-08-14** (butir (a)–(e)) — sisi tagihannya punya entri sendiri, lihat di bawah
- **Prioritas:** Medium
- **Yang TIDAK termasuk entri ini:** seat jadi komponen bulanan dan jalan melepas seat = `[BL-053]` (selesai 2026-08-08); kuota AI yang bisa dibeli = `[BL-069]`; tagihan Rp 0 saat menambah pengguna = `[BL-049]`. Entri ini murni tentang **keterlihatannya**, jangan dikerjakan sebagai duplikat ketiganya.
- **Area Terdampak:**
  - `resources/views/public/landing.blade.php` — bagian harga tidak menyebut seat maupun kuota AI sama sekali (dan paketnya sendiri karangan, `[BL-032]`)
  - `app/Http/Controllers/Auth/AuthController.php:35-47` — pendaftaran tidak menampilkan isi paket apa pun
  - `resources/js/Pages/Billing/Show.vue:395-435` — **satu-satunya** tempat yang menjelaskannya dengan benar: harga per pengguna tambahan /bulan, "N dari M" pengguna aktif, plus bar pemakaian
  - `resources/js/Components/AiQuotaMeter.vue` — kuota AI kini tampil di Pengaturan (versi lengkap) **dan** di AI Analysis (versi ringkas), sejak `[BL-062]` selesai 2026-08-13. Yang masih kurang untuk entri ini: keduanya hanya bicara kuota AI, tidak pernah menyebut seat, dan tidak satu pun terlihat sebelum orang jadi tenant
  - `app/Models/Plan.php:35` — `included_seats` + `extra_seat_price` sudah jadi kolom paket; `limits.ai_daily` sudah jadi batas kuota per paket
- **Deskripsi:**
  Datanya lengkap dan sudah rapi di model: tiap paket tahu berapa seat bawaannya, berapa harga seat tambahannya, dan berapa jatah AI hariannya (keputusan 2026-08-07: 2/5, 3/15, 5/30, 10/60). Yang tidak ada adalah satu pun permukaan yang memperlihatkan itu **sebelum** orang jadi tenant. Calon klien memilih paket tanpa tahu berapa kasir yang boleh dipakainya; pendaftar baru menemukan batasnya saat menambah staf ketiga dan ditolak.
  Di dalam aplikasi pun keterangannya tersebar: seat di `/langganan`, kuota AI di `/pengaturan`, dan tidak ada satu tempat yang menjawab "paket saya dapat apa saja".
- **Usulan Perbaikan:**
  **(a)** Tabel perbandingan paket di landing yang barisnya diambil dari `plans` — nama, harga, seat bawaan, kuota AI/hari, harga seat tambahan. Satu sumber dengan `[BL-032]`(2) dan `[BL-066]`(c); jangan dibuat sebagai tabel HTML ketiga yang bisa basi sendiri.
  **(b)** Ringkasan isi paket di halaman `/langganan`, satu blok, mencakup **keduanya** — bukan seat saja seperti hari ini.
  **(c)** Pakai istilah yang sudah diputuskan 2026-08-07: **"seat bawaan paket"** vs **"seat tambahan"**. Jangan memperkenalkan kata ketiga di permukaan publik.
  **(d)** Untuk kuota AI, sebutkan juga **apa yang terjadi saat habis** (analisis ditolak sampai besok) dan bahwa BYOK melepas batas itu. Batas yang tidak dijelaskan konsekuensinya akan dibaca sebagai batas keras yang memutus fitur.
  **(e)** **Jangan menjanjikan "tambah kuota AI" di landing sebelum `[BL-069]` ada.** Alur belinya belum berbentuk sama sekali — tidak ada kolom, tidak ada tagihan, tidak ada layar.

### [BL-057] Harga Khusus per Tenant: Dropdown Mengunci ke Depan, Input Manual Sebulan Saja — Alasannya Belum Wajib
- **Ditemukan:** 2026-08-07
- **Sumber:** Keputusan pemilik 2026-08-07 — "bisa pilih berdasarkan paket yang ada (dropdown)... atau saya input manual dan paksa berikan komentar atau alasan"
- **Status:** **Selesai 2026-08-14** (butir (a) dan (b) terpasang; butir (c) adalah larangan dan memang tidak menuntut kode)
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Http/Controllers/Platform/InvoiceController.php` — `store()`: `amount` bebas dengan `min:0`, **tanpa kolom alasan**
  - `app/Http/Controllers/Platform/InvoiceController.php` — jejak audit sudah mencatat `follows_rule`
  - `app/Http/Controllers/Platform/SubscriptionController.php` — `updatePlan()`: dropdown paket, `reason` **sudah wajib**
- **Deskripsi:**
  Dua mekanisme yang diminta pemilik ternyata sebagian besar sudah ada, dan yang dulu terlihat sebagai cacat justru jadi fiturnya:
  - **Dropdown paket = mengunci ke depan.** `changePlan()` + `PUT /platform/subscriptions/{id}/plan` sudah ada, `reason` sudah wajib, jejaknya sudah `SEVERITY_SENSITIVE`. **Tidak ada pekerjaan.**
  - **Input manual = berlaku sebulan saja.** Penerbit manual sudah menerima nominal bebas, dan `issueDuePeriodInvoices()` **tidak pernah membaca `price_locked`** — jadi bulan berikutnya otomatis kembali ke harga aturan. Itu persis yang diminta. Lihat koreksi di `[BL-041]`.
  Yang benar-benar belum ada tinggal satu: **kolom alasan wajib** pada penerbit manual, khususnya ketika nominalnya menyimpang dari aturan.
- **Usulan Perbaikan:**
  **(a)** Tambahkan `reason` di `Platform\InvoiceController::store()`, wajib **ketika `follows_rule` bernilai false** — memaksa alasan untuk tagihan yang persis mengikuti aturan hanya melatih orang mengetik "sesuai aturan" tanpa membacanya. Deteksinya sudah ada di controller, tinggal dipakai sebagai syarat validasi.
  **(b)** Alasannya masuk `meta` jejak audit berdampingan dengan `follows_rule`, dan ikut terlihat di layar tagihan tenant — nominal yang berbeda dari daftar harga tanpa penjelasan adalah pertanyaan yang pasti datang.
  **(c)** Jangan menambahkan kolom "harga khusus permanen" per tenant. Pemilik sudah memutuskan harga tetap datang dari paket; harga khusus yang berdiri sendiri berarti membuat paket bayangan yang tidak muncul di daftar mana pun. Bila suatu tenant memang perlu harga tetap yang lain, yang benar adalah **membuat paket baru** — ia auditable dan muncul di panel.
- **Catatan penutup:** butir (b) menuntut keputusan yang tidak tertulis di entri ini — jejak audit milik platform, jadi "ikut terlihat di layar tagihan tenant" berarti kolom basis data baru, bukan sekadar menampilkan sesuatu yang sudah tersimpan. Pemilik memilih **opsi A**: satu kolom `invoices.amount_reason` yang memang dibaca tenant, dengan formulirnya menyatakan itu terus terang saat alasannya diketik. Opsi B (alasan internal saja, tanpa migrasi) ditolak karena yang bertanya "kenapa tagihan saya beda" adalah tenant.

### [BL-039] "Profil Usaha" Mencampur Merek, Aturan Kerja, Kapabilitas, dan Kredensial
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "pada profile usaha dan setting usaha, pisahkan bersifat brand usaha, cara kerja sistem, sampai ke api key dll settingannya pisahkan semua"
- **Status:** **Selesai 2026-08-14** (seluruh butir kecuali unggah logo struk, yang ditahan menunggu `[BL-076]`)
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
- **Ditutup 2026-08-14.** Dipecah jadi tiga halaman + tiga endpoint: `owner.settings.*` (Profil & Merek), `owner.settings.operations.*` (Cara Kerja Sistem), `owner.settings.integrations.*` (Integrasi & Kredensial). `Owner\SettingsController` dihapus, diganti tiga controller di `app/Http/Controllers/Owner/Settings/`. Jenis usaha mendarat di Profil & Merek beserta kalimat dampak tarifnya, seperti yang diminta entri ini.
  **Dua hal yang sengaja ditinggalkan:** (1) **unggah logo struk** — `logo` ternyata kolom mati (hanya ada di `$fillable` `Tenant`, tidak dipakai di mana pun), jadi membangunnya berarti fitur baru yang menabrak `[BL-076]`; (2) **nama usaha tetap tidak bisa diedit**, karena membukanya menyeret `slug` dan itu keputusan tersendiri.
  Detail lengkapnya di `[REFACTOR] "Profil Usaha" Pecah Jadi Tiga Halaman dan Tiga Endpoint (BL-039)` di `docs/CHANGELOG.md`.

### [BL-038] Halaman Staf Tidak Menunjukkan Modul Efektif Per Orang
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "dalam konsep tim dan akses, staf dan role konsepnya dia langsung melihat kalau orang ini (email ini) memiliki akses ke module module berikut"
- **Status:** **Selesai 2026-08-14** (seluruh butir; baris owner ditambahkan atas permintaan pemilik saat entri ini dikerjakan)
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
- **Catatan penutup:** usulan aslinya menulis "untuk owner, tampilkan satu lencana Akses penuh" tanpa menyebut di mana barisnya berada — dan ternyata tidak ada: kueri staf menyaring `role = 'cashier'`, jadi owner tidak pernah punya baris untuk ditempati. Yang dikerjakan karena itu bukan mengubah lencana, melainkan menambahkan baris owner (jamak — dibaca dari kolom `role`, bukan dari user yang sedang masuk) sebagai baris hanya-baca di atas daftar staf.

### [BL-033] Tiga Permukaan Publik Belum Satu Keluarga — Desain, Aset, dan Wordmark
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "dokumentasi publik dan dokumentasi teknis seperti ai dan api kasih konsisten", "konsep logo SAPI Pos seperti di halaman dokumentasi menarik"
- **Status:** **Selesai 2026-08-14** (seluruh butir)
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

### [BL-070] Membeli Seat di Tengah Periode Gratis Sampai Periode Habis — Prorata Ditunda, Bukan Ditolak
- **Ditemukan:** 2026-08-08 (sisa `[BL-053]` butir (b)(2), sengaja dipisahkan untuk dipikirkan ulang)
- **Sumber:** Keputusan pemilik 2026-08-08 — "gratis sisa periode", dipilih sadar sebagai yang paling sederhana, bukan sebagai yang paling adil
- **Status:** **Selesai 2026-09-07** — bentuk **(a)** dipilih pemilik dan diterapkan. Teks di bawah ini adalah keadaan entri saat masih terbuka; lihat catatan penutup di akhir untuk apa yang akhirnya dikerjakan dan apa yang ternyata lebih rusak daripada yang tercatat di sini.
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

- **Pemutakhiran 2026-09-06 — ambangnya dicoba diukur, dan penyebutnya nol.** `platform_audit_logs` **tidak memuat satu pun** `subscriptions.seats-granted` (120 log, rentang 2026-07-21 s/d 2026-08-15). Dua event seat yang ada bukan pembelian: satu `subscriptions.seats.update` (2026-07-31, pemberian manual lewat konsol platform, alasan tercatat "perjanjian pada saat pertama, dan benefit tester beta") dan satu `subscriptions.seats-release-scheduled` (2026-08-15). `purchased_extra_seats = 3` pada kedua langganan pun bukan jejak pembelian melainkan backfill migrasi `2026_08_08_073124_add_purchased_seats_to_subscriptions_table.php:68` (`seats − included_seats`, dihitung sekali saat kolomnya dibuat). Jalur pembeliannya sendiri hidup dan tersambung — `UpgradeController.php:55` → `grantSeats()` — hanya belum pernah dilewati siapa pun.
  **Yang berubah untuk entri ini: tidak ada, kecuali alasan menahannya jadi terukur.** "Sebarannya masih merata" dan "belum ada sebaran sama sekali" menghasilkan keputusan yang sama — biarkan — tapi hanya yang kedua yang benar hari ini. Membedakannya penting supaya peninjau berikutnya tidak membaca nol sebagai bukti bahwa polanya aman; yang benar adalah ambang di atas belum bisa dijawab, bukan sudah dijawab "tidak".
  **Catatan untuk pengukuran berikutnya:** query sebaran yang membandingkan `DAY(created_at)` terhadap `billing_anchor_day` lewat `MOD(selisih + 30, 30)` cukup sebagai indikator kasar, tapi **meleset di bulan pendek dan pada anchor day di atas 28**. Jangan dipakai sebagai dasar perhitungan kalau nanti bentuk (a) atau (b) dipilih — di sana tanggalnya harus diturunkan dari `current_period_start`/`current_period_end` yang sebenarnya, seperti `releaseSeats()` sudah lakukan lewat `nextAnchoredDateAfter()`.

- **Catatan penutup 2026-09-07 — bentuk (a), dan satu celah yang entri ini tidak lihat.**
  Pemilik memilih **(a) prorata per hari**. Yang membalik perbandingan ongkos di atas adalah temuan bahwa (a) dan (b) membutuhkan **state baru yang sama persis**: `purchased_extra_seats` hanya cacahan, dan tidak ada satu kolom pun yang menyimpan KAPAN sebuah seat dibeli — satu-satunya jejak tanggalnya ada di `platform_audit_logs`, tempat yang tidak boleh jadi sumber kebenaran penagihan. Begitu state itu ada, prorata per hari cuma beberapa baris lebih banyak daripada paruh-periode, sehingga penghematan (b) sebagian besar semu.
  **Rumusnya bukan "sisa periode berjalan", melainkan "hari yang belum tertutup tagihan penuh mana pun".** Keduanya sama untuk kasus biasa dan berbeda untuk kasus yang paling merugikan: tagihan periode berikutnya terbit `invoice_lead_days` SEBELUM periode berjalan habis, dan penjaga periode-ganda menolak tagihan kedua untuk periode yang sama — jadi seat yang dibeli di dalam jendela itu baru tertagih penuh DUA periode kemudian. Kerugiannya karena itu bukan "paling banyak satu periode per seat" seperti tertulis di atas, melainkan bisa hampir dua. Rumus yang dipakai menutupnya sendiri, dan menghasilkan pecahan di atas 1 justru ketika memang seharusnya.
  **Ambang peninjauan di atas tidak pernah terjawab, dan itu disengaja.** Pengukuran 2026-09-06 menemukan nol baris `subscriptions.seats-granted` — penyebutnya nol, bukan sebarannya merata. Keputusan mengerjakan ini diambil tanpa menunggu data, sadar bahwa ia menyalip prioritas `Low` yang entri ini sendiri tetapkan.
  Bentuk **(b)** dan **(c)** karena itu tidak jadi dipakai. Peringatan **"yang jangan dilakukan"** tetap berlaku dan tetap dipatuhi: tidak ada tagihan tersendiri di tengah periode, `KIND_UPGRADE` tidak dihidupkan kembali.

---

### [BL-035] Paket Setelan Awal per Cara Berjualan — "Mode Bazar" Ternyata Bukan Mode
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "mode bazar atau tenant khusus untuk jualan di cfd, event, dll"
- **Status:** **Selesai 2026-09-07** — mendarat sebagai paket setelan awal per cara berjualan, bukan sebagai mode. Lihat `[ADDITION] "Mode Bazar" Mendarat Sebagai Paket Setelan, Bukan Mode` di `docs/CHANGELOG.md`.
- **Prioritas:** Medium
- **Area Terdampak:**
  - Seluruh repo — pencarian `bazar|bazaar` hanya menemukan **satu** kecocokan: `docs/phases-2/PHASE-QUEUE_Kitchen-Order-Queue.md:56`, dan itu pun menyebutnya sebagai sasaran akhir, bukan fitur ("Sasaran akhirnya tetap kaki lima/bazar")
  - `app/Models/Tenant.php:80-88` — tempat kapabilitas baru akan bergabung bila mode ini jadi capability flag
- **Deskripsi:**
  Tidak ada kolom, flag, konfigurasi, maupun rencana tertulis untuk mode bazar. Yang belum terjawab bukan soal teknis melainkan soal produk: **apa yang berubah** saat mode ini aktif? Kandidat yang masuk akal dari catatan pemilik — tagihan terbuka dimatikan (jualan selalu lunas di tempat), nomor antrian ditonjolkan, stok disederhanakan jadi hitungan terpakai/sisa per hari acara, dan penekanan pada operasi offline. Ada juga pertanyaan yang bersinggungan dengan `[BL-031]`: kalau tidak ada open bill, umur tagihan tidak perlu diputuskan untuk mode ini.
- **Usulan Perbaikan:**
  Putuskan dulu daftar perbedaannya, lalu wujudkan sebagai capability flag lewat `Tenant::hasFeature()` seperti `kitchen_queue` — bukan sebagai `business_type` baru, karena `business_type` milik penetapan harga dan membeku per tagihan. Sesudah itu barulah `[BL-034]` bisa menawarkan preset "bazar" yang berarti sesuatu, dan `[BL-036]` bisa memperagakannya.

- **Pemutakhiran 2026-08-15 — tempat mendaratnya sudah ada, tinggal keputusannya.** `[BL-034]` selesai tanpa menunggu entri ini: mekanisme presetnya utuh dan berjalan untuk keempat jenis usaha yang ada. Yang berubah untuk entri ini adalah biaya menyelesaikannya. Dulu "mode bazar" berarti memutuskan definisinya **dan** membangun jalan agar ia sampai ke tenant baru. Sekarang jalannya sudah ada: `config/business-presets.php` memetakan jenis usaha ke daftar kapabilitas, dan pendaftar melihatnya sebagai daftar centang yang bisa ia ubah. Begitu daftar perbedaan mode bazar diputuskan dan diwujudkan sebagai flag di `Tenant::hasFeature()`, menyalakannya untuk tenant bazar baru adalah **satu baris tambahan di config** — bukan penyuntingan alur pendaftaran. Yang tersisa di entri ini murni pertanyaan produk, persis seperti judulnya bilang.

- **Pemutakhiran 2026-09-06 — pemilik membalik bentuknya: ini paketan setelan, bukan mode.** Ditanya soal daftar perbedaan, jawaban pemilik justru mengubah jenis barangnya: *"mungkin ini akan lebih ke konsep paketan mode … lebih ke paketan settings saja, sangat berguna untuk first time experienced user agar tidak bingung pilih settingannya."* Konsekuensinya besar dan menyederhanakan: **tidak ada `bazar_enabled`, tidak ada `hasFeature('bazar')`, tidak ada rute yang digerbangi, tidak ada perilaku baru di mana pun.** Aplikasi tidak pernah tahu ia sedang "mode bazar" — yang ada hanya tenant dengan setelan tertentu yang kebetulan disetel sekaligus. Entri ini berhenti jadi "definisikan sebuah fitur" dan jadi "perluas peta yang sudah ada" (`config/business-presets.php` + `BusinessPresetService`).

- **Dua dari empat kandidat di Deskripsi di atas gugur setelah dicek ke kode — jangan dikerjakan.**
  1. **Operasi offline sudah ada, dan berlaku untuk semua tenant tanpa flag apa pun**: `SyncOfflineTransactionsRequest`, `OfflineReviewController`, `routes/web.php:295` dan `:364`, plus penanganan anomali `SYNC_NEEDS_REVIEW`. `OpenBillExpiryService` bahkan sudah menghitung umur dari `occurred_at` justru karena penjualan offline. Menjadikannya "fitur mode bazar" berarti mengambilnya dari tenant lain.
  2. **"Nomor antrian ditonjolkan" sudah bisa hari ini**: `kitchen_queue` + `order_identity_mode = 'code'` sudah persis itu, dan sudah bisa disetel dari Pengaturan → Operasional. Ia turun jadi isi paketan, bukan fitur baru.

- **Empat keputusan pemilik, semuanya "ya".**
  1. **Paketan punya ruang kunci sendiri, terpisah dari `business_type`.** Formulir pendaftaran jadi dua pertanyaan: jenis usaha (untuk harga, seperti sekarang) dan *"paling mirip yang mana cara Anda berjualan?"* (untuk setelan). Alasannya: penjual di CFD tetap `kuliner` untuk penetapan harga — yang berbeda cuma cara ia bekerja. Memaksa `bazar` masuk `business_type` akan menyeretnya ke `PricingRule` dan `BusinessTypeResolver`, persis yang entri ini sudah putuskan tidak boleh terjadi.
  2. **Aturan kerja boleh masuk paketan** (`order_identity_mode`, `upsell_mandatory`, dst), **dengan syarat terlihat dan bisa diubah di layar yang sama.** Ini **pembalikan tertulis** atas aturan di `config/business-presets.php` yang menyatakan preset sengaja hanya menyentuh kapabilitas modul karena "menebaknya dari jenis usaha berarti menebak cara orang bekerja". Aturan itu ditulis untuk mencegah aplikasi memutuskan **diam-diam**; batas #3 di berkas yang sama sudah menetralkannya (preset mengisi daftar centang, pendaftar boleh mengubahnya). Yang tetap terlarang adalah setelan yang mendarat tanpa pernah muncul di layar. **Komentar di berkas itu harus ikut diperbarui saat ini dikerjakan** — kalau tidak, kode akan menyatakan aturan yang sudah tidak berlaku.
  3. **Tombol "Terapkan paketan ini" di Pengaturan ikut sekarang**, dengan tiga syarat: diminta pengguna (tidak pernah otomatis), memperlihatkan apa yang akan berubah sebelum dijalankan, dan tidak pernah terpicu oleh perubahan jenis usaha. Aturan "sekali pakai" dari `[BL-034]` tetap utuh — yang dilarang adalah penerapan ulang **diam-diam**, dan ini bukan itu.
  4. **Tagihan terbuka dipecah keluar** jadi `[BL-104]`. Ia satu-satunya bagian yang butuh migrasi dan perubahan perilaku, jadi menahannya di sini akan membuat entri ini menyeret pekerjaan yang bukan miliknya.

- **Batas dari pemilik yang membentuk seluruh entri ini:** *"perbedaan harga hanya dari adaptif atau premium urutannya, semua setting dan fitur lainnya tetap terbuka."* Artinya **paketan adalah nilai awal, tidak pernah pembatas** — tidak ada setelan atau fitur yang dikunci di baliknya, dan tidak ada paketan yang menaikkan atau menurunkan harga. **Diverifikasi ke kode dan cocok:** `business_type` memang terdaftar sebagai dimensi harga (`config/pricing-dimensions.php:83`, `BusinessTypeResolver`), tapi **tidak ada satu pun `PricingRuleCondition` yang memakainya** — seluruh kondisi yang ada memakai `monthly_revenue`. Jadi jenis usaha hari ini praktis tidak menggerakkan harga, dan menambah ruang kunci kedua untuk paketan tidak mengambil apa pun dari penetapan harga.

- **Draf isi paketan "Gerai Acara & Bazar"** (kolom terakhir: `Ya` = tampil di formulir dan bisa diubah saat itu juga; `Ringkasan` = mendarat lalu disebutkan di layar ringkasan sesudahnya):

  | Setelan | Nilai | Tampil? | Alasan |
  |---|---|---|---|
  | Antrian dapur | nyala | Ya | Inti pekerjaan gerai acara |
  | Analisis AI | nyala | Ya | Sama dengan semua preset lain |
  | Pesan mandiri | mati | Ya | Membuka tautan publik — harus keputusan sadar, di mana pun |
  | Bukti pembayaran | mati | Ringkasan | Menambah satu langkah ke tiap penjualan non-tunai; antrean panjang paling rugi |
  | Identitas pesanan | kode panggil | Ya | Inilah "nomor antrian ditonjolkan" dari catatan pemilik |
  | Saran jual wajib dijawab | mati | Ringkasan | Ia menahan tombol bayar; di antrean panjang itu racun |
  | Pajak | tidak disentuh | — | Status pajak urusan hukum, tidak boleh ditebak dari cara berjualan |
  | Margin minimum & ambang pengeluaran kas | tidak disentuh | — | Angka kebijakan, bukan gaya berjualan |

- **Namanya jangan "mode".** "Mode" membuat orang mengira aplikasi berperilaku berbeda selama ia menyala, lalu mencari tombol mematikannya — dan tidak ada, karena yang ada cuma setelan yang sudah terlanjur berubah. Nama yang dipakai: **"Paket Setelan Awal"**, dengan pilihan seperti **"Gerai Acara & Bazar"**. Judul entri ini sudah diganti mengikutinya.

- **Yang perlu dikerjakan:** satu migrasi kecil (menyimpan paketan yang dipilih), perluasan `config/business-presets.php` agar entri `features` bisa memuat setelan non-boolean (hari ini tiap entri mengasumsikan satu kolom `*_enabled`), penyesuaian `BusinessPresetService` dan formulir pendaftaran, lalu tombol "Terapkan paketan ini" di Pengaturan beserta pratinjau perubahannya. Sesudah itu `[BL-036]` terbuka.

---

---

### [BL-097] Service Charge — Ditunda Sejak Awal, dan Belum Ada yang Memintanya
- **Ditemukan:** 2026-08-30 (dipisah dari `[BL-065]`, tempatnya ditunda sejak entri itu ditulis 2026-08-08)
- **Sumber:** Kemungkinan yang terlihat saat memikirkan pajak — **bukan** permintaan siapa pun. Saran pasca-peragaan yang melahirkan `[BL-065]` berbunyi "pajak + PPN, mode include atau tidak"; service charge tidak disebut sama sekali.
- **Status:** **Selesai 2026-09-07** — ketiga tahap mendarat. Syarat masuk entri ini ("ada calon klien yang benar-benar memungut") **tidak pernah terpenuhi**; pemilik mengesampingkannya dan meminta pekerjaan ini dikerjakan atas dasar keempat usulan bawaan.
  > **SELESAI 2026-09-07.** Lihat `[SCHEMA] Biaya Layanan Mendapat Angkanya Sendiri — dan Pajak Dipungut di Atasnya (BL-097)` di `docs/CHANGELOG.md`.
  >
  > **Yang berubah dari teks di bawah, dan ini penting bagi pembacanya:**
  >
  > 1. **Pertanyaan 1 berhenti jadi usulan.** Verifikasi hukum yang entri ini sebut "bagian dari pekerjaan ini, bukan prasyarat yang bisa dilewati" **dilakukan**: DPP PBJT adalah "jumlah pembayaran yang diterima penyedia makanan dan/atau minuman" (UU HKPD Pasal 51, dirinci PP 35/2023 Pasal 19). Biaya layanan adalah uang yang diterima restoran, jadi ia masuk DPP. Jawaban **A** terkonfirmasi — bukan lagi dipilih karena arah salahnya lebih murah.
  > 2. **Jawaban 2, 3, dan 4 dipakai apa adanya (A, B, A), dan tetap belum divalidasi peminat.** Yang membuat itu aman bukan keberanian melainkan bentuk kolomnya: keempat jawaban menghasilkan **skema yang sama persis**, jadi tidak ada tebakan yang membeku di basis data. Alternatif **C** pertanyaan 2 (melarang inclusive + biaya layanan) tetap bisa ditambahkan kapan saja sebagai satu baris validasi, dan mencabutnya tidak meninggalkan apa pun.
  > 3. **Kutipan `CashDrawerReconciliation.php:99` di jawaban 4 salah, dan sudah dikoreksi di teks di bawah** sebelum pekerjaan dimulai. Laci tidak dihitung dari `total_amount` melainkan dari `SUM(transaction_payments.amount)`. Kesimpulannya tetap berdiri; yang runtuh mekanismenya.
  > 4. **Jawaban 3 ternyata gratis.** Karena `service_charge_amount` kolomnya sendiri dan tidak pernah dilebur ke `subtotal_amount`, `net_revenue` mengecualikan biaya layanan **tanpa satu pun kueri yang diubah**.
  > 5. **Tahap 3 mendarat tanpa mesin penguncian**, persis seperti butir terakhir entri ini memutuskan — satu tahap penuh yang tidak perlu dibangun. Empat pertanyaan di bawah menentukan kolom mana yang lahir; menjawabnya tanpa pengguna yang benar-benar memungut berarti membekukan tebakan ke dalam basis data. Usulan bawaan untuk keempatnya ditulis 2026-09-06 di bawah — ia mempercepat validasi, **bukan** menggantikannya.
- **Prioritas:** Low (naik begitu ada calon klien yang benar-benar memungut biaya layanan)
- **Duduk perkaranya:**
  Nol kode. `grep -i "service_charge\|servicecharge\|biaya layanan"` di seluruh `app/`, `database/`, `resources/js`, dan `config/` tidak menghasilkan apa pun — posisi yang sama persis dengan pajak sebelum 2026-08-28.
- **Kenapa aman ditunda:**
  Tarif dibekukan per transaksi sejak `[BL-065]`. Kolom yang lahir belakangan dengan `default 0` tidak merusak satu pun transaksi lama, dan polanya sudah terbukti di migrasi pajak. Menunda tidak menumpuk utang.
- **Empat yang harus diputuskan sebelum satu baris kode ditulis:**
  1. **Urutannya terhadap pajak.** Praktik lazim: subtotal → **+ service charge** → pajak atas *subtotal + service*, karena DPP PBJT adalah jumlah yang dibayar kepada restoran. **Belum diverifikasi ke sumber resmi** seperti riset pajak 2026-08-27 yang mengutip UU HKPD Pasal 51 — verifikasi itu bagian dari pekerjaan ini, bukan prasyarat yang bisa dilewati. Urutan terbalik memungut pajak lebih kecil dari seharusnya.
  2. **Mode inclusive jadi rumit.** Di inclusive harga katalog sudah mengandung pajak; service charge ditambahkan di atasnya, lalu pajak atasnya diurai dari mana? `TaxCalculator::apply()` menerima **satu** angka dasar. Dengan service charge ada dua komponen berperlakuan berbeda, dan invarian `subtotal + pajak = total` (`[BL-065]` butir 8) harus ditulis ulang jadi empat angka dengan tetap **satu** yang dibulatkan.
  3. **Uangnya milik siapa.** Di banyak tempat service charge dikumpulkan lalu dibagikan ke staf — artinya bukan pendapatan toko. Keputusan margin 2026-08-29 membuat pertanyaan ini tajam: kalau ia masuk `net_revenue`, margin menggembung persis seperti yang baru saja diperbaiki untuk pajak.
  4. **Dasar penagihan.** `app/Jobs/ComputeTenantMonthlyRevenue.php:128` menjumlahkan `total_amount` untuk menentukan bracket harga adaptif. Service charge yang masuk ke sana menaikkan bracket tenant atas uang yang mungkin bukan miliknya. Jebakan `updateOrCreate` berkunci tenant+periode tetap berlaku: kalau dasarnya diganti, ganti **maju saja**.
- **Yang membedakannya dari `[BL-065]`:**
  Pajak punya kewajiban hukum di baliknya — tenant yang tembus Rp 4,8 M **wajib** memungut, dan aplikasi yang menghalanginya menolak membiarkan penggunanya patuh. Service charge tidak punya paksaan apa pun: ia pilihan komersial pemilik toko. Itu sebabnya ia boleh menunggu peminat, sementara pajak tidak boleh.
- **Usulan bawaan untuk keempatnya — ditulis 2026-09-06, dan TETAP BUKAN KEPUTUSAN:**
  Diturunkan dari kode pajak yang sudah mendarat, bukan dari peminat yang belum ada. Gunanya sempit dan disengaja: saat calon klien itu muncul, yang tersisa adalah **memvalidasi** empat jawaban ini, bukan menurunkan ulang aritmetika inclusive dari nol. Tak satu pun boleh dikodekan sebelum divalidasi — status entri ini tetap "butuh keputusan".
  1. **Urutan → A: subtotal → + service charge → pajak atas *subtotal + service*.** Alasannya sama dengan yang sudah tertulis di pertanyaan 1, dan verifikasi ke Perda tetap wajib mendahului baris kode pertama. Alternatif **B** (SC di luar objek pajak) ditolak sementara karena salahnya jatuh ke arah yang mahal: pajak terpungut lebih kecil dari seharusnya, dan yang menanggung tenant. Alternatif **C** (`service_charge_taxable` per tenant) ditolak sebagai bawaan — ia membayar ongkos konfigurabilitas untuk ketidaktahuan yang bisa dihapus dengan membaca. Ambil C hanya kalau verifikasi menemukan daerah yang benar-benar berbeda.
  2. **Inclusive → A: SC dihitung dari harga katalog, pajak diurai dari `G + SC`.** Dengan `G` = jumlah baris, `r` = tarif pajak, `s` = tarif SC:

     ```
     exclusive:  SC    = round(G × s/100)
                 pajak = round((G + SC) × r/100)
                 total = G + SC + pajak            ← total diturunkan (penjumlahan)

     inclusive:  SC       = round(G × s/100)
                 total    = G + SC
                 pajak    = round(total × r/(100 + r))
                 subtotal = total − SC − pajak     ← subtotal diturunkan (pengurangan)
     ```

     Invarian butir 8 `[BL-065]` ditulis ulang jadi **`subtotal + service_charge + pajak = total`**: empat angka, dua dibulatkan, dan tetap **tepat satu** yang diturunkan — jangkarnya tetap berbeda per mode, persis seperti sekarang. Secara aritmetika ini identik dengan "SC kena pajak bertarif sama", jadi ia konsisten dengan jawaban 1.
     **Ongkos yang harus disadari sebelum dicetak ke kertas pelanggan:** di mode inclusive, angka SC yang tercetak adalah angka **kotor** (sudah mengandung pajak) sementara "Subtotal" tercetak bersih. Benar secara aritmetika, tapi tidak semua pemilik toko akan membacanya begitu.
     Alternatif **C — melarang kombinasi inclusive + service charge lewat validasi** layak dipertimbangkan serius, bukan basa-basi: kalau calon kliennya ternyata exclusive semua, satu baris validasi menghapus separuh pekerjaan Tahap 1 dan bisa dicabut kapan saja tanpa meninggalkan apa pun di basis data.
  3. **Atribusi → B: bukan pendapatan toko (bawaan).** Dikeluarkan dari `net_revenue`, dan laporan mendapat angka keempat di samping tiga yang sudah ada. Dipilih karena arah salahnya lebih murah: menganggapnya pendapatan padahal ia titipan staf mengulang persis cacat yang baru diperbaiki 2026-08-29, dan cacat itu tidak terlihat dari angkanya sendiri. **C** (`service_charge_is_revenue` per tenant, dibekukan per transaksi) adalah jawaban yang paling benar dan boleh menyusul kapan saja — lihat butir berikutnya.
  4. **Dasar penagihan → A: biarkan `total_amount`.** `total_amount` **wajib** memuat SC apa pun jawabannya, karena uang itu benar-benar ada di laci. Perlu dicatat bentuk sebenarnya (dikoreksi 2026-09-07): laci **tidak** dihitung dari `total_amount`. `expected_amount` di `app/Services/CashDrawerReconciliation.php:99` bertumpu pada `cash_in`, yang datang dari `paymentSummary()` → `SUM(transaction_payments.amount)` (`:215`); satu-satunya `sum('total_amount')` di berkas itu ada di `:108` untuk `unsettled_cash`, yang justru sengaja di luar rumus laci. Artinya SC sampai ke laci lewat baris pembayaran, **tanpa bergantung pada bentuk kolom SC sama sekali** — rekonsiliasi ikut benar dengan sendirinya. Yang menyangga jawaban A karenanya bukan laci. Yang jadi pilihan cuma dasar bracket, dan membiarkannya konsisten dengan butir 7 `[BL-065]` yang sudah menerima ongkos yang sama untuk pajak secara sadar. Memindahkannya ke `subtotal_amount` menjawab keluhan tenant exclusive **dan** SC sekaligus — tapi itu keputusan tentang dasar tagihan, bukan tentang service charge, dan tempatnya entri sendiri. **C** (mengurangi SC saja, pajak tetap) ditolak: tidak bisa dijelaskan ke siapa pun.
- **Yang membuat pertanyaan 3 lebih murah dari bunyinya:**
  Selama `service_charge_amount` jadi **kolomnya sendiri** dan tidak pernah dilebur ke `subtotal_amount`, atribusi hanyalah soal penurunan angka di `app/Services/ProfitService.php` dan `app/Http/Controllers/Owner/ReportController.php` — bisa dibalik kapan saja tanpa migrasi dan tanpa menyentuh satu baris transaksi lama. Yang benar-benar mahal kalau salah cuma **bentuk kolomnya**, bukan jawabannya. Ini menurunkan taruhan pertanyaan 3 dari "membekukan tebakan" jadi "memilih bawaan".
- **Rencana bertahap kalau lampunya hijau:**
  **Tahap 0 — keputusan, nol kode.** Verifikasi hukum untuk pertanyaan 1, validasi keempat usulan di atas, tulis hasilnya ke entri ini dengan format kedelapan-butir `[BL-065]` — termasuk alasan menolak alternatifnya. Syarat masuk tetap: ada calon klien yang benar-benar memungut.
  **Tahap 1 — skema + aritmetika, satu commit.** `tenants` mendapat `service_charge_enabled` (default false), `service_charge_rate` (default 0), dan `service_charge_label` **nullable tanpa default** — ditanyakan, bukan ditebak, mengikuti butir 6 `[BL-065]` dan pelajaran `[BL-079]`. `transactions` mendapat `service_charge_amount` (default 0) plus tarif dan label yang **dibekukan**, semuanya nullable. `TaxCalculator::apply()` menerima konteks kedua dan mengembalikan **empat** angka.
  **Tahap 2 — tampilan dan turunan.** Struk layar, termal, dan mobile; keranjang POS; `TransactionEditService` (hitung ulang wajib pakai tarif **beku**, bukan tarif hari ini); laporan harian/bulanan beserta kolom CSV-nya; `ProfitService` sesuai jawaban 3.
  **Tahap 3 — setelan pemilik.** Tanpa mesin penguncian; lihat butir terakhir.
- **Jebakan commit yang khas pekerjaan ini:**
  Bentuk kembalian `TaxCalculator::apply()` berubah, jadi cerminnya di `resources/js/support/tax.js` dan **enam** pembacanya harus ikut di commit yang sama — dua pemanggil PHP (`TransactionService`, `TransactionEditService`) dan empat pembaca JS (`POS.vue`, `ReceiptModal.vue`, `TransactionSuccessModal.vue`, `services/escpos.js`), ditambah `POSController` yang mengirim konteksnya dan `MobileTransactionController` yang meneruskan kolom bekunya. Docblock kedua berkas kalkulator sudah menuliskan aturan "ubah keduanya bersamaan". **`composer run check:boot` tidak akan menangkap pelanggarannya** — tidak ada kelas yang hilang di sini, yang ada cuma dua salinan aturan yang menyimpang, dan akibatnya penjualan offline jatuh ke `needs_review` satu per satu saat sinkronisasi. Yang menangkapnya adalah `tests/Unit/TaxCalculatorTest.php` dan `tests/Feature/TaxFoundationTest.php`, yang harus lebih dulu diperluas ke invarian empat-angka di kedua mode.
- **Satu hal yang sudah bisa diputuskan sekarang tanpa menunggu siapa pun — service charge TIDAK perlu dikunci:**
  Penguncian `tax_mode` dibeli oleh kewajiban hukum dan riwayat pajak tanpa patahan (butir 3 dan 4 `[BL-065]`). Service charge tidak punya keduanya: ia pilihan komersial yang boleh dinyalakan dan dimatikan pemilik toko kapan pun, dan riwayat yang berlubang di sana tidak melanggar apa pun. Cukup berlaku **maju** dan **dibekukan per transaksi**. Itu menghapus seluruh mesin `tax_lock_opened_until` + `TenantTaxLockController` dari lingkup pekerjaan ini — kira-kira satu tahap penuh yang tidak perlu dibangun.
