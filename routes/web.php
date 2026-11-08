<?php

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CollectionController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\RobotsController;
use App\Http\Controllers\Shop\SearchController;
use App\Http\Controllers\Shop\SitemapController;
use App\Http\Controllers\Shop\TrackController;
use App\Http\Controllers\Shop\WelcomeController;
use Illuminate\Support\Facades\Route;

// The last word of each throttle gives it its own count. Without it every route shares
// one count per visitor, and typing in the search box uses up the order tracking limit.
$storefront = function (): void {
    Route::get('/', WelcomeController::class)->name('welcome');
    Route::get('/shop', HomeController::class)->name('home');
    Route::get('/category/{category:slug}', CategoryController::class)->name('category');
    Route::get('/product/{product:slug}', ProductController::class)->name('product');
    Route::get('/offers', [CollectionController::class, 'offers'])->name('offers');
    Route::get('/best-sellers', [CollectionController::class, 'bestSellers'])->name('best-sellers');
    Route::get('/new', [CollectionController::class, 'newArrivals'])->name('new');
    Route::get('/joz-picks', [CollectionController::class, 'picks'])->name('picks');
    Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:60,1,search')->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:60,1,suggest')->name('search.suggest');
    Route::get('/page/{page:slug}', PageController::class)->name('page');

    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart/preview', [CartController::class, 'preview'])->middleware('throttle:120,1,cart')->name('cart.preview');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:30,1,quote')->name('checkout.quote');
    Route::get('/order/{order:number}/thanks', [CheckoutController::class, 'thanks'])->middleware('signed:relative')->name('order.thanks');

    Route::get('/track', [TrackController::class, 'show'])->name('track');
    Route::post('/track', [TrackController::class, 'lookup'])->middleware('throttle:10,1,track')->name('track.lookup');
};

Route::middleware('locale:ar')->group($storefront);
Route::prefix('en')->name('en.')->middleware('locale:en')->group($storefront);

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
