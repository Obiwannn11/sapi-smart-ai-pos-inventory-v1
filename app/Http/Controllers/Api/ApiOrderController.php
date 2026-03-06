<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Transaction;
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

    /**
     * Buat self-order baru.
     * Flow: simpan transaksi (pending, stok BELUM dikurangi) → buat Xendit invoice → return link.
     * Stok baru dikurangi setelah Xendit webhook confirm payment.
     */
    public function store(Request $request): JsonResponse
    {
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
            'order_type'                      => 'nullable|in:dine_in,pickup',
            'table_number'                    => 'nullable|string|max:10',
        ]);

        try {
            // 1. Buat transaksi self-order (TANPA deduct stok)
            $transaction = $this->transactionService->createSelfOrder($validated);

            // 2. Generate Xendit Invoice
            Configuration::setXenditKey(config('services.xendit.secret_key'));
            $invoiceApi = new InvoiceApi();

            $invoiceRequest = new CreateInvoiceRequest([
                'external_id'      => $transaction->code,
                'amount'           => (int) $transaction->total_amount,
                'payer_email'      => 'customer@sapi.app',
                'description'      => 'Order ' . $transaction->code . ' via SAPI Self Order',
                'currency'         => 'IDR',
                'invoice_duration' => 1800, // 30 menit
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
                // Semua payment method — cash pickup juga bisa via Xendit QRIS
                'payment_methods' => ['QRIS', 'OVO', 'DANA', 'BNI', 'BRI', 'MANDIRI'],
            ]);

            $invoice = $invoiceApi->createInvoice($invoiceRequest);

            // 3. Return data untuk n8n
            return response()->json([
                'success'          => true,
                'transaction_code' => $transaction->code,
                'total_amount'     => (int) $transaction->total_amount,
                'invoice_url'      => $invoice['invoice_url'],
                'invoice_id'       => $invoice['id'],
                'customer_name'    => $validated['customer_name'] ?? 'Customer',
                'order_type'       => $transaction->order_type,
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
     */
    public function updateFulfillment(Request $request, Transaction $transaction): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user || $transaction->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$transaction->hasFulfillmentTracking()) {
            return response()->json(['message' => 'Transaksi ini tidak memiliki fulfillment tracking.'], 422);
        }

        if ($transaction->fulfillment_status === Transaction::FULFILLMENT_DONE) {
            return response()->json(['message' => 'Pesanan sudah selesai.'], 422);
        }

        $transaction->advanceFulfillment();

        return response()->json([
            'success'            => true,
            'fulfillment_status' => $transaction->fulfillment_status,
            'message'            => 'Status diupdate ke: ' . $transaction->fulfillment_status,
        ]);
    }
}
