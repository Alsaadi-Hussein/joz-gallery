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

    expect($counts())->toBe([18, 6, 13, 5, 3, 5, 7, 1, 25]);

    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe([18, 6, 13, 5, 3, 5, 7, 1, 25]);

    // Every demo price, as shown on the demo's home page.
    $expected = [
        'emerald-velvet-sofa' => [1_450_000, 1_232_500],
        'sand-three-seater' => [1_850_000, 1_850_000],
        'cream-boucle-armchair' => [650_000, 552_500],
        'swivel-lounge-chair' => [720_000, 720_000],
        'walnut-side-table' => [240_000, 192_000],
        'brass-pendant-trio' => [395_000, 395_000],
        'charcoal-floor-lamp' => [145_000, 145_000],
        'ivory-ceramic-vase' => [85_000, 85_000],
        'royal-tufted-bed' => [2_350_000, 2_100_000],
        'natural-oak-bed' => [1_650_000, 1_650_000],
        'emerald-dining-set' => [2_900_000, 2_900_000],
        'round-brass-mirror' => [310_000, 310_000],
        'gold-silk-rug' => [520_000, 416_000],
    ];

    foreach ($expected as $slug => [$base, $final]) {
        $quote = prices()->quote(Product::query()->where('slug', $slug)->firstOrFail());

        expect([$quote->base, $quote->final])->toBe([$base, $final]);
    }

    // The home page sections.
    expect(Product::query()->where('is_new', true)->pluck('slug')->all())
        ->toEqualCanonicalizing(['sand-three-seater', 'swivel-lounge-chair', 'charcoal-floor-lamp', 'ivory-ceramic-vase'])
        ->and(Product::query()->where('is_featured', true)->pluck('slug')->all())
        ->toEqualCanonicalizing(['cream-boucle-armchair', 'walnut-side-table', 'emerald-velvet-sofa', 'brass-pendant-trio']);

    // Every product has a photo with both conversions.
    Product::query()->get()->each(function (Product $product) {
        $media = $product->getFirstMedia('gallery');

        expect($media->hasGeneratedConversion('thumb'))->toBeTrue()
            ->and($media->hasGeneratedConversion('large'))->toBeTrue()
            ->and($media->hasGeneratedConversion('share'))->toBeTrue();
    });

    // New arrivals keep the demo's order when listed newest first.
    expect(Product::query()->where('is_new', true)->latest()->pluck('slug')->all())
        ->toBe(['sand-three-seater', 'swivel-lounge-chair', 'charcoal-floor-lamp', 'ivory-ceramic-vase']);

    // Banners carry both the desktop and phone images.
    Banner::query()->get()->each(function (Banner $banner) {
        expect($banner->getFirstMedia('image'))->not->toBeNull()
            ->and($banner->getFirstMedia('image_mobile'))->not->toBeNull();
    });

    // Offers are running after seeding, unlike the demo's expired countdown.
    expect(Offer::query()->active()->count())->toBe(5);

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
