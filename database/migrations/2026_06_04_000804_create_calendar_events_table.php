<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->dateTime('beginnt_am');
            $table->dateTime('endet_am')->nullable();
            $table->boolean('ganztaegig')->default(false);
            $table->foreignId('recurrence_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('abgesagt_am')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['tenant_id', 'beginnt_am']);
            $table->index(['tenant_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
