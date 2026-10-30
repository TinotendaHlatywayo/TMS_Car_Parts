<?php

namespace App\Support\Pricing;

final readonly class PriceLine
{
    public function __construct(
        public string $productId,
        public string $unitId,
        public string $currencyCode,
        public int $unitPriceMinor,
        public string $quantity,
        public int $floorPriceMinor = 0,
        public float $discountPercent = 0,
        public float $taxRatePercent = 0,
    ) {}
}
