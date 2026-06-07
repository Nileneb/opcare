<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Probenahmestellen einer Trinkwasseranlage (repräsentative Stellen gem. TrinkwV 2023).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('probenahmestellen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trinkwasseranlage_id')->constrained('trinkwasseranlagen')->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->string('ort')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('probenahmestellen');
    }
};
