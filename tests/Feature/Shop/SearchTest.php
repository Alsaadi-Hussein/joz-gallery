<?php

use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Support\ArabicText;

function found(string $term): array
{
    return test()->get('/search?q='.urlencode($term))->assertOk()->viewData('products')->getCollection()->pluck('slug')->all();
}

beforeEach(function () {
    $this->category = Category::factory()->create(['name_ar' => 'الإضاءة', 'name_en' => 'Lighting']);

    Product::factory()->for($this->category)->create(['slug' => 'lamp', 'name_ar' => 'مصباح أرضي فحمي', 'name_en' => 'Charcoal Floor Lamp']);
    Product::factory()->create(['slug' => 'mirror', 'name_ar' => 'مرآة دائرية', 'name_en' => 'Round Mirror']);
    Product::factory()->create(['slug' => 'table', 'name_ar' => 'طاولة قهوة', 'name_en' => 'Coffee Table']);
});

it('finds Arabic and English names', function (string $term, string $slug) {
    expect(found($term))->toBe([$slug]);
})->with([
    ['مصباح', 'lamp'],
    ['Mirror', 'mirror'],
    ['coffee', 'table'],
]);

it('forgives the spelling differences people type', function (string $term, string $slug) {
    expect(found($term))->toBe([$slug]);
})->with([
    'without hamza' => ['مراة', 'mirror'],
    'ha for ta marbuta' => ['مرآه', 'mirror'],
    'plain alef' => ['ارضي', 'lamp'],
    'with diacritics' => ['مِصْبَاح', 'lamp'],
    'with kashida' => ['مصـباح', 'lamp'],
]);

it('needs every word, in any order', function () {
    expect(found('فحمي مصباح'))->toBe(['lamp'])
        ->and(found('مصباح طاولة'))->toBe([]);
});

it('finds products by their section, so the popular chips work', function () {
    expect(found('الإضاءة'))->toBe(['lamp']);
});

it('treats wildcards as plain text', function () {
    expect(found('%'))->toBe([])
        ->and(found('_'))->toBe([]);
});

it('tells the shopper when nothing matches', function () {
    $this->get('/search?q=zzzz')->assertSee(__('shop.search.empty'));
});

it('asks for a word on an empty search', function () {
    expect(found(''))->toBe([]);

    $this->get('/search')->assertOk()->assertSee(__('shop.search.prompt'));
});

it('survives a malformed query', function () {
    $this->get('/search?q[]=x')->assertOk();
});

it('escapes the search term on the page', function () {
    $this->get('/search?q='.urlencode('<script>alert(1)</script>'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('stays out of search engines', function () {
    $this->get('/search?q=lamp')->assertSee('<meta name="robots" content="noindex">', false);
});

describe('suggestions', function () {
    it('returns matches with the server price', function () {
        $lamp = Product::query()->where('slug', 'lamp')->first();
        $lamp->update(['price' => 100_000]);
        Offer::factory()->forProduct($lamp)->percent(10)->create();

        $this->getJson('/en/search/suggest?q=lamp')
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Charcoal Floor Lamp')
            ->assertJsonPath('results.0.section', 'Lighting')
            ->assertJsonPath('results.0.price', '90,000 IQD')
            ->assertJsonPath('results.0.url', url('/en/product/lamp'));
    });

    it('returns at most six', function () {
        Product::factory()->count(8)->create(['name_ar' => 'كرسي']);

        $this->getJson('/search/suggest?q=كرسي')->assertJsonCount(6, 'results');
    });

    it('returns nothing for an empty term', function () {
        $this->getJson('/search/suggest?q=')->assertJsonCount(0, 'results');
    });

    it('is rate limited', function () {
        foreach (range(1, 60) as $attempt) {
            $this->getJson('/search/suggest?q=a')->assertOk();
        }

        $this->getJson('/search/suggest?q=a')->assertTooManyRequests();
    });
});

it('normalizes text the same way as the old storefront', function () {
    expect(ArabicText::normalize('أإآةى'))->toBe('اااهي')
        ->and(ArabicText::normalize('LAMP'))->toBe('lamp');
});
