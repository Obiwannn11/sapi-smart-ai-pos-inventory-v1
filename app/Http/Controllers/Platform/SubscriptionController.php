<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\SubscriptionResource;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
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
    public function index(Request $request): Response
    {
        $subscriptions = Subscription::query()
            ->with(['tenant:id,name,status', 'plan:id,name'])
            ->join('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')
            ->orderBy('tenants.name')
            ->select('subscriptions.*')
            ->paginate(25)
            ->withQueryString();

        PlatformAuditLog::recordRoutine('subscriptions.index');

        return Inertia::render('Platform/Subscriptions/Index', [
            'subscriptions' => SubscriptionResource::collection($subscriptions),
        ]);
    }
}
