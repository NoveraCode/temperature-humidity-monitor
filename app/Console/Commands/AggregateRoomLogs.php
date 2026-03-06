<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\SensorLog;
use Illuminate\Console\Command;

class AggregateRoomLogs extends Command
{
    protected $signature = 'aggregate:room-logs';

    protected $description = 'Aggregate per-room averages into sensor_logs (runs every 15 minutes)';

    public function handle(): int
    {
        $rooms = Room::with('hmis.sensors.latestData')->get();

        foreach ($rooms as $room) {
            $sensors = $room->hmis->flatMap->sensors;
            $online = $sensors->filter(fn ($s) => $s->latestData?->status !== 'OFFLINE');

            if ($online->isEmpty()) {
                continue;
            }

            SensorLog::create([
                'room_id' => $room->id,
                'avg_temperature' => $online->avg(fn ($s) => $s->latestData->temperature),
                'avg_humidity' => $online->avg(fn ($s) => $s->latestData->humidity),
            ]);
        }

        $this->info('Room log aggregation complete.');

        return self::SUCCESS;
    }
}
