<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

class CartLine
{
    public function __construct(
        public readonly Product $product,
        public readonly ?ProductVariant $variant,
        public readonly int $qty,
        public readonly int $unitPrice,
    ) {}

    public function stock(): int
    {
        return $this->variant?->stock ?? $this->product->stock;
    }

    public function total(): int
    {
        return $this->unitPrice * $this->qty;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->product->id,
            'variant_id' => $this->variant?->id,
            'name' => $this->product->localized('name'),
            'variant' => $this->variant?->label(),
            'image' => $this->product->imageUrl(),
            'url' => shop_route('product', $this->product),
            'qty' => $this->qty,
            'stock' => $this->stock(),
            'line_total' => $this->total(),
            'line_total_formatted' => Money::format($this->total()),
        ];
    }
}
