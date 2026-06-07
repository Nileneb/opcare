<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lieferschein_position_vorschlaege', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('analyse_id')->constrained('lieferschein_analysen')->cascadeOnDelete();
            $table->string('text');
            $table->decimal('menge', 12, 2)->nullable();
            $table->string('einheit')->nullable();
            $table->decimal('einzelpreis', 12, 2)->nullable();
            $table->string('charge_nr')->nullable();
            $table->date('mhd')->nullable();
            $table->foreignId('matched_artikel_id')->nullable()->constrained('artikel')->nullOnDelete();
            $table->foreignId('matched_bestellposition_id')->nullable()->constrained('bestellpositionen')->nullOnDelete();
            $table->json('kandidaten')->nullable();
            $table->decimal('konfidenz', 4, 3)->nullable();
            $table->string('status')->default('vorgeschlagen');
            $table->foreignId('wareneingang_bewegung_id')->nullable()->constrained('lagerbewegungen')->nullOnDelete();
            $table->foreignId('entschieden_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entschieden_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieferschein_position_vorschlaege');
    }
};
