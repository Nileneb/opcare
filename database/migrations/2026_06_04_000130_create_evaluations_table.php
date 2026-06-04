<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('evaluable_type');
            $table->unsignedBigInteger('evaluable_id');
            $table->unsignedBigInteger('created_by');
            $table->foreignId('superseded_by')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->date('datum');
            $table->string('zielerreichung'); // erreicht/teilweise/nicht
            $table->string('anlass')->nullable();
            $table->timestamps();
            $table->index(['evaluable_type', 'evaluable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
