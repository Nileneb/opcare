<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reinigungsnachweise', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('reinigungsaufgabe_id')->constrained('reinigungsaufgaben')->cascadeOnDelete();
            $table->date('erledigt_am');
            $table->foreignId('erledigt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->text('bemerkung')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reinigungsnachweise');
    }
};
