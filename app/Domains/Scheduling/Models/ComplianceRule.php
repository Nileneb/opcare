<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Editierbare Arbeitsrecht-Regel je Einrichtung (ArbZG-abgeleitet). Schwellwerte (`params`), Schwere und
 * Aktivierung sind im Regel-Editor anpassbar; `gesetz_url`/`gesetz_zitat` geben Zugriff auf den amtlichen
 * Gesetzestext. Der WorkingHoursAnalyzer wertet ausschließlich die aktiven Regeln dieser Tabelle aus.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $key
 * @property string $paragraph
 * @property string $label
 * @property ViolationSeverity $severity
 * @property array<array-key, mixed> $params
 * @property string $gesetz_url
 * @property string $gesetz_zitat
 * @property string|null $note
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereGesetzUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereGesetzZitat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereParagraph($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereParams($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceRule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ComplianceRule extends BaseModel
{
    protected $fillable = ['tenant_id', 'key', 'paragraph', 'label', 'severity', 'params', 'gesetz_url', 'gesetz_zitat', 'note', 'aktiv'];

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
