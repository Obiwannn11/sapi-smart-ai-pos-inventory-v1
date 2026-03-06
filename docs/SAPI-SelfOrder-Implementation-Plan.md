# SAPI — Self Order via Telegram + Xendit Payment
## Implementation Plan & Technical Guide
**Versi:** 1.1 (Revised)  
**Tanggal:** Maret 2026  
**Scope:** Penambahan fitur Self Order channel via Telegram Bot, terintegrasi dengan SAPI POS dan Xendit payment gateway (sandbox)

---

## ⚠️ Revisi dari Plan v1.0

> Section ini mendokumentasikan semua perubahan keputusan dari plan awal setelah diskusi arsitektur.

### Revisi 1: Self-Order Stok TIDAK Dikurangi Sebelum Bayar
- **Plan awal:** `ApiOrderController` memanggil `checkout()` yang langsung deduct stok + set status completed
- **Revisi:** Stok baru dikurangi setelah Xendit webhook confirm PAID. Self-order menggunakan method baru `createSelfOrder()` yang hanya simpan item snapshot tanpa deduct stok
- **Alasan:** Mencegah order fiktif. Jika customer tidak bayar (invoice expired), tidak perlu restore stok

### Revisi 2: Fulfillment Tracking System (Fitur Baru)
- **Plan awal:** Tidak ada sistem tracking penyajian
- **Revisi:** Kolom baru `fulfillment_status` (waiting → preparing → ready → done) + `order_type` (dine_in/pickup) + `customer_name` + `table_number`
- **Aturan:** POS bayar langsung = `null` (skip tracking). Open bill & self-order (setelah bayar) = `waiting`
- **Operator:** Kasir + Owner (tanpa role kitchen). Customer diberitahu manual (panggil nama)

### Revisi 3: Xendit Webhook Handle EXPIRED
- **Plan awal:** Webhook hanya handle status PAID (update reference_code saja)
- **Revisi:** Webhook handle PAID (deduct stok + completed + fulfillment aktif) DAN EXPIRED (void tanpa restore)

### Revisi 4: `customer_name` Jadi Kolom Terstruktur
- **Plan awal:** `customer_name` digabung ke field `notes`
- **Revisi:** Kolom `customer_name` dedicated (varchar 100) di tabel `transactions`

### Revisi 5: Semua Payment Method Didukung
- **Plan awal:** Placeholder cash method saat checkout
- **Revisi:** Xendit handle semua payment (QRIS, transfer, e-wallet). Payment record disimpan setelah webhook PAID dengan reference ke payment method yang sesuai

### Revisi 6: `source` di Migration Terpisah
- **Plan awal:** Hanya kolom `source` yang ditambahkan
- **Revisi:** Migration mencakup `source`, `order_type`, `fulfillment_status`, `customer_name`, `table_number` sekaligus

---

## Gambaran Besar Fitur

```
Customer kirim pesan Telegram
    → n8n terima & parsing via AI
    → n8n ambil daftar produk dari SAPI API
    → n8n kirim order ke SAPI API
    → SAPI buat transaksi (pending, stok BELUM dikurangi) + generate Xendit Invoice
    → n8n kirim payment link + info order ke customer
    → Customer bayar via Xendit (QRIS / transfer / e-wallet)
    → Xendit kirim webhook PAID ke SAPI
    → SAPI deduct stok + set completed + aktifkan fulfillment tracking
    → Kasir lihat order masuk → preparing → ready → done (panggil nama)
    → Order tercatat sebagai completed di dashboard owner

    [Jika tidak bayar dalam 30 menit]
    → Xendit kirim webhook EXPIRED ke SAPI
    → SAPI void transaksi (stok tidak perlu di-restore)
```

---

## Prerequisites

Sebelum mulai, pastikan hal-hal berikut sudah siap:

- [ ] VPS sudah live dan accessible dari internet
- [ ] Domain/IP publik sudah pointing ke VPS
- [ ] Laravel SAPI sudah running di VPS
- [ ] Akun Xendit sudah dibuat di dashboard.xendit.co
- [ ] Xendit Secret Key sudah di-copy (mode Test/Sandbox)
- [ ] Sanctum sudah ter-install (cek dengan `php artisan migrate:status`)
- [ ] Docker sudah ter-install di VPS (untuk n8n)
- [ ] Akun Telegram sudah ada untuk buat bot

### Verifikasi Sanctum

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

---

## Struktur File Baru yang Akan Dibuat

```
app/
├── Http/
│   └── Controllers/
│       └── Api/
│           ├── ApiProductController.php    ← BARU (✅ sudah dibuat)
│           ├── ApiOrderController.php      ← BARU (✅ sudah dibuat)
│           └── XenditWebhookController.php ← BARU (✅ sudah dibuat)
├── Models/
│   └── Transaction.php                     ← UPDATE (✅ constants, fillable, helpers)
├── Services/
│   └── TransactionService.php              ← UPDATE (✅ createSelfOrder, confirmPayment, processItems)
routes/
└── api.php                                 ← BARU (✅ sudah dibuat)
bootstrap/
└── app.php                                 ← UPDATE (✅ api routes registered)
database/
├── migrations/
│   └── 2026_03_06_300001_add_selforder_and_fulfillment_to_transactions.php ← BARU (✅ sudah dibuat & migrated)
└── factories/
    └── TransactionFactory.php              ← UPDATE (✅ selfOrder, withFulfillment, pickup states)
```

---

## HARI 1 — Laravel API Layer

### Step 1: Buat `routes/api.php`

File ini belum ada di project. Buat baru:

```php
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SAPI Self Order
|--------------------------------------------------------------------------
| Semua route di sini menggunakan prefix /api secara otomatis
| karena didaftarkan sebagai api routes di bootstrap/app.php
*/

// Routes yang butuh autentikasi Sanctum
Route::middleware(['auth:sanctum'])->group(function () {

    // Ambil daftar produk aktif + variant tersedia
    Route::get('/products', [\App\Http\Controllers\Api\ApiProductController::class, 'index']);

    // Buat order baru dari self-order channel
    Route::post('/orders', [\App\Http\Controllers\Api\ApiOrderController::class, 'store']);

});

// Xendit webhook — tidak perlu auth, verifikasi via callback token
Route::post('/xendit/webhook', [\App\Http\Controllers\Api\XenditWebhookController::class, 'handle']);
```

---

### Step 2: Daftarkan API routes di `bootstrap/app.php`

Buka `bootstrap/app.php`, tambahkan baris `api`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',      // ← TAMBAH BARIS INI
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // ... sisa kode tidak berubah
```

---

### Step 3: Buat migration kolom `source` di `transactions`

```bash
php artisan make:migration add_source_to_transactions_table
```

Isi migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Tambah setelah kolom notes
            $table->enum('source', ['pos', 'self_order'])
                  ->default('pos')
                  ->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
```

Jalankan:

```bash
php artisan migrate
```

Update `Transaction` model — tambahkan `'source'` ke `$fillable`:

```php
// app/Models/Transaction.php
protected $fillable = [
    'tenant_id', 'user_id', 'code', 'status',
    'total_amount', 'change_amount', 'notes', 'source', // ← tambah 'source'
];
```

---

### Step 4: Install Xendit PHP SDK

```bash
composer require xendit/xendit-php
```

Tambahkan ke `.env`:

```env
XENDIT_SECRET_KEY=xnd_development_xxxxxx_your_key_here
XENDIT_WEBHOOK_TOKEN=buat_string_random_minimal_32_karakter
```

> **Catatan `XENDIT_WEBHOOK_TOKEN`:** Buat string random bebas, contoh: `sapi2026xenditwebhooktoken`. String ini akan dipakai untuk verifikasi request dari Xendit. Simpan, nanti diisi juga di Xendit dashboard.

Tambahkan ke `config/services.php`:

```php
'xendit' => [
    'secret_key'    => env('XENDIT_SECRET_KEY'),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
],
```

---

### Step 5: Buat `ApiProductController`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ApiProductController extends Controller
{
    /**
     * Ambil semua produk aktif milik tenant yang login,
     * beserta variant yang masih punya stok.
     * Dipakai oleh n8n untuk context AI parsing order.
     */
    public function index(): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->with([
                'variants' => fn($q) => $q
                    ->select('id', 'product_id', 'name', 'price', 'stock')
                    ->where('stock', '>', 0),
                'category:id,name',
            ])
            ->get(['id', 'name', 'category_id']);

        return response()->json([
            'data' => $products,
        ]);
    }
}
```

---

### Step 6: Buat `ApiOrderController`

Ini controller terpenting. Alurnya:

1. Validasi request dari n8n
2. Hitung total dari DB price (bukan client price)
3. Checkout via `TransactionService` yang sudah ada
4. Generate Xendit Invoice
5. Return data + payment link ke n8n

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

class ApiOrderController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input dari n8n
        $validated = $request->validate([
            'items'                           => 'required|array|min:1',
            'items.*.variant_id'              => 'required|exists:product_variants,id',
            'items.*.variant_name'            => 'required|string|max:255',
            'items.*.qty'                     => 'required|integer|min:1',
            'items.*.modifiers'               => 'nullable|array',
            'items.*.modifiers.*.id'          => 'required|exists:modifiers,id',
            'items.*.modifiers.*.name'        => 'required|string',
            'items.*.modifiers.*.extra_price' => 'required|numeric|min:0',
            'customer_name'                   => 'nullable|string|max:100',
            'notes'                           => 'nullable|string|max:500',
        ]);

        try {
            // 2. Hitung total dari DB price — jangan percaya client
            $total = collect($validated['items'])->sum(function ($item) {
                $variant      = ProductVariant::findOrFail($item['variant_id']);
                $modifierTotal = collect($item['modifiers'] ?? [])->sum('extra_price');
                return ($variant->price + $modifierTotal) * $item['qty'];
            });

            // 3. Ambil payment method cash sebagai placeholder
            // (akan di-update jadi reference Xendit setelah bayar)
            $cashMethod = PaymentMethod::where('type', 'cash')
                ->where('is_active', true)
                ->firstOrFail();

            // 4. Checkout via TransactionService — reuse logic yang sudah ada
            $transaction = $this->transactionService->checkout([
                'items'    => $validated['items'],
                'payments' => [[
                    'payment_method_id' => $cashMethod->id,
                    'amount'            => $total,
                ]],
                'notes'    => ($validated['customer_name'] ?? 'Self Order')
                              . ($validated['notes'] ? ' — ' . $validated['notes'] : ''),
                'source'   => 'self_order',
            ]);

            // 5. Generate Xendit Invoice
            Configuration::setXenditKey(config('services.xendit.secret_key'));
            $invoiceApi = new InvoiceApi();

            $invoiceRequest = new CreateInvoiceRequest([
                'external_id'      => $transaction->code,
                'amount'           => (int) $transaction->total_amount,
                'payer_email'      => 'customer@sapi.test',
                'description'      => 'Order ' . $transaction->code . ' via SAPI Self Order',
                'currency'         => 'IDR',
                'invoice_duration' => 1800, // 30 menit dalam detik
                'customer'         => [
                    'given_names' => $validated['customer_name'] ?? 'Customer',
                ],
                'items' => collect($validated['items'])->map(function ($item) {
                    $variant = ProductVariant::with('product')->find($item['variant_id']);
                    return [
                        'name'     => $variant->product->name . ' — ' . $item['variant_name'],
                        'quantity' => $item['qty'],
                        'price'    => (int) $variant->price,
                    ];
                })->toArray(),
                // Aktifkan payment methods yang tersedia di sandbox
                'payment_methods' => ['QRIS', 'OVO', 'DANA', 'BNI', 'BRI', 'MANDIRI'],
            ]);

            $invoice = $invoiceApi->createInvoice($invoiceRequest);

            // 6. Return semua data yang dibutuhkan n8n
            return response()->json([
                'success'          => true,
                'transaction_code' => $transaction->code,
                'total_amount'     => (int) $transaction->total_amount,
                'invoice_url'      => $invoice['invoice_url'],
                'invoice_id'       => $invoice['id'],
                'customer_name'    => $validated['customer_name'] ?? 'Customer',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
```

---

### Step 7: Buat `XenditWebhookController`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XenditWebhookController extends Controller
{
    /**
     * Terima notifikasi pembayaran dari Xendit.
     * Xendit akan hit endpoint ini setiap ada update status invoice.
     */
    public function handle(Request $request): JsonResponse
    {
        // Verifikasi bahwa request benar-benar dari Xendit
        $callbackToken = $request->header('x-callback-token');

        if ($callbackToken !== config('services.xendit.webhook_token')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payload = $request->all();

        // Hanya proses jika invoice sudah PAID
        if (($payload['status'] ?? '') === 'PAID') {

            $transaction = Transaction::where('code', $payload['external_id'])->first();

            if ($transaction && $transaction->status === Transaction::STATUS_COMPLETED) {
                // Update reference_code di payment record dengan Xendit invoice ID
                $transaction->payments()->update([
                    'reference_code' => $payload['id'],
                ]);
            }
        }

        // Selalu return 200 ke Xendit agar tidak retry
        return response()->json(['message' => 'OK']);
    }
}
```

---

### Step 8: Update `TransactionService` untuk handle `source`

Buka `app/Services/TransactionService.php`, update bagian `Transaction::create()` di dalam method `checkout()`:

```php
// Cari baris ini:
$transaction = Transaction::create([
    'tenant_id'     => $tenantId,
    'user_id'       => auth()->id(),
    'code'          => $code,
    'status'        => Transaction::STATUS_PENDING,
    'total_amount'  => 0,
    'change_amount' => 0,
    'notes'         => $data['notes'] ?? null,
]);

// Ganti dengan ini:
$transaction = Transaction::create([
    'tenant_id'     => $tenantId,
    'user_id'       => auth()->id(),
    'code'          => $code,
    'status'        => Transaction::STATUS_PENDING,
    'total_amount'  => 0,
    'change_amount' => 0,
    'notes'         => $data['notes'] ?? null,
    'source'        => $data['source'] ?? 'pos', // ← baris baru
]);
```

---

### Step 9: Generate Sanctum Token untuk n8n

Token ini akan dipakai n8n untuk autentikasi ke SAPI API. Jalankan di server:

```bash
php artisan tinker
```

```php
// Pakai user kasir yang sudah ada
$user = \App\Models\User::where('role', 'cashier')
                        ->where('tenant_id', 1) // sesuaikan tenant_id
                        ->first();

$token = $user->createToken('n8n-self-order-bot')->plainTextToken;
echo $token;
// Output: 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

> **SIMPAN TOKEN INI.** Token hanya ditampilkan sekali. Kalau hilang, delete token lama dan buat baru via `php artisan tinker` lagi.

---

### Step 10: Daftarkan Webhook di Xendit Dashboard

1. Login ke dashboard.xendit.co
2. Pergi ke **Settings → Webhooks & Callbacks**
3. Di bagian **Invoice** → tambahkan URL:
   ```
   https://domain-kamu.com/api/xendit/webhook
   ```
4. Di field **Webhook Verification Token**, isi dengan value yang sama persis dengan `XENDIT_WEBHOOK_TOKEN` di `.env` kamu

---

### Checklist Akhir Hari 1

Sebelum lanjut ke Hari 2, test semua endpoint ini via **Postman** atau **Hoppscotch**:

```bash
# Test 1: Pastikan API products bisa diakses
GET https://domain-kamu.com/api/products
Header: Authorization: Bearer {TOKEN}

# Expected response:
# { "data": [ { "id": 1, "name": "Espresso", "variants": [...] } ] }

# Test 2: Pastikan order bisa dibuat
POST https://domain-kamu.com/api/orders
Header: Authorization: Bearer {TOKEN}
Body:
{
  "items": [
    {
      "variant_id": 1,
      "variant_name": "Single",
      "qty": 1,
      "modifiers": []
    }
  ],
  "customer_name": "Budi",
  "notes": "Test order"
}

# Expected response:
# {
#   "success": true,
#   "transaction_code": "TRX-20260307-001",
#   "total_amount": 18000,
#   "invoice_url": "https://checkout.xendit.co/...",
#   "invoice_id": "..."
# }
```

Jika kedua test berhasil, Hari 1 selesai.

---

## HARI 2 — Telegram Bot + n8n Workflow

### Step 11: Buat Telegram Bot

1. Buka Telegram → cari `@BotFather`
2. Ketik `/newbot`
3. Isi nama bot: contoh `SAPI Order Bot`
4. Isi username bot: contoh `sapi_order_bot` (harus diakhiri `bot`)
5. Simpan **Bot Token** — format: `1234567890:AAxxxxxxxxxxxxxx`

Test bot sudah aktif:
```
https://api.telegram.org/bot{BOT_TOKEN}/getMe
```
Harusnya return info bot kamu.

---

### Step 12: Install n8n di VPS

Jalankan di VPS:

```bash
# Buat volume untuk data persistence
docker volume create n8n_data

# Jalankan n8n
docker run -d \
  --name n8n \
  --restart unless-stopped \
  -p 5678:5678 \
  -e N8N_BASIC_AUTH_ACTIVE=true \
  -e N8N_BASIC_AUTH_USER=admin \
  -e N8N_BASIC_AUTH_PASSWORD=password_kamu_di_sini \
  -e WEBHOOK_URL=https://domain-kamu.com:5678/ \
  -e N8N_HOST=0.0.0.0 \
  -v n8n_data:/home/node/.n8n \
  n8nio/n8n
```

Pastikan port 5678 terbuka di firewall:

```bash
# Ubuntu/Debian dengan UFW
ufw allow 5678/tcp

# Atau dengan iptables
iptables -A INPUT -p tcp --dport 5678 -j ACCEPT
```

Akses n8n di: `https://domain-kamu.com:5678`

---

### Step 13: Setup Credentials di n8n

Sebelum buat workflow, daftarkan credentials dulu:

**Telegram Credential:**
1. Di n8n → **Credentials** → **Add Credential**
2. Pilih: Telegram
3. Isi Bot Token dari Step 11
4. Save

**OpenAI atau Anthropic Credential** (untuk AI parsing):
1. Di n8n → **Credentials** → **Add Credential**
2. Pilih: OpenAI API (atau Anthropic)
3. Isi API key
4. Save

---

### Step 14: Buat Workflow n8n (5 Node)

Buat workflow baru di n8n. Tambahkan node satu per satu sesuai urutan:

---

**Node 1 — Telegram Trigger**

| Setting | Value |
|---|---|
| Credential | Telegram credential dari Step 13 |
| Updates | message |

Ini adalah pintu masuk. Setiap pesan yang dikirim ke bot akan trigger workflow ini.

---

**Node 2 — HTTP Request (Ambil Produk)**

| Setting | Value |
|---|---|
| Method | GET |
| URL | `https://domain-kamu.com/api/products` |
| Authentication | Header Auth |
| Header Name | `Authorization` |
| Header Value | `Bearer TOKEN_DARI_STEP_9` |

Node ini ambil daftar produk terbaru setiap ada order masuk — supaya AI selalu punya data produk yang aktual.

---

**Node 3 — AI Agent / Basic LLM Chain (Parse Order)**

| Setting | Value |
|---|---|
| Credential | OpenAI/Anthropic dari Step 13 |
| Model | gpt-4o-mini atau claude-haiku (lebih hemat) |

**Prompt:**

```
Kamu adalah parser order untuk aplikasi kasir SAPI POS.

DAFTAR PRODUK TERSEDIA:
{{ JSON.stringify($node["HTTP Request"].json.data) }}

PESAN DARI CUSTOMER:
"{{ $node["Telegram Trigger"].json.message.text }}"

TUGAS KAMU:
Ubah pesan customer menjadi JSON order yang valid.
Cocokkan nama produk yang disebutkan dengan daftar produk di atas.
Pilih variant dengan stock > 0. Jika ada beberapa variant, pilih yang pertama 
kecuali customer menyebutkan spesifik (contoh: "hot", "iced", "large").

ATURAN:
- Return HANYA JSON, tidak ada teks lain, tidak ada markdown code block
- Jika produk tidak ditemukan di daftar, abaikan item tersebut
- qty default 1 jika tidak disebutkan
- modifiers kosong array jika tidak ada

FORMAT RESPONSE:
{
  "items": [
    {
      "variant_id": 1,
      "variant_name": "nama variant",
      "qty": 1,
      "modifiers": []
    }
  ],
  "customer_name": "nama customer atau null",
  "notes": "catatan tambahan atau null",
  "is_valid": true
}

Jika pesan bukan order makanan/minuman, return:
{
  "is_valid": false,
  "message": "Maaf, saya hanya bisa proses order ya!"
}
```

---

**Node 4 — IF (Cek apakah order valid)**

| Setting | Value |
|---|---|
| Condition | `{{ $json.is_valid }}` equals `true` |

Jika `is_valid = false`, kirim pesan error ke Telegram.
Jika `is_valid = true`, lanjut ke Node 5.

**Branch FALSE → Node 4b (Telegram kirim pesan error):**

```
{{ $json.message ?? "Maaf, saya tidak mengerti pesananmu. Coba ketik ulang ya, contoh: 'pesan 2 kopi susu'" }}
```

---

**Node 5 — HTTP Request (Kirim Order ke SAPI)**

| Setting | Value |
|---|---|
| Method | POST |
| URL | `https://domain-kamu.com/api/orders` |
| Authentication | Header Auth |
| Header Name | `Authorization` |
| Header Value | `Bearer TOKEN_DARI_STEP_9` |
| Content Type | JSON |

**Body (JSON):**

```json
{
  "items": {{ JSON.stringify($node["AI Agent"].json.items) }},
  "customer_name": {{ JSON.stringify($node["AI Agent"].json.customer_name) }},
  "notes": {{ JSON.stringify($node["AI Agent"].json.notes) }}
}
```

---

**Node 6 — Telegram (Kirim Konfirmasi ke Customer)**

| Setting | Value |
|---|---|
| Credential | Telegram credential |
| Operation | Send Message |
| Chat ID | `{{ $node["Telegram Trigger"].json.message.chat.id }}` |

**Message Text:**

```
✅ Order kamu masuk!

👤 Nama: {{ $node["HTTP Request1"].json.customer_name }}
🧾 Kode: {{ $node["HTTP Request1"].json.transaction_code }}
💰 Total: Rp {{ $node["HTTP Request1"].json.total_amount.toLocaleString("id-ID") }}

💳 Bayar sekarang (berlaku 30 menit):
{{ $node["HTTP Request1"].json.invoice_url }}

Setelah bayar, pesananmu langsung diproses! 🙏
```

---

### Struktur Workflow Lengkap

```
[Telegram Trigger]
      ↓
[HTTP Request — Get Products]
      ↓
[AI Agent — Parse Order]
      ↓
[IF — is_valid?]
      ↓ TRUE                    ↓ FALSE
[HTTP Request — Post Order]   [Telegram — Send Error]
      ↓
[Telegram — Send Confirmation]
```

---

### Step 15: Aktifkan Workflow

1. Di n8n, klik tombol **Activate** di pojok kanan atas workflow
2. Status harus berubah jadi **Active**
3. Test dengan kirim pesan ke bot: `"pesan 1 kopi susu buat Budi"`

---

### Checklist Akhir Hari 2

Test skenario berikut sebelum lanjut ke Hari 3:

```
✅ Kirim pesan order valid → bot balas dengan kode + link
✅ Kirim pesan bukan order → bot balas dengan pesan error
✅ Order muncul di dashboard owner dengan badge "Self Order"
✅ Stok berkurang sesuai qty yang dipesan
✅ Payment link dari Xendit bisa dibuka di browser
✅ Halaman Xendit menampilkan detail order yang benar
```

Jika semua ✅, Hari 2 selesai.

---

## HARI 3 — Stabilisasi + Polish Demo

### Step 16: Setup Midtrans Sandbox (Opsional)

> **Jalankan Step 16 HANYA jika Hari 1 dan Hari 2 sudah 100% stabil.**
> Kalau ada yang masih belum stabil, skip step ini dan fokus ke Step 17.

Webhook Xendit sudah disetup di Step 10. Untuk memastikan webhook bekerja di sandbox, test menggunakan Xendit Dashboard:

1. Login Xendit → **Settings → Webhooks**
2. Klik **Test Webhook** di samping URL yang sudah didaftarkan
3. Pilih event type: `invoice.paid`
4. Cek log Laravel untuk memastikan request masuk

```bash
# Di VPS, lihat log Laravel
tail -f /path/to/sapi/storage/logs/laravel.log
```

---

### Step 17: Tambahkan Badge "Self Order" di Dashboard

Buka `resources/js/Pages/Owner/Dashboard.vue`, di bagian list recent transactions, tambahkan indikator visual:

```vue
<!-- Di dalam loop recentTransactions -->
<div class="flex items-center gap-2">
  <span class="font-medium">{{ transaction.code }}</span>
  
  <!-- Badge Self Order -->
  <span 
    v-if="transaction.source === 'self_order'"
    class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium"
  >
    Self Order
  </span>
</div>
```

Pastikan `source` dikirim dari `DashboardController`. Buka `app/Http/Controllers/Owner/DashboardController.php`, update query `recentTransactions`:

```php
$recentTransactions = Transaction::with('user:id,name')
    ->where('status', Transaction::STATUS_COMPLETED)
    ->latest()
    ->take(5)
    ->get(['id', 'code', 'total_amount', 'user_id', 'created_at', 'source']); // ← tambah 'source'
```

---

### Step 18: Siapkan Data Demo yang Bagus

Pastikan data demo terlihat meyakinkan saat demo:

```bash
# Reset dan seed ulang data
php artisan migrate:fresh --seed

# Atau hanya seed tanpa reset
php artisan db:seed
```

Data yang harus ada sebelum demo:
- Minimal 5-6 produk dengan gambar dan kategori
- Beberapa varian per produk (Hot/Iced, Regular/Large)
- Badge stok kritis sudah muncul untuk 1-2 produk
- Satu sesi kas terbuka (buka kas dulu sebelum demo)

---

### Step 19: Test End-to-End 10x

Lakukan skenario ini minimal 10 kali sampai kamu bisa melakukannya tanpa berpikir:

```
1. Buka Telegram
2. Kirim: "pesan 2 kopi susu sama 1 americano buat Budi"
3. Bot balas dalam < 10 detik dengan kode + link
4. Buka link Xendit → tampil halaman bayar dengan QRIS
5. Buka dashboard owner → order "TRX-xxx" muncul dengan badge "Self Order"
6. Total waktu dari kirim pesan sampai order muncul di dashboard: < 15 detik
```

Catat jika ada yang error dan fix sebelum demo.

---

### Step 20: Script Demo 5 Menit

Latih narasi ini sampai natural:

**Menit 1 (Hook + Problem):**
> "Owner warung kopi di Makassar rata-rata baru tahu stoknya bermasalah setelah pelanggan komplain. Bukan karena mereka tidak mau tahu — tapi karena tidak ada sistem yang kasih tahu mereka secara otomatis. SAPI hadir untuk mengubah itu."

**Menit 2 (Demo POS):**
> "Ini tampilan kasir SAPI. Kasir buka sesi, pilih produk, proses pembayaran. Sederhana. Tapi yang menarik ada di sini..."
> *Switch ke owner dashboard — tunjukkan badge stok kritis muncul otomatis*
> "Badge ini muncul sendiri. Owner tidak perlu cek manual. SAPI yang kasih tahu."

**Menit 3-4 (Demo Self Order):**
> "Tapi SAPI tidak berhenti di kasir. Kami menambahkan channel order baru — langsung dari Telegram."
> *Ketik pesan order di Telegram live*
> "Customer pesan dari meja atau dari luar. AI kami parsing pesanannya, kirim ke sistem, generate payment link Xendit — semua dalam hitungan detik."
> *Tunjukkan invoice Xendit di browser*
> *Tunjukkan order muncul di dashboard*

**Menit 5 (Roadmap + Business Model):**
> "Ini bukan sekadar fitur keren. Ini data. Setiap transaksi masuk ke sistem kami — stock movements, pola penjualan, semua tercatat. Dalam 3 bulan, kami aktifkan ML layer — prediksi restock berbasis data bisnis mereka sendiri, bukan benchmark industri. SaaS multi-tenant, subscription bulanan. Ini SAPI."

---

## Troubleshooting

### Error: "No payment method cash found"

Artinya tenant tidak punya payment method cash aktif. Solusi:
```bash
php artisan tinker
\App\Models\PaymentMethod::create([
    'tenant_id' => 1,
    'name' => 'Cash',
    'type' => 'cash',
    'is_active' => true,
]);
```

---

### Error: "Unauthenticated" dari API

Token Sanctum tidak valid atau expired. Buat ulang:
```bash
php artisan tinker
$user = \App\Models\User::find(1);
$user->tokens()->delete(); // hapus semua token lama
$token = $user->createToken('n8n-self-order-bot')->plainTextToken;
echo $token;
```

---

### n8n AI tidak bisa parse order dengan benar

Kemungkinan penyebab:
1. Format JSON dari AI berisi markdown code block — tambahkan instruksi "Jangan gunakan markdown" ke prompt
2. `variant_id` tidak cocok — cek response `/api/products` apakah data produk terkirim ke AI
3. Model terlalu lambat — coba ganti ke model yang lebih cepat (gpt-4o-mini)

---

### Xendit Invoice gagal dibuat

Cek hal berikut:
1. Secret key benar dan mode sandbox aktif
2. `amount` minimal Rp 1.000 (Xendit reject di bawah ini)
3. `external_id` harus unik — transaction code SAPI sudah handle ini

---

### Webhook Xendit tidak diterima

1. Pastikan URL webhook accessible dari internet: `curl https://domain-kamu.com/api/xendit/webhook`
2. Pastikan `XENDIT_WEBHOOK_TOKEN` di `.env` sama persis dengan yang diisi di Xendit dashboard
3. Cek header yang dikirim Xendit: `x-callback-token` (lowercase semua)

---

## Catatan Penting untuk Demo

**Yang boleh kamu klaim:**
- SAPI menggunakan AI untuk parsing order natural language
- Self-order terintegrasi penuh dengan inventory management
- Payment via Xendit dengan QRIS dinamis
- Multi-channel order masuk ke satu dashboard

**Yang tidak boleh kamu overclaim:**
- Jangan bilang "AI kami buatan sendiri" — kamu pakai model third-party via n8n
- Jangan bilang "sudah production-ready untuk payment" — ini masih sandbox
- Jangan bilang "sudah ada 10 user" jika belum ada

**Jika judges tanya soal AI di Badge Helper:**
> "Badge Helper kami adalah intelligence layer pertama — rule-based yang divalidasi akurasinya dari data nyata. Begitu kami punya 3 bulan data transaksi dari tenant, kami aktifkan ML layer untuk prediksi demand forecasting. Data infrastructure-nya sudah siap dari hari pertama."

---

## Checklist Final Sebelum Demo

```
Infrastructure
[ ] VPS running, semua service aktif
[ ] n8n workflow aktif (status: Active)
[ ] Laravel API endpoint accessible

Fitur
[ ] GET /api/products mengembalikan data produk
[ ] POST /api/orders membuat transaksi + Xendit invoice
[ ] Telegram bot menerima dan membalas pesan
[ ] Order dari Telegram muncul di dashboard dengan badge "Self Order"
[ ] Badge Helper menampilkan minimal 1-2 badge

Demo Data
[ ] Minimal 5 produk dengan stok yang cukup
[ ] Satu sesi kas sudah dibuka
[ ] Ada sedikit transaksi historis agar dashboard tidak kosong

Presentasi
[ ] Slide pitch sudah siap (5 menit)
[ ] Demo script sudah dilatih minimal 5x
[ ] Koneksi internet demo day sudah dicek
[ ] Backup: video screen recording demo kalau internet bermasalah
```

---

*Dokumen ini adalah implementation guide untuk penambahan fitur Self Order pada SAPI versi Fase 1+.*  
*Fitur ini berada di luar Fase 1 original roadmap dan dibangun sebagai proof of concept untuk demo day.*  
*Setelah demo, evaluasi apakah fitur ini layak dimasukkan ke Fase 2 berdasarkan feedback judges.*
