<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('belastung_freischaltungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('abstimmung_id')->constrained('abstimmungen')->cascadeOnDelete();
            $table->foreignId('freigeschaltet_von')->nullable()->constrained('users')->nullOnDelete();
            $table->date('freigeschaltet_am');
            $table->foreignId('zurueckgenommen_von')->nullable()->constrained('users')->nullOnDelete();
            $table->date('zurueckgenommen_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('belastung_freischaltungen');
    }
};
