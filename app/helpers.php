<?php

use App\Models\Setting;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;

if (! function_exists('shop_route')) {
    /**
     * URL of a storefront route in the given language, the current one by default.
     */
    function shop_route(string $name, mixed $parameters = [], ?string $locale = null): string
    {
        $prefix = ($locale ?? app()->getLocale()) === 'en' ? 'en.' : '';

        return route($prefix.$name, $parameters);
    }
}

if (! function_exists('shop_link')) {
    /**
     * Puts a path saved in the admin, such as a banner link, under the current
     * language. Full URLs are left alone.
     */
    function shop_link(?string $link): string
    {
        if (blank($link)) {
            return shop_route('home');
        }

        if (! str_starts_with($link, '/')) {
            return $link;
        }

        return url((app()->getLocale() === 'en' ? '/en' : '').$link);
    }
}

if (! function_exists('alternate_url')) {
    /**
     * The current page in another language, or the home page when there is no
     * matching route, as on a 404.
     */
    function alternate_url(string $locale, bool $keepQuery = true): string
    {
        $route = request()->route();
        $name = $route instanceof Route ? $route->getName() : null;

        if ($name === null) {
            return shop_route('home', locale: $locale);
        }

        $url = shop_route(preg_replace('/^en\./', '', $name), $route->parameters(), $locale);
        $query = request()->getQueryString();

        return $keepQuery && $query ? "{$url}?{$query}" : $url;
    }
}

if (! function_exists('canonical_url')) {
    /**
     * The address search engines should index for a paginated list. Filters and
     * sorting are left out because they show the same products again.
     */
    function canonical_url(LengthAwarePaginator $paginator): string
    {
        $page = $paginator->currentPage();

        return url()->current().($page > 1 ? "?page={$page}" : '');
    }
}

if (! function_exists('whatsapp_url')) {
    /**
     * Link that opens a chat with the store, with an optional message ready to send.
     */
    function whatsapp_url(?string $message = null): string
    {
        $url = 'https://wa.me/'.Setting::get('whatsapp_number');

        return $url.'?text='.rawurlencode($message ?? __('shop.whatsapp.greeting'));
    }
}
