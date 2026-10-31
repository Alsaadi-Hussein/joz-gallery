<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'color_ar' => 'بيج',
            'color_en' => 'Beige',
            'size' => fake()->randomElement(['S', 'M', 'L']),
            'price_delta' => 0,
            'stock' => fake()->numberBetween(1, 20),
        ];
    }
}
