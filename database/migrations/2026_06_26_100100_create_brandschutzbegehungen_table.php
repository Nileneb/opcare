<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brandschutzbegehungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('bereich');
            $table->date('begangen_am');
            $table->foreignId('begangen_von')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('intervall_monate')->default(12);
            $table->text('bemerkung')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brandschutzbegehungen');
    }
};
