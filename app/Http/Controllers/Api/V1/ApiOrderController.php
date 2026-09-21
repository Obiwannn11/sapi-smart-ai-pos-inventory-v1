<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Services\FulfillmentService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;

class ApiOrderController extends Controller
{
    public function __construct(
        private TransactionService $transactionService,
        private FulfillmentService $fulfillment,
    ) {}

    /**
     * Buat self-order baru.
     * Flow: simpan transaksi (pending, stok BELUM dikurangi) → buat Xendit invoice → return link.
     * Stok baru dikurangi setelah Xendit webhook confirm payment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.variant_name' => 'required|string|max:255',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*.id' => 'required|exists:modifiers,id',
            'items.*.modifiers.*.name' => 'required|string',
            'items.*.modifiers.*.extra_price' => 'required|numeric|min:0',
            'customer_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'order_type' => 'nullable|in:dine_in,pickup',
            'table_number' => 'nullable|string|max:10',

            // Nasib saran upsell yang ditampilkan ke pelanggan. Kepemilikan
            // tenant diperiksa di UpsellEventRecorder, yang menolkan FK asing
            // alih-alih menolak pesanan — statistik tidak boleh punya kuasa
            // menggagalkan order yang sah.
            'upsell_events' => 'nullable|array|max:20',
            'upsell_events.*.type' => 'required|string|max:30',
            'upsell_events.*.status' => 'required|string|max:20',
            'upsell_events.*.reason' => 'nullable|string|max:50',
            'upsell_events.*.label' => 'required|string|max:255',
            'upsell_events.*.extra_amount' => 'nullable|numeric|min:0',
            'upsell_events.*.trigger_variant_id' => 'nullable|integer',
            'upsell_events.*.suggested_variant_id' => 'nullable|integer',
            'upsell_events.*.suggested_modifier_id' => 'nullable|integer',
        ]);

        try {
            // 1. Buat transaksi self-order (TANPA deduct stok)
            $transaction = $this->transactionService->createSelfOrder($validated);

            // 2. Generate Xendit Invoice
            Configuration::setXenditKey(config('services.xendit.secret_key'));
            $invoiceApi = new InvoiceApi;

            $invoiceRequest = new CreateInvoiceRequest([
                'external_id' => $transaction->code,
                'amount' => (int) $transaction->total_amount,
                'payer_email' => 'customer@sapi.app',
                'description' => 'Order '.$transaction->code.' via SAPI Self Order',
                'currency' => 'IDR',
                'invoice_duration' => 1800, // 30 menit
                'customer' => [
                    'given_names' => $validated['customer_name'] ?? 'Customer',
                ],
                'items' => collect($validated['items'])->map(function ($item) {
                    $variant = ProductVariant::with('product')->find($item['variant_id']);

                    return [
                        'name' => $variant->product->name.' — '.$item['variant_name'],
                        'quantity' => $item['qty'],
                        'price' => (int) $variant->price,
                    ];
                })->toArray(),
                // Semua payment method — cash pickup juga bisa via Xendit QRIS
                'payment_methods' => ['QRIS', 'OVO', 'DANA', 'BNI', 'BRI', 'MANDIRI'],
            ]);

            $invoice = $invoiceApi->createInvoice($invoiceRequest);

            // 3. Return data untuk n8n
            return response()->json([
                'success' => true,
                'transaction_code' => $transaction->code,
                'total_amount' => (int) $transaction->total_amount,
                'invoice_url' => $invoice['invoice_url'],
                'invoice_id' => $invoice['id'],
                'customer_name' => $validated['customer_name'] ?? 'Customer',
                'order_type' => $transaction->order_type,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Advance fulfillment status ke step berikutnya.
     * Kasir/owner klik tombol → waiting → preparing → ready → done
     *
     * `expected_from` adalah perubahan kontrak yang disengaja: klien mana pun
     * — n8n, aplikasi kasir, papan web — bisa memegang status basi, dan tanpa
     * menyebutkan status yang diharapkannya satu permintaan bisa melompati
     * langkah. Ia opsional demi konsumen lama, tapi memakainya jauh lebih aman.
     */
    public function updateFulfillment(Request $request, Transaction $transaction): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user || $transaction->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'expected_from' => 'nullable|in:waiting,preparing,ready',
        ]);

        try {
            $this->fulfillment->advance(
                $transaction,
                $validated['expected_from'] ?? $transaction->fulfillment_status ?? '',
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'fulfillment_status' => $transaction->fulfillment_status,
            'message' => 'Status diupdate ke: '.$transaction->fulfillment_status,
        ]);
    }
}
