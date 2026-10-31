<?php

use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Testing\TestResponse;

function preview(array $items, string $prefix = ''): TestResponse
{
    return test()->postJson("{$prefix}/cart/preview", ['items' => $items]);
}

beforeEach(function () {
    $this->product = Product::factory()->create(['price' => 100_000, 'stock' => 5, 'name_ar' => 'طاولة', 'name_en' => 'Table']);
});

it('prices lines on the server', function () {
    Offer::factory()->forProduct($this->product)->percent(10)->create();

    preview([['product_id' => $this->product->id, 'qty' => 2]])
        ->assertOk()
        ->assertJsonPath('lines.0.name', 'طاولة')
        ->assertJsonPath('lines.0.qty', 2)
        ->assertJsonPath('lines.0.line_total', 180_000)
        ->assertJsonPath('lines.0.line_total_formatted', '180,000 د.ع')
        ->assertJsonPath('count', 2)
        ->assertJsonPath('subtotal', 180_000);
});

it('ignores any price sent by the browser', function () {
    preview([['product_id' => $this->product->id, 'qty' => 1, 'price' => 1, 'line_total' => 1]])
        ->assertJsonPath('subtotal', 100_000);
});

it('answers in the page language', function () {
    preview([['product_id' => $this->product->id, 'qty' => 1]], '/en')
        ->assertJsonPath('lines.0.name', 'Table')
        ->assertJsonPath('subtotal_formatted', '100,000 IQD')
        ->assertJsonPath('lines.0.url', url('/en/product/'.$this->product->slug));
});

it('adds up several lines', function () {
    $other = Product::factory()->create(['price' => 50_000, 'stock' => 9]);

    preview([
        ['product_id' => $this->product->id, 'qty' => 1],
        ['product_id' => $other->id, 'qty' => 3],
    ])->assertJsonPath('subtotal', 250_000)->assertJsonPath('count', 4);
});

it('handles an empty cart', function () {
    preview([])->assertOk()->assertJsonPath('count', 0)->assertJsonPath('subtotal_formatted', '0 د.ع');
});

describe('variants', function () {
    beforeEach(function () {
        $this->variant = ProductVariant::factory()->for($this->product)->create(['price_delta' => 20_000, 'stock' => 3, 'color_ar' => 'بني', 'size' => 'L']);
    });

    it('prices the variant', function () {
        preview([['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'qty' => 1]])
            ->assertJsonPath('subtotal', 120_000)
            ->assertJsonPath('lines.0.variant', 'بني / L')
            ->assertJsonPath('lines.0.variant_id', $this->variant->id);
    });

    it('drops a product with variants when none is chosen', function () {
        preview([['product_id' => $this->product->id, 'qty' => 1]])->assertJsonCount(0, 'lines');
    });

    it('drops a variant that belongs to another product', function () {
        $foreign = ProductVariant::factory()->create();

        preview([['product_id' => $this->product->id, 'variant_id' => $foreign->id, 'qty' => 1]])->assertJsonCount(0, 'lines');
    });

    it('limits the quantity to the variant stock', function () {
        preview([['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'qty' => 9]])
            ->assertJsonPath('lines.0.qty', 3)
            ->assertJsonPath('lines.0.stock', 3);
    });
});

describe('items that cannot be bought', function () {
    it('drops a hidden product', function () {
        $this->product->update(['is_active' => false]);

        preview([['product_id' => $this->product->id, 'qty' => 1]])->assertJsonCount(0, 'lines');
    });

    it('drops a product in a hidden category', function () {
        Category::query()->whereKey($this->product->category_id)->update(['is_active' => false]);

        preview([['product_id' => $this->product->id, 'qty' => 1]])->assertJsonCount(0, 'lines');
    });

    it('drops a product that no longer exists', function () {
        preview([['product_id' => 999_999, 'qty' => 1]])->assertOk()->assertJsonCount(0, 'lines');
    });

    it('drops a variant id sent for a simple product', function () {
        preview([['product_id' => $this->product->id, 'variant_id' => 999_999, 'qty' => 1]])->assertJsonCount(0, 'lines');
    });

    it('drops a product that has sold out', function () {
        $this->product->update(['stock' => 0]);

        preview([['product_id' => $this->product->id, 'qty' => 1]])->assertJsonCount(0, 'lines');
    });

    it('limits the quantity to what is in stock', function () {
        preview([['product_id' => $this->product->id, 'qty' => 50]])
            ->assertJsonPath('lines.0.qty', 5)
            ->assertJsonPath('subtotal', 500_000);
    });
});

it('rejects malformed carts', function (array $items) {
    preview($items)->assertUnprocessable();
})->with([
    'no product' => [[['qty' => 1]]],
    'zero quantity' => fn () => [[['product_id' => 1, 'qty' => 0]]],
    'negative quantity' => fn () => [[['product_id' => 1, 'qty' => -3]]],
    'huge quantity' => fn () => [[['product_id' => 1, 'qty' => 100]]],
    'text id' => fn () => [[['product_id' => 'abc', 'qty' => 1]]],
]);

it('rejects a cart with too many lines', function () {
    $items = array_map(fn (int $id) => ['product_id' => $id, 'qty' => 1], range(1, 51));

    preview($items)->assertUnprocessable();
});

it('runs a handful of queries however long the cart is', function () {
    $items = Product::factory()->count(10)->create()
        ->map(fn (Product $product) => ['product_id' => $product->id, 'qty' => 1])
        ->all();

    DB::enableQueryLog();

    preview($items)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(10);
});
