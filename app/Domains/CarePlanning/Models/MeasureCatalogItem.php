<?php

namespace App\Domains\CarePlanning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// WHY: Referenzdaten (Standard-Pflegemaßnahmen) — tenant-übergreifend, daher kein BaseModel/Tenant-Scope.
/**
 * @property int $id
 * @property string $bezeichnung
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureCatalogItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureCatalogItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureCatalogItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureCatalogItem whereBezeichnung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureCatalogItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureCatalogItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureCatalogItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MeasureCatalogItem extends Model
{
    protected $fillable = ['bezeichnung'];
}
