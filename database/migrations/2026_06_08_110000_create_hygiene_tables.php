<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hygieneplaene', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('titel');
            $table->string('version')->default('1.0');
            $table->text('inhalt')->nullable();
            $table->foreignId('freigegeben_von')->nullable()->constrained('users')->nullOnDelete();
            $table->date('freigegeben_am')->nullable();
            $table->unsignedSmallInteger('revision_intervall_monate')->default(12);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('infektions_befunde', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->string('erreger');
            $table->string('art');
            $table->date('festgestellt_am');
            $table->date('aufgehoben_am')->nullable();
            $table->text('massnahmen')->nullable();
            $table->boolean('meldepflichtig')->default(false);
            $table->date('gemeldet_am')->nullable();
            $table->foreignId('erfasst_von_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['resident_id', 'festgestellt_am']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infektions_befunde');
        Schema::dropIfExists('hygieneplaene');
    }
};
