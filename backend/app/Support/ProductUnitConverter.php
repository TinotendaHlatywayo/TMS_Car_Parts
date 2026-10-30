<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

final class ProductUnitConverter
{
    /**
     * @return array{quantity: string, from_unit: Unit, to_unit: Unit, from_factor: string, to_factor: string}
     */
    public function convert(Product $product, string $fromUnitId, string $toUnitId, string $quantity): array
    {
        $fromUnit = $this->unitFor($product, $fromUnitId, 'from');
        $toUnit = $this->unitFor($product, $toUnitId, 'to');
        $fromFactor = $this->factorFor($product, $fromUnitId, 'from');
        $toFactor = $this->factorFor($product, $toUnitId, 'to');

        if (! $fromUnit->allow_decimal && Decimal::hasFraction($quantity, 6)) {
            throw ValidationException::withMessages([
                'quantity' => 'The selected source unit does not allow fractional quantities.',
            ]);
        }

        $baseQuantity = bcmul($quantity, $fromFactor, 6);
        $converted = Decimal::roundHalfUp(bcdiv($baseQuantity, $toFactor, 6), 6);

        if (! $toUnit->allow_decimal && Decimal::hasFraction($converted, 6)) {
            throw ValidationException::withMessages([
                'to_unit_id' => 'The conversion result cannot be represented using the selected target unit.',
            ]);
        }

        return [
            'quantity' => Decimal::trim($converted),
            'from_unit' => $fromUnit,
            'to_unit' => $toUnit,
            'from_factor' => Decimal::trim($fromFactor),
            'to_factor' => Decimal::trim($toFactor),
        ];
    }

    private function unitFor(Product $product, string $unitId, string $side): Unit
    {
        if ($unitId === $product->base_unit_id) {
            $unit = $product->baseUnit;

            if (! $unit) {
                throw ValidationException::withMessages([
                    "{$side}_unit_id" => 'The product base unit is not available.',
                ]);
            }

            if (! $unit->is_active) {
                throw ValidationException::withMessages([
                    "{$side}_unit_id" => 'The selected unit is archived.',
                ]);
            }

            return $unit;
        }

        $productUnit = $product->units()
            ->with('unit')
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->first();

        if (! $productUnit || ! $productUnit->unit) {
            throw ValidationException::withMessages([
                "{$side}_unit_id" => 'The selected unit is not configured for this product.',
            ]);
        }

        if (! $productUnit->unit->is_active) {
            throw ValidationException::withMessages([
                "{$side}_unit_id" => 'The selected unit is archived.',
            ]);
        }

        return $productUnit->unit;
    }

    private function factorFor(Product $product, string $unitId, string $side): string
    {
        if ($unitId === $product->base_unit_id) {
            return '1.000000';
        }

        $productUnit = $product->units()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->first();

        if (! $productUnit || bccomp($productUnit->conversion_factor, '0', 6) <= 0) {
            throw ValidationException::withMessages([
                "{$side}_unit_id" => 'No explicit conversion is defined for the selected unit.',
            ]);
        }

        return $productUnit->conversion_factor;
    }
}
