<?php

namespace App\Mcp\Tools;

use App\Models\Tenant;
use App\Services\AiContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Ringkasan penjualan tenant pada rentang tanggal: revenue, jumlah transaksi, rata-rata nota, produk terlaris, dan tren harian. Semua angka teragregasi (tanpa data pelanggan).')]
class GetSalesSummaryTool extends BusinessDataTool
{
    public function __construct(private AiContextService $context) {}

    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request, Tenant $tenant): array
    {
        [$from, $to] = $this->period($request);

        $context = $this->context->buildContext($tenant, $from, $to);

        return [
            'period' => $context['business']['period'],
            'sales' => $context['sales'],
            'top_products' => $context['top_products'],
            'daily_trend' => $context['daily_trend'],
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema);
    }
}
