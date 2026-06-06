<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beleg_analysen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('modell');
            $table->float('konfidenz')->nullable();
            $table->json('roh_json')->nullable();
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('einsortierungs_vorschlaege', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beleg_analyse_id')->constrained('beleg_analysen')->cascadeOnDelete();
            $table->string('ziel_typ');
            $table->json('ziel_felder');
            $table->string('status')->default('vorgeschlagen');
            $table->float('konfidenz')->nullable();
            // Gesetzt, sobald die Bestätigung einen Zieldatensatz geschrieben hat (hier: eine Buchung).
            $table->foreignId('buchung_id')->nullable()->constrained('buchungen')->nullOnDelete();
            $table->foreignId('entschieden_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entschieden_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einsortierungs_vorschlaege');
        Schema::dropIfExists('beleg_analysen');
    }
};
