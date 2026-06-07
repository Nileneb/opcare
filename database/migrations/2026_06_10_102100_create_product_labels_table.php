<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('yolo_label');
            $table->unsignedBigInteger('artikel_id');
            $table->decimal('multiplier', 12, 2)->default(1.00);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('artikel_id')->references('id')->on('artikel')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_labels');
    }
};
