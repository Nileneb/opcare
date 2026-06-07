<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wahlteilnahmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('abstimmung_id')->constrained('abstimmungen')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->boolean('hat_abgestimmt')->default(false);
            $table->timestamps();

            // one-person-one-vote: verhindert Doppeleintrag (null-Einträge werden je DB-Standard ignoriert)
            $table->unique(['abstimmung_id', 'user_id']);
            $table->unique(['abstimmung_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wahlteilnahmen');
    }
};
