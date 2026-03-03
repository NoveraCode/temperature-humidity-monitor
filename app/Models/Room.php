<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<\Database\Factories\RoomFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'location',
        'temp_max_limit',
        'hum_max_limit',
    ];

    protected function casts(): array
    {
        return [
            'temp_max_limit' => 'decimal:2',
            'hum_max_limit' => 'decimal:2',
        ];
    }

    public function hmis(): HasMany
    {
        return $this->hasMany(Hmi::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SensorLog::class);
    }
}
