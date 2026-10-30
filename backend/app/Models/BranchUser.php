<?php

namespace App\Models;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BranchUser extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['branch_id', 'user_id', 'role', 'permissions', 'is_active'];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, string>
     */
    public function effectivePermissions(): array
    {
        $permissions = Roles::permissions($this->role);

        foreach ((array) ($this->permissions ?? []) as $permission) {
            if (is_string($permission) && Permissions::isBranch($permission)) {
                $permissions[] = $permission;
            }
        }

        return array_values(array_unique(array_intersect($permissions, Permissions::branch())));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Roles::ADMIN;
    }
}
