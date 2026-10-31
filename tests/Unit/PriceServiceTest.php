<?php

use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->product = Product::factory()->for($this->category)->create(['price' => 1_450_000]);
});

describe('without an offer', function () {
    it('charges the base price', function () {
        $quote = prices()->quote($this->product);

        expect($quote->base)->toBe(1_450_000)
            ->and($quote->final)->toBe(1_450_000)
            ->and($quote->offer)->toBeNull()
            ->and($quote->hasDiscount())->toBeFalse()
            ->and($quote->percentOff())->toBe(0);
    });

    it('adds the variant delta', function () {
        $variant = ProductVariant::factory()->for($this->product)->create(['price_delta' => 50_000]);

        expect(prices()->finalPrice($this->product, $variant))->toBe(1_500_000);
    });

    it('subtracts a negative variant delta', function () {
        $variant = ProductVariant::factory()->for($this->product)->create(['price_delta' => -450_000]);

        expect(prices()->finalPrice($this->product, $variant))->toBe(1_000_000);
    });

    it('never goes below zero when a delta exceeds the price', function () {
        $variant = ProductVariant::factory()->for($this->product)->create(['price_delta' => -2_000_000]);

        expect(prices()->finalPrice($this->product, $variant))->toBe(0);
    });
});

describe('discount maths', function () {
    it('applies a percent offer', function () {
        Offer::factory()->forProduct($this->product)->percent(15)->create();

        $quote = prices()->quote($this->product);

        expect($quote->final)->toBe(1_232_500)
            ->and($quote->discount())->toBe(217_500)
            ->and($quote->percentOff())->toBe(15);
    });

    it('applies a fixed offer', function () {
        $this->product->update(['price' => 2_350_000]);
        Offer::factory()->forProduct($this->product)->fixed(250_000)->create();

        $quote = prices()->quote($this->product);

        expect($quote->final)->toBe(2_100_000)
            ->and($quote->percentOff())->toBe(11);
    });

    it('rounds to whole dinars, half up', function (int $price, int $percent, int $expected) {
        $this->product->update(['price' => $price]);
        Offer::factory()->forProduct($this->product)->percent($percent)->create();

        expect(prices()->finalPrice($this->product))->toBe($expected);
    })->with([
        '49.95 off becomes 50' => [333, 15, 283],
        '0.5 off becomes 1' => [10, 5, 9],
        '0.49 off becomes 0' => [7, 7, 7],
    ]);

    it('applies the offer after the variant delta', function () {
        $variant = ProductVariant::factory()->for($this->product)->create(['price_delta' => 50_000]);
        Offer::factory()->forProduct($this->product)->percent(10)->create();

        expect(prices()->finalPrice($this->product, $variant))->toBe(1_350_000);
    });

    it('never goes below zero with a fixed offer above the price', function () {
        Offer::factory()->forProduct($this->product)->fixed(9_000_000)->create();

        expect(prices()->finalPrice($this->product))->toBe(0);
    });

    it('caps a percent offer at 100', function () {
        Offer::factory()->forProduct($this->product)->percent(150)->create();

        expect(prices()->finalPrice($this->product))->toBe(0);
    });
});

describe('precedence', function () {
    it('prefers a product offer over a bigger category offer', function () {
        $productOffer = Offer::factory()->forProduct($this->product)->percent(5)->create();
        Offer::factory()->forCategory($this->category)->percent(50)->create();

        expect(prices()->quote($this->product)->offer->id)->toBe($productOffer->id);
    });

    it('prefers a category offer over a bigger store-wide offer', function () {
        $categoryOffer = Offer::factory()->forCategory($this->category)->percent(5)->create();
        Offer::factory()->percent(50)->create();

        expect(prices()->quote($this->product)->offer->id)->toBe($categoryOffer->id);
    });

    it('falls back to a store-wide offer', function () {
        $storeOffer = Offer::factory()->percent(10)->create();

        expect(prices()->quote($this->product)->offer->id)->toBe($storeOffer->id);
    });

    it('never stacks offers', function () {
        Offer::factory()->forProduct($this->product)->percent(10)->create();
        Offer::factory()->forCategory($this->category)->percent(10)->create();
        Offer::factory()->percent(10)->create();

        expect(prices()->finalPrice($this->product))->toBe(1_305_000);
    });

    it('picks the biggest discount inside the winning level', function () {
        Offer::factory()->forProduct($this->product)->percent(10)->create();
        $best = Offer::factory()->forProduct($this->product)->percent(20)->create();
        Offer::factory()->forProduct($this->product)->fixed(1_000)->create();

        expect(prices()->quote($this->product)->offer->id)->toBe($best->id);
    });

    it('compares percent and fixed offers on the actual price', function () {
        $percent = Offer::factory()->forProduct($this->product)->percent(10)->create();
        $fixed = Offer::factory()->forProduct($this->product)->fixed(100_000)->create();

        expect(prices()->quote($this->product)->offer->id)->toBe($percent->id);

        $this->product->update(['price' => 500_000]);

        expect(prices()->quote($this->product)->offer->id)->toBe($fixed->id);
    });

    it('reaches products through the parent category', function () {
        $parent = Category::factory()->create();
        $this->category->update(['parent_id' => $parent->id]);
        Offer::factory()->forCategory($parent)->percent(10)->create();

        expect(prices()->finalPrice($this->product))->toBe(1_305_000);
    });

    it('reaches products through every level of parents', function () {
        $top = Category::factory()->create();
        $middle = Category::factory()->create(['parent_id' => $top->id]);
        $this->category->update(['parent_id' => $middle->id]);
        Offer::factory()->forCategory($top)->percent(10)->create();

        expect(prices()->finalPrice($this->product))->toBe(1_305_000);
    });

    it('treats a category and its parent as one level', function () {
        $parent = Category::factory()->create();
        $this->category->update(['parent_id' => $parent->id]);
        Offer::factory()->forCategory($this->category)->percent(10)->create();
        $best = Offer::factory()->forCategory($parent)->percent(20)->create();

        expect(prices()->quote($this->product)->offer->id)->toBe($best->id);
    });

    it('survives a loop in the category tree', function () {
        $other = Category::factory()->create(['parent_id' => $this->category->id]);
        $this->category->update(['parent_id' => $other->id]);

        expect(prices()->finalPrice($this->product))->toBe(1_450_000);
    });
});

describe('which offers count', function () {
    it('ignores another product\'s offer', function () {
        Offer::factory()->forProduct(Product::factory()->create())->percent(50)->create();

        expect(prices()->finalPrice($this->product))->toBe(1_450_000);
    });

    it('ignores another category\'s offer', function () {
        Offer::factory()->forCategory(Category::factory()->create())->percent(50)->create();

        expect(prices()->finalPrice($this->product))->toBe(1_450_000);
    });

    it('does not apply a parent\'s sibling or child offer upward', function () {
        $child = Category::factory()->create(['parent_id' => $this->category->id]);
        Offer::factory()->forCategory($child)->percent(50)->create();

        expect(prices()->finalPrice($this->product))->toBe(1_450_000);
    });

    it('ignores offers that are switched off', function () {
        Offer::factory()->forProduct($this->product)->percent(50)->create(['is_active' => false]);

        expect(prices()->finalPrice($this->product))->toBe(1_450_000);
    });

    it('ignores offers that have not started', function () {
        Offer::factory()->forProduct($this->product)->percent(50)->create(['starts_at' => now()->addHour()]);

        expect(prices()->finalPrice($this->product))->toBe(1_450_000);
    });

    it('ignores offers that have ended', function () {
        Offer::factory()->forProduct($this->product)->percent(50)->create(['ends_at' => now()->subHour()]);

        expect(prices()->finalPrice($this->product))->toBe(1_450_000);
    });

    it('counts the first and last second of an offer', function () {
        $start = Carbon::parse('2026-11-01 10:00:00');
        $end = Carbon::parse('2026-11-05 10:00:00');
        Offer::factory()->forProduct($this->product)->percent(10)->create(['starts_at' => $start, 'ends_at' => $end]);

        Carbon::setTestNow($start);
        expect(prices()->finalPrice($this->product))->toBe(1_305_000);

        Carbon::setTestNow($end);
        expect(prices()->finalPrice($this->product))->toBe(1_305_000);

        Carbon::setTestNow($end->copy()->addSecond());
        expect(prices()->finalPrice($this->product))->toBe(1_450_000);
    });

    it('prices a product that is not saved yet', function () {
        Offer::factory()->percent(10)->create();

        $product = new Product(['price' => 100_000]);

        expect(prices()->finalPrice($product))->toBe(90_000);
    });
});

it('loads offers and the category tree once however many products it prices', function () {
    Offer::factory()->forCategory($this->category)->percent(10)->create();
    $products = Product::factory()->count(5)->for($this->category)->create();
    $service = prices();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $products->each(fn (Product $product) => $service->finalPrice($product));

    expect(DB::getQueryLog())->toHaveCount(2);
});

it('sees new offers after a flush', function () {
    $service = prices();

    expect($service->finalPrice($this->product))->toBe(1_450_000);

    Offer::factory()->percent(10)->create();
    $service->flush();

    expect($service->finalPrice($this->product))->toBe(1_305_000);
});
