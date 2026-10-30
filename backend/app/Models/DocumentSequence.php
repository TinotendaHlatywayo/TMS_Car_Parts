<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use HasUlids;

    protected $fillable = ['branch_id', 'document_type', 'next_number'];
}
