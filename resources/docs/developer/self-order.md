# API Pesan Mandiri

Endpoint untuk kanal pemesanan di luar aplikasi kasir — bot WhatsApp, alur n8n, halaman pesan sendiri, atau apa pun yang perlu memasukkan pesanan ke SAPI.

## Autentikasi

Bearer token, dibuat pemilik usaha dari halaman Pengaturan. Sama seperti permukaan lain, token melekat pada satu usaha.

```http
Authorization: Bearer {token}
Accept: application/json
```

## Membaca katalog

```http
GET /api/v1/products
```

Mengembalikan produk beserta varian, harga, dan modifier yang tersedia. Inilah yang dipakai bot untuk menyusun pilihan bagi pembeli.

Produk yang dinonaktifkan tidak ikut terkirim, jadi Anda tidak perlu menyaringnya sendiri.

## Membuat pesanan

```http
POST /api/v1/orders
```

Dibatasi **60 permintaan per menit**.

Pesanan yang masuk lewat endpoint ini ditandai sebagai kanal pesan mandiri, sehingga di aplikasi kasir bisa dibedakan dari penjualan di konter.

Kirim item beserta varian, jumlah, dan modifier yang dipilih. Harga tidak dikirim dari sisi Anda — server memakai harga yang berlaku di katalog saat itu. Ini disengaja: kanal luar tidak boleh menentukan harga.

## Memperbarui status penyiapan

```http
PATCH /api/v1/orders/{id}/fulfillment
```

Untuk memberi tahu SAPI bahwa pesanan sudah disiapkan, diantar, atau selesai. Berguna bila alur penyiapan Anda berjalan di sistem lain — misalnya papan antrean dapur.

## Webhook pembayaran

```http
POST /api/xendit/webhook
```

Menerima pemberitahuan pembayaran dari Xendit. **Tidak diversikan** karena URL-nya dikonfigurasi di dashboard Xendit, di luar kendali kita.

Diverifikasi lewat header `x-callback-token`, bukan Bearer token. Endpoint ini tidak dimaksudkan untuk dipanggil aplikasi Anda.

## Pola yang kami sarankan

**Ambil katalog secukupnya, jangan tiap pesan.** Katalog jarang berubah; menyimpannya beberapa menit di sisi Anda jauh lebih ramah daripada memanggil ulang tiap kali pembeli mengetik.

**Tangani `429` dengan jeda, bukan pengulangan segera.** Batas lajunya per menit; mencoba ulang seketika hanya memperpanjang penolakan.

**Jangan menyimpan harga di sisi Anda sebagai kebenaran.** Harga bisa diubah pemilik kapan saja. Tampilkan dari katalog yang baru Anda ambil, dan biarkan server yang menghitung total.

**Pesanan yang belum dibayar tidak masuk laporan.** Selama statusnya masih menunggu, ia tidak dihitung sebagai penjualan di mana pun — jadi jangan kaget bila angka di dashboard belum bergerak.
