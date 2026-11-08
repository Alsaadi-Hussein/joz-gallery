<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\Catalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const OFFERS_SHOWN = 5;

    private const PRODUCTS_SHOWN = 4;

    public function __invoke(Catalog $catalog): View
    {
        return view('shop.home', [
            'banners' => Banner::query()->where('is_active', true)->orderBy('sort_order')->with('media')->get(),
            'sections' => $catalog->sections(),
            'picksTotal' => $catalog->featured()->count(),
            'offerProducts' => $catalog->forCards($catalog->onOffer(self::OFFERS_SHOWN)),
            'offerEndsAt' => $catalog->nextOfferEnd(),
            'bestSellers' => $catalog->forCards($catalog->bestSelling(self::PRODUCTS_SHOWN)),
            'newArrivals' => $catalog->forCards($catalog->newArrivals(self::PRODUCTS_SHOWN)),
        ]);
    }
}
