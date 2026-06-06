<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Medication\Enums\StockStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $med_inventory_id
 * @property numeric $menge_initial
 * @property numeric $menge_aktuell
 * @property string $einheit
 * @property string|null $charge
 * @property Carbon $eingang_am
 * @property Carbon|null $geoeffnet_am
 * @property Carbon|null $verfall_am
 * @property StockStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read MedInventory $inventory
 * @property-read Tenant $tenant
 * @property-read Collection<int, MedStockTransaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereCharge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereEingangAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereEinheit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereGeoeffnetAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereMedInventoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereMengeAktuell($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereMengeInitial($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStock whereVerfallAm($value)
 *
 * @mixin \Eloquent
 */
class MedStock extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'med_inventory_id', 'menge_initial', 'menge_aktuell',
        'einheit', 'charge', 'eingang_am', 'geoeffnet_am', 'verfall_am', 'status',
    ];

    protected $casts = [
        'menge_initial' => 'decimal:3',
        'menge_aktuell' => 'decimal:3',
        'eingang_am' => 'date',
        'geoeffnet_am' => 'date',
        'verfall_am' => 'date',
        'status' => StockStatus::class,
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(MedInventory::class, 'med_inventory_id');
    }

    /** @return HasMany<MedStockTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(MedStockTransaction::class);
    }
}
