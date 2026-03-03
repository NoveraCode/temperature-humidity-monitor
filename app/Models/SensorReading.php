<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorReading extends Model
{
    /** @use HasFactory<\Database\Factories\SensorReadingFactory> */
    use HasFactory;

    /** Baris ini tidak pernah diubah setelah insert — nonaktifkan updated_at. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'sensor_id',
        'avg_temp',
        'avg_hum',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'avg_temp' => 'decimal:2',
            'avg_hum' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
}
