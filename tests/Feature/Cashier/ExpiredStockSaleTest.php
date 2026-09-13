<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * Penjaga celah `[BL-108]` — barang yang SUDAH lewat `expiry_date` bisa
 * masuk keranjang dan lolos checkout pada harga penuh, tanpa peringatan dan
 * tanpa konfirmasi apa pun. `TransactionService::checkout()` (dan
 * `commitOffline()`) sama sekali tidak memeriksa `expiry_date` — satu-satunya
 * hal yang diperiksa `DiscountService::isDiscountable()` adalah HARGANYA,
 * bukan BOLEH TIDAKNYA dijual.
 *
 * Pemilik sudah memutuskan bentuk perbaikannya (2026-09-08): opsi (b) —
 * izinkan jual, tapi wajib ada konfirmasi eksplisit yang tercatat sebagai
 * kolom snapshot di `transaction_items`. Siapa yang boleh mengonfirmasi
 * masih pertanyaan terbuka (lihat `[BL-108]` di docs/BACKLOG.md).
 */
function makeExpiredSaleContext(): array
{
    $tenant = Tenant::factory()->create();

    $cashier = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
    ]);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 10,
        'expiry_date' => now()->subDays(57)->toDateString(),
    ]);

    $paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'cash',
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    return compact('tenant', 'cashier', 'variant', 'paymentMethod');
}

test('barang yang sudah kedaluwarsa 57 hari tetap lolos checkout tanpa peringatan (mendokumentasikan celah BL-108)', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makeExpiredSaleContext();

    actingAs($cashier);

    // Ini adalah reproduksi TRX-20260908-001 dari [BL-108]: varian yang
    // expiry_date-nya sudah lewat dua bulan, dijual tanpa hambatan apa pun.
    post('/cashier/transactions', [
        'items' => [
            [
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => $variant->price,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $paymentMethod->id,
                'amount' => $variant->price,
            ],
        ],
    ])->assertSessionHas('success');

    // Kalau assert di atas gagal, [BL-108] mungkin sudah dikerjakan — hapus
    // uji ini (ia sengaja mendokumentasikan bug, bukan perilaku yang
    // diinginkan) dan aktifkan uji "confirmation" yang di-skip di bawah.

    // Terjual pada HARGA PENUH — bukan didiskon, karena isDiscountable()
    // justru MENOLAK mendiskon barang kedaluwarsa. Ironi persis seperti yang
    // dicatat [BL-108]: satu-satunya perlakuan khusus barang basi hari ini
    // adalah "dilarang murah", bukan "dilarang dijual".
    expect(Transaction::query()->where([
        'tenant_id' => $tenant->id,
        'status' => 'completed',
        'total_amount' => $variant->price,
    ])->exists())->toBeTrue();

    // Stok tetap berkurang seperti penjualan normal — tidak ada jejak sama
    // sekali bahwa barang yang baru terjual itu sudah basi 57 hari.
    expect($variant->fresh()->stock)->toBe(9);
});

test('checkout wajib menolak barang kedaluwarsa tanpa konfirmasi eksplisit', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makeExpiredSaleContext();

    actingAs($cashier);

    // Perilaku yang DIINGINKAN pemilik (opsi b, [BL-108]): checkout barang
    // kedaluwarsa TANPA field konfirmasi harus ditolak server — bukan
    // diam-diam diterima seperti uji di atas. Field konfirmasinya sendiri
    // (nama, bentuk validasi) belum diputuskan, jadi uji ini masih di-skip
    // sampai [BL-108] diimplementasikan dan bentuk konfirmasinya jelas.
    post('/cashier/transactions', [
        'items' => [
            [
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => $variant->price,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $paymentMethod->id,
                'amount' => $variant->price,
            ],
        ],
    ])->assertSessionHas('error');

    expect($variant->fresh()->stock)->toBe(10);
})->skip('[BL-108] belum diimplementasikan — gerbang konfirmasi barang kedaluwarsa belum ada di TransactionService::checkout(). Aktifkan uji ini begitu gerbangnya dibangun.');
