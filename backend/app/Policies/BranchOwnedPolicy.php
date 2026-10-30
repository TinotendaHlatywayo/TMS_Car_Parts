<?php

namespace App\Policies;

use App\Models\User;

class BranchOwnedPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Default deny - must be explicitly allowed
        return null;
    }

    protected function sameBranch(User $user, $model): bool
    {
        $branchId = request()->attributes->get('branch_id');
        if (! $branchId) {
            return false;
        }
        if (isset($model->branch_id)) {
            return $model->branch_id === $branchId;
        }

        return false;
    }
}
