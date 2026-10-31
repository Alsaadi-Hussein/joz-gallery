<?php

namespace App\Providers\Filament;

use App\Http\Controllers\Admin\InvoiceController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Written by hand because Color::hex() keeps only the hue and turns the brand's
     * dark, muted walnut into a bright orange. Shade 600 is what buttons use.
     */
    private const WALNUT = [
        50 => 'oklch(0.975 0.006 40)',
        100 => 'oklch(0.94 0.012 40)',
        200 => 'oklch(0.87 0.02 40)',
        300 => 'oklch(0.77 0.03 40)',
        400 => 'oklch(0.64 0.04 40)',
        500 => 'oklch(0.52 0.044 40)',
        600 => '#5b3e34',
        700 => 'oklch(0.34 0.04 40)',
        800 => 'oklch(0.29 0.034 40)',
        900 => 'oklch(0.25 0.028 40)',
        950 => 'oklch(0.19 0.02 40)',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn (): string => __('admin.brand'))
            ->brandLogo(fn (): string => asset('assets/img/brand/logo-walnut.png'))
            ->brandLogoHeight('3rem')
            ->favicon(fn (): string => asset('assets/img/brand/favicon.png'))
            ->font('Tajawal')
            ->darkMode(false)
            ->colors([
                'primary' => self::WALNUT,
            ])
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('admin.nav.sales')),
                NavigationGroup::make(fn (): string => __('admin.nav.catalog')),
                NavigationGroup::make(fn (): string => __('admin.nav.content')),
                NavigationGroup::make(fn (): string => __('admin.nav.store')),
            ])
            // Excel exports finish in the background and arrive as a notification.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->authenticatedRoutes(function (): void {
                Route::get('orders/{order}/invoice', InvoiceController::class)->name('orders.invoice');
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
