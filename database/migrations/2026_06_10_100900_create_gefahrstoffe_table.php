<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gefahrstoffe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('artikel_id')->unique()->constrained('artikel')->cascadeOnDelete();
            $table->string('signalwort')->nullable();
            $table->json('h_saetze')->nullable();
            $table->json('p_saetze')->nullable();
            $table->json('ghs_piktogramme')->nullable();
            $table->string('mengenbereich')->nullable();
            $table->text('arbeitsbereiche')->nullable();
            $table->string('lagerort')->nullable();
            $table->text('betriebsanweisung')->nullable();
            $table->date('sdb_version_datum')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gefahrstoffe');
    }
};
