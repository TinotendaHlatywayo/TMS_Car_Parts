<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_other_branch_data_without_membership(): void
    {
        $platform = Platform::create([
            'name' => 'Test Platform',
            'slug' => 'test-platform',
            'currency_code' => 'USD',
        ]);

        $branchA = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Branch A',
            'code' => 'BTA',
            'slug' => 'branch-a',
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);

        $branchB = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Branch B',
            'code' => 'BTB',
            'slug' => 'branch-b',
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);

        $user = User::factory()->create();
        BranchUser::create([
            'branch_id' => $branchA->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Branch-Id', $branchB->id)
            ->getJson("/api/branches/{$branchB->id}/users")
            ->assertForbidden();
    }
}
