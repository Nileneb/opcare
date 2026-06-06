<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Medication\Database\Factories\MedProductFactory;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $trade_form_id
 * @property string $name
 * @property string|null $wirkstoff
 * @property string|null $staerke
 * @property string|null $atc_code
 * @property string|null $pzn
 * @property bool $btm
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read TradeForm|null $tradeForm
 *
 * @method static \App\Domains\Medication\Database\Factories\MedProductFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereAtcCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereBtm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct wherePzn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereStaerke($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereTradeFormId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedProduct whereWirkstoff($value)
 *
 * @mixin \Eloquent
 */
class MedProduct extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'trade_form_id', 'name', 'wirkstoff', 'staerke', 'atc_code', 'pzn', 'btm'];

    protected $casts = ['btm' => 'boolean'];

    public function tradeForm(): BelongsTo
    {
        return $this->belongsTo(TradeForm::class);
    }

    protected static function newFactory(): MedProductFactory
    {
        return MedProductFactory::new();
    }
}
