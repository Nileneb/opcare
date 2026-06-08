<?php

namespace App\Domains\Arbeitsschutz\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Freiwillig gesetzter persönlicher Belastungswert (0-10) eines Mitarbeitenden.
 *
 * Bewusst KEIN BaseModel / LogsActivity — privat wie Personnel\Energielevel.
 * Kein Vorgesetzten-Einblick in den Roh-Verlauf (§ 26 BDSG: Freiwilligkeit;
 * § 87 Abs. 1 Nr. 6 BetrVG: Mitbestimmungspflicht für technische Überwachungseinrichtungen).
 *
 * Nur der/die Mitarbeitende selbst setzt und sieht den eigenen Wert.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $wert
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersoenlicheBelastung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersoenlicheBelastung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersoenlicheBelastung query()
 *
 * @mixin \Eloquent
 */
class PersoenlicheBelastung extends Model
{
    use BelongsToTenant;

    protected $table = 'persoenliche_belastungen';

    protected $fillable = ['tenant_id', 'user_id', 'wert'];

    protected $casts = ['wert' => 'integer'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
