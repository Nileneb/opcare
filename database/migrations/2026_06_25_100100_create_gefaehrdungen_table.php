<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gefaehrdungen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('gefaehrdungsbeurteilung_id')->constrained('gefaehrdungsbeurteilungen')->cascadeOnDelete();
            $table->string('faktor');
            $table->text('beschreibung');
            $table->unsignedTinyInteger('wahrscheinlichkeit');
            $table->unsignedTinyInteger('schwere');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gefaehrdungen');
    }
};
