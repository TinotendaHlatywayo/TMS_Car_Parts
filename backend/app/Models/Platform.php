<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Platform extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'currency_code', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];
}
