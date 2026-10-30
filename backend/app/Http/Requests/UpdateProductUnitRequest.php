<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class UpdateProductUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversion_factor' => ['sometimes', 'required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,6})?$/'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d+(\.\d+)?$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                /** @var Product $product */
                $product = $this->route('product');
                $productUnitId = (string) $this->route('productUnit');
                $productUnit = ProductUnit::with('unit')
                    ->where('product_id', $product->id)
                    ->whereKey($productUnitId)
                    ->first();

                if (! $productUnit) {
                    return;
                }

                if ($this->has('is_active') && $this->boolean('is_active')
                    && $productUnit->unit && ! $productUnit->unit->is_active) {
                    $validator->errors()->add('is_active', 'The unit is archived and cannot be reactivated for this product.');
                }

                if ($this->has('conversion_factor')
                    && ! $validator->errors()->has('conversion_factor')
                    && ! $product->baseUnit?->allow_decimal
                    && Decimal::hasFraction((string) $this->input('conversion_factor'), 6)) {
                    $validator->errors()->add(
                        'conversion_factor',
                        'The conversion factor must be a whole number when the base unit does not allow fractions.'
                    );
                }

                if ($this->has('selling_price') && ! $validator->errors()->has('selling_price')) {
                    try {
                        Money::toMinor((string) $this->input('selling_price'), $product->currency_code);
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add('selling_price', $exception->getMessage());
                    }
                }
            },
        ];
    }
}
