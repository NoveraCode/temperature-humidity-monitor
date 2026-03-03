<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors')->cascadeOnDelete();
            $table->decimal('avg_temp', 5, 2);
            $table->decimal('avg_hum', 5, 2);
            $table->timestamp('created_at');

            $table->index(['sensor_id', 'created_at'], 'idx_sensor_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
