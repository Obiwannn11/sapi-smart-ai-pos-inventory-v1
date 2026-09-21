<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BusinessClock;
use App\Services\ConnectorSummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConnectorSummaryController extends Controller
{
    /**
     * Paket data toko untuk AI pengguna (`[BL-102]`).
     *
     * Header cache dan pengindeksan dipasang ConnectorPlainTextResponses, bukan
     * di sini, supaya penolakan dari gerbang ikut membawanya.
     */
    public function __invoke(Request $request, ConnectorSummaryService $summary): Response
    {
        return response(
            $summary->toMarkdown($request->user()->tenant, BusinessClock::now()),
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
