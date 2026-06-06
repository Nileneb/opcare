<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Personnel\Enums\Beschaeftigungsart;
use App\Domains\Personnel\Enums\Familienstand;
use App\Domains\Personnel\Enums\Geschlecht;
use App\Domains\Personnel\Enums\Krankenversicherung;
use App\Domains\Personnel\Enums\Masernschutz;
use App\Domains\Personnel\Enums\Qualifikation;
use App\Domains\Personnel\Enums\Steuerklasse;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Personalstammakte (Personalfragebogen) 1:1 zum App-Benutzer. Steuer-ID, SV-Nummer und IBAN werden per
 * `encrypted`-Cast at rest verschlüsselt (Track B). Tenant-Scoping über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string|null $personalnummer
 * @property string|null $anrede
 * @property string|null $vorname
 * @property string|null $nachname
 * @property string|null $geburtsname
 * @property Carbon|null $geburtsdatum
 * @property string|null $geburtsort
 * @property string|null $staatsangehoerigkeit
 * @property Geschlecht|null $geschlecht
 * @property Familienstand|null $familienstand
 * @property string|null $strasse
 * @property string|null $hausnummer
 * @property string|null $plz
 * @property string|null $ort
 * @property string|null $telefon
 * @property bool $schwerbehinderung
 * @property int|null $grad_behinderung
 * @property string|null $steuer_id
 * @property Steuerklasse|null $steuerklasse
 * @property string|null $konfession
 * @property float|null $kinderfreibetraege
 * @property string|null $sv_nummer
 * @property string|null $krankenkasse
 * @property Krankenversicherung|null $krankenversicherung
 * @property string|null $iban
 * @property string|null $bic
 * @property string|null $kontoinhaber
 * @property Carbon|null $eintritt_am
 * @property Carbon|null $austritt_am
 * @property Carbon|null $befristet_bis
 * @property Carbon|null $probezeit_bis
 * @property Beschaeftigungsart|null $beschaeftigungsart
 * @property float|null $wochenstunden
 * @property string|null $position
 * @property int|null $urlaubsanspruch
 * @property Qualifikation|null $qualifikation
 * @property string|null $berufsurkunde_nr
 * @property Carbon|null $fuehrungszeugnis_am
 * @property Masernschutz|null $masernschutz
 * @property string|null $notfallkontakt_name
 * @property string|null $notfallkontakt_telefon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereAnrede($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereAustrittAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereBefristetBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereBerufsurkundeNr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereBeschaeftigungsart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereBic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereEintrittAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereFamilienstand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereFuehrungszeugnisAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereGeburtsdatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereGeburtsname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereGeburtsort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereGeschlecht($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereGradBehinderung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereHausnummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereIban($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereKinderfreibetraege($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereKonfession($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereKontoinhaber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereKrankenkasse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereKrankenversicherung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereMasernschutz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereNachname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereNotfallkontaktName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereNotfallkontaktTelefon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereOrt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile wherePersonalnummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile wherePlz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereProbezeitBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereQualifikation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereSchwerbehinderung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereStaatsangehoerigkeit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereSteuerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereSteuerklasse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereStrasse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereSvNummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereTelefon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereUrlaubsanspruch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereVorname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeProfile whereWochenstunden($value)
 *
 * @mixin \Eloquent
 */
class EmployeeProfile extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'user_id', 'personalnummer', 'anrede', 'vorname', 'nachname', 'geburtsname',
        'geburtsdatum', 'geburtsort', 'staatsangehoerigkeit', 'geschlecht', 'familienstand',
        'strasse', 'hausnummer', 'plz', 'ort', 'telefon', 'schwerbehinderung', 'grad_behinderung',
        'steuer_id', 'steuerklasse', 'konfession', 'kinderfreibetraege',
        'sv_nummer', 'krankenkasse', 'krankenversicherung',
        'iban', 'bic', 'kontoinhaber',
        'eintritt_am', 'austritt_am', 'befristet_bis', 'probezeit_bis', 'beschaeftigungsart',
        'wochenstunden', 'position', 'urlaubsanspruch',
        'qualifikation', 'berufsurkunde_nr', 'fuehrungszeugnis_am', 'masernschutz',
        'notfallkontakt_name', 'notfallkontakt_telefon',
    ];

    protected $casts = [
        // At-Rest-Verschlüsselung sensibler Personaldaten (Track B).
        'steuer_id' => 'encrypted',
        'sv_nummer' => 'encrypted',
        'iban' => 'encrypted',
        'geburtsdatum' => 'date',
        'eintritt_am' => 'date',
        'austritt_am' => 'date',
        'befristet_bis' => 'date',
        'probezeit_bis' => 'date',
        'fuehrungszeugnis_am' => 'date',
        'schwerbehinderung' => 'boolean',
        'grad_behinderung' => 'integer',
        'urlaubsanspruch' => 'integer',
        'kinderfreibetraege' => 'float',
        'wochenstunden' => 'float',
        'geschlecht' => Geschlecht::class,
        'familienstand' => Familienstand::class,
        'steuerklasse' => Steuerklasse::class,
        'beschaeftigungsart' => Beschaeftigungsart::class,
        'krankenversicherung' => Krankenversicherung::class,
        'qualifikation' => Qualifikation::class,
        'masernschutz' => Masernschutz::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
