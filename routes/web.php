<?php

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CollectionController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\RobotsController;
use App\Http\Controllers\Shop\SearchController;
use Illuminate\Support\Facades\Route;

$storefront = function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/category/{category:slug}', CategoryController::class)->name('category');
    Route::get('/product/{product:slug}', ProductController::class)->name('product');
    Route::get('/offers', [CollectionController::class, 'offers'])->name('offers');
    Route::get('/best-sellers', [CollectionController::class, 'bestSellers'])->name('best-sellers');
    Route::get('/new', [CollectionController::class, 'newArrivals'])->name('new');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:60,1')->name('search.suggest');
    Route::get('/page/{page:slug}', PageController::class)->name('page');
    Route::post('/cart/preview', [CartController::class, 'preview'])->middleware('throttle:120,1')->name('cart.preview');

    // Linked from the header and footer; the real pages arrive with checkout in Phase 3.
    Route::view('/cart', 'shop.coming-soon')->name('cart');
    Route::view('/checkout', 'shop.coming-soon')->name('checkout');
    Route::view('/track', 'shop.coming-soon')->name('track');
};

Route::middleware('locale:ar')->group($storefront);
Route::prefix('en')->name('en.')->middleware('locale:en')->group($storefront);

Route::get('/robots.txt', RobotsController::class)->name('robots');
