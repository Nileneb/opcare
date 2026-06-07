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
 * Einzelne Objekterkennung innerhalb einer RegalAufnahme.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $aufnahme_id
 * @property string $label
 * @property numeric $confidence
 * @property int|null $artikel_id
 * @property numeric|null $menge_vorschlag
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Artikel|null $artikel
 * @property-read RegalAufnahme $aufnahme
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereAufnahmeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereConfidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereMengeVorschlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalDetection whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class RegalDetection extends BaseModel
{
    protected $table = 'regal_detektionen';

    protected $fillable = [
        'tenant_id',
        'aufnahme_id',
        'label',
        'confidence',
        'artikel_id',
        'menge_vorschlag',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'menge_vorschlag' => 'decimal:2',
    ];

    /** @return BelongsTo<RegalAufnahme, $this> */
    public function aufnahme(): BelongsTo
    {
        return $this->belongsTo(RegalAufnahme::class, 'aufnahme_id');
    }

    /** @return BelongsTo<Artikel, $this> */
    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class);
    }
}
