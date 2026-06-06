<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int $med_product_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read MedProduct $medProduct
 * @property-read Resident $resident
 * @property-read Collection<int, MedStock> $stocks
 * @property-read int|null $stocks_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory whereMedProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedInventory whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MedInventory extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'med_product_id'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function medProduct(): BelongsTo
    {
        return $this->belongsTo(MedProduct::class);
    }

    /** @return HasMany<MedStock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(MedStock::class);
    }
}
