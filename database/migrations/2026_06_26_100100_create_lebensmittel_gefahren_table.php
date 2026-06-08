<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lebensmittel_gefahren', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('gefahrenanalyse_id')->constrained('gefahrenanalysen')->cascadeOnDelete();
            $table->string('gefahrenart');
            $table->text('beschreibung');
            $table->unsignedTinyInteger('wahrscheinlichkeit');
            $table->unsignedTinyInteger('schwere');
            $table->boolean('ist_ccp')->default(false);
            // WHY: CCP-Gefahr verweist auf den existierenden Überwachungs-Messpunkt (HACCP-Prinzip 4);
            // nullOnDelete, damit das Löschen eines Messpunkts die Gefahrenanalyse nicht mitreißt.
            $table->foreignId('haccp_messpunkt_id')->nullable()->constrained('haccp_messpunkte')->nullOnDelete();
            $table->text('ccp_begruendung')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lebensmittel_gefahren');
    }
};
