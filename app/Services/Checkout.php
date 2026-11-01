<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Exceptions\CheckoutFailed;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Checkout
{
    // Two shoppers can deadlock over the same rows; MariaDB kills one and it retries.
    private const ATTEMPTS = 3;

    public function __construct(private readonly PriceService $prices) {}

    /**
     * Places a cash-on-delivery order. Every price is worked out here from the
     * database; nothing price-related is taken from the browser.
     *
     * @param  array{name: string, phone: string, governorate_id: int, area: string, landmark?: string|null, notes?: string|null}  $customer
     * @param  array<int, array{product_id: int, variant_id?: int|null, qty: int}>  $items
     *
     * @throws CheckoutFailed
     */
    public function place(array $customer, array $items, ?string $couponCode, string $locale, ?string $ip): Order
    {
        $order = DB::transaction(
            fn () => $this->createOrder($customer, Cart::merge($items), $couponCode, $locale, $ip),
            self::ATTEMPTS,
        );

        // The order is safely saved by now, so a queue problem must not reach the shopper.
        rescue(fn () => OrderPlaced::dispatch($order));

        return $order;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @param  list<array{product_id: int, variant_id: int|null, qty: int}>  $items
     */
    private function createOrder(array $customer, array $items, ?string $couponCode, string $locale, ?string $ip): Order
    {
        if ($items === []) {
            throw new CheckoutFailed(__('shop.checkout.cart_empty'));
        }

        $number = Order::nextNumber();
        $lines = $this->lockLines($items);
        $subtotal = $lines->sum(fn (array $line) => $line['line_total']);
        $coupon = $this->lockCoupon($couponCode, $subtotal);
        $totals = $this->prices->totals($subtotal, $coupon, Governorate::query()->findOrFail($customer['governorate_id'])->delivery_fee);

        $order = Order::query()->create([
            'number' => $number,
            'name' => $customer['name'],
            'phone' => $customer['phone'],
            'governorate_id' => $customer['governorate_id'],
            'area' => $customer['area'],
            'landmark' => $customer['landmark'] ?? null,
            'notes' => $customer['notes'] ?? null,
            'coupon_id' => $coupon?->id,
            'subtotal' => $totals->subtotal,
            'discount' => $totals->discount,
            'delivery_fee' => $totals->deliveryFee,
            'total' => $totals->total(),
            'status' => OrderStatus::New,
            'locale' => $locale,
            'ip' => $ip,
        ]);

        $lines->each(function (array $line) use ($order) {
            ($line['variant'] ?? $line['product'])->decrement('stock', $line['qty']);

            $order->items()->create([
                'product_id' => $line['product']->id,
                'variant_id' => $line['variant']?->id,
                'name_snapshot' => $line['product']->localized('name'),
                'variant_snapshot' => $line['variant']?->label(),
                'unit_price' => $line['unit_price'],
                'qty' => $line['qty'],
                'line_total' => $line['line_total'],
            ]);
        });

        $coupon?->increment('used_count');

        return $order;
    }

    /**
     * Locks every product and variant in the cart, in id order so two carts cannot
     * lock each other out, then checks stock against the locked rows.
     *
     * @param  list<array{product_id: int, variant_id: int|null, qty: int}>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function lockLines(array $items): Collection
    {
        $productIds = array_column($items, 'product_id');

        $products = Product::query()->visible()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $variants = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('product_id');

        return collect($items)->map(function (array $item) use ($products, $variants) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new CheckoutFailed(__('shop.checkout.item_unavailable'));
            }

            $product->setRelation('variants', $variants->get($product->id, collect()));
            $variant = $product->variants->firstWhere('id', $item['variant_id']);
            $name = $product->localized('name');

            if (! Cart::isValidChoice($product, $variant, $item['variant_id'])) {
                throw new CheckoutFailed(__('shop.checkout.item_unavailable_named', ['name' => $name]));
            }

            $stock = $variant->stock ?? $product->stock;

            if ($stock < 1) {
                throw new CheckoutFailed(__('shop.checkout.sold_out', ['name' => $name]));
            }

            if ($stock < $item['qty']) {
                throw new CheckoutFailed(__('shop.checkout.not_enough', ['name' => $name, 'stock' => $stock]));
            }

            $unitPrice = $this->prices->finalPrice($product, $variant);

            return [
                'product' => $product,
                'variant' => $variant,
                'qty' => $item['qty'],
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $item['qty'],
            ];
        });
    }

    /**
     * Locked so the last use of a limited coupon can only go to one order.
     */
    private function lockCoupon(?string $code, int $subtotal): ?Coupon
    {
        if (blank($code)) {
            return null;
        }

        $coupon = Coupon::query()->whereCode($code)->lockForUpdate()->first();
        $problem = $this->prices->couponProblem($coupon, $subtotal);

        if ($problem !== null) {
            throw new CheckoutFailed($problem);
        }

        return $coupon;
    }
}
