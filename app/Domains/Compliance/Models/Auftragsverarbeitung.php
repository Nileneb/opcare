<?php

namespace App\Domains\Compliance\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein Auftragsverarbeitungs-Verhältnis nach Art. 28 DSGVO (Dienstleister, der personenbezogene Daten im
 * Auftrag verarbeitet — z. B. Hosting, Abrechnungsdienstleister, Medikations-/TI-Anbieter). Pflicht ist ein
 * schriftlicher AVV mit Mindestinhalt; fehlt das Vertragsdatum, ist der Eintrag rot (kein AVV nachgewiesen).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $verarbeitungstaetigkeit_id
 * @property string $dienstleister
 * @property string $zweck
 * @property string $kategorien_daten
 * @property string|null $drittland
 * @property bool $unterauftragnehmer
 * @property Carbon|null $vertrag_geschlossen_am
 * @property int $pruef_intervall_monate
 * @property Carbon|null $geprueft_am
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read Verarbeitungstaetigkeit|null $verarbeitungstaetigkeit
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereDienstleister($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereDrittland($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereGeprueftAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereKategorienDaten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung wherePruefIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereUnterauftragnehmer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereVerarbeitungstaetigkeitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereVertragGeschlossenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Auftragsverarbeitung whereZweck($value)
 *
 * @mixin \Eloquent
 */
class Auftragsverarbeitung extends BaseModel
{
    protected $table = 'auftragsverarbeitungen';

    protected $fillable = [
        'tenant_id', 'verarbeitungstaetigkeit_id', 'dienstleister', 'zweck', 'kategorien_daten', 'drittland',
        'unterauftragnehmer', 'vertrag_geschlossen_am', 'pruef_intervall_monate', 'geprueft_am', 'notiz',
    ];

    protected $casts = [
        'unterauftragnehmer' => 'boolean',
        'vertrag_geschlossen_am' => 'date',
        'geprueft_am' => 'date',
    ];

    /** @return BelongsTo<Verarbeitungstaetigkeit, $this> */
    public function verarbeitungstaetigkeit(): BelongsTo
    {
        return $this->belongsTo(Verarbeitungstaetigkeit::class);
    }

    public function naechstePruefung(): ?Carbon
    {
        $basis = $this->geprueft_am ?? $this->vertrag_geschlossen_am;

        return $basis?->copy()->addMonths($this->pruef_intervall_monate);
    }

    /** kein_avv | ueberfaellig | faellig | aktuell */
    public function status(): string
    {
        if ($this->vertrag_geschlossen_am === null) {
            return 'kein_avv';
        }
        $faellig = $this->naechstePruefung();
        if ($faellig !== null && $faellig->isPast()) {
            return 'ueberfaellig';
        }
        if ($faellig !== null && $faellig->lessThanOrEqualTo(today()->addDays(30))) {
            return 'faellig';
        }

        return 'aktuell';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'kein_avv', 'ueberfaellig' => 'red',
            'faellig' => 'amber',
            default => 'green',
        };
    }
}
