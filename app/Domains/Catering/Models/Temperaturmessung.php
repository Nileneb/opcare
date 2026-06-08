<?php

namespace App\Domains\Catering\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Temperaturmessung an einem HACCP-Messpunkt.
 * Abweichungs-Flag + offener Korrekturmaßnahmen-Workflow (VO (EG) 852/2004 Art. 5 Abs. 2 lit. d+e).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $haccp_messpunkt_id
 * @property Carbon $gemessen_am
 * @property float $wert
 * @property bool $abweichung
 * @property string|null $korrekturmassnahme
 * @property int|null $erfasst_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read HaccpMesspunkt $messpunkt
 * @property-read User|null $erfasser
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereAbweichung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereErfasstVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereGemessenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereHaccpMesspunktId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereKorrekturmassnahme($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Temperaturmessung whereWert($value)
 *
 * @mixin \Eloquent
 */
class Temperaturmessung extends BaseModel
{
    protected $table = 'temperaturmessungen';

    protected $fillable = [
        'tenant_id',
        'haccp_messpunkt_id',
        'gemessen_am',
        'wert',
        'abweichung',
        'korrekturmassnahme',
        'erfasst_von',
    ];

    protected $casts = [
        'gemessen_am' => 'datetime',
        'wert' => 'decimal:1',
        'abweichung' => 'boolean',
    ];

    public function messpunkt(): BelongsTo
    {
        return $this->belongsTo(HaccpMesspunkt::class, 'haccp_messpunkt_id');
    }

    public function erfasser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erfasst_von');
    }

    /** Abweichung ohne Korrekturmaßnahme = offener Pflicht-Workflow. */
    public function offen(): bool
    {
        return $this->abweichung && $this->korrekturmassnahme === null;
    }
}
