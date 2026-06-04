<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('superseded_by')->nullable()->constrained('care_measures')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->string('themenfeld');
            $table->text('beschreibung');
            $table->text('ziel')->nullable();
            $table->string('verantwortlich')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_measures');
    }
};
