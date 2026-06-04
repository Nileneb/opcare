<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sis_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('created_by');
            $table->foreignId('superseded_by')->nullable()->constrained('sis_assessments')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->date('erstellt_am');
            $table->string('status')->default('entwurf'); // entwurf/aktiv/abgelöst
            $table->text('eingangsfrage')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sis_assessments');
    }
};
