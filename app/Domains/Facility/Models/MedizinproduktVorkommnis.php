<?php

namespace App\Domains\Facility\Models;

use App\Domains\Facility\Enums\MpVorkommnisArt;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Funktionsstörung/Vorkommnis im Medizinproduktebuch (§ 13 MPBetreibV); schwerwiegende Vorkommnisse sind
 * dem BfArM zu melden (§ 3 MPAMIV) — `bfarm_gemeldet` hält den Meldestand fest.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $medizinprodukt_id
 * @property Carbon $datum
 * @property MpVorkommnisArt $art
 * @property string $beschreibung
 * @property string|null $massnahme
 * @property bool $bfarm_gemeldet
 * @property int $gemeldet_von
 * @property Carbon|null $behoben_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read Medizinprodukt $medizinprodukt
 * @property-read Tenant $tenant
 * @property-read User $melder
 *
 * @mixin \Eloquent
 */
class MedizinproduktVorkommnis extends BaseModel
{
    protected $table = 'medizinprodukt_vorkommnisse';

    protected $fillable = ['tenant_id', 'medizinprodukt_id', 'datum', 'art', 'beschreibung', 'massnahme', 'bfarm_gemeldet', 'gemeldet_von', 'behoben_am'];

    protected $casts = [
        'art' => MpVorkommnisArt::class,
        'datum' => 'date',
        'behoben_am' => 'date',
        'bfarm_gemeldet' => 'boolean',
    ];

    public function medizinprodukt(): BelongsTo
    {
        return $this->belongsTo(Medizinprodukt::class, 'medizinprodukt_id');
    }

    public function melder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gemeldet_von');
    }

    public function offen(): bool
    {
        return $this->behoben_am === null;
    }
}
