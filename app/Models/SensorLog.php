<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorLog extends Model
{
    /** @use HasFactory<\Database\Factories\SensorLogFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'room_id',
        'avg_temperature',
        'avg_humidity',
    ];

    protected function casts(): array
    {
        return [
            'avg_temperature' => 'float',
            'avg_humidity'    => 'float',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
