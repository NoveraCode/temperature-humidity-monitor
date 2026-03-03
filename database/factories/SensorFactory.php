<?php

namespace Database\Factories;

use App\Models\Hmi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sensor>
 */
class SensorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = fake()->unique()->numberBetween(1, 9999);

        return [
            'hmi_id' => Hmi::factory(),
            'name' => 'T/H '.fake()->numberBetween(1, 99),
            'modbus_address_temp' => $base,
            'modbus_address_hum' => $base + 1,
        ];
    }
}
