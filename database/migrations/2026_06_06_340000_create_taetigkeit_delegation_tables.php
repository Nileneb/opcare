<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berechtigungsmatrix: Tätigkeitskatalog (Mindestqualifikation, erforderliche Zusatzkompetenz, Vorbehalt,
 * Delegationspflicht) sowie die generische Delegationsverwaltung (Pflege: Arzt→Pflege bewohnerbezogen;
 * Haustechnik: Betreiber→befähigte Person anlagenbezogen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taetigkeiten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('bereich');                       // pflege | medikation | haustechnik | kueche | verwaltung
            $table->boolean('nur_fachkraft')->default(false); // Mindestqualifikation Fachkraft
            $table->boolean('vorbehaltsaufgabe')->default(false); // § 4 PflBG
            $table->foreignId('erforderliche_kompetenz_id')->nullable()->constrained('kompetenzen')->nullOnDelete();
            $table->boolean('arzt_anordnung_noetig')->default(false); // erfordert gültige Delegation/Anordnung
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('delegationen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('taetigkeit_id')->constrained('taetigkeiten')->cascadeOnDelete();
            $table->foreignId('nehmer_id')->constrained('users');       // durchführende Person
            $table->string('anordner_name');                            // Arzt / Betreiber (oft extern)
            $table->nullableMorphs('bezug');                            // Bewohner ODER Anlage/Gerät (optional)
            $table->date('delegiert_am');
            $table->date('gueltig_bis')->nullable();
            $table->string('nachweis_notiz')->nullable();
            $table->timestamp('widerruf_am')->nullable();
            $table->string('widerruf_grund')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'nehmer_id', 'taetigkeit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegationen');
        Schema::dropIfExists('taetigkeiten');
    }
};
