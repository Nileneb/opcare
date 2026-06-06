<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Contracts\BudgetGrenze;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Monatliches Budget je Sachkonto im Hauptbuch (typischerweise Abteilungs-Aufwandskonten): Limit + Warn-Schwelle
 * + optionale harte Sperre. Dasselbe Muster wie das Treuhand-Budget (gemeinsames BudgetGrenze/BudgetStatus) —
 * die Auslastung berechnet der KontoBudgetMonitor aus den Buchungen des Monats.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $konto_id
 * @property numeric $limit_betrag
 * @property int $warn_prozent
 * @property bool $sperre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Konto $konto
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereKontoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereLimitBetrag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereSperre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereWarnProzent($value)
 *
 * @mixin \Eloquent
 */
class Budget extends BaseModel implements BudgetGrenze
{
    protected $table = 'budgets';

    protected $fillable = ['tenant_id', 'konto_id', 'limit_betrag', 'warn_prozent', 'sperre'];

    protected $casts = ['limit_betrag' => 'decimal:2', 'warn_prozent' => 'integer', 'sperre' => 'boolean'];

    public function limitBetrag(): float
    {
        return (float) $this->limit_betrag;
    }

    public function warnProzent(): int
    {
        return $this->warn_prozent;
    }

    public function sperreAktiv(): bool
    {
        return $this->sperre;
    }

    public function konto(): BelongsTo
    {
        return $this->belongsTo(Konto::class);
    }
}
