<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHY(Track B, at-rest encryption): SisTopicFieldEntry::strukturdaten wird mit dem Cast
 * `encrypted:array` gespeichert. Laravel speichert verschlüsselte Werte als Ciphertext-String
 * (base64), nicht als JSON-Struktur. PostgreSQL lehnt einen Ciphertext-String in einer jsonb-
 * Spalte ab. Die Spalte muss daher als `text` deklariert sein, damit der Ciphertext gespeichert
 * werden kann; das Modell-Cast übernimmt die Dekodierung/Decodierung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sis_topic_field_entries', function (Blueprint $table) {
            $table->text('strukturdaten')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sis_topic_field_entries', function (Blueprint $table) {
            $table->jsonb('strukturdaten')->nullable()->change();
        });
    }
};
