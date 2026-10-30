<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MoneyMovement extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id',
        'payment_account_id',
        'movement_type',
        'amount_minor',
        'currency_code',
        'direction',
        'source',
        'source_id',
        'reference',
        'reason',
        'related_movement_id',
        'transfer_group_id',
        'reverses_movement_id',
        'idempotency_key',
        'request_hash',
        'actor_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_movement_id');
    }

    public function relatedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_movement_id');
    }
}
