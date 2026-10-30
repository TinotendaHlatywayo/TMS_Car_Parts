<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessRevocation
{
    public function revoke(User $user): void
    {
        $user->tokens()->delete();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    public function revokeIfNoBranchAccess(User $user): void
    {
        if (! $user->is_active) {
            $this->revoke($user);

            return;
        }

        if ($user->is_platform_admin) {
            return;
        }

        if (! $user->activeBranchMemberships()->exists()) {
            $this->revoke($user);
        }
    }
}
