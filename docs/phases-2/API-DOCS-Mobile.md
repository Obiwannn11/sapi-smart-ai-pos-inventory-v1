# SAPI Mobile API — Dokumentasi Endpoint

**Versi:** 1.0  
**Tanggal:** Mei 2026  
**Base URL:** `http://{domain}/api/mobile`  
**Format:** Semua request dan response menggunakan `Content-Type: application/json`

---

## Daftar Endpoint

| # | Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|---|
| 1 | POST | `/login` | Tidak | Login kasir, dapatkan Bearer token |
| 2 | POST | `/logout` | Bearer | Revoke token aktif |
| 3 | GET | `/tenant/profile` | Bearer | Info usaha untuk header struk |
| 4 | GET | `/products` | Bearer | Daftar produk aktif + varian + kategori |
| 5 | GET | `/cash-drawer/status` | Bearer | Status shift kas kasir |
| 6 | POST | `/transactions` | Bearer | Checkout / buat transaksi baru |
| 7 | GET | `/transactions/{id}/receipt` | Bearer | Data struk terformat untuk print |

---

## Authentication

Semua endpoint kecuali `/login` membutuhkan header:

```
Authorization: Bearer {token}
```

Token didapatkan dari response `POST /login`. Token bersifat permanen sampai di-revoke via `POST /logout`.

**Alur umum:**
1. Login → simpan token
2. Set header `Authorization: Bearer {token}` di semua request
3. Gunakan sampai sesi berakhir, lalu logout

---

## Format Error Standar

| HTTP Code | Kondisi | Response Body |
|---|---|---|
| 401 | Token tidak ada / tidak valid / sudah di-revoke | `{ "message": "Unauthenticated." }` |
| 403 | User tidak punya tenant / akses ke resource tenant lain | `{ "message": "Forbidden." }` |
| 422 | Validasi gagal | `{ "message": "...", "errors": { "field": ["pesan"] } }` |
| 422 | Error domain (stok habis, dll) | `{ "message": "Stok X tidak cukup. Tersedia: 0, diminta: 2" }` |
| 429 | Rate limit login (> 5x/menit) | `{ "message": "Too Many Attempts." }` |
| 500 | Error server | `{ "message": "Server Error" }` |

---

## 1. Login

**`POST /api/mobile/login`**

Tidak butuh token. Jika kasir login dari device berbeda, token lama otomatis di-revoke.

**Rate limit:** 5 request per menit per IP. Melebihi batas → HTTP 429.

### Request Body

```json
{
  "email": "kasir@tokoanda.com",
  "password": "password123"
}
```

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `email` | string | Ya | Email user terdaftar |
| `password` | string | Ya | Password user |

### Response — 200 OK

```json
{
  "token": "1|abcdefghijklmnopqrstuvwxyz1234567890",
  "user": {
    "id": 3,
    "name": "Budi Kasir",
    "email": "kasir@tokoanda.com",
    "role": "cashier"
  },
  "tenant": {
    "id": 1,
    "name": "Warung Kopi Mas Bro",
    "address": "Jl. Merdeka No. 1, Jakarta Pusat",
    "phone": "021-1234567"
  }
}
```

> `tenant` bisa `null` jika user belum terhubung ke tenant (jarang terjadi).

### Error

```json
// 422 — Email atau password salah
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Email atau password salah."]
  }
}
```

---

## 2. Logout

**`POST /api/mobile/logout`**

Revoke token yang sedang dipakai. Setelah logout, token tidak bisa digunakan lagi.

### Headers

```
Authorization: Bearer {token}
```

### Request Body

Tidak ada (kosong).

### Response — 200 OK

```json
{
  "message": "Logout berhasil."
}
```

---

## 3. Profil Tenant

**`GET /api/mobile/tenant/profile`**

Dipakai untuk refresh info usaha (misal setelah owner ubah data di web).

### Headers

```
Authorization: Bearer {token}
```

### Response — 200 OK

```json
{
  "data": {
    "id": 1,
    "name": "Warung Kopi Mas Bro",
    "address": "Jl. Merdeka No. 1, Jakarta Pusat",
    "phone": "021-1234567"
  }
}
```

> `address` dan `phone` bisa `null` jika belum diisi di Settings web.

---

## 4. Daftar Produk

**`GET /api/mobile/products`**

Mengembalikan semua produk aktif milik tenant beserta varian yang stoknya > 0 dan kategorinya.

### Headers

```
Authorization: Bearer {token}
```

### Response — 200 OK

```json
{
  "data": [
    {
      "id": 1,
      "name": "Kopi Hitam",
      "category_id": 2,
      "category": {
        "id": 2,
        "name": "Minuman"
      },
      "variants": [
        {
          "id": 5,
          "product_id": 1,
          "name": "Regular",
          "price": 12000,
          "stock": 50
        },
        {
          "id": 6,
          "product_id": 1,
          "name": "Large",
          "price": 16000,
          "stock": 30
        }
      ]
    }
  ]
}
```

> Varian dengan stok 0 **tidak muncul** di response ini.

---

## 5. Status Shift Kas

**`GET /api/mobile/cash-drawer/status`**

Cek apakah kasir yang login sedang punya sesi kas yang terbuka. Berguna untuk menentukan apakah kasir perlu buka shift dulu sebelum transaksi (dibuka dari web atau mobile).

### Headers

```
Authorization: Bearer {token}
```

### Response — 200 OK (shift terbuka)

```json
{
  "is_open": true,
  "drawer_id": 12,
  "opened_at": "2026-05-14T08:00:00.000000Z"
}
```

### Response — 200 OK (shift belum buka)

```json
{
  "is_open": false,
  "drawer_id": null,
  "opened_at": null
}
```

---

## 6. Checkout / Buat Transaksi

**`POST /api/mobile/transactions`**

Endpoint utama POS. Mendukung dua mode:

- **Direct-pay** — checkout langsung bayar, stok langsung dikurangi, status `completed`
- **Open Bill** — simpan pesanan dulu (untuk dine-in bayar di akhir), kirim `is_open_bill: true`, field `payments` boleh dikosongkan, status `pending`

Harga selalu diambil dari **database** — field `unit_price` di payload hanya untuk display snapshot, tidak dipakai untuk kalkulasi total.

### Headers

```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body

```json
{
  "items": [
    {
      "variant_id": 5,
      "variant_name": "Regular",
      "qty": 2,
      "notes": "less ice",
      "modifiers": [
        { "id": 3 }
      ]
    },
    {
      "variant_id": 8,
      "variant_name": "Original",
      "qty": 1,
      "notes": null,
      "modifiers": []
    }
  ],
  "is_open_bill": false,
  "order_type": "dine_in",
  "customer_name": "Pak Rudi",
  "table_number": "A5",
  "notes": "Jangan terlalu manis",
  "payments": [
    {
      "payment_method_id": 1,
      "amount": 50000,
      "reference_code": null
    }
  ]
}
```

### Field Reference

**`items[]`**

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `variant_id` | integer | Ya | ID varian dari `GET /products` — harus milik tenant yang sama |
| `variant_name` | string | Ya | Nama snapshot untuk struk (boleh bebas, tidak divalidasi ke DB) |
| `qty` | integer | Ya | Min: 1 |
| `notes` | string | Tidak | Catatan per item (misal: "less ice") |
| `modifiers[]` | array | Tidak | Kosongkan jika tidak ada modifier |
| `modifiers[].id` | integer | Ya* | ID modifier — harus milik tenant yang sama |

**Root fields**

| Field | Tipe | Wajib | Default | Keterangan |
|---|---|---|---|---|
| `is_open_bill` | boolean | Tidak | `false` | `true` = open bill, `payments` boleh kosong |
| `order_type` | string | Tidak | `dine_in` | Enum: `dine_in`, `takeaway` |
| `customer_name` | string | Tidak | `null` | Nama pelanggan untuk struk |
| `table_number` | string | Tidak | `null` | Nomor meja |
| `notes` | string | Tidak | `null` | Catatan order keseluruhan |

**`payments[]`** (wajib kecuali `is_open_bill: true`)

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `payment_method_id` | integer | Ya | ID dari daftar metode pembayaran tenant |
| `amount` | number | Ya | Jumlah yang dibayarkan (boleh lebih → kembalian dihitung otomatis) |
| `reference_code` | string | Tidak | Kode referensi QRIS / transfer (opsional) |

### Response — 201 Created (Direct-pay)

```json
{
  "message": "Transaksi berhasil.",
  "transaction_id": 47,
  "code": "TRX-20260514-003",
  "total_amount": 39000,
  "change_amount": 11000,
  "status": "completed",
  "is_open_bill": false
}
```

### Response — 201 Created (Open Bill)

```json
{
  "message": "Transaksi berhasil.",
  "transaction_id": 48,
  "code": "TRX-20260514-004",
  "total_amount": 24000,
  "change_amount": 0,
  "status": "pending",
  "is_open_bill": true
}
```

> Simpan `transaction_id` untuk memanggil receipt endpoint berikutnya.

### Error

```json
// 422 — Stok habis
{
  "message": "Stok Regular tidak cukup. Tersedia: 1, diminta: 2"
}

// 422 — variant_id tidak dikenali / bukan milik tenant
{
  "message": "The selected items.0.variant_id is invalid.",
  "errors": {
    "items.0.variant_id": ["The selected items.0.variant_id is invalid."]
  }
}

// 422 — payments wajib tapi kosong (bukan open bill)
{
  "message": "The payments field is required unless is open bill is 1.",
  "errors": {
    "payments": ["The payments field is required unless is open bill is 1."]
  }
}
```

---

## 7. Data Struk (Receipt)

**`GET /api/mobile/transactions/{id}/receipt`**

Ambil data transaksi terformat lengkap untuk print struk thermal. Hanya bisa akses transaksi milik tenant sendiri.

> Untuk **open bill yang belum dibayar**, `payments` akan berupa array kosong `[]` dan `is_open_bill` akan `true`. Mobile harus handle ini dan tampilkan status "BELUM DIBAYAR".

### Headers

```
Authorization: Bearer {token}
```

### Path Parameter

| Param | Keterangan |
|---|---|
| `{id}` | `transaction_id` dari response `POST /transactions` |

### Response — 200 OK

```json
{
  "data": {
    "tenant": {
      "name": "Warung Kopi Mas Bro",
      "address": "Jl. Merdeka No. 1, Jakarta Pusat",
      "phone": "021-1234567"
    },
    "transaction": {
      "code": "TRX-20260514-003",
      "date": "14/05/2026 09:30",
      "cashier": "Budi Kasir",
      "total_amount": 39000,
      "change_amount": 11000,
      "status": "completed",
      "order_type": "dine_in",
      "customer_name": "Pak Rudi",
      "table_number": "A5",
      "notes": "Jangan terlalu manis",
      "is_open_bill": false
    },
    "items": [
      {
        "name": "Regular",
        "qty": 2,
        "price": 12000,
        "subtotal": 27000,
        "notes": "less ice",
        "modifiers": [
          {
            "name": "Ekstra Shot",
            "extra_price": 1500
          }
        ]
      },
      {
        "name": "Original",
        "qty": 1,
        "price": 15000,
        "subtotal": 15000,
        "notes": null,
        "modifiers": []
      }
    ],
    "payments": [
      {
        "method": "Tunai",
        "amount": 50000,
        "reference_code": null
      }
    ]
  }
}
```

> **Catatan subtotal:** subtotal item sudah include extra_price modifier × qty.  
> Contoh: Regular 2×12000 + (1500×2) = 27000

### Error

```json
// 403 — Akses ke transaksi tenant lain
{
  "message": "Forbidden."
}

// 404 — Transaction ID tidak ditemukan
{
  "message": "No query results for model [App\\Models\\Transaction] 999"
}
```

---

## Panduan Testing di Postman

### Setup Awal

1. Buat **Collection** baru: `SAPI Mobile API`
2. Buat **Variable Collection**:
   - `base_url` → `http://127.0.0.1:8000`
   - `token` → (dikosongkan dulu, diisi otomatis setelah login)
3. Di semua request kecuali login: set header `Authorization: Bearer {{token}}`

### Urutan Test (Wajib Berurutan)

---

#### Test 1 — Login

**Request:**
```
POST {{base_url}}/api/mobile/login
Content-Type: application/json

{
  "email": "kasir@tokoanda.com",
  "password": "password123"
}
```

**Ekspektasi:** HTTP 200, ada field `token` di response.

**Script Postman (Tests tab):**
```javascript
const res = pm.response.json();
pm.test("Status 200", () => pm.response.to.have.status(200));
pm.test("Token ada", () => pm.expect(res.token).to.be.a('string'));
pm.collectionVariables.set("token", res.token);
```

---

#### Test 2 — Profil Tenant

**Request:**
```
GET {{base_url}}/api/mobile/tenant/profile
Authorization: Bearer {{token}}
```

**Ekspektasi:** HTTP 200, field `data.name` terisi nama usaha.

---

#### Test 3 — Status Kas

**Request:**
```
GET {{base_url}}/api/mobile/cash-drawer/status
Authorization: Bearer {{token}}
```

**Ekspektasi:** HTTP 200, field `is_open` bernilai `true` atau `false`.

> Jika `is_open: false`, buka shift kasir dulu dari web (`/cashier/cash-drawer`) sebelum test transaksi.

---

#### Test 4 — Daftar Produk

**Request:**
```
GET {{base_url}}/api/mobile/products
Authorization: Bearer {{token}}
```

**Ekspektasi:** HTTP 200, `data` adalah array produk.

**Script Postman:**
```javascript
const res = pm.response.json();
pm.test("Status 200", () => pm.response.to.have.status(200));
pm.test("Ada produk", () => pm.expect(res.data).to.be.an('array').that.is.not.empty);

// Simpan ID untuk test berikutnya
const variant = res.data[0].variants[0];
pm.collectionVariables.set("test_variant_id", variant.id);
pm.collectionVariables.set("test_variant_name", variant.name);
```

---

#### Test 5 — Checkout Direct-Pay

Ambil `variant_id`, `payment_method_id` dari data aktual tenant Anda.

**Request:**
```
POST {{base_url}}/api/mobile/transactions
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "items": [
    {
      "variant_id": {{test_variant_id}},
      "variant_name": "{{test_variant_name}}",
      "qty": 1,
      "notes": null,
      "modifiers": []
    }
  ],
  "is_open_bill": false,
  "order_type": "dine_in",
  "customer_name": "Test Customer",
  "table_number": "T1",
  "notes": null,
  "payments": [
    {
      "payment_method_id": 1,
      "amount": 100000,
      "reference_code": null
    }
  ]
}
```

**Ekspektasi:** HTTP 201, `status: "completed"`, ada `transaction_id` dan `code`.

**Script Postman:**
```javascript
const res = pm.response.json();
pm.test("Status 201", () => pm.response.to.have.status(201));
pm.test("Transaction completed", () => pm.expect(res.status).to.equal('completed'));
pm.collectionVariables.set("transaction_id", res.transaction_id);
```

---

#### Test 6 — Receipt

**Request:**
```
GET {{base_url}}/api/mobile/transactions/{{transaction_id}}/receipt
Authorization: Bearer {{token}}
```

**Ekspektasi:** HTTP 200, semua field struk lengkap (tenant, transaction, items, payments).

**Cek manual:**
- `data.tenant.name` — nama usaha benar
- `data.transaction.code` — format `TRX-YYYYMMDD-XXX`
- `data.items[0].subtotal` — nilai benar (harga × qty + modifier)
- `data.payments[0].method` — nama metode pembayaran benar

---

#### Test 7 — Logout

**Request:**
```
POST {{base_url}}/api/mobile/logout
Authorization: Bearer {{token}}
```

**Ekspektasi:** HTTP 200, `message: "Logout berhasil."`

---

#### Test 8 — Verifikasi Token Invalid Setelah Logout

**Request:**
```
GET {{base_url}}/api/mobile/products
Authorization: Bearer {{token}}
```

**Ekspektasi:** HTTP 401 `{ "message": "Unauthenticated." }`

---

### Test Skenario Khusus

#### A — Open Bill (Dine-In Bayar Belakangan)

```json
POST /api/mobile/transactions
{
  "items": [{ "variant_id": 5, "variant_name": "Regular", "qty": 1, "modifiers": [] }],
  "is_open_bill": true,
  "order_type": "dine_in",
  "customer_name": "Meja 3",
  "table_number": "3"
}
```
**Ekspektasi:** HTTP 201, `status: "pending"`, `is_open_bill: true`, `payments` di receipt kosong `[]`

---

#### B — Cross-Tenant Security

Kirim `variant_id` dari tenant lain (ID yang tidak ada di tenant Anda).

**Ekspektasi:** HTTP 422 validation error — bukan stok error.

---

#### C — Rate Limit Login

Kirim 6× request login dengan password salah dalam 1 menit.

**Ekspektasi:** Request ke-6 → HTTP 429.

---

#### D — Stok Habis

Kirim `qty` yang melebihi stok tersedia (lihat stok dari `GET /products`).

**Ekspektasi:** HTTP 422, `message: "Stok {nama} tidak cukup. Tersedia: X, diminta: Y"`

---

#### E — Takeaway

```json
POST /api/mobile/transactions
{
  "items": [...],
  "order_type": "takeaway",
  "customer_name": "Pak Andi",
  "payments": [{ "payment_method_id": 1, "amount": 30000 }]
}
```
**Cek receipt:** `data.transaction.order_type` harus `"takeaway"`

---

## Nilai Enum

| Field | Nilai Valid |
|---|---|
| `order_type` | `dine_in`, `takeaway` |
| `status` (transaksi) | `pending` (open bill), `completed`, `voided` |
| `role` (user) | `owner`, `cashier` |

---

## Catatan Implementasi

- **Harga selalu dari DB** — `unit_price` di payload tidak dipakai untuk kalkulasi, hanya untuk snapshot struk
- **Modifier extra_price dari DB** — cukup kirim `id`, tidak perlu `name` atau `extra_price`
- **Token revoke otomatis** — login baru dari device yang sama akan revoke token lama (`mobile-app`)
- **Tenant isolation** — semua data di-filter otomatis by tenant; `variant_id` / `modifier_id` / `payment_method_id` dari tenant lain akan ditolak saat validasi
- **Idempotency** — belum diimplementasi (Phase 2); mobile app harus disable tombol setelah submit untuk cegah double-submit
