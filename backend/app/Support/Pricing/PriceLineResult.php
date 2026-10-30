<?php

namespace App\Support\Pricing;

final readonly class PriceLineResult
{
    /**
     * @param  array<int, string>  $approvalReasons
     */
    public function __construct(
        public string $productId,
        public string $unitId,
        public string $currencyCode,
        public int $unitPriceMinor,
        public string $quantity,
        public int $floorPriceMinor,
        public float $discountPercent,
        public float $taxRatePercent,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $floorSubtotalMinor,
        public int $netMinor,
        public int $taxMinor,
        public int $totalMinor,
        public bool $requiresApproval,
        public array $approvalReasons,
    ) {}
}
