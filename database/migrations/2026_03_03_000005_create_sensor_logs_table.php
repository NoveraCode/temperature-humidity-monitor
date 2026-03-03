<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->decimal('avg_temperature', 5, 2);
            $table->decimal('avg_humidity', 5, 2);
            $table->timestamps();

            $table->index(['room_id', 'created_at'], 'idx_room_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_logs');
    }
};
