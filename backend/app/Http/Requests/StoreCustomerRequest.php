<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'phone' => is_string($this->input('phone')) && trim($this->input('phone')) !== ''
                ? trim($this->input('phone'))
                : null,
            'email' => is_string($this->input('email')) && trim($this->input('email')) !== ''
                ? strtolower(trim($this->input('email')))
                : null,
            'address' => is_string($this->input('address')) && trim($this->input('address')) !== ''
                ? trim($this->input('address'))
                : null,
            'notes' => is_string($this->input('notes')) && trim($this->input('notes')) !== ''
                ? trim($this->input('notes'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
