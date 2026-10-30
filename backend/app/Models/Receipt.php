<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'sale_id',
        'receipt_number',
        'issued_at',
        'print_count',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'print_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
