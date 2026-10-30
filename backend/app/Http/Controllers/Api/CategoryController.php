<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = in_array($request->query('status'), ['active', 'inactive', 'all'], true)
            ? $request->query('status')
            : 'active';

        $categories = $branch->categories()
            ->withCount('products')
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (Category $category): array => $this->payload($category)),
        ]);
    }

    public function store(StoreCategoryRequest $request, Branch $branch): JsonResponse
    {
        $data = $request->validated();

        $category = DB::transaction(function () use ($data, $branch, $request): Category {
            $category = Category::create([
                'branch_id' => $branch->id,
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($branch, $data['slug'] ?? $data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $category->loadCount('products');

            $this->audit->log(
                'category.created',
                $category,
                null,
                $this->snapshot($category),
                $branch->id,
                $request->user(),
            );

            return $category;
        });

        return response()->json(['data' => $this->payload($category)], 201);
    }

    public function update(UpdateCategoryRequest $request, Branch $branch, Category $category): JsonResponse
    {
        $data = $request->validated();

        $category = DB::transaction(function () use ($data, $branch, $category, $request): Category {
            if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
                $this->ensureNoCycle($category, $data['parent_id']);
            }

            $before = $this->snapshot($category);

            if (array_key_exists('slug', $data) && $data['slug'] !== null) {
                $data['slug'] = $this->uniqueSlug($branch, $data['slug'], $category->id);
            } elseif (array_key_exists('slug', $data)) {
                unset($data['slug']);
            }

            $category->update($data);
            $category->refresh();
            $category->loadCount('products');

            $this->audit->log(
                'category.updated',
                $category,
                $before,
                $this->snapshot($category),
                $branch->id,
                $request->user(),
            );

            return $category;
        });

        return response()->json(['data' => $this->payload($category)]);
    }

    public function destroy(Request $request, Branch $branch, Category $category): Response
    {
        DB::transaction(function () use ($branch, $category, $request): void {
            $before = $this->snapshot($category);

            $category->update(['is_active' => false]);

            $this->audit->log(
                'category.archived',
                $category,
                $before,
                $this->snapshot($category->refresh()),
                $branch->id,
                $request->user(),
            );
        });

        return response()->noContent();
    }

    private function uniqueSlug(Branch $branch, string $base, ?string $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'category';
        $candidate = $slug;
        $suffix = 2;

        while (Category::where('branch_id', $branch->id)
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function ensureNoCycle(Category $category, string $parentId): void
    {
        $visited = [];
        $currentId = $parentId;

        while ($currentId !== null) {
            if ($currentId === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A category cannot be moved below one of its descendants.',
                ]);
            }

            if (in_array($currentId, $visited, true) || count($visited) > 50) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The category hierarchy contains a cycle.',
                ]);
            }

            $visited[] = $currentId;
            $currentId = Category::where('branch_id', $category->branch_id)
                ->whereKey($currentId)
                ->value('parent_id');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'is_active' => $category->is_active,
            'product_count' => $category->products_count ?? null,
            'created_at' => $category->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Category $category): array
    {
        return [
            'branch_id' => $category->branch_id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
        ];
    }
}
