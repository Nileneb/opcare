<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_zeilen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->json('roh')->nullable();
            $table->string('ziel_typ');
            $table->string('name')->nullable();
            $table->string('einheit')->nullable();
            $table->string('abteilung')->nullable();
            $table->decimal('einkaufspreis', 12, 2)->nullable();
            $table->decimal('mindestbestand', 12, 2)->nullable();
            $table->decimal('bestand', 12, 2)->nullable();
            $table->decimal('einstandspreis', 12, 4)->nullable();
            $table->string('pg_nummer')->nullable();
            $table->string('lieferant_text')->nullable();
            $table->string('charge_nr')->nullable();
            $table->date('mhd')->nullable();
            $table->foreignId('matched_artikel_id')->nullable()->constrained('artikel')->nullOnDelete();
            $table->foreignId('matched_lieferant_id')->nullable()->constrained('lieferanten')->nullOnDelete();
            $table->json('kandidaten')->nullable();
            $table->string('aktion')->default('anlegen');
            $table->string('status')->default('vorgeschlagen');
            $table->foreignId('ergebnis_artikel_id')->nullable()->constrained('artikel')->nullOnDelete();
            $table->foreignId('ergebnis_lieferant_id')->nullable()->constrained('lieferanten')->nullOnDelete();
            $table->foreignId('wareneingang_bewegung_id')->nullable()->constrained('lagerbewegungen')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_zeilen');
    }
};
