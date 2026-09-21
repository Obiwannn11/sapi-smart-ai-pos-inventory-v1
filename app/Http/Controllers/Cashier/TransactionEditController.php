<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\EditTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionEditService;
use Illuminate\Http\RedirectResponse;

class TransactionEditController extends Controller
{
    public function update(EditTransactionRequest $request, Transaction $transaction, TransactionEditService $service): RedirectResponse
    {
        try {
            $service->edit($transaction, $request->validated(), $request->user());

            return back()->with('success', "Transaksi {$transaction->code} berhasil diperbarui.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
