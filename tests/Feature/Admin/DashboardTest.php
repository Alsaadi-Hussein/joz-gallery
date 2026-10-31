<?php

use App\Enums\OrderStatus;
use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\StoreStats;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 12:00:00');

    $this->actingAs(staff());
});

function stats(): array
{
    $widget = new class extends StoreStats
    {
        public function values(): array
        {
            return array_map(fn ($stat) => (string) $stat->getValue(), $this->getStats());
        }
    };

    return $widget->values();
}

it('shows on the dashboard for staff and the owner', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(StoreStats::class)
        ->assertSeeLivewire(LowStockProducts::class);
});

describe('stats', function () {
    it('counts today, leaving out cancelled orders', function () {
        Order::factory()->count(2)->create();
        Order::factory()->create(['status' => OrderStatus::Cancelled]);
        Order::factory()->create(['created_at' => now()->subDay()]);

        expect(stats()[0])->toBe('2');
    });

    it('adds up delivered sales for this month only', function () {
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total' => 100_000]);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total' => 50_000, 'created_at' => '2026-11-01 00:30:00']);
        Order::factory()->create(['status' => OrderStatus::Shipping, 'total' => 900_000]);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total' => 700_000, 'created_at' => '2026-10-31 23:30:00']);

        expect(stats()[1])->toBe('150,000 د.ع');
    });

    it('counts orders waiting to be confirmed, however old', function () {
        Order::factory()->create();
        Order::factory()->create(['created_at' => now()->subWeek()]);
        Order::factory()->create(['status' => OrderStatus::Confirmed]);

        expect(stats()[2])->toBe('2');
    });

    it('starts at zero', function () {
        expect(stats())->toBe(['0', '0 د.ع', '0']);
    });

    it('checks once a minute, not every few seconds', function () {
        expect((new ReflectionProperty(StoreStats::class, 'pollingInterval'))->getDefaultValue())->toBe('60s');
    });
});

describe('low stock list', function () {
    it('lists products at or under the threshold', function () {
        Setting::put('low_stock_threshold', '3');
        $low = Product::factory()->create(['stock' => 3]);
        $fine = Product::factory()->create(['stock' => 4]);

        Livewire::test(LowStockProducts::class)
            ->assertCanSeeTableRecords([$low])
            ->assertCanNotSeeTableRecords([$fine]);
    });

    it('uses variant stock when there are variants', function () {
        $low = Product::factory()->create(['stock' => 50]);
        ProductVariant::factory()->for($low)->create(['stock' => 1]);
        $fine = Product::factory()->create(['stock' => 0]);
        ProductVariant::factory()->for($fine)->create(['stock' => 40]);

        Livewire::test(LowStockProducts::class)
            ->assertCanSeeTableRecords([$low])
            ->assertCanNotSeeTableRecords([$fine]);
    });

    it('leaves out hidden products', function () {
        $hidden = Product::factory()->create(['stock' => 0, 'is_active' => false]);

        Livewire::test(LowStockProducts::class)->assertCanNotSeeTableRecords([$hidden]);
    });

    it('says so when everything is fine', function () {
        Livewire::test(LowStockProducts::class)->assertSee(__('admin.dashboard.low_stock_empty'));
    });
});
