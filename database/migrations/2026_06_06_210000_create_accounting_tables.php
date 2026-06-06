<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buchhaltung (doppelte Buchführung): Konten + Buchungssätze (Soll an Haben).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nummer');
            $table->string('name');
            $table->string('typ');
            $table->timestamps();
            $table->unique(['tenant_id', 'nummer']);
        });

        Schema::create('buchungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('datum');
            $table->foreignId('soll_konto_id')->constrained('konten')->cascadeOnDelete();
            $table->foreignId('haben_konto_id')->constrained('konten')->cascadeOnDelete();
            $table->decimal('betrag', 12, 2);
            $table->string('text');
            $table->string('beleg')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buchungen');
        Schema::dropIfExists('konten');
    }
};
