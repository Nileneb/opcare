<?php

namespace App\Domains\Capture\Models;

use App\Domains\Accounting\Models\Buchung;
use App\Domains\Capture\Enums\VorschlagStatus;
use App\Domains\Capture\Enums\ZielTyp;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Vorschlag, WO eine Beleg-Analyse einsortiert werden könnte (Ziel-Slot + vorgeschlagene Feldwerte). Bleibt
 * `vorgeschlagen`, bis ein:e Berechtigte:r ihn bestätigt (schreibt den Zieldatensatz, z. B. eine Buchung) oder
 * verwirft. Die VLM-Ausgabe schreibt nie still — Human-in-the-loop.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $beleg_analyse_id
 * @property ZielTyp $ziel_typ
 * @property array<array-key, mixed> $ziel_felder
 * @property VorschlagStatus $status
 * @property float|null $konfidenz
 * @property int|null $buchung_id
 * @property int|null $entschieden_von
 * @property Carbon|null $entschieden_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read BelegAnalyse $analyse
 * @property-read Buchung|null $buchung
 * @property-read User|null $entscheider
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereBelegAnalyseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereBuchungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereEntschiedenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereEntschiedenVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereKonfidenz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereZielFelder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EinsortierungsVorschlag whereZielTyp($value)
 *
 * @mixin \Eloquent
 */
class EinsortierungsVorschlag extends BaseModel
{
    protected $table = 'einsortierungs_vorschlaege';

    protected $fillable = [
        'tenant_id', 'beleg_analyse_id', 'ziel_typ', 'ziel_felder', 'status', 'konfidenz',
        'buchung_id', 'entschieden_von', 'entschieden_am',
    ];

    protected $casts = [
        'ziel_typ' => ZielTyp::class,
        'status' => VorschlagStatus::class,
        'ziel_felder' => 'array',
        'konfidenz' => 'float',
        'entschieden_am' => 'datetime',
    ];

    public function offen(): bool
    {
        return $this->status === VorschlagStatus::Vorgeschlagen;
    }

    public function analyse(): BelongsTo
    {
        return $this->belongsTo(BelegAnalyse::class, 'beleg_analyse_id');
    }

    public function buchung(): BelongsTo
    {
        return $this->belongsTo(Buchung::class);
    }

    public function entscheider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entschieden_von');
    }
}
