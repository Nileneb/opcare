<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qdvs_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('stichtag');
            $table->string('spec');
            $table->string('status')->default('entwurf'); // entwurf/validiert/exportiert/fehler
            $table->unsignedInteger('bewohner_count')->default(0);
            $table->string('pfad')->nullable();
            $table->jsonb('fehler')->nullable();           // ValidationIssue[]
            $table->unsignedBigInteger('erstellt_von')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'stichtag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qdvs_exports');
    }
};
