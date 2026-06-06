<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\KontoTyp;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Sachkonto der doppelten Buchführung. `saldo()` liefert den Saldo in der natürlichen Richtung der Kontoart
 * (Aktiv/Aufwand: Soll − Haben; Passiv/Ertrag: Haben − Soll). Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nummer
 * @property string $name
 * @property KontoTyp $typ
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Buchung> $habenBuchungen
 * @property-read int|null $haben_buchungen_count
 * @property-read Collection<int, Buchung> $sollBuchungen
 * @property-read int|null $soll_buchungen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto whereNummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konto whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Konto extends BaseModel
{
    protected $table = 'konten';

    protected $fillable = ['tenant_id', 'nummer', 'name', 'typ'];

    protected $casts = ['typ' => KontoTyp::class];

    public function sollBuchungen(): HasMany
    {
        return $this->hasMany(Buchung::class, 'soll_konto_id');
    }

    public function habenBuchungen(): HasMany
    {
        return $this->hasMany(Buchung::class, 'haben_konto_id');
    }

    public function saldo(): float
    {
        $soll = (float) $this->sollBuchungen()->sum('betrag');
        $haben = (float) $this->habenBuchungen()->sum('betrag');

        return round($this->typ->sollSeite() ? $soll - $haben : $haben - $soll, 2);
    }
}
