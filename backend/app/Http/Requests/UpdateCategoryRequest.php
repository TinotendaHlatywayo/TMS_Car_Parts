<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $merge['name'] = trim($this->input('name'));
        }

        if ($this->has('slug')) {
            $merge['slug'] = is_string($this->input('slug')) && trim($this->input('slug')) !== ''
                ? Str::slug($this->input('slug'))
                : null;
        }

        if ($this->has('description')) {
            $merge['description'] = is_string($this->input('description')) && trim($this->input('description')) !== ''
                ? trim($this->input('description'))
                : null;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'parent_id' => ['sometimes', 'nullable', 'string'],
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
                /** @var Category $category */
                $category = $this->route('category');

                if ($this->input('parent_id') === $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be its own parent.');

                    return;
                }

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
