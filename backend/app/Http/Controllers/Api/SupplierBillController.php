<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PaymentAccount;
use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Support\MoneyMovementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierBillController extends Controller
{
    public function __construct(private readonly MoneyMovementService $movements) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $bills = $branch->supplierBills()
            ->with('payments')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('due_date')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $bills->getCollection()->map(fn (SupplierBill $bill) => [
                'id' => $bill->id,
                'supplier_name' => $bill->supplier_name,
                'bill_number' => $bill->bill_number,
                'amount_minor' => $bill->amount_minor,
                'currency_code' => $bill->currency_code,
                'bill_date' => $bill->bill_date?->toDateString(),
                'due_date' => $bill->due_date?->toDateString(),
                'paid_minor' => $bill->paid_minor,
                'status' => $bill->status,
                'created_at' => $bill->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $bills->currentPage(),
                'last_page' => $bills->lastPage(),
                'per_page' => $bills->perPage(),
                'total' => $bills->total(),
            ],
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'supplier_name' => ['required', 'string', 'max:180'],
            'bill_number' => ['required', 'string', 'max:80'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
        ]);

        $bill = $branch->supplierBills()->create([
            ...$data,
            'paid_minor' => 0,
            'status' => 'open',
            'actor_id' => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $bill->id, 'status' => $bill->status]], 201);
    }

    public function pay(Request $request, Branch $branch, SupplierBill $supplierBill): JsonResponse
    {
        $data = $request->validate([
            'payment_account_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $account = PaymentAccount::where('branch_id', $branch->id)->whereKey($data['payment_account_id'])->firstOrFail();

        $bill = $supplierBill;

        if ($account->currency_code !== $bill->currency_code) {
            return response()->json(['message' => 'Payment account currency must match the bill currency.'], 422);
        }

        $remaining = $bill->amount_minor - $bill->paid_minor;

        if ($data['amount_minor'] > $remaining) {
            return response()->json(['message' => 'Payment amount exceeds the outstanding bill balance.'], 422);
        }

        $this->movements->postMovement(
            $branch,
            $account,
            'adjustment_out',
            $data['amount_minor'],
            $bill->currency_code,
            $data['reference'] ?? null,
            'Supplier bill payment: '.$bill->supplier_name.' '.$bill->bill_number,
            'supplier_bill',
            (string) $bill->id,
            $request->user(),
            'supplier-bill-pay:'.$bill->id.':'.$data['amount_minor'],
        );

        SupplierBillPayment::create([
            'branch_id' => $branch->id,
            'supplier_bill_id' => $bill->id,
            'payment_account_id' => $account->id,
            'amount_minor' => $data['amount_minor'],
            'currency_code' => $bill->currency_code,
            'paid_at' => $data['paid_at'],
            'reference' => $data['reference'] ?? null,
            'actor_id' => $request->user()->id,
        ]);

        $bill->paid_minor += $data['amount_minor'];
        $bill->status = $bill->paid_minor >= $bill->amount_minor ? 'paid' : 'partial';
        $bill->save();

        return response()->json(['data' => ['id' => $bill->id, 'status' => $bill->status, 'paid_minor' => $bill->paid_minor]]);
    }

    public function ageing(Request $request, Branch $branch): JsonResponse
    {
        $asOf = $request->query('as_of', now()->toDateString());

        $bills = $branch->supplierBills()
            ->where('status', '!=', 'paid')
            ->get();

        $buckets = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_over_90' => 0,
        ];

        foreach ($bills as $bill) {
            $outstanding = $bill->amount_minor - $bill->paid_minor;
            $days = (int) Carbon::parse($asOf)->diffInDays($bill->due_date, false) * -1;

            if ($days <= 0) {
                $buckets['current'] += $outstanding;
            } elseif ($days <= 30) {
                $buckets['days_1_30'] += $outstanding;
            } elseif ($days <= 60) {
                $buckets['days_31_60'] += $outstanding;
            } elseif ($days <= 90) {
                $buckets['days_61_90'] += $outstanding;
            } else {
                $buckets['days_over_90'] += $outstanding;
            }
        }

        return response()->json([
            'data' => $buckets,
            'as_of' => $asOf,
        ]);
    }
}
