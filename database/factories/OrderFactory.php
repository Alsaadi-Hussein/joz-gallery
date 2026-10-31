<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Governorate;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'JG-'.now()->format('y').'-'.fake()->unique()->numerify('####'),
            'name' => fake()->name(),
            'phone' => '0770'.fake()->numerify('#######'),
            'governorate_id' => Governorate::factory(),
            'area' => 'الكرادة',
            'landmark' => null,
            'notes' => null,
            'coupon_id' => null,
            'subtotal' => 100_000,
            'discount' => 0,
            'delivery_fee' => 5_000,
            'total' => 105_000,
            'status' => OrderStatus::New,
            'locale' => 'ar',
            'ip' => fake()->ipv4(),
        ];
    }
}
