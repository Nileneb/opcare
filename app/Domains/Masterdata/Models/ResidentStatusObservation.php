<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Support\StatusObservationCatalog;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property string $typ
 * @property string|null $wert_code
 * @property string|null $wert_text
 * @property Carbon|null $erfasst_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereErfasstAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereWertCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentStatusObservation whereWertText($value)
 *
 * @mixin \Eloquent
 */
class ResidentStatusObservation extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'typ', 'wert_code', 'wert_text', 'erfasst_am'];

    protected $casts = ['erfasst_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** Menschliche Anzeige: Katalog-Label des Typs + Wert (codiert → Options-Label, sonst Freitext). */
    public function anzeige(): string
    {
        $def = StatusObservationCatalog::get($this->typ);
        $wert = $this->wert_code ? ($def['options'][$this->wert_code] ?? $this->wert_code) : (string) $this->wert_text;

        return ($def['label'] ?? $this->typ).': '.$wert;
    }
}
