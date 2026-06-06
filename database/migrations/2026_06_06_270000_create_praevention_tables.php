<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prävention in der stationären Pflege (§ 5 SGB XI, von der Pflegekasse mitfinanziert): Programme je
 * Handlungsfeld + dokumentierte Teilnahmen je Bewohner (Grundlage für den Verwendungsnachweis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('praeventionsprogramme', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('handlungsfeld');
            $table->string('titel');
            $table->string('frequenz')->nullable();   // z. B. „wöchentlich"
            $table->string('verantwortlich')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'handlungsfeld']);
        });

        Schema::create('praeventionsteilnahmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('praeventionsprogramm_id')->constrained('praeventionsprogramme')->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->date('datum');
            $table->unsignedSmallInteger('dauer_minuten')->default(30);
            $table->string('beobachtung')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'praeventionsprogramm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('praeventionsteilnahmen');
        Schema::dropIfExists('praeventionsprogramme');
    }
};
