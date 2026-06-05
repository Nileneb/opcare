<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('substanz');
            // FHIR AllergyIntolerance: type allergy/intolerance, category medication/food/environment/biologic,
            // criticality low/high/unable-to-assess — als deutsche Schlüssel, Mapping im FHIR-Mapper.
            $table->string('typ')->default('allergie');
            $table->string('kategorie')->nullable();
            $table->string('kritikalitaet')->nullable();
            $table->string('reaktion')->nullable();
            $table->date('erfasst_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_allergies');
    }
};
