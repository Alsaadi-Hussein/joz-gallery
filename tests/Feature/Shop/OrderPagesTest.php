<?php

use App\Enums\OrderStatus;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(SettingSeeder::class);

    $this->order = Order::factory()
        ->for(Governorate::factory())
        ->create(['number' => 'JG-26-0007', 'phone' => '07701234567', 'subtotal' => 300_000, 'discount' => 30_000, 'delivery_fee' => 5_000, 'total' => 275_000]);

    OrderItem::factory()->for($this->order)->create(['name_snapshot' => 'كنبة خضراء', 'variant_snapshot' => 'بني / L', 'qty' => 2, 'line_total' => 300_000]);
});

describe('the thanks page', function () {
    it('needs the signed link', function () {
        $this->get('/order/JG-26-0007/thanks')->assertForbidden();
        $this->get($this->order->thanksUrl().'x')->assertForbidden();
    });

    it('rejects a signature from another order', function () {
        $other = Order::factory()->create(['number' => 'JG-26-0008']);
        $signature = parse_url($other->thanksUrl(), PHP_URL_QUERY);

        $this->get("/order/JG-26-0007/thanks?{$signature}")->assertForbidden();
    });

    it('shows the number, items and totals', function () {
        $this->get($this->order->thanksUrl())
            ->assertOk()
            ->assertSee('JG-26-0007')
            ->assertSee('كنبة خضراء (بني / L)', false)
            ->assertSee('300,000 د.ع')
            ->assertSee('-30,000 د.ع')
            ->assertSee('5,000 د.ع')
            ->assertSee('275,000 د.ع');
    });

    it('prefills a WhatsApp message to the store', function () {
        $page = $this->get($this->order->thanksUrl())->getContent();

        preg_match_all('/https:\/\/wa\.me\/9647740243538\?text=([^"]+)"/', $page, $matches);

        $message = collect($matches[1])->map(fn (string $text) => rawurldecode($text))
            ->first(fn (string $text) => str_contains($text, 'JG-26-0007'));

        expect($message)
            ->toContain('كنبة خضراء (بني / L) × 2 = 300,000 د.ع')
            ->toContain('275,000 د.ع');
    });

    it('clears the cart only on the visit right after ordering', function () {
        $this->withSession(['order_placed' => true])
            ->get($this->order->thanksUrl())
            ->assertSee('data-order-placed', false)
            ->assertSee('"phone":"07701234567"', false);

        $this->flushSession();

        $this->get($this->order->thanksUrl())->assertDontSee('data-order-placed', false);
    });

    it('stays out of search engines', function () {
        $this->get($this->order->thanksUrl())->assertSee('noindex');
    });
});

describe('tracking', function () {
    beforeEach(fn () => RateLimiter::clear('track'));

    function track(array $data): TestResponse
    {
        return test()->from('/track')->post('/track', $data);
    }

    it('shows the status with the right number and phone', function () {
        $this->order->update(['status' => OrderStatus::Shipping]);

        track(['number' => 'JG-26-0007', 'phone' => '07701234567'])
            ->assertOk()
            ->assertSee(__('shop.order_status.shipping'))
            ->assertSee('كنبة خضراء')
            ->assertSee('275,000 د.ع');
    });

    it('requires the phone', function () {
        track(['number' => 'JG-26-0007'])->assertSessionHasErrors('phone');
    });

    it('requires the number', function () {
        track(['phone' => '07701234567'])->assertSessionHasErrors('number');
    });

    it('reveals nothing with the wrong phone', function () {
        track(['number' => 'JG-26-0007', 'phone' => '07709999999'])
            ->assertRedirect('/track')
            ->assertSessionHasErrors(['number' => __('shop.track.not_found')]);
    });

    it('gives the same answer for a number that does not exist', function () {
        track(['number' => 'JG-26-9999', 'phone' => '07701234567'])
            ->assertSessionHasErrors(['number' => __('shop.track.not_found')]);
    });

    it('accepts the number in any case and the phone in any format', function () {
        track(['number' => ' jg-26-0007 ', 'phone' => '+964 770 123 4567'])
            ->assertOk()
            ->assertSee('JG-26-0007');
    });

    it('never shows the address', function () {
        $this->order->update(['area' => 'منطقة سرية']);

        track(['number' => 'JG-26-0007', 'phone' => '07701234567'])->assertDontSee('منطقة سرية');
    });

    it('marks a cancelled order', function () {
        $this->order->update(['status' => OrderStatus::Cancelled]);

        track(['number' => 'JG-26-0007', 'phone' => '07701234567'])->assertSee(__('shop.track.cancelled'));
    });

    it('is rate limited so numbers cannot be guessed', function () {
        foreach (range(1, 10) as $attempt) {
            track(['number' => "JG-26-{$attempt}", 'phone' => '07701234567']);
        }

        track(['number' => 'JG-26-0007', 'phone' => '07701234567'])->assertTooManyRequests();
    });

    it('offers the form in both languages', function () {
        $this->get('/track')->assertOk()->assertSee(__('shop.track.title'));
        $this->get('/en/track')->assertOk()->assertSee('Track your order');
    });
});
