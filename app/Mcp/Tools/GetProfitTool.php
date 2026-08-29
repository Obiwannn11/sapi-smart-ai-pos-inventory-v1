<?php

namespace App\Mcp\Tools;

use App\Models\Tenant;
use App\Services\ProfitService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Ringkasan profit tenant pada rentang tanggal: revenue (dibayar pelanggan, termasuk pajak), net_revenue (pendapatan toko), pajak terpungut, COGS, gross profit, margin, proyeksi periode berikutnya, dan margin per item. Margin dihitung dari net_revenue, bukan revenue. Semua angka teragregasi (tanpa data pelanggan).')]
class GetProfitTool extends BusinessDataTool
{
    public function __construct(private ProfitService $profit) {}

    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request, Tenant $tenant): array
    {
        [$from, $to] = $this->period($request);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'profit' => $this->profit->overallProfit($from, $to),
            'projection' => $this->profit->projection($from, $to),
            'profit_by_item' => $this->profit->profitByProduct($from, $to),
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
