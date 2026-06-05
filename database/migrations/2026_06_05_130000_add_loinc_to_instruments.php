<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // WHY(Track A/ÜLB): macht Assessment-Instrumente FHIR-adressierbar — pro Item ein LOINC-Code
    // (Barthel-Items), pro Instrument der Panel-/Summen-Code (Total_Barthel_Index 96761-2).
    public function up(): void
    {
        Schema::table('instruments', function (Blueprint $table) {
            $table->string('loinc')->nullable()->after('name');
        });
        Schema::table('instrument_items', function (Blueprint $table) {
            $table->string('loinc')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('instruments', fn (Blueprint $table) => $table->dropColumn('loinc'));
        Schema::table('instrument_items', fn (Blueprint $table) => $table->dropColumn('loinc'));
    }
};
