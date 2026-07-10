# SAPI Smart AI POS Inventory

SAPI adalah aplikasi Point of Sale (POS) multi-tenant berbasis Laravel untuk kebutuhan inventory, transaksi kasir, dan operasional outlet.

## Stack Utama

- Backend: Laravel 12 (PHP)
- Frontend: Inertia.js + Vite
- Auth API: Laravel Sanctum
- Database: MySQL/MariaDB (sesuai konfigurasi `.env`)

## Fitur Inti

- Multi-tenant isolation (`tenant_id`) pada data bisnis.
- Master data produk, kategori, modifier, dan payment method.
- POS transaction flow (pending/completed/voided).
- Cash drawer session (buka kas, tutup kas, rekap sesi).
- Mobile API untuk operasional kasir.
- MCP Server data bisnis (read-only) untuk AI client milik owner (mis. Claude Desktop).

## Update Terbaru (2026-07-11)

Penambahan MCP Server (data bridge read-only):

- Endpoint `POST /mcp/business` mengekspos data agregat tenant (penjualan, profit, menu) ke AI client milik owner (mis. Claude Desktop) — app hanya menyediakan data, tidak memakai kuota AI aplikasi.
- Akses via bearer token Sanctum yang dibuat/dicabut owner di Pengaturan; owner-only, tenant-scoped, tanpa data pelanggan, dan dibatasi rate limit anti-abuse.
- Tata cara pemakaian ada di bagian [MCP Server](#mcp-server-akses-data-bisnis-untuk-ai-client) di bawah.

Lihat detail teknis di `docs/phases-2/PHASE-AI-4_MCP-Server.md` dan `docs/CHANGELOG.md`.

## Update Terbaru (2026-05-29)

Perubahan besar pada API consumer dan dokumentasinya:

- Semua endpoint consumer dipindahkan ke prefix `/api/v1`.
- Controller API dipindahkan ke namespace `App\Http\Controllers\Api\V1` agar versioning route dan implementasi konsisten.
- Xendit webhook tetap non-versioned di `POST /api/xendit/webhook` agar callback eksternal tidak perlu diubah tiap versi API.
- Ditambahkan halaman referensi API publik di `/api-docs` untuk dokumentasi endpoint mobile POS.

Lihat detail perubahan dan catatan migrasi di `docs/CHANGELOG.md`.

## Update Terbaru (2026-05-25)

Perubahan besar pada Mobile API Phase 2:

- Operasi cash drawer mobile:
  - Buka kas (`open`)
  - Tutup kas (`close`)
  - Ringkasan sesi kas (`summary`)
- Operasi transaksi mobile:
  - List transaksi dengan filter (`index`)
  - Bayar open bill (`pay`)
  - Void transaksi (`void`, owner only)
- Registrasi route API baru dengan proteksi role:
  - `cashier,owner` untuk alur kasir harian
  - `owner` khusus untuk void transaksi

Lihat detail teknis dan alasan perubahan di `docs/CHANGELOG.md`.

## Update Terbaru (2026-05-28)

Penyegaran besar pada UI system dan alur kasir/owner:

- Theme system semantik baru berbasis design token (`primary`, `success`, `warning`, `destructive`) diterapkan lintas komponen dan halaman.
- Komponen reusable baru:
   - `CashierTopbar` untuk navigasi konsisten di halaman kasir
   - `DatePicker` custom untuk filter laporan owner
   - `useFlash` composable untuk notifikasi sukses/error/peringatan tanpa alert browser
- Peningkatan UX kasir:
   - Flow tutup kas 2 langkah dengan ringkasan sebelum konfirmasi
   - Validasi stok menampilkan flash message (bukan alert blocking)
   - Konsistensi copy untuk open bill/tagihan
- Perbaikan role flow backend:
   - Owner tidak lagi diwajibkan membuka sesi kas sebelum masuk POS
   - Sesi cash drawer dibatasi untuk role kasir
- Halaman login di-redesign agar konsisten dengan visual system baru.

Lihat detail lengkap file terdampak di `docs/CHANGELOG.md`.

## Update Tambahan (2026-05-28)

Perubahan incremental non-landing page:

- Owner Modifiers:
   - Toggle cepat pengaturan group modifier (`wajib dipilih` dan `boleh pilih banyak`) langsung dari halaman list.
   - Detail group menampilkan item modifier dan daftar produk yang menggunakan group tersebut.
- Cashier Topbar:
   - Menu akun berbasis dropdown (nama, email, logout) untuk navigasi yang lebih ringkas.
- DatePicker:
   - Popup calendar dipindah ke body (`Teleport`) dengan posisi adaptif agar tidak terpotong area scroll/layout.
- UI Polish dan kompatibilitas request:
   - Scrollbar custom untuk sidebar dan area konten owner.
   - Penambahan meta CSRF token pada layout utama app.

Rincian file dan konteks perubahan tersedia di `docs/CHANGELOG.md`.

## API Endpoints (v1)

Base prefix endpoint consumer sekarang adalah `/api/v1`.

Endpoint utama:

- `POST /api/v1/mobile/login`
- `POST /api/v1/mobile/logout`
- `GET /api/v1/mobile/tenant/profile`
- `GET /api/v1/mobile/products`
- `GET /api/v1/mobile/cash-drawer/status`
- `POST /api/v1/mobile/cash-drawer/open`
- `POST /api/v1/mobile/cash-drawer/close`
- `GET /api/v1/mobile/cash-drawer/{cashDrawer}/summary`
- `POST /api/v1/mobile/transactions`
- `GET /api/v1/mobile/transactions`
- `POST /api/v1/mobile/transactions/{transaction}/pay`
- `GET /api/v1/mobile/transactions/{transaction}/receipt`
- `POST /api/v1/mobile/transactions/{transaction}/void` (owner only)
- `GET /api/v1/products`
- `POST /api/v1/orders`
- `PATCH /api/v1/orders/{transaction}/fulfillment`

Endpoint non-versioned yang tetap dipertahankan:

- `POST /api/xendit/webhook`

Referensi yang lebih lengkap tersedia di halaman `/api-docs`.

## MCP Server (Akses Data Bisnis untuk AI Client)

MCP Server memberi **AI client milik owner** (mis. Claude Desktop) akses **read-only** ke data bisnis tenant: ringkasan penjualan, profit, dan menu. Berbeda dengan fitur AI Analysis internal — di sini LLM yang memanggil adalah client milik owner, sehingga **tidak memakai kuota AI aplikasi**. App hanya berperan sebagai sumber data.

Karakteristik:

- **Endpoint:** `POST {APP_URL}/mcp/business`
- **Auth:** bearer token Sanctum (`Authorization: Bearer <token>`), **hanya untuk owner**.
- **Isolasi:** data otomatis ter-scope ke tenant pemilik token; hanya data agregat (tanpa data pelanggan).
- **Rate limit:** 60 request/menit per user (anti-abuse).

### Tool yang tersedia

| Tool | Fungsi | Argumen |
|---|---|---|
| `get-sales-summary` | Revenue, jumlah transaksi, rata-rata nota, produk terlaris, tren harian | `from`, `to` (opsional, `YYYY-MM-DD`) |
| `get-profit` | Profit, margin, proyeksi periode berikutnya, margin per item | `from`, `to` (opsional, `YYYY-MM-DD`) |
| `get-menu` | Daftar produk aktif + varian (harga, sisa stok) | — |

> Tanpa `from`/`to`, rentang default adalah 30 hari terakhir.

### Tata Cara Pemakaian

1. Login sebagai **owner**, buka **Pengaturan**.
2. Di bagian **Akses MCP (AI Client)**, klik **Generate Token**, lalu **salin token** (hanya ditampilkan sekali).
3. Konfigurasikan AI client dengan URL endpoint + token. Contoh untuk Claude Desktop (`claude_desktop_config.json`), menggunakan jembatan `mcp-remote`:

   ```json
   {
     "mcpServers": {
       "sapi-business": {
         "command": "npx",
         "args": [
           "mcp-remote",
           "https://APP_URL/mcp/business",
           "--header",
           "Authorization: Bearer TOKEN_ANDA"
         ]
       }
     }
   }
   ```

4. Mulai percakapan di AI client, mis. "Berapa profit bulan ini?" atau "Produk mana yang paling laris minggu ini?" — client akan memanggil tool yang sesuai.
5. **Rotasi/cabut token** kapan saja dari Pengaturan (klik **Buat Ulang Token** atau **Cabut**). Token yang dicabut langsung tidak berlaku.

### Debug (opsional)

Uji server dengan MCP Inspector bawaan:

```
php artisan mcp:inspector mcp/business
```

Sertakan header `Authorization: Bearer <token owner>` saat menyambung.

## Setup Singkat

1. Install dependency PHP:
   - `composer install`
2. Install dependency frontend:
   - `npm install`
3. Copy env:
   - `cp .env.example .env`
4. Generate app key:
   - `php artisan key:generate`
5. Migrasi database:
   - `php artisan migrate`
6. Jalankan server local:
   - `php artisan serve`
7. Jalankan Vite:
   - `npm run dev`

## Dokumentasi

- Changelog: `docs/CHANGELOG.md`
- Public API reference: `/api-docs`
- MCP Server: `docs/phases-2/PHASE-AI-4_MCP-Server.md`
- Technical docs: `docs/SAPI_Technical_Doc_v1.1.md`
- Security audit: `docs/SAPI-Security-Audit_v1.0.md`

## Lisensi

Project ini mengikuti lisensi yang digunakan pada repository ini.
