<?php

namespace App\Domains\Capture\Models;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Bestellposition;
use App\Domains\Accounting\Models\Lagerbewegung;
use App\Domains\Capture\Enums\PositionStatus;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein einzelner Positions-Vorschlag aus einer LieferscheinAnalyse.
 *
 * Erst ein bestätigter Vorschlag schreibt eine Lagerbewegung.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $analyse_id
 * @property string $text
 * @property numeric|null $menge
 * @property string|null $einheit
 * @property numeric|null $einzelpreis
 * @property string|null $charge_nr
 * @property Carbon|null $mhd
 * @property int|null $matched_artikel_id
 * @property int|null $matched_bestellposition_id
 * @property array<array-key, mixed>|null $kandidaten
 * @property numeric|null $konfidenz
 * @property PositionStatus $status
 * @property int|null $wareneingang_bewegung_id
 * @property int|null $entschieden_von
 * @property Carbon|null $entschieden_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read LieferscheinAnalyse $analyse
 * @property-read Artikel|null $artikel
 * @property-read Bestellposition|null $bestellposition
 * @property-read User|null $entscheider
 * @property-read Tenant $tenant
 * @property-read Lagerbewegung|null $wareneingangBewegung
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereAnalyseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereChargeNr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereEinheit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereEinzelpreis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereEntschiedenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereEntschiedenVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereKandidaten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereKonfidenz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereMatchedArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereMatchedBestellpositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereMenge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereMhd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinPositionVorschlag whereWareneingangBewegungId($value)
 *
 * @mixin \Eloquent
 */
class LieferscheinPositionVorschlag extends BaseModel
{
    protected $table = 'lieferschein_position_vorschlaege';

    protected $fillable = [
        'tenant_id',
        'analyse_id',
        'text',
        'menge',
        'einheit',
        'einzelpreis',
        'charge_nr',
        'mhd',
        'matched_artikel_id',
        'matched_bestellposition_id',
        'kandidaten',
        'konfidenz',
        'status',
        'wareneingang_bewegung_id',
        'entschieden_von',
        'entschieden_am',
    ];

    protected $casts = [
        'menge' => 'decimal:2',
        'einzelpreis' => 'decimal:2',
        'mhd' => 'date',
        'kandidaten' => 'array',
        'konfidenz' => 'decimal:3',
        'status' => PositionStatus::class,
        'entschieden_am' => 'datetime',
    ];

    public function offen(): bool
    {
        return $this->status === PositionStatus::Vorgeschlagen;
    }

    /** @return BelongsTo<LieferscheinAnalyse, $this> */
    public function analyse(): BelongsTo
    {
        return $this->belongsTo(LieferscheinAnalyse::class, 'analyse_id');
    }

    /** @return BelongsTo<Artikel, $this> */
    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'matched_artikel_id');
    }

    /** @return BelongsTo<Bestellposition, $this> */
    public function bestellposition(): BelongsTo
    {
        return $this->belongsTo(Bestellposition::class, 'matched_bestellposition_id');
    }

    /** @return BelongsTo<Lagerbewegung, $this> */
    public function wareneingangBewegung(): BelongsTo
    {
        return $this->belongsTo(Lagerbewegung::class, 'wareneingang_bewegung_id');
    }

    /** @return BelongsTo<User, $this> */
    public function entscheider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entschieden_von');
    }
}
