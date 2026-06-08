<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reinigungsaufgaben', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->string('bereich')->nullable();
            $table->string('intervall');
            $table->string('verantwortlich')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->date('letzte_erledigung_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reinigungsaufgaben');
    }
};
