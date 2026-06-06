<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Enums\Aufgabenkreis;
use App\Domains\Masterdata\Enums\EreignisKategorie;
use App\Domains\Masterdata\Enums\VertretungTyp;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rechtliche Vertretung eines Bewohners (§§ 1814/1815/1820 BGB) — abgegrenzt von {@see ResidentContact}
 * (Angehörige, FHIR RelatedPerson). Aufgabenkreise gaten Sicht/Benachrichtigung; bericht_intervall_monate +
 * letzter_bericht_am bilden die Pflicht-mit-Frist (§ 1863 Jahresbericht / § 1865 Rechnungslegung) ab.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property VertretungTyp $typ
 * @property array<int, string>|null $aufgabenkreise
 * @property int|null $user_id
 * @property string $name
 * @property string|null $umfang
 * @property string|null $kontakt
 * @property string|null $email
 * @property bool $beruflich
 * @property string|null $gericht
 * @property string|null $aktenzeichen
 * @property Carbon|null $gueltig_bis
 * @property int|null $bericht_intervall_monate
 * @property Carbon|null $letzter_bericht_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 * @property-read User|null $user
 *
 * @mixin \Eloquent
 */
class Custodian extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'typ', 'aufgabenkreise', 'user_id', 'name', 'umfang',
        'kontakt', 'email', 'beruflich', 'gericht', 'aktenzeichen', 'gueltig_bis', 'bericht_intervall_monate',
        'letzter_bericht_am'];

    protected $casts = [
        'typ' => VertretungTyp::class,
        'aufgabenkreise' => 'array',
        'beruflich' => 'boolean',
        'gueltig_bis' => 'date',
        'letzter_bericht_am' => 'date',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hatAufgabenkreis(Aufgabenkreis $kreis): bool
    {
        return in_array($kreis->value, $this->aufgabenkreise ?? [], true);
    }

    /** @return array<int, Aufgabenkreis> */
    public function aufgabenkreiseEnums(): array
    {
        return array_values(array_filter(
            array_map(fn (string $v): ?Aufgabenkreis => Aufgabenkreis::tryFrom($v), $this->aufgabenkreise ?? []),
        ));
    }

    /** Bestellung/Vollmacht aktiv (unbefristet oder gueltig_bis noch nicht überschritten). */
    public function aktiv(): bool
    {
        return $this->gueltig_bis === null || ! $this->gueltig_bis->isPast();
    }

    /**
     * Recht auf Beteiligung/Information bei diesem Ereignis: leere Pflicht-Kreisliste = alle aktiven
     * Vertretungen, sonst Schnittmenge mit den eigenen Aufgabenkreisen.
     */
    public function darfEreignis(EreignisKategorie $kategorie): bool
    {
        $erforderlich = $kategorie->erforderlicheAufgabenkreise();
        if ($erforderlich === []) {
            return true;
        }
        foreach ($erforderlich as $kreis) {
            if ($this->hatAufgabenkreis($kreis)) {
                return true;
            }
        }

        return false;
    }

    /** Fälligkeit des nächsten Berichts (§ 1863) ab letztem Bericht bzw. Bestellung/Anlage. */
    public function naechsterBericht(): ?Carbon
    {
        if ($this->bericht_intervall_monate === null) {
            return null;
        }
        $basis = $this->letzter_bericht_am ?? $this->created_at;
        if ($basis === null) {
            return null;
        }

        return Carbon::parse($basis)->addMonths($this->bericht_intervall_monate);
    }

    public function berichtAmpel(): string
    {
        $faellig = $this->naechsterBericht();
        if ($faellig === null) {
            return 'gray';
        }
        if ($faellig->isPast()) {
            return 'red';
        }

        return $faellig->lte(now()->addDays(30)) ? 'amber' : 'green';
    }

    public function vertretungAmpel(): string
    {
        if ($this->gueltig_bis === null) {
            return 'green';
        }
        if ($this->gueltig_bis->isPast()) {
            return 'red';
        }

        return $this->gueltig_bis->lte(now()->addDays(30)) ? 'amber' : 'green';
    }
}
