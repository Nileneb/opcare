<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freigabe-Audit für Bewohner-Dokumente/Fotos (spatie media): wer hat wann was an wen freigegeben,
 * mit Ablaufzeit. Erfüllt die DSGVO-Protokollpflicht bei der Weitergabe von Gesundheitsdaten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users');
            $table->string('share_type'); // physician | relative | authority | internal
            $table->string('recipient_name');
            $table->timestamp('expires_at');
            $table->timestamp('accessed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_shares');
    }
};
