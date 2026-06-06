<?php

namespace App\Domains\Compliance\Models;

use App\Domains\Compliance\Enums\Rechtsgrundlage;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Verarbeitungstätigkeit im Verzeichnis nach Art. 30 Abs. 1 DSGVO (Verantwortlicher). Editierbarer
 * Katalog je Einrichtung mit den Pflichtangaben (Zweck, Rechtsgrundlage, Daten-/Betroffenenkategorien,
 * Empfänger, Drittland, Löschfrist, TOM). Aus Prüfdatum + Intervall folgt die Aktualitäts-Ampel — dasselbe
 * Nachweis-mit-Frist-Muster wie bei den Beauftragten/Nachweisen.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $schluessel
 * @property string $name
 * @property string $zweck
 * @property Rechtsgrundlage $rechtsgrundlage
 * @property string $kategorien_betroffene
 * @property string $kategorien_daten
 * @property string|null $empfaenger
 * @property string|null $drittland
 * @property string $loeschfrist
 * @property string|null $tom
 * @property int $pruef_intervall_monate
 * @property Carbon|null $geprueft_am
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Auftragsverarbeitung> $auftragsverarbeitungen
 * @property-read int|null $auftragsverarbeitungen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereDrittland($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereEmpfaenger($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereGeprueftAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereKategorienBetroffene($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereKategorienDaten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereLoeschfrist($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit wherePruefIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereRechtsgrundlage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereSchluessel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereTom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Verarbeitungstaetigkeit whereZweck($value)
 *
 * @mixin \Eloquent
 */
class Verarbeitungstaetigkeit extends BaseModel
{
    protected $table = 'verarbeitungstaetigkeiten';

    protected $fillable = [
        'tenant_id', 'schluessel', 'name', 'zweck', 'rechtsgrundlage', 'kategorien_betroffene',
        'kategorien_daten', 'empfaenger', 'drittland', 'loeschfrist', 'tom', 'pruef_intervall_monate',
        'geprueft_am', 'aktiv',
    ];

    protected $casts = [
        'rechtsgrundlage' => Rechtsgrundlage::class,
        'geprueft_am' => 'date',
        'aktiv' => 'boolean',
    ];

    /** @return HasMany<Auftragsverarbeitung, $this> */
    public function auftragsverarbeitungen(): HasMany
    {
        return $this->hasMany(Auftragsverarbeitung::class);
    }

    public function naechstePruefung(): ?Carbon
    {
        $basis = $this->geprueft_am ?? $this->created_at;

        return $basis?->copy()->addMonths($this->pruef_intervall_monate);
    }

    /** ungeprueft | ueberfaellig | faellig | aktuell */
    public function status(): string
    {
        if ($this->geprueft_am === null) {
            return 'ungeprueft';
        }
        $faellig = $this->naechstePruefung();
        if ($faellig === null) {
            return 'aktuell';
        }
        if ($faellig->isPast()) {
            return 'ueberfaellig';
        }

        return $faellig->lessThanOrEqualTo(today()->addDays(30)) ? 'faellig' : 'aktuell';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'ungeprueft', 'ueberfaellig' => 'red',
            'faellig' => 'amber',
            default => 'green',
        };
    }
}
