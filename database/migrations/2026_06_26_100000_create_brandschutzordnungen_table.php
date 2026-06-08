<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brandschutzordnungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('titel');
            $table->string('teil');
            $table->string('version');
            $table->text('inhalt')->nullable();
            $table->foreignId('freigegeben_von')->nullable()->constrained('users')->nullOnDelete();
            $table->date('freigegeben_am')->nullable();
            $table->integer('revision_intervall_monate')->default(24);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brandschutzordnungen');
    }
};
