<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Lagerbewegung (Eingang/Verbrauch/Korrektur) eines Artikels, ggf. mit der erzeugten Buchung verknüpft.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $artikel_id
 * @property string $typ
 * @property numeric $menge
 * @property Carbon $datum
 * @property string|null $notiz
 * @property int|null $buchung_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Artikel $artikel
 * @property-read Buchung|null $buchung
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereBuchungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereMenge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerbewegung whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Lagerbewegung extends BaseModel
{
    protected $table = 'lagerbewegungen';

    protected $fillable = ['tenant_id', 'artikel_id', 'typ', 'menge', 'datum', 'notiz', 'buchung_id'];

    protected $casts = ['datum' => 'date', 'menge' => 'decimal:2'];

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class);
    }

    public function buchung(): BelongsTo
    {
        return $this->belongsTo(Buchung::class);
    }
}
