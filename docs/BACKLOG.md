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

> **Catatan pemilik 2026-07-31** — `[BL-021]`–`[BL-028]` berasal dari satu daftar catatan yang sama dan sudah diverifikasi terhadap kode. Tiga di antaranya (`[BL-021]`, `[BL-022]`, `[BL-028]`) menyangkut **angka uang yang tercatat salah**, jadi didahulukan; sisanya UX. Urutan pengerjaan yang disarankan: ~~`[BL-028]` Tahap A~~ → ~~`[BL-021]`+`[BL-022]`~~ → ~~`[BL-027]`~~ → ~~`[BL-023]`~~ → ~~`[BL-025]`~~ → ~~`[BL-026]`~~ (selesai 2026-08-01), dengan `[BL-024]` menunggu perincian dari pemilik dan `[BL-028]` Tahap B ditunda sampai ada kasir kedua. **Dari jalur ini tinggal `[BL-024]` dan `[BL-028]` Tahap B yang terbuka**, dan keduanya menunggu sesuatu — bukan menunggu giliran.

> **Catatan pemilik 2026-07-31 (kedua)** — `[BL-032]`–`[BL-041]` berasal dari catatan review demo "SAPI — ASpire" dan sudah **diverifikasi terhadap kode**; tiap entri menyebut berkas dan barisnya. Dua hal dari catatan itu sengaja TIDAK jadi entri karena ternyata sudah benar: (1) pemisahan login platform vs login tenant sudah utuh — guard `platform` sendiri (`config/auth.php:50`), broker reset kata sandi sendiri (`config/auth.php:120`), dan `redirectGuestsTo` yang memilah tujuan berdasarkan prefiks URL (`bootstrap/app.php:80`); (2) "kelas harga sesuai omzet" sudah ada mesinnya (`pricing_rules` + bracket A–D), yang belum ada hanya angkanya — itu masuk `[BL-041]`.

> **Catatan pemilik 2026-08-01** — `[BL-043]`–`[BL-047]` berasal dari catatan menjalankan panel platform, dan sudah **diverifikasi terhadap kode**. Empat dari lima bukan "belum ada sama sekali" melainkan **setengah jadi**: gerbang hanya-baca sudah menegakkan dirinya tapi tak terlihat (`[BL-045]`), CRUD paket sudah ada tapi tak ada yang membacanya (`[BL-046]`), kuota AI sudah ada tapi tinggal di `.env` (`[BL-047]`), dan siklus hidup langganan sudah berpindah keadaan tapi tak pernah menerbitkan tagihan (`[BL-044]`). Hanya `[BL-043]` yang murni cacat. **Koreksi terhadap catatan 2026-07-31 (kedua) di atas:** butir (1) di sana — "pemisahan login platform vs login tenant sudah utuh" — benar untuk *guard, broker, dan pengalihan tamu*, tapi **tidak** untuk pengalihan **setelah** berhasil masuk; lihat `[BL-043]`.
>
> Urutan yang disarankan: ~~`[BL-043]` (cacat, berdiri sendiri, kecil)~~ → ~~`[BL-030]` (mumpung `invoices` masih kosong)~~ → ~~`[BL-041]`(a) menetapkan angka~~ → ~~`[BL-044]`~~ (butir b 2026-08-06, butir c 2026-08-15 — **tertutup**) → ~~`[BL-046]`~~ → ~~`[BL-047]`~~ (selesai 2026-08-13) → ~~`[BL-045]`~~. Yang dicoret selesai 2026-08-05 s.d. 2026-08-15. **Seluruh jalur ini kini tertutup:** `[BL-041]`(a) terjawab 2026-08-07 dan `[BL-048]` ditutup `[BL-055]` pada 2026-08-10.

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
> Yang sudah terpasang 2026-08-07: paket, bracket, `trial_months`, jangkar tanggal daftar, dan tangga tenggat di config. Yang **belum** dan jadi entri baru: ~~`[BL-052]` perpindahan otomatis akhir masa gratis~~ (selesai 2026-08-08), ~~`[BL-053]` seat & kuota AI jadi komponen bulanan~~ (bagian seat selesai 2026-08-08; kuota AI jadi `[BL-069]`), ~~`[BL-054]` penegak tenggat bertingkat~~ (selesai 2026-08-08), ~~`[BL-055]` alur pengajuan Adaptif~~ (selesai 2026-08-10; butir (g) jadi `[BL-073]`), ~~`[BL-056]` pengajuan berlaku bulan mana~~ (selesai 2026-08-19), ~~`[BL-057]` harga khusus per tenant~~ (selesai 2026-08-14). **Seluruh daftar ini kini tertutup.**

> **Permintaan pemilik 2026-08-07 (pembayaran)** — halaman bayar "ala-ala" untuk development dan peragaan, dengan Sumopod payment gateway menyusul belakangan. Dipecah jadi dua: `[BL-059]` **(selesai hari yang sama)**, dan `[BL-060]` yang menunggu dokumentasi serta kredensial sandbox. Satu hal yang menentukan seluruh bentuk `[BL-059]`: gateway tiruannya **melunasi lewat webhook**, bukan dengan memanggil `settle()` langsung — supaya yang diperagakan besok adalah jalur yang sama dengan yang akan dipakai produksi, dan memasang Sumopod tinggal menukar satu driver. Catatan kecil yang mudah terlewat: nama "Sumopod" sudah dipakai di aplikasi ini untuk **gateway AI**, bukan pembayaran; kredensialnya jangan dipakai ulang.

> **Catatan pemilik 2026-08-08 (pasca-peragaan & laporan progres)** — tujuh saran dari sesi peragaan, sudah **diverifikasi terhadap kode** sebelum jadi entri. Yang perlu diketahui sebelum membacanya satu per satu:
>
> - **Satu saran ternyata sudah punya entri.** "Perbaiki include seats atau tambahan kuota akun" pada sisi **mekanika tagihannya** adalah `[BL-053]` (seat & kuota jadi komponen bulanan, plus jalan melepas seat) dan `[BL-049]` (tagihan Rp 0 saat menambah pengguna) — dan **tidak diduplikasi** di sini. Sisi seat-nya selesai 2026-08-08; sisa kuota AI-nya kini `[BL-069]`. Yang benar-benar belum tercatat hanyalah sisi **keterlihatannya**: berapa seat dan berapa kuota AI yang didapat sebuah paket tidak muncul di satu pun permukaan sebelum orang mendaftar. Itu yang jadi `[BL-067]`.
> - **Satu saran tidak punya kode sama sekali untuk diperbaiki.** PPN/pajak nol kata di seluruh `app/`, `config/`, dan migrasi; `transactions` hanya punya `total_amount`, dan struk hanya menampilkan Subtotal → TOTAL. Jadi `[BL-065]` bukan "perbaiki mode pajak", melainkan "belum ada pajaknya" — dan bentuk yang diminta (include vs dibebankan ke pelanggan) menentukan **kolom mana yang lahir**, bukan sekadar cara menampilkannya. Bentuknya diputuskan pemilik 2026-08-28 dan seluruhnya **sudah mendarat**; entrinya diarsipkan 2026-08-30, menyisakan `[BL-097]` (service charge) yang bukan tentang pajak.
> - **"Dynamic pricing" di saran ini berarti mesin harga langganan SaaS**, bukan diskon barang mendekati kedaluwarsa (`[BL-018]`). Keduanya sama-sama pernah disebut "harga dinamis" di proyek ini; `[BL-066]` memakai arti yang pertama, sesuai `docs/SAPI-Pitch-Fitur-Unggulan_v1.0.md` bagian 3.
> - **Saran cabang sengaja dipatok terakhir** atas permintaan pemilik sendiri ("pastiin yang lain berhasil semua dulu"). `[BL-068]` mencatat kenapa syarat itu tepat: tidak ada satu pun konsep cabang di kode hari ini, dan menambahkannya menyentuh hampir setiap tabel operasional.
>
> Urutan yang disarankan, termurah dulu: ~~`[BL-067]` (keterlihatan seat/kuota)~~ → ~~`[BL-066]` (penjelasan Dynamic Pricing di landing)~~ (keduanya selesai 2026-08-14) → ~~`[BL-065]`~~ (PPN — **selesai 2026-08-29**, diarsipkan) → `[BL-068]` (cabang, paling akhir). **Yang tersisa dari jalur ini tinggal `[BL-068]`**, dan ia memang dipatok terakhir atas permintaan pemilik sendiri. Tiga yang paling murah — `[BL-062]` (kuota AI), `[BL-063]` (rekap bulanan), dan `[BL-064]` (grafik garis) — selesai 2026-08-13; ketiganya memang tidak menyentuh uang sama sekali.

> **Catatan pemilik 2026-08-13 (penyisiran backlog)** — pemilik menanyakan empat fitur yang dikiranya mungkin terlewat dicatat; seluruhnya sudah **diperiksa terhadap kode**, dan hasilnya dua sudah tercatat, dua memang terlewat. **Sudah ada:** offline + printer Bluetooth + lapisan native adalah `[BL-016]` (Capacitor ada di sana sebagai opsi 2, dan lingkupnya sudah dikunci Android saja), sedangkan upsell dari sinyal stok sudah **selesai** lewat `[BL-017]`/`[BL-025]` — sisanya hanya bundling berdiskon yang menunggu ~~`[BL-018]`~~. **`[BL-018]` selesai 2026-08-19**, dan bundling sengaja ditinggalkan sebagai pekerjaan tersendiri — yang akhirnya punya entrinya sendiri sebagai `[BL-103]` (2026-09-06). **Benar-benar terlewat:** upsell yang **ditargetkan manual oleh owner** — tiga strategi yang ada semuanya menurunkan saran dari data dan tidak ada satu pun tempat bagi owner menuliskan targetnya sendiri — jadi `[BL-074]`; dan **foto bukti pembayaran non-tunai**, yang nol kode (`transaction_payments` hanya punya `reference_code`), jadi `[BL-075]`. Satu hal yang mudah menyesatkan dan sudah ditulis di dalam `[BL-075]`: `invoices.proof_path` yang sudah ada itu bukti tenant membayar langganan SaaS, **bukan** bukti pelanggan membayar di kasir. `[BL-076]` lahir dari keputusan pemilik di hari yang sama — penyimpanan `[BL-075]` untuk sementara di disk server, dan pemindahannya ke object storage dicatat terpisah supaya "sementara" tidak diam-diam jadi permanen.

> **Catatan pemilik 2026-08-21 (sesi tanya-jawab konsep)** — `[BL-084]`–`[BL-088]` berasal dari satu daftar pertanyaan pemilik dan sudah **diverifikasi terhadap kode** sebelum jadi entri; tiap entri menyebut berkas dan barisnya. Dua hal dari daftar itu sengaja TIDAK jadi entri karena ternyata sudah benar: (1) **transaksi void** sudah utuh — stok kembali, papan dapur dibersihkan dua lapis, omzet menyaring `completed` saja, dan jumlah void dilaporkan terpisah di rekap harian, bulanan, dan CSV; satu-satunya batas yang tersisa ("hanya transaksi hari ini", `app/Services/TransactionService.php:469`) ditulis sendiri sebagai MVP dan belum pernah dikeluhkan, jadi dibiarkan sampai ada yang menagihnya. (2) **pendaftaran toko baru** sudah punya seluruh alurnya — `AuthController::register()`, preset fitur per jenis usaha (`[BL-034]`), masa coba dibuka di transaksi yang sama sehingga tenant tak pernah lahir tanpa langganan, penandaan IP oleh `SignupGuardService`, lalu verifikasi surel. Yang belum ada adalah **bergabung ke toko yang sudah ada** (staf hari ini ditambahkan owner dari modul Staf, tidak mendaftar sendiri) dan **multi-cabang** — yang kedua sudah tercatat sebagai `[BL-068]`.
>
> **Satu koreksi terhadap dugaan pemilik, dan ia menentukan bentuk tiga entri kas di bawah:** yang berumur 24 jam lalu tersapu otomatis adalah **tagihan terbuka** (`[BL-031]`, `open-bills:expire`, tiap jam) — **bukan sesi kas**. Sesi kas hari ini tidak punya umur sama sekali, dan tidak ada satu pun tugas terjadwal yang menyentuh `cash_drawers`. Jadi `[BL-088]` adalah fitur baru, bukan perbaikan sesuatu yang sudah berjalan.
>
> ~~Urutan yang disarankan, termurah dulu: `[BL-084]` → `[BL-085]` (keduanya satu berkas, tanpa skema) → `[BL-086]` (UI + pemecahan rute) → `[BL-088]` → `[BL-087]`.~~ **Seluruh jalur ini tertutup 2026-08-21**, urutannya diikuti apa adanya — termasuk alasannya, karena `[BL-087]` mengubah rumus `expected_amount` dan harus mendarat sesudah `[BL-086]` supaya layar tutup kas tidak dibongkar dua kali. `[BL-093]` yang dipecah dari `[BL-087]` menyusul 2026-09-06. Tidak ada sisa yang terbuka dari daftar ini.

### [BL-113] Seeder Demo Menulis Transaksi Tanpa `subtotal_amount` — Laba Kopi Nusantara dan Kopi Story Terbaca Rugi Besar di AI Analysis, MCP, dan Link Data
- **Ditemukan:** 2026-09-15
- **Sumber:** uji ujung ke ujung endpoint link data untuk AI (`[BL-102]` tahap 2) terhadap basis data lokal
- **Status:** Open
- **Prioritas:** Medium — tidak menyentuh transaksi kasir sungguhan, tapi angka yang dibacakan AI saat peragaan salah arah: toko yang sehat terbaca rugi
- **Area Terdampak:**
  - `database/seeders/DemoTransactionSeeder.php:142-148` — insert transaksi tanpa `subtotal_amount` maupun `tax_amount`
  - `database/seeders/CafeStudyCaseSeeder.php:384-390` — sama
  - `database/migrations/2026_08_28_030340_add_tax_columns_to_transactions_table.php:34,46` — kolom baru ber-default 0; pengisian `subtotal_amount = total_amount` hanya berlaku untuk baris yang sudah ada saat migrasi dijalankan
  - `app/Services/ProfitService.php:69-96` — `net_revenue = SUM(subtotal_amount)`, dasar `gross_profit`
- **Deskripsi:**
  Diperiksa dengan kueri baca di basis data lokal 2026-09-15 (transaksi `completed`, 30 hari terakhir): Kopi Nusantara 309 dari 396 tanpa `subtotal_amount`, Kopi Story 389 dari 389, Squid Coffee & Eatery 0 dari 991. Semua baris yang kosong bersumber `pos` dan jatuh dalam satu rentang tanggal (Kopi Nusantara 21 Agu sampai 3 Sep, Kopi Story 2 Agu sampai 4 Sep); baris sesudahnya terisi.

  Akibatnya terlihat paling jelas di link data: "Laba kotor 30 hari Rp -6.383.000 (margin -63,2%)", padahal setiap baris laba per produk bermargin 59–74%. Laba per produk dihitung dari `transaction_items.subtotal`, sedangkan ringkasannya dari `transactions.subtotal_amount` — dua angka yang berselisih di satu paket, dibaca AI yang tidak punya cara mencurigainya. Angka ringkasan yang sama dipakai AI Analysis dan tool `get-profit` MCP.
- **Dugaan Penyebab:** kedua seeder di atas menulis transaksi tanpa kolom pajak, jadi `subtotal_amount` jatuh ke default 0. `SquidCoffeeSeeder` sudah menulisnya (`:476`). Kaitan antara rentang tanggal yang kosong dan kapan seeder itu dijalankan **belum dipastikan** — dugaan ini dicocokkan dari isi seeder dan pola datanya, bukan dari riwayat menjalankannya.
- **Usulan Perbaikan:**
  1. Kedua seeder menulis `subtotal_amount`, `tax_amount`, dan `service_charge_amount` seperti `SquidCoffeeSeeder`, atau lewat `TaxCalculator` bila tenant demonya memungut pajak.
  2. Data lokal yang sudah terlanjur diperbaiki sekali jalan (`subtotal_amount = total_amount` untuk baris dengan `subtotal_amount`, `tax_amount`, dan `service_charge_amount` sama-sama 0), bukan lewat migrasi — ini data demo, bukan skema.
  3. Test penjaga: setiap transaksi `completed` hasil seeder memenuhi `subtotal + pajak + service charge = total`.
- **Temuan ikutan (belum jadi entri sendiri):** sebagian `transaction_items.variant_name` berisi nama lengkap ("Croissant - Plain") alih-alih nama varian ("Plain"), sehingga produk terlaris dan laba per produk memecah satu varian jadi dua baris. Asal baris itu belum ditelusuri.
- **Sampai selesai:** uji prompt `[BL-102]` di Claude, ChatGPT, dan Gemini sebaiknya memakai Squid Coffee & Eatery, satu-satunya tenant demo yang angkanya utuh.

### [BL-107] Foto Struk Belum Bisa Jadi Restock — dan Karena Itu 41 dari 42 Varian Tidak Punya Tanggal Kedaluwarsa
- **Ditemukan:** 2026-09-08
- **Sumber:** Pertanyaan pemilik — *"bisa edit dan nambah dengan note otomatis dari ai … jadi foto struk, melalui vn, dll di dashboard owner"*. Angkanya ditemukan saat memeriksa apakah permintaan itu masuk akal, dan ternyata ia menjawab pertanyaan lain yang lebih mendesak.
- **Status:** Open — **diblokir tiga hal**, urut dari yang paling murah: (1) jalur non-AI di blok "Alternatif murah" di bawah, yang harus dicoba dan diukur lebih dulu; (2) `[BL-106]` sebagai fondasi; (3) satu keputusan harga yang belum diambil.
- **Prioritas:** High
- **Area Terdampak:**
  - `app/Services/StockService.php:60` — `restock(..., ?string $expiryDate = null)`. **Jalur tulisnya sudah lengkap**, dari formulir sampai kolom. Yang kurang bukan mekanismenya — lihat temuan di bawah.
  - `resources/js/Pages/Owner/Stock/Index.vue:173`, `app/Http/Controllers/Owner/StockController.php:86` — kolom kedaluwarsa yang sudah ada di formulir restock dan hampir tidak pernah diisi
  - `app/Services/Upsell/Strategies/PressedStockStrategy.php` — konsumen terbesar `expiry_date`
  - `app/Services/ProofFileService.php:56`, `app/Services/ImageService.php` — penyimpanan + pengecilan gambar di perangkat, sudah ada sejak `[BL-077]`
  - `app/Services/Ai/AiQuota.php:138` — satuan kuota hari ini

- **Temuan yang membalik urutan prioritas, dan ia diukur bukan diduga.**
  Basis data pengembangan, 2026-09-08:

  | | |
  |---|---|
  | Varian aktif | **42** |
  | Punya `expiry_date` | **1** |
  | Restock yang pernah terjadi | **324** |
  | Restock yang mencatat kedaluwarsa | **0** (selain yang satu itu) |
  | Aturan diskon | **0** |

  Artinya jalur `near_expiry` pada Penyelamat Stok — separuh mesinnya, yang punya potongan mendalam (`[BL-018]`) dan skor tertinggi (`PressedStockStrategy::classify()` memberi 100 dikurangi 5 per hari sisa, melawan 40 rata untuk dead stock) — **praktis gelap**. Yang benar-benar menyala cuma jalur dead stock, yang skornya terendah.

  **Penyebabnya BUKAN fitur yang belum ada, dan ini sudah diperiksa ke kode — jangan membangun ulang apa pun di jalur ini.** Rantainya utuh dari ujung ke ujung: formulir restock sudah punya kolomnya (`resources/js/Pages/Owner/Stock/Index.vue:173`), `RestockRequest` sudah memvalidasinya, `StockController::restock():86` sudah meneruskannya, dan `StockService::restock()` sudah menyimpannya. Kolomnya **opsional**, dan manusia yang sedang sibuk tidak mengisi kolom yang boleh dikosongkan. 324 kali berturut-turut.

  Jadi yang gagal adalah *permintaannya*, bukan mekanismenya — dan itu membuka satu jalur yang jauh lebih murah daripada seluruh entri ini, yang **wajib dicoba lebih dulu** sebelum AI dilibatkan:

- **Alternatif murah yang harus dicoba lebih dulu: buat kolomnya sulit dilewatkan, bukan mustahil.**
  Mewajibkan `expiry_date` untuk semua barang salah — kopi bubuk kiloan dan gelas plastik tidak punya kedaluwarsa, dan memaksa mengisinya akan melahirkan tanggal karangan yang lebih buruk daripada kolom kosong. Tapi ada beberapa bentuk yang lebih lunak dan belum dicoba satu pun: mengingat tanggal dari restock sebelumnya pada varian yang sama sebagai nilai awal, menandai varian yang PERNAH punya kedaluwarsa lalu menuntutnya lagi, atau sekadar menyebutkan akibatnya di layar (*"tanpa tanggal ini, barangnya tidak akan pernah masuk saran Penyelamat Stok"*) — hari ini kolom itu tidak menjelaskan apa pun tentang untuk apa ia ada.

  **Kalau jalur murah ini menaikkan angka 1/42 secara berarti, entri ini turun prioritasnya drastis** — foto struk tetap berguna untuk `cost_price` dan untuk kecepatan, tapi ia berhenti jadi satu-satunya jalan keluar. Mengukur dulu, membangun sesudah.

- **Deskripsi:**
  Owner memotret struk belanja dari supplier. AI membaca barisnya — nama barang, jumlah, harga modal, tanggal kedaluwarsa bila tercetak — dan mengembalikannya sebagai **usulan restock bertipe**, bukan sebagai tulisan. Owner meninjau, mencocokkan tiap baris ke varian yang benar, menyunting yang salah baca, lalu menyetujuinya. Yang menulis tetap `StockService::restock()`, dengan `StockMovement` seperti biasa.

- **Kenapa usulan, bukan langsung tulis — dan ini bukan kehati-hatian umum.**
  `cost_price` adalah masukan `ProfitService`. Struk yang salah baca dan langsung mendarat **meracuni margin diam-diam**, lalu margin yang salah ikut menggerakkan bracket Harga Adaptif lewat `TenantMonthlyMetric`. Kesalahannya tidak akan terlihat sebagai kesalahan; ia akan terlihat sebagai angka. Gerbang tinjauan di sini bukan birokrasi — ia satu-satunya yang menahan OCR buruk keluar dari pembukuan. Alasan lengkapnya di `[BL-106]`.

- **Pemblokir yang harus diputuskan SEBELUM kodenya ditulis: satuan kuota.**
  `AiQuota` hari ini menghitung **analisis per hari**, bawaan 5 (`Plan::LIMIT_AI_DAILY`, atau `AiQuotaPolicy` baseline). Foto struk adalah unit dengan frekuensi jauh lebih tinggi **dan** token gambar berkali lipat teks. Kalau satu scan memakan satu jatah analisis, gratisannya habis di restock kedua — dan fitur yang paling sering dipakai akan jadi yang paling cepat mentok.

  Ini keputusan harga, bukan keputusan teknis, dan `[BL-069]` sudah menetapkan preseden bahwa kuota AI dijual sebagai blok bulanan. Yang perlu dijawab: apakah scan struk punya anggaran sendiri, ikut anggaran analisis, atau justru tidak dibatasi sama sekali karena ia mengisi data yang menguntungkan aplikasi itu sendiri. **Memutuskannya setelah ada yang memakai berarti mengubah harga pada pengguna yang sudah terbiasa.**

- **Yang TIDAK termasuk entri ini:** mencocokkan baris struk ke varian secara otomatis. Nama di struk supplier hampir tidak pernah sama dengan nama di katalog, dan menebaknya berarti mengarang. Pencocokan tetap kerja manusia di layar tinjauan; AI boleh menyarankan kandidat, tidak boleh memutuskan.

- **Sesudah ini terbuka:** `[BL-105]` butir 4 jadi jujur — "Penyelamat Stok" baru pantas jadi nama unggulan setelah bahan bakarnya benar-benar terisi.

---

### [BL-106] Harness AI Hanya Bisa Bicara — Belum Bisa Melihat Lampiran, dan Belum Punya Tempat Menaruh Usulan
- **Ditemukan:** 2026-09-08
- **Sumber:** Pertanyaan pemilik — *"bagaimana saya tingkatkan kemampuan agent harness nya? jadi bukan sekedar saran, tapi bisa edit dan nambah dengan note otomatis dari ai dengan memberikan chat logs untuk tracebility"*
- **Status:** Open — fondasi bagi `[BL-107]`
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Services/Ai/AiProvider.php:11` — `generate(string $systemPrompt, array $context, string $userPrompt): AiResult`
  - `app/Services/Ai/AiResult.php` — hanya `text` dan `tokensUsed`
  - `app/Services/Ai/AnthropicProvider.php:17-28` — satu pesan user, teks saja, tanpa blok gambar
  - `app/Services/Ai/AiProviderFactory.php:23-28` — empat provider di balik satu `match`
  - `database/migrations/*_create_ai_analyses_table.php:21-22` — satu `prompt`, satu `result`
  - `app/Mcp/Tools/BusinessDataTool.php` — seluruh tool MCP `#[IsReadOnly]`, owner-only

- **Deskripsi — jarak sebenarnya, diverifikasi ke kode:**

  | Yang diminta | Yang ada hari ini |
  |---|---|
  | AI membaca foto | `generate()` hanya menerima tiga string; tak satu pun provider mengirim blok gambar |
  | AI membaca suara | tidak ada jalur audio di mana pun |
  | AI mengusulkan perubahan | balasannya `string $text`; tidak ada bentuk terstruktur |
  | Chat log untuk telusur | `ai_analyses` = satu prompt, satu hasil. Bukan percakapan |
  | Catatan otomatis pada data | tidak ada satu pun kolom di aplikasi ini yang menyebut asal-usul AI |

  Tool MCP memang ada, tapi ia bukan jalannya: seluruhnya read-only dan dipanggil **client milik owner** (Claude Desktop), bukan oleh aplikasi.

- **Keputusan bentuk, dan ia dipaksa oleh keputusan-keputusan yang sudah diambil aplikasi ini sendiri.**
  Empat preseden yang semuanya mengatakan hal yang sama:

  1. `DiscountRule` — *"Aturan ini ADALAH persetujuannya. Sistem tidak pernah menurunkan harga sendiri."*
  2. `[BL-035]` — *"Yang tetap terlarang adalah setelan yang mendarat tanpa pernah muncul di layar."*
  3. `[BL-087]` — uang keluar laci di atas ambang tercatat dan terlihat, tapi belum mengurangi apa pun sampai owner menyetujui.
  4. `[BL-018]` — lantai untung hanya manusia yang boleh menembus.

  AI yang menulis langsung ke basis data membatalkan keempatnya sekaligus. Bentuk yang sah di aplikasi ini: **AI mengusulkan perubahan bertipe, manusia yang menjadikannya nyata**, dan yang menulis tetap service yang sudah ada beserta jejaknya (`StockMovement`, `transaction_edits`, dst).

  Bonusnya bukan sekadar keamanan. Kalau nasib tiap usulan ikut dicatat — diterima, disunting, ditolak — aplikasi ini bisa menjawab **"seberapa sering AI-nya benar"**, persis seperti `upsell_events` menjawabnya untuk saran jual. Itu pertanyaan yang hampir tidak ada produk lain bisa jawab tentang fitur AI-nya sendiri.

- **Yang perlu dikerjakan:**
  1. **`AiProvider` tumbuh dua hal:** menerima lampiran (gambar, kelak audio) dan mengembalikan **JSON bertipe sesuai skema**, bukan prosa. `AiResult` ikut tumbuh.
  2. **Tabel percakapan + usulan + provenance.** Percakapan (pesan, peran, lampiran), usulan (perubahan bertipe, status `pending|applied|edited|rejected`, siapa yang menerapkan, kapan), dan tautan dari baris yang akhirnya tertulis kembali ke pesan yang melahirkannya — supaya owner bisa mengklik dari sebuah mutasi stok ke foto struk asalnya. **Itulah arti "traceability" pada permintaan aslinya**, dan ia hanya berguna kalau tautannya dua arah.
  3. Tabelnya **lahir sebelum permukaannya**, alasan yang sama dengan `upsell_events` (`[BL-017]` usulan 5) dan `expired_stock_records` (`[BL-105]`).

- **JANGAN bangun agent loop lebih dulu.** Istilah "agent harness" pada permintaan aslinya menyiratkan percakapan multi-giliran dengan tool calling. Untuk struk→restock dan VN→catatan, **structured output sudah cukup**: satu panggilan, satu balasan JSON. Deterministik, gampang diuji, biayanya terduga. Loop multi-giliran baru perlu untuk "lakukan apa saja yang saya minta" — dan di situ biaya, latensi, serta cara gagalnya meledak bersamaan. Kalau ternyata benar-benar dibutuhkan, ia dibangun di atas fondasi ini, bukan menggantikannya.

- **Percabangan yang harus diputuskan untuk voice note, dan ia mahal kalau salah pilih.**
  Chat API tidak setara soal audio: Gemini menerimanya langsung, OpenAI menuntut endpoint transkripsi terpisah, Anthropic tidak menerima audio sama sekali. Dukungan SumoPod — provider bawaan — **belum diperiksa dan jangan ditebak**. Artinya `AiProviderFactory` yang hari ini rapi justru karena keempat provider setara akan berhenti setara.

  **Alternatif yang layak dipertimbangkan lebih dulu: Web Speech API di peramban.** Transkripsi terjadi di HP, gratis, tanpa provider, tanpa kuota, dan AI tidak pernah menerima audio sama sekali — seluruh percabangan di atas lenyap. Bahasa Indonesia didukung. Ongkosnya: butuh online, dan dukungan iOS/Safari buruk. Untuk basis pengguna Android-first, ini kemungkinan pilihan yang lebih baik, tapi **harus diuji di perangkat nyata sebelum diputuskan**, bukan dipilih dari dokumentasi.

- **VN di kasir: ditolak sebagai perintah, diterima sebagai alasan.**
  Sebagai perintah ia menabrak `PRODUCT.md` (*"Speed is the product. Hesitation anywhere breaks trust."*) dan menabrak mode offline: rekam → unggah → transkripsi → konfirmasi, di lingkungan paling berisik di toko, pada satu-satunya alur yang tidak boleh ragu.

  Tapi ada satu tempat di kasir yang justru cocok: **VN sebagai alasan yang sudah wajib ditulis** — `cash_drawer_movements` menuntut alasan (`[BL-087]`), begitu juga void. Mengetik alasan di HP saat antrean panjang persis situasi di mana VN menang, dan latensinya tidak menahan apa pun karena penjualannya sudah selesai. Ini sempit dan aman, dan hanya layak dikerjakan **setelah** VN owner terbukti dipakai.

- **Satuan kuotanya diputuskan di `[BL-107]`, bukan di sini** — karena di sanalah pemakaian frekuensi tingginya mendarat.

---

### [BL-103] Bundling Berdiskon Belum Ada Wujudnya — dan Dua Komentar Kode Masih Menunggu Entri yang Sudah Selesai
- **Ditemukan:** 2026-09-06 (saat membersihkan catatan usang di `docs/BACKLOG.md`)
- **Sumber:** Catatan penutup `[BL-018]` sendiri, 2026-08-19: *"YANG TERSISA, dan ia entri tersendiri: bundling berdiskon."* Baris arsipnya juga menuliskannya (*"bundling berdiskon tetap pekerjaan tersendiri"*), dan catatan pemilik 2026-08-13 menyebutnya sekali lagi. Disebut tiga kali sebagai pekerjaan tersendiri, dan tidak pernah dibuatkan entrinya — jadi begitu `[BL-018]` diarsipkan, ia tidak punya rumah di mana pun
- **Status:** Open untuk butir 2 — **butuh keputusan pemilik soal BENTUK bundelnya sebelum ada kode.**
  > **BUTIR 1 SELESAI 2026-09-07.** Lihat `[HOTFIX] Strip Saran Berhenti Mengutip Harga Katalog untuk Barang yang Sedang Berdiskon (BL-103 Butir 1)` di `docs/CHANGELOG.md`. Ketiga strategi yang menyarankan varian kini mengutip harga efektif, dan kedua komentar yang menunggu `[BL-018]` sudah diperbarui. Yang menahan entri ini tetap terbuka sekarang murni bundelnya — pertanyaan produk, bukan cacat yang terlihat kasir.
- **Prioritas:** Low sebagai fitur (belum ada yang memintanya sejak 2026-08-19). Butir 1 yang berprioritas Medium sudah mendarat
- **Area Terdampak** (lima baris pertama **sudah beres 2026-09-07** lewat butir 1; dibiarkan tertulis karena ia peta cacatnya, bukan daftar tugas):
  - ~~`app/Services/Upsell/Strategies/PressedStockStrategy.php:24-26` — docblock masih berbunyi *"Versi berdiskonnya menunggu [BL-018]…"*~~ — docblock sudah diperbarui
  - ~~`resources/js/Pages/Cashier/POS.vue:548-549` — komentar yang sama, kalimat yang sama, entri yang sama~~ — ikut diperbarui
  - ~~`app/Services/Upsell/Strategies/PressedStockStrategy.php:63,67` — `extraAmount` dan `suggestedVariantPrice` diambil dari `$variant->price`, harga KATALOG~~ — kini harga efektif
  - ~~`app/Services/Upsell/Strategies/UpsizeVariantStrategy.php:54,70` — sama, dari `$step->price`~~ — kini selisih dua harga efektif
  - ~~`resources/js/Pages/Cashier/POS.vue:541,550` — baris keranjang menerima `suggestion.suggested_variant_price` apa adanya~~ — tetap apa adanya, dan sekarang itu benar: angkanya sudah berdiskon dari server
  - **`app/Services/Upsell/Strategies/ManualRuleStrategy.php` — instansi KEEMPAT yang tidak tercatat saat entri ini ditulis.** Ikut diperbaiki 2026-09-07: cacatnya sama persis, dan aturan yang ditulis owner tidak membebaskan strip dari mengutip harga yang akan ditagih
  - `resources/js/Pages/Cashier/POS.vue:380,774` — bandingkan: jalur katalog biasa memakai `variant.effective_price ?? variant.price`
  - `app/Services/TransactionService.php:683` — `resolveItemPrice()`, server SELALU menghitung ulang harganya dan mengabaikan `unit_price` kiriman klien
  - `app/Services/DiscountService.php`, `database/migrations/2026_08_19_112853_create_discount_rules_table.php` — mesin diskon dan lantai marginnya sudah berdiri penuh
  - **Bundling sendiri: nol kode.** Penyisiran `bundl|combo|paket hemat` di `app/`, `database/`, `resources/js/`, dan `config/` tidak menghasilkan satu pun kecocokan
- **Deskripsi — dua hal yang bertetangga tapi bisa dikerjakan terpisah:**
  **(a) Sisa `[BL-018]` yang tertinggal di jalur upsell.** Strip saran mengutip harga KATALOG, sementara kartu katalog sudah memakai `effective_price` sejak `[BL-018]`. Varian yang sedang berdiskon karena itu masuk keranjang dengan harga berbeda tergantung tombol mana yang ditekan kasir — dari grid katalog ia masuk berdiskon, dari strip saran ia masuk pada harga penuh.
  **(b) Bundling berdiskon belum ada wujudnya.** Yang dimaksud bukan diskon per barang — itu sudah selesai — melainkan potongan yang lahir dari KOMBINASI: "Nasi Goreng + Teh Manis Rp 25.000" untuk barang yang kalau dibeli sendiri-sendiri berjumlah Rp 28.000.
- **Yang menahan (a) supaya tidak jadi kesalahan uang, dan kenapa ia tetap harus diperbaiki:** `resolveItemPrice()` mengabaikan harga kiriman klien sepenuhnya, jadi yang DITAGIH selalu benar. Yang salah adalah angka yang dibacakan kasir kepada pelanggan dan total keranjang sebelum bayar — dan arahnya selalu sama: strip mengutip LEBIH MAHAL daripada yang akan ditagih, karena potongan hanya pernah menurunkan harga. Ini persis "jalur harga keempat" yang `[BL-018]` catat sendiri di catatan penutupnya — layar menyebut satu angka lalu menagih angka lain, dan kasir tidak punya cara menjelaskan selisihnya.
- **Kenapa (b) bukan sekadar "diskon dengan syarat dua barang":** bundel menuntut jawaban yang tidak dipunyai mesin diskon hari ini — potongannya melekat pada apa, stoknya berkurang dari mana, dan margin dihitung terhadap apa. `discount_rules` menyimpan potongan PER VARIAN; sebuah bundel tidak punya varian tunggal untuk ditempeli.
- **Usulan Perbaikan (urut; butir 1 berdiri sendiri sepenuhnya):**
  1. ~~**Tutup sisa `[BL-018]` lebih dulu — murah, dan tidak menunggu keputusan apa pun.**~~ **SELESAI 2026-09-07.** Ketiga strategi yang menyarankan varian memakai harga efektif, dan kedua komentar diperbarui. Tiga hal yang baru terlihat saat dikerjakan, dan ketiganya sudah tertulis di entri changelog-nya: `ManualRuleStrategy` ternyata instansi keempat dari cacat yang sama; `extraAmount` naik ukuran kini bisa **negatif** saat varian besar sedang berdiskon lebih dalam daripada pemicunya, sehingga rasionya ditahan di nol supaya skornya tetap di pita 30–40; dan `priceFor()` tidak bisa membedakan "tidak punya aturan" dari "aturannya belum dicari", jadi lahir `DiscountService::effectivePrice()` yang menjawab dari peta `rulesFor()` alih-alih memicu satu kueri per varian.
  2. **Baru definisikan bundelnya**, sesudah keputusan di bawah turun.
- **Yang perlu diputuskan pemilik sebelum butir 2 — empat pertanyaan yang menentukan skemanya, bukan tampilannya:**
  1. **Bundel itu ENTITAS atau ATURAN?** Entitas = produk tersendiri dengan komposisi dan harganya sendiri, muncul di katalog seperti barang lain. Aturan = potongan yang berlaku begitu kombinasi tertentu ada di keranjang, tanpa barang baru. Keduanya masuk akal dan menghasilkan skema yang sama sekali berbeda; yang pertama menuntut penurunan stok komponen, yang kedua tidak.
  2. **Marginnya dihitung terhadap apa?** Lantai `cost_price × (1 + min_margin_percent/100)` hari ini berlaku per varian. Untuk bundel, lantainya berlaku per komponen atau atas total bundelnya? Jawaban "per total" memungkinkan satu komponen dijual di bawah lantainya sendiri — sah secara bisnis, tapi harus disengaja dan tercatat, bukan efek samping.
  3. **Bundel muncul di mana?** Sebagai barang di katalog, sebagai jenis saran upsell kelima, atau keduanya.
  4. **Apakah bundel boleh memuat barang yang sedang berdiskon?** Potongan bertumpuk adalah cara tercepat menembus lantai tanpa ada yang memutuskannya.
- **Yang JANGAN dilakukan saat mengerjakannya kelak:**
  1. **Jangan membuat bundel dengan menurunkan `product_variants.price`.** Itu persis yang `[BL-018]` tolak: potongan yang tidak bisa dibedakan dari perubahan harga permanen, dan laporan yang tidak pernah bisa menjawab "berapa yang kita korbankan".
  2. **Jangan menegakkan lantai margin hanya di sisi harga.** `[BL-018]` menutup entrinya dengan syarat eksplisit: lantai **wajib** ikut ditegakkan di sisi SARAN. Mesin tidak pernah boleh menyarankan harga yang manusia saja boleh menembusnya.
  3. **Jangan menambah jenis saran kelima tanpa saklarnya.** `[BL-099]` baru saja memberi keempat jenis saran saklar per-tenant lewat `Tenant::upsellTypeColumns()`; jenis kelima yang lahir tanpa kolomnya akan jadi satu-satunya yang tidak bisa dimatikan owner — dan itu baru ketahuan setelah ada yang ingin mematikannya.

### [BL-102] Konektor Data Lewat URL — Jalur Non-MCP untuk Pemula, dengan Kredensial yang Menumpang di Query String
- **Ditemukan:** 2026-09-05 (permintaan pemilik, belum diputuskan)
- **Sumber:** "selain MCP, itu ada konektor langsung via fetch, karena kredensialnya itu langsung di params url, cocok untuk pemula dan sisa copy url dan prompt bawaan untuk petunjuk ke AI pengguna, jadi nanti AI pengguna tahu untuk fetch website datanya — dan beri tahu kalau ini cukup bahaya"
- **Status:** In Progress — **bentuknya diputuskan pemilik 2026-09-15** (lihat blok "Keputusan pemilik 2026-09-15" di akhir entri). **Tahap 1 selesai 2026-09-15:** UI kelola link di halaman Integrasi (`[ADDITION] Link Data untuk AI di Halaman Integrasi: Owner Membuat, Melihat, dan Mencabut Link Berumur, Endpoint Datanya Menyusul (BL-102 Tahap 1)`). **Belum:** endpoint `/api/v1/connector/summary` (link yang disalin masih 404), dan uji prompt di Claude, ChatGPT, dan Gemini. Butir 1 diubah pemilik pada hari yang sama sesudah `[BL-112]` ditutup: link konektor kini **token Sanctum**, bukan tabel sendiri
- **Prioritas:** Low — keputusan bentuk yang dulu membuatnya High sudah diambil; tidak ada yang rusak hari ini
- **Area Terdampak (kalau kelak dikerjakan):**
  - `routes/ai.php:18-19` — `Mcp::web('/mcp/business')` di balik `auth:sanctum` + `tenant.api` + `feature.api:ai` + `role:owner` + `throttle:mcp`; jalur baru harus menjawab gerbang yang sama, bukan melewatinya
  - `app/Mcp/Tools/` — `GetSalesSummaryTool`, `GetProfitTool`, `GetMenuTool`; datanya sudah ada dan sudah teragregasi, jadi yang dibicarakan di sini **hanya pintunya**, bukan isinya
  - `app/Http/Controllers/Owner/Settings/IntegrationController.php` — `generateMcpToken()`/`revokeMcpToken()`, token Sanctum bernama `mcp-client` dengan ability `mcp:use`, plaintext hanya di-flash sekali
  - `resources/js/Pages/Owner/Settings/Integrations.vue` — tempat URL dan prompt bawaan itu akan disalin owner
- **Deskripsi (apa yang diminta):**
  MCP hari ini mensyaratkan pengguna memasang server MCP di klien AI-nya — langkah yang wajar bagi orang teknis dan tembok bagi pemilik warung. Usulan pemilik: sediakan jalur kedua yang tidak butuh pemasangan apa pun. Owner menyalin **satu URL** (kredensialnya ikut di dalam URL sebagai query param) dan **satu prompt bawaan**, menempelkannya ke AI apa pun yang sudah bisa mengambil halaman web, lalu AI itu mengambil sendiri datanya dan menjawab pertanyaan bisnis. Nol konfigurasi, nol istilah teknis.
- **Kenapa ini memang menarik, supaya tidak ditolak karena alasan yang salah:** jalurnya bukan mengendurkan keamanan demi kemalasan — ia menjangkau kelompok pengguna yang MCP tidak akan pernah jangkau. Datanya pun sudah agregat tanpa data pelanggan (lihat `#[Instructions]` di `SapiBusinessServer`), jadi yang bocor kalau bocor adalah angka penjualan dan margin, bukan identitas orang.
- **Kenapa pemilik sendiri menyebutnya "cukup bahaya" — dan ini bagian yang harus utuh sebelum ada kode:**
  1. **Query string bocor ke tempat yang tidak dikendalikan siapa pun di sini.** Ia tercatat di access log server dan proxy, di riwayat peramban, di header `Referer`, dan — yang paling menentukan — **di riwayat percakapan penyedia AI pengguna**. Token yang ditempel ke chat pihak ketiga sudah keluar dari kendali aplikasi ini sejak detik pertama.
  2. **Menempel = menyerahkan.** Sekali URL itu ada di sebuah percakapan, siapa pun yang bisa membaca percakapan itu bisa membaca data tokonya, kapan saja, tanpa membuka SAPI.
  3. **Token `mcp-client` hari ini berumur setahun dan lingkupnya tidak dibatasi sama sekali.** *(Dikoreksi 2026-09-15 — tulisan semula menyebut "tidak punya masa berlaku" dan "dibatasi `mcp:use`"; keduanya keliru.)* Umurnya mengikuti `SANCTUM_TOKEN_EXPIRY=525600` menit (365 hari) yang berlaku untuk semua token Sanctum. Ability `mcp:use` ditulis saat token dibuat tapi tidak diperiksa di mana pun, jadi token itu diterima seluruh rute `auth:sanctum`, termasuk void transaksi — lihat `[BL-112]`. Untuk konektor URL, umur setahun di riwayat chat pihak ketiga tetap terlalu panjang.
  4. **Prompt bawaan mengajari AI pengguna mengambil URL berkredensial.** Yang diajarkan bukan cuma cara memakai fitur, tapi kebiasaan — dan kebiasaan itu terbawa ke URL lain.
- **Yang perlu diputuskan pemilik lebih dulu (bukan detail teknis, ini yang menentukan bentuknya):**
  - Apakah konektor ini memakai **kredensial terpisah** dari token MCP — token khusus, hanya-baca, berumur pendek, bisa dicabut satu-satu dan terlihat kapan terakhir dipakai — atau menumpang token yang sudah ada. (Menumpang berarti mencabut kebocoran juga mematikan MCP-nya.)
  - Apakah ia **berumur** (mis. 7/30 hari, otomatis mati) atau hidup sampai dicabut.
  - Apakah ia mengembalikan **satu ringkasan tetap** atau tetap bisa memilih rentang tanggal lewat parameter.
  - Bagaimana bahayanya **disampaikan** — kalimat peringatan tidak cukup kalau tombolnya tetap berlabel "Salin URL". Kelompok pengguna yang jadi alasan fitur ini ada adalah kelompok yang paling kecil kemungkinannya membaca peringatan.
- **Catatan arah (bukan keputusan):** bahaya nomor 1 dan 2 melekat pada "kredensial di URL", bukan pada "konektor tanpa pemasangan". Sebelum menerima keduanya, pantas dicek dulu apakah tujuannya bisa dicapai tanpa itu — misalnya URL berumur pendek yang bisa dicabut, atau kredensial di header yang tetap bisa disalin sekali. Kalau ternyata tidak bisa, terimalah risikonya dengan sadar, jangan diam-diam.
- **Yang JANGAN dilakukan saat mengerjakannya kelak:** membuka rute konektor di luar gerbang yang sudah dipakai `/mcp/business` (`tenant.api`, `feature.api:ai`, `role:owner`, throttle). Pintu yang lebih mudah tidak boleh berarti pagar yang lebih rendah — kalau modul AI tenant mati atau langganannya lewat tenggat, konektor ini harus ikut mati.
- **Keputusan pemilik 2026-09-15 — keempat pertanyaan di atas terjawab, semuanya mengikuti rekomendasi:**
  1. **Kredensial: link terpisah, boleh lebih dari satu — disimpan sebagai token Sanctum ber-ability `connector:read`.** *(Diubah pemilik 2026-09-15 sesudah `[BL-112]` ditutup. Semula: tabel sendiri, dengan alasan token Sanctum diterima semua rute `auth:sanctum` — alasan yang gugur begitu setiap rute bertoken menuntut ability.)* Satu link = satu baris `personal_access_tokens`: `name` jadi label (mis. "ChatGPT"), `expires_at` jadi tanggal mati, `last_used_at` diisi Sanctum sendiri, dan dicabut satu per satu tanpa menyentuh token MCP. Token yang bocor lalu dipasang di header `Authorization` tertolak di rute lain dengan `token_ability_missing`. Gerbangnya sama dengan `/mcp/business`: `auth:sanctum`, `ability:connector:read`, `tenant.api`, `feature.api:ai`, `role:owner`, dan throttle.
  2. **Masa berlaku: wajib, owner memilih 7 atau 30 hari, default 30.** Tidak ada pilihan "sampai dicabut". Memperpanjang berarti membuat link baru — link lama tidak diperpanjang, supaya salinan yang sudah tersebar tetap mati pada tanggalnya.
  3. **Isi: satu paket tetap tanpa parameter.** Nama usaha, hari ini, 7 hari, 30 hari, bulan berjalan vs bulan lalu, produk terlaris, profit, dan menu — disusun dari layanan yang sama dengan tool MCP (`AiContextService`, `ProfitService`, `ProductCatalogService`), supaya angkanya tidak bisa berbeda dari jawaban MCP. Alasan tanpa parameter adalah batas klien, bukan kesederhanaan — lihat butir berikutnya.
  4. **Penyampaian bahaya: dialog + centang + riwayat pakai.** Tidak ada tombol "Salin URL" langsung. Membuat link membuka dialog: pilih masa berlaku, lalu centang kalimat konkret bertanggal ("Siapa pun yang memegang link ini bisa melihat penjualan dan profit toko saya sampai 15 Okt 2026"). URL tampil **sekali**, seperti token MCP. Daftar link aktif menampilkan label, tanggal mati, dan "terakhir dipakai".
- **Keputusan pemilik 2026-09-15 (prompt bawaan):** prompt dikirim sebagai **satu pesan tanpa pertanyaan**, ditutup tes baca: AI diminta menyebut nama usaha yang tercatat dan daftar produk yang dijual, lalu menyatakan siap. Pertanyaan pertama owner baru dikirim di pesan kedua. Ditambahkan saat menuliskannya: AI yang gagal membuka link diminta mengatakannya, bukan menebak, karena tanpa kalimat itu tes baca bisa lulus dengan jawaban karangan. Akibatnya bagi endpoint: **nama usaha wajib ada di paket** (butir 3), dan daftar produk harus cukup ringkas untuk dibacakan ulang.
- **Catatan untuk yang mengerjakan butir 1** (diperiksa di `vendor/laravel/sanctum` 2026-09-15):
  - **Membaca token dari query string tidak butuh middleware tambahan.** `Sanctum::getAccessTokenFromRequestUsing()` mengganti cara `Guard` mengambil token (`Guard.php:85`). Callback-nya berlaku global dan **menggantikan** pembacaan header, jadi ia hanya boleh membaca `?token=` untuk rute konektor dan harus mengembalikan `$request->bearerToken()` untuk yang lain — kalau tidak, seluruh API mobile dan MCP ikut tertolak.
  - **Masa berlaku dan jejak pakai sudah ditangani Sanctum.** `Guard::isValidAccessToken()` menolak token yang `expires_at`-nya lewat *dan* yang umurnya melewati `SANCTUM_TOKEN_EXPIRY` (`Guard.php:128-129`); `last_used_at` diperbarui di setiap permintaan yang lolos. Pilihan 7/30 hari cukup diberikan sebagai argumen ketiga `createToken()`.
  - **Label link jangan pernah `mcp-client` atau `mobile-app`.** `IntegrationController` mencabut MCP dengan `where('name', 'mcp-client')->delete()` dan login mobile mencabut `where('name', 'mobile-app')`; link yang kebetulan berlabel sama ikut terhapus. Daftar link aktif disaring lewat ability, bukan nama.
  - **Token `*` ikut lolos `ability:connector:read`.** Token login mobile yang bocor bisa membaca konektor. Itu tidak menambah bahaya — token yang sama sudah bisa jauh lebih banyak — tapi daftar link aktif di halaman Integrasi harus menyaring token yang abilities-nya memuat `connector:read`, bukan "semua token yang lolos gerbangnya".
  - **Token Sanctum berbentuk `{id}|{rahasia}`.** Karakter `|` di URL perlu diuji di ketiga AI; kalau ada yang mengubah atau memotongnya, tulis link dengan `%7C` sejak awal. Panjang link tetap tunduk pada batas 250 karakter di bawah.
- **Batas klien yang sudah diperiksa** (dokumentasi *web fetch tool* Claude API, `platform.claude.com/docs/en/agents-and-tools/tool-use/web-fetch-tool`, dibaca 2026-09-15):
  - Claude hanya boleh mengambil URL yang sudah muncul di percakapan — pesan pengguna, atau hasil fetch/search sebelumnya. URL yang disusunnya sendiri ditolak (`url_not_in_prior_context`). Inilah alasan butir 3: AI tidak bisa mengganti `?from=` sendiri.
  - URL maksimal **250 karakter** (`url_too_long`) — domain, path, dan token harus muat.
  - Tipe konten yang diterima hanya **text, HTML, dan PDF** (`unsupported_content_type`), dan halaman yang dirender JavaScript tidak didukung. Jadi responsnya teks/Markdown yang dirender server — **bukan JSON dan bukan halaman Inertia**.
  - `robots.txt` dihormati (`url_not_allowed`). `public/robots.txt` hari ini mengizinkan semua path; kalau kelak diperketat, path konektor harus tetap diizinkan.
  - Hasil fetch **di-cache di sisi penyedia** — angka bisa tertinggal, dan isi respons dari URL berkredensial ikut tersimpan di cache mereka. Satu alasan lagi untuk butir 2.
  - Ini dokumentasi **API**. Perilaku aplikasi claude.ai, ChatGPT, dan Gemini **belum diuji** — uji ketiganya dengan URL sungguhan sebelum prompt bawaan difinalkan, jangan diasumsikan sama.

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
- **Bila keluhannya benar-benar muncul, timbang (viii) LEBIH DULU — perbandingan yang tidak pernah dilakukan saat keputusannya diambil (ditambahkan 2026-08-31):**
  Waktu `[BL-080]` butir (b) diputuskan, pertanyaan yang sedang dijawab masih **"omzet bulan mana yang dipakai"**. Di pertanyaan itu (iv-b) memang kandidat kuat dan (viii) terlihat seperti pengalihan pokok soal. Opsi (i) sudah menutup pertanyaan itu sepenuhnya — tidak ada lagi angka yang salah. Yang tersisa di entri ini pertanyaan yang **berbeda**: berapa hari peringatan yang didapat tenant berjangkar 1–7. Keduanya menjawab pertanyaan baru itu dengan penuh, tapi ongkosnya beda **jenis**, dan keduanya belum pernah disandingkan di halaman yang sama:

  | | (iv-b) jendela 30 hari | (viii) bagian Adaptif ditagih di belakang |
  |---|---|---|
  | Masa siap jangkar 1–7 | kembali 7 hari | kembali 7 hari |
  | Consent v2 + tanda tangan ulang | **wajib** | **tidak** — pengumpulannya tetap "sekali sebulan, setelah bulan berjalan tutup", persis kalimat yang sudah ditandatangani |
  | Kalimat aturan `[BL-056]` + `README.md` | berubah | tetap |
  | Bentuk `tenant_monthly_metrics` | `period` bukan lagi label bulan; retensi & `--period YYYY-MM` ikut terseret | tidak tersentuh |
  | Ketergantungan tagihan pada data yang belum ada | tetap ada, hanya selalu kebetulan terpenuhi | **hilang total** — tagihan tidak pernah lagi butuh angka yang belum ditulis |
  | Ongkos yang ditanggung | tanda tangan ulang; sebagian tenant tidak menyetujui lagi dan keluar dari keringanan yang dibuat untuk mereka | satu baris "koreksi bulan lalu" di tagihan berikutnya, dan pertanyaan dukungan pertama yang akan masuk karenanya |
  | **Siapa yang menanggung** | **tenant** | **kita** |

  Baris terakhir itu yang memutuskan. Ongkos (iv-b) dibayar oleh orang yang sedang kita bantu — mereka diminta menandatangani ulang pengumpulan data omzetnya demi memulihkan 1–6 hari pemberitahuan, dan sebagian akan menjawab tidak. Ongkos (viii) dibayar oleh kita, berupa satu baris tagihan yang harus bisa dijelaskan. Untuk menukar hal yang sama, ongkos yang jatuh ke pihak kita lebih pantas.

  **Yang harus jujur disebut soal (viii), karena ia belum pernah ditulis di mana pun:** menagih di belakang memindahkan satu koreksi ke **ujung hubungan**. Tagihan pertama tenant tidak punya baris koreksi, dan tenant yang berhenti berlangganan meninggalkan satu koreksi terakhir yang tidak punya tagihan berikutnya untuk ditumpangi. Itu keputusan tersendiri — dihapuskan, atau diterbitkan sebagai tagihan penutup — dan harus dijawab sebelum (viii) dikerjakan, bukan ditemukan saat tenant pertama pergi. `SubsidyEstimator` juga tetap wajib ikut: tenant akan melihat perkiraan di layar lebih dulu, lalu koreksinya, dan dua angka untuk satu bulan yang tidak saling menjelaskan adalah aduan berikutnya.

  **Yang TIDAK berubah dari keputusan 2026-08-20:** (viii) juga bukan alasan membuka entri ini sekarang. Kedua syarat pembuka di atas tetap berlaku apa adanya — yang berubah hanya **opsi mana yang ditimbang pertama** begitu syaratnya terpenuhi. Dan bila yang terpenuhi syarat nomor 2 (consent naik versi untuk alasan lain), urutannya berbeda lagi: di sana ongkos termahal sudah dibayar orang lain, dan yang layak dipertimbangkan **(iv)**, bukan (iv-b) — hanya (iv) yang benar-benar menghapus dua penanggalan yang berselisih, sementara (iv-b) menyisakannya dengan jendela yang kebetulan selalu penuh.
- **Syarat pembuka nomor 1 hari ini bergantung pada tenant yang MENGADU — tidak ada tanda yang lebih awal (dicatat 2026-08-31, belum dikerjakan):**
  Penundaan yang wajar hanya muncul sebagai satu baris keluaran agregat di `subscriptions:advance-lifecycle` — "Menunggu ringkasan omzet : N tenant". Ia sengaja **tidak** masuk `PlatformAuditLog`, dan alasannya benar: penundaan beberapa hari adalah keadaan NORMAL yang berulang tiap bulan bagi tenant berjangkar awal bulan, dan jejak audit yang terisi hal yang sama tiap hari menenggelamkan kejadian yang benar-benar perlu terlihat. **Jangan balik keputusan itu.** Yang punya surel dan jejak audit tetap hanya yang lewat jatuh tempo (`invoices.postponement-overdue`), dan itu keadaan yang berbeda.

  Yang tidak dijawab baris itu: **siapa, dan jangkar berapa.** Akibatnya hari pertama ada tenant Adaptif berjangkar 1–7 tidak memberi tanda apa pun — pola yang persis sama dengan yang membuat `[BL-080]` tidak terlihat sampai seseorang mengukurnya. Bedanya, dan ini yang menahan prioritasnya tetap Low: di sana yang diam adalah angka uang yang salah, di sini cuma masa siap.

  **Populasi per 2026-08-31:** 2 tenant, 1 di jalur `subsidized`, jangkarnya tanggal **24** — zona aman. Yang bisa memicu syarat 1 hari ini **nol**, jadi tripwire apa pun akan mencetak angka 0 untuk sementara. Nilainya ada di masa depan, bukan sekarang, dan itu sebabnya ia dicatat alih-alih dikerjakan.

  **Bila dikerjakan nanti:** cukup lebarkan baris laporan yang sudah ada — cacah tenant Adaptif berjangkar 1–7 di antara yang tertunda, atau jangkar terkecilnya. Dua hal yang harus dijaga: (1) tetap baris laporan, jangan dinaikkan jadi jejak audit atau surel, karena yang dicari sinyal untuk KITA dan bukan peringatan untuk tenant; (2) ia **tidak** menggerakkan keputusan yang sedang ditahan — yang berubah hanya KAPAN kita tahu syarat 1 terpenuhi, bukan apa yang kita putuskan sesudahnya.
- **Usulan Perbaikan bila (iv-b) yang dipilih:**
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
  **Ganjalan pada (b), ditemukan 2026-09-07 — baca sebelum memilihnya.** Tempat yang disebut (b) tidak menyapu semua tenant. `subscriptions:compute-revenue` hanya menjalankan `ComputeTenantMonthlyRevenue`, dan job itu menyaring `where('pricing_track', TenantConsent::TYPE_SUBSIDIZED)` ditambah cek consent yang masih hidup (`app/Jobs/ComputeTenantMonthlyRevenue.php:38`). Sementara `active_seats` sengaja berlaku untuk **kedua** jalur harga — itu bunyi komentarnya sendiri di `config/pricing-dimensions.php:71` ("hanya `count()` di tabel `users`", tanpa syarat consent). Dipasang persis seperti tertulis, (b) hanya menurunkan puncak milik tenant jalur subsidi; tenant jalur normal puncaknya tetap tidak pernah turun, dan bug entri ini cuma berpindah, bukan hilang. Bila (b) yang dipilih, penurunnya harus menyapu **semua tenant berlangganan**, bukan menumpang penyaringan job omzet apa adanya — dan begitu ia berdiri sendiri seperti itu, pertanyaan berikutnya adalah apakah ia masih pantas menumpang jadwal `compute-revenue` sama sekali.
  **Jangan** menambah reset kedua di tempat lain tanpa menghapus yang lama — dua penurun puncak yang berjalan di jadwal berbeda akan membuat harga tenant bergantung pada peristiwa mana yang kebetulan terjadi lebih dulu.
- **Diperiksa ulang 2026-09-07 — seluruh isi entri ini masih berlaku apa adanya, tidak ada satu baris pun yang perlu diubah:**
  `ActiveSeatsResolver:27` masih `max($subscription->seat_high_water, $this->activeNow($subscription))`; `Subscription::recordSeatUsage()` masih hanya menaikkan; `InvoiceSettlement:144` masih satu-satunya yang menurunkannya, dan masih hanya saat tagihan langganan dilunasi. Enam aturan yang ada di `pricing_rules` semuanya bersyarat `monthly_revenue` — **nol yang memakai `active_seats`**, jadi prioritasnya tetap Low dan dampaknya tetap nol.
  **Satu hal yang belum pernah tercatat dan memperkuat kalimat "tanpa satu pun tanda" di atas:** `active_seats` sudah bisa dipilih hari ini di panel aturan harga — `PricingRuleController:74` mengirim seluruh katalog dimensi lewat `DimensionRegistry::forPanel()`, dan validasinya di baris 324 menerima nama apa pun yang terdaftar. Jadi "hari pertama ada aturan yang memakainya" tidak menunggu rilis kode: satu klik pemilik SaaS di panel sudah cukup, dan tidak ada apa pun yang akan memberi tahu bahwa dimensinya bermakna ganda. Bila keputusannya belum diambil saat itu tiba, yang paling murah adalah menahannya di sisi panel, bukan menambal resolver-nya diam-diam.

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

### [BL-104] Gerai Acara Tetap Bisa Membuka Tagihan yang Hampir Pasti Jadi Kas Negatif
- **Ditemukan:** 2026-09-06
- **Sumber:** Dipecah dari `[BL-035]` atas keputusan pemilik — satu-satunya bagian "mode bazar" yang butuh perubahan perilaku, bukan sekadar nilai awal setelan
- **Status:** Open
- **Prioritas:** Medium
- **Area Terdampak:**
  - `app/Http/Requests/StoreTransactionRequest.php:99` — `is_open_bill` diterima tanpa syarat; `:137` melewatkan validasi pembayaran saat ia menyala
  - `app/Services/OpenBillExpiryService.php` — memindahkan tagihan lewat 24 jam ke kas negatif (`Transaction::STATUS_UNSETTLED`)
  - `app/Http/Controllers/Owner/UnsettledBillController.php` — satu-satunya jalur pemilik membereskannya
- **Deskripsi:**
  Tagihan terbuka masuk akal untuk warung menetap: pelanggan makan dulu, bayar sebelum pulang, dan kalaupun lupa ia masih bisa ditemui besok. Di gerai acara asumsi itu runtuh — pelanggan pergi saat acara bubar dan tidak pernah kembali. Artinya hampir **setiap** tagihan terbuka di sana akan lewat 24 jam, jatuh jadi kas negatif, lalu menunggu pemilik menghapusnya satu per satu lewat jalur `writeOff()`. Yang dibutuhkan bukan pengingat, melainkan kemampuan menutup pintunya: outlet yang tahu dirinya tidak melayani utang harus bisa membuat tombolnya tidak ada.
- **Usulan Perbaikan:**
  Satu setelan tenant "izinkan tagihan terbuka" (bawaan: nyala, supaya tidak ada tenant berjalan yang berubah perilakunya). Saat dimatikan: tombolnya hilang dari layar kasir **dan** `is_open_bill` ditolak server — dua-duanya, bukan salah satu, karena jalur `MobileTransactionController` dan sinkronisasi offline tidak melewati layar kasir.
- **Keputusan yang sudah diambil (2026-09-06):** tenant yang **sudah punya** tagihan terbuka lalu mematikan setelan ini tetap bisa **melunasinya**; yang dilarang hanya membuat **yang baru**. Mematikan jalur pelunasan akan mengubur uang yang benar-benar tertagih.
- **Hubungan dengan entri lain:** bukan prasyarat `[BL-035]` dan bukan turunannya — keduanya bisa dikerjakan dalam urutan mana pun. Kalau `[BL-035]` mendarat lebih dulu, setelan ini ikut jadi anggota paket "Gerai Acara & Bazar" (nilai: mati) dengan satu baris tambahan. `[BL-031]` tidak terpengaruh: `open-bills:expire` tetap berjalan lintas tenant dan tetap benar, ia cuma tidak menemukan apa-apa di outlet yang menutup pintunya.

### [BL-036] Belum Ada Studi Kasus Demo Kedua — Semua Peragaan Bertumpu pada Satu Kafe
- **Ditemukan:** 2026-07-31
- **Sumber:** Review demo pemilik — "tambahkan suatu toko usaha baru, bergerak di bidang makanan bazar kayak chicken shi lin atau ayam potong kripsi, ... dia ada antrian, dan mode bazar"
- **Status:** Open — **tidak lagi diblokir**; `[BL-035]` selesai 2026-09-07, jadi paket setelan "Gerai Acara & Bazar" sudah bisa dipakai seeder ini
- **Prioritas:** Low (nilainya untuk demo & pengujian, bukan untuk klien yang sudah jalan)
- **Area Terdampak:**
  - `database/seeders/CafeStudyCaseSeeder.php:76` — satu-satunya tenant demo, "Kopi Story", dengan katalog kopi/pastry
  - `database/seeders/DatabaseSeeder.php` — hanya memanggil `PermissionCatalogSeeder`
- **Deskripsi:**
  Setiap peragaan, tangkapan layar landing, dan pengujian manual memakai tenant kafe yang sama. Bentuk usaha yang justru jadi sasaran utama — gerai makanan cepat saji di acara, dengan antrian panjang dan katalog pendek bersaus — belum pernah dicoba di aplikasi ini. Perbedaannya bukan kosmetik: katalog pendek dengan banyak modifier saus, transaksi cepat beruntun, dan nomor antrian sebagai penanda utama akan menekan bagian sistem yang berbeda dari katalog kafe yang panjang.
- **Usulan Perbaikan:**
  Seeder studi kasus kedua dengan pola yang sama seperti `CafeStudyCaseSeeder` (tenant + owner + kasir + katalog + modifier + transaksi contoh): katalog ±8 produk gorengan/ayam, satu grup modifier saus wajib pilih satu, antrian dapur menyala, dan begitu `[BL-035]` mendarat, paket setelan "Gerai Acara & Bazar" diterapkan. Berguna sekaligus sebagai bahan tangkapan layar baru untuk `[BL-032]`.

### [BL-028] Rekonsiliasi Kas Tidak Memperhitungkan Penjualan Tunai, dan Angka Server Tidak Per-Laci
- **Ditemukan:** 2026-07-31
- **Sumber:** Catatan pemilik — "review dan mau diperbaiki konsep kas uang dalam menu kasir, bukan dari uang modal, tapi dari uang dari bertipe cash yang diterima harusnya include juga"
- **Status:** In Progress — **Tahap A selesai 2026-07-31**, **Tahap B langkah 1 selesai 2026-09-06** (keduanya di `CHANGELOG.md`); yang tersisa hanya Tahap B langkah 2, dan ia masih ditunda
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
- **Keadaan data per 2026-07-31 (dasar pemecahan tahap di bawah):** 207 sesi kas / 2 tenant / 2 kasir — **1 kasir per tenant**, sehingga **0 sesi tumpang-tindih** dan **0 transaksi yang jatuh ke laci kasir lain**. Cacat #2 masih **laten**: belum merusak angka mana pun, tapi aktif begitu kasir kedua ditambahkan. Cacat #3 juga belum menggigit (**0 transaksi** ber-`occurred_at` beda tanggal dari `created_at`). Cacat #1 sebaliknya dialami **setiap kali salah satu dari 207 sesi ditutup**.
- **Diperiksa ulang 2026-09-06 — tidak satu pun pemicu Tahap B menyala:** 248 sesi, **masih 1 kasir per tenant**, **0 sesi tumpang-tindih**, maks **1 sesi per hari per user**, **0 sesi tertutup paksa** (`closed_by_system`), **0 transaksi** ber-`occurred_at` beda tanggal, dan **0 transaksi** tenant aktif yang jatuh di luar sesi mana pun. Angka-angka inilah yang membuat langkah 2 tetap ditunda; jangan diambil dari ingatan saat memutuskannya lagi, jalankan ulang querinya.

#### Tahap A — SELESAI 2026-07-31 (tanpa migrasi)
> **Koreksi terhadap catatan awal entri ini:** `cash_drawer_id` sempat ditulis sebagai "prasyarat". Itu keliru. `transactions.user_id` sudah ada, dan penyaring `transactions.user_id = drawer.user_id` + jendela sesi sudah memisahkan laci dengan benar — termasuk saat kasir kedua masuk. Query verifikasi atas 207 sesi menghasilkan angka identik dengan data sekarang. Jadi seluruh Tahap A berjalan **tanpa migrasi, tanpa backfill, tanpa risiko data**.

1. **Satu sumber perhitungan.** Pindahkan rumus ke service tersendiri (mis. `CashDrawerReconciliation`) yang dipakai **bersama** oleh preview di UI, `close()`, dan `summary()`. Preview yang menghitung sendiri adalah cara termudah membuat dua angka berbeda untuk hal yang sama.
2. **Ganti scope `tenant_id` → `user_id` + jendela sesi.** Memperbaiki cacat #2 selagi masih laten.
3. **Ganti `created_at` → `Transaction::scopeWhereEffectiveBetween()`.** Memperbaiki cacat #3; scope-nya sudah ada dan sudah teruji dipakai di tempat lain, jadi ini penggantian pemanggilan, bukan logika baru.
4. **Tampilkan rinciannya sebelum kasir menekan tutup:** modal awal + tunai diterima − kembalian diberikan = **seharusnya di laci**, baru dibandingkan dengan uang fisik. Non-tunai ditampilkan terpisah dan **ditandai jelas "tidak masuk laci"** — supaya kasir tidak mencari uang QRIS di dalam laci.
5. **Test:** dua kasir dengan sesi tumpang-tindih (menjaga #2 tidak kembali), dan satu penjualan ber-`occurred_at` sebelum sesi dibuka (menjaga #3).

#### Tahap B langkah 1 — SELESAI 2026-09-06: kolom ada dan terisi maju, tanpa backfill
1. `transactions.cash_drawer_id` (nullable, `nullOnDelete`, indeks `[cash_drawer_id, status]`). Diisi saat kejadiannya, bukan disimpulkan saat dibaca. Kebijakan lengkap tiap jalur ada di `TransactionService::drawerReceiving()`.
2. **Tidak ada backfill, dan tidak ada perubahan jalur baca.** Baris lama tetap `null`, rekonsiliasi tetap memakai `user_id` + jendela tanggal efektif. Ini yang membuat keberatan pemilik tetap dihormati: yang ditolak adalah backfill-nya, bukan kolomnya.
3. **Akibatnya `null` berarti dua hal** sampai langkah 2 — "lahir sebelum kolomnya ada" dan "memang tidak jatuh ke laci mana pun" (pelunasan terlambat oleh pemilik, self-order lewat webhook, penjualan saat tak ada sesi terbuka). Tidak bisa dibedakan dari nilainya, hanya dari umur barisnya.
4. Penjualan offline memakai `CashDrawer::coveringAt()` — laci yang jendelanya melingkupi `occurred_at`, **boleh yang sudah tertutup**. Sesi yang sudah ditutup tetap tidak berubah angkanya (`expected_amount` dibekukan saat tutup kas); yang berubah adalah penjualannya berhenti tak-bertuan.

**Temuan yang lahir saat mengerjakannya, dan sebelumnya tidak tertulis di mana pun:** aturan `[BL-028]` menyebut uang milik laci yang **MELUNASI**, tapi kode tidak pernah melakukannya. Tagihan terbuka membawa `user_id` **pembuatnya**, dan `whereEffectiveBetween()` menyaring pakai tanggal saat tagihan itu **DIBUKA** — jadi tagihan pagi yang dilunasi malam menaruh uangnya di laci **pagi**. Laten selama satu kasir; bukan kekurangan yang menunggu masa depan, melainkan aturan yang selama ini dilanggar diam-diam. Kolom baru inilah yang pertama kali menyatakannya (`payOpenBill()` sekarang menerima `?User $paidBy`).

#### Tahap B langkah 2 — sakelar baca + backfill, MASIH ditunda
1. Pindahkan penyaring `CashDrawerReconciliation` dari `user_id` + jendela ke `cash_drawer_id`.
2. Backfill baris lama dari rentang `opened_at`–`closed_at` per user. Transaksi yang jatuh di luar sesi mana pun dibiarkan `null` — jangan dipaksa masuk laci terdekat.
- **Syarat teknisnya, dan ia tidak boleh dilewati:** salah satu dari — seluruh sesi yang masih hidup lahir sesudah migrasi langkah 1, **atau** backfill benar-benar dijalankan. Memindahkan jalur baca tanpa salah satunya akan membuat 248 sesi lama menghitung nol.
- **Kapan dikerjakan — tiga pemicu, dan yang ketiga baru:**
  1. Kasir kedua benar-benar ditambahkan.
  2. Satu user bisa membuka lebih dari satu sesi dalam sehari (di situ jendela waktu mulai ambigu dan `user_id` tidak lagi cukup).
  3. **Sesi pertama yang ditutup paksa `[BL-088]`.** Tutup-paksa 24 jam lahir sesudah keputusan penundaan ini, dan ia bentuk ketiga dari kegagalan yang sama: penjualan berlanjut saat tidak ada laci terbuka, lalu tidak jatuh ke mana pun. `closed_by_system` adalah tanda yang bisa dipantau.
- **Catatan:** `summary()` memakai pola query yang sama dan **harus ikut dipindahkan**. Kalau hanya `close()` yang dipindahkan, rekap sesi akan menampilkan angka berbeda dari angka yang barusan dipakai menutup kas — lebih membingungkan daripada keadaan sekarang.

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
  > **SELESAI 2026-08-28 — keduanya sudah mendarat.** Lihat `[ADDITION] Antrean Penjualan Offline Berhenti Bergantung pada Tab yang Terbuka (BL-016 Bagian B)` di `docs/CHANGELOG.md`. Tiga hal yang mengikat pekerjaan berikutnya: **(1)** service worker hanya MENGIRIM antrean, tidak pernah menulis ke outbox — pembukuan tetap milik `useOfflineQueue` seorang diri, dan `client_uuid` yang membuat itu aman; **(2)** kredensial yang dititipkan halaman (`cashier_id` + CSRF) tinggal di **Cache API**, bukan IndexedDB, supaya tidak ada migrasi yang bisa menahan `enqueue()` saat ada kasir menunggu — skema IndexedDB **tetap v1**; **(3)** `OfflineDurabilityTest` menjaga setiap nama yang disalin antara `sw.js` dan `resources/js`, karena `sw.js` di luar bundel dan salah ketik di sana gagal tanpa pesan apa pun. **Yang belum diuji:** perilakunya di peramban sungguhan dengan semua tab ditutup.
  >
  > **Ini TIDAK memindahkan entri ini ke Riwayat Selesai.** Bagian printer (High) belum tersentuh sama sekali, dan Bagian B pun belum tuntas — lihat D5.
- **Status kedua bagian setelah 2026-08-28:** B.1 dan B.2 sudah dikerjakan **untuk pengguna PWA di Chrome Android**. Bagian A (printer) tetap nol kode.
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

> **HASIL SPIKE 2026-08-26 — gerbang keputusan ini LULUS, rencana Capacitor boleh diteruskan.**
> Diuji tanpa APK sama sekali, dan memang tidak perlu: pertanyaan gerbang ini murni soal service worker, dan WebView Capacitor menjalankan mesin Chromium yang sama. Cara ujinya — Chrome sungguhan, service worker terpasang, POS dibuka online sekali supaya tercache, lalu **kedua server dimatikan sampai `curl` menjawab *connection refused***, baru tab baru dibuka. Bukan `navigator.onLine` yang dipalsukan, dan bukan pula throttling DevTools.
>
> **Yang terbukti jalan:** service worker menyajikan dokumen POS dari cache saat jaringan benar-benar mati — cangkang, kategori, keranjang, tombol BAYAR tampil utuh; ketiga cache terisi seperti rancangannya (`sapi-shell-v3`, `sapi-assets-v3`, `sapi-pages-v3` berisi `/cashier/pos`).
>
> **Dua cacat yang ditemukan, keduanya bisa diperbaiki dan karena itu gerbangnya lulus, bukan gagal:** `[BL-095]` — katalog tidak pernah termuat karena `products` adalah prop tertunda dan `navigator.onLine` berbohong saat yang mati cuma servernya; dan `[BL-096]` — cold start di `/` mendarat di halaman buntu. **Keduanya sudah SELESAI di hari yang sama** (lihat `[HOTFIX] Kasir Offline Berhenti Menunggu Katalog yang Tidak Akan Pernah Datang (BL-095, BL-096)`), jadi prasyarat Tahap 1 tidak lagi menghalangi.
>
> **Satu keputusan dari `[BL-096]` yang mengikat pekerjaan ini:** `server.url` di `capacitor.config.json` harus menunjuk sampai **`/cashier/pos`**, bukan berhenti di akar. Akar kini punya jalan keluar (tombol "Buka Kasir"), tapi menyalakan aplikasi langsung di POS tetap satu ketukan lebih sedikit bagi kasir — dan itu ketukan yang terjadi tiap pagi.
>
> **Yang spike ini TIDAK bisa jawab** dan tetap menunggu perangkat sungguhan: cookie sesi di dalam WebView, tombol back Android, dan perilaku service worker di WebView (bukan di Chrome). Mesin ini tidak punya JDK, Android SDK, maupun `adb`, jadi APK-nya belum pernah dibangun. Ketiganya butuh Tahap 1 dijalankan ulang di HP setelah `[BL-095]`/`[BL-096]` mendarat.

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
- **Bagian B.1 (Background Sync) tetap terbuka — dan ini tidak berubah oleh pekerjaan 2026-08-28.** Service worker jalan di WebView Android, tapi Background Sync API tidak tersedia di sana. Jadi handler `sync` yang sudah ada hari ini **menolong pengguna PWA, bukan pengguna APK**: begitu cangkang Capacitor dipasang, sinkronisasi kembali menuntut aplikasi dibuka. Menutupnya betul-betul butuh `WorkManager` di sisi native — pekerjaan tersendiri, dan bisa menyusul di atas cangkang yang sama.
- **Bagian B.2 (IndexedDB diusir) justru membaik, meski tidak sengaja.** Penyimpanan WebView tinggal di direktori data aplikasi, jadi "Hapus data penjelajahan" di Chrome **tidak lagi menyentuh antrean penjualan**. Yang tersisa hanya "Hapus data aplikasi" dan uninstall — dua tindakan yang jauh lebih disengaja. Ini alasan tambahan yang layak dihitung saat menimbang ongkosnya.
- **Dua perbaikan murah di Bagian B sudah dikerjakan lebih dulu** dan tidak menunggu rencana ini (2026-08-28): `navigator.storage.persist()` dan pendaftaran Background Sync. Keduanya berguna untuk pengguna PWA yang tidak memasang APK, dan pengguna itu tidak akan pernah hilang. **Yang perlu diingat saat Tahap 2 dikerjakan:** `NativeSppTransport` bukan satu-satunya tempat `Capacitor.isNativePlatform()` relevan — pendaftaran Background Sync di `services/backgroundSync.js` akan diam-diam gagal di WebView, dan itu bukan bug melainkan batas yang sudah tertulis di D5.

---

## Riwayat Selesai (Arsip)

Isi lengkap entri yang sudah selesai dipindahkan ke **`docs/BACKLOG-ARCHIVE.md`** (2026-07-31). Tabel di bawah adalah indeksnya — cukup untuk menjawab "sudah selesai belum?" dan "entri CHANGELOG mana yang menutupnya?" tanpa membuka arsipnya sama sekali.

| ID | Judul | Selesai | Entri penutup di `docs/CHANGELOG.md` |
|---|---|---|---|
| `BL-112` | Ability token Sanctum tidak pernah ditegakkan — token MCP "read-only" bisa membatalkan transaksi | 2026-09-15 (ability per permukaan `self-order:use`/`mobile:use`/`mcp:use`, penolakan berkode `token_ability_missing`, penjaga rute; token `*` yang beredar tetap lolos, token login mobile sengaja belum dipersempit) | `[HOTFIX] Token MCP Berhenti Bisa Membatalkan Transaksi — Ability Token Sanctum Akhirnya Ditegakkan di Setiap Rute Bertoken (BL-112)` |
| `BL-111` | Stok satu varian tidak bisa punya lebih dari satu tanggal kedaluwarsa — restock menimpa batch sebelumnya | 2026-09-15 (tabel batch + FEFO di antara barang yang masih baik; `stock` tetap kolom yang dibaca, dijaga sama dengan jumlah batch; void dan edit mengembalikan unit ke batch asalnya) | `[SCHEMA] Stok Satu Varian Akhirnya Punya Banyak Tanggal Kedaluwarsa (Batch + FEFO), dan Barang Basi Hanya Terjual dengan Alasan Tertulis (BL-111, BL-108)` |
| `BL-108` | Barang kedaluwarsa terjual tanpa satu pun peringatan — dan tiga komentar menjanjikan penjagaan yang tidak pernah ada | 2026-09-15 (pertanyaan terbuka dijawab pemilik: kasir boleh mengonfirmasi dengan alasan wajib, pemilik meninjau di Laporan Saran Jual. Gerbang di server termasuk jalur offline; ketiga komentar diperbaiki di perubahan yang sama) | `[SCHEMA] Stok Satu Varian Akhirnya Punya Banyak Tanggal Kedaluwarsa (Batch + FEFO), dan Barang Basi Hanya Terjual dengan Alasan Tertulis (BL-111, BL-108)` |
| `BL-110` | Nama pembantu uji bersifat global tapi tidak ada yang menjaga keunikannya — bentrokan berikutnya mematikan `check:boot` lagi | 2026-09-12 (penjaganya jadi perintah Artisan, bukan tes: bentrokan mematikan pelari uji pada tahap pemuatan, jadi tes penjaga ikut mati bersama yang dijaganya — dibuktikan dengan menanam bentrokannya. Dipasang sebagai langkah pertama `check:boot`) | `[ADDITION] Penjaga Bentrokan Nama Pembantu Uji Jadi Perintah Artisan — Karena Sebuah Tes Tidak Bisa Menjaganya (BL-110)` |
| `BL-105` | Penyelamat Stok tidak pernah menyebut angkanya — rantainya sudah utuh, hasilnya berhenti jadi satu baris tabel | 2026-09-09 (keempat butirnya plus pencatat harian yang tidak bisa ditambal mundur. Pembaca angka periodenya dikerjakan lebih awal daripada yang ditahan entri ini, atas permintaan pemilik — keberatan "Rp 0 untuk data yang belum terkumpul" dijawab dengan kalimat "sejak pencatatan mulai …", bukan dengan menunda. Kalimat pembeda di landing tetap ditahan `[BL-107]`) | `[ADDITION] Barang Tertekan Akhirnya Menyebut Rupiahnya…(BL-105 Butir 1 & 2)` + `[ADDITION] Rantai Barang Tertekan Dapat Namanya Sendiri di Tiga Layar…(BL-105 Butir 4)` + `[ADDITION] "Lihat di Stok" Mendarat pada Daftar yang Sama, dan Penyelamat Stok Akhirnya Punya Sisi Bulanan (BL-105 Butir Terakhir)` |
| `BL-109` | Rekap metode pembayaran menjumlahkan uang yang diserahkan, bukan yang dibayarkan — kembalian ikut terhitung jadi omzet tunai | 2026-09-09 (satu pembaca bersama `PaymentMethodRecap` untuk keempat pembaca; kembalian hanya dikurangkan dari baris tunai. Permukaan kelima — rekap di layar tutup kas kasir — sengaja ditinggalkan, lihat penutup entrinya) | `[HOTFIX] Kembalian Berhenti Terhitung Sebagai Omzet Tunai — Empat Rekap Metode Pembayaran Jadi Satu Pembaca (BL-109)` |
| `BL-097` | Service charge — ditunda sejak awal, dan belum ada yang memintanya | 2026-09-07 (pemilik mengesampingkan syarat masuknya sendiri dan meminta dikerjakan. Keempat usulan bawaan dipakai: **A/A/B/A**. Pertanyaan 1 tidak lagi usulan — DPP PBJT adalah "jumlah pembayaran yang diterima penyedia" (UU HKPD Pasal 51, PP 35/2023 Pasal 19), jadi pajak dipungut atas subtotal + biaya layanan. Seluruh mesin penguncian sengaja tidak dibangun) | `[SCHEMA] Biaya Layanan Mendapat Angkanya Sendiri — dan Pajak Dipungut di Atasnya (BL-097)` |
| `BL-035` | "Mode bazar" belum ada wujudnya di kode — perlu definisi lebih dulu | 2026-09-07 (pemilik membalik bentuknya: paket setelan, bukan mode. Tidak ada flag dan tidak ada perilaku baru — kuncinya `tenants.selling_style`, sengaja terpisah dari `business_type` yang milik penetapan harga. Aturan kerja boleh ikut paket, membalik aturan yang tertulis di config, dengan syarat yang tak ditanyakan wajib diringkas di layar. Tagihan terbuka dipecah keluar jadi `[BL-104]`) | `[ADDITION] "Mode Bazar" Mendarat Sebagai Paket Setelan, Bukan Mode — dan Kuncinya Lepas dari Penetapan Harga (BL-035)` |
| `BL-070` | Membeli seat di tengah periode gratis sampai periode habis — prorata ditunda, bukan ditolak | 2026-09-07 (bentuk **(a)** dipilih pemilik: prorata per hari, jadi komponen tersendiri di tagihan berikutnya, rinciannya dibekukan di `pricing_context.billing_breakdown`. Yang ditagih adalah hari yang belum tertutup tagihan penuh mana pun — BUKAN sisa periode berjalan — karena seat yang dibeli di jendela `invoice_lead_days` melewatkan satu tagihan utuh dan baru tertagih dua periode kemudian; celah yang entri ini sendiri tidak catat) | `[ADDITION] Seat yang Dibeli di Tengah Periode Ditagih per Hari — dan Celah Jendela Tagihan Ikut Tertutup (BL-070)` |
| `BL-093` | Pencatatan uang keluar laci belum bisa dilampiri foto struk | 2026-09-06 (kolom `proof_path` nullable, foto OPSIONAL, berkas ikut permintaan pencatatan — pola unggah-lalu-klaim yang disarankan entrinya sengaja tidak ditiru karena syaratnya tidak berlaku di formulir datar. Rute media digerbang batas tenant, menolak mutasi tidak menghapus fotonya) | `[ADDITION] Uang Keluar Laci Bisa Dilampiri Foto Struk — Opsional, dan Tanpa Langkah Kedua (BL-093)` |
| `BL-100` | Nama varian di hasil AI belum bisa ditelusuri — owner membacanya, lalu mencarinya sendiri | 2026-09-06 (ketiga tahap. Keputusan pemilik: nama yang produknya terhapus DITANDAI, bukan didiamkan — itu yang melahirkan keadaan ketiga dan, bersamanya, penjaga terhadap nama karangan model. Nama yang cocok dengan lebih dari satu varian tetap teks biasa) | `[ADDITION] Katalog Produk Akhirnya Bisa Dicari… (BL-100 Tahap 1)` + `[ADDITION] Nama Varian di Hasil AI Jadi Bisa Diklik… (BL-100 Tahap 2 & 3)` |
| `BL-099` | Saklar per-jenis saran jual hanya ada di `config/upsell.php` — owner tak punya jalan ke sana | 2026-09-06 (empat kolom boolean per-tenant bawaan `true`, dibaca sebagai lapisan di ATAS config — config tetap saklar darurat global dan tetap menang. Halaman Aturan memisahkan "Anda yang mematikan" dari "mati untuk semua toko"; hanya yang pertama bertautan) | `[ADDITION] Saklar Per-Jenis Saran Jual Pindah dari Berkas PHP ke Layar Owner — dan Config Tetap Menang (BL-099)` |
| `BL-101` | Satu barang bisa mengisi dua slot kasir sekaligus lewat dua jenis saran berbeda | 2026-09-06 (dedup kedua per `suggested_variant_id`, sesudah pengurutan skor. `Suggestion::key()` sengaja tidak disentuh; saran `attach` dilewati karena `suggested_variant_id`-nya null. Mendarat di DUA sisi — entrinya hanya menyebut server, padahal kasir memilih slotnya sendiri di client) | `[HOTFIX] Satu Barang Berhenti Memakan Dua Slot Kasir Lewat Dua Jenis Saran Berbeda (BL-101)` |
| `BL-098` | `Platform\InvoiceController::index()` merender halaman yang sudah tidak ada, dan tidak ada rute yang bisa memanggilnya | 2026-09-06 (method + tiga impor yatim dihapus; rute pengalihan, `InvoiceResource`, dan impor `Tenant` tetap. Penjaga baru menyisir 57 pemanggilan `Inertia::render()`/`inertia()` atas 54 nama halaman dan menuntut komponennya ada di disk) | `[DEPRECATE] Platform\InvoiceController::index() Dihapus — 30 Baris yang Terbaca seperti Fitur Hidup (BL-098)` |
| `BL-051` | Tenant suspended tidak punya tagihan untuk dibayar — bagaimana ia keluar dari situ? | 2026-08-31 (keputusan pemilik: **opsi (ii)** — tombol "aktifkan kembali" yang menerbitkan SATU tagihan pemulihan atas permintaan tenant. Butir (b) ditegakkan lewat periode beku, bukan lewat penjaga baru. Opsi (i) tetap berlaku untuk tenant yang tarifnya tak bisa dihitung) | `[ADDITION] Tenant yang Ditangguhkan Punya Jalan Pulang — Satu Tagihan Pemulihan, Diminta Sendiri (BL-051)` |
| `BL-094` | `eager: true` menyatukan 56 halaman Vue jadi satu bundel entry 1,1 MB untuk pengguna yang sudah masuk | 2026-08-31 (butir (a), (b), (c). Entry 1.136 KB → 264 KB; diukur, bukan diasumsikan — paling banyak 15 permintaan per halaman, bukan 56. `delay: 500` bilah kemajuan sengaja tidak diubah, hanya alasannya yang ditulis ulang) | `[DECISION] Halaman Vue Berhenti Dikirim Berombongan — Satu Bundel 1.136 KB Jadi Chunk per Halaman (BL-094)` |
| `BL-065` | Pajak/PPN — nol kata di basis kode, sampai terpungut, tercetak, terlapor, dan bisa dibuka kuncinya | 2026-08-29 (butir (a)–(f) + kedelapan keputusan. Service charge dipisah jadi `[BL-097]`) | `[SCHEMA] Pajak Masuk ke Kasir…`, `[ADDITION] Pajak Terpungut Punya Angkanya Sendiri di Laporan…`, `[DECISION] Margin Diukur terhadap Pendapatan Toko…`, `[ADDITION] Kunci Pajak Punya Jalan Bukanya…` (BL-065) |
| `BL-095` | Cold start offline membuka POS yang tidak pernah bisa menjual — katalog tertahan selamanya di kerangka | 2026-08-26 (butir (a), (c), (d); butir (b) ditolak sadar karena akan memperlihatkan harga basi ke kasir yang sedang online. Pendengar `success` + penyelidik 60 detik ikut mendarat sebagai jalan pulang yang belum pernah ada) | `[HOTFIX] Kasir Offline Berhenti Menunggu Katalog yang Tidak Akan Pernah Datang (BL-095, BL-096)` |
| `BL-096` | Cold start offline di `/` berujung halaman buntu — tidak ada jalan menuju POS | 2026-08-26 (butir (a); butir (c) tidak dikerjakan karena (a) sudah menutupnya dengan lebih murah, butir (b) menunggu `[BL-016]`. `CACHE_VERSION` naik ke v4 — tanpa itu perbaikannya tidak akan pernah sampai ke pemasangan yang sudah ada) | `[HOTFIX] Kasir Offline Berhenti Menunggu Katalog yang Tidak Akan Pernah Datang (BL-095, BL-096)` |
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
| `BL-075` | Foto bukti pembayaran non-tunai belum ada — dan harus bisa dimatikan per toko | 2026-08-19 (seluruh butir) + 2026-09-09 (sisa modal EDIT: kamera ada, dan kewajibannya hanya untuk baris yang BARU jadi non-tunai) | `[ADDITION] Pembayaran Non-Tunai Bisa Difoto, dan Sinkronisasi Offline Ternyata Tidak Perlu Ikut Berubah (BL-075)` + `[ADDITION] Layar Edit Transaksi Akhirnya Punya Kamera — dan Kewajibannya Berhenti pada Metode yang Sudah Ada (BL-075 Sisa)` |
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
