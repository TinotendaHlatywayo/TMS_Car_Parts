<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'category',
        'description',
        'amount_minor',
        'currency_code',
        'incurred_date',
        'paid_date',
        'payment_account_id',
        'status',
        'attachment_path',
        'actor_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'incurred_date' => 'date',
        'paid_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
