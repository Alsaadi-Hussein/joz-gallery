<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'settings';

    private const DEFAULT_LOW_STOCK_THRESHOLD = 5;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return $settings[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function lowStockThreshold(): int
    {
        return (int) static::get('low_stock_threshold', self::DEFAULT_LOW_STOCK_THRESHOLD);
    }
}
