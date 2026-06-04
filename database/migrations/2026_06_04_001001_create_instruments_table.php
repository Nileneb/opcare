<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('risk_type');     // RiskType-Wert (dekubitus|sturz|schmerz|...)
            $table->string('direction');     // ScaleDirection
            // [{ "band": "hoch", "min": null, "max": 12 }, ...] — Score-Schwellen je Risikostufe
            $table->json('risk_bands');
            $table->string('beschreibung')->nullable();
            $table->unsignedInteger('intervall_tage')->default(90); // Standard-Wiedervorlage
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('superseded_by')->nullable()->constrained('instruments')->nullOnDelete();
            $table->string('status')->default('aktiv');
            $table->timestamps();
            $table->index(['tenant_id', 'risk_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
