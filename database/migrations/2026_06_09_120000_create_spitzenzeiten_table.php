<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spitzenzeiten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('beginn', 5);  // HH:MM
            $table->string('ende', 5);
            $table->unsignedSmallInteger('soll_personen')->default(1);
            $table->boolean('nur_werktags')->default(false);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spitzenzeiten');
    }
};
