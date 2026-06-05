<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // WHY(ÜLB medizinprodukte): Hilfsmittel/Medizinprodukte des Bewohners (Rollator, Hörgerät, PEG, …)
    // → FHIR Device (type.text + patient). Freitext-Bezeichnung wie ÜLB-Variante Device_Other_Item.
    public function up(): void
    {
        Schema::create('resident_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->string('kategorie')->nullable(); // hilfsmittel|implantat|sonstiges
            $table->string('hinweis')->nullable();
            $table->date('seit')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_devices');
    }
};
