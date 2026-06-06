<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Personnel\Enums\NachweisTyp;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein erbrachter Arbeitsschutz-Nachweis. Aus Datum + Intervall ergibt sich die Fälligkeit und damit der
 * Ampel-Status (gültig / fällig / überfällig) — anlassbezogene Nachweise (Intervall null) haben keine Frist.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property NachweisTyp $typ
 * @property Carbon $datum
 * @property int|null $intervall_monate
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schutznachweis whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Schutznachweis extends BaseModel
{
    protected $table = 'schutznachweise';

    protected $fillable = ['tenant_id', 'user_id', 'typ', 'datum', 'intervall_monate', 'notiz'];

    protected $casts = ['typ' => NachweisTyp::class, 'datum' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intervall(): ?int
    {
        return $this->intervall_monate ?? $this->typ->intervallMonate();
    }

    public function faelligAm(): ?Carbon
    {
        $intervall = $this->intervall();

        return $intervall === null ? null : $this->datum->copy()->addMonths($intervall);
    }

    /** gueltig | faellig | ueberfaellig | anlassbezogen */
    public function status(): string
    {
        $faellig = $this->faelligAm();
        if ($faellig === null) {
            return 'anlassbezogen';
        }
        if ($faellig->isPast()) {
            return 'ueberfaellig';
        }

        return $faellig->lessThanOrEqualTo(today()->addDays(30)) ? 'faellig' : 'gueltig';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'ueberfaellig' => 'red',
            'faellig' => 'amber',
            default => 'green',
        };
    }
}
