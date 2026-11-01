<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use LogicException;

#[Fillable([
    'number', 'name', 'phone', 'governorate_id', 'area', 'landmark', 'notes', 'coupon_id',
    'subtotal', 'discount', 'delivery_fee', 'total', 'status', 'locale', 'ip',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'status' => OrderStatus::class,
        ];
    }

    /**
     * The next JG-YY-#### for this year. Call it first in the transaction: its row
     * lock makes checkouts take turns, and MariaDB refuses to lock a row that changed
     * after the transaction's first plain read. Counting in a separate table also
     * means a deleted order's number is never reused, because an old signed link
     * would then open another customer's order.
     */
    public static function nextNumber(): string
    {
        $year = now()->format('y');

        DB::table('order_sequences')->upsert(
            ['year' => $year, 'last_number' => 1],
            ['year'],
            ['last_number' => DB::raw('last_number + 1')],
        );

        $sequence = DB::table('order_sequences')->where('year', $year)->lockForUpdate()->value('last_number');

        return "JG-{$year}-".str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Signed, because the page shows personal details and the number alone is guessable.
     * The signature covers the path only, so it survives www, http and proxy differences.
     */
    public function thanksUrl(): string
    {
        $route = ($this->locale === 'en' ? 'en.' : '').'order.thanks';

        return URL::signedRoute($route, ['order' => $this->number], absolute: false);
    }

    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::Cancelled;
    }

    /**
     * Cancelling is final: it hands the stock and the coupon back, and by the time
     * someone changes their mind those may already have gone to another order.
     */
    public function changeStatus(OrderStatus $status): void
    {
        DB::transaction(function () use ($status) {
            $order = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($order->isCancelled()) {
                throw new LogicException("Order {$order->number} is cancelled and cannot change status.");
            }

            if ($status === OrderStatus::Cancelled) {
                $order->returnStockAndCoupon();
            }

            $order->update(['status' => $status]);
        });

        $this->status = $status;
    }

    private function returnStockAndCoupon(): void
    {
        $this->load(['items.product', 'items.variant']);

        foreach ($this->items as $item) {
            if ($item->variant) {
                $item->variant->increment('stock', $item->qty);
            } elseif ($item->product && $item->variant_snapshot === null) {
                // A deleted variant must not add stock to the product it belonged to.
                $item->product->increment('stock', $item->qty);
            }
        }

        Coupon::query()->whereKey($this->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
    }

    /**
     * @return BelongsTo<Governorate, $this>
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
