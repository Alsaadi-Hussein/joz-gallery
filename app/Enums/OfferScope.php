<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OfferScope: string implements HasLabel
{
    case Product = 'product';
    case Category = 'category';
    case All = 'all';

    public function getLabel(): string
    {
        return __('admin.enums.offer_scope.'.$this->value);
    }
}
