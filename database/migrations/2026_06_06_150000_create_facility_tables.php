<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Haustechnik/Instandhaltung (DIN 31051): Betriebsmittel mit Prüffristen + Mängelmeldungen mit Status-Workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->string('kategorie');
            $table->string('standort')->nullable();
            $table->string('norm')->nullable();
            $table->unsignedSmallInteger('pruefintervall_monate')->nullable();
            $table->date('letzte_pruefung')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('facility_meldungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->string('standort')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('facility_assets')->nullOnDelete();
            $table->string('prioritaet')->default('mittel');
            $table->string('status')->default('offen');
            $table->foreignId('gemeldet_von')->constrained('users')->cascadeOnDelete();
            $table->date('erledigt_am')->nullable();
            $table->text('erledigt_notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_meldungen');
        Schema::dropIfExists('facility_assets');
    }
};
