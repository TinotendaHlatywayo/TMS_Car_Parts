<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'sale_id',
        'product_id',
        'product_name',
        'sku',
        'unit_id',
        'unit_code',
        'quantity',
        'unit_price_minor',
        'discount_minor',
        'tax_minor',
        'total_minor',
        'currency_code',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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
