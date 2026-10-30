<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['branch_id', 'user_id', 'action', 'entity_type', 'entity_id', 'ip_address', 'user_agent', 'before', 'after'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}
