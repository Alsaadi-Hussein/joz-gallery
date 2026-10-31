<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CartLine;
use Illuminate\Support\Collection;

class Cart
{
    public function __construct(private readonly PriceService $prices) {}

    /**
     * Turns the browser's cart, which holds ids and quantities only, into lines with
     * the server's current prices and stock. Items that can no longer be bought are
     * left out, and quantities are cut down to what is in stock.
     *
     * @param  array<int, array{product_id: int, variant_id?: int|null, qty: int}>  $items
     * @return Collection<int, CartLine>
     */
    public function lines(array $items): Collection
    {
        $items = self::merge($items);

        $products = Product::query()->visible()
            ->with(['variants', 'media'])
            ->whereKey(array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($products) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    return null;
                }

                $variant = $product->variants->firstWhere('id', $item['variant_id']);

                if (! self::isValidChoice($product, $variant, $item['variant_id'])) {
                    return null;
                }

                $qty = min($item['qty'], $variant->stock ?? $product->stock);

                return $qty > 0
                    ? new CartLine($product, $variant, $qty, $this->prices->finalPrice($product, $variant))
                    : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Joins repeated lines, so sending the same product twice cannot get past a
     * stock check made per line.
     *
     * @param  array<int, array{product_id: int, variant_id?: int|null, qty: int}>  $items
     * @return list<array{product_id: int, variant_id: int|null, qty: int}>
     */
    public static function merge(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $key = $item['product_id'].':'.($item['variant_id'] ?? '');

            $merged[$key] ??= ['product_id' => $item['product_id'], 'variant_id' => $item['variant_id'] ?? null, 'qty' => 0];
            $merged[$key]['qty'] += $item['qty'];
        }

        return array_values($merged);
    }

    /**
     * A product with variants can only be bought through one of them, and a variant
     * id must belong to the product it was sent with.
     */
    public static function isValidChoice(Product $product, ?ProductVariant $variant, ?int $variantId): bool
    {
        return $variantId === null
            ? $product->variants->isEmpty()
            : $variant !== null;
    }
}
