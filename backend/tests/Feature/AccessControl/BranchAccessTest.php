<?php

namespace Tests\Feature\AccessControl;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $cashier;

    private User $branchBUser;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->admin = $this->user('Branch A Admin');
        $this->cashier = $this->user('Branch A Cashier');
        $this->branchBUser = $this->user('Branch B User');
        $this->platformAdmin = User::factory()->platformAdmin()->create(['name' => 'Platform Owner']);

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->cashier, 'cashier');
        $this->membership($this->branchB, $this->branchBUser, 'admin');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson("/api/branches/{$this->branchA->id}/users")
            ->assertUnauthorized();
    }

    public function test_cashier_without_user_view_permission_is_forbidden(): void
    {
        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/users")
            ->assertForbidden();
    }

    public function test_cashier_cannot_create_branch_users(): void
    {
        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/users", [
                'name' => 'Unauthorized User',
                'email' => 'unauthorized@example.com',
                'password' => 'password123',
                'role' => 'cashier',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.com']);
    }

    public function test_default_deny_applies_to_unknown_roles(): void
    {
        $legacy = $this->user('Legacy User');
        $this->membership($this->branchA, $legacy, 'legacy_role');

        $this->withAuthToken($legacy)
            ->getJson("/api/branches/{$this->branchA->id}/users")
            ->assertForbidden();
    }

    public function test_inactive_membership_is_forbidden(): void
    {
        $inactive = $this->user('Inactive Membership');
        $this->membership($this->branchA, $inactive, 'admin', false);

        $this->withAuthToken($inactive)
            ->getJson("/api/branches/{$this->branchA->id}/users")
            ->assertForbidden();
    }

    public function test_branch_admin_can_list_only_their_branch_members(): void
    {
        $response = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/users")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $emails = collect($response->json('data'))->pluck('user.email');

        $this->assertTrue($emails->contains($this->admin->email));
        $this->assertTrue($emails->contains($this->cashier->email));
        $this->assertFalse($emails->contains($this->branchBUser->email));
    }

    public function test_branch_admin_cannot_access_another_branch(): void
    {
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/users")
            ->assertForbidden();
    }

    public function test_conflicting_branch_header_cannot_override_route_branch(): void
    {
        $response = $this->withAuthToken($this->admin)
            ->withHeader('X-Branch-Id', $this->branchB->id)
            ->getJson("/api/branches/{$this->branchA->id}/users")
            ->assertOk();

        $emails = collect($response->json('data'))->pluck('user.email');
        $this->assertFalse($emails->contains($this->branchBUser->email));
    }

    public function test_platform_admin_without_branch_membership_cannot_access_branch_data(): void
    {
        $this->withAuthToken($this->platformAdmin)
            ->getJson("/api/branches/{$this->branchA->id}/users")
            ->assertForbidden();
    }

    public function test_platform_access_is_separate_from_branch_administration(): void
    {
        $this->withAuthToken($this->admin)
            ->getJson('/api/platform/access')
            ->assertForbidden();

        $response = $this->withAuthToken($this->platformAdmin)
            ->getJson('/api/platform/access')
            ->assertOk()
            ->assertJsonPath('is_platform_admin', true);

        $this->assertContains('platform.users.manage', $response->json('permissions'));
    }

    public function test_me_returns_branch_permissions_without_platform_permissions(): void
    {
        $response = $this->withAuthToken($this->cashier)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('branches.0.id', $this->branchA->id)
            ->assertJsonPath('platform_permissions', []);

        $permissions = $response->json('branches.0.permissions');

        $this->assertContains('branch.sales.create', $permissions);
        $this->assertNotContains('branch.users.manage', $permissions);
        $this->assertNotContains('platform.users.manage', $permissions);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->postJson('/register', [
            'name' => 'Public User',
            'email' => 'public@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'public@example.com']);
    }
}
