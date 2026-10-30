<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchUserRequest;
use App\Http\Requests\UpdateBranchUserRequest;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\User;
use App\Support\AccessRevocation;
use App\Support\AuditLogger;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchUserController extends Controller
{
    public function __construct(
        private readonly AccessRevocation $revocation,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Branch $branch): JsonResponse
    {
        $roleLabels = Roles::labels();

        $memberships = $branch->memberships()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $memberships->map(fn (BranchUser $membership): array => $this->membershipPayload($membership, $roleLabels)),
        ]);
    }

    public function store(StoreBranchUserRequest $request, Branch $branch): JsonResponse
    {
        $data = $request->validated();

        $membership = DB::transaction(function () use ($data, $branch, $request): BranchUser {
            $user = User::where('email', $data['email'])->first();

            if ($user && ! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => 'This user account is inactive.',
                ]);
            }

            if (! $user) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'is_active' => true,
                ]);
                $before = null;
            } else {
                $before = $this->membershipSnapshot(
                    BranchUser::withTrashed()
                        ->where('branch_id', $branch->id)
                        ->where('user_id', $user->id)
                        ->first()
                );
            }

            $membership = BranchUser::withTrashed()
                ->where('branch_id', $branch->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership && ! $membership->trashed()) {
                throw ValidationException::withMessages([
                    'email' => 'This user already belongs to this branch.',
                ]);
            }

            if ($membership) {
                $membership->restore();
                $membership->update([
                    'role' => $data['role'],
                    'permissions' => $data['permissions'] ?? [],
                    'is_active' => $data['is_active'] ?? true,
                ]);
            } else {
                $membership = BranchUser::create([
                    'branch_id' => $branch->id,
                    'user_id' => $user->id,
                    'role' => $data['role'],
                    'permissions' => $data['permissions'] ?? [],
                    'is_active' => $data['is_active'] ?? true,
                ]);
            }

            $membership->refresh();
            $membership->load('user');

            $this->audit->log(
                $before ? 'branch_user.reactivated' : 'branch_user.created',
                $membership,
                $before,
                $this->membershipSnapshot($membership),
                $branch->id,
                $request->user(),
            );

            return $membership;
        });

        if (! $membership->is_active) {
            $this->revocation->revokeIfNoBranchAccess($membership->user);
        }

        return response()->json([
            'data' => $this->membershipPayload($membership, Roles::labels()),
        ], 201);
    }

    public function update(UpdateBranchUserRequest $request, Branch $branch, string $membership): JsonResponse
    {
        $data = $request->validated();

        $updated = DB::transaction(function () use ($data, $branch, $membership, $request): BranchUser {
            $memberships = BranchUser::where('branch_id', $branch->id)
                ->lockForUpdate()
                ->get();

            /** @var BranchUser|null $target */
            $target = $memberships->firstWhere('id', $membership);

            if (! $target) {
                abort(404);
            }

            $target->load('user');

            $newRole = $data['role'];
            $newActive = $data['is_active'] ?? $target->is_active;

            if ($target->isAdmin() && (! $newActive || $newRole !== Roles::ADMIN)) {
                $otherAdmins = $memberships
                    ->where('is_active', true)
                    ->where('role', Roles::ADMIN)
                    ->where('id', '!=', $target->id)
                    ->count();

                if ($otherAdmins === 0) {
                    throw ValidationException::withMessages([
                        'role' => 'A branch must keep at least one active administrator.',
                    ]);
                }
            }

            $before = $this->membershipSnapshot($target);

            $target->update([
                'role' => $newRole,
                'permissions' => array_key_exists('permissions', $data)
                    ? $data['permissions']
                    : ($target->permissions ?? []),
                'is_active' => $newActive,
            ]);

            $target->refresh();
            $target->load('user');

            $this->audit->log(
                'branch_user.updated',
                $target,
                $before,
                $this->membershipSnapshot($target),
                $branch->id,
                $request->user(),
            );

            return $target;
        });

        $this->revocation->revokeIfNoBranchAccess($updated->user);

        return response()->json([
            'data' => $this->membershipPayload($updated, Roles::labels()),
        ]);
    }

    public function destroy(Request $request, Branch $branch, string $membership): Response
    {
        DB::transaction(function () use ($branch, $membership, $request): void {
            $memberships = BranchUser::where('branch_id', $branch->id)
                ->lockForUpdate()
                ->get();

            /** @var BranchUser|null $target */
            $target = $memberships->firstWhere('id', $membership);

            if (! $target) {
                abort(404);
            }

            $target->load('user');

            if ($target->isAdmin()) {
                $otherAdmins = $memberships
                    ->where('is_active', true)
                    ->where('role', Roles::ADMIN)
                    ->where('id', '!=', $target->id)
                    ->count();

                if ($otherAdmins === 0) {
                    throw ValidationException::withMessages([
                        'membership' => 'A branch must keep at least one active administrator.',
                    ]);
                }
            }

            $before = $this->membershipSnapshot($target);

            $target->update(['is_active' => false]);
            $target->delete();

            $this->audit->log(
                'branch_user.removed',
                $target,
                $before,
                null,
                $branch->id,
                $request->user(),
            );

            $this->revocation->revokeIfNoBranchAccess($target->user);
        });

        return response()->noContent();
    }

    /**
     * @param  array<string, string>  $roleLabels
     * @return array<string, mixed>
     */
    private function membershipPayload(BranchUser $membership, array $roleLabels): array
    {
        return [
            'id' => $membership->id,
            'user' => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'is_active' => $membership->user->is_active,
            ],
            'role' => $membership->role,
            'role_label' => $roleLabels[$membership->role] ?? $membership->role,
            'permissions' => $membership->permissions ?? [],
            'effective_permissions' => $membership->effectivePermissions(),
            'is_active' => $membership->is_active,
            'created_at' => $membership->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function membershipSnapshot(?BranchUser $membership): ?array
    {
        if (! $membership) {
            return null;
        }

        return [
            'branch_id' => $membership->branch_id,
            'user_id' => $membership->user_id,
            'role' => $membership->role,
            'permissions' => $membership->permissions ?? [],
            'is_active' => $membership->is_active,
            'deleted_at' => $membership->deleted_at?->toIso8601String(),
        ];
    }
}
