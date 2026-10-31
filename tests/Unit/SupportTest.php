<?php

use App\Models\Coupon;
use App\Models\Setting;
use App\Support\Money;

it('formats dinars for each language', function () {
    expect(Money::format(1_450_000, 'ar'))->toBe('1,450,000 د.ع')
        ->and(Money::format(1_450_000, 'en'))->toBe('1,450,000 IQD')
        ->and(Money::format(0, 'en'))->toBe('0 IQD');
});

it('uses the current language by default', function () {
    app()->setLocale('en');

    expect(Money::format(5_000))->toBe('5,000 IQD');
});

it('stores coupon codes uppercase', function () {
    $coupon = Coupon::factory()->create(['code' => ' welcome10 ']);

    expect($coupon->refresh()->code)->toBe('WELCOME10');
});

it('finds a coupon whatever case is typed', function () {
    Coupon::factory()->create(['code' => 'WELCOME10']);

    expect(Coupon::query()->where('code', 'welcome10')->exists())->toBeTrue();
});

it('reads settings and refreshes the cache when one changes', function () {
    Setting::put('low_stock_threshold', '3');
    expect(Setting::lowStockThreshold())->toBe(3);

    Setting::put('low_stock_threshold', '7');
    expect(Setting::lowStockThreshold())->toBe(7);
});

it('falls back to defaults for missing settings', function () {
    expect(Setting::get('missing', 'fallback'))->toBe('fallback')
        ->and(Setting::lowStockThreshold())->toBe(5);
});
