# Staf & Hak Akses

Menambah orang ke usaha Anda, dan menentukan apa yang boleh mereka buka.

## Menambah staf

Menu **Staf** — hanya pemilik yang bisa membukanya. Isi nama, email, dan kata sandi awal untuk kasir Anda.

Staf yang Anda buat **langsung bisa masuk**, tanpa perlu memverifikasi email. Anda yang menjaminnya, dan kasir warung kecil kerap tak punya alamat email sendiri. Kata sandinya Anda berikan langsung.

Bila kasir lupa kata sandinya, Anda bisa langsung menggantinya dari halaman yang sama. Ia tidak perlu menunggu email apa pun.

## Role dan modul

**Role** adalah kumpulan modul yang boleh dibuka. Buat role sekali, lalu pasang ke sebanyak apa pun staf.

Modul yang bisa dicentang:

| Modul | Membuka |
|---|---|
| Kasir (POS) | Layar penjualan |
| Sesi Kas | Buka dan tutup kas |
| Produk | Kategori, produk, varian, modifier |
| Stok / Inventori | Restock dan penyesuaian stok |
| Laporan & Riwayat | Laporan penjualan dan laba |
| Metode Pembayaran | Pengaturan metode bayar |
| AI Analysis | Menjalankan analisis AI |

Beberapa hal **tidak** bisa diberikan ke staf dengan cara apa pun: manajemen staf, manajemen role, pengaturan usaha, void transaksi, dan halaman langganan. Semuanya milik pemilik.

### "Tanpa role (POS saja)"

Saat menambah staf, pilihan role paling atas adalah *Tanpa role (POS saja)*. Kasir seperti ini bisa melayani penjualan dan mengurus sesi kas, tapi tidak melihat menu lain.

Untuk sebagian besar warung, ini pilihan yang tepat. Role baru mulai berguna saat ada staf yang juga mengurus stok atau membaca laporan.

## Menonaktifkan, bukan menghapus

Saat staf keluar, **nonaktifkan** akunnya — jangan dihapus.

Perbedaannya nyata:

- **Nonaktif** — ia tidak bisa masuk lagi, sesi yang sedang berjalan langsung terputus, dan token aplikasi kasirnya dicabut. Tapi namanya tetap melekat di transaksi lama, jadi riwayat Anda tetap utuh.
- **Hapus** — akunnya hilang, dan jejaknya di transaksi lama ikut terputus.

Menonaktifkan juga **membebaskan kuota pengguna**, jadi Anda bisa langsung menambah penggantinya tanpa menaikkan paket.

Staf yang dinonaktifkan bisa diaktifkan kembali kapan saja — selama masih ada kuota tersisa.

## Batas jumlah pengguna

Paket Anda mencakup sejumlah **pengguna aktif**. Pemilik ikut dihitung.

Saat kuota penuh, tombol tambah staf dimatikan dan jumlah terpakai ditampilkan di bagian atas halaman. Anda punya dua jalan: nonaktifkan staf yang sudah tidak bekerja, atau tambah kuota dari halaman [Langganan](/dokumentasi/panduan/langganan).

> **Catatan tentang tagihan.** Tagihan mengikuti jumlah pengguna aktif **tertinggi** selama periode berjalan, bukan jumlah saat tanggal tagih. Menonaktifkan staf sehari sebelum ditagih tidak menurunkan tagihan bulan itu — tapi menurunkan tagihan bulan berikutnya.

## Siapa melihat apa di aplikasi kasir

Aplikasi kasir mobile menerima daftar modul yang sama dan menyembunyikan menu yang tidak dimiliki staf. Perubahan role akan terbaca aplikasi tanpa staf perlu keluar-masuk.
