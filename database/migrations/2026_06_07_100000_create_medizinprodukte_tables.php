<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MPBetreibV 2025: Bestandsverzeichnis (§ 14) aller aktiven nichtimplantierbaren Medizinprodukte +
 * Medizinproduktebuch (§ 13) für Anlage-1/2-Produkte (Einweisungen, STK/MTK-Fristen, Vorkommnisse).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medizinprodukte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('bezeichnung');                          // § 14: Bezeichnung/Art
            $table->string('typ')->nullable();                      // § 14: Typ
            $table->string('hersteller')->nullable();               // § 14: Hersteller/Bevollmächtigter
            $table->string('seriennummer')->nullable();             // § 14: Los-/Seriennummer
            $table->string('udi_di')->nullable();                   // UDI-DI (MDR, sofern vorhanden)
            $table->string('inventarnummer')->nullable();           // § 14: betriebl. Identifikationsnummer
            $table->unsignedSmallInteger('anschaffungsjahr')->nullable();
            $table->string('standort')->nullable();                 // § 14: Standort
            $table->string('zuordnung')->nullable();                // § 14: betriebliche Zuordnung (Wohnbereich/Station)
            $table->string('risikoklasse')->nullable();             // MDR-Klasse I/IIa/IIb/III (informativ)
            $table->string('anlage')->default('keine');             // keine | anlage1 | anlage2 (treibt § 13/STK/MTK)
            $table->date('inbetriebnahme_am')->nullable();
            $table->date('letzte_stk')->nullable();                 // § 12 sicherheitstechn. Kontrolle
            $table->unsignedSmallInteger('stk_intervall_monate')->nullable();
            $table->date('letzte_mtk')->nullable();                 // § 15 messtechn. Kontrolle
            $table->unsignedSmallInteger('mtk_intervall_monate')->nullable();
            $table->date('ausser_betrieb_am')->nullable();          // Außerbetriebnahme (aktiv = null)
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'anlage']);
        });

        Schema::create('medizinprodukt_einweisungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medizinprodukt_id')->constrained('medizinprodukte')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // eingewiesene Person
            $table->date('eingewiesen_am');
            $table->string('eingewiesen_durch')->nullable();        // Einweiser:in (ggf. Hersteller/extern)
            $table->string('art')->default('ersteinweisung');       // ersteinweisung | folgeeinweisung
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'medizinprodukt_id']);
        });

        Schema::create('medizinprodukt_vorkommnisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medizinprodukt_id')->constrained('medizinprodukte')->cascadeOnDelete();
            $table->date('datum');
            $table->string('art');                                  // funktionsstoerung | beinahe_vorkommnis | vorkommnis
            $table->text('beschreibung');
            $table->text('massnahme')->nullable();
            $table->boolean('bfarm_gemeldet')->default(false);      // Meldung an BfArM (§ 3 MPAMIV)
            $table->foreignId('gemeldet_von')->constrained('users')->cascadeOnDelete();
            $table->date('behoben_am')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'medizinprodukt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medizinprodukt_vorkommnisse');
        Schema::dropIfExists('medizinprodukt_einweisungen');
        Schema::dropIfExists('medizinprodukte');
    }
};
