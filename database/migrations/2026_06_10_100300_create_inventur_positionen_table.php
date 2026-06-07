<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventur_positionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventur_id')->constrained('inventuren')->cascadeOnDelete();
            $table->foreignId('artikel_id')->constrained('artikel')->cascadeOnDelete();
            $table->decimal('soll_menge', 12, 2);
            $table->decimal('ist_menge', 12, 2)->nullable();
            $table->decimal('einstandspreis_schnitt', 12, 4)->default(0);
            $table->foreignId('gezaehlt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gezaehlt_am')->nullable();
            $table->timestamps();
            $table->unique(['inventur_id', 'artikel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventur_positionen');
    }
};
