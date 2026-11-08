<?php

use App\Enums\UserRole;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');

    config([
        'joz.admin.email' => 'owner@example.com',
        'joz.admin.password' => 'Str0ng-Pass_word!',
        'joz.seed_demo' => true,
    ]);
});

it('builds the store from the demo, and running it again changes nothing', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = fn () => [
        Governorate::query()->count(),
        Category::query()->count(),
        Product::query()->count(),
        Offer::query()->count(),
        Banner::query()->count(),
        Page::query()->count(),
        Setting::query()->count(),
        User::query()->count(),
        Media::query()->count(),
    ];

    expect($counts())->toBe([18, 11, 32, 7, 3, 5, 7, 1, 77]);

    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe([18, 11, 32, 7, 3, 5, 7, 1, 77]);

    // Every price, as the design preview shows it.
    $expected = [
        'round-brass-mirror' => [310_000, 310_000],
        'oval-wall-mirror' => [285_000, 285_000],
        'walnut-side-table' => [240_000, 192_000],
        'round-dining-table' => [450_000, 450_000],
        'c-side-table' => [135_000, 135_000],
        'walnut-gold-console' => [890_000, 890_000],
        'rattan-console' => [640_000, 544_000],
        'bonsai-ficus' => [95_000, 95_000],
        'aloe-succulent' => [25_000, 25_000],
        'ficus-plant' => [60_000, 60_000],
        'terracotta-cactus' => [20_000, 20_000],
        'cast-iron-casserole' => [120_000, 120_000],
        'ceramic-cup-set' => [45_000, 45_000],
        'porcelain-mug' => [15_000, 15_000],
        'tripod-table-lamp' => [110_000, 110_000],
        'glass-candle-holder' => [30_000, 30_000],
        'brass-pendant-trio' => [395_000, 395_000],
        'ivory-ceramic-vase' => [85_000, 85_000],
        'white-bud-vase' => [35_000, 35_000],
        'amber-glass-vase' => [55_000, 55_000],
        'gold-silk-rug' => [520_000, 416_000],
        'still-life-flowers' => [175_000, 175_000],
        'abstract-oil' => [210_000, 168_000],
        'gallery-frame-set' => [160_000, 160_000],
        'cream-boucle-armchair' => [650_000, 552_500],
        'swivel-lounge-chair' => [720_000, 720_000],
        'rattan-wood-chair' => [185_000, 185_000],
        'emerald-velvet-sofa' => [1_450_000, 1_232_500],
        'sand-three-seater' => [1_850_000, 1_850_000],
        'royal-tufted-bed' => [2_350_000, 2_100_000],
        'natural-oak-bed' => [1_650_000, 1_650_000],
        'emerald-dining-set' => [2_900_000, 2_900_000],
    ];

    foreach ($expected as $slug => [$base, $final]) {
        $quote = prices()->quote(Product::query()->where('slug', $slug)->firstOrFail());

        expect([$quote->base, $quote->final])->toBe([$base, $final]);
    }

    // The home page sections.
    expect(Product::query()->where('is_new', true)->pluck('slug')->all())
        ->toEqualCanonicalizing([
            'round-brass-mirror',
            'round-dining-table',
            'walnut-gold-console',
            'bonsai-ficus',
            'cast-iron-casserole',
            'tripod-table-lamp',
            'ivory-ceramic-vase',
            'amber-glass-vase',
            'still-life-flowers',
            'swivel-lounge-chair',
            'sand-three-seater',
            'natural-oak-bed',
        ])
        ->and(Product::query()->where('is_featured', true)->pluck('slug')->all())
        ->toEqualCanonicalizing([
            'round-brass-mirror',
            'walnut-gold-console',
            'bonsai-ficus',
            'ivory-ceramic-vase',
            'still-life-flowers',
            'cream-boucle-armchair',
            'emerald-velvet-sofa',
        ]);

    // Every product has a photo with both conversions.
    Product::query()->get()->each(function (Product $product) {
        $media = $product->getFirstMedia('gallery');

        expect($media->hasGeneratedConversion('thumb'))->toBeTrue()
            ->and($media->hasGeneratedConversion('large'))->toBeTrue()
            ->and($media->hasGeneratedConversion('share'))->toBeTrue();
    });

    // New arrivals keep the preview's order when listed newest first.
    expect(Product::query()->where('is_new', true)->latest()->orderByDesc('id')->pluck('slug')->all())
        ->toBe([
            'round-brass-mirror',
            'round-dining-table',
            'walnut-gold-console',
            'bonsai-ficus',
            'cast-iron-casserole',
            'tripod-table-lamp',
            'ivory-ceramic-vase',
            'amber-glass-vase',
            'still-life-flowers',
            'swivel-lounge-chair',
            'sand-three-seater',
            'natural-oak-bed',
        ]);

    // The sections, in the preview's order, each with its photo.
    expect(Category::query()->orderBy('sort_order')->pluck('slug')->all())
        ->toBe(['mirrors', 'tables', 'consoles', 'plants', 'kitchen', 'lamps', 'vases', 'accessories', 'paintings', 'chairs', 'furniture']);
    Category::query()->get()->each(fn (Category $category) => expect($category->getFirstMedia('image'))->not->toBeNull());

    // Some pieces have more than one photo, so the product gallery shows in the demo.
    expect(Product::query()->where('slug', 'emerald-velvet-sofa')->firstOrFail()->getMedia('gallery'))->toHaveCount(3);

    // Every piece says what it is made of and how big it is.
    expect(Product::query()->where(fn ($query) => $query->whereNull('material_ar')->orWhereNull('width_cm'))->count())->toBe(0);

    // The About page tells the store's story instead of placeholder text.
    expect(Page::query()->where('slug', 'about')->value('body_ar'))->toContain('خشب الجوز')->not->toContain('نص مؤقت');

    // Banners carry both the desktop and phone images.
    Banner::query()->get()->each(function (Banner $banner) {
        expect($banner->getFirstMedia('image'))->not->toBeNull()
            ->and($banner->getFirstMedia('image_mobile'))->not->toBeNull();
    });

    // Offers are running after seeding, unlike the demo's expired countdown.
    expect(Offer::query()->active()->count())->toBe(7);

    // The source images survive so the seeder can run again.
    expect(database_path('seeders/demo/p-emerald-sofa.webp'))->toBeFile();
})->group('slow');

it('seeds the 18 governorates with Baghdad cheaper', function () {
    $this->seed(GovernorateSeeder::class);

    expect(Governorate::query()->count())->toBe(18)
        ->and(Governorate::query()->where('name_en', 'Baghdad')->value('delivery_fee'))->toBe(5_000)
        ->and(Governorate::query()->where('delivery_fee', 8_000)->count())->toBe(17);
});

it('keeps fees changed in the panel when seeding again', function () {
    $this->seed(GovernorateSeeder::class);

    Governorate::query()->where('name_en', 'Basra')->update(['delivery_fee' => 12_000]);

    $this->seed(GovernorateSeeder::class);

    expect(Governorate::query()->where('name_en', 'Basra')->value('delivery_fee'))->toBe(12_000);
});

it('skips the catalog when the demo is switched off', function () {
    config(['joz.seed_demo' => false]);

    $this->seed(DatabaseSeeder::class);

    expect(Product::query()->count())->toBe(0)
        ->and(Governorate::query()->count())->toBe(18)
        ->and(User::query()->count())->toBe(1);
});

it('creates the owner from the environment', function () {
    $this->seed(AdminUserSeeder::class);

    $user = User::query()->sole();

    expect($user->email)->toBe('owner@example.com')
        ->and($user->role)->toBe(UserRole::Owner)
        ->and(Hash::check('Str0ng-Pass_word!', $user->password))->toBeTrue();
});

it('stops when the admin details are missing', function () {
    config(['joz.admin.password' => null]);

    $this->seed(AdminUserSeeder::class);
})->throws(RuntimeException::class, 'ADMIN_PASSWORD');

it('refuses a weak admin password', function () {
    config(['joz.admin.password' => 'password']);

    $this->seed(AdminUserSeeder::class);
})->throws(RuntimeException::class, 'too weak');

it('never resets an existing owner password', function () {
    $this->seed(AdminUserSeeder::class);

    User::query()->sole()->update(['password' => 'Ch4nged-In_Panel!']);
    config(['joz.admin.password' => 'An0ther-Pass_word!']);

    $this->seed(AdminUserSeeder::class);

    expect(Hash::check('Ch4nged-In_Panel!', User::query()->sole()->password))->toBeTrue();
});
