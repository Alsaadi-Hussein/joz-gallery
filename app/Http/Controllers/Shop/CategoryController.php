<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog;
use App\Support\CatalogFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __invoke(Request $request, Category $category, Catalog $catalog): View
    {
        abort_unless($category->is_active, 404);

        $filters = CatalogFilters::fromRequest($request);
        $products = $catalog->inCategory($category);
        $matching = $catalog->sort($catalog->filter($products, $filters), $filters->sort);

        return view('shop.category', [
            'category' => $category->load('parent'),
            'subcategories' => $category->children()->active()->orderBy('sort_order')->get(),
            'filters' => $filters,
            'facets' => $catalog->facets($products),
            'products' => $catalog->paginate($matching, $request),
        ]);
    }
}
