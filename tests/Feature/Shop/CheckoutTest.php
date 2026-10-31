<?php

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Event::fake([OrderPlaced::class]);
    RateLimiter::clear('checkout');

    $this->governorate = Governorate::factory()->create(['delivery_fee' => 5_000]);
    $this->product = Product::factory()->create(['price' => 100_000, 'stock' => 5, 'name_ar' => 'طاولة قهوة', 'name_en' => 'Coffee Table']);
});

function checkoutForm(array $overrides = []): array
{
    return [
        'name' => 'زبون تجريبي',
        'phone' => '07701234567',
        'governorate_id' => test()->governorate->id,
        'area' => 'الكرادة',
        'landmark' => 'قرب الجسر',
        'notes' => 'اتصل قبل الوصول',
        'cart' => json_encode([['product_id' => test()->product->id, 'qty' => 2]]),
        ...$overrides,
    ];
}

function placeOrder(array $overrides = [], string $prefix = ''): TestResponse
{
    return test()->from("{$prefix}/checkout")->post("{$prefix}/checkout", checkoutForm($overrides));
}

describe('a successful order', function () {
    it('saves the order, its items and the customer details', function () {
        $response = placeOrder();

        $order = Order::query()->sole();

        $response->assertRedirect($order->thanksUrl())->assertSessionHas('order_placed');

        expect($order->name)->toBe('زبون تجريبي')
            ->and($order->phone)->toBe('07701234567')
            ->and($order->area)->toBe('الكرادة')
            ->and($order->landmark)->toBe('قرب الجسر')
            ->and($order->notes)->toBe('اتصل قبل الوصول')
            ->and($order->status)->toBe(OrderStatus::New)
            ->and($order->locale)->toBe('ar')
            ->and($order->ip)->toBe('127.0.0.1');

        $item = $order->items->sole();

        expect($item->product_id)->toBe($this->product->id)
            ->and($item->name_snapshot)->toBe('طاولة قهوة')
            ->and($item->unit_price)->toBe(100_000)
            ->and($item->qty)->toBe(2)
            ->and($item->line_total)->toBe(200_000);
    });

    it('works out the totals', function () {
        placeOrder();

        $order = Order::query()->sole();

        expect($order->subtotal)->toBe(200_000)
            ->and($order->discount)->toBe(0)
            ->and($order->delivery_fee)->toBe(5_000)
            ->and($order->total)->toBe(205_000);
    });

    it('takes the sold quantity out of stock', function () {
        placeOrder();

        expect($this->product->refresh()->stock)->toBe(3);
    });

    it('announces the order', function () {
        placeOrder();

        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->order->is(Order::query()->sole()));
    });

    it('keeps the English name and language for an English order', function () {
        placeOrder(prefix: '/en');

        $order = Order::query()->sole();

        expect($order->locale)->toBe('en')
            ->and($order->items->sole()->name_snapshot)->toBe('Coffee Table')
            ->and($order->thanksUrl())->toContain('/en/order/');
    });

    it('stores the phone in one format', function () {
        placeOrder(['phone' => '+964 770 123 4567']);

        expect(Order::query()->sole()->phone)->toBe('07701234567');
    });

    it('accepts an order without notes', function () {
        placeOrder(['notes' => '']);

        expect(Order::query()->sole()->notes)->toBeNull();
    });
});

describe('order numbers', function () {
    it('follows JG-YY-####', function () {
        Carbon::setTestNow('2026-11-02 10:00:00');

        placeOrder();

        expect(Order::query()->sole()->number)->toBe('JG-26-0001');
    });

    it('counts up', function () {
        Carbon::setTestNow('2026-11-02 10:00:00');
        Order::factory()->create(['number' => 'JG-26-0041']);

        placeOrder();

        expect(Order::query()->latest('id')->first()->number)->toBe('JG-26-0042');
    });

    it('starts again each year', function () {
        Carbon::setTestNow('2027-01-01 09:00:00');
        Order::factory()->create(['number' => 'JG-26-0999']);

        placeOrder();

        expect(Order::query()->latest('id')->first()->number)->toBe('JG-27-0001');
    });

    it('keeps counting past 9999', function () {
        Carbon::setTestNow('2026-11-02 10:00:00');
        Order::factory()->create(['number' => 'JG-26-9999']);

        placeOrder();

        expect(Order::query()->latest('id')->first()->number)->toBe('JG-26-10000');
    });
});

describe('prices come from the server', function () {
    it('ignores prices sent with the form', function () {
        placeOrder([
            'cart' => json_encode([['product_id' => $this->product->id, 'qty' => 2, 'price' => 1, 'unit_price' => 1, 'line_total' => 1]]),
            'subtotal' => 1,
            'discount' => 999_999,
            'delivery_fee' => 0,
            'total' => 1,
        ]);

        $order = Order::query()->sole();

        expect($order->subtotal)->toBe(200_000)
            ->and($order->discount)->toBe(0)
            ->and($order->delivery_fee)->toBe(5_000)
            ->and($order->total)->toBe(205_000)
            ->and($order->items->sole()->unit_price)->toBe(100_000);
    });

    it('applies the running offer', function () {
        Offer::factory()->forProduct($this->product)->percent(15)->create();

        placeOrder();

        $order = Order::query()->sole();

        expect($order->items->sole()->unit_price)->toBe(85_000)
            ->and($order->subtotal)->toBe(170_000)
            ->and($order->total)->toBe(175_000);
    });

    it('charges the variant price and takes variant stock', function () {
        $variant = ProductVariant::factory()->for($this->product)->create(['price_delta' => 20_000, 'stock' => 4, 'color_ar' => 'بني', 'size' => 'L']);

        placeOrder(['cart' => json_encode([['product_id' => $this->product->id, 'variant_id' => $variant->id, 'qty' => 3]])]);

        $item = Order::query()->sole()->items->sole();

        expect($item->variant_id)->toBe($variant->id)
            ->and($item->variant_snapshot)->toBe('بني / L')
            ->and($item->unit_price)->toBe(120_000)
            ->and($variant->refresh()->stock)->toBe(1)
            ->and($this->product->refresh()->stock)->toBe(5);
    });

    it('uses the delivery fee of the chosen governorate', function () {
        $far = Governorate::factory()->create(['delivery_fee' => 8_000]);

        placeOrder(['governorate_id' => $far->id]);

        expect(Order::query()->sole()->delivery_fee)->toBe(8_000);
    });
});

describe('stock', function () {
    it('rejects a sold-out product and names it', function () {
        $this->product->update(['stock' => 0]);

        placeOrder()
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors(['cart' => __('shop.checkout.sold_out', ['name' => 'طاولة قهوة'])]);

        expect(Order::query()->count())->toBe(0);
    });

    it('rejects more than is in stock and says how many are left', function () {
        placeOrder(['cart' => json_encode([['product_id' => $this->product->id, 'qty' => 6]])])
            ->assertSessionHasErrors(['cart' => __('shop.checkout.not_enough', ['name' => 'طاولة قهوة', 'stock' => 5])]);

        expect(Order::query()->count())->toBe(0)
            ->and($this->product->refresh()->stock)->toBe(5);
    });

    it('explains in the language of the page', function () {
        $this->product->update(['stock' => 0]);

        placeOrder(prefix: '/en')->assertSessionHasErrors(['cart' => '“Coffee Table” has sold out. Remove it from your cart and try again.']);
    });

    it('allows buying the very last one', function () {
        $this->product->update(['stock' => 2]);

        placeOrder();

        expect($this->product->refresh()->stock)->toBe(0)
            ->and(Order::query()->count())->toBe(1);
    });

    it('cannot get past the stock check by repeating a line', function () {
        placeOrder(['cart' => json_encode([
            ['product_id' => $this->product->id, 'qty' => 3],
            ['product_id' => $this->product->id, 'qty' => 3],
        ])])->assertSessionHasErrors(['cart' => __('shop.checkout.not_enough', ['name' => 'طاولة قهوة', 'stock' => 5])]);

        expect($this->product->refresh()->stock)->toBe(5);
    });

    it('changes nothing when one line of many fails', function () {
        $soldOut = Product::factory()->create(['stock' => 0]);

        placeOrder(['cart' => json_encode([
            ['product_id' => $this->product->id, 'qty' => 2],
            ['product_id' => $soldOut->id, 'qty' => 1],
        ])])->assertSessionHasErrors('cart');

        expect(Order::query()->count())->toBe(0)
            ->and($this->product->refresh()->stock)->toBe(5);
    });

    it('checks the variant stock, not the product stock', function () {
        $variant = ProductVariant::factory()->for($this->product)->create(['stock' => 0]);

        placeOrder(['cart' => json_encode([['product_id' => $this->product->id, 'variant_id' => $variant->id, 'qty' => 1]])])
            ->assertSessionHasErrors('cart');

        expect(Order::query()->count())->toBe(0);
    });

    it('keeps what the shopper typed after a failure', function () {
        $this->product->update(['stock' => 0]);

        placeOrder()->assertSessionHasInput('area', 'الكرادة');
    });
});

describe('items that cannot be ordered', function () {
    it('rejects a hidden product', function () {
        $this->product->update(['is_active' => false]);

        placeOrder()->assertSessionHasErrors(['cart' => __('shop.checkout.item_unavailable')]);
    });

    it('rejects a product in a hidden category', function () {
        Category::query()->whereKey($this->product->category_id)->update(['is_active' => false]);

        placeOrder()->assertSessionHasErrors('cart');
    });

    it('rejects a product with variants ordered without one', function () {
        ProductVariant::factory()->for($this->product)->create();

        placeOrder()->assertSessionHasErrors(['cart' => __('shop.checkout.item_unavailable_named', ['name' => 'طاولة قهوة'])]);
    });

    it('rejects a variant of another product', function () {
        $foreign = ProductVariant::factory()->create();

        placeOrder(['cart' => json_encode([['product_id' => $this->product->id, 'variant_id' => $foreign->id, 'qty' => 1]])])
            ->assertSessionHasErrors('cart');

        expect(Order::query()->count())->toBe(0);
    });

    it('rejects an empty cart', function () {
        placeOrder(['cart' => '[]'])->assertSessionHasErrors(['cart' => __('shop.checkout.cart_empty')]);
    });

    it('rejects a cart that is not valid', function (string $cart) {
        placeOrder(['cart' => $cart]);

        expect(Order::query()->count())->toBe(0);
    })->with([
        'not json' => 'not json',
        'a number' => '5',
        'zero quantity' => fn () => json_encode([['product_id' => 1, 'qty' => 0]]),
        'huge quantity' => fn () => json_encode([['product_id' => 1, 'qty' => 100]]),
    ]);
});

describe('coupons', function () {
    it('takes the discount off the subtotal after offers', function () {
        Offer::factory()->forProduct($this->product)->percent(50)->create();
        $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'value' => 10]);

        placeOrder(['coupon' => 'SAVE10']);

        $order = Order::query()->sole();

        expect($order->subtotal)->toBe(100_000)
            ->and($order->discount)->toBe(10_000)
            ->and($order->total)->toBe(95_000)
            ->and($order->coupon_id)->toBe($coupon->id);
    });

    it('counts the use', function () {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);

        placeOrder(['coupon' => 'SAVE10']);

        expect($coupon->refresh()->used_count)->toBe(1);
    });

    it('accepts any case and stray spaces', function () {
        Coupon::factory()->create(['code' => 'SAVE10']);

        placeOrder(['coupon' => ' save10 ']);

        expect(Order::query()->sole()->coupon_id)->not->toBeNull();
    });

    it('caps a fixed coupon at the subtotal', function () {
        Coupon::factory()->create(['code' => 'BIG', 'type' => DiscountType::Fixed, 'value' => 9_000_000]);

        placeOrder(['coupon' => 'BIG']);

        expect(Order::query()->sole()->total)->toBe(5_000);
    });

    it('refuses the order rather than quietly charging full price', function (array $attributes, string $message) {
        Coupon::factory()->create(['code' => 'NOPE', ...$attributes]);

        placeOrder(['coupon' => 'NOPE'])->assertSessionHasErrors(['cart' => __($message, ['amount' => '900,000 د.ع'])]);

        expect(Order::query()->count())->toBe(0)
            ->and($this->product->refresh()->stock)->toBe(5);
    })->with([
        'expired' => [['expires_at' => '2020-01-01'], 'shop.coupon.expired'],
        'used up' => [['usage_limit' => 2, 'used_count' => 2], 'shop.coupon.used_up'],
        'switched off' => [['is_active' => false], 'shop.coupon.invalid'],
        'minimum not met' => [['min_total' => 900_000], 'shop.coupon.min_total'],
    ]);

    it('refuses a code that does not exist', function () {
        placeOrder(['coupon' => 'MADEUP'])->assertSessionHasErrors(['cart' => __('shop.coupon.invalid')]);
    });
});

describe('validation', function () {
    it('requires the delivery details', function (string $field) {
        placeOrder([$field => ''])->assertSessionHasErrors($field);

        expect(Order::query()->count())->toBe(0);
    })->with(['name', 'phone', 'governorate_id', 'area', 'landmark']);

    it('rejects a phone that is not an Iraqi mobile', function (string $phone) {
        placeOrder(['phone' => $phone])->assertSessionHasErrors(['phone' => __('shop.checkout.phone_invalid')]);
    })->with(['12345', '07601234567', '0770123456']);

    it('rejects a governorate that is switched off', function () {
        $this->governorate->update(['is_active' => false]);

        placeOrder()->assertSessionHasErrors('governorate_id');
    });

    it('shows Arabic field names in messages', function () {
        placeOrder(['name' => ''])->assertSessionHasErrors(['name' => 'حقل الاسم الكامل مطلوب.']);
    });
});

describe('abuse', function () {
    it('stops a bot that fills the hidden field', function () {
        placeOrder(['website' => 'https://spam.example']);

        expect(Order::query()->count())->toBe(0);
    });

    it('allows five attempts a minute from one address', function () {
        foreach (range(1, 5) as $attempt) {
            placeOrder(['name' => ''])->assertRedirect();
        }

        placeOrder()->assertTooManyRequests()->assertSee(__('shop.errors.too_many_title'));

        expect(Order::query()->count())->toBe(0);
    });

    it('counts each address separately', function () {
        foreach (range(1, 5) as $attempt) {
            placeOrder(['name' => '']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9']);

        placeOrder();

        expect(Order::query()->count())->toBe(1);
    });
});

describe('the checkout page', function () {
    it('lists active governorates only', function () {
        Governorate::factory()->create(['name_ar' => 'محافظة مخفية', 'is_active' => false]);

        $this->get('/checkout')->assertOk()->assertSee($this->governorate->name_ar)->assertDontSee('محافظة مخفية');
    });

    it('has the hidden trap field and CSRF protection', function () {
        $this->get('/checkout')->assertSee('name="website"', false)->assertSee('name="_token"', false);
    });

    it('stays out of search engines', function () {
        $this->get('/checkout')->assertSee('noindex');
        $this->get('/cart')->assertSee('noindex');
    });
});

describe('the live summary', function () {
    function quote(array $data, string $prefix = ''): TestResponse
    {
        return test()->postJson("{$prefix}/checkout/quote", [
            'items' => [['product_id' => test()->product->id, 'qty' => 2]],
            ...$data,
        ]);
    }

    it('shows the delivery fee once a governorate is chosen', function () {
        quote([])->assertJsonPath('delivery_fee_formatted', null)->assertJsonPath('total_formatted', '200,000 د.ع');

        quote(['governorate_id' => $this->governorate->id])
            ->assertJsonPath('delivery_fee_formatted', '5,000 د.ع')
            ->assertJsonPath('total_formatted', '205,000 د.ع');
    });

    it('applies a valid coupon', function () {
        Coupon::factory()->create(['code' => 'SAVE10', 'value' => 10]);

        quote(['coupon' => 'save10', 'governorate_id' => $this->governorate->id])
            ->assertJsonPath('coupon_applied', true)
            ->assertJsonPath('coupon_message', __('shop.coupon.applied'))
            ->assertJsonPath('discount', 20_000)
            ->assertJsonPath('total_formatted', '185,000 د.ع');
    });

    it('explains why a coupon does not work and keeps the full price', function () {
        Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);

        quote(['coupon' => 'OLD'])
            ->assertJsonPath('coupon_applied', false)
            ->assertJsonPath('coupon_message', __('shop.coupon.expired'))
            ->assertJsonPath('discount', 0)
            ->assertJsonPath('total_formatted', '200,000 د.ع');
    });

    it('does not use a coupon up by checking it', function () {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);

        quote(['coupon' => 'SAVE10']);

        expect($coupon->refresh()->used_count)->toBe(0);
    });

    it('ignores a governorate that is switched off', function () {
        $this->governorate->update(['is_active' => false]);

        quote(['governorate_id' => $this->governorate->id])->assertJsonPath('delivery_fee_formatted', null);
    });

    it('is rate limited so codes cannot be guessed', function () {
        foreach (range(1, 30) as $attempt) {
            quote(['coupon' => "GUESS{$attempt}"])->assertOk();
        }

        quote(['coupon' => 'GUESS31'])->assertTooManyRequests();
    });
});
