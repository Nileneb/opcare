<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fortbildungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('thema');
            $table->string('titel');
            $table->string('anbieter')->nullable();
            $table->date('geplant_am')->nullable();
            $table->date('absolviert_am')->nullable();
            $table->unsignedSmallInteger('umfang_stunden')->nullable();
            $table->boolean('pflicht')->default(false);
            $table->unsignedSmallInteger('intervall_monate')->nullable();
            $table->string('notiz')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'thema']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fortbildungen');
    }
};
