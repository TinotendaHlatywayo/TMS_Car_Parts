<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertProductUnitRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Unit;
use App\Support\ProductUnitConverter;
use Illuminate\Http\JsonResponse;

class ProductConversionController extends Controller
{
    public function __invoke(
        ConvertProductUnitRequest $request,
        Branch $branch,
        Product $product,
        ProductUnitConverter $converter,
    ): JsonResponse {
        $data = $request->validated();

        $result = $converter->convert(
            $product,
            $data['from_unit_id'],
            $data['to_unit_id'],
            (string) $data['quantity'],
        );

        return response()->json([
            'data' => [
                'product_id' => $product->id,
                'quantity' => $result['quantity'],
                'from_unit' => $this->unitPayload($result['from_unit']),
                'to_unit' => $this->unitPayload($result['to_unit']),
                'from_factor' => $result['from_factor'],
                'to_factor' => $result['to_factor'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function unitPayload(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'allow_decimal' => $unit->allow_decimal,
        ];
    }
}
