<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\OfferScope;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title_ar' => 'عرض تجريبي',
            'title_en' => 'Test offer',
            'type' => DiscountType::Percent,
            'value' => 10,
            'scope' => OfferScope::All,
            'target_id' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => OfferScope::Product,
            'target_id' => $product->id,
        ]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => OfferScope::Category,
            'target_id' => $category->id,
        ]);
    }

    public function percent(int $value): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DiscountType::Percent,
            'value' => $value,
        ]);
    }

    public function fixed(int $value): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DiscountType::Fixed,
            'value' => $value,
        ]);
    }
}
