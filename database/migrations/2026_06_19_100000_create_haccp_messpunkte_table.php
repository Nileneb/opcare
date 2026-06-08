<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HACCP-Messpunkte (kritische Kontrollpunkte) je Mandant.
 * Norm-Anker: VO (EG) 852/2004 Art. 5, LMHV §§ 3/4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haccp_messpunkte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->string('art');
            $table->decimal('grenzwert', 5, 1);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'aktiv']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haccp_messpunkte');
    }
};
