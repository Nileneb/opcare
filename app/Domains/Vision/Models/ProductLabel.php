<?php

namespace App\Domains\Vision\Models;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Verknüpft ein YOLO-Label mit einem Lagerartikel + Umrechnungsfaktor.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $yolo_label
 * @property int $artikel_id
 * @property numeric $multiplier
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Artikel $artikel
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel whereArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel whereMultiplier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLabel whereYoloLabel($value)
 *
 * @mixin \Eloquent
 */
class ProductLabel extends BaseModel
{
    protected $table = 'product_labels';

    protected $fillable = [
        'tenant_id',
        'yolo_label',
        'artikel_id',
        'multiplier',
    ];

    protected $casts = [
        'multiplier' => 'decimal:2',
    ];

    /** @return BelongsTo<Artikel, $this> */
    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class);
    }

    public function mengeFuer(int $count): float
    {
        return $count * (float) $this->multiplier;
    }
}
