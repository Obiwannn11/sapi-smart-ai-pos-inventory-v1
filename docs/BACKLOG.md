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

### [BL-019] Rencana Antrian Dapur (PHASE-QUEUE) Perlu Ditinjau Ulang & Ditulis Ulang Sebelum Diimplementasi
- **Ditemukan:** 2026-07-28
- **Sumber:** Peninjauan teknis `docs/phases-2/PHASE-QUEUE_Kitchen-Order-Queue.md` terhadap keadaan kode hari ini, dilakukan **sebelum** implementasi dimulai
- **Status:** In Progress
- **Prioritas:** High untuk **peninjauannya**, bukan untuk fiturnya. Fiturnya sendiri boleh menunggu; yang mendesak adalah dokumennya, karena ia ditulis dalam bentuk instruksi siap-salin dan sebagian isinya kini **menyesatkan**. Siapa pun yang mengeksekusinya apa adanya akan merusak hal lain yang sudah berjalan.
- **JANGAN diimplementasikan dulu.** Urutannya: tinjau ulang tujuan → tulis ulang dokumennya menyesuaikan sistem sekarang → baru kerjakan. Menambal dokumen sambil ngoding akan menghasilkan setengah rencana lama dan setengah keadaan baru.
- **KEMAJUAN (2026-07-28):** ✅ tujuan ditinjau & tiga keputusan dikunci (lihat blok di bawah) · ✅ `PHASE-QUEUE_Kitchen-Order-Queue.md` **selesai ditulis ulang** · ✅ `PHASE-FEATURE-FLAGS_Capabilities-Sync.md` **selesai ditulis ulang** — kini ia yang memiliki fondasi flag (tidak lagi menumpang "Bagian 0"), blok aliasnya dikoreksi, dan **klaim "MCP masih kerangka" ternyata sudah usang: MCP hidup penuh dan gerbangnya dibutuhkan sekarang** · ⬜ implementasi belum dimulai. Kedua dokumen punya bagian **Riwayat Revisi** yang mencatat apa yang berubah dan kenapa. Entri ini baru pindah ke Riwayat Selesai setelah fiturnya benar-benar mendarat di kode.
- **Turunan:** `[BL-020]` lahir dari penulisan ulang ini, dan **sudah ditutup 2026-07-29**. Yang perlu dibaca ulang saat mengerjakan poin 4 di bawah: perbaikan itu memasang gerbang langganan di jalur self-order tapi **sengaja meninggalkan `PATCH /orders/{id}/fulfillment` di luarnya**, dengan alasan yang sama persis dengan poin 4 — menyelesaikan kewajiban yang sudah dibayar bukan layanan baru. Preseden untuk `cashier.queue.*` sudah ada, dan `EnsureSubscriptionActive` masih utuh belum tersentuh, jadi keputusan `ALWAYS_ALLOWED` tetap sepenuhnya milik entri ini.

#### KEPUTUSAN PEMILIK (2026-07-28) — arah tulisan ulang
> Diambil setelah peninjauan di bawah dipaparkan. Tiga keputusan ini **mengunci arah** dan menutup sebagian temuan; sisanya tetap berlaku apa adanya.

1. **Persona bertahap — versi pertama ONLINE saja, offline jadi fase lanjutan.** Sasaran akhirnya tetap kaki lima/bazar, tapi papan versi pertama dibangun untuk outlet yang koneksinya wajar. Konsekuensi yang harus **ditulis eksplisit** di dokumen, bukan dibiarkan tersirat: saat perangkat offline, mode antrian tidak aktif dan kasir kembali ke alur biasa; transaksi hasil sinkronisasi **tidak** menyusul masuk papan. Kalau tidak dinyatakan, perilaku ini akan dilaporkan sebagai bug oleh orang pertama yang mengalaminya.
2. **Nilai inti papan = urutan pesanan**, sesuai rencana asli. Pengelompokan pekerjaan (poin 16) resmi **di luar lingkup** dan tinggal sebagai arah lanjutan, bukan kekurangan yang perlu ditutup sekarang. Perlu dicatat bahwa ini **tidak** membatalkan poin 11: mengganti naik/turun jadi *Dahulukan* satu-tap itu soal biaya interaksi, bukan soal nilai inti — jadi tetap dikerjakan.
3. **Papan menampung pesanan belum lunas dan menandainya.** Open bill ikut masuk papan dengan penanda **BELUM BAYAR** yang mencolok, dan transisi ke `done` menuntut konfirmasi bila `status !== completed`. Dengan ini poin 12 naik derajat dari temuan jadi **syarat penerimaan**, dan status pembayaran menjadi bagian sah dari model papan — bukan tempelan.

##### Penjaga anti-kunci — wajib dipasang di versi pertama meski offline belum dikerjakan
> Ini harga dari memilih "bertahap". Empat hal berikut hampir tak berbiaya sekarang, tapi mahal sekali kalau baru disadari saat fase offline dibuka.

1. **`queue_number` tidak boleh jadi identitas kartu.** Identitas tetap `id`/`code`; nomor antrian hanya label tampilan. Perangkat offline nanti tidak akan bisa menjamin nomor unik lintas perangkat, jadi apa pun yang menjadikan nomor sebagai kunci akan menghalangi fase kedua.
2. **Pengalokasian nomor disembunyikan di balik satu seam tersendiri** (mis. `QueueNumberAllocator`), bukan ditanam langsung di `TransactionService@checkout`. Fase offline akan menukar strateginya — blok nomor per perangkat, atau awalan per perangkat seperti `A-12`/`B-12` — dan penukaran itu harus jadi penggantian satu kelas, bukan bedah ulang checkout.
3. **`sort_index` diturunkan dari `effectiveDate()`/`occurred_at`, bukan `now()`.** Gratis hari ini karena keduanya sama persis untuk transaksi online. Wajib nanti: penjualan offline disinkronkan belakangan, sehingga `now()` saat sync akan melemparkannya ke dasar papan padahal pesanannya datang paling awal.
4. **Batas hari pada papan juga memakai `effectiveDate()`** (lihat poin 9), dengan alasan yang sama.

- **Area Terdampak:**
  - `docs/phases-2/PHASE-QUEUE_Kitchen-Order-Queue.md` — dokumen utama yang perlu ditulis ulang
  - `docs/phases-2/PHASE-FEATURE-FLAGS_Capabilities-Sync.md` — ikut terdampak; ia menyatakan dirinya bergantung pada Bagian 0 dokumen di atas
  - `database/migrations/2026_07_26_180117_add_business_type_to_tenants_table.php` — kolom yang direbutkan
  - `config/pricing-dimensions.php:83` — pemakaian sah kolom itu hari ini
  - `app/Services/TransactionService.php:471` — `commitOffline()` tidak menyentuh fulfillment sama sekali
  - `app/Services/TransactionService.php:323` — `void()` tidak membersihkan `fulfillment_status`
  - `app/Http/Middleware/EnsureSubscriptionActive.php:52` — masa tenggang menolak semua method non-safe
  - `bootstrap/app.php:23` — `tenant`/`tenant.api` adalah GRUP, bukan alias
  - `app/Http/Controllers/Api/V1/ApiOrderController.php:113` — satu-satunya jalur advance yang ada hari ini
  - `resources/js/Components/ReceiptModal.vue` — struk belum mengenal nomor antrian

- **Deskripsi — kenapa dokumennya usang, bukan salah sejak awal:**
  Dokumen ini ditulis saat fondasi self-order baru selesai, dan waktu itu isinya benar. Sesudahnya tiga pekerjaan besar mendarat dan menggeser tanah tempat ia berdiri: **PHASE SAAS** (`[BL-005]`/`[BL-006]`) membawa siklus hidup langganan berikut gerbang hanya-baca, **`[BL-015]`** menaruh `tenants.business_type` sebagai dimensi harga, dan **PHASE PWA** membuat penjualan offline jadi jalur tulis kedua yang setara. Ketiganya menyentuh persis titik yang diandaikan dokumen ini masih kosong.

- **A. Penghalang keras — akan merusak yang sudah jalan:**
  1. **Kolom `business_type` sudah dipakai penetapan harga, dan rebutan ini menyentuh tagihan.** Dokumen merencanakan `string('business_type')->default('cafe')` bernilai `cafe|street_food` (Bagian 1a). Kolomnya **sudah ada** — `nullable`, bernilai `kuliner|retail|jasa|lainnya`, bertipe dimensi `attribute`, dan **dibekukan ke `invoices.pricing_context`** setiap kali tagihan terbit. Migrasinya akan gagal karena duplikat; yang jauh lebih buruk adalah bila kolomnya "dipakai bareng" — toggle bernama *Mode Outlet* di Settings akan **diam-diam mengubah dasar harga langganan tenant**. Owner mengira mengganti wajah papan dapur; yang berubah adalah aturan harga mana yang cocok untuknya. Ini persis jenis kesalahan yang tak akan disadari siapa pun sampai ada yang memeriksa tagihan.
  2. **Transaksi offline tidak akan pernah muncul di papan — dan itu menimpa persona yang justru ditarget.** `commitOffline()` tak pernah menyetel `fulfillment_status`, jadi nilainya `null` dan `hasFulfillmentTracking()` menjawab false. Checklist dokumen menyebut `checkout` dan `confirmSelfOrderPayment`, tapi **melewatkan jalur offline seluruhnya**. Sasaran fiturnya adalah kaki lima dan tenant bazar — tempat dengan sinyal paling buruk. Jadi papan dapur akan kosong justru pada hari antreannya paling panjang. Turunannya perlu diputuskan sadar-sadar: nomor antrian digenerate server saat sync, sehingga urutan nomor tak mencerminkan urutan orang datang, dan pelanggan tak memegang nomor apa pun saat memesan.
  3. **Transaksi void nyangkut di papan selamanya.** `void()` hanya mengubah `status` jadi `voided` tanpa menyentuh `fulfillment_status`, sementara query papan (Bagian 2c) hanya menyaring `fulfillment_status`. Pesanan yang sudah dibatalkan tetap tampil sebagai kartu aktif dan akan dimasak. Perbaikannya dua lapis: `void()` menolkan fulfillment, **dan** query papan tetap mengecualikan `STATUS_VOIDED` — jangan bergantung pada satu saja.
  4. **Masa tenggang membekukan dapur.** `EnsureSubscriptionActive` menolak seluruh method non-safe saat status `grace`, dan semua rute antrian yang diusulkan adalah POST. Artinya begitu langganan lewat jatuh tempo, operator tak bisa memajukan status pesanan **yang uangnya sudah diterima**. Ini bertabrakan dengan prinsip yang ditulis middleware itu sendiri — *"Menyandera data pelanggan bukan alat penagihan yang sah"*. Menyelesaikan kewajiban yang sudah dibayar bukan "layanan baru", jadi `queue.advance` layak masuk `ALWAYS_ALLOWED`.

- **B. Koreksi pada kode contoh di dokumen (bukan penghalang, tapi jangan disalin apa adanya):**
  5. **`advance()` tanpa proteksi balapan, padahal dokumen sendiri menetapkan polling 5–10 detik dan skenario dua aktor.** Dengan polling, UI basi bukan kemungkinan melainkan keadaan normal: kartu yang masih menampilkan "Mulai masak" padahal sudah `preparing` akan melompati satu status begitu ditekan. Kirim status yang diharapkan dari klien (`advance($tx, expectedFrom:)`) dan tolak bila tak cocok — murah, dan menutup seluruh kelas bug ini sekaligus.
  6. **`sort_index` tidak sungguh anti-tie.** Dokumen mengklaim `now()->valueOf()` membuat tiap kartu unik; itu milidetik, dan dua self-order bisa jatuh di milidetik yang sama. Query tetangga memakai perbandingan **strict** (`<` / `>`), sehingga kartu kembar dilewati dan tombolnya terasa rusak. Pakai `id` sebagai pemecah seri di pengurutan maupun di pencarian tetangga.
  7. **Lost update di `swapWithNeighbor`.** Hanya tetangga yang kena `lockForUpdate`; `$transaction->sort_index` dibaca dari model in-memory yang tidak dikunci dan tidak di-refresh di dalam `DB::transaction`. Dua tap cepat saling menimpa.
  8. **`advance()` menulis tiga kali dan tidak atomik** — `advanceFulfillment()` sudah `update()`, lalu dua `update()` lagi untuk stempel waktu, dan tidak dibungkus `DB::transaction` padahal `moveUp`/`moveDown` dibungkus. Jadikan satu `update()`.
  9. **Papan tidak punya batas hari.** Pesanan `ready` yang terlupakan saat tutup lapak akan tetap muncul besok pagi, dan karena nomor antrian reset harian, "#5 kemarin" berdampingan dengan "#5 hari ini". Filternya juga harus memakai `effectiveDate()`/`whereEffectiveDate()` sesuai konvensi repo — bukan `created_at` mentah seperti pada `generateQueueNumber` usulan, yang akan salah untuk transaksi offline.
  10. **Blok alias di PHASE-FEATURE-FLAGS Bagian 2 salah fakta.** Ia menulis `tenant` dan `tenant.api` sebagai alias, padahal `bootstrap/app.php` menyatakan keduanya **sengaja grup** berikut alasan panjangnya. Blok itu juga menghilangkan `permission`, `permission.api`, `platform.can`, dan `platform.owner` yang sudah ada — menyalinnya mentah-mentah akan mematikan gating RBAC dan panel platform sekaligus.

- **C. Kasus yang belum tertangani — ini soal rancangan, bukan bug:**
  11. **Cara mendahulukan pesanan bertabrakan dengan personanya sendiri.** Dokumen menggambarkan operator yang "lagi masak, tangan repot", lalu mewajibkan modal konfirmasi **di setiap** tekan ▲/▼. Karena mekanismenya tukar-satu-langkah, mendahulukan pesanan dari posisi 10 ke posisi 1 berarti **9 tap dan 9 konfirmasi**. Niat sebenarnya hampir selalu "dahulukan yang ini", bukan "geser satu": jadikan *Dahulukan* (lompat ke atas) aksi utamanya dengan satu konfirmasi, dan sisakan ▲/▼ sebagai penghalus tanpa modal.
  12. **Pesanan yang belum dibayar bisa ditandai "Selesai".** Open bill masuk papan dengan `fulfillment_status = waiting` tapi `status = pending`, dan rancangan kartunya tidak menampilkan status pembayaran sama sekali. Untuk operator tunggal yang merangkap masak dan kasir — persona yang persis ditarget — ini kebocoran uang, bukan kekurangan kosmetik.
  13. **Pelanggan tidak pernah menerima nomor antriannya.** Seluruh gunanya `queue_number` bertumpu pada nomor itu bisa dipanggil, tapi tidak ada satu langkah pun yang menyampaikannya: `ReceiptModal.vue` tidak mengenal kolomnya, dan self-order hanya menerima `transaction_code`. Tanpa ini papan cuma jadi catatan internal.
  14. **Tidak ada jalan batal dari papan.** Salah input di jam sibuk hanya bisa dikeluarkan dengan menekan "Selesai" tiga kali — yang mencatatnya sebagai terlayani dan mengotori data.
  15. **Status `ready` ikut dalam himpunan yang bisa diurutkan ulang,** sehingga pesanan yang sudah siap bisa ditukar posisinya dengan yang belum dimasak. Tidak bermakna; pisahkan atau pin.
  16. **Pengelompokan pekerjaan (batching) tidak tertangani — dan ini peluang terbesar yang terlewat.** Bagi operator tunggal, pertanyaan sesungguhnya bukan "pesanan mana duluan" melainkan "apa yang bisa saya kerjakan sekaligus": tiga es teh di tiga pesanan berbeda idealnya sekali jalan. Papan berbentuk daftar-pesanan murni justru menyembunyikan informasi itu. Tidak harus masuk versi pertama, tapi layak jadi arah lanjutan — efisiensi yang benar-benar dirasakan persona ini ada di sini, jauh melebihi tombol naik/turun.
  17. **Fulfillment parsial** (sebagian item siap) tidak didukung. Untuk street-food itu penyederhanaan yang wajar — tapi nyatakan sebagai batas yang disadari, jangan dibiarkan ambigu.
  18. **Hari pertama flag dinyalakan, papan akan langsung penuh sampah — dan ini akibat data yang sudah tertimbun, bukan bug baru.** Ditemukan menyusul Keputusan 3. Open bill **sudah** memperoleh `fulfillment_status = waiting` sejak hari pertama fitur itu ada (`TransactionService.php:60`), sementara `payOpenBill()` (`:264`) hanya mengubah `status` jadi `completed` dan **tidak pernah menyentuh `fulfillment_status`**. Artinya setiap open bill yang pernah dibuat — termasuk yang sudah lunas dan selesai berbulan-bulan lalu — sampai detik ini masih berbunyi `waiting` di basis data, dan selama ini tidak terlihat semata karena belum ada permukaan yang menampilkannya. Begitu papan menyala, semuanya muncul sekaligus sebagai pesanan aktif. Dua hal yang harus ikut dikerjakan: **(a)** `payOpenBill()` memajukan fulfillment saat tagihan lunas — perbaikan yang benar terlepas dari fitur antrian, dan **(b)** migrasi backfill yang menolkan `fulfillment_status` pada open bill lama yang sudah `completed`/`voided`. Poin 9 (batas hari) saja **tidak cukup** menutup ini: sebagian open bill lama akan jatuh di hari yang sama dengan pengaktifan, dan yang lolos tetap salah — hanya tidak terlihat.

- **Usulan Perbaikan:**
  1. **Selaraskan ulang tujuannya lebih dulu, baru bentuknya.** Yang perlu dijawab: apakah sasarannya tetap kaki lima/bazar (yang berarti offline **wajib** ikut, poin 2), dan apakah nilai utamanya "urutan siapa duluan" atau "apa yang dikerjakan berikutnya" (poin 16). Dua jawaban ini menentukan hampir seluruh sisanya.
  2. **Cabut `business_type` dari fase ini sepenuhnya.** Kalau preset saat pendaftaran tetap diinginkan, pakai kolom bernama lain (mis. `operation_mode`) — jangan menumpang dimensi harga. Ini sekaligus menutup "Keputusan Terbuka A" di dokumen: pilih **toggle murni**, karena slot registrasi sudah punya penghuni sah.
  3. **Tulis ulang dokumennya, jangan ditambal.** Bagian 0, 1, dan 2b bertumpu pada asumsi yang sudah gugur, jadi menyunting sepotong-sepotong akan menyisakan kalimat lama yang saling bertentangan dengan yang baru.
  4. **Perbarui PHASE-FEATURE-FLAGS di sesi yang sama.** Ia menyatakan dirinya bergantung pada Bagian 0 dokumen ini; membiarkannya menunjuk bagian yang sudah berubah akan mengulang persoalan yang sama di permukaan lain.

- **Catatan:**
  - **Yang tidak perlu diragukan.** Beberapa keputusan di dokumen ini tetap benar dan sebaiknya dipertahankan saat ditulis ulang: memusatkan logika fulfillment di satu service (duplikasi di `ApiOrderController:113` memang nyata), migrasi yang hanya menambah kolom, gating agar mode cafe nol perubahan, penggunaan ulang `ConfirmDialog.vue`, polling sebelum Reverb, dan urutan "test backend dulu, UI belakangan".
  - **Fondasi datanya memang sudah berdiri seperti klaim dokumen** — `fulfillment_status`, `advanceFulfillment()`, `source`, `order_type`, `customer_name`, `table_number` semuanya ada. Jadi yang usang adalah asumsi tentang *sekitarnya*, bukan tentang fondasinya.
  - **Pemisahan `feature:` dari `permission:` bukan duplikasi sistem.** Keduanya ortogonal: `permission` menjawab "pengguna ini boleh?", `feature` menjawab "outlet ini punya kapabilitasnya?". Yang perlu dijaga saat menulis ulang hanyalah urutan gerbang dan agar menu nav menghormati keduanya.

### [BL-018] Diskon Dinamis Barang Mendekati Habis/Kedaluwarsa dengan Penjaga Margin
- **Ditemukan:** 2026-07-25
- **Sumber:** Saran yang diterima setelah sesi pitching — diskon yang "harganya ditentukan dan menyesuaikan sembari tetap untung, melihat harga modal dan harga jual"
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `database/migrations/2026_03_06_000011_create_transactions_table.php` & `..._000013_create_transaction_items_table.php` — **tak ada satu pun kolom diskon** di kedua tabel
  - `app/Services/TransactionService.php:335` — checkout selalu memakai `$variant->price`; harga dari klien diabaikan
  - `app/Services/TransactionService.php:569` — sinkronisasi offline menandai `needs_review` bila harga bayar ≠ harga katalog
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
  2. **Lantai harga dihitung dari `cost_price`, dan ambang untungnya milik owner — bukan konstanta.** Bentuknya `cost_price × (1 + margin minimum)`; sistem tak pernah menyarankan apalagi menerapkan di bawah lantai itu. Perlu diputuskan apakah lantai boleh ditembus manual untuk barang yang sudah pasti terbuang — menjual rugi tipis tetap lebih baik daripada dibuang total, tapi kalau boleh, itu harus tindakan sadar dengan alasan tercatat, bukan efek samping rumus.
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

## Riwayat Selesai

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
