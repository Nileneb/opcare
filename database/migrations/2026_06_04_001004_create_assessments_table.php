<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained();
            $table->integer('score')->nullable();
            $table->string('risk_band')->nullable();
            $table->date('durchgefuehrt_am');
            $table->date('faellig_am')->nullable();
            $table->text('notiz')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('superseded_by')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('status')->default('aktiv');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id', 'instrument_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
