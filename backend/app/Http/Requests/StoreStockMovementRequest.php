<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => is_string($this->input('type')) ? strtolower(trim($this->input('type'))) : $this->input('type'),
            'quantity' => is_string($this->input('quantity')) ? trim($this->input('quantity')) : $this->input('quantity'),
            'unit_id' => is_string($this->input('unit_id')) ? trim($this->input('unit_id')) : $this->input('unit_id'),
            'reason' => is_string($this->input('reason')) ? trim($this->input('reason')) : $this->input('reason'),
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:opening,correction'],
            'quantity' => ['required', 'numeric', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'unit_id' => ['required', 'string'],
            'reason' => ['required_if:type,correction', 'nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
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

                if ($product->branch_id !== $branch->id) {
                    $validator->errors()->add('product_id', 'The product must belong to this branch.');
                }

                if (! $validator->errors()->has('quantity') && is_numeric($this->input('quantity'))) {
                    $quantity = (string) $this->input('quantity');

                    if ($this->input('type') === 'opening' && bccomp($quantity, '0', 4) <= 0) {
                        $validator->errors()->add('quantity', 'Opening stock must be greater than zero.');
                    }

                    if ($this->input('type') === 'correction' && bccomp($quantity, '0', 4) === 0) {
                        $validator->errors()->add('quantity', 'A correction must change stock by a non-zero quantity.');
                    }
                }
            },
        ];
    }
}
