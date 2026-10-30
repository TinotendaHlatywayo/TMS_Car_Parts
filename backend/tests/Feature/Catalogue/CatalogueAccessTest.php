<?php

namespace Tests\Feature\Catalogue;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class CatalogueAccessTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $cashier;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->admin = $this->user('Branch A Admin');
        $this->cashier = $this->user('Branch A Cashier');
        $this->adminB = $this->user('Branch B Admin');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->cashier, 'cashier');
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_unauthenticated_catalogue_requests_are_rejected(): void
    {
        $this->getJson("/api/branches/{$this->branchA->id}/products")->assertUnauthorized();
    }

    public function test_cashier_can_view_but_cannot_manage_catalogue(): void
    {
        $unit = $this->unit($this->branchA);
        $category = $this->category($this->branchA);
        $this->product($this->branchA, $unit, $category);

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertOk();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/categories")
            ->assertOk();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/units")
            ->assertOk();

        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Forbidden Product',
                'sku' => 'NO-1',
                'base_unit_id' => $unit->id,
                'selling_price' => '1.00',
            ])
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/categories", ['name' => 'Forbidden'])
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/units", ['code' => 'NO', 'name' => 'No'])
            ->assertForbidden();
    }

    public function test_storekeeper_can_manage_catalogue(): void
    {
        $storekeeper = $this->user('Storekeeper');
        $this->membership($this->branchA, $storekeeper, 'storekeeper');
        $unit = $this->unit($this->branchA);

        $this->withAuthToken($storekeeper)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Storekeeper Product',
                'sku' => 'STORE-1',
                'base_unit_id' => $unit->id,
                'selling_price' => '9.99',
            ])
            ->assertCreated();
    }

    public function test_branch_admin_cannot_list_another_branch_catalogue(): void
    {
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/products")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/categories")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/units")
            ->assertForbidden();
    }

    public function test_cross_branch_product_id_is_not_resolved_inside_another_branch(): void
    {
        $unitB = $this->unit($this->branchB);
        $productB = $this->product($this->branchB, $unitB, null, 'B-1');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/products/{$productB->id}")
            ->assertNotFound();

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/products/{$productB->id}", ['name' => 'Changed'])
            ->assertNotFound();
    }

    public function test_platform_admin_without_branch_membership_cannot_read_catalogue(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->withAuthToken($platformAdmin)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertForbidden();
    }

    public function test_unknown_role_has_no_catalogue_access(): void
    {
        $legacy = $this->user('Legacy');
        $this->membership($this->branchA, $legacy, 'legacy_role');

        $this->withAuthToken($legacy)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertForbidden();
    }
}
