<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('geburtsdatum');
            $table->string('geschlecht', 1);            // m/w/d
            $table->smallInteger('pflegegrad')->nullable();
            $table->date('aufnahme_am');
            $table->date('entlassung_am')->nullable();
            $table->string('status')->default('aktiv'); // aktiv/abwesend/entlassen
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
