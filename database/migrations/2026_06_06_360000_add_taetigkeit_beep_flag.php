<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag, ob die erforderliche Kompetenz auch von Pflegefachkräften verlangt wird. LG1/LG2-Tätigkeiten gelten
 * für Fachkräfte als inhärent beherrscht (Flag false → für Fachkräfte gewaivt); Spezialqualifikationen wie die
 * eigenständige Heilkunde nach BEEP-Gesetz (B.Sc.) gelten für alle (Flag true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taetigkeiten', function (Blueprint $table) {
            $table->boolean('kompetenz_auch_fuer_fachkraft')->default(false)->after('erforderliche_kompetenz_id');
        });
    }

    public function down(): void
    {
        Schema::table('taetigkeiten', function (Blueprint $table) {
            $table->dropColumn('kompetenz_auch_fuer_fachkraft');
        });
    }
};
