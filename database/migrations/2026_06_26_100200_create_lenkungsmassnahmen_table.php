<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lenkungsmassnahmen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lebensmittel_gefahr_id')->constrained('lebensmittel_gefahren')->cascadeOnDelete();
            $table->string('art');
            $table->text('beschreibung');
            $table->string('verantwortlich')->nullable();
            $table->date('frist')->nullable();
            $table->date('umgesetzt_am')->nullable();
            $table->date('verifiziert_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lenkungsmassnahmen');
    }
};
