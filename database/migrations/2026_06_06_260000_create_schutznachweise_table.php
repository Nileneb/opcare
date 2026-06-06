<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbeitsschutz-Nachweise je Mitarbeiter:in (Unterweisung, arbeitsmedizinische Vorsorge, Erste Hilfe,
 * Brandschutzhelfer, BEM) mit Datum und Intervall — der generische „Nachweis-mit-Frist"-Mechanismus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schutznachweise', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('typ');
            $table->date('datum');
            $table->unsignedSmallInteger('intervall_monate')->nullable(); // Override; sonst Typ-Default
            $table->string('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'typ']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schutznachweise');
    }
};
