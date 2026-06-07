<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lieferant_artikel_aliasse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lieferant_id')->nullable()->constrained('lieferanten')->nullOnDelete();
            $table->string('norm_text');
            $table->foreignId('artikel_id')->constrained('artikel')->cascadeOnDelete();
            $table->unsignedInteger('treffer')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'lieferant_id', 'norm_text', 'artikel_id'], 'laa_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieferant_artikel_aliasse');
    }
};
