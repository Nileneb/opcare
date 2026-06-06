<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Editierbare ergonomische Schichtplan-Regel je Einrichtung (BAuA/BGHM/DGAUM-abgeleitet). Der
 * ScheduleQualityAnalyzer wertet ausschließlich die aktiven Regeln dieser Tabelle aus.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $key
 * @property string $label
 * @property string $kategorie
 * @property ViolationSeverity $severity
 * @property array<array-key, mixed> $params
 * @property string $quelle
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereKategorie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereParams($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereQuelle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleQualityRule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ScheduleQualityRule extends BaseModel
{
    protected $fillable = ['tenant_id', 'key', 'label', 'kategorie', 'severity', 'params', 'quelle', 'aktiv'];

    protected $casts = [
        'severity' => ViolationSeverity::class,
        'params' => 'array',
        'aktiv' => 'boolean',
    ];

    public function param(string $name, int|float $default = 0): int|float
    {
        return $this->params[$name] ?? $default;
    }
}
