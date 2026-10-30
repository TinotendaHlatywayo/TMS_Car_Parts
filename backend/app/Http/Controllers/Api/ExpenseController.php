<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\PaymentAccount;
use App\Support\MoneyMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private readonly MoneyMovementService $movements) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $expenses = $branch->expenses()
            ->with(['paymentAccount:id,name,type,currency_code', 'actor:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->query('category')))
            ->orderByDesc('incurred_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $expenses->getCollection()->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'category' => $expense->category,
                'description' => $expense->description,
                'amount_minor' => $expense->amount_minor,
                'currency_code' => $expense->currency_code,
                'incurred_date' => $expense->incurred_date?->toDateString(),
                'paid_date' => $expense->paid_date?->toDateString(),
                'status' => $expense->status,
                'payment_account_id' => $expense->payment_account_id,
                'attachment_path' => $expense->attachment_path,
                'actor' => $expense->actor ? ['id' => $expense->actor->id, 'name' => $expense->actor->name] : null,
                'created_at' => $expense->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:500'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3'],
            'incurred_date' => ['required', 'date'],
            'paid_date' => ['nullable', 'date'],
            'payment_account_id' => ['nullable', 'string'],
            'attachment_path' => ['nullable', 'string', 'max:500'],
        ]);

        $status = isset($data['paid_date']) ? 'paid' : 'incurred';

        $expense = $branch->expenses()->create([
            'category' => $data['category'],
            'description' => $data['description'],
            'amount_minor' => $data['amount_minor'],
            'currency_code' => $data['currency_code'],
            'incurred_date' => $data['incurred_date'],
            'paid_date' => $data['paid_date'] ?? null,
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'status' => $status,
            'attachment_path' => $data['attachment_path'] ?? null,
            'actor_id' => $request->user()->id,
        ]);

        if ($status === 'paid' && $expense->payment_account_id) {
            $account = PaymentAccount::where('branch_id', $branch->id)->whereKey($expense->payment_account_id)->firstOrFail();

            $this->movements->postMovement(
                $branch,
                $account,
                'adjustment_out',
                $expense->amount_minor,
                $expense->currency_code,
                null,
                'Expense: '.$expense->description,
                'expense',
                (string) $expense->id,
                $request->user(),
                'expense:'.$expense->id,
            );
        }

        return response()->json(['data' => ['id' => $expense->id, 'status' => $expense->status]], 201);
    }

    public function pay(Request $request, Branch $branch, Expense $expense): JsonResponse
    {
        $data = $request->validate([
            'payment_account_id' => ['required', 'string'],
            'paid_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        if ($expense->status === 'paid') {
            return response()->json(['data' => ['id' => $expense->id, 'status' => 'paid']]);
        }

        $account = PaymentAccount::where('branch_id', $branch->id)->whereKey($data['payment_account_id'])->firstOrFail();

        if ($account->currency_code !== $expense->currency_code) {
            return response()->json(['message' => 'Payment account currency must match the expense currency.'], 422);
        }

        $this->movements->postMovement(
            $branch,
            $account,
            'adjustment_out',
            $expense->amount_minor,
            $expense->currency_code,
            $data['reference'] ?? null,
            'Expense payment: '.$expense->description,
            'expense',
            (string) $expense->id,
            $request->user(),
            'expense-pay:'.$expense->id,
        );

        $expense->update([
            'status' => 'paid',
            'paid_date' => $data['paid_date'],
            'payment_account_id' => $account->id,
        ]);

        return response()->json(['data' => ['id' => $expense->id, 'status' => 'paid']]);
    }
}
