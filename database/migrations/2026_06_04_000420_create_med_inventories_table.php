<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('med_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('med_product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['resident_id', 'med_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_inventories');
    }
};
