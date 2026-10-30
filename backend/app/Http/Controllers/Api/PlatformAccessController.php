<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAccessController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'is_platform_admin' => $request->user()->is_platform_admin,
            'permissions' => $request->user()->platformPermissions(),
            'labels' => Permissions::platformLabels(),
        ]);
    }
}
