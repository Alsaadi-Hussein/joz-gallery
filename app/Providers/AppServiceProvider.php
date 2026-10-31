<?php

namespace App\Providers;

use App\Services\PriceService;
use App\View\Composers\StorefrontComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        URL::forceHttps(str_starts_with((string) config('app.url'), 'https://'));

        if ($proxies = config('joz.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : explode(',', $proxies));
        }

        // Bound to the pages, not the layout: a page renders first and passes its data up.
        View::composer(['shop.*', 'errors::404', 'errors::429'], StorefrontComposer::class);

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(self::CHECKOUTS_PER_MINUTE)->by($request->ip()));
    }
}
