<?php

namespace App\Support\Pricing;

final readonly class CartPriceResult
{
    /**
     * @param  array<int, PriceLineResult>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $netMinor,
        public int $taxMinor,
        public int $totalMinor,
        public string $currencyCode,
        public string $pricedAt,
        public bool $requiresApproval,
    ) {}
}
