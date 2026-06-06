<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Medication\Enums\StockTransactionType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $med_stock_id
 * @property int|null $administration_id
 * @property StockTransactionType $typ
 * @property numeric $menge
 * @property Carbon $gebucht_am
 * @property int|null $gebucht_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read MedStock $stock
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereAdministrationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereGebuchtAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereGebuchtVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereMedStockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereMenge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedStockTransaction whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MedStockTransaction extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'med_stock_id', 'administration_id',
        'typ', 'menge', 'gebucht_am', 'gebucht_von',
    ];

    protected $casts = [
        'typ' => StockTransactionType::class,
        'menge' => 'decimal:3',
        'gebucht_am' => 'datetime',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(MedStock::class, 'med_stock_id');
    }
}
