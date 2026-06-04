<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qdvs_exports', function (Blueprint $table) {
            // Coverage-Report der DAS-Regel-Engine (applicable/skipped je Grund) — Sichtbarkeit statt Verschlucken
            $table->jsonb('regel_coverage')->nullable()->after('fehler');
        });
    }

    public function down(): void
    {
        Schema::table('qdvs_exports', function (Blueprint $table) {
            $table->dropColumn('regel_coverage');
        });
    }
};
