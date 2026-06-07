<?php

namespace App\Domains\Vision\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Foto-Aufnahme eines Regals für die Vision-Bestandserkennung.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $erstellt_von
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, RegalDetection> $detektionen
 * @property-read int|null $detektionen_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme whereErstelltVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegalAufnahme whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class RegalAufnahme extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'regal_aufnahmen';

    protected $fillable = [
        'tenant_id',
        'erstellt_von',
        'notiz',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('foto')->useDisk(config('opcare.media_disk', 'media'));
    }

    /** @return HasMany<RegalDetection, $this> */
    public function detektionen(): HasMany
    {
        return $this->hasMany(RegalDetection::class, 'aufnahme_id');
    }
}
