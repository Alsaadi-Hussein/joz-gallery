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
    | Seeds the demo's categories, products, offers, banners and its working JOZ10
    | coupon. Off unless SEED_DEMO asks for it, so a setting forgotten on the live
    | site can never fill the store with demo data.
    |
    */

    'seed_demo' => (bool) env('SEED_DEMO', false),

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

    /*
    |--------------------------------------------------------------------------
    | Health check
    |--------------------------------------------------------------------------
    |
    | A monitor sending this in the X-Health-Token header gets the full figures
    | from /api/health. Everyone else only gets ok or down. Leave it empty to
    | keep the figures private from everybody.
    |
    */

    'health_token' => env('HEALTH_TOKEN'),

];
