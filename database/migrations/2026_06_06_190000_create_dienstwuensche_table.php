<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wunschdienstplan: Dienstwünsche der Mitarbeitenden (Vorschlagscharakter) — die PDL sieht sie bei der Planung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dienstwuensche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('datum');
            $table->string('typ');
            $table->string('schicht_kind')->nullable();
            $table->string('notiz')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'datum'], 'dienstwunsch_unique');
            $table->index(['tenant_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dienstwuensche');
    }
};
