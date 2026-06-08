<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temperaturmessungen je HACCP-Messpunkt — tägliches Eigenkontroll-Journal.
 * Norm-Anker: VO (EG) 852/2004 Art. 5 Abs. 2 lit. f (Aufzeichnungen), LMHV §§ 3/4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperaturmessungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('haccp_messpunkt_id')
                ->constrained('haccp_messpunkte')
                ->cascadeOnDelete();
            $table->dateTime('gemessen_am');
            $table->decimal('wert', 5, 1);
            $table->boolean('abweichung')->default(false);
            $table->text('korrekturmassnahme')->nullable();
            $table->foreignId('erfasst_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'haccp_messpunkt_id', 'gemessen_am']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperaturmessungen');
    }
};
