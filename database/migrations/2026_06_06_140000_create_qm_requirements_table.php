<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QM-Norm-Checkliste: einrichtungsweite Anforderungen aus QPR-Qualitätsbereichen + Abteilungs-/Querschnitts-
 * normen. Standard-Anforderungen werden idempotent über `schluessel` geseedet; Status/Nachweis/Zuständig/
 * Fälligkeit sind editierbar, eigene Anforderungen (schluessel = null) ergänzbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qm_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('bereich');
            $table->string('norm');
            $table->text('anforderung');
            $table->string('gesetz_url')->nullable();
            $table->string('status')->default('offen');
            $table->text('nachweis')->nullable();
            $table->string('zustaendig')->nullable();
            $table->date('faellig_am')->nullable();
            $table->date('geprueft_am')->nullable();
            // stabiler Schlüssel der Standard-Anforderungen (idempotentes Seeding); null = eigene Anforderung.
            $table->string('schluessel')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'schluessel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qm_requirements');
    }
};
