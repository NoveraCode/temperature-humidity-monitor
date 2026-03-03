<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SensorLog>
 */
class SensorLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'avg_temperature' => fake()->randomFloat(2, 18.00, 30.00),
            'avg_humidity' => fake()->randomFloat(2, 40.00, 75.00),
        ];
    }
}
