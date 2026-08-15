<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Resources\QueueCardResource;
use App\Models\Transaction;
use App\Services\FulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    public function __construct(private FulfillmentService $fulfillment) {}

    public function index(): Response
    {
        return Inertia::render('Cashier/Queue', [
            // Ditunda ([BL-037]): papan beserta item tiap pesanannya menyusul,
            // supaya rangka halaman dan peringatan offline muncul lebih dulu.
            // Polling papan memakai `router.reload({ only: ['queue'] })` —
            // permintaan parsial yang menyebut propnya tetap menyelesaikan
            // prop yang ditunda, jadi kerangkanya hanya tampil di pemuatan
            // pertama dan tidak berkedip tiap tujuh detik.
            //
            // resolve(): papan tidak dipaginasi, jadi pembungkus `data` bawaan
            // resource collection hanya menambah satu lapis tanpa guna di sisi
            // Vue — dan membuat `queue` terbaca sebagai objek, bukan daftar.
            'queue' => Inertia::defer(fn () => QueueCardResource::collection($this->board())->resolve()),
        ]);
    }

    public function advance(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeTenant($transaction);

        // `expected_from` wajib di permukaan web: papan di-poll, jadi kartu
        // basi adalah keadaan normal, bukan kemungkinan.
        $validated = $request->validate([
            'expected_from' => 'required|in:waiting,preparing,ready',
        ]);

        try {
            $this->fulfillment->advance($transaction, $validated['expected_from']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function moveToTop(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTenant($transaction);
        $this->fulfillment->moveToTop($transaction);

        return back();
    }

    public function moveUp(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTenant($transaction);
        $this->fulfillment->moveUp($transaction);

        return back();
    }

    public function moveDown(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTenant($transaction);
        $this->fulfillment->moveDown($transaction);

        return back();
    }

    /**
     * Kartu yang hidup di papan hari ini, terurut.
     *
     * `id` sebagai pemecah seri bukan hiasan: `sort_index` lahir dari cap waktu
     * milidetik dan dua pesanan bisa jatuh di milidetik yang sama.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    private function board()
    {
        return Transaction::whereIn('fulfillment_status', [
            Transaction::FULFILLMENT_WAITING,
            Transaction::FULFILLMENT_PREPARING,
            Transaction::FULFILLMENT_READY,
        ])
            ->where('status', '!=', Transaction::STATUS_VOIDED)
            ->whereEffectiveDate(now())
            ->with(['items.modifiers'])
            ->orderBy('sort_index')
            ->orderBy('id')
            ->get();
    }

    /**
     * Scoping ganda: query papan sudah ter-scope otomatis lewat BelongsToTenant,
     * dan rute ber-parameter tetap memeriksa tenant_id eksplisit — pola yang
     * sudah dipakai POSController.
     */
    private function authorizeTenant(Transaction $transaction): void
    {
        if ($transaction->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }
    }
}
