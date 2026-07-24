<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\SubscriptionResource;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar langganan seluruh tenant.
 *
 * Hanya keterangan komersial — paket, tarif, seat, periode. Model operasional
 * tenant TIDAK boleh diimpor di sini; ditegakkan PlatformArchTest.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request, PricingService $pricing): Response
    {
        $subscriptions = Subscription::query()
            ->with(['tenant:id,name,status', 'plan:id,name'])
            ->join('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')
            ->orderBy('tenants.name')
            ->select('subscriptions.*')
            ->paginate(25)
            ->withQueryString();

        PlatformAuditLog::recordRoutine('subscriptions.index');

        // Kelompok harga (bukan angka rupiah) hanya dihitung bila pengguna
        // memang berizin melihat data omzet. Untuk yang tidak, kuncinya tidak
        // ada sama sekali di payload — bukan ada tapi kosong.
        $brackets = $request->user()->hasModule('revenue_data')
            ? $this->bracketsFor($subscriptions->getCollection(), $pricing)
            : [];

        return Inertia::render('Platform/Subscriptions/Index', [
            'subscriptions' => SubscriptionResource::collection($subscriptions),
            'brackets' => $brackets,
            'can_view_revenue' => $request->user()->hasModule('revenue_data'),
        ]);
    }

    /**
     * Kelompok harga per tenant subsidi — LABEL saja, tanpa angka rupiah.
     *
     * Membuka daftar ini tidak dicatat sebagai kejadian sensitif justru karena
     * angkanya tidak ikut terkirim. Yang tercatat adalah membuka rinciannya,
     * di RevenueController.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Subscription>  $subscriptions
     * @return array<int, string>
     */
    private function bracketsFor($subscriptions, PricingService $pricing): array
    {
        return $subscriptions
            ->filter(fn (Subscription $subscription) => $subscription->isSubsidized())
            ->mapWithKeys(function (Subscription $subscription) use ($pricing) {
                $bracket = $pricing->currentBracketFor($subscription->tenant);

                return [$subscription->tenant_id => $bracket['label'] ?? '—'];
            })
            ->all();
    }
}
