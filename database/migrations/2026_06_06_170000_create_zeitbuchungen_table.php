<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbeitszeit-Ist-Erfassung (BAG/EuGH-Erfassungspflicht): eine Buchung je Arbeitstag mit Beginn/Ende + Pause.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeitbuchungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('datum');
            $table->time('beginn');
            $table->time('ende')->nullable();
            $table->unsignedSmallInteger('pause_minuten')->default(0);
            $table->string('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeitbuchungen');
    }
};
