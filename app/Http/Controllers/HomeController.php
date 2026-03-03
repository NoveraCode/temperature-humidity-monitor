<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\SensorLog;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $rooms = Room::with([
            'hmis.sensors.latestData',
        ])->get()->map(function (Room $room) {
            $sensors = $room->hmis->flatMap(fn($hmi) => $hmi->sensors);

            $onlineSensors = $sensors->filter(
                fn($sensor) => $sensor->latestData && $sensor->latestData->status !== 'OFFLINE',
            );

            $roomAvgTemp = $onlineSensors->isNotEmpty()
                ? round($onlineSensors->avg(fn($s) => $s->latestData->temperature), 1)
                : null;

            $roomAvgHum = $onlineSensors->isNotEmpty()
                ? round($onlineSensors->avg(fn($s) => $s->latestData->humidity), 1)
                : null;

            $lastUpdate = $onlineSensors->isNotEmpty()
                ? $onlineSensors->max(fn($s) => $s->latestData->last_read_at)
                : null;

            $roomStatus = $this->resolveRoomStatus($sensors);

            return [
                'id' => $room->id,
                'name' => $room->name,
                'location' => $room->location,
                'temp_max_limit' => $room->temp_max_limit,
                'hum_max_limit' => $room->hum_max_limit,
                'room_avg_temp' => $roomAvgTemp,
                'room_avg_hum' => $roomAvgHum,
                'status' => $roomStatus,
                'last_update' => $lastUpdate,
                'sensors' => $sensors->map(fn($sensor) => [
                    'id' => $sensor->id,
                    'name' => $sensor->name,
                    'temperature' => $sensor->latestData?->temperature !== null ? (float) $sensor->latestData->temperature : null,
                    'humidity' => $sensor->latestData?->humidity !== null ? (float) $sensor->latestData->humidity : null,
                    'status' => $sensor->latestData?->status ?? 'OFFLINE',
                    'last_read_at' => $sensor->latestData?->last_read_at,
                ])->values(),
            ];
        });

        $chartLogs = Room::with([
            'logs' => fn($q) => $q->latest()->limit(20),
        ])->get()->mapWithKeys(fn(Room $room) => [
            $room->id => $room->logs->reverse()->map(fn($log) => [
                'time' => $log->created_at->format('H:i'),
                'avg_temperature' => $log->avg_temperature !== null ? (float) $log->avg_temperature : null,
                'avg_humidity' => $log->avg_humidity !== null ? (float) $log->avg_humidity : null,
            ])->values(),
        ]);

        $allSensors = $rooms->flatMap(fn($r) => $r['sensors']);
        $onlineAll = $allSensors->filter(fn($s) => $s['status'] !== 'OFFLINE');

        $globalStats = [
            'avg_temp' => $onlineAll->isNotEmpty()
                ? round($onlineAll->avg('temperature'), 1)
                : null,
            'avg_hum' => $onlineAll->isNotEmpty()
                ? round($onlineAll->avg('humidity'), 1)
                : null,
            'active_alarms' => $allSensors->filter(
                fn($s) => in_array($s['status'], ['WARNING', 'CRITICAL']),
            )->count(),
            'last_update' => SensorLog::latest()->value('created_at'),
        ];

        return Inertia::render('home', [
            'rooms' => $rooms,
            'chartLogs' => $chartLogs,
            'globalStats' => $globalStats,
        ]);
    }

    /**
     * Determine a room's aggregate status from its sensors.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Sensor>  $sensors
     */
    private function resolveRoomStatus(\Illuminate\Support\Collection $sensors): string
    {
        $statuses = $sensors->map(fn($s) => $s->latestData?->status ?? 'OFFLINE');

        if ($statuses->every(fn($s) => $s === 'OFFLINE')) {
            return 'OFFLINE';
        }

        if ($statuses->contains('CRITICAL')) {
            return 'CRITICAL';
        }

        if ($statuses->contains('WARNING')) {
            return 'WARNING';
        }

        return 'NORMAL';
    }
}
