<?php

namespace App\Providers;

use App\Services\PriceService;
use App\View\Composers\StorefrontComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
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
        View::composer(['shop.*', 'errors::404'], StorefrontComposer::class);
    }
}
