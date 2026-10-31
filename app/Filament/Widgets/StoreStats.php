<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    // Shared hosting: a check once a minute is plenty for a furniture store.
    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $today = Order::query()
            ->whereDate('created_at', today())
            ->where('status', '!=', OrderStatus::Cancelled)
            ->count();

        $monthSales = (int) Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $awaiting = Order::query()->where('status', OrderStatus::New)->count();

        return [
            Stat::make(__('admin.dashboard.today'), number_format($today))
                ->icon(Heroicon::OutlinedShoppingBag),
            Stat::make(__('admin.dashboard.month_sales'), Money::format($monthSales))
                ->description(__('admin.dashboard.month_sales_hint'))
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make(__('admin.dashboard.awaiting'), number_format($awaiting))
                ->description(__('admin.dashboard.awaiting_hint'))
                ->color($awaiting > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedClock)
                ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => OrderStatus::New->value]]])),
        ];
    }
}
