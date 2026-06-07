<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wahlteilnahmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('abstimmung_id')->constrained('abstimmungen')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->boolean('hat_abgestimmt')->default(false);
            // WHY: KEINE timestamps() — updated_at würde die personenbezogene Stimmabgabe-Sekunde
            // rekonstruierbar machen (Timing-Korrelation, Spec §3 / DSGVO Art. 5(1)(c)).
            // Nur das Boolean hat_abgestimmt ist erlaubt; kein Zeitstempel, kein Kanal, kein Gerät.

            // one-person-one-vote: verhindert Doppeleintrag (null-Einträge werden je DB-Standard ignoriert)
            $table->unique(['abstimmung_id', 'user_id']);
            $table->unique(['abstimmung_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wahlteilnahmen');
    }
};
