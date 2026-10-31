<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title_ar' => 'صفحة تجريبية',
            'title_en' => 'Test page',
            'body_ar' => 'نص تجريبي',
            'body_en' => 'Test body',
            'is_active' => true,
        ];
    }
}
