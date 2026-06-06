<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Anpassbare Werte der Personalbemessung je Einrichtung (§ 113c SGB XI). Siehe Migration für die Bedeutung.
 *
 * @property int $id
 * @property int $tenant_id
 * @property float $wochenstunden
 * @property float $fachkraftquote_min
 * @property int $nachtdienst_je_fachkraft
 * @property float $paw_multiplikator
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig whereFachkraftquoteMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig whereNachtdienstJeFachkraft($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig wherePawMultiplikator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffingConfig whereWochenstunden($value)
 *
 * @mixin \Eloquent
 */
class StaffingConfig extends BaseModel
{
    protected $fillable = ['tenant_id', 'wochenstunden', 'fachkraftquote_min', 'nachtdienst_je_fachkraft', 'paw_multiplikator'];

    // WHY: firstOrCreate(['tenant_id'=>…]) lädt die DB-Defaults nicht ins frische Model → ohne diese
    // In-Memory-Defaults wären die Werte null (× null = 0 im Betreuungsschlüssel).
    protected $attributes = [
        'wochenstunden' => 38.5,
        'fachkraftquote_min' => 0.5,
        'nachtdienst_je_fachkraft' => 50,
        'paw_multiplikator' => 1.0,
    ];

    protected $casts = [
        'wochenstunden' => 'float',
        'fachkraftquote_min' => 'float',
        'nachtdienst_je_fachkraft' => 'integer',
        'paw_multiplikator' => 'float',
    ];
}
