<?php

use App\Enums\DiscountType;
use App\Enums\OfferScope;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Offers\Pages\ManageOffers;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Product;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(owner()));

function offerForm(array $overrides = []): array
{
    return [
        'title_ar' => 'عرض الشتاء',
        'title_en' => 'Winter offer',
        'type' => DiscountType::Percent->value,
        'value' => 15,
        'scope' => OfferScope::All->value,
        'starts_at' => '2026-11-01 10:00',
        'ends_at' => '2026-11-10 10:00',
        'is_active' => true,
        ...$overrides,
    ];
}

describe('offers', function () {
    it('creates a store-wide offer', function () {
        Livewire::test(ManageOffers::class)
            ->callAction('create', offerForm())
            ->assertHasNoFormErrors();

        $offer = Offer::query()->sole();

        expect($offer->scope)->toBe(OfferScope::All)
            ->and($offer->target_id)->toBeNull()
            ->and($offer->value)->toBe(15);
    });

    it('points a product offer at a product', function () {
        $product = Product::factory()->create();

        Livewire::test(ManageOffers::class)
            ->callAction('create', offerForm(['scope' => OfferScope::Product->value, 'target_id' => $product->id]))
            ->assertHasNoFormErrors();

        expect(Offer::query()->sole()->target_id)->toBe($product->id);
    });

    it('points a category offer at a category', function () {
        $category = Category::factory()->create();

        Livewire::test(ManageOffers::class)
            ->callAction('create', offerForm(['scope' => OfferScope::Category->value, 'target_id' => $category->id]))
            ->assertHasNoFormErrors();

        expect(Offer::query()->sole()->target_id)->toBe($category->id);
    });

    it('needs a target for a product or category offer', function (OfferScope $scope) {
        Livewire::test(ManageOffers::class)
            ->callAction('create', offerForm(['scope' => $scope->value]))
            ->assertHasFormErrors(['target_id' => 'required']);
    })->with([OfferScope::Product, OfferScope::Category]);

    it('rejects a target that does not exist', function () {
        Livewire::test(ManageOffers::class)
            ->callAction('create', offerForm(['scope' => OfferScope::Product->value, 'target_id' => 999_999]))
            ->assertHasFormErrors(['target_id']);
    });

    it('shows the right picker for the scope', function () {
        Livewire::test(ManageOffers::class)
            ->mountAction('create')
            ->fillForm(['scope' => OfferScope::All->value])
            ->assertFormFieldHidden('target_id')
            ->fillForm(['scope' => OfferScope::Product->value])
            ->assertFormFieldVisible('target_id')
            ->fillForm(['scope' => OfferScope::Category->value])
            ->assertFormFieldVisible('target_id');
    });

    it('clears the target when the scope changes', function () {
        $product = Product::factory()->create();

        Livewire::test(ManageOffers::class)
            ->mountAction('create')
            ->fillForm(['scope' => OfferScope::Product->value, 'target_id' => $product->id])
            ->fillForm(['scope' => OfferScope::All->value])
            ->assertSchemaStateSet(['target_id' => null]);
    });

    it('removes the old target when an offer becomes store-wide', function () {
        $product = Product::factory()->create();
        $offer = Offer::factory()->forProduct($product)->create();

        Livewire::test(ManageOffers::class)
            ->callAction(TestAction::make('edit')->table($offer), ['scope' => OfferScope::All->value])
            ->assertHasNoFormErrors();

        expect($offer->refresh()->scope)->toBe(OfferScope::All)
            ->and($offer->target_id)->toBeNull();
    });

    it('requires the end to come after the start', function (string $end) {
        Livewire::test(ManageOffers::class)
            ->callAction('create', offerForm(['ends_at' => $end]))
            ->assertHasFormErrors(['ends_at' => 'after']);
    })->with(['2026-11-01 10:00', '2026-10-01 10:00']);

    it('caps a percentage at 100', function () {
        Livewire::test(ManageOffers::class)
            ->callAction('create', offerForm(['value' => 101]))
            ->assertHasFormErrors(['value' => 'max']);
    });

    it('allows big fixed amounts', function () {
        Livewire::test(ManageOffers::class)
            ->callAction('create', offerForm(['type' => DiscountType::Fixed->value, 'value' => 250_000]))
            ->assertHasNoFormErrors();

        expect(Offer::query()->sole()->value)->toBe(250_000);
    });

    it('changes prices straight away', function () {
        $product = Product::factory()->create(['price' => 100_000, 'slug' => 'sofa']);

        Livewire::test(ManageOffers::class)->callAction('create', offerForm([
            'scope' => OfferScope::Product->value,
            'target_id' => $product->id,
            'value' => 20,
            'starts_at' => now()->subHour()->format('Y-m-d H:i'),
            'ends_at' => now()->addHour()->format('Y-m-d H:i'),
        ]));

        expect(prices()->finalPrice($product))->toBe(80_000);
    });

    it('shows what the offer applies to in the table', function () {
        $product = Product::factory()->create(['name_ar' => 'كنبة العرض']);
        Offer::factory()->forProduct($product)->create();

        Livewire::test(ManageOffers::class)->assertSee('كنبة العرض');
    });

    it('switches off from the table', function () {
        $offer = Offer::factory()->create();

        Livewire::test(ManageOffers::class)
            ->call('updateTableColumnState', 'is_active', (string) $offer->id, false);

        expect($offer->refresh()->is_active)->toBeFalse();
    });
});

describe('coupons', function () {
    function couponForm(array $overrides = []): array
    {
        return [
            'code' => 'welcome10',
            'type' => DiscountType::Percent->value,
            'value' => 10,
            'min_total' => 100_000,
            'usage_limit' => 50,
            'expires_at' => '2026-12-31 23:59',
            'is_active' => true,
            ...$overrides,
        ];
    }

    it('creates a coupon stored uppercase', function () {
        Livewire::test(ManageCoupons::class)
            ->callAction('create', couponForm())
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->sole();

        expect($coupon->code)->toBe('WELCOME10')
            ->and($coupon->min_total)->toBe(100_000)
            ->and($coupon->usage_limit)->toBe(50)
            ->and($coupon->used_count)->toBe(0);
    });

    it('allows no limit and no end date', function () {
        Livewire::test(ManageCoupons::class)
            ->callAction('create', couponForm(['usage_limit' => null, 'expires_at' => null]))
            ->assertHasNoFormErrors();

        expect(Coupon::query()->sole()->usage_limit)->toBeNull();
    });

    it('rejects a code that already exists in any case', function () {
        Coupon::factory()->create(['code' => 'WELCOME10']);

        Livewire::test(ManageCoupons::class)
            ->callAction('create', couponForm(['code' => 'welcome10']))
            ->assertHasFormErrors(['code' => 'unique']);
    });

    it('rejects spaces in a code', function () {
        Livewire::test(ManageCoupons::class)
            ->callAction('create', couponForm(['code' => 'two words']))
            ->assertHasFormErrors(['code']);
    });

    it('caps a percentage at 100', function () {
        Livewire::test(ManageCoupons::class)
            ->callAction('create', couponForm(['value' => 150]))
            ->assertHasFormErrors(['value' => 'max']);
    });

    it('shows how often a coupon has been used', function () {
        Coupon::factory()->create(['usage_limit' => 100, 'used_count' => 7]);

        Livewire::test(ManageCoupons::class)->assertSee('7 / 100');
    });

    it('does not let the form change the use count', function () {
        $coupon = Coupon::factory()->create(['used_count' => 7]);

        Livewire::test(ManageCoupons::class)
            ->callAction(TestAction::make('edit')->table($coupon), ['value' => 20, 'used_count' => 0])
            ->assertHasNoFormErrors();

        expect($coupon->refresh()->used_count)->toBe(7)
            ->and($coupon->value)->toBe(20);
    });
});

it('keeps offers and coupons from staff by direct URL', function () {
    $this->actingAs(staff());

    $this->get(OfferResource::getUrl())->assertForbidden();
    $this->get(CouponResource::getUrl())->assertForbidden();
});
