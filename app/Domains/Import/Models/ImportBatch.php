<?php

namespace App\Domains\Import\Models;

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
 * Import-Batch: Gruppe von Importzeilen aus einer Quelldatei.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $dateiname
 * @property string $anfangsbestand_modus
 * @property array<array-key, mixed>|null $mapping
 * @property string $status
 * @property int|null $erstellt_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Tenant $tenant
 * @property-read Collection<int, ImportZeile> $zeilen
 * @property-read int|null $zeilen_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereAnfangsbestandModus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereDateiname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereErstelltVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereMapping($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportBatch whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ImportBatch extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'import_batches';

    protected $fillable = [
        'tenant_id',
        'dateiname',
        'anfangsbestand_modus',
        'mapping',
        'status',
        'erstellt_von',
    ];

    protected $casts = [
        'mapping' => 'array',
    ];

    /** @return HasMany<ImportZeile, $this> */
    public function zeilen(): HasMany
    {
        return $this->hasMany(ImportZeile::class, 'batch_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('quelle')->useDisk(config('opcare.media_disk', 'media'));
    }
}
