<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adress-Stammdaten für Bewohner (Patient) + Ärzte/Praxis (Organization). Reale Felder — ersetzen die
 * E-Rezept-Platzhalteradresse (s. docs/INBETRIEBNAHME.md §2/§3). Alle nullable: Bestand bleibt gültig.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['residents', 'physicians'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('strasse')->nullable();
                $t->string('hausnummer', 20)->nullable();
                $t->string('plz', 10)->nullable();
                $t->string('ort')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['residents', 'physicians'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['strasse', 'hausnummer', 'plz', 'ort']);
            });
        }
    }
};
