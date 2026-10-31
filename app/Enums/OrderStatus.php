<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Shipping = 'shipping';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('admin.enums.order_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Confirmed => 'info',
            self::Shipping => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }
}
