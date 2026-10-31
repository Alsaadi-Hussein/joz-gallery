<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name_ar' => 'قسم '.$nameEn,
            'name_en' => Str::title($nameEn),
            'slug' => Str::slug($nameEn),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
