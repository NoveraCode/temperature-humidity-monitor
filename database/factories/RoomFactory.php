<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'RUANG '.strtoupper(fake()->word()),
            'location' => 'LT.'.fake()->numberBetween(1, 5),
            'temp_max_limit' => fake()->randomFloat(2, 24.00, 28.00),
            'hum_max_limit' => fake()->randomFloat(2, 55.00, 70.00),
        ];
    }
}
