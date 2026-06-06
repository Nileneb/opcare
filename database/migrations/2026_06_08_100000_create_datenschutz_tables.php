<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verarbeitungstaetigkeiten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('schluessel')->nullable();
            $table->string('name');
            $table->text('zweck');
            $table->string('rechtsgrundlage');
            $table->string('kategorien_betroffene');
            $table->string('kategorien_daten');
            $table->string('empfaenger')->nullable();
            $table->string('drittland')->nullable();
            $table->string('loeschfrist');
            $table->text('tom')->nullable();
            $table->unsignedSmallInteger('pruef_intervall_monate')->default(12);
            $table->date('geprueft_am')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'schluessel']);
        });

        Schema::create('auftragsverarbeitungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verarbeitungstaetigkeit_id')->nullable()->constrained('verarbeitungstaetigkeiten')->nullOnDelete();
            $table->string('dienstleister');
            $table->text('zweck');
            $table->string('kategorien_daten');
            $table->string('drittland')->nullable();
            $table->boolean('unterauftragnehmer')->default(false);
            $table->date('vertrag_geschlossen_am')->nullable();
            $table->unsignedSmallInteger('pruef_intervall_monate')->default(24);
            $table->date('geprueft_am')->nullable();
            $table->string('notiz')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auftragsverarbeitungen');
        Schema::dropIfExists('verarbeitungstaetigkeiten');
    }
};
