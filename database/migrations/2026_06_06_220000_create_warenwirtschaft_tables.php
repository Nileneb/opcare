<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warenwirtschaft: Lagerartikel je Abteilung + Lagerbewegungen (Eingang/Verbrauch), die in die Buchhaltung
 * gebucht werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artikel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('einheit')->default('Stück');
            $table->string('abteilung');
            $table->decimal('bestand', 12, 2)->default(0);
            $table->decimal('mindestbestand', 12, 2)->nullable();
            $table->decimal('einkaufspreis', 12, 2)->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'abteilung']);
        });

        Schema::create('lagerbewegungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artikel_id')->constrained('artikel')->cascadeOnDelete();
            $table->string('typ'); // eingang | verbrauch | korrektur
            $table->decimal('menge', 12, 2);
            $table->date('datum');
            $table->string('notiz')->nullable();
            $table->foreignId('buchung_id')->nullable()->constrained('buchungen')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'artikel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lagerbewegungen');
        Schema::dropIfExists('artikel');
    }
};
