<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;

class OrderSummary
{
    /**
     * Plain-text item lines for messages: "• Sofa (Green / L) × 2 = 300,000 IQD".
     */
    public static function items(Order $order): string
    {
        return $order->items
            ->map(fn (OrderItem $item) => sprintf(
                '• %s%s × %d = %s',
                $item->name_snapshot,
                $item->variant_snapshot ? " ({$item->variant_snapshot})" : '',
                $item->qty,
                Money::format($item->line_total),
            ))
            ->implode("\n");
    }

    /**
     * The message a shopper sends the store from the thanks page.
     */
    public static function whatsapp(Order $order): string
    {
        return implode("\n", [
            __('shop.thanks.whatsapp_intro', ['number' => $order->number]),
            self::items($order),
            __('shop.checkout.total').': '.Money::format($order->total),
        ]);
    }

    /**
     * The alert the store gets on Telegram.
     */
    public static function telegram(Order $order, string $adminUrl): string
    {
        return implode("\n", array_filter([
            __('orders.telegram.title', ['number' => $order->number]),
            __('orders.telegram.name', ['value' => $order->name]),
            __('orders.telegram.phone', ['value' => $order->phone]),
            __('orders.telegram.address', ['governorate' => $order->governorate->name_ar, 'area' => $order->area]),
            $order->landmark ? __('orders.telegram.landmark', ['value' => $order->landmark]) : null,
            $order->notes ? __('orders.telegram.notes', ['value' => $order->notes]) : null,
            '',
            self::items($order),
            '',
            __('orders.telegram.total', ['value' => Money::format($order->total)]),
            $adminUrl,
        ], fn (?string $line) => $line !== null));
    }
}
