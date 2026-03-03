<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_latest_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->unique()->constrained('sensors')->cascadeOnDelete();
            $table->float('temperature')->nullable();
            $table->float('humidity')->nullable();
            $table->string('status')->default('OFFLINE'); // NORMAL | WARNING | CRITICAL | OFFLINE
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_latest_data');
    }
};
