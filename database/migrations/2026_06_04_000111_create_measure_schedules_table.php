<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measure_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_measure_id')->constrained()->cascadeOnDelete();
            $table->string('turnus_typ'); // schicht/uhrzeit/intervall
            $table->jsonb('turnus_daten');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_schedules');
    }
};
