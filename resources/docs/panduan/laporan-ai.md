# Laporan & AI Analysis

Angka yang sudah Anda kumpulkan, dan cara membacanya tanpa harus jadi akuntan.

## Laporan penjualan

Menu **Laporan** menampilkan penjualan per rentang tanggal: omzet, jumlah transaksi, rata-rata per transaksi, dan produk terlaris.

Dua hal yang menentukan angkanya benar atau tidak:

- **Hanya transaksi selesai yang dihitung.** Open bill yang belum dibayar dan transaksi yang di-void tidak masuk.
- **Tanggal yang dipakai adalah waktu kejadian**, bukan waktu tersimpan di server. Penjualan offline tanggal 31 yang baru tersinkron tanggal 1 tetap masuk laporan bulan yang benar.

## Laba

Laba dihitung dari selisih harga jual dan harga modal yang tersimpan di tiap transaksi.

Kalau angka labanya terlihat aneh, hampir selalu penyebabnya satu: ada produk yang harga modalnya belum diisi. Periksa katalog Anda sebelum menyimpulkan usaha sedang rugi.

## AI Analysis

Ini bagian yang membedakan SAPI dari aplikasi kasir biasa. AI membaca data penjualan Anda dan menuliskan temuannya dalam bahasa manusia — bukan grafik lagi yang harus Anda tafsirkan sendiri.

Ada empat jenis analisis:

| Jenis | Menjawab pertanyaan |
|---|---|
| **Insight Umum** | Apa yang terjadi pada usaha saya periode ini? |
| **Saran Diskon** | Barang mana yang layak didiskon, dan kenapa? |
| **Proyeksi Laba** | Ke mana arah laba saya kalau pola ini berlanjut? |
| **Pertanyaan Sendiri** | Apa pun yang ingin Anda tanyakan tentang data Anda |

Pilih jenisnya, tentukan rentang tanggal, lalu jalankan. Hasilnya tersimpan dan bisa dibuka lagi kapan saja — Anda tidak perlu menjalankan ulang untuk membaca yang kemarin.

### Rentang tanggal yang masuk akal

Rentang yang terlalu pendek membuat AI menyimpulkan dari kebisingan; satu hari ramai bisa terbaca sebagai tren. Rentang minimal **dua minggu** memberi hasil yang jauh lebih berguna, dan sebulan lebih baik lagi.

### Yang perlu Anda ingat

AI membaca ringkasan penjualan Anda — produk, jumlah, nilai, waktu. Ia tidak tahu bahwa minggu lalu hujan tiap sore, atau bahwa ada acara di sebelah. **Konteks yang tidak ada di data tidak akan ada di kesimpulannya.**

Perlakukan hasilnya sebagai pendapat karyawan yang teliti membaca angka tapi tidak pernah berdiri di warung Anda. Berguna, kadang tajam, tapi tetap perlu Anda timbang.

### Kuota

Pemakaian AI dihitung dan bisa dilihat di halaman Pengaturan. Bila usaha Anda banyak memakainya, Anda bisa memasang kunci API sendiri di Pengaturan — pemakaian kemudian ditagihkan langsung oleh penyedia AI, bukan lewat kami.

## Menyambungkan AI Anda sendiri

Bagi yang ingin bertanya lewat asisten AI favoritnya — misalnya lewat Claude — SAPI menyediakan server MCP yang memberi asisten itu akses baca ke ringkasan usaha Anda. Caranya ada di [dokumentasi developer](/dokumentasi/developer/ai-mcp).
