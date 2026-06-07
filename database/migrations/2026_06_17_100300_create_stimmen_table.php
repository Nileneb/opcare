<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stimmen', function (Blueprint $table) {
            // WHY: UUID-PK statt Auto-Increment — verhindert, dass die Einfüge-Reihenfolge
            // die n-te Teilnahme mit der n-ten Stimme verkettbar macht (DSGVO ErwG 26 / echte Anonymität).
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('abstimmung_id')->constrained('abstimmungen')->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('abstimmung_optionen')->nullOnDelete();
            // WHY: KEIN ->unique() — bei Mehrfachauswahl teilen alle Stimmen einer Abgabe denselben
            // beleg_token (Abgabe-Beleg, kein Zeilen-Schlüssel). Index für Token-Suche reicht aus.
            $table->string('beleg_token')->index();
            // WHY: waehler_*-Felder NUR bei modus=Namentlich befüllt; bei Geheim bleiben sie NULL
            // (kein Personenbezug an der Stimme → echte Anonymität, nicht nur Pseudonymisierung).
            $table->foreignId('waehler_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('waehler_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            // WHY: KEINE timestamps() — created_at wäre ein Zeitstempel an der Stimme und würde
            // Personenbezug wiederherstellen (Reihenfolge-Korrelation mit Wahlteilnahme). DSGVO ErwG 26.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stimmen');
    }
};
