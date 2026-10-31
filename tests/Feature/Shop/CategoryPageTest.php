<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Pagination\LengthAwarePaginator;

beforeEach(function () {
    $this->category = Category::factory()->create(['slug' => 'sofas']);
});

function listed(string $url): array
{
    /** @var LengthAwarePaginator $products */
    $products = test()->get($url)->assertOk()->viewData('products');

    return $products->getCollection()->pluck('slug')->all();
}

function inCategory(string $slug, array $attributes = []): Product
{
    return Product::factory()->for(test()->category)->create(['slug' => $slug, ...$attributes]);
}

it('lists the category and everything below it', function () {
    $child = Category::factory()->create(['parent_id' => $this->category->id]);
    inCategory('direct');
    Product::factory()->for($child)->create(['slug' => 'nested']);
    Product::factory()->create(['slug' => 'elsewhere']);

    expect(listed('/category/sofas'))->toEqualCanonicalizing(['direct', 'nested']);
});

it('links to sub-sections', function () {
    Category::factory()->create(['parent_id' => $this->category->id, 'slug' => 'two-seaters']);

    $this->get('/category/sofas')->assertSee(url('/category/two-seaters'));
});

describe('sorting', function () {
    beforeEach(function () {
        inCategory('cheap', ['price' => 100_000, 'created_at' => now()->subDays(3)]);
        inCategory('middle', ['price' => 200_000, 'created_at' => now()->subDays(2)]);
        inCategory('dear', ['price' => 300_000, 'created_at' => now()->subDay()]);
    });

    it('shows the newest first by default', function () {
        expect(listed('/category/sofas'))->toBe(['dear', 'middle', 'cheap']);
    });

    it('sorts by price both ways', function () {
        expect(listed('/category/sofas?sort=price_asc'))->toBe(['cheap', 'middle', 'dear'])
            ->and(listed('/category/sofas?sort=price_desc'))->toBe(['dear', 'middle', 'cheap']);
    });

    it('sorts by the price after offers, not the list price', function () {
        Offer::factory()->forProduct(Product::query()->where('slug', 'dear')->first())->percent(90)->create();

        expect(listed('/category/sofas?sort=price_asc'))->toBe(['dear', 'cheap', 'middle']);
    });

    it('sorts by quantity delivered', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);
        OrderItem::factory()->for($order)->create(['product_id' => Product::query()->where('slug', 'cheap')->value('id'), 'qty' => 5]);
        OrderItem::factory()->for($order)->create(['product_id' => Product::query()->where('slug', 'middle')->value('id'), 'qty' => 9]);

        expect(listed('/category/sofas?sort=best_selling'))->toBe(['middle', 'cheap', 'dear']);
    });

    it('ignores an unknown sort', function () {
        expect(listed('/category/sofas?sort=hacked'))->toBe(['dear', 'middle', 'cheap']);
    });
});

describe('filters', function () {
    it('filters by price range', function () {
        inCategory('cheap', ['price' => 100_000]);
        inCategory('middle', ['price' => 200_000]);
        inCategory('dear', ['price' => 300_000]);

        expect(listed('/category/sofas?min=150000&max=250000'))->toBe(['middle']);
    });

    it('filters on the price after offers', function () {
        $sale = inCategory('on-sale', ['price' => 300_000]);
        inCategory('full-price', ['price' => 300_000]);
        Offer::factory()->forProduct($sale)->percent(50)->create();

        expect(listed('/category/sofas?max=200000'))->toBe(['on-sale']);
    });

    it('filters by color in the current language', function () {
        inCategory('red', ['color_ar' => 'أحمر', 'color_en' => 'Red']);
        inCategory('blue', ['color_ar' => 'أزرق', 'color_en' => 'Blue']);

        expect(listed('/category/sofas?color[]=أحمر'))->toBe(['red'])
            ->and(listed('/en/category/sofas?color[]=Blue'))->toBe(['blue']);
    });

    it('matches colors on variants too', function () {
        $product = inCategory('with-variant', ['color_ar' => null, 'color_en' => null]);
        ProductVariant::factory()->for($product)->create(['color_ar' => 'أخضر', 'color_en' => 'Green']);
        inCategory('other');

        expect(listed('/en/category/sofas?color[]=Green'))->toBe(['with-variant']);
    });

    it('filters by material', function () {
        inCategory('wood', ['material_ar' => 'خشب', 'material_en' => 'Wood']);
        inCategory('metal', ['material_ar' => 'معدن', 'material_en' => 'Metal']);

        expect(listed('/en/category/sofas?material[]=Wood'))->toBe(['wood']);
    });

    it('combines filters', function () {
        inCategory('match', ['price' => 100_000, 'color_en' => 'Red', 'material_en' => 'Wood']);
        inCategory('wrong-color', ['price' => 100_000, 'color_en' => 'Blue', 'material_en' => 'Wood']);
        inCategory('too-dear', ['price' => 900_000, 'color_en' => 'Red', 'material_en' => 'Wood']);

        expect(listed('/en/category/sofas?max=500000&color[]=Red&material[]=Wood'))->toBe(['match']);
    });

    it('offers only the choices that exist in this section', function () {
        inCategory('here', ['color_en' => 'Red', 'material_en' => 'Wood']);
        Product::factory()->create(['color_en' => 'Purple']);

        $facets = $this->get('/en/category/sofas')->viewData('facets');

        expect($facets['colors'])->toBe(['Red'])
            ->and($facets['materials'])->toBe(['Wood']);
    });

    it('keeps the choices visible while a filter is on', function () {
        inCategory('red', ['color_en' => 'Red']);
        inCategory('blue', ['color_en' => 'Blue']);

        $facets = $this->get('/en/category/sofas?color[]=Red')->viewData('facets');

        expect($facets['colors'])->toBe(['Blue', 'Red']);
    });

    it('says so when nothing matches', function () {
        inCategory('cheap', ['price' => 100_000]);

        $this->get('/category/sofas?min=999999')->assertSee(__('shop.listing.empty'));
    });

    it('ignores malformed filters', function (string $query) {
        inCategory('only');

        expect(listed("/category/sofas?{$query}"))->toBe(['only']);
    })->with(['min=abc', 'max=-5', 'min[]=1', 'color=', 'sort[]=x']);
});

describe('pagination', function () {
    beforeEach(function () {
        Product::factory()->count(14)->for($this->category)->create();
    });

    it('shows twelve products a page', function () {
        expect(listed('/category/sofas'))->toHaveCount(12)
            ->and(listed('/category/sofas?page=2'))->toHaveCount(2);
    });

    it('keeps filters in page links', function () {
        $this->get('/category/sofas?sort=price_asc')->assertSee('sort=price_asc&amp;page=2', false);
    });

    it('points search engines at the clean page address', function () {
        $this->get('/category/sofas?sort=price_asc&page=2')
            ->assertSee('<link rel="canonical" href="'.url('/category/sofas?page=2').'">', false);
    });
});

it('has its own title and description', function () {
    $this->category->update(['name_ar' => 'الكنب']);

    $this->get('/category/sofas')
        ->assertSee('<title>الكنب | '.__('shop.meta.title_suffix').'</title>', false)
        ->assertSee(__('shop.meta.category', ['name' => 'الكنب']));
});
