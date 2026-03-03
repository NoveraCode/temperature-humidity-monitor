<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorLatestData extends Model
{
    /** @use HasFactory<\Database\Factories\SensorLatestDataFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'sensor_id',
        'temperature',
        'humidity',
        'status',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature'  => 'float',
            'humidity'     => 'float',
            'last_read_at' => 'datetime',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
}
