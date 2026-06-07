<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lieferschein_analysen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('lieferant_text')->nullable();
            $table->foreignId('lieferant_id')->nullable()->constrained('lieferanten')->nullOnDelete();
            $table->date('datum')->nullable();
            $table->string('lieferschein_nr')->nullable();
            $table->json('roh_json')->nullable();
            $table->string('modell')->nullable();
            $table->decimal('konfidenz', 4, 3)->nullable();
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieferschein_analysen');
    }
};
