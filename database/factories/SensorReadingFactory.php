<?php

namespace Database\Factories;

use App\Models\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SensorReading>
 */
class SensorReadingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sensor_id' => Sensor::factory(),
            'avg_temp' => fake()->randomFloat(2, 18.00, 30.00),
            'avg_hum' => fake()->randomFloat(2, 40.00, 75.00),
            'created_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
