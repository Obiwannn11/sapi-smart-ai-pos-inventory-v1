<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MobileTenantController extends Controller
{
    public function profile(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        return response()->json([
            'data' => [
                'id'      => $tenant->id,
                'name'    => $tenant->name,
                'address' => $tenant->address,
                'phone'   => $tenant->phone,
            ],
        ]);
    }
}
