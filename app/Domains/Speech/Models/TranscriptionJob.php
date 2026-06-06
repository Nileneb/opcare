<?php

namespace App\Domains\Speech\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptionJob extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'reviewer_id', 'kontext',
        'audio_ref', 'status', 'rohtranskript', 'sis_vorschlag', 'fehler', 'freigegeben_at',
    ];

    protected $casts = [
        'status' => TranscriptionStatus::class,
        // WHY(Track B, At-Rest): gesprochene Gesundheitsdaten — Rohtranskript + LLM-SIS-Vorschlag verschlüsselt.
        'rohtranskript' => 'encrypted',
        'sis_vorschlag' => 'encrypted:array',
        'freigegeben_at' => 'datetime',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
