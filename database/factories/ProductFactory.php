<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name_ar' => 'منتج '.$nameEn,
            'name_en' => Str::title($nameEn),
            'slug' => Str::slug($nameEn),
            'price' => fake()->numberBetween(10, 300) * 5_000,
            'stock' => fake()->numberBetween(6, 30),
            'is_active' => true,
            'is_new' => false,
            'is_featured' => false,
        ];
    }
}
