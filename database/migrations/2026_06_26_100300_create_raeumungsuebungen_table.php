<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raeumungsuebungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('durchgefuehrt_am');
            $table->foreignId('durchgefuehrt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('intervall_monate')->default(12);
            $table->string('bereich')->nullable();
            $table->string('szenario')->nullable();
            $table->integer('teilnehmer_anzahl')->nullable();
            $table->integer('dauer_minuten')->nullable();
            $table->text('erkenntnisse')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raeumungsuebungen');
    }
};
