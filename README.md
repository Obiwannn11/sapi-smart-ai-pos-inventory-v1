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

## Mobile API Endpoints (Phase 2)

Base prefix mengikuti konfigurasi route API project.

- `POST /mobile/cash-drawer/open`
- `POST /mobile/cash-drawer/close`
- `GET /mobile/cash-drawer/{cashDrawer}/summary`
- `GET /mobile/transactions`
- `POST /mobile/transactions/{transaction}/pay`
- `POST /mobile/transactions/{transaction}/void` (owner only)

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
- Technical docs: `docs/SAPI_Technical_Doc_v1.1.md`
- Security audit: `docs/SAPI-Security-Audit_v1.0.md`

## Lisensi

Project ini mengikuti lisensi yang digunakan pada repository ini.
