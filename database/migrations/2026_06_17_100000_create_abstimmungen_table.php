<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abstimmungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->string('elektorat');
            $table->foreignId('gremium_id')->nullable()->constrained('gremien')->nullOnDelete();
            $table->string('modus');
            $table->string('art');
            $table->boolean('mehrfachauswahl')->default(false);
            $table->timestamp('start_am')->nullable();
            $table->timestamp('ende_am')->nullable();
            $table->string('status')->default('entwurf');
            $table->boolean('ergebnis_sichtbar')->default(false);
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abstimmungen');
    }
};
