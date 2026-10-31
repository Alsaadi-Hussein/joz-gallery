<?php

use App\Enums\OrderStatus;
use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(staff());
});

function orderWith(Product $product, int $qty, ?ProductVariant $variant = null, array $attributes = []): Order
{
    $order = Order::factory()->create($attributes);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'variant_id' => $variant?->id,
        'variant_snapshot' => $variant?->label(),
        'qty' => $qty,
    ]);

    return $order;
}

describe('the list', function () {
    it('shows orders newest first', function () {
        $older = Order::factory()->create(['created_at' => now()->subDay()]);
        $newer = Order::factory()->create();

        Livewire::test(ListOrders::class)->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    });

    it('searches number, phone and name', function (string $field, string $value) {
        $match = Order::factory()->create([$field => $value]);
        $other = Order::factory()->create();

        Livewire::test(ListOrders::class)
            ->searchTable($value)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    })->with([
        ['number', 'JG-26-7777'],
        ['phone', '07905550000'],
        ['name', 'نور الهدى'],
    ]);

    it('filters by status', function () {
        $new = Order::factory()->create();
        $delivered = Order::factory()->create(['status' => OrderStatus::Delivered]);

        Livewire::test(ListOrders::class)
            ->filterTable('status', OrderStatus::Delivered->value)
            ->assertCanSeeTableRecords([$delivered])
            ->assertCanNotSeeTableRecords([$new]);
    });

    it('filters by governorate', function () {
        $basra = Governorate::factory()->create(['name_ar' => 'البصرة']);
        $match = Order::factory()->for($basra)->create();
        $other = Order::factory()->create();

        Livewire::test(ListOrders::class)
            ->filterTable('governorate_id', $basra->id)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    });

    it('filters by date range', function () {
        $inside = Order::factory()->create(['created_at' => '2026-10-15 12:00:00']);
        $before = Order::factory()->create(['created_at' => '2026-10-01 12:00:00']);
        $after = Order::factory()->create(['created_at' => '2026-10-30 12:00:00']);

        Livewire::test(ListOrders::class)
            ->filterTable('created_at', ['from' => '2026-10-10', 'until' => '2026-10-20'])
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$before, $after]);
    });
});

describe('the order page', function () {
    it('shows the customer, items and totals', function () {
        $order = Order::factory()->create(['name' => 'زينب كريم', 'phone' => '07501112222', 'area' => 'الجادرية', 'total' => 123_000]);
        OrderItem::factory()->for($order)->create(['name_snapshot' => 'سرير بلوط', 'variant_snapshot' => 'أبيض / 180']);

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('زينب كريم')
            ->assertSee('07501112222')
            ->assertSee('الجادرية')
            ->assertSee('سرير بلوط')
            ->assertSee('أبيض / 180')
            ->assertSee('123,000 د.ع');
    });

    it('has no way to add or edit orders by hand', function () {
        expect(staff()->can('create', Order::class))->toBeFalse()
            ->and(owner()->can('create', Order::class))->toBeFalse()
            ->and(OrderResource::getPages())->toHaveKeys(['index', 'view'])->not->toHaveKeys(['create', 'edit']);
    });
});

describe('changing status', function () {
    it('moves an order forward', function () {
        $order = Order::factory()->create();

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('changeStatus')->table($order), ['status' => OrderStatus::Confirmed->value])
            ->assertHasNoFormErrors()
            ->assertNotified();

        expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
    });

    it('works from the order page', function () {
        $order = Order::factory()->create();

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('changeStatus', ['status' => OrderStatus::Shipping->value]);

        expect($order->refresh()->status)->toBe(OrderStatus::Shipping);
    });

    it('needs a status', function () {
        $order = Order::factory()->create();

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('changeStatus')->table($order), ['status' => null])
            ->assertHasFormErrors(['status' => 'required']);
    });

    it('does not move stock unless the order is cancelled', function () {
        $product = Product::factory()->create(['stock' => 5]);
        $order = orderWith($product, 2);

        $order->changeStatus(OrderStatus::Shipping);
        $order->changeStatus(OrderStatus::Delivered);

        expect($product->refresh()->stock)->toBe(5);
    });
});

describe('cancelling', function () {
    it('returns the product stock', function () {
        $product = Product::factory()->create(['stock' => 3]);
        $order = orderWith($product, 2);

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('changeStatus')->table($order), ['status' => OrderStatus::Cancelled->value]);

        expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
            ->and($product->refresh()->stock)->toBe(5);
    });

    it('returns the variant stock, not the product stock', function () {
        $product = Product::factory()->create(['stock' => 3]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 1]);
        $order = orderWith($product, 2, $variant);

        $order->changeStatus(OrderStatus::Cancelled);

        expect($variant->refresh()->stock)->toBe(3)
            ->and($product->refresh()->stock)->toBe(3);
    });

    it('returns the coupon use', function () {
        $coupon = Coupon::factory()->create(['used_count' => 4]);
        $order = orderWith(Product::factory()->create(), 1, attributes: ['coupon_id' => $coupon->id]);

        $order->changeStatus(OrderStatus::Cancelled);

        expect($coupon->refresh()->used_count)->toBe(3);
    });

    it('never takes a coupon below zero', function () {
        $coupon = Coupon::factory()->create(['used_count' => 0]);
        $order = orderWith(Product::factory()->create(), 1, attributes: ['coupon_id' => $coupon->id]);

        $order->changeStatus(OrderStatus::Cancelled);

        expect($coupon->refresh()->used_count)->toBe(0);
    });

    it('copes with a product that has been deleted', function () {
        $product = Product::factory()->create();
        $order = orderWith($product, 2);
        $product->delete();

        $order->refresh()->changeStatus(OrderStatus::Cancelled);

        expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
            ->and($order->items)->toHaveCount(1);
    });

    it('does not add a deleted variant back to its product', function () {
        $product = Product::factory()->create(['stock' => 0]);
        $variant = ProductVariant::factory()->for($product)->create();
        $order = orderWith($product, 2, $variant);
        $variant->delete();

        $order->refresh()->changeStatus(OrderStatus::Cancelled);

        expect($product->refresh()->stock)->toBe(0);
    });

    it('is final', function () {
        $product = Product::factory()->create(['stock' => 3]);
        $order = orderWith($product, 2, attributes: ['status' => OrderStatus::Cancelled]);

        Livewire::test(ListOrders::class)->assertActionHidden(TestAction::make('changeStatus')->table($order));

        expect(fn () => $order->changeStatus(OrderStatus::New))->toThrow(LogicException::class)
            ->and($product->refresh()->stock)->toBe(3);
    });

    it('cannot return stock twice from a stale page', function () {
        $product = Product::factory()->create(['stock' => 3]);
        $order = orderWith($product, 2);
        $stale = Order::query()->find($order->id);

        $order->changeStatus(OrderStatus::Cancelled);

        expect(fn () => $stale->changeStatus(OrderStatus::Cancelled))->toThrow(LogicException::class)
            ->and($product->refresh()->stock)->toBe(5);
    });
});

describe('who can delete', function () {
    it('hides delete from staff', function () {
        $order = Order::factory()->create();

        Livewire::test(ListOrders::class)->assertActionHidden(TestAction::make('delete')->table($order));
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionHidden('delete');
    });

    it('lets the owner delete', function () {
        $this->actingAs(owner());
        $order = Order::factory()->create();

        Livewire::test(ListOrders::class)->callAction(TestAction::make('delete')->table($order));

        expect(Order::query()->count())->toBe(0);
    });
});

describe('invoice', function () {
    it('prints for staff', function () {
        $order = Order::factory()->create(['number' => 'JG-26-0033', 'name' => 'أحمد سالم', 'total' => 77_000]);
        OrderItem::factory()->for($order)->create(['name_snapshot' => 'مصباح أرضي']);

        $this->get(route('filament.admin.orders.invoice', $order))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('JG-26-0033')
            ->assertSee('أحمد سالم')
            ->assertSee('مصباح أرضي')
            ->assertSee('77,000 د.ع')
            ->assertSee('window.print()', false);
    });

    it('opens in a new tab', function () {
        $order = Order::factory()->create();

        Livewire::test(ListOrders::class)
            ->assertActionHasUrl(TestAction::make('invoice')->table($order), route('filament.admin.orders.invoice', $order))
            ->assertActionShouldOpenUrlInNewTab(TestAction::make('invoice')->table($order));
    });

    it('is closed to guests', function () {
        $order = Order::factory()->create();

        auth()->logout();

        $this->get(route('filament.admin.orders.invoice', $order))->assertRedirect('/admin/login');
    });
});

describe('export', function () {
    it('produces Excel only', function () {
        $export = new Export(['exporter' => OrderExporter::class, 'total_rows' => 1, 'file_disk' => 'local']);

        expect((new OrderExporter($export, [], []))->getFormats())->toBe([ExportFormat::Xlsx]);
    });

    it('keeps customer data off the public disk', function () {
        Storage::fake('local');
        Storage::fake('public');

        $export = new Export(['exporter' => OrderExporter::class, 'total_rows' => 1, 'file_disk' => 'local']);

        expect((new OrderExporter($export, [], []))->getFileDisk())->toBe('local');
    });

    it('is offered on the orders list', function () {
        Livewire::test(ListOrders::class)->assertActionVisible(TestAction::make('export')->table());
    });

    it('writes the columns the store needs', function () {
        $order = Order::factory()->create(['number' => 'JG-26-0050', 'status' => OrderStatus::Delivered]);
        OrderItem::factory()->for($order)->create(['name_snapshot' => 'كرسي', 'qty' => 2, 'line_total' => 40_000]);

        $columns = collect(OrderExporter::getColumns())->map->getName();

        expect($columns)->toContain('number', 'created_at', 'status', 'name', 'phone', 'governorate.name_ar', 'area', 'items', 'total');
    });
});
