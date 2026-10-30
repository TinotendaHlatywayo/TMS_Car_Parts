<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name');
        $slug = is_string($this->input('slug')) && trim($this->input('slug')) !== ''
            ? Str::slug($this->input('slug'))
            : (is_string($name) ? Str::slug($name) : null);

        $this->merge(array_filter([
            'name' => $name,
            'slug' => $slug,
            'description' => is_string($this->input('description')) && trim($this->input('description')) !== ''
                ? trim($this->input('description'))
                : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->has('parent_id') || ! $this->filled('parent_id')) {
                    return;
                }

                /** @var Branch $branch */
                $branch = $this->route('branch');

                $exists = Category::where('branch_id', $branch->id)
                    ->whereKey($this->input('parent_id'))
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add('parent_id', 'The parent category must belong to this branch.');
                }
            },
        ];
    }
}
