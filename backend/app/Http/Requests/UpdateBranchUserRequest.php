<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $permissions = $this->input('permissions');

        if (is_array($permissions)) {
            $this->merge([
                'permissions' => array_values(array_unique($permissions)),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Roles::names())],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::branch())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
