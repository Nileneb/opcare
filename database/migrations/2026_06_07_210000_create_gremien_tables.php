<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gremien & Mitwirkung: Heimbeirat/Bewohnervertretung (HeimmwV, § 10 WBVG, Landes-WTG), Angehörigenbeirat,
 * Qualitätszirkel (§ 113 SGB XI) und Arbeitsschutzausschuss (§ 11 ASiG). Wahlperiode → Neuwahl-Ampel,
 * Sitzungsintervall → Sitzungs-Ampel (ASA mind. vierteljährlich). Mitglieder + Sitzungsprotokolle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gremien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('typ'); // heimbeirat|angehoerigenbeirat|bewohnervertretung|qualitaetszirkel|arbeitsschutzausschuss|sonstiges
            $table->string('name');
            $table->text('beschreibung')->nullable();
            $table->date('gewaehlt_am')->nullable();
            $table->unsignedSmallInteger('periode_monate')->nullable(); // Wahlperiode/Amtszeit
            $table->unsignedSmallInteger('sitzung_intervall_monate')->nullable(); // Soll-Sitzungstakt
            $table->date('aufgeloest_am')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'typ']);
        });

        Schema::create('gremium_mitglieder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gremium_id')->constrained('gremien')->cascadeOnDelete();
            $table->string('name');
            $table->string('art')->default('mitglied'); // bewohner|angehoerige|mitarbeiter|leitung|extern|betriebsarzt|sifa
            $table->string('funktion')->default('mitglied'); // vorsitz|stellvertretung|schriftfuehrung|mitglied
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->date('bis')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'gremium_id']);
        });

        Schema::create('gremium_sitzungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gremium_id')->constrained('gremien')->cascadeOnDelete();
            $table->date('datum');
            $table->string('thema');
            $table->text('protokoll')->nullable();
            $table->text('beschluesse')->nullable();
            $table->unsignedSmallInteger('teilnehmerzahl')->nullable();
            $table->foreignId('protokoll_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'gremium_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gremium_sitzungen');
        Schema::dropIfExists('gremium_mitglieder');
        Schema::dropIfExists('gremien');
    }
};
