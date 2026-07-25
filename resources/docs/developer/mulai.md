# Pengantar Integrasi

SAPI menyediakan tiga permukaan untuk sistem luar. Halaman ini menjelaskan yang berlaku untuk ketiganya.

| Permukaan | Untuk apa | Dokumentasi |
|---|---|---|
| **REST API Mobile POS** | Aplikasi kasir — transaksi, kas, produk | [Referensi API](/api-docs) |
| **Server MCP** | Asisten AI membaca ringkasan usaha | [Server MCP](/dokumentasi/developer/ai-mcp) |
| **API Pesan Mandiri** | Bot, n8n, kanal pesanan luar | [API Pesan Mandiri](/dokumentasi/developer/self-order) |

## Base URL & versi

```
https://domain-anda.com/api/v1
```

Semua endkonsumen berada di bawah `/api/v1/`. Versi ada di URL, bukan di header — perubahan yang memutus kompatibilitas akan lahir sebagai `/api/v2/`, dan `/api/v1/` tetap hidup.

Satu pengecualian: webhook Xendit tidak diversikan, karena URL-nya dikonfigurasi di dashboard pihak ketiga yang tidak ikut kita kendalikan.

## Autentikasi

Bearer token lewat Laravel Sanctum.

```http
Authorization: Bearer {token}
Accept: application/json
```

Cara memperoleh token berbeda per permukaan:

- **Mobile POS** — `POST /api/v1/mobile/login` dengan email dan kata sandi.
- **MCP & pesan mandiri** — dibuat pemilik usaha dari halaman Pengaturan aplikasi.

Token tidak kedaluwarsa dengan sendirinya, tapi bisa dicabut kapan saja. Perlakukan seperti kata sandi.

## Isolasi tenant

**Setiap token melekat pada satu usaha.** Tidak ada parameter `tenant_id` di endpoint mana pun, dan menambahkannya tidak akan berpengaruh — cakupan datanya ditentukan token, bukan permintaan.

Ini berarti Anda tidak perlu menyaring apa pun sendiri, dan tidak bisa keliru membaca data usaha lain.

## Bentuk jawaban

Sukses:

```json
{ "data": { } }
```

Gagal:

```json
{ "message": "Penjelasan dalam bahasa Indonesia." }
```

Kesalahan validasi mengikuti bentuk baku Laravel:

```json
{
  "message": "Kolom Email wajib diisi.",
  "errors": { "email": ["Kolom Email wajib diisi."] }
}
```

Pesan-pesannya berbahasa Indonesia — aplikasi Anda bisa menampilkannya apa adanya kepada pengguna.

## Kode status

| Kode | Artinya | Yang perlu aplikasi lakukan |
|---|---|---|
| `200` | Berhasil | — |
| `201` | Data baru dibuat | — |
| `401` | Token tidak ada atau tidak berlaku | Arahkan pengguna masuk ulang |
| `403` | Token sah, tapi tidak berhak | Tampilkan `message` apa adanya |
| `422` | Validasi gagal | Tampilkan `errors` per kolom |
| `429` | Terlalu banyak permintaan | Tunggu, lalu coba lagi |

### Tiga penyebab 403 yang perlu ditangani berbeda

Aplikasi kasir yang baik membedakan ketiganya, karena tindakan penggunanya berbeda:

1. **Akun dinonaktifkan** — pemiliknya menonaktifkan staf ini. Tokennya sekalian dicabut; arahkan ke halaman masuk.
2. **Email belum diverifikasi** — arahkan pengguna membuka tautan di emailnya.
3. **Langganan lewat masa tenggang** — permintaan baca tetap berhasil, permintaan tulis ditolak. Tampilkan pesan bahwa langganan perlu diselesaikan; jangan paksa keluar.

Ketiganya mengembalikan `message` yang bisa langsung ditampilkan.

## Hak akses modul

Payload login menyertakan `permissions`:

```json
{
  "user": {
    "role": "cashier",
    "permissions": ["pos", "stock"]
  }
}
```

Pemilik usaha menerima `["*"]` — ia melewati seluruh gerbang, jadi daftar lengkap tidak disusun untuknya.

Daftar ini dimaksudkan untuk **menyembunyikan menu**, bukan sebagai satu-satunya penjaga. Endpoint yang memang bermodul menegakkan izinnya sendiri di server.

Izin bisa berubah kapan saja sementara token bertahan berminggu-minggu, jadi ambil ulang dari `GET /api/v1/mobile/tenant/profile` — jawabannya menyertakan `permissions` yang berlaku sekarang.

## Batas laju

Endpoint login dibatasi per kombinasi email dan IP, bukan per IP saja. Beberapa perangkat kasir di balik satu IP publik tidak akan saling menghabiskan jatah.

Endpoint pesanan mandiri dibatasi 60 permintaan per menit.
