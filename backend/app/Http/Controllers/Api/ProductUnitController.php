<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductUnitRequest;
use App\Http\Requests\UpdateProductUnitRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProductUnitController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(StoreProductUnitRequest $request, Branch $branch, Product $product): JsonResponse
    {
        $data = $request->validated();
        $sellingPriceMinor = Money::toMinor((string) $data['selling_price'], $product->currency_code);

        unset($data['selling_price']);

        $productUnit = DB::transaction(function () use ($data, $branch, $product, $request, $sellingPriceMinor): ProductUnit {
            $productUnit = $product->units()->create([
                ...$data,
                'branch_id' => $branch->id,
                'selling_price_minor' => $sellingPriceMinor,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $productUnit->load('unit');

            $this->audit->log(
                'product_unit.created',
                $productUnit,
                null,
                $this->snapshot($productUnit),
                $branch->id,
                $request->user(),
            );

            return $productUnit;
        });

        return response()->json(['data' => $this->payload($productUnit)], 201);
    }

    public function update(
        UpdateProductUnitRequest $request,
        Branch $branch,
        Product $product,
        string $productUnit,
    ): JsonResponse {
        $existing = $product->units()->whereKey($productUnit)->firstOrFail();
        $data = $request->validated();

        if (array_key_exists('selling_price', $data)) {
            $data['selling_price_minor'] = Money::toMinor((string) $data['selling_price'], $product->currency_code);
            unset($data['selling_price']);
        }

        $updated = DB::transaction(function () use ($data, $branch, $existing, $request): ProductUnit {
            $before = $this->snapshot($existing);

            $existing->update($data);
            $existing->refresh();
            $existing->load('unit');

            $this->audit->log(
                'product_unit.updated',
                $existing,
                $before,
                $this->snapshot($existing),
                $branch->id,
                $request->user(),
            );

            return $existing;
        });

        return response()->json(['data' => $this->payload($updated)]);
    }

    public function destroy(
        Request $request,
        Branch $branch,
        Product $product,
        string $productUnit,
    ): Response {
        $existing = $product->units()->whereKey($productUnit)->firstOrFail();

        DB::transaction(function () use ($branch, $existing, $request): void {
            $before = $this->snapshot($existing);

            $existing->update(['is_active' => false]);

            $this->audit->log(
                'product_unit.archived',
                $existing,
                $before,
                $this->snapshot($existing->refresh()),
                $branch->id,
                $request->user(),
            );
        });

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ProductUnit $productUnit): array
    {
        return [
            'id' => $productUnit->id,
            'unit' => $productUnit->unit ? [
                'id' => $productUnit->unit->id,
                'code' => $productUnit->unit->code,
                'name' => $productUnit->unit->name,
                'allow_decimal' => $productUnit->unit->allow_decimal,
                'is_active' => $productUnit->unit->is_active,
            ] : null,
            'conversion_factor' => $productUnit->conversion_factor,
            'selling_price_minor' => $productUnit->selling_price_minor,
            'selling_price' => Money::format($productUnit->selling_price_minor, $productUnit->product->currency_code),
            'is_active' => $productUnit->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ProductUnit $productUnit): array
    {
        return [
            'branch_id' => $productUnit->branch_id,
            'product_id' => $productUnit->product_id,
            'unit_id' => $productUnit->unit_id,
            'conversion_factor' => $productUnit->conversion_factor,
            'selling_price_minor' => $productUnit->selling_price_minor,
            'is_active' => $productUnit->is_active,
        ];
    }
}
