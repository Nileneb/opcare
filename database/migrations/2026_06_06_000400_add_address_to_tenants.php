<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adress-Stammdaten der Einrichtung (Tenant). Vorbereitung der ZETA-Schicht (TI 2.0): die Institutions-
 * Identität braucht eine Postadresse (auch ISiP-Organization-Adresse, KIM-Absender). Alle nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->string('strasse')->nullable();
            $t->string('hausnummer', 20)->nullable();
            $t->string('plz', 10)->nullable();
            $t->string('ort')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropColumn(['strasse', 'hausnummer', 'plz', 'ort']);
        });
    }
};
