<?php

namespace App\Domains\Facility\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Repräsentative Probenahmestelle einer Trinkwasseranlage (z. B. Austritt Erwärmer, entferntester Punkt je Steigstrang).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $trinkwasseranlage_id
 * @property string $bezeichnung
 * @property string|null $ort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Trinkwasseranlage $anlage
 * @property-read Collection<int, Legionellenbefund> $befunde
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class Probenahmestelle extends BaseModel
{
    protected $table = 'probenahmestellen';

    protected $fillable = [
        'tenant_id', 'trinkwasseranlage_id', 'bezeichnung', 'ort',
    ];

    /** @return BelongsTo<Trinkwasseranlage, $this> */
    public function anlage(): BelongsTo
    {
        return $this->belongsTo(Trinkwasseranlage::class, 'trinkwasseranlage_id');
    }

    /** @return HasMany<Legionellenbefund, $this> */
    public function befunde(): HasMany
    {
        return $this->hasMany(Legionellenbefund::class, 'probenahmestelle_id');
    }
}
