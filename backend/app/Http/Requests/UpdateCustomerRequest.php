<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => trim($this->input('phone')) === '' ? null : trim($this->input('phone'))]);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => trim($this->input('email')) === '' ? null : strtolower(trim($this->input('email')))]);
        }

        if (is_string($this->input('address'))) {
            $this->merge(['address' => trim($this->input('address')) === '' ? null : trim($this->input('address'))]);
        }

        if (is_string($this->input('notes'))) {
            $this->merge(['notes' => trim($this->input('notes')) === '' ? null : trim($this->input('notes'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
