<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Branch;
use App\Models\Sale;
use App\Support\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function store(CheckoutRequest $request, Branch $branch): JsonResponse
    {
        $sale = $this->checkout->checkout(
            $branch,
            $request->user(),
            $request->validated(),
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json([
            'data' => $this->payload($sale),
        ], 201);
    }

    public function show(Branch $branch, Sale $sale): JsonResponse
    {
        $sale->load(['customer', 'items.product', 'items.unit', 'payments', 'receipt']);

        return response()->json(['data' => $this->payload($sale)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'status' => $sale->status,
            'payment_status' => $sale->payment_status,
            'currency_code' => $sale->currency_code,
            'subtotal_minor' => $sale->subtotal_minor,
            'discount_minor' => $sale->discount_minor,
            'tax_minor' => $sale->tax_minor,
            'total_minor' => $sale->total_minor,
            'paid_minor' => $sale->paid_minor,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
            ] : null,
            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'unit_id' => $item->unit_id,
                'unit_code' => $item->unit_code,
                'quantity' => (string) $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'discount_minor' => $item->discount_minor,
                'tax_minor' => $item->tax_minor,
                'total_minor' => $item->total_minor,
                'currency_code' => $item->currency_code,
            ])->all(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount_minor' => $payment->amount_minor,
                'currency_code' => $payment->currency_code,
                'reference' => $payment->reference,
                'status' => $payment->status,
                'created_at' => $payment->created_at?->toIso8601String(),
            ])->all(),
            'receipt' => $sale->receipt ? [
                'id' => $sale->receipt->id,
                'receipt_number' => $sale->receipt->receipt_number,
                'issued_at' => $sale->receipt->issued_at?->toIso8601String(),
            ] : null,
            'created_at' => $sale->created_at?->toIso8601String(),
        ];
    }
}
