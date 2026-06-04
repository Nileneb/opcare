<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sis_topic_field_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sis_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('themenfeld');
            $table->text('freitext')->nullable();
            $table->jsonb('strukturdaten')->nullable();
            $table->timestamps();
            $table->unique(['sis_assessment_id', 'themenfeld']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sis_topic_field_entries');
    }
};
