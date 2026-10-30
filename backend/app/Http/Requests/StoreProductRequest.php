<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'sku' => is_string($this->input('sku')) ? strtoupper(trim($this->input('sku'))) : $this->input('sku'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'barcode' => is_string($this->input('barcode')) && trim($this->input('barcode')) !== ''
                ? trim($this->input('barcode'))
                : null,
            'description' => is_string($this->input('description')) && trim($this->input('description')) !== ''
                ? trim($this->input('description'))
                : null,
            'category_id' => $this->input('category_id') === '' ? null : $this->input('category_id'),
            'reorder_level' => $this->input('reorder_level') === '' ? null : $this->input('reorder_level'),
        ];

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        $rules = [
            'name' => ['required', 'string', 'max:180'],
            'sku' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('products', 'sku')->where(fn ($query) => $query->where('branch_id', $branch->id)),
            ],
            'barcode' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'string'],
            'base_unit_id' => ['required', 'string'],
            'selling_price' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d+)?$/'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,4})?$/'],
        ];

        if ($this->filled('barcode')) {
            $rules['barcode'][] = Rule::unique('products', 'barcode')
                ->where(fn ($query) => $query->where('branch_id', $branch->id));
        }

        return $rules;
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                /** @var Branch $branch */
                $branch = $this->route('branch');

                if (! $validator->errors()->has('selling_price')) {
                    try {
                        Money::toMinor((string) $this->input('selling_price'), $branch->currency_code);
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add('selling_price', $exception->getMessage());
                    }
                }

                if (! $validator->errors()->has('category_id') && $this->filled('category_id')) {
                    $category = Category::where('branch_id', $branch->id)
                        ->whereKey($this->input('category_id'))
                        ->first();

                    if (! $category) {
                        $validator->errors()->add('category_id', 'The category must belong to this branch.');
                    } elseif (! $category->is_active) {
                        $validator->errors()->add('category_id', 'The selected category is archived.');
                    }
                }

                if (! $validator->errors()->has('base_unit_id')) {
                    $unit = Unit::where('branch_id', $branch->id)
                        ->whereKey($this->input('base_unit_id'))
                        ->first();

                    if (! $unit) {
                        $validator->errors()->add('base_unit_id', 'The unit must belong to this branch.');
                    } elseif (! $unit->is_active) {
                        $validator->errors()->add('base_unit_id', 'The selected unit is archived.');
                    }
                }
            },
        ];
    }
}
