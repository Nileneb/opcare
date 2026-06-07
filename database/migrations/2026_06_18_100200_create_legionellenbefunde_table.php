<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legionellen-Befunde je Anlage/Probenahmestelle (Anlage 3 Teil II TrinkwV 2023, Maßnahmenwert 100 KbE/100 ml).
 * gesundheitsamt_gemeldet_am = Anzeige-Nachweis nach § 51 TrinkwV 2023 bei Überschreitung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legionellenbefunde', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trinkwasseranlage_id')->constrained('trinkwasseranlagen')->cascadeOnDelete();
            $table->foreignId('probenahmestelle_id')->nullable()->constrained('probenahmestellen')->nullOnDelete();
            $table->date('untersucht_am');
            $table->string('labor')->nullable();
            $table->unsignedInteger('kbe_pro_100ml');
            $table->boolean('ueberschreitung')->default(false);
            $table->text('massnahme')->nullable();
            $table->date('gesundheitsamt_gemeldet_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legionellenbefunde');
    }
};
