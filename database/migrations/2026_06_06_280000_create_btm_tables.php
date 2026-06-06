<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betäubungsmittel-Nachweisführung (§ 13 BtMVV): je Bewohner + Substanz ein Konto (kein Stationsvorrat in
 * Pflegeheimen, § 5c BtMVV), eine append-only Buchungsliste (Zugang/Abgang/Vernichtung/Korrektur) mit
 * laufender Nummer + Bestand, sowie der monatliche Abschluss mit Arzt-Prüfung (§ 13 Abs. 2 BtMVV).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('btm_konten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->string('substanz');
            $table->string('form')->nullable();        // Darreichungsform
            $table->string('staerke')->nullable();
            $table->string('einheit')->default('Stück'); // Stück/mg/ml
            $table->string('arzt_name');               // verantwortlicher/verschreibender Arzt
            $table->date('eroeffnet_am');
            $table->date('geschlossen_am')->nullable();
            $table->string('schliessgrund')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });

        Schema::create('btm_buchungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('btm_konto_id')->constrained('btm_konten')->cascadeOnDelete();
            $table->unsignedInteger('lfd_nr');                 // fortlaufend je Konto
            $table->string('vorgang');
            $table->date('datum');
            $table->decimal('menge', 12, 3);                   // vorzeichenbehaftet (Abgang negativ)
            $table->decimal('bestand_nach', 12, 3);
            $table->string('lieferant')->nullable();           // bei Lieferung (Apotheke)
            $table->string('empfaenger')->nullable();          // bei Transfer/Rücknahme
            $table->string('arzt_name')->nullable();           // verschreibender Arzt bei Lieferung
            $table->foreignId('durchgefuehrt_von')->nullable()->constrained('users');
            $table->string('zeuge_1')->nullable();             // Vernichtung (BtMG § 16)
            $table->string('zeuge_2')->nullable();
            $table->string('vernichtungsmethode')->nullable();
            $table->foreignId('korrigiert_buchung_id')->nullable()->constrained('btm_buchungen');
            $table->string('grund')->nullable();
            $table->timestamp('created_at')->nullable();        // append-only: kein updated_at
            $table->index(['tenant_id', 'btm_konto_id', 'lfd_nr']);
        });

        Schema::create('btm_monatsabschluesse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('btm_konto_id')->constrained('btm_konten')->cascadeOnDelete();
            $table->date('monat');                              // erster Tag des Monats
            $table->decimal('soll_bestand', 12, 3);
            $table->decimal('ist_bestand', 12, 3);
            $table->string('differenz_notiz')->nullable();
            $table->string('geprueft_von');                     // Arzt (§ 13 Abs. 2)
            $table->date('pruef_datum');
            $table->timestamp('gesperrt_am')->nullable();
            $table->timestamps();
            $table->unique(['btm_konto_id', 'monat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('btm_monatsabschluesse');
        Schema::dropIfExists('btm_buchungen');
        Schema::dropIfExists('btm_konten');
    }
};
