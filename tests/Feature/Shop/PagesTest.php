<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Database\Seeders\PageSeeder;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    $this->seed([SettingSeeder::class, PageSeeder::class]);

    $this->category = Category::factory()->create(['slug' => 'sofas', 'name_ar' => 'كنب', 'name_en' => 'Sofas']);
    $this->product = Product::factory()->for($this->category)->create(['slug' => 'soft-sofa', 'name_ar' => 'كنبة ناعمة', 'name_en' => 'Soft Sofa']);
});

describe('languages', function () {
    it('serves Arabic right to left at the root', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('shop.nav.offers'));
    });

    it('serves English left to right under /en', function () {
        $this->get('/en')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Offers')
            ->assertDontSee('العروض');
    });

    it('opens every page in both languages', function (string $path) {
        $this->get($path)->assertOk();
        $this->get('/en'.$path)->assertOk();
    })->with([
        '/', '/category/sofas', '/product/soft-sofa', '/offers', '/best-sellers', '/new',
        '/search?q=sofa', '/page/about', '/page/delivery', '/page/returns', '/page/privacy',
        '/cart', '/checkout', '/track',
    ]);

    it('shows each language its own product name', function () {
        $this->get('/product/soft-sofa')->assertSee('كنبة ناعمة');
        $this->get('/en/product/soft-sofa')->assertSee('Soft Sofa');
    });

    it('falls back to Arabic when the English text is empty', function () {
        Page::query()->where('slug', 'about')->update(['body_en' => null, 'body_ar' => '<p>نص عربي فقط</p>']);

        $this->get('/en/page/about')->assertSee('نص عربي فقط');
    });

    it('links to the same page in the other language and keeps the query', function () {
        $this->get('/category/sofas?sort=price_asc')
            ->assertSee('href="'.url('/en/category/sofas?sort=price_asc').'"', false);

        $this->get('/en/category/sofas')
            ->assertSee('href="'.url('/category/sofas').'"', false);
    });

});

describe('the home page', function () {
    it('shows sections with product counts', function () {
        $this->get('/')->assertSee('كنب')->assertSee('منتج واحد');
    });

    it('uses a translated count in English', function () {
        Product::factory()->for($this->category)->create();

        $this->get('/en')->assertSee('2 products');
    });

    it('shows banners with phone images and localized links', function () {
        Banner::factory()->create(['title_ar' => 'عنوان البنر', 'title_en' => 'Banner title', 'link' => '/offers', 'button_ar' => 'شاهد', 'button_en' => 'See']);

        $this->get('/')->assertSee('عنوان البنر')->assertSee('href="'.url('/offers').'"', false);
        $this->get('/en')->assertSee('Banner title')->assertSee('href="'.url('/en/offers').'"', false);
    });

    it('hides switched-off banners', function () {
        Banner::factory()->create(['title_ar' => 'بنر مخفي', 'is_active' => false]);

        $this->get('/')->assertDontSee('بنر مخفي');
    });

    it('is open to search engines', function () {
        $this->get('/')->assertDontSee('noindex');
    });
});

describe('hidden content', function () {
    it('returns 404 for a hidden product', function () {
        $this->product->update(['is_active' => false]);

        $this->get('/product/soft-sofa')->assertNotFound();
    });

    it('returns 404 for a product in a hidden category', function () {
        $this->category->update(['is_active' => false]);

        $this->get('/product/soft-sofa')->assertNotFound();
        $this->get('/category/sofas')->assertNotFound();
    });

    it('returns 404 for a hidden page', function () {
        Page::query()->where('slug', 'about')->update(['is_active' => false]);

        $this->get('/page/about')->assertNotFound();
    });

    it('keeps hidden products out of lists', function () {
        Product::factory()->for($this->category)->create(['name_ar' => 'قطعة مخفية', 'is_active' => false, 'is_new' => true, 'is_featured' => true]);

        foreach (['/', '/category/sofas', '/new', '/best-sellers', '/search?q=قطعة'] as $path) {
            $this->get($path)->assertDontSee('قطعة مخفية');
        }
    });
});

it('renders the 404 page in the store layout and the right language', function () {
    $this->get('/nothing-here')->assertNotFound()->assertSee(__('shop.errors.not_found_title'))->assertSee('site-header', false);
    $this->get('/en/nothing-here')->assertNotFound()->assertSee('Page not found');
});

it('strips scripts from page content', function () {
    Page::query()->where('slug', 'about')->update(['body_ar' => '<p>نص آمن</p><script>alert(1)</script>']);

    $this->get('/page/about')->assertSee('نص آمن')->assertDontSee('alert(1)');
});

it('shows the store contact details from settings', function () {
    $this->get('/')
        ->assertSee('0774 024 3538')
        ->assertSee('+964 774 024 3538')
        ->assertSee('https://wa.me/9647740243538', false)
        ->assertSee('@joz.gallery');
});
