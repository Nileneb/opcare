<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('created_by');
            $table->foreignId('superseded_by')->nullable()->constrained('care_reports')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->timestamp('datum');
            $table->string('schicht'); // frueh/spaet/nacht
            $table->text('text');
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_reports');
    }
};
