<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('med_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trade_form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('wirkstoff')->nullable();
            $table->string('staerke')->nullable();
            $table->string('atc_code')->nullable();
            $table->string('pzn')->nullable();
            $table->boolean('btm')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_products');
    }
};
