<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('indicator');
            $table->date('datum');
            $table->date('behoben_am')->nullable();
            $table->string('severity')->nullable();
            $table->jsonb('details')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'indicator', 'datum']);
            $table->index(['resident_id', 'indicator']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_events');
    }
};
