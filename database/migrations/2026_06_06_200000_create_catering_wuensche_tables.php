<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Essenswünsche (allgemeine Vorlieben/Abneigungen je Bewohner, die Küche sieht sie jederzeit) + Menüwahl
 * (Bewohner legt fest, welches angebotene Gericht er möchte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('essenswuensche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->string('art');
            $table->string('text');
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });

        Schema::create('menuewahlen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('gericht_id')->constrained('catering_gerichte')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['resident_id', 'gericht_id'], 'menuewahl_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menuewahlen');
        Schema::dropIfExists('essenswuensche');
    }
};
