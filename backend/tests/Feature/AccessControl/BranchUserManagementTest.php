<?php

namespace Tests\Feature\AccessControl;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BranchUserManagementTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private BranchUser $adminMembership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->admin = $this->user('Branch A Admin');
        $this->adminMembership = $this->membership($this->branchA, $this->admin, 'admin');
    }

    public function test_branch_admin_can_create_a_new_user_in_their_branch(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/users", [
                'name' => 'New Cashier',
                'email' => 'cashier@example.com',
                'password' => 'password123',
                'role' => 'cashier',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'cashier@example.com')
            ->assertJsonPath('data.role', 'cashier');

        $this->assertDatabaseHas('users', ['email' => 'cashier@example.com', 'is_active' => true]);
        $this->assertDatabaseHas('branch_users', [
            'branch_id' => $this->branchA->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    public function test_branch_admin_can_attach_an_existing_user_without_creating_a_duplicate(): void
    {
        $existing = $this->user('Existing User');
        $countBefore = User::count();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/users", [
                'email' => $existing->email,
                'role' => 'storekeeper',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $existing->id);

        $this->assertSame($countBefore, User::count());
        $this->assertDatabaseHas('branch_users', [
            'branch_id' => $this->branchA->id,
            'user_id' => $existing->id,
            'role' => 'storekeeper',
        ]);
    }

    public function test_unknown_roles_are_rejected(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/users", [
                'name' => 'Bad Role',
                'email' => 'bad-role@example.com',
                'password' => 'password123',
                'role' => 'super_admin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'bad-role@example.com']);
    }

    public function test_platform_permissions_cannot_be_granted_through_branch_memberships(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/users", [
                'name' => 'Escalation Attempt',
                'email' => 'escalation@example.com',
                'password' => 'password123',
                'role' => 'cashier',
                'permissions' => ['platform.users.manage'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions.0');
    }

    public function test_branch_admin_cannot_manage_another_branch_membership_by_id(): void
    {
        $otherUser = $this->user('Other Branch User');
        $otherMembership = $this->membership($this->branchB, $otherUser, 'cashier');

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/users/{$otherMembership->id}", [
                'role' => 'admin',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('branch_users', [
            'id' => $otherMembership->id,
            'role' => 'cashier',
        ]);
    }

    public function test_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/users/{$this->adminMembership->id}", [
                'role' => 'cashier',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/users/{$this->adminMembership->id}", [
                'role' => 'admin',
                'is_active' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_last_active_admin_cannot_be_removed(): void
    {
        $this->withAuthToken($this->admin)
            ->deleteJson("/api/branches/{$this->branchA->id}/users/{$this->adminMembership->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('membership');

        $this->assertDatabaseHas('branch_users', [
            'id' => $this->adminMembership->id,
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_deactivating_last_membership_revokes_tokens_and_access_immediately(): void
    {
        $target = $this->user('Target Cashier');
        $targetMembership = $this->membership($this->branchA, $target, 'cashier');
        $token = $this->tokenFor($target);

        DB::table('sessions')->insert([
            'id' => 'session-'.$target->id,
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $this->assertSame(1, $target->tokens()->count());

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/users/{$targetMembership->id}", [
                'role' => 'cashier',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $target->fresh()->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);

        $this->withRawToken($token)
            ->getJson("/api/branches/{$this->branchA->id}/users")
            ->assertUnauthorized();
    }

    public function test_role_changes_are_recomputed_without_a_new_login(): void
    {
        $target = $this->user('Promoted Cashier');
        $targetMembership = $this->membership($this->branchA, $target, 'cashier');
        $token = $this->tokenFor($target);

        $this->withRawToken($token)
            ->getJson("/api/branches/{$this->branchA->id}/roles")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/users/{$targetMembership->id}", [
                'role' => 'manager',
            ])
            ->assertOk();

        $this->withRawToken($token)
            ->getJson("/api/branches/{$this->branchA->id}/roles")
            ->assertOk();
    }

    public function test_removed_membership_can_be_reactivated_without_duplicate_rows(): void
    {
        $target = $this->user('Returning Staff');
        $targetMembership = $this->membership($this->branchA, $target, 'cashier');

        $this->withAuthToken($this->admin)
            ->deleteJson("/api/branches/{$this->branchA->id}/users/{$targetMembership->id}")
            ->assertNoContent();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/users", [
                'email' => $target->email,
                'role' => 'storekeeper',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'storekeeper');

        $this->assertSame(1, BranchUser::withTrashed()
            ->where('branch_id', $this->branchA->id)
            ->where('user_id', $target->id)
            ->count());

        $this->assertDatabaseHas('branch_users', [
            'id' => $targetMembership->id,
            'role' => 'storekeeper',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_extra_branch_permissions_are_applied_but_platform_permissions_are_ignored(): void
    {
        $target = $this->user('Extra Permissions');
        $membership = $this->membership($this->branchA, $target, 'cashier', true, [
            'branch.roles.view',
            'platform.users.manage',
        ]);

        $this->assertContains('branch.roles.view', $membership->effectivePermissions());
        $this->assertNotContains('platform.users.manage', $membership->effectivePermissions());
    }
}
