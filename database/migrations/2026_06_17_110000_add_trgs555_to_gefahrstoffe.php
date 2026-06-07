<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gefahrstoffe', function (Blueprint $table) {
            $table->text('schutzmassnahmen')->nullable()->after('betriebsanweisung');
            $table->text('stoerfall_massnahmen')->nullable()->after('schutzmassnahmen');
            $table->text('erste_hilfe')->nullable()->after('stoerfall_massnahmen');
            $table->text('entsorgung')->nullable()->after('erste_hilfe');
            // WHY(§ 14 Abs. 2 GefStoffV): Unterweisung mindestens jährlich — Standardintervall 12 Monate, abweichend konfigurierbar.
            $table->unsignedSmallInteger('unterweisung_intervall_monate')->default(12)->after('entsorgung');
        });
    }

    public function down(): void
    {
        Schema::table('gefahrstoffe', function (Blueprint $table) {
            $table->dropColumn([
                'schutzmassnahmen',
                'stoerfall_massnahmen',
                'erste_hilfe',
                'entsorgung',
                'unterweisung_intervall_monate',
            ]);
        });
    }
};
