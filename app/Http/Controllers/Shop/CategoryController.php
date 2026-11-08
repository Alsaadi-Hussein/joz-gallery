<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog;
use App\Support\CatalogFilters;
use App\Support\SectionNav;
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

        $category->load('parent.parent');

        return view('shop.category', [
            'category' => $category,
            'nav' => SectionNav::make($catalog->sections(), SectionNav::rootOf($category)->id),
            'subcategories' => $category->children()->active()->orderBy('sort_order')->get(),
            'filters' => $filters,
            'facets' => $catalog->facets($products),
            'products' => $catalog->paginate($matching, $request),
        ]);
    }
}
