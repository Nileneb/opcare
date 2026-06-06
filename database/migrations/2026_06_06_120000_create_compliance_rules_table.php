<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('paragraph');
            $table->string('label');
            // Schwere des Haupt-Verstoßes (verstoss|warnung|hinweis) — editierbar je Einrichtung.
            $table->string('severity');
            // editierbare Schwellwerte, z. B. {"max_stunden": 10, "hinweis_ab_stunden": 8}
            $table->json('params');
            // Zugriff aufs Arbeitsrecht: Link + Zitat des amtlichen Gesetzestextes je Regel.
            $table->string('gesetz_url');
            $table->text('gesetz_zitat');
            $table->text('note')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_rules');
    }
};
