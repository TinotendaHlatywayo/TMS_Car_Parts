<?php

namespace App\Support;

use App\Models\StockMovement;

final readonly class StockPostingResult
{
    public function __construct(
        public StockMovement $movement,
        public bool $replayed,
    ) {}
}
