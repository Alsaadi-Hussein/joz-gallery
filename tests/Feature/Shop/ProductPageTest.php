<?php

use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->seed(SettingSeeder::class);

    $this->category = Category::factory()->create(['name_en' => 'Sofas']);
    $this->product = Product::factory()->for($this->category)->create([
        'slug' => 'green-sofa',
        'name_ar' => 'كنبة خضراء',
        'name_en' => 'Green Sofa',
        'price' => 1_000_000,
        'description_en' => 'A deep, comfortable sofa.',
        'material_en' => 'Velvet',
        'color_en' => 'Green',
        'width_cm' => 220,
        'depth_cm' => 90,
        'height_cm' => 80,
    ]);
});

it('shows the details', function () {
    $this->get('/en/product/green-sofa')
        ->assertSee('Green Sofa')
        ->assertSee('A deep, comfortable sofa.')
        ->assertSee('Velvet')
        ->assertSee('220 × 90 × 80 cm');
});

it('shows the price before and after an offer', function () {
    Offer::factory()->forProduct($this->product)->percent(15)->create();

    $this->get('/product/green-sofa')
        ->assertSee('850,000 د.ع')
        ->assertSee('1,000,000 د.ع')
        ->assertSee('-15%');
});

it('hides the old price without an offer', function () {
    expect($this->get('/product/green-sofa')->getContent())->toMatch('/data-price-old\s+hidden/');
});

it('formats prices for the language', function () {
    $this->get('/en/product/green-sofa')->assertSee('1,000,000 IQD');
});

describe('gallery', function () {
    beforeEach(function () {
        $this->product->addMedia(UploadedFile::fake()->image('a.jpg', 1800, 2250))->toMediaCollection('gallery');
        $this->product->addMedia(UploadedFile::fake()->image('b.jpg', 1800, 2250))->toMediaCollection('gallery');
    });

    it('uses the large image with a srcset', function () {
        $media = $this->product->getFirstMedia('gallery');

        $this->get('/product/green-sofa')
            ->assertSee('src="'.$media->getUrl('large').'"', false)
            ->assertSee($media->getUrl('thumb').' 600w, '.$media->getUrl('large').' 1600w', false);
    });

    it('offers a thumbnail for each image', function () {
        $this->get('/product/green-sofa')->assertSee('data-gallery-thumb', false);
    });

    it('shares the main image as a small landscape jpg', function () {
        $media = $this->product->getFirstMedia('gallery');
        [$width, $height] = getimagesize($media->getPath('share'));

        expect([$width, $height])->toBe([1200, 630])
            ->and($media->getUrl('share'))->toEndWith('.jpg');

        $this->get('/en/product/green-sofa')
            ->assertSee('<meta property="og:image" content="'.$media->getUrl('share').'">', false);
    });
});

describe('sharing and search engines', function () {
    it('has the product in its title, description and Open Graph tags', function () {
        $this->get('/en/product/green-sofa')
            ->assertSee('<title>Green Sofa | Joz Gallery for Home</title>', false)
            ->assertSee('<meta name="description" content="A deep, comfortable sofa.">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="og:title" content="Green Sofa | Joz Gallery for Home">', false)
            ->assertSee('<meta property="og:description" content="A deep, comfortable sofa.">', false)
            ->assertSee('<meta property="product:price:amount" content="1000000">', false);
    });

    it('prefers the SEO fields when they are filled', function () {
        $this->product->update(['meta_title' => 'Best Green Sofa', 'meta_description' => 'Custom text.']);

        $this->get('/en/product/green-sofa')
            ->assertSee('<title>Best Green Sofa | Joz Gallery for Home</title>', false)
            ->assertSee('content="Custom text."', false);
    });

    it('declares both languages and a canonical address', function () {
        $this->get('/product/green-sofa?utm=whatsapp')
            ->assertSee('<link rel="canonical" href="'.url('/product/green-sofa').'">', false)
            ->assertSee('<link rel="alternate" hreflang="ar" href="'.url('/product/green-sofa').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/product/green-sofa').'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/product/green-sofa').'">', false);
    });

    it('prefills the WhatsApp question with the product name and link', function () {
        $message = rawurlencode('Hello Joz Gallery, I would like to ask about: Green Sofa'."\n".url('/en/product/green-sofa'));

        $this->get('/en/product/green-sofa')
            ->assertSee('https://wa.me/9647740243538?text='.$message, false);
    });
});

describe('variants', function () {
    it('offers each variant with its own price', function () {
        ProductVariant::factory()->for($this->product)->create(['color_en' => 'Red', 'size' => 'L', 'price_delta' => 50_000]);
        Offer::factory()->forProduct($this->product)->percent(10)->create();

        $this->get('/en/product/green-sofa')
            ->assertSee('Red / L')
            ->assertSee('data-now="945,000 IQD"', false)
            ->assertSee('data-old="1,050,000 IQD"', false);
    });

    it('selects the first variant in stock', function () {
        ProductVariant::factory()->for($this->product)->create(['stock' => 0]);
        $available = ProductVariant::factory()->for($this->product)->create(['stock' => 3]);

        $this->get('/product/green-sofa')->assertSee('data-variant="'.$available->id.'"', false);
    });

    it('disables variants that have run out', function () {
        ProductVariant::factory()->for($this->product)->create(['stock' => 0]);

        $this->get('/product/green-sofa')->assertSee('data-stock="0"', false)->assertSee('disabled', false);
    });
});

describe('stock', function () {
    it('offers add to cart while in stock', function () {
        $this->get('/en/product/green-sofa')->assertSee('Add to cart')->assertDontSee('Sold out');
    });

    it('shows sold out instead', function () {
        $this->product->update(['stock' => 0]);

        $this->get('/en/product/green-sofa')->assertSee('Sold out');
    });

    it('uses variant stock when there are variants', function () {
        $this->product->update(['stock' => 50]);
        ProductVariant::factory()->for($this->product)->create(['stock' => 0]);

        $this->get('/en/product/green-sofa')->assertSee('Sold out');
    });
});

describe('product cards', function () {
    it('send products with variants to the product page to choose', function () {
        ProductVariant::factory()->for($this->product)->create();
        $this->product->update(['is_new' => true]);

        $this->get('/en/new')->assertSee('Choose options')->assertDontSee('data-add=', false);
    });

    it('add simple products straight to the cart', function () {
        $this->product->update(['is_new' => true]);

        $this->get('/new')->assertSee('data-add="'.$this->product->id.'"', false);
    });

    it('never offer a sold-out product', function () {
        $this->product->update(['is_new' => true, 'stock' => 0]);

        $this->get('/en/new')->assertSee('Sold out')->assertDontSee('data-add=', false);
    });
});

it('suggests other products from the same section', function () {
    $same = Product::factory()->for($this->category)->create(['name_en' => 'Same Section']);
    Product::factory()->create(['name_en' => 'Other Section']);

    $this->get('/en/product/green-sofa')
        ->assertSee('Same Section')
        ->assertDontSee('Other Section');

    expect($same->exists)->toBeTrue();
});
