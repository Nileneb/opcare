<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bestellpositionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('bestellung_id')->constrained('bestellungen')->cascadeOnDelete();
            $table->foreignId('artikel_id')->constrained('artikel')->cascadeOnDelete();
            $table->decimal('menge_bestellt', 12, 2);
            $table->decimal('menge_geliefert', 12, 2)->default(0);
            $table->decimal('einzelpreis', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestellpositionen');
    }
};
