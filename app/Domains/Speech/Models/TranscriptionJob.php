<?php

namespace App\Domains\Speech\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int|null $reviewer_id
 * @property string $kontext
 * @property string|null $audio_ref
 * @property TranscriptionStatus $status
 * @property string|null $rohtranskript
 * @property array<array-key, mixed>|null $sis_vorschlag
 * @property string|null $fehler
 * @property Carbon|null $freigegeben_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereAudioRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereFehler($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereFreigegebenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereKontext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereReviewerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereRohtranskript($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereSisVorschlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranscriptionJob whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
