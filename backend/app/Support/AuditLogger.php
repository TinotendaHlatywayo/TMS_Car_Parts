<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        Model|string $entity,
        ?array $before = null,
        ?array $after = null,
        ?string $branchId = null,
        ?User $actor = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'branch_id' => $branchId,
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entity instanceof Model ? $entity::class : $entity,
            'entity_id' => $entity instanceof Model ? (string) $entity->getKey() : '',
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'before' => $before,
            'after' => $after,
        ]);
    }
}
