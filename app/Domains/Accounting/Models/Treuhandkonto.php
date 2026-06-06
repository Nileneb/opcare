<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Treuhand-/Barbetragskonto eines Bewohners (§ 27b SGB XII). Getrennt vom Einrichtungsvermögen und „für
 * Rechnung des einzelnen Bewohners" geführt (HeimsicherungsV § 8) — genau ein Konto je Bewohner.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property string|null $iban
 * @property Carbon $eroeffnet_am
 * @property Carbon|null $geschlossen_am
 * @property string|null $schliessgrund
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class Treuhandkonto extends BaseModel
{
    protected $table = 'treuhand_konten';

    protected $fillable = ['tenant_id', 'resident_id', 'iban', 'eroeffnet_am', 'geschlossen_am', 'schliessgrund'];

    protected $casts = ['eroeffnet_am' => 'date', 'geschlossen_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return HasMany<Treuhandbuchung, $this> */
    public function buchungen(): HasMany
    {
        return $this->hasMany(Treuhandbuchung::class, 'treuhand_konto_id')->orderBy('lfd_nr');
    }

    /** @return HasMany<Treuhandbudget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Treuhandbudget::class, 'treuhand_konto_id');
    }

    /** @return HasMany<TreuhandMonatsabschluss, $this> */
    public function abschluesse(): HasMany
    {
        return $this->hasMany(TreuhandMonatsabschluss::class, 'treuhand_konto_id');
    }

    public function saldo(): float
    {
        // eigene Query statt der (aufsteigend sortierten) Relation — sonst gewinnt die ASC-Sortierung.
        return (float) (Treuhandbuchung::where('treuhand_konto_id', $this->id)->orderByDesc('lfd_nr')->value('saldo_nach') ?? 0.0);
    }

    public function offen(): bool
    {
        return $this->geschlossen_am === null;
    }
}
