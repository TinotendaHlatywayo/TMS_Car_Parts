<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'product_id',
        'unit_id',
        'conversion_factor',
        'selling_price_minor',
        'is_active',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'selling_price_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
