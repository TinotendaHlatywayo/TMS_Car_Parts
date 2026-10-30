<?php

namespace Tests\Feature\Catalogue;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Platform;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class CategoryUnitManagementTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');
        $this->admin = $this->user('Branch A Admin');
        $this->membership($this->branchA, $this->admin, 'admin');
    }

    public function test_category_is_created_with_generated_slug_and_parent(): void
    {
        $parent = $this->category($this->branchA, 'Filters');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/categories", [
                'name' => 'Oil Filters',
                'parent_id' => $parent->id,
                'description' => 'Oil filtration products',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Oil Filters')
            ->assertJsonPath('data.slug', 'oil-filters')
            ->assertJsonPath('data.parent_id', $parent->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created']);
    }

    public function test_category_slug_is_unique_per_branch_and_can_be_customised(): void
    {
        $this->category($this->branchA, 'Brakes');
        Category::where('branch_id', $this->branchA->id)->firstOrFail()->update(['slug' => 'brakes']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/categories", [
                'name' => 'Brakes',
                'slug' => 'brakes',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'brakes-2');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/categories", [
                'name' => 'Custom',
                'slug' => 'custom-slug',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'custom-slug');
    }

    public function test_category_parent_must_be_in_same_branch(): void
    {
        $foreignParent = $this->category($this->branchB, 'Foreign');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/categories", [
                'name' => 'Child',
                'parent_id' => $foreignParent->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_category_cycle_is_rejected(): void
    {
        $root = $this->category($this->branchA, 'Root');
        $child = $this->category($this->branchA, 'Child');
        $child->update(['parent_id' => $root->id]);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/categories/{$root->id}", [
                'parent_id' => $child->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/categories/{$root->id}", [
                'parent_id' => $root->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_category_can_be_renamed_and_archived_without_deletion(): void
    {
        $category = $this->category($this->branchA, 'Old Name');
        $category->update(['slug' => 'old-name']);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/categories/{$category->id}", [
                'name' => 'New Name',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.slug', 'old-name')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.updated']);
    }

    public function test_unit_is_created_uppercase_and_with_decimal_policy(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/units", [
                'code' => 'kg',
                'name' => 'Kilogram',
                'allow_decimal' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'KG')
            ->assertJsonPath('data.allow_decimal', true)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'unit.created']);
    }

    public function test_unit_code_is_unique_per_branch_but_may_repeat_across_branches(): void
    {
        $this->unit($this->branchA, 'PCS');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/units", [
                'code' => 'pcs',
                'name' => 'Pieces',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame(0, Unit::where('branch_id', $this->branchB->id)->where('code', 'PCS')->count());
    }

    public function test_unit_can_be_updated_and_archived(): void
    {
        $unit = $this->unit($this->branchA, 'PCS');

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/units/{$unit->id}", [
                'name' => 'Pieces (each)',
                'allow_decimal' => false,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Pieces (each)')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'unit.updated']);
    }
}
