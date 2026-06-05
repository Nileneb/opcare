<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // WHY(ÜLB): trägt die codierten Einzel-Observations der Pflegeüberleitung (Bewusstsein, Kontinenz,
    // Ernährung, Atmung) generisch — ein Typ-Schlüssel je StatusObservationCatalog-Eintrag, Wert codiert
    // (SNOMED) ODER frei. Vermeidet eine bespoke Tabelle je ÜLB-Sektion.
    public function up(): void
    {
        Schema::create('resident_status_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('typ');           // Catalog-Schlüssel (bewusstsein|harnkontinenz|…)
            $table->string('wert_code')->nullable();   // SNOMED-Code (codierte Werte)
            $table->string('wert_text')->nullable();   // Freitext (kind=text)
            $table->date('erfasst_am')->nullable();
            $table->timestamps();
            $table->index(['resident_id', 'typ']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_status_observations');
    }
};
