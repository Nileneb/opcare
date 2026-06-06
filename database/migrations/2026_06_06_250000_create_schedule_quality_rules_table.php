<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editierbare „positive"/ergonomische Schichtplan-Regeln je Einrichtung — zweite Stufe nach der harten
 * ArbZG-Prüfung (§ 6 ArbZG verweist auf gesicherte arbeitswissenschaftliche Erkenntnisse, BAuA/BGHM/DGAUM).
 * Schwellwerte (`params`), Schwere und Aktivierung sind einrichtungsspezifisch anpassbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_quality_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('kategorie');
            $table->string('severity'); // warnung | hinweis
            $table->json('params');
            $table->string('quelle');
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_quality_rules');
    }
};
