<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBill extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'supplier_name',
        'bill_number',
        'amount_minor',
        'currency_code',
        'bill_date',
        'due_date',
        'paid_minor',
        'status',
        'actor_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'paid_minor' => 'integer',
        'bill_date' => 'date',
        'due_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierBillPayment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
