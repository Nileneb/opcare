<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artikel', function (Blueprint $table) {
            $table->json('name_embedding')->nullable()->after('aktiv');
            $table->string('embedding_model')->nullable()->after('name_embedding');
        });
    }

    public function down(): void
    {
        Schema::table('artikel', function (Blueprint $table) {
            $table->dropColumn(['name_embedding', 'embedding_model']);
        });
    }
};
