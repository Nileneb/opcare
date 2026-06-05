<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// WHY: Standard-Pflegemaßnahmen-Katalog ist tenant-übergreifend (Referenzdaten) — KEIN tenant_id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measure_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('bezeichnung')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_catalog_items');
    }
};
