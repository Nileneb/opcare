<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konversation_teilnehmer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('konversation_id')->constrained('konversationen')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('zuletzt_gelesen_am')->nullable();
            $table->boolean('darf_schreiben')->default(true);
            $table->timestamps();

            $table->unique(['konversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konversation_teilnehmer');
    }
};
