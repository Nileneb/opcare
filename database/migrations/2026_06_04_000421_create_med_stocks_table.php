<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('med_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('med_inventory_id')->constrained()->cascadeOnDelete();
            $table->decimal('menge_initial', 10, 3);
            $table->decimal('menge_aktuell', 10, 3);
            $table->string('einheit');
            $table->string('charge')->nullable();
            $table->date('eingang_am');
            $table->date('geoeffnet_am')->nullable();
            $table->date('verfall_am')->nullable();
            $table->string('status')->default('vorraetig');
            $table->timestamps();
            $table->index('med_inventory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_stocks');
    }
};
