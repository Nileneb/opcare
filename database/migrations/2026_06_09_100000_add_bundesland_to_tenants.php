<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Explizit gewähltes Bundesland (Landesheimrecht); leer = automatisch aus der PLZ abgeleitet.
            $table->string('bundesland', 2)->nullable()->after('ort');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('bundesland');
        });
    }
};
