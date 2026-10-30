<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $branchUser = $request->attributes->get('branch_user');

        if (! $branchUser || ! Permissions::isBranch($permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        if (! $branchUser->hasPermission($permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
