<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skill-Baum: Kompetenz-Katalog je Einrichtung (Grundberufe/Weiterbildungen/interne Schulungen) mit
 * Voraussetzungen (DAG) und Gültigkeit/Auffrischung, sowie die erworbenen Kompetenzen je Mitarbeiter:in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kompetenzen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('typ');                          // grundberuf | weiterbildung | interne_schulung
            $table->boolean('ist_fachkraft')->default(false); // Grundberuf, der die Fachkraft-Eigenschaft begründet
            $table->string('rechtsbasis')->nullable();
            $table->unsignedSmallInteger('umfang_stunden')->nullable();
            $table->unsignedSmallInteger('gueltigkeit_monate')->nullable();  // null = unbefristet
            $table->unsignedSmallInteger('auffrischung_monate')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('kompetenz_voraussetzungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kompetenz_id')->constrained('kompetenzen')->cascadeOnDelete();
            $table->foreignId('voraussetzung_id')->constrained('kompetenzen')->cascadeOnDelete();
            $table->unique(['kompetenz_id', 'voraussetzung_id']);
        });

        Schema::create('mitarbeiter_kompetenzen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kompetenz_id')->constrained('kompetenzen')->cascadeOnDelete();
            $table->date('erworben_am');
            $table->date('gueltig_bis')->nullable();        // berechnet aus erworben_am + gueltigkeit_monate
            $table->foreignId('verifiziert_von')->nullable()->constrained('users');
            $table->string('notiz')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'kompetenz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitarbeiter_kompetenzen');
        Schema::dropIfExists('kompetenz_voraussetzungen');
        Schema::dropIfExists('kompetenzen');
    }
};
