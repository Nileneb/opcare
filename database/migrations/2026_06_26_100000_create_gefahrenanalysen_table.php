<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gefahrenanalysen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('prozessschritt');
            $table->string('bereich')->nullable();
            $table->text('beschreibung')->nullable();
            $table->date('erstellt_am');
            $table->unsignedInteger('verifizierungsintervall_monate')->default(12);
            $table->date('letzte_verifizierung_am')->nullable();
            $table->string('verantwortlich')->nullable();
            $table->date('freigegeben_am')->nullable();
            $table->string('status')->default('entwurf');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gefahrenanalysen');
    }
};
