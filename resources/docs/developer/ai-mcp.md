# Server MCP untuk AI

SAPI menyediakan server **Model Context Protocol** yang memberi asisten AI akses **baca** ke ringkasan usaha Anda. Setelah tersambung, Anda bisa bertanya dengan bahasa biasa — *"produk apa yang paling menguntungkan bulan lalu?"* — dan asisten mengambil angkanya sendiri.

## Endpoint

```
POST https://domain-anda.com/mcp/business
```

Transport HTTP. Autentikasi Bearer token.

## Membuat token

1. Masuk sebagai **pemilik usaha**.
2. Buka **Pengaturan**.
3. Tekan **Buat Token MCP**.
4. Salin tokennya saat itu juga — ia hanya ditampilkan sekali.

Token bisa dicabut dari halaman yang sama, dan pencabutannya berlaku seketika.

> **Hanya pemilik.** Endpoint MCP digerbang `role:owner`. Token milik staf akan ditolak, berapa pun modul yang dimilikinya — data ringkasan usaha bukan sesuatu yang diberikan lewat pencentangan modul.

## Menyambungkan dari Claude Desktop

Tambahkan ke berkas konfigurasi MCP Anda:

```json
{
  "mcpServers": {
    "sapi": {
      "command": "npx",
      "args": [
        "-y", "mcp-remote",
        "https://domain-anda.com/mcp/business",
        "--header", "Authorization: Bearer TOKEN_ANDA"
      ]
    }
  }
}
```

Ganti `domain-anda.com` dan `TOKEN_ANDA`, lalu mulai ulang aplikasinya.

## Tools yang tersedia

| Tool | Mengembalikan |
|---|---|
| `get_sales_summary` | Ringkasan penjualan per rentang tanggal — omzet, jumlah transaksi, rata-rata |
| `get_profit` | Laba per rentang tanggal, dihitung dari harga modal yang tersimpan di transaksi |
| `get_menu` | Katalog produk berikut varian dan harganya |
| `business_data` | Konteks usaha gabungan untuk pertanyaan yang lebih luas |

Semuanya **hanya membaca**. Tidak ada tool yang membuat transaksi, mengubah harga, atau menyentuh stok. Asisten AI tidak bisa mengubah apa pun di usaha Anda lewat jalur ini.

## Yang perlu diketahui sebelum memakai

**Cakupannya satu usaha.** Token melekat pada satu tenant; tidak ada parameter untuk berpindah.

**Angkanya sama dengan yang di laporan.** Tool ini membaca sumber yang sama dengan menu Laporan — hanya transaksi berstatus selesai, dengan tanggal mengikuti waktu kejadian. Kalau angka di asisten berbeda dari yang di layar, hampir pasti rentang tanggalnya yang berbeda.

**Laba bergantung pada harga modal.** Varian yang harga modalnya kosong akan membuat `get_profit` melaporkan laba yang terlalu tinggi. Ini bukan kesalahan tool — ia menghitung dari yang ada.

**Ada batas laju.** Endpoint MCP di-throttle. Asisten yang menembakkan puluhan permintaan beruntun akan menerima `429`; beri jeda dan coba lagi.

## Kalau Anda memakai kunci AI sendiri

Server MCP dan fitur AI Analysis di dalam aplikasi adalah dua hal terpisah:

- **AI Analysis** berjalan di server kami, memakai kuota atau kunci API yang Anda pasang di Pengaturan.
- **Server MCP** tidak memakai kuota AI apa pun. Ia hanya menyajikan data; yang memakai model AI adalah aplikasi di sisi Anda.

Jadi menyambungkan MCP tidak menambah pemakaian kuota AI Anda sama sekali.
