<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('traeger')->nullable()->after('name');
            $table->string('ik_nummer')->nullable()->after('slug');
            $table->jsonb('settings')->nullable();
            $table->boolean('aktiv')->default(true);
        });
    }
    public function down(): void {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['traeger', 'ik_nummer', 'settings', 'aktiv']);
        });
    }
};
