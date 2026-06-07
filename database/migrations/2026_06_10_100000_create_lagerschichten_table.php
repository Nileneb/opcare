<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lagerschichten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artikel_id')->constrained('artikel')->cascadeOnDelete();
            $table->foreignId('eingang_bewegung_id')->nullable()->constrained('lagerbewegungen')->nullOnDelete();
            $table->date('eingangsdatum');
            $table->decimal('menge_eingang', 12, 2);
            $table->decimal('menge_rest', 12, 2);
            $table->decimal('einstandspreis', 12, 4);
            $table->string('charge_nr')->nullable();
            $table->date('mhd')->nullable();
            $table->timestamps();
            $table->index(['artikel_id', 'eingangsdatum', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lagerschichten');
    }
};
