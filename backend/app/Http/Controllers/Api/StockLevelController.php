<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockLevelController extends Controller
{
    public function __invoke(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $search = trim((string) $request->query('q', ''));

        $levels = $branch->stockLevels()
            ->with([
                'product' => fn ($query) => $query
                    ->select('id', 'branch_id', 'base_unit_id', 'sku', 'name', 'track_stock', 'is_active')
                    ->withExists(['stockMovements as has_opening' => fn ($movementQuery) => $movementQuery->whereNotNull('opening_marker')]),
                'product.baseUnit:id,code,name,symbol,allow_decimal',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('product', function ($productQuery) use ($search): void {
                    $productQuery->where(function ($nested) use ($search): void {
                        $nested->where('sku', 'like', $search.'%')
                            ->orWhere('name', 'like', $search.'%')
                            ->orWhere('barcode', $search);
                    });
                });
            })
            ->orderByDesc('updated_at')
            ->orderBy('product_id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $levels->getCollection()
                ->map(fn (StockLevel $level): array => $this->payload($level)),
            'meta' => [
                'current_page' => $levels->currentPage(),
                'last_page' => $levels->lastPage(),
                'per_page' => $levels->perPage(),
                'total' => $levels->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StockLevel $level): array
    {
        $product = $level->product;
        $reorderLevel = $level->reorder_level;
        $isLow = $product?->track_stock
            && $reorderLevel !== null
            && bccomp((string) $level->quantity_on_hand, (string) $reorderLevel, 4) <= 0;

        return [
            'product' => $product ? [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'track_stock' => $product->track_stock,
                'is_active' => $product->is_active,
                'has_opening' => (bool) ($product->has_opening ?? false),
                'base_unit' => $product->baseUnit ? [
                    'id' => $product->baseUnit->id,
                    'code' => $product->baseUnit->code,
                    'name' => $product->baseUnit->name,
                    'symbol' => $product->baseUnit->symbol,
                ] : null,
            ] : null,
            'quantity_on_hand' => (string) $level->quantity_on_hand,
            'quantity_reserved' => (string) $level->quantity_reserved,
            'reorder_level' => $reorderLevel !== null ? (string) $reorderLevel : null,
            'is_low' => $isLow,
            'updated_at' => $level->updated_at?->toIso8601String(),
        ];
    }
}
