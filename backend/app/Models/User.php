<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active', 'is_platform_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function branchMemberships(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    public function activeBranchMemberships(): HasMany
    {
        return $this->branchMemberships()->where('is_active', true);
    }

    public function branchMembershipFor(string $branchId): ?BranchUser
    {
        return $this->activeBranchMemberships()
            ->where('branch_id', $branchId)
            ->first();
    }

    public function isMemberOfBranch(string $branchId): bool
    {
        return $this->branchMembershipFor($branchId) !== null;
    }

    /**
     * @return array<int, string>
     */
    public function platformPermissions(): array
    {
        return $this->is_platform_admin ? Permissions::platform() : [];
    }
}
