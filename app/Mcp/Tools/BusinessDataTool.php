<?php

namespace App\Mcp\Tools;

use App\Models\Tenant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Basis untuk tool MCP yang mengekspos data agregat satu tenant.
 *
 * Menyeragamkan: guard owner, aktivasi TenantScope (berbasis auth()), dan
 * pembungkusan hasil sebagai structured response. Subclass cukup mengisi
 * data() dengan data agregat (tanpa PII).
 */
abstract class BusinessDataTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user?->isOwner()) {
            return Response::error('Hanya owner yang dapat mengakses data bisnis MCP.');
        }

        // Services memakai TenantScope berbasis auth(); pastikan guard terisi
        // agar seluruh query ter-scope ke tenant pemilik token.
        Auth::setUser($user);

        return Response::structured($this->data($request, $user->tenant));
    }

    /**
     * Data agregat yang dikembalikan tool. HANYA agregat — tanpa PII.
     *
     * @return array<string, mixed>
     */
    abstract protected function data(Request $request, Tenant $tenant): array;

    /**
     * Validasi & resolusi rentang tanggal. Default: 30 hari terakhir.
     *
     * @return array{0: Carbon, 1: Carbon} [from, to]
     */
    protected function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ], [
            'to.after_or_equal' => 'Tanggal "to" harus sama dengan atau setelah "from".',
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->subDays(30)->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    /**
     * Skema input rentang tanggal yang dipakai bersama oleh tool berbasis periode.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function periodSchema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()
                ->description('Tanggal mulai (format YYYY-MM-DD). Default: 30 hari lalu.'),
            'to' => $schema->string()
                ->description('Tanggal akhir (format YYYY-MM-DD). Default: hari ini.'),
        ];
    }
}
