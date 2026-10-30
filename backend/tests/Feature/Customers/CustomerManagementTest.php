<?php

namespace Tests\Feature\Customers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use InteractsWithBranchAccess;
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

    public function test_customer_can_be_created_and_listed(): void
    {
        $response = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/customers", [
                'name' => 'Jane Customer',
                'phone' => '0771 234 567',
                'email' => 'Jane@Example.com',
                'address' => '1 Main Street',
                'notes' => 'Prefers cash',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Jane Customer')
            ->assertJsonPath('data.phone', '0771 234 567')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('duplicate_warning', false);

        $id = $response->json('data.id');

        $this->assertDatabaseHas('customers', [
            'id' => $id,
            'branch_id' => $this->branchA->id,
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'customer.created',
            'entity_id' => $id,
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/customers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_duplicate_name_phone_or_email_returns_a_warning_without_merging(): void
    {
        $first = Customer::create([
            'branch_id' => $this->branchA->id,
            'name' => 'John Customer',
            'phone' => '0770000000',
            'email' => 'john@example.com',
            'is_active' => true,
        ]);

        $response = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/customers", [
                'name' => '  john customer  ',
                'phone' => '077-000-0000',
                'email' => 'JOHN@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('duplicate_warning', true);

        $secondId = $response->json('data.id');

        $this->assertNotSame($first->id, $secondId);
        $this->assertSame(2, Customer::where('branch_id', $this->branchA->id)->count());
    }

    public function test_customer_can_be_updated_and_archived(): void
    {
        $customer = Customer::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/customers/{$customer->id}", [
                'name' => 'Updated Name',
                'phone' => '0771111111',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '0771111111')
            ->assertJsonPath('data.is_active', false);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/customers")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/customers?status=all")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', false);

        $this->withAuthToken($this->admin)
            ->deleteJson("/api/branches/{$this->branchA->id}/customers/{$customer->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => false,
        ]);
    }

    public function test_validation_and_search_work(): void
    {
        Customer::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Searchable Customer',
            'phone' => '0772222222',
            'email' => 'search@example.com',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/customers", [
                'name' => '',
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/customers?q=search")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Searchable Customer');
    }

    public function test_cross_branch_customer_data_is_not_readable_or_mutable(): void
    {
        $customerB = Customer::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Branch B Customer',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/customers")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/customers/{$customerB->id}")
            ->assertNotFound();

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/customers/{$customerB->id}", ['name' => 'Changed'])
            ->assertNotFound();

        $this->withAuthToken($this->admin)
            ->deleteJson("/api/branches/{$this->branchA->id}/customers/{$customerB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('customers', [
            'id' => $customerB->id,
            'name' => 'Branch B Customer',
        ]);
    }

    public function test_cashier_can_create_customer_but_viewer_cannot(): void
    {
        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/customers", [
                'name' => 'Cashier Customer',
            ])
            ->assertCreated();

        $viewer = $this->user('Viewer');
        $this->membership($this->branchA, $viewer, 'viewer');

        $this->withAuthToken($viewer)
            ->postJson("/api/branches/{$this->branchA->id}/customers", [
                'name' => 'Viewer Customer',
            ])
            ->assertForbidden();

        $this->withAuthToken($viewer)
            ->getJson("/api/branches/{$this->branchA->id}/customers")
            ->assertOk();
    }

    public function test_unauthenticated_and_platform_admin_without_membership_are_rejected(): void
    {
        $this->getJson("/api/branches/{$this->branchA->id}/customers")->assertUnauthorized();

        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->withAuthToken($platformAdmin)
            ->getJson("/api/branches/{$this->branchA->id}/customers")
            ->assertForbidden();
    }
}
