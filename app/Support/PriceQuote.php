<?php

namespace App\Support;

use App\Models\Offer;

class PriceQuote
{
    public function __construct(
        public readonly int $base,
        public readonly int $final,
        public readonly ?Offer $offer = null,
    ) {}

    public function discount(): int
    {
        return $this->base - $this->final;
    }

    public function hasDiscount(): bool
    {
        return $this->final < $this->base;
    }

    /**
     * Rounded percentage for the sale badge, also for fixed-amount offers.
     */
    public function percentOff(): int
    {
        return $this->base > 0 ? (int) round($this->discount() * 100 / $this->base) : 0;
    }
}
