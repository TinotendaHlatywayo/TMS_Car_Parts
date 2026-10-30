<?php

namespace Tests\Feature\AccessControl;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;

trait InteractsWithBranchAccess
{
    protected function platform(): Platform
    {
        return Platform::create([
            'name' => 'Test Platform',
            'slug' => 'test-platform-'.uniqid(),
            'currency_code' => 'USD',
        ]);
    }

    protected function branch(Platform $platform, string $code): Branch
    {
        return Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Branch '.$code,
            'code' => $code,
            'slug' => 'branch-'.strtolower($code),
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
    }

    protected function user(string $name = 'Test User'): User
    {
        return User::factory()->create(['name' => $name]);
    }

    protected function membership(
        Branch $branch,
        User $user,
        string $role = 'cashier',
        bool $active = true,
        array $permissions = [],
    ): BranchUser {
        return BranchUser::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role' => $role,
            'permissions' => $permissions,
            'is_active' => $active,
        ]);
    }

    protected function tokenFor(User $user): string
    {
        return $user->createToken('access-control-test')->plainTextToken;
    }

    protected function withAuthToken(User $user): static
    {
        return $this->withRawToken($this->tokenFor($user));
    }

    protected function withRawToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
