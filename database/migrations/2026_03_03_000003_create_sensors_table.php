<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hmi_id')->constrained('hmis')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('modbus_address_temp');
            $table->unsignedInteger('modbus_address_hum');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
