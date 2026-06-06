<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beschwerde- und Gewaltschutz-Management (§ 113 SGB XI QM-Maßstäbe, Landes-WTG-Beschwerderecht,
 * Gewaltprävention § 5 SGB XI). Eine Beschwerde/Anregung/Meldung wird erfasst, vom QM bearbeitet und
 * kann an die betroffene Abteilung weitergeleitet werden — anonym oder namentlich, je nach Wahl des
 * Melders (melder_sichtbarkeit). Jeder Vorgang (Weiterleitung/Stellungnahme/Maßnahme) ist protokolliert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beschwerden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('titel');
            $table->text('beschreibung');
            $table->string('kategorie')->default('beschwerde'); // anregung|beschwerde|lob|gewaltvorfall
            $table->string('bereich')->default('leitung');      // adressierte Abteilung (Routing)
            $table->string('quelle')->default('bewohner');      // bewohner|angehoerige|mitarbeiter|extern
            $table->string('melder_sichtbarkeit')->default('namentlich'); // anonym|namentlich — Melder wählt
            $table->foreignId('melder_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('melder_name')->nullable();
            $table->foreignId('betroffener_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->date('eingang_am');
            $table->date('frist')->nullable();
            $table->string('status')->default('eingegangen'); // eingegangen|in_bearbeitung|weitergeleitet|erledigt|abgelehnt
            $table->foreignId('bearbeiter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('schweregrad')->nullable(); // bei Gewaltvorfall: niedrig|mittel|hoch
            $table->text('sofortmassnahme')->nullable(); // Gewaltschutz: Sofortmaßnahme
            $table->date('erledigt_am')->nullable();
            $table->text('ergebnis')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'bereich']);
        });

        Schema::create('beschwerde_vorgaenge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beschwerde_id')->constrained('beschwerden')->cascadeOnDelete();
            $table->string('art'); // notiz|weiterleitung|statuswechsel|stellungnahme|massnahme
            $table->string('an_bereich')->nullable(); // bei Weiterleitung: Zielabteilung
            $table->boolean('anonym')->default(false); // wurde der Melder dem Empfänger verborgen?
            $table->text('text')->nullable();
            $table->foreignId('von_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'beschwerde_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beschwerde_vorgaenge');
        Schema::dropIfExists('beschwerden');
    }
};
