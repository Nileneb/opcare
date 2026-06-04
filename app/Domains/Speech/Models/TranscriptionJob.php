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
        'sis_vorschlag' => 'array',
        'freigegeben_at' => 'datetime',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
