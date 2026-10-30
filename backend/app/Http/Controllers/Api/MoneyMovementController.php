<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Support\MoneyMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoneyMovementController extends Controller
{
    public function __construct(private readonly MoneyMovementService $movements) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $movements = $branch->moneyMovements()
            ->with(['account:id,name,type,currency_code', 'actor:id,name'])
            ->when($request->filled('payment_account_id'), fn ($query) => $query->where('payment_account_id', $request->query('payment_account_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('movement_type', $request->query('type')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $movements->getCollection()->map(fn (MoneyMovement $movement) => [
                'id' => $movement->id,
                'payment_account_id' => $movement->payment_account_id,
                'account' => $movement->account ? [
                    'id' => $movement->account->id,
                    'name' => $movement->account->name,
                    'type' => $movement->account->type,
                    'currency_code' => $movement->account->currency_code,
                ] : null,
                'movement_type' => $movement->movement_type,
                'amount_minor' => $movement->amount_minor,
                'currency_code' => $movement->currency_code,
                'direction' => $movement->direction,
                'source' => $movement->source,
                'source_id' => $movement->source_id,
                'reference' => $movement->reference,
                'reason' => $movement->reason,
                'related_movement_id' => $movement->related_movement_id,
                'transfer_group_id' => $movement->transfer_group_id,
                'reverses_movement_id' => $movement->reverses_movement_id,
                'actor' => $movement->actor ? ['id' => $movement->actor->id, 'name' => $movement->actor->name] : null,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    public function store(Request $request, Branch $branch, PaymentAccount $paymentAccount): JsonResponse
    {
        $data = $request->validate([
            'movement_type' => ['required', 'string', 'in:opening,adjustment_in,adjustment_out'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:40'],
            'source_id' => ['nullable', 'string', 'max:100'],
        ]);

        $movement = $this->movements->postMovement(
            $branch,
            $paymentAccount,
            $data['movement_type'],
            $data['amount_minor'],
            $data['currency_code'],
            $data['reference'] ?? null,
            $data['reason'],
            $data['source'] ?? null,
            $data['source_id'] ?? null,
            $request->user(),
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json(['data' => ['id' => $movement->id]], 201);
    }

    public function transfer(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'string'],
            'to_account_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $from = PaymentAccount::where('branch_id', $branch->id)->whereKey($data['from_account_id'])->firstOrFail();
        $to = PaymentAccount::where('branch_id', $branch->id)->whereKey($data['to_account_id'])->firstOrFail();

        $result = $this->movements->transfer(
            $branch,
            $from,
            $to,
            $data['amount_minor'],
            $data['currency_code'],
            $data['reason'],
            $data['reference'] ?? null,
            $request->user(),
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json([
            'data' => [
                'outgoing_id' => $result['outgoing']->id,
                'incoming_id' => $result['incoming']->id,
                'transfer_group_id' => $result['outgoing']->transfer_group_id,
            ],
        ], 201);
    }

    public function reverse(Request $request, Branch $branch, MoneyMovement $moneyMovement): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $movement = $this->movements->reverse(
            $branch,
            $moneyMovement,
            $data['reason'],
            $request->user(),
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json(['data' => ['id' => $movement->id]]);
    }
}
