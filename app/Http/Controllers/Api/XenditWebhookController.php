<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XenditWebhookController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    /**
     * Handle webhook dari Xendit.
     * Dipanggil otomatis oleh Xendit setiap ada perubahan status invoice.
     *
     * Status yang di-handle:
     * - PAID    → konfirmasi pembayaran, deduct stok, aktifkan fulfillment
     * - EXPIRED → void transaksi (stok tidak perlu di-restore karena belum dikurangi)
     */
    public function handle(Request $request): JsonResponse
    {
        // Verifikasi callback token dari Xendit
        $callbackToken = $request->header('x-callback-token');

        if ($callbackToken !== config('services.xendit.webhook_token')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payload = $request->all();
        $status = $payload['status'] ?? '';
        $externalId = $payload['external_id'] ?? '';
        $invoiceId = $payload['id'] ?? '';

        if (!$externalId) {
            return response()->json(['message' => 'Missing external_id'], 400);
        }

        $transaction = Transaction::where('code', $externalId)->first();

        if (!$transaction) {
            // Transaksi tidak ditemukan — mungkin dari tenant lain atau data lama
            return response()->json(['message' => 'Transaction not found, ignored'], 200);
        }

        try {
            match ($status) {
                'PAID' => $this->handlePaid($transaction, $invoiceId),
                'EXPIRED' => $this->handleExpired($transaction),
                default => null, // Status lain (PENDING, etc.) diabaikan
            };
        } catch (\Exception $e) {
            // Log error tapi tetap return 200 agar Xendit tidak retry
            \Illuminate\Support\Facades\Log::error('Xendit webhook error', [
                'external_id' => $externalId,
                'status'      => $status,
                'error'       => $e->getMessage(),
            ]);
        }

        // Selalu return 200 ke Xendit agar tidak retry
        return response()->json(['message' => 'OK']);
    }

    /**
     * Handle invoice PAID — konfirmasi pembayaran + deduct stok + aktifkan fulfillment.
     */
    private function handlePaid(Transaction $transaction, string $invoiceId): void
    {
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            return; // Sudah diproses sebelumnya, skip
        }

        $this->transactionService->confirmSelfOrderPayment($transaction, $invoiceId);
    }

    /**
     * Handle invoice EXPIRED — void transaksi.
     * Stok tidak perlu di-restore karena self-order belum deduct stok sebelum bayar.
     */
    private function handleExpired(Transaction $transaction): void
    {
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            return; // Sudah diproses sebelumnya, skip
        }

        $this->transactionService->voidExpiredSelfOrder($transaction);
    }
}
