<?php

namespace App\Domains\Facility\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Trinkwasser-Großanlage im Heim mit Legionellen-Untersuchungspflicht (§ 31 TrinkwV 2023).
 * Frist-Ampel spiegelt das Muster von Medizinprodukt::naechsteStk().
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $bezeichnung
 * @property string|null $gebaeude
 * @property bool $ist_grossanlage
 * @property int $untersuchungsintervall_monate
 * @property Carbon|null $letzte_untersuchung_am
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Probenahmestelle> $probenahmestellen
 * @property-read Collection<int, Legionellenbefund> $befunde
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class Trinkwasseranlage extends BaseModel
{
    protected $table = 'trinkwasseranlagen';

    protected $fillable = [
        'tenant_id', 'bezeichnung', 'gebaeude', 'ist_grossanlage',
        'untersuchungsintervall_monate', 'letzte_untersuchung_am', 'notiz',
    ];

    protected $casts = [
        'ist_grossanlage' => 'boolean',
        'untersuchungsintervall_monate' => 'integer',
        'letzte_untersuchung_am' => 'date',
    ];

    /** @return HasMany<Probenahmestelle, $this> */
    public function probenahmestellen(): HasMany
    {
        return $this->hasMany(Probenahmestelle::class, 'trinkwasseranlage_id');
    }

    /** @return HasMany<Legionellenbefund, $this> */
    public function befunde(): HasMany
    {
        return $this->hasMany(Legionellenbefund::class, 'trinkwasseranlage_id');
    }

    public function naechsteFaelligkeit(): ?Carbon
    {
        if ($this->letzte_untersuchung_am === null) {
            return null;
        }

        return $this->letzte_untersuchung_am->copy()->addMonths($this->untersuchungsintervall_monate);
    }

    public function istUeberfaellig(): bool
    {
        if (! $this->ist_grossanlage) {
            return false;
        }

        $naechste = $this->naechsteFaelligkeit();

        return $naechste === null || $naechste->lt(today());
    }

    /** Frist-Ampel: 'rot' (überfällig/nie untersucht), 'gelb' (<30 Tage), 'gruen' (sonst). */
    public function faelligkeitsStatus(): string
    {
        if (! $this->ist_grossanlage) {
            return 'gruen';
        }

        $naechste = $this->naechsteFaelligkeit();

        if ($naechste === null || $naechste->lt(today())) {
            return 'rot';
        }

        if ($naechste->lte(today()->addDays(30))) {
            return 'gelb';
        }

        return 'gruen';
    }

    /** Gibt true wenn ein Befund mit Überschreitung vorliegt, der noch nicht vollständig bearbeitet ist. */
    public function offeneUeberschreitung(): bool
    {
        return $this->befunde()
            ->where('ueberschreitung', true)
            ->where(function ($q) {
                $q->whereNull('gesundheitsamt_gemeldet_am')
                    ->orWhereNull('massnahme');
            })
            ->exists();
    }
}
