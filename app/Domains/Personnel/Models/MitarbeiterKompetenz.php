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
 * Eine von einer Mitarbeiter:in erworbene Kompetenz. Aus erworben_am + Gültigkeit ergibt sich die Fälligkeit
 * und damit der Ampel-Status — dasselbe Nachweis-mit-Frist-Muster wie bei den Arbeitsschutz-Nachweisen.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $kompetenz_id
 * @property Carbon $erworben_am
 * @property Carbon|null $gueltig_bis
 * @property int|null $verifiziert_von
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Kompetenz $kompetenz
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereErworbenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereGueltigBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereKompetenzId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MitarbeiterKompetenz whereVerifiziertVon($value)
 *
 * @mixin \Eloquent
 */
class MitarbeiterKompetenz extends BaseModel
{
    protected $table = 'mitarbeiter_kompetenzen';

    protected $fillable = ['tenant_id', 'user_id', 'kompetenz_id', 'erworben_am', 'gueltig_bis', 'verifiziert_von', 'notiz'];

    protected $casts = ['erworben_am' => 'date', 'gueltig_bis' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kompetenz(): BelongsTo
    {
        return $this->belongsTo(Kompetenz::class);
    }

    /** gueltig | faellig | abgelaufen | unbefristet */
    public function status(): string
    {
        if ($this->gueltig_bis === null) {
            return 'unbefristet';
        }
        if ($this->gueltig_bis->isPast()) {
            return 'abgelaufen';
        }

        return $this->gueltig_bis->lessThanOrEqualTo(today()->addDays(60)) ? 'faellig' : 'gueltig';
    }

    public function aktiv(): bool
    {
        return $this->status() !== 'abgelaufen';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'abgelaufen' => 'red',
            'faellig' => 'amber',
            default => 'green',
        };
    }
}
