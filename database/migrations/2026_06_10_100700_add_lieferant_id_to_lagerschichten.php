<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lagerschichten', function (Blueprint $table) {
            $table->foreignId('lieferant_id')->nullable()->constrained('lieferanten')->nullOnDelete()->after('mhd');
        });
    }

    public function down(): void
    {
        Schema::table('lagerschichten', function (Blueprint $table) {
            $table->dropForeign(['lieferant_id']);
            $table->dropColumn('lieferant_id');
        });
    }
};
