<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First owner account
    |--------------------------------------------------------------------------
    |
    | Read by the seeder to create the store owner. An existing account is left
    | untouched, so re-seeding never resets a password changed in the panel.
    |
    */

    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo catalog
    |--------------------------------------------------------------------------
    |
    | Seeds the demo's categories, products, offers and banners. Set SEED_DEMO to
    | false on the live site once the real catalog is in place.
    |
    */

    'seed_demo' => (bool) env('SEED_DEMO', true),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Leave empty on a normal cPanel host. Behind Cloudflare or another proxy,
    | every visitor would otherwise appear to share one address and the rate
    | limits would block real customers together. Comma separated, or * to
    | trust whatever proxy is in front of the site.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

];
