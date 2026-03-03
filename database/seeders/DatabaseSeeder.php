<?php

namespace Database\Seeders;

use App\Models\Hmi;
use App\Models\Room;
use App\Models\Sensor;
use App\Models\SensorLatestData;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Topology: 6 rooms × 1 HMI × 5 sensors = 30 sensors total.
     * Status distribution: 20 NORMAL · 5 WARNING · 3 CRITICAL · 2 OFFLINE
     */
    public function run(): void
    {
        // ─── Admin user ───────────────────────────────────────────────────────
        User::factory()->create([
            'name' => 'Admin SCADA',
            'email' => 'admin@scada.local',
        ]);

        // ─── Room names & locations ───────────────────────────────────────────
        $rooms = [
            ['name' => 'RUANG CCTV',   'location' => 'LT.2'],
            ['name' => 'RUANG FIDS',   'location' => 'LT.1'],
            ['name' => 'RUANG SERVER', 'location' => 'LT.3'],
            ['name' => 'RUANG NETWORK', 'location' => 'LT.2'],
            ['name' => 'RUANG UPS',    'location' => 'LT.1'],
            ['name' => 'RUANG GENSET', 'location' => 'LT.B1'],
        ];

        // Status pool: 20 NORMAL, 5 WARNING, 3 CRITICAL, 2 OFFLINE (total 30)
        $statusPool = array_merge(
            array_fill(0, 20, 'NORMAL'),
            array_fill(0, 5, 'WARNING'),
            array_fill(0, 3, 'CRITICAL'),
            array_fill(0, 2, 'OFFLINE'),
        );
        shuffle($statusPool);
        $statusIndex = 0;

        foreach ($rooms as $index => $roomData) {
            $room = Room::factory()->create([
                'name' => $roomData['name'],
                'location' => $roomData['location'],
            ]);

            $hmi = Hmi::factory()->create([
                'room_id' => $room->id,
                'name' => "HMI-0{$index}",
                'ip_address' => '192.168.1.'.(10 + $index),
            ]);

            for ($i = 1; $i <= 5; $i++) {
                $sensor = Sensor::factory()->create([
                    'hmi_id' => $hmi->id,
                    'name' => "{$roomData['name']} T/H {$i}",
                    'modbus_address_temp' => ($index * 100) + (($i - 1) * 2) + 1,
                    'modbus_address_hum' => ($index * 100) + (($i - 1) * 2) + 2,
                ]);

                $status = $statusPool[$statusIndex++];
                $factory = SensorLatestData::factory();

                match ($status) {
                    'NORMAL' => $factory->normal()->create(['sensor_id' => $sensor->id]),
                    'WARNING' => $factory->warning()->create(['sensor_id' => $sensor->id]),
                    'CRITICAL' => $factory->critical()->create(['sensor_id' => $sensor->id]),
                    'OFFLINE' => $factory->offline()->create(['sensor_id' => $sensor->id]),
                };
            }
        }
    }
}
