<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $rooms = Room::with([
            'hmis.sensors' => fn ($q) => $q->select(['id', 'hmi_id', 'name']),
            'hmis.sensors.latestData' => fn ($q) => $q->select(['id', 'sensor_id', 'temperature', 'humidity', 'status', 'last_read_at']),
        ])
            ->select(['id', 'name', 'location', 'temp_max_limit', 'hum_max_limit'])
            ->get();

        $payload = $rooms->map(function (Room $room) {
            $sensors = $room->hmis->flatMap->sensors;
            $online = $sensors->filter(fn ($s) => $s->latestData?->status !== 'OFFLINE');

            return [
                'id' => $room->id,
                'name' => $room->name,
                'location' => $room->location,
                'room_avg_temp' => $online->isNotEmpty()
                    ? round((float) $online->avg(fn ($s) => $s->latestData->temperature), 1)
                    : null,
                'room_avg_hum' => $online->isNotEmpty()
                    ? round((float) $online->avg(fn ($s) => $s->latestData->humidity), 1)
                    : null,
                'status' => $this->resolveRoomStatus($sensors),
                'sensors' => $sensors->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'temperature' => $s->latestData?->temperature !== null
                        ? (float) $s->latestData->temperature
                        : null,
                    'humidity' => $s->latestData?->humidity !== null
                        ? (float) $s->latestData->humidity
                        : null,
                    'status' => $s->latestData?->status ?? 'OFFLINE',
                ])->values()->all(),
            ];
        });

        $onlineRooms = $payload->whereNotNull('room_avg_temp');
        $globalAvgTemp = $onlineRooms->isNotEmpty()
            ? round((float) $onlineRooms->avg('room_avg_temp'), 1)
            : null;
        $globalAvgHum = $onlineRooms->isNotEmpty()
            ? round((float) $onlineRooms->avg('room_avg_hum'), 1)
            : null;

        $activeAlarms = $payload
            ->flatMap(fn ($r) => $r['sensors'])
            ->whereIn('status', ['WARNING', 'CRITICAL'])
            ->count();

        return Inertia::render('dashboard', [
            'globalStats' => [
                'avg_temp' => $globalAvgTemp,
                'avg_hum' => $globalAvgHum,
                'active_alarms' => $activeAlarms,
                'last_update' => now()->toDateTimeString(),
            ],
            'rooms' => $payload->values()->all(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Sensor>  $sensors
     */
    private function resolveRoomStatus(Collection|\Illuminate\Support\Collection $sensors): string
    {
        $statuses = $sensors->pluck('latestData.status')->filter()->unique();

        if ($statuses->contains('CRITICAL')) {
            return 'CRITICAL';
        }

        if ($statuses->contains('WARNING')) {
            return 'WARNING';
        }

        if ($statuses->isNotEmpty() && $statuses->every(fn ($s) => $s === 'OFFLINE')) {
            return 'OFFLINE';
        }

        return 'NORMAL';
    }
}
