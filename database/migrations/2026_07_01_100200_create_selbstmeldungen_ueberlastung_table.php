<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selbstmeldungen_ueberlastung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('wert');
            $table->string('notiz')->nullable();
            $table->date('gemeldet_am');
            $table->foreignId('quittiert_von')->nullable()->constrained('users')->nullOnDelete();
            $table->date('quittiert_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selbstmeldungen_ueberlastung');
    }
};
