<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('belastungs_konfigs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('gewicht_pflegelast')->default(40);
            $table->integer('gewicht_deckung')->default(35);
            $table->integer('gewicht_spitzenzeit')->default(15);
            $table->integer('gewicht_ergonomie')->default(10);
            $table->integer('schwelle_hoch')->default(60);
            $table->integer('schwelle_kritisch')->default(80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('belastungs_konfigs');
    }
};
