<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;

class BestSellers
{
    private const CACHE_KEY = 'best-sellers';

    private const CACHE_SECONDS = 3600;

    /**
     * Quantity sold in delivered orders per product id, best seller first.
     *
     * @return array<int, int>
     */
    public function quantities(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return OrderItem::query()
                ->whereNotNull('product_id')
                ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::Delivered))
                ->selectRaw('product_id, SUM(qty) as sold')
                ->groupBy('product_id')
                ->orderByDesc('sold')
                ->pluck('sold', 'product_id')
                ->map(fn (mixed $sold) => (int) $sold)
                ->all();
        });
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
