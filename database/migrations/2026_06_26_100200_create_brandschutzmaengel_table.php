<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brandschutzmaengel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('brandschutzbegehung_id')->constrained('brandschutzbegehungen')->cascadeOnDelete();
            $table->text('beschreibung');
            $table->string('schwere');
            $table->date('frist')->nullable();
            $table->date('behoben_am')->nullable();
            $table->string('behoben_notiz')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brandschutzmaengel');
    }
};
