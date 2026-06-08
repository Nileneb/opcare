<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schutzmassnahmen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('gefaehrdung_id')->constrained('gefaehrdungen')->cascadeOnDelete();
            $table->string('typ');
            $table->text('beschreibung');
            $table->string('verantwortlich')->nullable();
            $table->date('frist')->nullable();
            $table->date('umgesetzt_am')->nullable();
            $table->date('wirksam_geprueft_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schutzmassnahmen');
    }
};
