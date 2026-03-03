<?php

namespace Database\Factories;

use App\Models\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SensorLatestData>
 */
class SensorLatestDataFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sensor_id' => Sensor::factory(),
            'temperature' => fake()->randomFloat(2, 18.00, 30.00),
            'humidity' => fake()->randomFloat(2, 40.00, 75.00),
            'status' => 'NORMAL',
            'last_read_at' => now(),
        ];
    }

    public function normal(): static
    {
        return $this->state(fn (array $attributes) => [
            'temperature' => fake()->randomFloat(2, 18.00, 25.00),
            'humidity' => fake()->randomFloat(2, 40.00, 60.00),
            'status' => 'NORMAL',
            'last_read_at' => now(),
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'temperature' => fake()->randomFloat(2, 25.01, 30.00),
            'humidity' => fake()->randomFloat(2, 60.01, 75.00),
            'status' => 'WARNING',
            'last_read_at' => now(),
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'temperature' => fake()->randomFloat(2, 30.01, 40.00),
            'humidity' => fake()->randomFloat(2, 75.01, 95.00),
            'status' => 'CRITICAL',
            'last_read_at' => now(),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'OFFLINE',
            'last_read_at' => now()->subMinutes(fake()->numberBetween(5, 60)),
        ]);
    }
}
