<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\BeschwerdeBereich;
use App\Domains\Quality\Enums\BeschwerdeKategorie;
use App\Domains\Quality\Enums\BeschwerdeQuelle;
use App\Domains\Quality\Enums\BeschwerdeStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eine Beschwerde/Anregung/Meldung (§ 113 SGB XI Beschwerdemanagement, Landes-WTG-Beschwerderecht,
 * Gewaltschutz § 5 SGB XI). Der Melder wählt bei Eingang, ob seine Identität sichtbar ist
 * (melder_sichtbarkeit). Diese Wahl bindet jede spätere Weiterleitung: ist sie 'anonym', darf der Melder
 * dem empfangenden Bereich nie offengelegt werden.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $titel
 * @property string $beschreibung
 * @property BeschwerdeKategorie $kategorie
 * @property BeschwerdeBereich $bereich
 * @property BeschwerdeQuelle $quelle
 * @property string $melder_sichtbarkeit
 * @property int|null $melder_user_id
 * @property string|null $melder_name
 * @property int|null $betroffener_resident_id
 * @property Carbon $eingang_am
 * @property Carbon|null $frist
 * @property BeschwerdeStatus $status
 * @property int|null $bearbeiter_user_id
 * @property string|null $schweregrad
 * @property string|null $sofortmassnahme
 * @property Carbon|null $erledigt_am
 * @property string|null $ergebnis
 * @property-read User|null $melder
 * @property-read User|null $bearbeiter
 * @property-read Resident|null $resident
 * @property-read Tenant $tenant
 * @property-read Collection<int, BeschwerdeVorgang> $vorgaenge
 *
 * @mixin \Eloquent
 */
class Beschwerde extends BaseModel
{
    protected $table = 'beschwerden';

    protected $fillable = ['tenant_id', 'titel', 'beschreibung', 'kategorie', 'bereich', 'quelle',
        'melder_sichtbarkeit', 'melder_user_id', 'melder_name', 'betroffener_resident_id', 'eingang_am',
        'frist', 'status', 'bearbeiter_user_id', 'schweregrad', 'sofortmassnahme', 'erledigt_am', 'ergebnis'];

    protected $casts = [
        'kategorie' => BeschwerdeKategorie::class,
        'bereich' => BeschwerdeBereich::class,
        'quelle' => BeschwerdeQuelle::class,
        'status' => BeschwerdeStatus::class,
        'eingang_am' => 'date',
        'frist' => 'date',
        'erledigt_am' => 'date',
    ];

    public function melder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'melder_user_id');
    }

    public function bearbeiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bearbeiter_user_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'betroffener_resident_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<BeschwerdeVorgang, $this> */
    public function vorgaenge(): HasMany
    {
        return $this->hasMany(BeschwerdeVorgang::class)->orderBy('id');
    }

    /** Der Melder hat bei Eingang Anonymität gewählt → Identität nie an Empfänger offenlegen. */
    public function anonym(): bool
    {
        return $this->melder_sichtbarkeit === 'anonym';
    }

    /** Anzeigename des Melders; respektiert die Anonymitätswahl. */
    public function melderAnzeige(): string
    {
        if ($this->anonym()) {
            return 'anonym';
        }
        if (filled($this->melder_name)) {
            return $this->melder_name;
        }
        if ($this->melder_user_id === null) {
            return '—';
        }

        return $this->melder->name;
    }

    public function offen(): bool
    {
        return $this->status->offen();
    }

    /** Ein Gewaltvorfall ohne dokumentierte Sofortmaßnahme ist der dringlichste Fall. */
    public function gewaltOhneSofortmassnahme(): bool
    {
        return $this->kategorie->istGewalt() && blank($this->sofortmassnahme);
    }

    public function ueberfaellig(): bool
    {
        return $this->offen() && $this->frist !== null && $this->frist->isPast();
    }

    public function ampel(): string
    {
        if (! $this->offen()) {
            return $this->kategorie === BeschwerdeKategorie::Lob ? 'green' : 'gray';
        }
        if ($this->gewaltOhneSofortmassnahme() || $this->ueberfaellig()) {
            return 'red';
        }
        if ($this->frist !== null && $this->frist->lessThanOrEqualTo(today()->addDays(7))) {
            return 'amber';
        }
        if ($this->kategorie->istGewalt()) {
            return 'amber'; // Gewaltvorfall bleibt sichtbar dringlich, bis erledigt
        }

        return 'green';
    }
}
