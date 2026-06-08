<?php

namespace App\Domains\Brandschutz\Models;

use App\Domains\Brandschutz\Enums\MangelSchwere;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Brandschutz-Begehungsprotokoll (betriebliche Eigenkontrolle, DGUV Information 205-001).
 * Frist-Ampel: die jüngste Begehung je Bereich treibt den Status — Latest-Record-Muster.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $bereich
 * @property Carbon $begangen_am
 * @property int|null $begangen_von
 * @property int $intervall_monate
 * @property string|null $bemerkung
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $begeher
 * @property-read Tenant $tenant
 * @property-read Collection<int, Brandschutzmangel> $maengel
 * @property-read int|null $maengel_count
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereBegangenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereBegangenVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereBereich($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereBemerkung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzbegehung whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Brandschutzbegehung extends BaseModel
{
    protected $table = 'brandschutzbegehungen';

    protected $fillable = [
        'tenant_id', 'bereich', 'begangen_am', 'begangen_von', 'intervall_monate', 'bemerkung',
    ];

    protected $casts = [
        'begangen_am' => 'date',
        'intervall_monate' => 'integer',
    ];

    /** @return HasMany<Brandschutzmangel, $this> */
    public function maengel(): HasMany
    {
        return $this->hasMany(Brandschutzmangel::class, 'brandschutzbegehung_id');
    }

    /** @return BelongsTo<User, $this> */
    public function begeher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'begangen_von');
    }

    public function naechsteBegehung(): Carbon
    {
        return $this->begangen_am->copy()->addMonths($this->intervall_monate);
    }

    public function istUeberfaellig(): bool
    {
        return $this->naechsteBegehung()->lt(today());
    }

    /** Frist-Ampel: 'rot' (überfällig), 'gelb' (≤30 Tage), 'gruen' (sonst). */
    public function faelligkeitsStatus(): string
    {
        $naechste = $this->naechsteBegehung();

        if ($naechste->lt(today())) {
            return 'rot';
        }

        if ($naechste->lte(today()->addDays(30))) {
            return 'gelb';
        }

        return 'gruen';
    }

    /**
     * SSOT: alle offenen Mängel dieser Begehung (behoben_am IS NULL).
     *
     * @return Collection<int, Brandschutzmangel>
     */
    public function offeneMaengel(): Collection
    {
        return $this->maengel->filter(fn (Brandschutzmangel $m) => $m->istOffen())->values();
    }

    public function hatOffeneMaengel(): bool
    {
        return $this->offeneMaengel()->isNotEmpty();
    }

    public function hoechsteOffeneSchwere(): ?MangelSchwere
    {
        $offen = $this->offeneMaengel();

        if ($offen->isEmpty()) {
            return null;
        }

        return $offen->sortByDesc(fn (Brandschutzmangel $m) => $m->schwere->rang())->first()->schwere;
    }
}
