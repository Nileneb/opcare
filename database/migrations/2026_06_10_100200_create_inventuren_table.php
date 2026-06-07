<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventuren', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('abteilung')->nullable();
            $table->date('stichtag');
            $table->string('status')->default('offen');
            $table->decimal('bestandswert_summe', 14, 2)->nullable();
            $table->foreignId('differenz_buchung_id')->nullable()->constrained('buchungen')->nullOnDelete();
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('abgeschlossen_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('abgeschlossen_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventuren');
    }
};
