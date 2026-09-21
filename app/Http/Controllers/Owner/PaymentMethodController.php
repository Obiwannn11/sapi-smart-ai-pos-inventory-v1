<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Owner/PaymentMethods/Index', [
            // Ditunda ([BL-037]): daftarnya menyusul sementara tombol tambah dan
            // formulirnya sudah bisa dipakai sejak cat pertama.
            'paymentMethods' => Inertia::defer(fn () => PaymentMethod::latest()->get()),
        ]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        PaymentMethod::create($request->validated());

        return back()->with('success', 'Metode pembayaran berhasil ditambahkan.');
    }

    public function update(StorePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($request->validated());

        return back()->with('success', 'Metode pembayaran berhasil diperbarui.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete(); // soft delete

        return back()->with('success', 'Metode pembayaran berhasil dihapus.');
    }
}
