<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trinkwasser-Großanlagen mit Legionellen-Untersuchungspflicht (§ 31 TrinkwV 2023).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trinkwasseranlagen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->string('gebaeude')->nullable();
            $table->boolean('ist_grossanlage')->default(true);
            $table->unsignedSmallInteger('untersuchungsintervall_monate')->default(12);
            $table->date('letzte_untersuchung_am')->nullable();
            $table->text('notiz')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trinkwasseranlagen');
    }
};
