<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administration_id')->nullable()->constrained('medication_administrations')->nullOnDelete();
            $table->string('typ');
            $table->decimal('wert', 8, 2);
            $table->decimal('wert2', 8, 2)->nullable();
            $table->string('einheit');
            $table->timestamp('gemessen_am');
            $table->unsignedBigInteger('gemessen_von')->nullable();
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id', 'typ', 'gemessen_am']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_readings');
    }
};
