<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('code')) {
            $merge['code'] = is_string($this->input('code'))
                ? strtoupper(trim($this->input('code')))
                : $this->input('code');
        }

        if ($this->has('name') && is_string($this->input('name'))) {
            $merge['name'] = trim($this->input('name'));
        }

        if ($this->has('symbol')) {
            $merge['symbol'] = is_string($this->input('symbol')) && trim($this->input('symbol')) !== ''
                ? trim($this->input('symbol'))
                : null;
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
        /** @var Unit $unit */
        $unit = $this->route('unit');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('units', 'code')
                    ->where(fn ($query) => $query->where('branch_id', $branch->id))
                    ->ignore($unit->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'symbol' => ['sometimes', 'nullable', 'string', 'max:10'],
            'allow_decimal' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
