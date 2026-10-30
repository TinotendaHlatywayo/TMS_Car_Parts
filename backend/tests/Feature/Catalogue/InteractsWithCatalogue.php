<?php

namespace Tests\Feature\Catalogue;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;

trait InteractsWithCatalogue
{
    protected function category(Branch $branch, string $name = 'Brakes'): Category
    {
        return Category::create([
            'branch_id' => $branch->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'is_active' => true,
        ]);
    }

    protected function unit(Branch $branch, string $code = 'PCS', bool $allowDecimal = false): Unit
    {
        return Unit::create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => $code.' unit',
            'allow_decimal' => $allowDecimal,
            'is_active' => true,
        ]);
    }

    protected function product(
        Branch $branch,
        Unit $baseUnit,
        ?Category $category = null,
        string $sku = 'SKU-001',
        string $name = 'Test Product',
        ?string $barcode = null,
        int $priceMinor = 1000,
        bool $trackStock = true,
    ): Product {
        $product = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category?->id,
            'base_unit_id' => $baseUnit->id,
            'sku' => $sku,
            'name' => $name,
            'barcode' => $barcode,
            'selling_price_minor' => $priceMinor,
            'currency_code' => $branch->currency_code,
            'track_stock' => $trackStock,
            'is_active' => true,
        ]);

        $product->stockLevel()->create([
            'branch_id' => $branch->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'reorder_level' => null,
        ]);

        return $product;
    }

    protected function alternateUnit(
        Product $product,
        Unit $unit,
        string $factor = '10.000000',
        int $priceMinor = 5000,
        bool $active = true,
    ): ProductUnit {
        return $product->units()->create([
            'branch_id' => $product->branch_id,
            'unit_id' => $unit->id,
            'conversion_factor' => $factor,
            'selling_price_minor' => $priceMinor,
            'is_active' => $active,
        ]);
    }
}
