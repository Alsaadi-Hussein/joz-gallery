<?php

use App\Events\OrderPlaced;
use App\Listeners\SendOrderNotifications;
use App\Mail\OrderPlacedMail;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

const BOT_TOKEN = '123456:SECRET-BOT-TOKEN';

beforeEach(function () {
    config(['services.telegram.bot_token' => BOT_TOKEN]);
    Setting::put('telegram_chat_id', '-100555');
    Setting::put('notification_email', 'orders@example.com');

    Mail::fake();

    $this->order = Order::factory()
        ->for(Governorate::factory()->create(['name_ar' => 'البصرة']))
        ->create([
            'number' => 'JG-26-0012',
            'name' => 'سارة علي',
            'phone' => '07801234567',
            'area' => 'العشار',
            'landmark' => 'قرب الكورنيش',
            'total' => 455_000,
        ]);

    OrderItem::factory()->for($this->order)->create(['name_snapshot' => 'مرآة دائرية', 'qty' => 1, 'line_total' => 450_000]);
});

function notify(): void
{
    app(SendOrderNotifications::class)->handle(new OrderPlaced(test()->order));
}

describe('telegram', function () {
    beforeEach(fn () => Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]));

    it('sends the order details to the store chat', function () {
        notify();

        Http::assertSent(function (Request $request) {
            expect($request->url())->toBe('https://api.telegram.org/bot'.BOT_TOKEN.'/sendMessage')
                ->and($request['chat_id'])->toBe('-100555')
                ->and($request['text'])
                ->toContain('JG-26-0012')
                ->toContain('سارة علي')
                ->toContain('07801234567')
                ->toContain('البصرة / العشار')
                ->toContain('قرب الكورنيش')
                ->toContain('مرآة دائرية × 1 = 450,000 د.ع')
                ->toContain('455,000 د.ع')
                ->toContain(url('/admin/orders/'.test()->order->id));

            return true;
        });
    });

    it('writes in Arabic for an English order', function () {
        app()->setLocale('en');
        $this->order->update(['locale' => 'en']);

        notify();

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'طلب جديد') && str_contains($request['text'], 'د.ع'));

        expect(app()->getLocale())->toBe('en');
    });

    it('is skipped when no bot is set up', function () {
        config(['services.telegram.bot_token' => null]);

        notify();

        Http::assertNothingSent();
    });

    it('is skipped when no chat is set up', function () {
        Setting::put('telegram_chat_id', '');

        notify();

        Http::assertNothingSent();
    });
});

describe('email', function () {
    beforeEach(fn () => Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]));

    it('goes to the notification address', function () {
        notify();

        Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('orders@example.com'));
    });

    it('has the order in Arabic with a link to the admin', function () {
        $mail = new OrderPlacedMail($this->order->load(['items', 'governorate']), 'https://joz.example/admin/orders/1');

        $mail->assertHasSubject('طلب جديد JG-26-0012');
        $mail->assertSeeInHtml('سارة علي');
        $mail->assertSeeInHtml('مرآة دائرية');
        $mail->assertSeeInHtml('455,000 د.ع');
        $mail->assertSeeInHtml('https://joz.example/admin/orders/1');
    });

    it('is skipped without an address', function () {
        Setting::put('notification_email', '');

        notify();

        Mail::assertNothingSent();
    });
});

describe('failures', function () {
    it('logs a Telegram failure and still sends the email', function () {
        Http::fake(fn () => Http::response(['ok' => false], 500));
        Log::spy();

        notify();

        Mail::assertSent(OrderPlacedMail::class);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $context['channel'] === 'telegram');
    });

    it('survives Telegram being unreachable', function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
        Log::spy();

        notify();

        Mail::assertSent(OrderPlacedMail::class);
        Log::shouldHaveReceived('error')->once();
    });

    it('logs a mail failure and still sends Telegram', function () {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP connection refused'));
        Log::spy();

        notify();

        Http::assertSentCount(1);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $context['channel'] === 'email');
    });

    it('never writes the bot token to the log', function () {
        Http::fake(fn () => throw new ConnectionException('Connection failed for https://api.telegram.org/bot'.BOT_TOKEN.'/sendMessage'));
        Log::spy();

        notify();

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) {
            expect($context['error'])->not->toContain(BOT_TOKEN)->toContain('[token]');

            return true;
        });
    });

    it('gives up after five seconds', function () {
        expect((new ReflectionClassConstant(SendOrderNotifications::class, 'TIMEOUT_SECONDS'))->getValue())->toBe(5)
            ->and((int) config('mail.mailers.smtp.timeout'))->toBe(5);
    });
});

describe('after checkout', function () {
    it('runs in the background', function () {
        expect(new SendOrderNotifications)->toBeInstanceOf(ShouldQueue::class);
    });

    it('is queued by a real order', function () {
        Queue::fake();
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);

        $this->post('/checkout', [
            'name' => 'زبون',
            'phone' => '07701234567',
            'governorate_id' => $governorate->id,
            'area' => 'المنصور',
            'landmark' => 'قرب المول',
            'cart' => json_encode([['product_id' => $product->id, 'qty' => 1]]),
        ])->assertRedirect();

        Queue::assertPushed(
            CallQueuedListener::class,
            fn ($job) => $job->class === SendOrderNotifications::class,
        );
    });

    it('still confirms the order when the queue is down', function () {
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('database has gone away'));
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);

        $this->post('/checkout', [
            'name' => 'زبون',
            'phone' => '07701234567',
            'governorate_id' => $governorate->id,
            'area' => 'المنصور',
            'landmark' => 'قرب المول',
            'cart' => json_encode([['product_id' => $product->id, 'qty' => 1]]),
        ])->assertRedirect();

        expect(Order::query()->where('area', 'المنصور')->exists())->toBeTrue()
            ->and($product->refresh()->stock)->toBe(2);
    });
});
