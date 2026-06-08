<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('belastungsmeldungen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->string('wohnbereich');
            $table->string('stufe');
            $table->integer('score');
            $table->json('signale');
            $table->date('gemeldet_am');
            $table->foreignId('quittiert_von')->nullable()->constrained('users')->nullOnDelete();
            $table->date('quittiert_am')->nullable();
            $table->foreignId('schutzmassnahme_id')->nullable()->constrained('schutzmassnahmen')->nullOnDelete();
            $table->text('notiz')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('belastungsmeldungen');
    }
};
