<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

class MobileTransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'items'                        => 'required|array|min:1',
            'items.*.variant_id'           => [
                'required',
                Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.variant_name'         => 'required|string|max:255',
            'items.*.qty'                  => 'required|integer|min:1',
            'items.*.notes'                => 'nullable|string|max:500',
            'items.*.modifiers'            => 'nullable|array',
            'items.*.modifiers.*.id'       => [
                'required',
                Rule::exists('modifiers', 'id')->where('tenant_id', $tenantId),
            ],

            'is_open_bill'   => 'nullable|boolean',
            'order_type'     => 'nullable|in:dine_in,takeaway',
            'customer_name'  => 'nullable|string|max:255',
            'table_number'   => 'nullable|string|max:50',
            'notes'          => 'nullable|string|max:500',

            'payments'                       => 'required_unless:is_open_bill,true|array|min:1',
            'payments.*.payment_method_id'   => [
                'required',
                Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId),
            ],
            'payments.*.amount'              => 'required|numeric|min:0',
            'payments.*.reference_code'      => 'nullable|string|max:255',
        ]);

        try {
            $transaction = $this->transactionService->checkout($validated);

            return response()->json([
                'message'        => 'Transaksi berhasil.',
                'transaction_id' => $transaction->id,
                'code'           => $transaction->code,
                'total_amount'   => $transaction->total_amount,
                'change_amount'  => $transaction->change_amount,
                'status'         => $transaction->status,
                'is_open_bill'   => $transaction->status === Transaction::STATUS_PENDING,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Mobile checkout failed', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Transaction::where('tenant_id', $user->tenant_id)
            ->with(['user:id,name', 'payments.paymentMethod'])
            ->latest();

        if ($user->isCashier()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $paginated = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($paginated->items())->map(fn($t) => [
                'id'            => $t->id,
                'code'          => $t->code,
                'cashier'       => $t->user->name,
                'total_amount'  => $t->total_amount,
                'status'        => $t->status,
                'order_type'    => $t->order_type,
                'customer_name' => $t->customer_name,
                'table_number'  => $t->table_number,
                'created_at'    => $t->created_at->format('d/m/Y H:i'),
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function pay(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($transaction->status !== Transaction::STATUS_PENDING) {
            return response()->json(['message' => 'Transaksi ini bukan open bill yang bisa dibayar.'], 422);
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'payments'                     => 'required|array|min:1',
            'payments.*.payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId),
            ],
            'payments.*.amount'            => 'required|numeric|min:0',
            'payments.*.reference_code'    => 'nullable|string|max:255',
        ]);

        try {
            $transaction = $this->transactionService->payOpenBill($transaction, $validated['payments']);

            return response()->json([
                'message'        => 'Open bill berhasil dibayar.',
                'transaction_id' => $transaction->id,
                'code'           => $transaction->code,
                'total_amount'   => $transaction->total_amount,
                'change_amount'  => $transaction->change_amount,
                'status'         => $transaction->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile pay open bill failed', [
                'user_id'        => auth()->id(),
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function void(Transaction $transaction): JsonResponse
    {
        if ($transaction->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $transaction = $this->transactionService->void($transaction);

            return response()->json([
                'message'        => 'Transaksi berhasil di-void.',
                'transaction_id' => $transaction->id,
                'code'           => $transaction->code,
                'status'         => $transaction->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile void failed', [
                'user_id'        => auth()->id(),
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function receipt(Transaction $transaction): JsonResponse
    {
        // Tenant isolation — belt-and-suspenders selain BelongsToTenant global scope
        if ($transaction->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $transaction->load(['items.modifiers', 'payments.paymentMethod', 'user:id,name']);
        $tenant = auth()->user()->tenant;

        return response()->json([
            'data' => [
                'tenant' => [
                    'name'    => $tenant->name,
                    'address' => $tenant->address,
                    'phone'   => $tenant->phone,
                ],
                'transaction' => [
                    'code'          => $transaction->code,
                    'date'          => $transaction->created_at->format('d/m/Y H:i'),
                    'cashier'       => $transaction->user->name,
                    'total_amount'  => $transaction->total_amount,
                    'change_amount' => $transaction->change_amount,
                    'status'        => $transaction->status,
                    'order_type'    => $transaction->order_type,
                    'customer_name' => $transaction->customer_name,
                    'table_number'  => $transaction->table_number,
                    'notes'         => $transaction->notes,
                    'is_open_bill'  => $transaction->status === Transaction::STATUS_PENDING,
                ],
                'items'    => $transaction->items->map(fn($item) => [
                    'name'      => $item->variant_name,
                    'qty'       => $item->qty,
                    'price'     => $item->unit_price,
                    'subtotal'  => $item->subtotal,
                    'notes'     => $item->notes,
                    'modifiers' => $item->modifiers->map(fn($m) => [
                        'name'        => $m->modifier_name,
                        'extra_price' => $m->extra_price,
                    ]),
                ]),
                'payments' => $transaction->payments->map(fn($p) => [
                    'method'         => $p->paymentMethod->name,
                    'amount'         => $p->amount,
                    'reference_code' => $p->reference_code,
                ]),
            ],
        ]);
    }
}
