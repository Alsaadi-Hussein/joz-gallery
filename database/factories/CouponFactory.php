<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('JOZ-####'),
            'type' => DiscountType::Percent,
            'value' => 10,
            'min_total' => 0,
            'usage_limit' => null,
            'used_count' => 0,
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }
}
