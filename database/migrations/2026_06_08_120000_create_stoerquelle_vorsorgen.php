<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notfallvorsorge je Störquelle (Top-Ausfälle der Haustechnik): Mindest-Ersatzteile, schriftlich fixierte
 * Dienstleister-Reaktionszeit und interne Sofortmaßnahmen-Checkliste. Die Top-10-Auswertung selbst entsteht
 * zur Laufzeit aus facility_meldungen — hier wird nur die je Störquelle hinterlegte Vorsorge persistiert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stoerquelle_vorsorgen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->string('kategorie');
            // Optional an ein konkretes Betriebsmittel gebunden; sonst greift die Vorsorge kategorieweit.
            $table->foreignId('asset_id')->nullable()->constrained('facility_assets')->nullOnDelete();
            $table->text('mindest_ersatzteile')->nullable();
            $table->string('dienstleister')->nullable();
            $table->string('dienstleister_kontakt')->nullable();
            // Schriftlich fixierte Reaktionszeit: Freitext-SLA ("4 h", "nächster Werktag", "24/7-Notdienst")
            // plus optionale Stundenzahl für Sortierung/Ampel.
            $table->string('reaktionszeit')->nullable();
            $table->unsignedSmallInteger('reaktionszeit_stunden')->nullable();
            $table->json('sofortmassnahmen')->nullable();
            $table->text('notiz')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'aktiv']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stoerquelle_vorsorgen');
    }
};
