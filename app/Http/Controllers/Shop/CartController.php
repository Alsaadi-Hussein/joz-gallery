<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartPreviewRequest;
use App\Models\Product;
use App\Services\PriceService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    /**
     * Turns the browser's cart, which holds ids and quantities only, into lines
     * with the server's current names, prices and stock. Items that can no longer
     * be bought are left out, so the browser drops them.
     */
    public function preview(CartPreviewRequest $request, PriceService $prices): JsonResponse
    {
        $items = collect($request->validated('items'));

        $products = Product::query()->visible()
            ->with(['variants', 'media'])
            ->whereKey($items->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $lines = $items->map(function (array $item) use ($products, $prices) {
            $product = $products->get($item['product_id']);
            $variantId = $item['variant_id'] ?? null;
            $variant = $product?->variants->firstWhere('id', $variantId);

            // A product with variants can only be bought through one of them.
            if (! $product || ($product->variants->isNotEmpty() && ! $variant) || ($variantId && ! $variant)) {
                return null;
            }

            $stock = $variant?->stock ?? $product->stock;
            $qty = min($item['qty'], $stock);

            if ($qty < 1) {
                return null;
            }

            $unitPrice = $prices->finalPrice($product, $variant);

            return [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $product->localized('name'),
                'variant' => $variant?->label(),
                'image' => $product->imageUrl(),
                'url' => shop_route('product', $product),
                'qty' => $qty,
                'stock' => $stock,
                'line_total' => $unitPrice * $qty,
                'line_total_formatted' => Money::format($unitPrice * $qty),
            ];
        })->filter()->values();

        $subtotal = $lines->sum('line_total');

        return response()->json([
            'lines' => $lines,
            'count' => $lines->sum('qty'),
            'subtotal' => $subtotal,
            'subtotal_formatted' => Money::format($subtotal),
        ]);
    }
}
