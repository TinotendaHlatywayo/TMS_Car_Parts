<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

final class DocumentNumberService
{
    public function allocate(Branch $branch, string $type): string
    {
        return DB::transaction(function () use ($branch, $type): string {
            $sequence = DocumentSequence::where('branch_id', $branch->id)
                ->where('document_type', $type)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'branch_id' => $branch->id,
                    'document_type' => $type,
                    'next_number' => 1,
                ]);
            }

            $number = $sequence->next_number;
            $sequence->update(['next_number' => $number + 1]);

            return strtoupper($type).'-'.$branch->code.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        });
    }
}
