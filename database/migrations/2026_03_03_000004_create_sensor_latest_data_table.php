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
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->enum('status', ['NORMAL', 'WARNING', 'CRITICAL', 'OFFLINE'])->default('OFFLINE');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_status');
            $table->index('last_read_at', 'idx_last_read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_latest_data');
    }
};
