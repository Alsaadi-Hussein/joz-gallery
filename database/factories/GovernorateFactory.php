<?php

namespace Database\Factories;

use App\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Governorate>
 */
class GovernorateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'بغداد',
            'name_en' => 'Baghdad',
            'delivery_fee' => 5_000,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
