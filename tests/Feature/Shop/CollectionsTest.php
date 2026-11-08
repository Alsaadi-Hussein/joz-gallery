<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\BestSellers;

function slugs(string $url): array
{
    return test()->get($url)->assertOk()->viewData('products')->getCollection()->pluck('slug')->all();
}

function sell(Product $product, int $qty, OrderStatus $status = OrderStatus::Delivered): void
{
    OrderItem::factory()
        ->for(Order::factory()->create(['status' => $status]))
        ->create(['product_id' => $product->id, 'qty' => $qty]);
}

describe('offers', function () {
    it('lists only discounted products, biggest discount first', function () {
        $small = Product::factory()->create(['slug' => 'small']);
        $big = Product::factory()->create(['slug' => 'big']);
        Product::factory()->create(['slug' => 'full-price']);
        Offer::factory()->forProduct($small)->percent(10)->create();
        Offer::factory()->forProduct($big)->percent(40)->create();

        expect(slugs('/offers'))->toBe(['big', 'small']);
    });

    it('includes products covered by category and store offers', function () {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['slug' => 'in-category']);
        Offer::factory()->forCategory($category)->percent(10)->create();

        expect(slugs('/offers'))->toBe(['in-category']);

        Offer::query()->delete();
        Offer::factory()->percent(10)->create();

        expect(slugs('/offers'))->toBe(['in-category']);
    });

    it('counts down to the nearest ending offer', function () {
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        $soonest = now()->addDays(2)->startOfMinute();
        Offer::factory()->forProduct($first)->create(['ends_at' => now()->addDays(9)]);
        Offer::factory()->forProduct($second)->create(['ends_at' => $soonest]);

        $this->get('/offers')->assertSee('data-countdown="'.$soonest->toIso8601String().'"', false);
    });

    it('is empty and has no countdown without running offers', function () {
        Product::factory()->create();
        Offer::factory()->percent(10)->create(['ends_at' => now()->subDay()]);

        expect(slugs('/offers'))->toBe([]);

        $this->get('/offers')->assertDontSee('data-countdown', false);
    });

    it('shows the band on the home page only while offers run', function () {
        $product = Product::factory()->create();

        $this->get('/shop')->assertDontSee('offers__panel', false);

        Offer::factory()->forProduct($product)->percent(10)->create();

        $this->get('/shop')->assertSee('offers__panel', false)->assertSee('data-countdown', false);
    });
});

describe('new arrivals', function () {
    it('lists is_new products, newest first', function () {
        Product::factory()->create(['slug' => 'older', 'is_new' => true, 'created_at' => now()->subDays(2)]);
        Product::factory()->create(['slug' => 'newer', 'is_new' => true, 'created_at' => now()->subDay()]);
        Product::factory()->create(['slug' => 'not-new']);

        expect(slugs('/new'))->toBe(['newer', 'older']);
    });
});

describe('best sellers', function () {
    it('ranks by quantity in delivered orders', function () {
        $less = Product::factory()->create(['slug' => 'less']);
        $more = Product::factory()->create(['slug' => 'more']);
        sell($less, 2);
        sell($more, 7);

        expect(slugs('/best-sellers'))->toBe(['more', 'less']);
    });

    it('adds up every delivered order', function () {
        $steady = Product::factory()->create(['slug' => 'steady']);
        $single = Product::factory()->create(['slug' => 'single']);
        sell($steady, 3);
        sell($steady, 3);
        sell($single, 5);

        expect(slugs('/best-sellers'))->toBe(['steady', 'single']);
    });

    it('ignores orders that were not delivered', function (OrderStatus $status) {
        $product = Product::factory()->create(['slug' => 'pending']);
        sell($product, 50, $status);

        expect(slugs('/best-sellers'))->toBe([]);
    })->with([OrderStatus::New, OrderStatus::Confirmed, OrderStatus::Shipping, OrderStatus::Cancelled]);

    it('uses featured products until there are sales', function () {
        Product::factory()->create(['slug' => 'featured', 'is_featured' => true]);
        Product::factory()->create(['slug' => 'plain']);

        expect(slugs('/best-sellers'))->toBe(['featured']);
    });

    it('puts real sales ahead of featured products', function () {
        Product::factory()->create(['slug' => 'featured', 'is_featured' => true]);
        sell(Product::factory()->create(['slug' => 'sold']), 1);

        expect(slugs('/best-sellers'))->toBe(['sold', 'featured']);
    });

    it('caches the ranking for an hour', function () {
        $first = Product::factory()->create(['slug' => 'first']);
        $second = Product::factory()->create(['slug' => 'second']);
        sell($first, 1);

        expect(slugs('/best-sellers'))->toBe(['first']);

        sell($second, 9);

        expect(slugs('/best-sellers'))->toBe(['first']);

        $this->travel(61)->minutes();

        expect(slugs('/best-sellers'))->toBe(['second', 'first']);
    });

    it('numbers the ranking on the page', function () {
        sell(Product::factory()->create(), 1);

        $this->get('/best-sellers')->assertSee('badge--rank', false);
    });

    it('can forget the ranking', function () {
        sell(Product::factory()->create(['slug' => 'first']), 1);
        app(BestSellers::class)->quantities();

        sell(Product::factory()->create(['slug' => 'second']), 9);
        app(BestSellers::class)->forget();

        expect(slugs('/best-sellers'))->toBe(['second', 'first']);
    });
});

it('paginates long lists', function () {
    Product::factory()->count(13)->create(['is_new' => true]);

    expect(slugs('/new'))->toHaveCount(12)
        ->and(slugs('/new?page=2'))->toHaveCount(1);
});
