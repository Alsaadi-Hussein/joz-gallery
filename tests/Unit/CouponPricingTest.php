<?php

use App\Enums\DiscountType;
use App\Models\Coupon;

function coupon(array $attributes = []): Coupon
{
    return Coupon::factory()->create($attributes);
}

describe('working out the discount', function () {
    it('takes a percentage off the subtotal', function () {
        $totals = prices()->totals(200_000, coupon(['value' => 10]), 5_000);

        expect($totals->discount)->toBe(20_000)
            ->and($totals->total())->toBe(185_000);
    });

    it('takes a fixed amount off the subtotal', function () {
        $totals = prices()->totals(200_000, coupon(['type' => DiscountType::Fixed, 'value' => 30_000]), 5_000);

        expect($totals->discount)->toBe(30_000)
            ->and($totals->total())->toBe(175_000);
    });

    it('never touches the delivery fee', function () {
        $totals = prices()->totals(10_000, coupon(['type' => DiscountType::Fixed, 'value' => 90_000]), 5_000);

        expect($totals->discount)->toBe(10_000)
            ->and($totals->total())->toBe(5_000);
    });

    it('rounds to whole dinars', function () {
        expect(prices()->totals(333, coupon(['value' => 15]), 0)->discount)->toBe(50);
    });

    it('charges in full without a coupon', function () {
        $totals = prices()->totals(200_000, null, 5_000);

        expect($totals->discount)->toBe(0)
            ->and($totals->total())->toBe(205_000);
    });
});

describe('deciding if a coupon can be used', function () {
    it('accepts a valid coupon', function () {
        expect(prices()->couponProblem(coupon(['min_total' => 100_000]), 100_000))->toBeNull();
    });

    it('rejects a missing coupon', function () {
        expect(prices()->couponProblem(null, 100_000))->toBe(__('shop.coupon.invalid'));
    });

    it('rejects a coupon that is switched off', function () {
        expect(prices()->couponProblem(coupon(['is_active' => false]), 100_000))->toBe(__('shop.coupon.invalid'));
    });

    it('rejects an expired coupon', function () {
        expect(prices()->couponProblem(coupon(['expires_at' => now()->subMinute()]), 100_000))->toBe(__('shop.coupon.expired'));
    });

    it('accepts a coupon with no end date', function () {
        expect(prices()->couponProblem(coupon(['expires_at' => null]), 100_000))->toBeNull();
    });

    it('rejects a coupon that has reached its limit', function () {
        expect(prices()->couponProblem(coupon(['usage_limit' => 3, 'used_count' => 3]), 100_000))->toBe(__('shop.coupon.used_up'));
    });

    it('accepts the last allowed use', function () {
        expect(prices()->couponProblem(coupon(['usage_limit' => 3, 'used_count' => 2]), 100_000))->toBeNull();
    });

    it('allows unlimited use without a limit', function () {
        expect(prices()->couponProblem(coupon(['usage_limit' => null, 'used_count' => 9_999]), 100_000))->toBeNull();
    });

    it('rejects a subtotal under the minimum and says how much is needed', function () {
        expect(prices()->couponProblem(coupon(['min_total' => 100_000]), 99_999))
            ->toBe(__('shop.coupon.min_total', ['amount' => '100,000 د.ع']));
    });
});
