<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcription_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('kontext');                 // Themenfeld oder 'bericht'
            $table->string('audio_ref')->nullable();   // temp; nach ASR gelöscht
            $table->string('status')->default('queued');
            $table->text('rohtranskript')->nullable();
            $table->jsonb('sis_vorschlag')->nullable();
            $table->text('fehler')->nullable();
            $table->timestamp('freigegeben_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcription_jobs');
    }
};
