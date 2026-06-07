<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lagerschichten', function (Blueprint $table) {
            $table->foreignId('bestellposition_id')->nullable()->constrained('bestellpositionen')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lagerschichten', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bestellposition_id');
        });
    }
};
