<?php

namespace App\Support;

use App\Models\User;

final class PriceCalculatorFactory
{
    public function make(?User $approver = null, ?string $overrideReason = null): PriceCalculator
    {
        return new PriceCalculator(
            app(AuditLogger::class),
            $approver,
            $overrideReason,
        );
    }
}
