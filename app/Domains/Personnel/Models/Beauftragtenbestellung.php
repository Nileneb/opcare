<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Bestellung einer Person in eine Beauftragten-Rolle, mit Auffrischungsfrist (Fälligkeits-Ampel) und Abbestellung.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $beauftragten_rolle_id
 * @property int $user_id
 * @property Carbon $bestellt_am
 * @property Carbon|null $gueltig_bis
 * @property string|null $notiz
 * @property Carbon|null $abbestellt_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Beauftragtenrolle $rolle
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereAbbestelltAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereBeauftragtenRolleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereBestelltAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereGueltigBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenbestellung whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Beauftragtenbestellung extends BaseModel
{
    protected $table = 'beauftragten_bestellungen';

    protected $fillable = ['tenant_id', 'beauftragten_rolle_id', 'user_id', 'bestellt_am', 'gueltig_bis', 'notiz', 'abbestellt_am'];

    protected $casts = ['bestellt_am' => 'date', 'gueltig_bis' => 'date', 'abbestellt_am' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rolle(): BelongsTo
    {
        return $this->belongsTo(Beauftragtenrolle::class, 'beauftragten_rolle_id');
    }

    public function aktiv(): bool
    {
        return $this->abbestellt_am === null;
    }

    /** gueltig | faellig | ueberfaellig | unbefristet | abbestellt */
    public function status(): string
    {
        if (! $this->aktiv()) {
            return 'abbestellt';
        }
        if ($this->gueltig_bis === null) {
            return 'unbefristet';
        }
        if ($this->gueltig_bis->isPast()) {
            return 'ueberfaellig';
        }

        return $this->gueltig_bis->lessThanOrEqualTo(today()->addDays(60)) ? 'faellig' : 'gueltig';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'ueberfaellig' => 'red',
            'faellig' => 'amber',
            'abbestellt' => 'gray',
            default => 'green',
        };
    }
}
