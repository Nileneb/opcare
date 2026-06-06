<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // betroffene Mitarbeiter:in + Regel + Tag identifizieren den Befund eindeutig.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key');
            $table->date('datum');
            // zwingender Grund (§ 14 ArbZG), z. B. „Nachfolgekraft nicht erschienen".
            $table->text('grund');
            $table->foreignId('begruendet_von')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'rule_key', 'datum'], 'compliance_justification_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_justifications');
    }
};
