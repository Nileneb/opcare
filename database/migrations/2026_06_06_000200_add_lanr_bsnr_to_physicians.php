<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track C (E-Rezept): Arzt-Stammdaten um die für KBV-E-Rezept-FHIR nötigen Identifikatoren erweitern —
 * LANR (Lebenslange Arztnummer, KBV_PR_FOR_Practitioner) + BSNR (Betriebsstättennummer der Praxis,
 * KBV_PR_FOR_Organization). Beide nullable: nur für die E-Rezept-Repräsentation erforderlich.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physicians', function (Blueprint $table) {
            $table->string('lanr', 9)->nullable()->after('fachrichtung');
            $table->string('bsnr', 9)->nullable()->after('lanr');
        });
    }

    public function down(): void
    {
        Schema::table('physicians', function (Blueprint $table) {
            $table->dropColumn(['lanr', 'bsnr']);
        });
    }
};
