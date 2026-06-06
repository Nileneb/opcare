<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// WHY(§ 1821 BGB): wesentliche Bewohner-Ereignisse, bei denen die Vertretung ein Beteiligungs-/Informationsrecht
// hat. status/informiert_am dokumentiert die Pflichterfüllung des Trägers (Melde-Workflow-mit-Frist-Muster).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bewohner_ereignisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('kategorie');
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->date('datum');
            $table->string('status')->default('offen'); // offen | informiert | erledigt
            $table->date('informiert_am')->nullable();
            $table->foreignId('erstellt_von_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['resident_id', 'datum']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bewohner_ereignisse');
    }
};
