<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // WHY(ÜLB): An-/Zugehörige zur Benachrichtigung + Pflege durch Angehörige
    // (Sektionen benachrichtigungVonAn-undZugehoerigen / pflegeDurchAngehoerige) → FHIR RelatedPerson.
    // Abgegrenzt von Custodian (gesetzliche Betreuung).
    public function up(): void
    {
        Schema::create('resident_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('beziehung')->nullable(); // Tochter, Sohn, Ehepartner:in, …
            $table->string('telefon')->nullable();
            $table->boolean('benachrichtigen')->default(false);
            $table->string('hinweis')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_contacts');
    }
};
