<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Catalog;
use App\Support\SectionNav;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function offers(Request $request, Catalog $catalog): View
    {
        return view('shop.collection', [
            'title' => __('shop.nav.offers'),
            'subtitle' => __('shop.home.offers_sub'),
            'products' => $catalog->paginate($catalog->onOffer(), $request),
            'offerEndsAt' => $catalog->nextOfferEnd(),
        ]);
    }

    public function bestSellers(Request $request, Catalog $catalog): View
    {
        return view('shop.collection', [
            'title' => __('shop.nav.best_sellers'),
            'subtitle' => __('shop.home.best_sub'),
            'products' => $catalog->paginate($catalog->bestSelling(), $request),
            'ranked' => true,
        ]);
    }

    public function newArrivals(Request $request, Catalog $catalog): View
    {
        return view('shop.collection', [
            'title' => __('shop.nav.new'),
            'subtitle' => __('shop.home.new_sub'),
            'products' => $catalog->paginate($catalog->newArrivals(), $request),
        ]);
    }

    /**
     * "Joz Picks": the featured products, shown as one more section with the
     * section tabs on top.
     */
    public function picks(Request $request, Catalog $catalog): View
    {
        return view('shop.picks', [
            'nav' => SectionNav::make($catalog->sections(), SectionNav::PICKS),
            'products' => $catalog->paginate($catalog->featured(), $request),
        ]);
    }
}
