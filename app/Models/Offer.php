<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\OfferScope;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title_ar', 'title_en', 'type', 'value', 'scope', 'target_id', 'starts_at', 'ends_at', 'is_active'])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'scope' => OfferScope::class,
            'value' => 'integer',
            'target_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Offer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function amountOff(int $price): int
    {
        return $this->type->amountOff($price, $this->value);
    }
}
