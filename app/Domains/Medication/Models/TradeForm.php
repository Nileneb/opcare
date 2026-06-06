<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Medication\Database\Factories\TradeFormFactory;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $einheit
 * @property bool $teilbar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, MedProduct> $products
 * @property-read int|null $products_count
 * @property-read Tenant $tenant
 *
 * @method static \App\Domains\Medication\Database\Factories\TradeFormFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm whereEinheit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm whereTeilbar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeForm whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TradeForm extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'einheit', 'teilbar'];

    protected $casts = ['teilbar' => 'boolean'];

    public function products(): HasMany
    {
        return $this->hasMany(MedProduct::class);
    }

    protected static function newFactory(): TradeFormFactory
    {
        return TradeFormFactory::new();
    }
}
