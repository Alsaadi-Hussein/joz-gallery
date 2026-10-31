<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog;
use App\Services\PriceService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    private const SUGGESTIONS = 6;

    public function index(Request $request, Catalog $catalog): View
    {
        $term = $this->term($request);

        return view('shop.search', [
            'term' => $term,
            'products' => $catalog->paginate($catalog->search($term), $request),
        ]);
    }

    /**
     * Results for the search overlay as the shopper types.
     */
    public function suggest(Request $request, Catalog $catalog, PriceService $prices): JsonResponse
    {
        $products = $catalog->forCards($catalog->search($this->term($request), self::SUGGESTIONS));

        return response()->json([
            'results' => $products->map(fn (Product $product) => [
                'name' => $product->localized('name'),
                'section' => $product->category->localized('name'),
                'price' => Money::format($prices->finalPrice($product)),
                'image' => $product->imageUrl(),
                'url' => shop_route('product', $product),
            ])->values(),
        ]);
    }

    private function term(Request $request): string
    {
        $term = $request->query('q');

        return is_string($term) ? trim($term) : '';
    }
}
