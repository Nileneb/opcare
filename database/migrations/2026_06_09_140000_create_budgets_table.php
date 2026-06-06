<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('konto_id')->constrained('konten')->cascadeOnDelete();
            $table->decimal('limit_betrag', 12, 2);
            $table->unsignedTinyInteger('warn_prozent')->default(80);
            $table->boolean('sperre')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'konto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
