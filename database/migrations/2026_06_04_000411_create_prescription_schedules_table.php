<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('frequenz');
            $table->unsignedSmallInteger('intervall')->default(1);
            $table->jsonb('wochentage')->nullable();
            $table->jsonb('dosis');
            $table->decimal('max_anzahl_taeglich', 5, 2)->nullable();
            $table->decimal('max_einzeldosis', 8, 3)->nullable();
            $table->timestamps();
            $table->index('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_schedules');
    }
};
