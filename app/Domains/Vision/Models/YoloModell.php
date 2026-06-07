<?php

namespace App\Domains\Vision\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Trainiertes YOLO-Modell eines Tenants.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $model_path
 * @property string $version
 * @property bool $aktiv
 * @property array<array-key, mixed>|null $class_names
 * @property array<array-key, mixed>|null $metrics
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static Builder<static>|YoloModell newModelQuery()
 * @method static Builder<static>|YoloModell newQuery()
 * @method static Builder<static>|YoloModell query()
 * @method static Builder<static>|YoloModell whereAktiv($value)
 * @method static Builder<static>|YoloModell whereClassNames($value)
 * @method static Builder<static>|YoloModell whereCreatedAt($value)
 * @method static Builder<static>|YoloModell whereId($value)
 * @method static Builder<static>|YoloModell whereMetrics($value)
 * @method static Builder<static>|YoloModell whereModelPath($value)
 * @method static Builder<static>|YoloModell whereTenantId($value)
 * @method static Builder<static>|YoloModell whereUpdatedAt($value)
 * @method static Builder<static>|YoloModell whereVersion($value)
 *
 * @mixin \Eloquent
 */
class YoloModell extends BaseModel
{
    protected $table = 'yolo_modelle';

    protected $fillable = [
        'tenant_id',
        'model_path',
        'version',
        'aktiv',
        'class_names',
        'metrics',
    ];

    protected $casts = [
        'class_names' => 'array',
        'metrics' => 'array',
        'aktiv' => 'boolean',
    ];

    /** @return Builder<YoloModell> */
    public static function aktivesFuer(int $tenantId): Builder
    {
        return static::where('tenant_id', $tenantId)->where('aktiv', true);
    }
}
