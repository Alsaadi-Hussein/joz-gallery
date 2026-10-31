<?php

namespace App\Support;

class OrderTotals
{
    public function __construct(
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly int $deliveryFee,
    ) {}

    public function total(): int
    {
        return $this->subtotal - $this->discount + $this->deliveryFee;
    }
}
