<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-Dienstplan: Soll-Besetzung je Schicht (wie viele Mitarbeitende pro Tag) und ein Flag, das automatisch
 * erzeugte Zuweisungen als Vorschlag markiert — die PDL gibt sie frei oder verwirft sie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('soll_besetzung')->default(1)->after('ende');
        });
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->boolean('auto_generiert')->default(false)->after('notiz');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('soll_besetzung');
        });
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->dropColumn('auto_generiert');
        });
    }
};
