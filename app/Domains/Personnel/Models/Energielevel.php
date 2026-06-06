<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Personnel\Enums\Energiestufe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aktuelles, freiwillig gesetztes Energie-Level eines Mitarbeitenden (§ 26 BDSG: Freiwilligkeit; § 87 Abs. 1
 * Nr. 6 BetrVG: mitbestimmungspflichtig). Pro Mitarbeitendem existiert GENAU EINE Zeile, die beim Setzen
 * überschrieben wird — bewusst KEIN Verlaufstracking und KEIN Activity-Log (deshalb nicht `BaseModel`).
 *
 * Angezeigt wird ausschließlich der aggregierte Hausschnitt, nie ein personenbezogener Wert.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property Energiestufe $stufe
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel whereStufe($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Energielevel whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Energielevel extends Model
{
    use BelongsToTenant;

    protected $table = 'energielevels';

    protected $fillable = ['tenant_id', 'user_id', 'stufe'];

    protected $casts = ['stufe' => Energiestufe::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
