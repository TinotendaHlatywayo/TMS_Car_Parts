<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PaymentAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentAccountController extends Controller
{
    public function index(Request $request, Branch $branch): JsonResponse
    {
        $accounts = $branch->paymentAccounts()
            ->when($request->query('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $accounts->map(fn (PaymentAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency_code' => $account->currency_code,
                'is_active' => $account->is_active,
                'created_at' => $account->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:cash,bank,mobile_money,other'],
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        $account = $branch->paymentAccounts()->create($data);

        return response()->json(['data' => [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type,
            'currency_code' => $account->currency_code,
            'is_active' => $account->is_active,
        ]], 201);
    }

    public function update(Request $request, Branch $branch, PaymentAccount $paymentAccount): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $paymentAccount->update($data);

        return response()->json(['data' => [
            'id' => $paymentAccount->id,
            'name' => $paymentAccount->name,
            'type' => $paymentAccount->type,
            'currency_code' => $paymentAccount->currency_code,
            'is_active' => $paymentAccount->is_active,
        ]]);
    }
}
