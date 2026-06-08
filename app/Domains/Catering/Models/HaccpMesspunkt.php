<?php

namespace App\Domains\Catering\Models;

use App\Domains\Catering\Enums\HaccpArt;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ein HACCP-kritischer Kontrollpunkt (CCP) mit Grenzwert und Art.
 * Norm-Anker: VO (EG) 852/2004 Art. 5, DIN 10508 Temperatur-Grenzwerte.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $bezeichnung
 * @property HaccpArt $art
 * @property float $grenzwert
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, Temperaturmessung> $messungen
 * @property-read int|null $messungen_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt whereArt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt whereBezeichnung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt whereGrenzwert($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HaccpMesspunkt whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class HaccpMesspunkt extends BaseModel
{
    protected $table = 'haccp_messpunkte';

    protected $fillable = ['tenant_id', 'bezeichnung', 'art', 'grenzwert', 'aktiv'];

    protected $casts = [
        'art' => HaccpArt::class,
        'grenzwert' => 'decimal:1',
        'aktiv' => 'boolean',
    ];

    /** @return HasMany<Temperaturmessung, $this> */
    public function messungen(): HasMany
    {
        return $this->hasMany(Temperaturmessung::class);
    }

    /**
     * Prüft ob ein Messwert eine HACCP-Abweichung darstellt.
     * Grenzfall exakt am Grenzwert = KEINE Abweichung (≤ / ≥ inklusiv).
     */
    public function istAbweichung(float $wert): bool
    {
        if ($this->art->istMax()) {
            return $wert > (float) $this->grenzwert;
        }

        return $wert < (float) $this->grenzwert;
    }

    /**
     * All open deviations across all days — single source of truth for the correction workflow.
     * WHY(VO 852/2004 Art. 5): a deviation from a previous day without correction must remain
     * visible until explicitly closed; day-scoped eager-loads would hide it.
     *
     * @return Collection<int, Temperaturmessung>
     */
    public function offeneAbweichungen(): Collection
    {
        return $this->messungen()
            ->where('abweichung', true)
            ->whereNull('korrekturmassnahme')
            ->latest('gemessen_am')
            ->get();
    }

    /**
     * Single source of truth: hat dieser Messpunkt eine offene Abweichung (kein Korrekturmaßnahmen-Eintrag)?
     */
    public function offeneAbweichung(): bool
    {
        return $this->offeneAbweichungen()->isNotEmpty();
    }
}
