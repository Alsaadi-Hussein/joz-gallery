<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return __('admin.enums.discount_type.'.$this->value);
    }

    /**
     * The whole-dinar amount this discount takes off the given price, never more than the price.
     */
    public function amountOff(int $price, int $value): int
    {
        $price = max(0, $price);

        return match ($this) {
            // Integer maths with half-up rounding, so prices never pass through a float.
            self::Percent => intdiv($price * min($value, 100) + 50, 100),
            self::Fixed => min($value, $price),
        };
    }
}
