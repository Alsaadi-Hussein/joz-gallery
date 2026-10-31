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
use Illuminate\Support\Str;
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
     * The next JG-YY-#### for this year. The lock on the latest order makes two
     * checkouts take turns, so they can never get the same number.
     */
    public static function nextNumber(): string
    {
        $prefix = 'JG-'.now()->format('y').'-';

        $last = static::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first(['id', 'number']);

        $sequence = $last ? (int) Str::afterLast($last->number, '-') + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Signed, because the page shows personal details and the number alone is guessable.
     */
    public function thanksUrl(): string
    {
        $route = ($this->locale === 'en' ? 'en.' : '').'order.thanks';

        return URL::signedRoute($route, ['order' => $this->number]);
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
