<?php

namespace App\Services;

use App\Enums\OfferScope;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Support\OrderTotals;
use App\Support\PriceQuote;
use Illuminate\Support\Collection;

/**
 * The one place prices are worked out. Registered per request, so each page or
 * Livewire call reads the offers once however many products it prices.
 */
class PriceService
{
    /** @var Collection<int, Offer>|null */
    private ?Collection $offers = null;

    /** @var Collection<int, int|null>|null */
    private ?Collection $categoryParents = null;

    public function quote(Product $product, ?ProductVariant $variant = null): PriceQuote
    {
        $base = $this->basePrice($product, $variant);
        $offer = $this->offerFor($product, $base);

        return new PriceQuote($base, $base - ($offer?->amountOff($base) ?? 0), $offer);
    }

    public function finalPrice(Product $product, ?ProductVariant $variant = null): int
    {
        return $this->quote($product, $variant)->final;
    }

    public function basePrice(Product $product, ?ProductVariant $variant = null): int
    {
        return max(0, $product->price + ($variant?->price_delta ?? 0));
    }

    /**
     * Product offers beat category offers, which beat store-wide offers. Only inside
     * the winning level does the biggest discount decide, and offers never stack.
     */
    public function offerFor(Product $product, int $price): ?Offer
    {
        $levels = [
            $this->offers()->filter(fn (Offer $offer) => $offer->scope === OfferScope::Product && $product->exists && $offer->target_id === $product->id),
            $this->offers()->filter(fn (Offer $offer) => $offer->scope === OfferScope::Category && in_array($offer->target_id, $this->categoryChain($product), true)),
            $this->offers()->filter(fn (Offer $offer) => $offer->scope === OfferScope::All),
        ];

        foreach ($levels as $offers) {
            if ($offers->isNotEmpty()) {
                return $offers->sortByDesc(fn (Offer $offer) => $offer->amountOff($price))->first();
            }
        }

        return null;
    }

    /**
     * Why this coupon cannot be used on this subtotal, ready to show to the shopper,
     * or null when it can. The subtotal is the one after offers.
     */
    public function couponProblem(?Coupon $coupon, int $subtotal): ?string
    {
        return match (true) {
            $coupon === null, ! $coupon->is_active => __('shop.coupon.invalid'),
            $coupon->isExpired() => __('shop.coupon.expired'),
            $coupon->isUsedUp() => __('shop.coupon.used_up'),
            $subtotal < $coupon->min_total => __('shop.coupon.min_total', ['amount' => Money::format($coupon->min_total)]),
            default => null,
        };
    }

    /**
     * The coupon comes off the subtotal, never the delivery fee, and never takes the
     * subtotal below zero.
     */
    public function totals(int $subtotal, ?Coupon $coupon, int $deliveryFee): OrderTotals
    {
        $discount = $coupon?->type->amountOff($subtotal, $coupon->value) ?? 0;

        return new OrderTotals($subtotal, $discount, $deliveryFee);
    }

    /**
     * Forget loaded offers. Only needed when offers change during a single request.
     */
    public function flush(): void
    {
        $this->offers = null;
        $this->categoryParents = null;
    }

    /**
     * @return Collection<int, Offer>
     */
    private function offers(): Collection
    {
        return $this->offers ??= Offer::query()->active()->get();
    }

    /**
     * The product's category followed by each of its parents.
     *
     * @return list<int>
     */
    private function categoryChain(Product $product): array
    {
        $this->categoryParents ??= Category::query()->pluck('parent_id', 'id');

        $chain = [];

        for ($id = $product->category_id; $id !== null && ! in_array($id, $chain, true); $id = $this->categoryParents->get($id)) {
            $chain[] = $id;
        }

        return $chain;
    }
}
