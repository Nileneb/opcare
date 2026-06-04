<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// WHY: ICD-Katalog ist tenant-übergreifend (Referenzdaten) — KEIN tenant_id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('bezeichnung');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd_codes');
    }
};
