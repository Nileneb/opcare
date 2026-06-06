<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abwesenheiten (Krankmeldung/Urlaub) je Mitarbeiter:in und Dienst-Tauschanfragen (Tausch/Vertretung).
 * Eine Krankmeldung öffnet die betroffenen Dienste automatisch als Vertretungs-Anfrage in der Tauschbörse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abwesenheiten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('typ');                 // krank | urlaub | sonstiges
            $table->date('von');
            $table->date('bis');
            $table->string('notiz')->nullable();
            $table->foreignId('gemeldet_von')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'von']);
        });

        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_assignment_id')->constrained('shift_assignments')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('typ');                 // tausch | krankheit
            $table->string('status')->default('offen'); // offen | uebernommen | zurueckgezogen
            $table->foreignId('uebernommen_von')->nullable()->constrained('users');
            $table->string('grund')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
        Schema::dropIfExists('abwesenheiten');
    }
};
