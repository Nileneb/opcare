<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Quality\Enums\GremiumTyp;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ein Gremium der Mitwirkung/Selbstverwaltung: Heimbeirat (HeimmwV, § 10 WBVG), Angehörigenbeirat,
 * Qualitätszirkel (§ 113 SGB XI), Arbeitsschutzausschuss (§ 11 ASiG). Wahlperiode → Neuwahl-Ampel,
 * Soll-Sitzungstakt → Sitzungs-Ampel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property GremiumTyp $typ
 * @property string $name
 * @property string|null $beschreibung
 * @property Carbon|null $gewaehlt_am
 * @property int|null $periode_monate
 * @property int|null $sitzung_intervall_monate
 * @property Carbon|null $aufgeloest_am
 *
 * @mixin \Eloquent
 */
class Gremium extends BaseModel
{
    protected $table = 'gremien';

    protected $fillable = ['tenant_id', 'typ', 'name', 'beschreibung', 'gewaehlt_am', 'periode_monate',
        'sitzung_intervall_monate', 'aufgeloest_am'];

    protected $casts = [
        'typ' => GremiumTyp::class,
        'gewaehlt_am' => 'date',
        'aufgeloest_am' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<GremiumMitglied, $this> */
    public function mitglieder(): HasMany
    {
        return $this->hasMany(GremiumMitglied::class)->orderBy('funktion')->orderBy('name');
    }

    /** @return HasMany<GremiumSitzung, $this> */
    public function sitzungen(): HasMany
    {
        return $this->hasMany(GremiumSitzung::class)->orderByDesc('datum');
    }

    public function aktiv(): bool
    {
        return $this->aufgeloest_am === null;
    }

    public function periodeEndet(): ?Carbon
    {
        if ($this->gewaehlt_am === null || $this->periode_monate === null) {
            return null;
        }

        return $this->gewaehlt_am->copy()->addMonths($this->periode_monate);
    }

    public function naechsteSitzungFaellig(): ?Carbon
    {
        if ($this->sitzung_intervall_monate === null) {
            return null;
        }
        $basis = $this->sitzungen()->max('datum') ?? $this->gewaehlt_am ?? $this->created_at;
        if ($basis === null) {
            return null;
        }

        return Carbon::parse($basis)->addMonths($this->sitzung_intervall_monate);
    }

    /**
     * neuwahl_faellig | sitzung_faellig | aktiv | aufgeloest — schlechtester offener Punkt zählt.
     */
    public function status(): string
    {
        if (! $this->aktiv()) {
            return 'aufgeloest';
        }
        $periode = $this->periodeEndet();
        if ($periode !== null && $periode->isPast()) {
            return 'neuwahl_faellig';
        }
        $sitzung = $this->naechsteSitzungFaellig();
        if ($sitzung !== null && $sitzung->isPast()) {
            return 'sitzung_faellig';
        }

        return 'aktiv';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'neuwahl_faellig' => 'red',
            'sitzung_faellig' => 'amber',
            'aufgeloest' => 'gray',
            default => 'green',
        };
    }
}
