# Produk & Stok

Katalog yang rapi membuat kasir cepat dan laporan bisa dipercaya. Halaman ini menyusunnya dari nol.

## Urutannya: kategori dulu

Buat **kategori** sebelum produk — Kopi, Makanan, Snack. Kategori inilah yang jadi tab di layar POS, jadi susunannya menentukan seberapa cepat kasir menemukan barang.

Saran dari warung yang sudah jalan: buat kategori sesedikit mungkin. Enam kategori yang jelas mengalahkan dua puluh yang harus dicari-cari.

## Produk dan varian

Satu **produk** bisa punya beberapa **varian**. Kopi Susu punya varian Reguler dan Large; keduanya harga dan stoknya sendiri-sendiri.

Yang perlu diisi per varian:

| Kolom | Untuk apa |
|---|---|
| Harga jual | Yang ditagihkan ke pembeli |
| Harga modal | Dasar perhitungan laba di laporan |
| Stok | Jumlah yang tersedia sekarang |
| Stok minimum | Ambang peringatan stok menipis |
| Tanggal kedaluwarsa | Opsional, untuk barang yang punya masa simpan |

**Isi harga modal walau terasa merepotkan.** Tanpa itu, laporan hanya bisa menunjukkan omzet — dan omzet besar dengan laba tipis terlihat persis sama seperti usaha yang sehat.

## Modifier

Modifier adalah pilihan tambahan saat menjual: level gula, topping, ukuran es. Ia dikelompokkan, lalu grup itu dipasang ke produk yang relevan.

Tiap grup punya dua pengaturan yang menentukan perilakunya di kasir:

- **Wajib dipilih** — kasir tidak bisa lanjut sebelum memilih. Cocok untuk hal seperti level gula.
- **Boleh pilih banyak** — pembeli bisa mengambil lebih dari satu. Cocok untuk topping.

Modifier boleh menambah harga, boleh juga tidak.

## Stok

Stok berkurang otomatis setiap kali barang terjual, dan kembali otomatis saat transaksi di-void atau diedit. Anda tidak perlu menghitungnya manual.

Yang perlu Anda lakukan manual hanya dua:

**Restock** — saat barang datang dari pemasok. Masukkan jumlah dan, bila ada, tanggal kedaluwarsanya.

**Penyesuaian stok** — saat hitungan fisik berbeda dari catatan. Barang rusak, tumpah, atau hilang. **Alasan wajib diisi**, dan penyesuaian tidak bisa membuat stok jadi negatif.

Setiap pergerakan stok tercatat: apa, berapa, kapan, oleh siapa, dan kenapa. Saat suatu saat ada selisih yang membingungkan, riwayat inilah yang menjawabnya.

## Stok menipis

Varian yang stoknya menyentuh atau melewati batas minimum ditandai di dashboard. Ini alasan utama mengisi kolom stok minimum — tanpa itu, Anda baru tahu barang habis ketika pembeli menanyakannya.

## Hal yang sering terlewat

- **Produk nonaktif tetap ada di laporan lama.** Menonaktifkan produk menyembunyikannya dari kasir, tidak menghapus riwayat penjualannya. Itu memang yang diinginkan.
- **Mengubah harga tidak mengubah transaksi lama.** Harga tersimpan di transaksi saat penjualan terjadi, jadi laporan bulan lalu tidak ikut berubah saat Anda menaikkan harga hari ini.
- **Harga modal juga tersimpan per transaksi.** Laba bulan lalu dihitung dengan modal saat itu, bukan modal hari ini.
