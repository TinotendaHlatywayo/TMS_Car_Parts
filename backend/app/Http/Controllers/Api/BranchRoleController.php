<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;

class BranchRoleController extends Controller
{
    public function index(): JsonResponse
    {
        $labels = Permissions::branchLabels();

        return response()->json([
            'roles' => Roles::catalogue(),
            'permissions' => array_map(
                fn (string $permission): array => [
                    'name' => $permission,
                    'label' => $labels[$permission] ?? $permission,
                ],
                Permissions::branch(),
            ),
        ]);
    }
}
