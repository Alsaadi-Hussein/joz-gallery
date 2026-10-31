<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Filament\Resources\Orders\OrderResource;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\Setting;
use App\Support\OrderSummary;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Tells the store about a new order. Runs on the queue, after the order is saved,
 * and each channel fails on its own: an alert that cannot be sent is logged and
 * never affects the order or the other channel.
 */
class SendOrderNotifications implements ShouldQueue
{
    use Localizable;

    private const TELEGRAM_API = 'https://api.telegram.org';

    private const TIMEOUT_SECONDS = 5;

    /**
     * Failures are handled here, so a retry would only risk sending twice.
     */
    public int $tries = 1;

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->load(['items', 'governorate']);
        $adminUrl = OrderResource::getUrl('view', ['record' => $order], panel: 'admin');

        // The store works in Arabic whichever language the shopper used.
        $this->withLocale('ar', function () use ($order, $adminUrl) {
            $this->attempt('telegram', $order, fn () => $this->sendTelegram($order, $adminUrl));
            $this->attempt('email', $order, fn () => $this->sendEmail($order, $adminUrl));
        });
    }

    private function sendTelegram(Order $order, string $adminUrl): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = Setting::get('telegram_chat_id');

        if (blank($token) || blank($chatId)) {
            return;
        }

        Http::timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::TIMEOUT_SECONDS)
            ->post(self::TELEGRAM_API."/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => OrderSummary::telegram($order, $adminUrl),
                'link_preview_options' => ['is_disabled' => true],
            ])
            ->throw();
    }

    private function sendEmail(Order $order, string $adminUrl): void
    {
        $address = Setting::get('notification_email');

        if (blank($address)) {
            return;
        }

        Mail::to($address)->send(new OrderPlacedMail($order, $adminUrl));
    }

    private function attempt(string $channel, Order $order, Closure $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            Log::error('Order notification failed', [
                'channel' => $channel,
                'order' => $order->number,
                // A failed request prints its URL, and the Telegram URL contains the bot token.
                'error' => str_replace((string) config('services.telegram.bot_token'), '[token]', $exception->getMessage()),
            ]);
        }
    }
}
