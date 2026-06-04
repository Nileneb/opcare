<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('med_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('med_stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administration_id')->nullable()->constrained('medication_administrations')->nullOnDelete();
            $table->string('typ');
            $table->decimal('menge', 10, 3);
            $table->timestamp('gebucht_am');
            $table->unsignedBigInteger('gebucht_von')->nullable();
            $table->timestamps();
            $table->index(['med_stock_id', 'gebucht_am']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_stock_transactions');
    }
};
