<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'quantity',
        'amount_minor',
        'currency_code',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'amount_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
