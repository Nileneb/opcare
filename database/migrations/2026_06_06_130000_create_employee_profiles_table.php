<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personalstammakte (Personalfragebogen-Felder) 1:1 zum App-Benutzer. Sensible Felder (Steuer-ID,
 * SV-Nummer, IBAN) werden im Model per `encrypted`-Cast at rest verschlüsselt (Track B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Persönliche Angaben
            $table->string('personalnummer')->nullable();
            $table->string('anrede')->nullable();
            $table->string('vorname')->nullable();
            $table->string('nachname')->nullable();
            $table->string('geburtsname')->nullable();
            $table->date('geburtsdatum')->nullable();
            $table->string('geburtsort')->nullable();
            $table->string('staatsangehoerigkeit')->nullable();
            $table->string('geschlecht')->nullable();
            $table->string('familienstand')->nullable();
            $table->string('strasse')->nullable();
            $table->string('hausnummer', 20)->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('ort')->nullable();
            $table->string('telefon')->nullable();
            $table->boolean('schwerbehinderung')->default(false);
            $table->unsignedTinyInteger('grad_behinderung')->nullable();

            // Steuer (ELStAM) — steuer_id verschlüsselt
            $table->text('steuer_id')->nullable();
            $table->string('steuerklasse')->nullable();
            $table->string('konfession')->nullable();
            $table->decimal('kinderfreibetraege', 3, 1)->nullable();

            // Sozialversicherung — sv_nummer verschlüsselt
            $table->text('sv_nummer')->nullable();
            $table->string('krankenkasse')->nullable();
            $table->string('krankenversicherung')->nullable();

            // Bank — iban verschlüsselt
            $table->text('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('kontoinhaber')->nullable();

            // Beschäftigung / Vertrag
            $table->date('eintritt_am')->nullable();
            $table->date('austritt_am')->nullable();
            $table->date('befristet_bis')->nullable();
            $table->date('probezeit_bis')->nullable();
            $table->string('beschaeftigungsart')->nullable();
            $table->decimal('wochenstunden', 4, 1)->nullable();
            $table->string('position')->nullable();
            $table->unsignedSmallInteger('urlaubsanspruch')->nullable();

            // Pflege-Compliance
            $table->string('qualifikation')->nullable();
            $table->string('berufsurkunde_nr')->nullable();
            $table->date('fuehrungszeugnis_am')->nullable();
            $table->string('masernschutz')->nullable();
            $table->string('notfallkontakt_name')->nullable();
            $table->string('notfallkontakt_telefon')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
