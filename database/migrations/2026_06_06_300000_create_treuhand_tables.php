<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barbetrags-/Taschengeldverwaltung (§ 27b SGB XII). Das Heim verwaltet das Bargeld des Bewohners
 * treuhänderisch, getrennt vom Einrichtungsvermögen und „für Rechnung des einzelnen Bewohners"
 * (HeimsicherungsV § 8). Pro Bewohner ein Treuhandkonto mit append-only Einzelbuchungen (Einzelbeleg-
 * pflicht + prüfungsfähige Aufzeichnung je Bewohner, HeimsicherungsV § 17), Budget-Setzungen je Kategorie
 * mit Warn-/Sperr-Ampel und monatlicher Rechnungslegung (HeimsicherungsV § 15) für die Heimaufsicht/das
 * Betreuungsgericht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treuhand_konten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->string('iban')->nullable();          // Sonderkonto bei einem Kreditinstitut (HeimsicherungsV § 8)
            $table->date('eroeffnet_am');
            $table->date('geschlossen_am')->nullable();
            $table->string('schliessgrund')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'resident_id']); // genau ein Treuhandkonto je Bewohner
        });

        Schema::create('treuhand_buchungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treuhand_konto_id')->constrained('treuhand_konten')->cascadeOnDelete();
            $table->unsignedInteger('lfd_nr');                 // fortlaufend je Konto
            $table->string('vorgang');                         // einzahlung/auszahlung/korrektur
            $table->date('datum');
            $table->decimal('betrag', 10, 2);                  // vorzeichenbehaftet (Auszahlung negativ)
            $table->decimal('saldo_nach', 10, 2);
            $table->string('kategorie')->nullable();           // Friseur/Kleidung/… — Budget-Zuordnung
            $table->string('zweck');                           // Verwendungszweck (Pflichttext)
            $table->string('beleg_nr')->nullable();            // Einzelbeleg-Referenz (HeimsicherungsV § 17)
            $table->foreignId('erfasst_von')->nullable()->constrained('users');
            $table->foreignId('korrigiert_buchung_id')->nullable()->constrained('treuhand_buchungen');
            $table->string('grund')->nullable();               // bei Korrektur Pflicht
            $table->timestamp('created_at')->nullable();       // append-only: kein updated_at
            $table->index(['tenant_id', 'treuhand_konto_id', 'lfd_nr']);
        });

        Schema::create('treuhand_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treuhand_konto_id')->constrained('treuhand_konten')->cascadeOnDelete();
            $table->string('kategorie')->nullable();           // null = Gesamtbudget über alle Kategorien
            $table->decimal('limit_betrag', 10, 2);
            $table->unsignedTinyInteger('warn_prozent')->default(80);
            $table->boolean('sperre')->default(false);         // true = Auszahlung über Limit wird blockiert
            $table->timestamps();
            $table->unique(['treuhand_konto_id', 'kategorie']);
        });

        Schema::create('treuhand_monatsabschluesse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treuhand_konto_id')->constrained('treuhand_konten')->cascadeOnDelete();
            $table->date('monat');                             // erster Tag des Monats
            $table->decimal('anfangsbestand', 10, 2);
            $table->decimal('summe_einzahlungen', 10, 2);
            $table->decimal('summe_auszahlungen', 10, 2);
            $table->decimal('endbestand', 10, 2);
            $table->string('erstellt_von');
            $table->timestamp('gesperrt_am')->nullable();
            $table->timestamps();
            $table->unique(['treuhand_konto_id', 'monat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treuhand_monatsabschluesse');
        Schema::dropIfExists('treuhand_budgets');
        Schema::dropIfExists('treuhand_buchungen');
        Schema::dropIfExists('treuhand_konten');
    }
};
