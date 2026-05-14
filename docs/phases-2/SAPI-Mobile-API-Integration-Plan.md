    # SAPI — Planning: Mobile API Integration (POS Kasir + Print Struk)

**Dibuat:** Mei 2026  
**Status:** In Progress  
**Tujuan:** Membuka API layer untuk integrasi mobile app kasir — dimulai dari full POS flow (POV kasir) dan print struk, sebagai fondasi sebelum fitur rekap dan menu lainnya.

---

## Konteks & Keputusan Awal

SAPI sudah memiliki API layer yang berjalan untuk self-order (Telegram bot via n8n). Layer ini menggunakan:
- **Laravel Sanctum** — token-based authentication (Bearer token)
- **`routes/api.php`** — sudah terdaftar di `bootstrap/app.php`
- **`ApiProductController`** — sudah ada, endpoint `GET /api/products`
- **`TransactionService`** — sudah ada, method `checkout()` bisa di-reuse langsung
- **`EnsureTenant` middleware** — sudah ada, tapi saat ini redirect ke halaman login (perilaku web), **belum aman untuk API**

Keputusan arsitektur:
- Tidak membuat controller baru dari nol — **reuse `TransactionService` yang sudah ada**
- Tidak duplikasi logic stok atau transaksi — semua tetap lewat service layer
- Mobile app autentikasi via **Sanctum Personal Access Token** (sama seperti self-order)
- Response selalu **JSON** — tidak boleh ada redirect atau Inertia response di api routes

---

## Scope Fase Ini (Mobile Phase 1)

| Scope | Status |
|---|---|
| Full POS — produk, checkout, payment | 🔲 Dikerjakan sekarang |
| Print struk — ambil data transaksi terformat | 🔲 Dikerjakan sekarang |
| Rekap / laporan owner | ⏳ Fase berikutnya |
| Manajemen menu dari mobile | ⏳ Fase berikutnya |

---

## Daftar Endpoint — Phase 1

Total: **7 endpoint**

### Auth
| Method | Endpoint | Deskripsi |
|---|---|---|
| `POST` | `/api/login` | Login kasir, return Sanctum Bearer token |
| `POST` | `/api/logout` | Revoke token aktif |

### Tenant
| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/tenant/profile` | Nama usaha, alamat, telp — untuk header struk |

### Produk
| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/products` | **Sudah ada.** List produk aktif + varian + modifier |

### Kas
| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/cash-drawer/status` | Cek apakah shift kasir sedang buka |

### Transaksi
| Method | Endpoint | Deskripsi |
|---|---|---|
| `POST` | `/api/transactions` | Checkout — proses transaksi, potong stok |
| `GET` | `/api/transactions/{id}/receipt` | Ambil data struk terformat untuk print |

---

## Urutan Pengerjaan

Urutan ini **wajib diikuti** — jangan loncat step karena setiap step adalah dependency step berikutnya.

---

### Step 1 — Buat `EnsureTenantApi` Middleware

**Kenapa perlu step ini dulu?**  
`EnsureTenant` yang ada sekarang melakukan `redirect()->route('login')` jika user tidak punya tenant. Di konteks API, redirect itu akan mengembalikan HTML — bukan JSON. Mobile app tidak bisa membaca itu. Harus ada versi API-nya yang return `401 JSON`.

**File:** `app/Http/Middleware/EnsureTenantApi.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!auth()->user()->tenant_id) {
            return response()->json(['message' => 'User tidak terhubung ke tenant manapun.'], 403);
        }

        return $next($request);
    }
}
```

Daftarkan di `bootstrap/app.php`:

```php
$middleware->alias([
    'tenant'     => \App\Http\Middleware\EnsureTenant::class,
    'tenant.api' => \App\Http\Middleware\EnsureTenantApi::class, // ← TAMBAH
    'role'       => \App\Http\Middleware\EnsureRole::class,
]);
```

---

### Step 2 — Buka Routes di `api.php`

Tambahkan di bawah routes self-order yang sudah ada:

```php
// ─────────────────────────────────────────
// MOBILE APP — POS Kasir
// ─────────────────────────────────────────

// Auth (tidak perlu Sanctum — ini untuk dapat token)
Route::post('/login', [\App\Http\Controllers\Api\Mobile\MobileAuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'tenant.api'])->group(function () {

    // Auth
    Route::post('/logout', [\App\Http\Controllers\Api\Mobile\MobileAuthController::class, 'logout']);

    // Tenant
    Route::get('/tenant/profile', [\App\Http\Controllers\Api\Mobile\MobileTenantController::class, 'profile']);

    // Produk (reuse ApiProductController yang sudah ada)
    Route::get('/products', [\App\Http\Controllers\Api\ApiProductController::class, 'index']);

    // Kas
    Route::get('/cash-drawer/status', [\App\Http\Controllers\Api\Mobile\MobileCashDrawerController::class, 'status']);

    // Transaksi
    Route::post('/transactions', [\App\Http\Controllers\Api\Mobile\MobileTransactionController::class, 'store']);
    Route::get('/transactions/{transaction}/receipt', [\App\Http\Controllers\Api\Mobile\MobileTransactionController::class, 'receipt']);

});
```

**Catatan penting:**  
- `GET /api/products` tetap pakai `ApiProductController` yang sudah ada — tidak perlu duplikasi
- Grouping `auth:sanctum` + `tenant.api` memastikan setiap request sudah autentikasi DAN punya tenant valid

---

### Step 3 — Buat Controllers

Semua controller baru disimpan di folder: `app/Http/Controllers/Api/Mobile/`

#### 3.1 `MobileAuthController`

```php
<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Hapus token lama (opsional — satu device satu token)
        $user->tokens()->where('name', 'mobile-app')->delete();

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
```

#### 3.2 `MobileTenantController`

```php
<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MobileTenantController extends Controller
{
    public function profile(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        return response()->json([
            'data' => [
                'name'    => $tenant->name,
                'address' => $tenant->address ?? null,
                'phone'   => $tenant->phone ?? null,
            ],
        ]);
    }
}
```

#### 3.3 `MobileCashDrawerController`

```php
<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CashDrawer;
use Illuminate\Http\JsonResponse;

class MobileCashDrawerController extends Controller
{
    public function status(): JsonResponse
    {
        $drawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        return response()->json([
            'is_open'   => (bool) $drawer,
            'drawer_id' => $drawer?->id,
            'opened_at' => $drawer?->opened_at,
        ]);
    }
}
```

#### 3.4 `MobileTransactionController`

Ini controller terpenting. Reuse `TransactionService` — tidak ada duplikasi logic.

```php
<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileTransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'                           => 'required|array|min:1',
            'items.*.variant_id'              => 'required|exists:product_variants,id',
            'items.*.variant_name'            => 'required|string|max:255',
            'items.*.qty'                     => 'required|integer|min:1',
            'items.*.unit_price'              => 'required|numeric|min:0',
            'items.*.modifiers'               => 'nullable|array',
            'items.*.modifiers.*.id'          => 'required|exists:modifiers,id',
            'items.*.modifiers.*.name'        => 'required|string',
            'items.*.modifiers.*.extra_price' => 'required|numeric|min:0',
            'payments'                        => 'required|array|min:1',
            'payments.*.payment_method_id'    => 'required|exists:payment_methods,id',
            'payments.*.amount'               => 'required|numeric|min:0',
            'notes'                           => 'nullable|string|max:500',
        ]);

        try {
            // TransactionService sudah handle: DB::transaction, lockForUpdate,
            // snapshot harga, potong stok, log stock_movements
            $transaction = $this->transactionService->checkout($validated);

            return response()->json([
                'message'        => 'Transaksi berhasil.',
                'transaction_id' => $transaction->id,
                'code'           => $transaction->code,
                'total_amount'   => $transaction->total_amount,
                'change_amount'  => $transaction->change_amount,
                'status'         => $transaction->status,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function receipt(Transaction $transaction): JsonResponse
    {
        // Pastikan hanya bisa akses transaksi milik tenant sendiri
        if ($transaction->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $transaction->load(['items.modifiers', 'payments.paymentMethod', 'user:id,name']);
        $tenant = auth()->user()->tenant;

        return response()->json([
            'data' => [
                'tenant' => [
                    'name'    => $tenant->name,
                    'address' => $tenant->address ?? null,
                    'phone'   => $tenant->phone ?? null,
                ],
                'transaction' => [
                    'code'         => $transaction->code,
                    'date'         => $transaction->created_at->format('d/m/Y H:i'),
                    'cashier'      => $transaction->user->name,
                    'total_amount' => $transaction->total_amount,
                    'change_amount'=> $transaction->change_amount,
                    'status'       => $transaction->status,
                ],
                'items' => $transaction->items->map(fn($item) => [
                    'name'      => $item->variant_name,
                    'qty'       => $item->qty,
                    'price'     => $item->unit_price,
                    'subtotal'  => $item->subtotal,
                    'modifiers' => $item->modifiers->map(fn($m) => [
                        'name'        => $m->name,
                        'extra_price' => $m->extra_price,
                    ]),
                ]),
                'payments' => $transaction->payments->map(fn($p) => [
                    'method' => $p->paymentMethod->name,
                    'amount' => $p->amount,
                ]),
            ],
        ]);
    }
}
```

---

### Step 4 — Testing di Postman

Sebelum membuat docs, **semua endpoint harus lolos test ini**. Jangan lanjut ke docs kalau ada yang error.

Urutan test:

1. `POST /api/login` → simpan token dari response
2. Set header `Authorization: Bearer {token}` di semua request berikutnya
3. `GET /api/tenant/profile` → verifikasi nama usaha muncul
4. `GET /api/cash-drawer/status` → cek response `is_open` true/false
5. `GET /api/products` → verifikasi produk + varian muncul dengan benar
6. `POST /api/transactions` → kirim payload lengkap, cek response `transaction_id` dan `code`
7. `GET /api/transactions/{id}/receipt` → gunakan `transaction_id` dari step 6, cek semua field struk
8. `POST /api/logout` → verifikasi token tidak bisa dipakai lagi

**Payload contoh untuk `POST /api/transactions`:**

```json
{
  "items": [
    {
      "variant_id": 1,
      "variant_name": "Regular",
      "qty": 2,
      "unit_price": 25000,
      "modifiers": []
    }
  ],
  "payments": [
    {
      "payment_method_id": 1,
      "amount": 50000
    }
  ],
  "notes": null
}
```

---

### Step 5 — Buat API Documentation

Setelah semua endpoint lolos Postman:

1. **Export Postman Collection** sebagai JSON (v2.1)
2. **Buat `API-DOCS.md`** — satu file markdown dengan struktur:
   - Overview & Base URL
   - Authentication (cara dapat token, cara pakai Bearer)
   - Per endpoint: method, URL, headers, request body, response contoh, error codes

Format response error yang konsisten (wajib dokumentasikan):

```json
// 401 Unauthenticated
{ "message": "Unauthenticated." }

// 403 Forbidden
{ "message": "Forbidden." }

// 422 Validation Error
{
  "message": "The given data was invalid.",
  "errors": {
    "items": ["The items field is required."]
  }
}
```

---

## Hal-hal Yang Perlu Diperhatikan

### Tenant Isolation di Receipt
`MobileTransactionController@receipt` wajib cek `$transaction->tenant_id === auth()->user()->tenant_id` sebelum return data. Ini mencegah kasir tenant A bisa akses struk tenant B cukup dengan menebak transaction ID.

### TransactionService Sudah Atomic
`checkout()` sudah dibungkus `DB::transaction()` dan pakai `lockForUpdate()` untuk cegah race condition stok. Mobile app tidak perlu khawatir soal ini — cukup kirim payload yang valid.

### Harga Dari DB, Bukan Client
`TransactionService` sudah ambil harga dari database, bukan dari `unit_price` yang dikirim client. `unit_price` di payload hanya untuk keperluan display/snapshot — tidak dipakai untuk menghitung total. Ini sudah ditest di unit test (`checkout uses DB price not client price`).

### Token Revocation Saat Login
Di `MobileAuthController@login`, token lama dengan nama `mobile-app` dihapus sebelum token baru dibuat. Ini mencegah akumulasi token yang tidak terpakai jika kasir login berkali-kali dari device yang sama.

---

## Yang Tidak Dikerjakan Sekarang (Defer)

| Item | Alasan |
|---|---|
| Thermal printer ESC/POS integration | Butuh printer fisik untuk test — defer post-deal |
| Buka/tutup kas dari mobile | Bukan prioritas demo, bisa lewat web dulu |
| Rekap & laporan dari mobile | Mobile Phase 2 |
| QRIS payment di mobile | Sudah di-defer dari awal — kompleksitas real-time UI |
| Role check di mobile (kasir vs owner) | Untuk sekarang semua kasir punya akses yang sama |

---

## Checklist

- [ ] `EnsureTenantApi` middleware dibuat dan didaftarkan
- [ ] Routes ditambahkan di `api.php`
- [ ] `MobileAuthController` dibuat dan lolos test login/logout
- [ ] `MobileTenantController` dibuat dan lolos test
- [ ] `MobileCashDrawerController` dibuat dan lolos test
- [ ] `MobileTransactionController` dibuat — `store` dan `receipt` lolos test
- [ ] Semua 7 endpoint lolos test di Postman (urutan step 4)
- [ ] Postman Collection di-export
- [ ] `API-DOCS.md` selesai dan diserahkan ke tim mobile
