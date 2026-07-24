<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Halaman langganan sisi tenant.
 *
 * Sengaja terbuka untuk SEMUA pengguna tenant, bukan hanya owner: begitu
 * tenant ditangguhkan, setiap halaman lain mengarah ke sini. Kalau halaman ini
 * pun digerbang `role:owner`, kasir yang sedang bekerja akan mendarat di 403
 * tanpa penjelasan apa pun. Tindakan yang mengubah keadaan (persetujuan,
 * unggah bukti bayar) tetap milik owner — digerbang di rutenya sendiri.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request, SubscriptionService $subscriptions): Response
    {
        $tenant = $request->user()->tenant;
        $subscription = $subscriptions->ensureFor($tenant);
        $subscription->loadMissing('plan');

        return inertia('Billing/Show', [
            'tenant' => [
                'name' => $tenant->name,
                'status' => $tenant->status,
                'is_owner' => $request->user()->isOwner(),
            ],
            'subscription' => [
                'plan_name' => $subscription->plan->name,
                'base_price' => (float) $subscription->plan->base_price,
                'price_locked' => $subscription->price_locked === null
                    ? null
                    : (float) $subscription->price_locked,
                'pricing_track' => $subscription->pricing_track,
                'seats' => $subscription->seats,
                'seats_used' => $subscription->activeSeatsUsed(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'current_period_end' => $subscription->current_period_end?->toDateString(),
                // Tanggal penangguhan dihitung dan DITAMPILKAN, bukan disimpan
                // diam-diam. Tenant di masa tenggang berhak tahu persis kapan
                // pintunya tertutup, bukan sekadar bahwa ia akan tertutup.
                'suspends_at' => $tenant->status === Tenant::STATUS_GRACE
                    ? $subscription->current_period_end?->copy()
                        ->addDays(SubscriptionService::graceDays())
                        ->toDateString()
                    : null,
            ],
        ]);
    }
}
