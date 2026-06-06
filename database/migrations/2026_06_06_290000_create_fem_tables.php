<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freiheitsentziehende Maßnahmen (FEM, § 1831 BGB): Fall mit Anlass, geprüften milderen Mitteln, ärztlicher
 * Anordnung, Genehmigungsstatus + Befristung; dazu ein laufendes Überwachungs-/Beendigungs-Protokoll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fem_faelle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->string('art');
            $table->string('detail')->nullable();
            $table->string('anlass');
            $table->json('mildere_mittel')->nullable();           // geprüfte Alternativen
            $table->string('mildere_begruendung')->nullable();    // warum nicht ausreichend
            $table->foreignId('anordnung_pflegekraft')->nullable()->constrained('users');
            $table->string('anordnung_arzt')->nullable();
            $table->timestamp('anordnung_am')->nullable();
            $table->string('einwilligungsstatus');
            $table->date('antrag_am')->nullable();
            $table->string('aktenzeichen')->nullable();
            $table->string('gericht')->nullable();
            $table->date('beschluss_am')->nullable();
            $table->date('gueltig_bis')->nullable();              // Befristung (FamFG § 329)
            $table->string('ueberpruefung_intervall')->default('taeglich');
            $table->timestamp('beendet_am')->nullable();
            $table->string('beendigungsgrund')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });

        Schema::create('fem_protokolle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fem_fall_id')->constrained('fem_faelle')->cascadeOnDelete();
            $table->timestamp('zeitpunkt');
            $table->string('typ');                                // kontrolle | vitalzeichen | beendigung | sonstiges
            $table->string('befund')->nullable();
            $table->boolean('indikation_gegeben')->nullable();
            $table->foreignId('dokumentiert_von')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['tenant_id', 'fem_fall_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fem_protokolle');
        Schema::dropIfExists('fem_faelle');
    }
};
