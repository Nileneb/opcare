<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artikel', function (Blueprint $table) {
            $table->boolean('pflegehilfsmittel')->default(false)->after('aktiv');
            $table->string('pg_nummer')->nullable()->after('pflegehilfsmittel');
        });
    }

    public function down(): void
    {
        Schema::table('artikel', function (Blueprint $table) {
            $table->dropColumn(['pflegehilfsmittel', 'pg_nummer']);
        });
    }
};
