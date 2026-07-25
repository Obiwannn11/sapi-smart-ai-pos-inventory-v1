# Kasir & Transaksi

Alur sehari-hari di depan pembeli, dari membuka kas pagi hari sampai menutupnya malam.

## Buka sesi kas dulu

**POS tidak bisa dipakai sebelum sesi kas dibuka.** Bila Anda membuka menu Kasir tanpa sesi terbuka, aplikasi mengarahkan Anda ke halaman Sesi Kas.

Masukkan **modal awal** — uang tunai yang ada di laci saat Anda mulai. Angka ini yang nanti dibandingkan dengan uang di laci saat tutup, dan selisihnya itulah yang memberi tahu ada yang perlu diperiksa.

Satu sesi kas melekat pada satu kasir. Bila kasir berganti shift, sesi lama ditutup dan yang baru membuka sesinya sendiri.

> **Pemilik tidak perlu membuka sesi kas.** Bila pemilik membuka menu Sesi Kas, ia diarahkan ke POS — sesi kas memang ditujukan untuk kasir.

## Mencatat penjualan

Di layar POS:

1. Pilih produk dari daftar. Bila produk punya varian (ukuran, level pedas), pilih variannya.
2. Tambahkan **modifier** bila ada — topping, tanpa gula, dan sejenisnya. Modifier bisa menambah harga.
3. Ubah jumlah bila pembeli mengambil lebih dari satu.
4. Tambahkan catatan per item bila perlu, misalnya "tanpa es".
5. Tekan bayar, pilih metode pembayaran, masukkan nominal yang diterima.

Kembalian dihitung otomatis. Struk bisa dicetak atau dibagikan.

### Pembayaran campuran

Satu transaksi boleh dibayar dengan lebih dari satu metode — misalnya sebagian tunai, sisanya QRIS. Tambahkan baris pembayaran sebanyak yang diperlukan; sistem menutup transaksi begitu totalnya terpenuhi.

## Open bill

Untuk pembeli yang makan di tempat dan membayar belakangan, simpan transaksi sebagai **open bill**. Transaksi tersimpan berstatus menunggu, dan bisa ditambah pesanannya sebelum ditutup.

Bayar open bill dari daftar transaksi, pilih pesanan yang bersangkutan, lalu proses pembayarannya seperti biasa.

Open bill yang belum dibayar **tidak dihitung sebagai penjualan** di laporan mana pun.

## Mengoreksi transaksi

Salah input terjadi. Ada dua jalan, dan keduanya meninggalkan jejak:

**Edit transaksi.** Mengubah isi transaksi yang sudah tersimpan. Stok ikut dihitung ulang otomatis — barang yang dikurangi kembali ke stok, yang ditambahkan dipotong. Kasir hanya bisa mengedit selama sesi kasnya masih terbuka; pemilik bisa kapan saja.

**Void.** Membatalkan seluruh transaksi. **Hanya pemilik yang bisa.** Transaksi yang di-void tidak dihitung di laporan mana pun, dan stoknya dikembalikan.

Keduanya tercatat berikut alasannya. Ini bukan untuk mencurigai kasir — ini yang membuat selisih kas bisa dijelaskan tanpa saling menuduh.

## Menutup kas

Di akhir shift, buka **Sesi Kas** lalu tekan tutup. Masukkan uang tunai yang benar-benar ada di laci.

Sistem menampilkan:

- Modal awal
- Penjualan tunai selama sesi
- Perkiraan kas seharusnya
- Kas yang Anda hitung
- **Selisih**

Selisih kecil itu biasa. Selisih yang besar atau berulang adalah hal yang perlu ditanyakan — dan sekarang Anda punya angkanya, bukan firasat.

## Kalau internet mati

SAPI tetap bisa mencatat transaksi tanpa internet. Transaksi disimpan di perangkat dan dikirim otomatis begitu koneksi kembali.

Yang perlu Anda tahu:

- Transaksi offline dihitung berdasarkan **waktu kejadiannya**, bukan waktu terkirim. Penjualan tanggal 31 yang baru tersinkron tanggal 1 tetap masuk laporan bulan yang benar.
- Bila ada yang janggal saat sinkronisasi — misalnya transaksi ganda — pemilik akan melihatnya di menu **Tinjau Transaksi Offline** untuk diperiksa satu per satu.
- Jangan hapus data aplikasi saat masih ada transaksi yang belum terkirim.
