<?php

namespace Database\Seeders;

use App\Models\Hmi;
use App\Models\Room;
use App\Models\Sensor;
use App\Models\SensorLatestData;
use App\Models\SensorLog;
use App\Models\SensorReading;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Seed dummy data matching the documented API shape.
     */
    public function run(): void
    {
        // ── Room 1: RUANG CCTV ──────────────────────────────────────────────
        $roomCctv = Room::create([
            'name' => 'RUANG CCTV',
            'location' => 'LT.2',
            'temp_max_limit' => 25.0,
            'hum_max_limit' => 60.0,
        ]);

        $hmiCctv = Hmi::create([
            'room_id' => $roomCctv->id,
            'name' => 'HMI CCTV',
            'ip_address' => '192.168.1.10',
            'port' => 502,
            'is_active' => true,
        ]);

        /** @var array<int, array{name: string, temp: float, hum: float, status: string}> */
        $cctvSensors = [
            ['name' => 'R.CCTV T/H 1', 'temp' => 27.8, 'hum' => 61.7, 'status' => 'WARNING'],
            ['name' => 'R.CCTV T/H 2', 'temp' => 27.1, 'hum' => 62.1, 'status' => 'WARNING'],
            ['name' => 'R.CCTV T/H 3', 'temp' => 28.4, 'hum' => 63.2, 'status' => 'WARNING'],
            ['name' => 'R.CCTV T/H 4', 'temp' => 29.8, 'hum' => 63.4, 'status' => 'WARNING'],
            ['name' => 'R.CCTV T/H 5', 'temp' => 27.2, 'hum' => 67.0, 'status' => 'WARNING'],
        ];

        foreach ($cctvSensors as $i => $data) {
            $sensor = Sensor::create([
                'hmi_id' => $hmiCctv->id,
                'name' => $data['name'],
                'modbus_address_temp' => ($i * 2) + 1,
                'modbus_address_hum' => ($i * 2) + 2,
            ]);

            SensorLatestData::create([
                'sensor_id' => $sensor->id,
                'temperature' => $data['temp'],
                'humidity' => $data['hum'],
                'status' => $data['status'],
                'last_read_at' => now(),
            ]);

            // 20 historical readings per sensor, 1 minute apart
            for ($j = 19; $j >= 0; $j--) {
                SensorReading::create([
                    'sensor_id' => $sensor->id,
                    'avg_temp' => round($data['temp'] + (mt_rand(-10, 10) / 10), 2),
                    'avg_hum' => round($data['hum'] + (mt_rand(-10, 10) / 10), 2),
                    'created_at' => Carbon::now()->subMinutes($j),
                ]);
            }
        }

        // Sensor logs (chart data) — 20 points spaced 1 minute apart
        for ($i = 19; $i >= 0; $i--) {
            SensorLog::create([
                'room_id' => $roomCctv->id,
                'avg_temperature' => round(27.0 + (mt_rand(-10, 30) / 10), 1),
                'avg_humidity' => round(62.0 + (mt_rand(-20, 50) / 10), 1),
                'created_at' => Carbon::now()->subMinutes($i),
                'updated_at' => Carbon::now()->subMinutes($i),
            ]);
        }

        // ── Room 2: RUANG FIDS ──────────────────────────────────────────────
        $roomFids = Room::create([
            'name' => 'RUANG FIDS',
            'location' => 'LT.1',
            'temp_max_limit' => 25.0,
            'hum_max_limit' => 60.0,
        ]);

        $hmiFids = Hmi::create([
            'room_id' => $roomFids->id,
            'name' => 'HMI FIDS',
            'ip_address' => '192.168.1.11',
            'port' => 502,
            'is_active' => true,
        ]);

        /** @var array<int, array{name: string}> */
        $fidsSensors = [
            ['name' => 'R.FIDS T/H 1'],
            ['name' => 'R.FIDS T/H 2'],
        ];

        foreach ($fidsSensors as $i => $data) {
            $sensor = Sensor::create([
                'hmi_id' => $hmiFids->id,
                'name' => $data['name'],
                'modbus_address_temp' => 100 + ($i * 2) + 1,
                'modbus_address_hum' => 100 + ($i * 2) + 2,
            ]);

            SensorLatestData::create([
                'sensor_id' => $sensor->id,
                'temperature' => null,
                'humidity' => null,
                'status' => 'OFFLINE',
                'last_read_at' => null,
            ]);
        }
    }
}
