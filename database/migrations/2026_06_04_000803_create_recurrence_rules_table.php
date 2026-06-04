<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrence_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('freq');            // daily|weekly|monthly
            $table->unsignedSmallInteger('intervall')->default(1);
            $table->json('byday')->nullable(); // ISO-Wochentage [1..7] bei weekly; Monatstag [1..31] bei monthly
            $table->date('until')->nullable(); // exklusives Enddatum; null = unbegrenzt
            $table->unsignedSmallInteger('count')->nullable(); // alternativ: max. Anzahl Vorkommen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrence_rules');
    }
};
