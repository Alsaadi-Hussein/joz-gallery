<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Database\Seeders\PageSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed([SettingSeeder::class, PageSeeder::class]);

    $this->mirrors = Category::factory()->create(['slug' => 'mirrors', 'name_ar' => 'مرايات', 'name_en' => 'Mirrors', 'sort_order' => 1]);
    $this->lamps = Category::factory()->create(['slug' => 'lamps', 'name_ar' => 'تيبل لامب وشمعدان', 'name_en' => 'Table Lamps & Candlesticks', 'sort_order' => 2]);
    $this->vases = Category::factory()->create(['slug' => 'vases', 'name_ar' => 'فازات', 'name_en' => 'Vases', 'sort_order' => 3]);
});

describe('the welcome page', function () {
    it('opens the site with the video, the brand and a way into the store', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('data-welcome-video', false)
            ->assertSee(asset('assets/video/landing-portrait.mp4'), false)
            ->assertSee('href="'.url('/shop').'"', false)
            ->assertSee(__('shop.welcome.enter'))
            ->assertDontSee('site-header', false);
    });

    it('has an English twin that leads to the English store', function () {
        $this->get('/en')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('href="'.url('/en/shop').'"', false)
            ->assertSee('Enter the store');
    });

    it('offers WhatsApp, Instagram, a call and furnishing projects', function () {
        $this->get('/')
            ->assertSee('https://wa.me/9647740243538', false)
            ->assertSee('https://instagram.com/joz.gallery', false)
            ->assertSee('href="tel:+9647740243538"', false)
            ->assertSee(rawurlencode(__('shop.whatsapp.projects')), false);
    });
});

describe('section pages', function () {
    it('show every section as a tab and mark the current one', function () {
        $html = $this->get('/category/lamps')->assertOk()->getContent();

        expect(preg_match_all('/class="sec-tab[ "]/', $html))->toBe(4)
            ->and($html)->toContain('مرايات')->toContain('فازات')->toContain(__('shop.nav.picks'))
            ->and($html)->toMatch('#<a class="sec-tab" href="'.preg_quote(url('/category/lamps'), '#').'" data-key="\d+"\s+aria-current="page"\s*>#');
    });

    it('link to the sections before and after, for the swipe and the pager', function () {
        $this->get('/category/lamps')
            ->assertSee('data-prev="'.url('/category/mirrors').'"', false)
            ->assertSee('data-next="'.url('/category/vases').'"', false)
            ->assertSee(__('shop.sections_nav.next'))
            ->assertSee('data-section-pager', false);
    });

    it('name the next section for the list to run on into, so moving on needs no new page', function () {
        $this->get('/category/lamps')
            ->assertSee('data-stream-next="'.url('/category/vases').'"', false)
            ->assertSee('data-url="'.url('/category/lamps').'"', false);

        // Each page the list fetches carries its own block with its own heading.
        Product::factory()->for($this->vases)->create(['name_ar' => 'فازة الكتلة']);
        $html = $this->get('/category/vases')->getContent();
        expect(substr_count($html, 'data-stream-block'))->toBe(1)
            ->and($html)->toContain('data-stream-head hidden')->toContain('فازة الكتلة');
    });

    it('run on to the next page of a long section before the next section', function () {
        Product::factory()->for($this->lamps)->count(13)->create();

        $this->get('/category/lamps')->assertSee('data-stream-next="'.url('/category/lamps?page=2').'"', false);
        $this->get('/category/lamps?page=2')->assertSee('data-stream-next="'.url('/category/vases').'"', false);
    });

    it('keep filtered results on their own', function () {
        $this->get('/category/lamps?min=1000')->assertSee('data-stream-next=""', false);
    });

    it('wrap around: the last section leads to Joz Picks, and Joz Picks back to the first', function () {
        $this->get('/category/vases')->assertSee('data-next="'.url('/joz-picks').'"', false);
        $this->get('/joz-picks')
            ->assertSee('data-prev="'.url('/category/vases').'"', false)
            ->assertSee('data-next="'.url('/category/mirrors').'"', false);
    });

    it('mark the parent section for a sub-section', function () {
        $floor = Category::factory()->create(['slug' => 'floor-lamps', 'parent_id' => $this->lamps->id, 'name_ar' => 'أرضية']);

        expect($this->get('/category/floor-lamps')->getContent())
            ->toMatch('#href="'.preg_quote(url('/category/lamps'), '#').'" data-key="\d+"\s+aria-current="page"#');
    });

    it('keep the English links under /en', function () {
        $this->get('/en/category/lamps')
            ->assertSee('href="'.url('/en/category/vases').'"', false)
            ->assertSee('Table Lamps &amp; Candlesticks', false)
            ->assertSee('Swipe left or right to move between sections');
    });

    it('never put a WhatsApp button on the product photos', function () {
        Product::factory()->for($this->vases)->create(['is_featured' => true]);

        foreach (['/shop', '/category/vases', '/joz-picks'] as $path) {
            $this->get($path)->assertDontSee('pcard__wa', false);
        }
    });
});

describe('Joz Picks', function () {
    it('lists only the featured products', function () {
        Product::factory()->for($this->vases)->create(['name_ar' => 'فازة مختارة', 'is_featured' => true]);
        Product::factory()->for($this->vases)->create(['name_ar' => 'فازة عادية', 'is_featured' => false]);

        $this->get('/joz-picks')
            ->assertOk()
            ->assertSee('فازة مختارة')
            ->assertDontSee('فازة عادية')
            ->assertSee(__('shop.picks.subtitle'));
    });

    it('opens first on the home page, with its own tile and count', function () {
        Product::factory()->for($this->vases)->count(2)->create(['is_featured' => true]);

        $html = $this->get('/shop')->getContent();
        $highlights = substr($html, strpos($html, 'class="highlights"'));

        expect($html)->toContain('cat cat--picks')
            ->and(strpos($highlights, 'href="'.url('/joz-picks').'"'))->toBeLessThan(strpos($highlights, 'href="'.url('/category/mirrors').'"'))
            ->and($html)->toContain(trans_choice('shop.product_count', 2));
    });
});

describe('links that went nowhere in the preview', function () {
    it('takes "read our story" to the About page, which opens', function () {
        $this->get('/shop')->assertSee('href="'.url('/page/about').'"', false)->assertSee(__('shop.home.story_cta'));
        $this->get('/page/about')->assertOk()->assertSee(Page::query()->where('slug', 'about')->value('title_ar'));
    });

    it('takes "track your order" to the tracking form, which opens', function () {
        $this->get('/shop')->assertSee('href="'.url('/track').'"', false);
        $this->get('/track')->assertOk()->assertSee('name="number"', false)->assertSee('name="phone"', false);
    });

    it('offers furnishing projects on WhatsApp from the home page and the menu', function () {
        expect(substr_count($this->get('/shop')->getContent(), rawurlencode(__('shop.whatsapp.projects'))))->toBeGreaterThanOrEqual(3);
    });
});

describe('the product page', function () {
    beforeEach(function () {
        $this->product = Product::factory()->for($this->vases)->create([
            'slug' => 'ivory-vase',
            'name_en' => 'Ivory Vase',
            'material_ar' => 'سيراميك',
            'material_en' => 'Ceramic',
            'width_cm' => 18,
            'depth_cm' => 18,
            'height_cm' => 35,
            'description_en' => 'Hand-glazed ceramic.',
        ]);
    });

    it('shows a thumbnail for every photo of the product', function () {
        foreach (['a', 'b', 'c'] as $name) {
            $this->product->addMedia(UploadedFile::fake()->image("{$name}.jpg", 1800, 2250))->toMediaCollection('gallery');
        }

        expect(substr_count($this->get('/product/ivory-vase')->getContent(), 'data-gallery-thumb'))->toBe(3);
    });

    it('shows what the piece is made of and its size right under the price, before the buttons', function () {
        $html = $this->get('/en/product/ivory-vase')->assertSee('Ceramic')->assertSee('18 × 18 × 35 cm')->getContent();

        expect(strpos($html, 'pdp__price'))->toBeLessThan(strpos($html, 'pdp__specs'))
            ->and(strpos($html, 'pdp__specs'))->toBeLessThan(strpos($html, 'pdp__actions'))
            ->and($html)->toContain('Hand-glazed ceramic.');
    });
});
