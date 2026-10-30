<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBillPayment extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'supplier_bill_id',
        'payment_account_id',
        'amount_minor',
        'currency_code',
        'paid_at',
        'reference',
        'actor_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'paid_at' => 'date',
        'created_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
