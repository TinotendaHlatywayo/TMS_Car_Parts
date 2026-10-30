<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Unit;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreProductUnitRequest extends FormRequest
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
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'unit_id' => [
                'required',
                'string',
                Rule::unique('product_units', 'unit_id')
                    ->where(fn ($query) => $query->where('product_id', $product->id)),
            ],
            'conversion_factor' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,6})?$/'],
            'selling_price' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d+)?$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                /** @var Branch $branch */
                $branch = $this->route('branch');
                /** @var Product $product */
                $product = $this->route('product');

                if (! $validator->errors()->has('unit_id')) {
                    $unit = Unit::where('branch_id', $branch->id)
                        ->whereKey($this->input('unit_id'))
                        ->first();

                    if (! $unit) {
                        $validator->errors()->add('unit_id', 'The unit must belong to this branch.');
                    } elseif ($unit->id === $product->base_unit_id) {
                        $validator->errors()->add('unit_id', 'The base unit cannot be added as an alternate unit.');
                    } elseif (! $unit->is_active) {
                        $validator->errors()->add('unit_id', 'The selected unit is archived.');
                    } elseif (! $product->baseUnit?->allow_decimal
                        && Decimal::hasFraction((string) $this->input('conversion_factor'), 6)) {
                        $validator->errors()->add(
                            'conversion_factor',
                            'The conversion factor must be a whole number when the base unit does not allow fractions.'
                        );
                    }
                }

                if (! $validator->errors()->has('selling_price')) {
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
