<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speiseplan-Gerichte mit LMIV-Allergenkennzeichnung (VO (EU) 1169/2011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catering_gerichte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('datum');
            $table->string('mahlzeit');
            $table->string('bezeichnung');
            $table->json('allergene')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catering_gerichte');
    }
};
