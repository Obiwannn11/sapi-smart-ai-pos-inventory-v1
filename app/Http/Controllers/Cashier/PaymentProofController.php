<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Services\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unggahan foto bukti bayar non-tunai, satu langkah sebelum penjualannya
 * disimpan.
 *
 * Menjawab JSON, bukan redirect Inertia: pemanggilnya modal pembayaran yang
 * butuh TOKEN kembali untuk diselipkan ke payload checkout, bukan halaman baru.
 * Alasan alur dua langkahnya ada di PaymentProofService.
 */
class PaymentProofController extends Controller
{
    public function __construct(
        private readonly PaymentProofService $proofs,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        // Digerbang kapabilitas di sini juga, bukan hanya di UI. Endpoint yang
        // menerima berkas dari toko yang tidak menyalakan fiturnya adalah
        // tempat penyimpanan gratis untuk siapa pun yang punya akun kasir.
        abort_unless($tenant->hasFeature('payment_proof'), 403);

        // PDF sengaja TIDAK diterima di sini, berbeda dari bukti transfer
        // langganan. Ini foto yang diambil kasir dengan kamera di tempat, dan
        // menerima berkas yang tidak bisa dipratinjau berarti menerima berkas
        // yang tidak pernah bisa diperiksa siapa pun.
        $request->validate([
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'proof.required' => 'Belum ada foto yang dipilih.',
            'proof.image' => 'Berkas harus berupa gambar.',
            'proof.max' => 'Ukuran foto maksimal 8 MB.',
        ]);

        $token = $this->proofs->storePending(
            $request->file('proof'),
            $tenant->id,
        );

        return response()->json(['token' => $token]);
    }
}
