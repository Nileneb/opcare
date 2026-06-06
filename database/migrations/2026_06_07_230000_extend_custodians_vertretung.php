<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// WHY(§§ 1814/1815 BGB): Custodian wird zur vollwertigen rechtlichen Vertretung mit strukturierten
// Aufgabenkreisen, optionalem Login-Konto und Pflicht-mit-Frist (§ 1863 Jahresbericht / § 1865 Rechnungslegung).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custodians', function (Blueprint $table) {
            $table->string('typ')->default('gesetzlicher_betreuer')->after('resident_id');
            $table->json('aufgabenkreise')->nullable()->after('typ');
            $table->foreignId('user_id')->nullable()->after('aufgabenkreise')->constrained('users')->nullOnDelete();
            $table->string('email')->nullable()->after('kontakt');
            $table->boolean('beruflich')->default(false)->after('email');
            $table->string('gericht')->nullable()->after('beruflich');
            $table->string('aktenzeichen')->nullable()->after('gericht');
            $table->date('gueltig_bis')->nullable()->after('aktenzeichen');
            $table->unsignedSmallInteger('bericht_intervall_monate')->nullable()->after('gueltig_bis');
            $table->date('letzter_bericht_am')->nullable()->after('bericht_intervall_monate');
        });
    }

    public function down(): void
    {
        Schema::table('custodians', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['typ', 'aufgabenkreise', 'email', 'beruflich', 'gericht', 'aktenzeichen',
                'gueltig_bis', 'bericht_intervall_monate', 'letzter_bericht_am']);
        });
    }
};
