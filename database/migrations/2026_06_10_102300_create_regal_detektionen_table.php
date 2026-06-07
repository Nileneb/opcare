<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regal_detektionen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('aufnahme_id');
            $table->string('label');
            $table->decimal('confidence', 8, 4);
            $table->unsignedBigInteger('artikel_id')->nullable();
            $table->decimal('menge_vorschlag', 12, 2)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('aufnahme_id')->references('id')->on('regal_aufnahmen')->cascadeOnDelete();
            $table->foreign('artikel_id')->references('id')->on('artikel')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regal_detektionen');
    }
};
