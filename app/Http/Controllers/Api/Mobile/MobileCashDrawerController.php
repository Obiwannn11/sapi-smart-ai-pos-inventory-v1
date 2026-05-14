<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CashDrawer;
use Illuminate\Http\JsonResponse;

class MobileCashDrawerController extends Controller
{
    public function status(): JsonResponse
    {
        $drawer = CashDrawer::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        return response()->json([
            'is_open'   => (bool) $drawer,
            'drawer_id' => $drawer?->id,
            'opened_at' => $drawer?->opened_at,
        ]);
    }
}
