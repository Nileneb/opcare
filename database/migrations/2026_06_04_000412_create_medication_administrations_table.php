<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('soll_zeitpunkt');
            $table->string('tageszeit');
            $table->decimal('dosis', 8, 3);
            $table->string('status')->default('geplant');
            $table->timestamp('ist_zeitpunkt')->nullable();
            $table->unsignedBigInteger('quittiert_von')->nullable();
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id', 'soll_zeitpunkt']);
            $table->index(['prescription_schedule_id', 'soll_zeitpunkt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};
