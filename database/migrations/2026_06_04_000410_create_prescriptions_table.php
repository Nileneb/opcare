<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('med_product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('bhp_text')->nullable();
            $table->foreignId('physician_id')->nullable()->constrained('physicians')->nullOnDelete();
            $table->foreignId('situation_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('bei_bedarf')->default(false);
            $table->date('gueltig_von');
            $table->date('gueltig_bis')->nullable();
            $table->date('abgesetzt_am')->nullable();
            $table->unsignedBigInteger('abgesetzt_von')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->text('hinweis')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
