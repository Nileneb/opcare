<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beauftragten-Register: Katalog der Pflicht-/„befähigte-Person"-Rollen (Hygiene, Brandschutz, Datenschutz,
 * Elektro/Leiter …) und die Bestellungen (benannte Person + Frist), mit Fälligkeits-Ampel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beauftragten_rollen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('rechtsbasis')->nullable();
            $table->boolean('pflicht')->default(true);
            $table->string('schwelle')->nullable();         // z. B. "ab 20 MA", "ab 1 Betrieb"
            $table->string('bereich');                      // pflege|kueche|technik|verwaltung|alle
            $table->unsignedSmallInteger('auffrischung_monate')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('beauftragten_bestellungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beauftragten_rolle_id')->constrained('beauftragten_rollen')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->date('bestellt_am');
            $table->date('gueltig_bis')->nullable();
            $table->string('notiz')->nullable();
            $table->date('abbestellt_am')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'beauftragten_rolle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beauftragten_bestellungen');
        Schema::dropIfExists('beauftragten_rollen');
    }
};
