<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog;
use App\Services\PriceService;
use App\Support\Money;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __invoke(Product $product, Catalog $catalog, PriceService $prices): View
    {
        abort_unless(Product::query()->visible()->whereKey($product->id)->exists(), 404);

        $product->load(['category.parent', 'variants', 'media']);

        return view('shop.product', [
            'product' => $product,
            'quote' => $prices->quote($product),
            'variants' => $product->variants->map(function (ProductVariant $variant) use ($product, $prices) {
                $quote = $prices->quote($product, $variant);

                return [
                    'id' => $variant->id,
                    'label' => $variant->label(),
                    'stock' => $variant->stock,
                    'now' => Money::format($quote->final),
                    'old' => $quote->hasDiscount() ? Money::format($quote->base) : '',
                ];
            }),
            'related' => $catalog->forCards($catalog->related($product)),
        ]);
    }
}
