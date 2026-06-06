<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soziale Betreuung (§ 43b SGB XI): Betreuungs-/Aktivierungsangebote + Teilnahme-Dokumentation je Bewohner
 * (= Nachweis der zusätzlichen Betreuung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('betreuungsangebote', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('datum');
            $table->time('beginn')->nullable();
            $table->unsignedSmallInteger('dauer_minuten')->default(30);
            $table->string('art');
            $table->string('typ')->default('gruppe');
            $table->string('titel');
            $table->string('ort')->nullable();
            $table->foreignId('leitung_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'datum']);
        });

        Schema::create('betreuungs_teilnahmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('betreuungsangebot_id')->constrained('betreuungsangebote')->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->boolean('teilgenommen')->default(true);
            $table->string('notiz')->nullable();
            $table->timestamps();
            $table->unique(['betreuungsangebot_id', 'resident_id'], 'betreuung_teilnahme_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('betreuungs_teilnahmen');
        Schema::dropIfExists('betreuungsangebote');
    }
};
