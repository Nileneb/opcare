<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schichtabgaenge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bewegung_id')->constrained('lagerbewegungen')->cascadeOnDelete();
            $table->foreignId('schicht_id')->constrained('lagerschichten')->cascadeOnDelete();
            $table->decimal('menge', 12, 2);
            $table->decimal('einstandspreis', 12, 4);
            $table->timestamps();
            $table->index(['bewegung_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schichtabgaenge');
    }
};
