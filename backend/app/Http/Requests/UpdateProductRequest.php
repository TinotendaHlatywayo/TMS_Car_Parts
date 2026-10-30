<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('sku')) {
            $merge['sku'] = is_string($this->input('sku')) ? strtoupper(trim($this->input('sku'))) : $this->input('sku');
        }

        if ($this->has('name') && is_string($this->input('name'))) {
            $merge['name'] = trim($this->input('name'));
        }

        if ($this->has('barcode')) {
            $merge['barcode'] = is_string($this->input('barcode')) && trim($this->input('barcode')) !== ''
                ? trim($this->input('barcode'))
                : null;
        }

        if ($this->has('description')) {
            $merge['description'] = is_string($this->input('description')) && trim($this->input('description')) !== ''
                ? trim($this->input('description'))
                : null;
        }

        if ($this->has('category_id')) {
            $merge['category_id'] = $this->input('category_id') === '' ? null : $this->input('category_id');
        }

        if ($this->has('reorder_level')) {
            $merge['reorder_level'] = $this->input('reorder_level') === '' ? null : $this->input('reorder_level');
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');
        /** @var Product $product */
        $product = $this->route('product');

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('branch_id', $branch->id))
                    ->ignore($product->id),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category_id' => ['sometimes', 'nullable', 'string'],
            'base_unit_id' => ['sometimes', 'required', 'string'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d+(\.\d+)?$/'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'reorder_level' => ['sometimes', 'nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,4})?$/'],
        ];

        if ($this->filled('barcode')) {
            $rules['barcode'][] = Rule::unique('products', 'barcode')
                ->where(fn ($query) => $query->where('branch_id', $branch->id))
                ->ignore($product->id);
        }

        return $rules;
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                /** @var Branch $branch */
                $branch = $this->route('branch');
                /** @var Product $product */
                $product = $this->route('product');

                if ($this->has('selling_price') && ! $validator->errors()->has('selling_price')) {
                    try {
                        Money::toMinor((string) $this->input('selling_price'), $branch->currency_code);
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add('selling_price', $exception->getMessage());
                    }
                }

                if (! $validator->errors()->has('category_id') && $this->has('category_id') && $this->filled('category_id')) {
                    $category = Category::where('branch_id', $branch->id)
                        ->whereKey($this->input('category_id'))
                        ->first();

                    if (! $category) {
                        $validator->errors()->add('category_id', 'The category must belong to this branch.');
                    } elseif ($this->input('category_id') !== $product->category_id && ! $category->is_active) {
                        $validator->errors()->add('category_id', 'The selected category is archived.');
                    }
                }

                if (! $validator->errors()->has('base_unit_id') && $this->has('base_unit_id')) {
                    $unit = Unit::where('branch_id', $branch->id)
                        ->whereKey($this->input('base_unit_id'))
                        ->first();

                    if (! $unit) {
                        $validator->errors()->add('base_unit_id', 'The unit must belong to this branch.');
                    } elseif ($this->input('base_unit_id') !== $product->base_unit_id && ! $unit->is_active) {
                        $validator->errors()->add('base_unit_id', 'The selected unit is archived.');
                    }
                }
            },
        ];
    }
}
