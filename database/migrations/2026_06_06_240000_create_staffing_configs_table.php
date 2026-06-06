<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einrichtungsspezifische Stellschrauben der Personalbemessung (§ 113c SGB XI / PeBeM). Die bundeseinheitliche
 * PAW-Tabelle (VZÄ je Pflegegrad/Qualifikation) bleibt Code-Konstante; hier nur die anpassbaren Werte —
 * Tarif-Wochenstunden, Fachkraftquote, Nachtdienst-Schlüssel (landesrechtlich) und der PAW-Multiplikator
 * (private Häuser mit mehr Personal verhandeln über § 84 Abs. 2 mehr).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staffing_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('wochenstunden', 4, 1)->default(38.5);
            $table->decimal('fachkraftquote_min', 4, 3)->default(0.500);
            $table->unsignedSmallInteger('nachtdienst_je_fachkraft')->default(50);
            $table->decimal('paw_multiplikator', 4, 2)->default(1.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staffing_configs');
    }
};
