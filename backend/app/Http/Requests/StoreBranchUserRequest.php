<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $permissions = $this->input('permissions');

        $this->merge(array_filter([
            'email' => is_string($this->input('email')) ? strtolower($this->input('email')) : $this->input('email'),
            'permissions' => is_array($permissions) ? array_values(array_unique($permissions)) : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(Roles::names())],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::branch())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->has('email')) {
                    return;
                }

                $exists = User::where('email', $this->input('email'))->exists();

                if (! $exists && ! $this->filled('name')) {
                    $validator->errors()->add('name', 'Name is required for a new user.');
                }

                if (! $exists && ! $this->filled('password')) {
                    $validator->errors()->add('password', 'Password is required for a new user.');
                }
            },
        ];
    }
}
