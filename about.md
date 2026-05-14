# SAPI — Rencana Besar (Master Roadmap)
**Dibuat:** Maret 2026  
**Tujuan dokumen ini:** Supaya kamu tidak lupa ke mana arah SAPI setelah MVP selesai.  
Baca dokumen ini setiap kali kamu merasa stuck, kehilangan arah, atau tergoda menambah fitur yang tidak ada di rencana.

---

## Prinsip yang Harus Kamu Pegang

> **Selesaikan dulu, baru sempurnakan. Sempurnakan dulu, baru tambahkan.**

Artinya:
- Jangan tambah fitur baru sebelum fitur yang ada benar-benar selesai dan berjalan baik
- Jangan polish UI sebelum logika bisnis benar
- Jangan mikir ML sebelum data transaksi nyata sudah terkumpul
- Jangan mikir mobile sebelum web terbukti dipakai orang

---

## Gambaran Besar — 4 Fase

```
FASE 1          FASE 2            FASE 3              FASE 4
─────────       ──────────        ────────────        ──────────────
Core POS    →   Smart Layer   →   Growth Layer    →   Scale Layer
(Bulan 1-4)     (Bulan 5-8)       (Bulan 9-12)        (Tahun 2+)

Kasir bisa      Badge Helper      ML nyala            Multi-outlet
transaksi       berbasis data     Prediksi stok       Mobile app
Stok akurat     nyata             QRIS dinamis        API publik
```

---

## FASE 1 — Core POS (Bulan 1–4)
**Tujuan:** SAPI bisa dipakai untuk operasional kasir harian. Tidak lebih, tidak kurang.

### Yang harus selesai:
- [ ] Auth manual — login, role owner/kasir, proteksi route
- [ ] Manajemen produk — CRUD, varian, harga modal & jual
- [ ] Modifier system — group, opsi, aturan per produk
- [ ] Transaksi POS — cart, modifier popup, hitung total
- [ ] Multi-payment — tunai, QRIS statis, transfer
- [ ] Stok otomatis berkurang + stock movement log
- [ ] Buka & tutup kas — rekap per metode pembayaran
- [ ] Laporan harian sederhana — total penjualan, produk terlaris
- [ ] Badge Helper rule-based — stok kritis, dead stock, out of stock
- [ ] Deploy ke VPS — aplikasi live dan bisa diakses

### Penanda Fase 1 selesai:
> Kamu bisa duduk di meja kasir café kenalan, operasikan SAPI dari buka sampai tutup, dan datanya akurat.

### Yang TIDAK boleh dikerjakan di fase ini:
- ML apapun
- Voice assistant
- QRIS dinamis
- Fitur akuntansi
- Mobile app

---

## FASE 2 — Smart Layer (Bulan 5–8)
**Tujuan:** SAPI mulai "pintar" berdasarkan data nyata yang sudah terkumpul di Fase 1.

### Prasyarat sebelum mulai Fase 2:
- Minimal 3 bulan data transaksi nyata (dari user real, bukan dummy)
- Core POS tidak ada bug kritis
- Minimal 2-3 user aktif yang pakai SAPI rutin

### Yang harus dikerjakan:
- [ ] **ML Pipeline ringan** — training per tenant otomatis (cron job bulanan)
  - Algoritma: Regresi Linear atau Simple Exponential Smoothing
  - Input: data `stock_movements` + `transaction_items` 90 hari terakhir
  - Output: prediksi permintaan 30 hari ke depan per SKU
- [ ] **Badge Helper upgrade** — dari rule-based ke data-driven
  - Dead stock: berdasarkan tren penjualan, bukan hanya 30 hari kosong
  - Reorder point: "Stok ini akan habis dalam X hari berdasarkan pola penjualan"
  - Potensi rugi expired: prediksi sisa stok vs sisa waktu expired
- [ ] **QRIS Dinamis** — integrasi Midtrans atau Xendit
  - Customer scan → bayar langsung → status transaksi update otomatis
  - Tidak perlu konfirmasi manual dari kasir
- [ ] **Dashboard owner yang lebih kaya**
  - Grafik tren penjualan mingguan/bulanan
  - Perbandingan performa produk
  - Estimasi keuntungan bersih (harga jual - harga modal)

### Penanda Fase 2 selesai:
> Badge Helper memberikan saran berdasarkan prediksi nyata, bukan hanya angka statis. QRIS bisa dipakai tanpa konfirmasi manual.

---

## FASE 3 — Growth Layer (Bulan 9–12)
**Tujuan:** SAPI siap ditawarkan ke lebih banyak UMKM. Bukan hanya fungsional, tapi menarik untuk dibeli.

### Yang harus dikerjakan:
- [ ] **Onboarding flow yang smooth** — user baru bisa setup sendiri tanpa bantuan
- [ ] **Import produk via Excel** — upload template → produk masuk ke sistem
- [ ] **Manajemen supplier & purchase order** — catat restock dari supplier mana, harga berapa
- [ ] **Notifikasi** — email atau WhatsApp saat stok kritis atau badge penting muncul
- [ ] **Ekspor laporan** — PDF atau Excel untuk keperluan pembukuan manual
- [ ] **Multi-kasir per tenant** — lebih dari satu kasir bisa login dan transaksi bersamaan
- [ ] **Halaman landing + pricing** — SAPI punya wajah publik, bisa daftar sendiri
- [ ] **Sistem berlangganan sederhana** — free trial + paket berbayar

### Penanda Fase 3 selesai:
> Orang yang tidak kenal kamu bisa menemukan SAPI, daftar sendiri, setup sendiri, dan mulai pakai dalam satu hari.

---

## FASE 4 — Scale Layer (Tahun 2+)
**Tujuan:** SAPI tumbuh sebagai produk, bukan sekadar portofolio.

### Yang bisa dikerjakan (berdasarkan kebutuhan nyata waktu itu):
- [ ] **Multi-outlet** — satu akun owner, beberapa cabang dengan stok terpisah
- [ ] **Mobile app (React Native)** — kasir bisa transaksi dari tablet atau HP
- [ ] **API publik** — integrasi dengan platform lain (marketplace, delivery)
- [ ] **Voice assistant** — hanya jika ada user yang benar-benar minta dan butuh
- [ ] **Akuntansi dasar** — laporan laba rugi, arus kas (bukan full ERP)
- [ ] **Fitur member & loyalty** — poin, diskon khusus pelanggan tetap

---

## Diferensiasi SAPI — Jangan Sampai Lupa

Ini yang membedakan SAPI dari Booble, Majoo, dan Olsera. Kalau kamu kehilangan ini, SAPI tidak punya alasan untuk dipilih.

| Pembeda | Penjelasan |
|---|---|
| **Prediksi personal per tenant** | ML dilatih dengan data bisnis masing-masing, bukan data industri generik |
| **Badge Helper actionable** | Bukan grafik — tapi kartu saran yang langsung terhubung ke aksi nyata |
| **Tidak butuh konsultan** | SAPI jadi "asisten finansial otomatis" untuk UMKM yang tidak mampu sewa konsultan |
| **Ringan dan terjangkau** | Tidak butuh GPU, tidak butuh ERP mahal, bisa jalan di VPS standar |

---

## Peringatan untuk Dirimu Sendiri

**Ketika kamu tergoda menambah fitur di luar fase yang sedang berjalan — baca ini:**

Fitur baru yang kamu tambahkan sebelum waktunya artinya:
- Fase sekarang tidak selesai tepat waktu
- Utang teknis menumpuk
- Kamu akan refactor lagi nanti ketika fitur itu seharusnya dikerjakan
- Portofolio tidak pernah selesai, tidak pernah bisa di-demo

**Ketika kamu merasa MVP sudah cukup dan malas lanjut — baca ini:**

MVP tanpa Smart Layer artinya SAPI sama saja dengan Booble versi lebih kecil. Tidak ada alasan user pilih SAPI. Diferensiasi kamu ada di Fase 2 — bukan Fase 1. Fase 1 hanya fondasi.

---

## Checklist Sebelum Lanjut ke Fase Berikutnya

Sebelum naik fase, jawab semua pertanyaan ini dengan jujur:

**Fase 1 → Fase 2:**
- Apakah POS bisa dipakai dari buka sampai tutup tanpa error?
- Apakah stok akurat setelah transaksi?
- Apakah minimal 2 orang nyata sudah pakai SAPI lebih dari 2 minggu?
- Apakah data transaksi sudah terkumpul minimal 3 bulan?

**Fase 2 → Fase 3:**
- Apakah Badge Helper ML memberikan saran yang relevan dan akurat?
- Apakah QRIS dinamis sudah berjalan tanpa konfirmasi manual?
- Apakah tidak ada bug kritis yang tertunda?

**Fase 3 → Fase 4:**
- Apakah ada user yang membayar untuk menggunakan SAPI?
- Apakah ada permintaan nyata untuk fitur Fase 4 dari user aktif?

---

*Dokumen ini bukan kontrak. Tapi setiap kali kamu menyimpang dari rencana ini tanpa alasan yang jelas dan terukur — kamu sedang berbohong pada dirimu sendiri.*
