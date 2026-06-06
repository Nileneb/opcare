<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betriebsärztliche & sicherheitstechnische Betreuung (ASiG §§ 2/5/6, DGUV V2): Stammdaten zu Betriebsarzt
 * und Fachkraft für Arbeitssicherheit (Sifa) — intern/extern, Einsatzzeit (DGUV V2), Begehungsintervall →
 * Begehungs-Ampel (Nachweis-mit-Frist). Speist den Arbeitsschutzausschuss (§ 11 ASiG) als Gremium.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('betriebsbetreuungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('art'); // betriebsarzt|sifa
            $table->string('name');
            $table->string('firma')->nullable();
            $table->boolean('extern')->default(true);
            $table->string('telefon')->nullable();
            $table->string('email')->nullable();
            $table->date('bestellt_am')->nullable();
            $table->date('vertrag_bis')->nullable();
            $table->unsignedSmallInteger('einsatzzeit_stunden')->nullable(); // jährlich, DGUV V2
            $table->date('letzte_begehung')->nullable();
            $table->unsignedSmallInteger('begehung_intervall_monate')->nullable();
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'art']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('betriebsbetreuungen');
    }
};
