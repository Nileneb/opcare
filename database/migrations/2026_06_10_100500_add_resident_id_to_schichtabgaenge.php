<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schichtabgaenge', function (Blueprint $table) {
            $table->foreignId('resident_id')->nullable()->after('tenant_id')
                ->constrained('residents')->nullOnDelete();
            $table->index('resident_id');
        });
    }

    public function down(): void
    {
        Schema::table('schichtabgaenge', function (Blueprint $table) {
            $table->dropForeign(['resident_id']);
            $table->dropIndex(['resident_id']);
            $table->dropColumn('resident_id');
        });
    }
};
