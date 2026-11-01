<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived lists the catalog would otherwise rebuild from every product on each
 * request. Only ids and search text go in here, never prices or stock, so shoppers
 * always see live figures. It uses the site's cache store, so a host with Redis or
 * Memcached gets this in memory by changing CACHE_STORE alone.
 */
class CatalogCache
{
    private const VERSION_KEY = 'catalog.version';

    // Also the longest a scheduled offer can run late, as nothing is saved when it starts.
    private const SECONDS = 60;

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function remember(string $key, Closure $build): mixed
    {
        return Cache::remember('catalog.'.$this->version().'.'.$key, self::SECONDS, $build);
    }

    /**
     * Retires every list at once, so an edit in the admin shows straight away.
     */
    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(8));
    }

    private function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn () => Str::random(8));
    }
}
