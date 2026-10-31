<?php

namespace App\Providers;

use App\Services\PriceService;
use App\View\Composers\StorefrontComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    private const CHECKOUTS_PER_MINUTE = 5;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PriceService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers()->symbols());

        // Bound to the pages, not the layout: a page renders first and passes its data up.
        View::composer(['shop.*', 'errors::404', 'errors::429'], StorefrontComposer::class);

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(self::CHECKOUTS_PER_MINUTE)->by($request->ip()));
    }
}
