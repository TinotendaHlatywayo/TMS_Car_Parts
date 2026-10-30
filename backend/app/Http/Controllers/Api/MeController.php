<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = $user->activeBranchMemberships()
            ->with('branch')
            ->orderBy('created_at')
            ->get()
            ->filter(fn ($membership) => $membership->branch !== null);

        $roleLabels = Roles::labels();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'is_platform_admin' => $user->is_platform_admin,
            ],
            'branches' => $memberships->map(fn ($membership): array => [
                'id' => $membership->branch_id,
                'name' => $membership->branch->name,
                'code' => $membership->branch->code,
                'role' => $membership->role,
                'role_label' => $roleLabels[$membership->role] ?? $membership->role,
                'permissions' => $membership->effectivePermissions(),
            ])->values(),
            'platform_permissions' => $user->platformPermissions(),
        ]);
    }
}
