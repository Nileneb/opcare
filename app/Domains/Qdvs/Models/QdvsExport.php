<?php

namespace App\Domains\Qdvs\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $stichtag
 * @property string $spec
 * @property string $status
 * @property int $bewohner_count
 * @property string|null $pfad
 * @property array<array-key, mixed>|null $fehler
 * @property int|null $erstellt_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property array<array-key, mixed>|null $regel_coverage
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereBewohnerCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereErstelltVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereFehler($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport wherePfad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereRegelCoverage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereSpec($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereStichtag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QdvsExport whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class QdvsExport extends BaseModel
{
    protected $fillable = ['tenant_id', 'stichtag', 'spec', 'status', 'bewohner_count', 'pfad', 'fehler', 'regel_coverage', 'erstellt_von'];

    protected $casts = ['stichtag' => 'date', 'fehler' => 'array', 'regel_coverage' => 'array', 'bewohner_count' => 'integer'];
}
