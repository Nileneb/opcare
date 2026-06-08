<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // WHY(ÜLB): zwei ÜLB-Composition-Sektionen mit eigenem Ressourcentyp (kein generischer Observation-Slot):
    // krankenhausaufenthalt → Encounter_Hospital_Stay (nur period.end, start verboten) und
    // empfehlung → CarePlan_Recommendation_Receiving_Institution (Freitext in activity.detail.code.text).
    public function up(): void
    {
        Schema::create('resident_hospital_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->date('ende');                       // FHIR Encounter.period.end (period.start ist im Profil verboten)
            $table->string('grund')->nullable();        // interner Vermerk/Narrativ (FHIR reasonCode ist im Profil verboten)
            $table->timestamps();
            $table->index(['resident_id', 'ende']);
        });

        Schema::create('resident_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->text('empfehlung');                 // FHIR CarePlan.activity.detail.code.text
            $table->date('erstellt_am')->nullable();
            $table->timestamps();
            $table->index('resident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_recommendations');
        Schema::dropIfExists('resident_hospital_stays');
    }
};
