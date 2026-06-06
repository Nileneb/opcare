<?php

namespace App\Domains\Masterdata\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// WHY: Referenzdaten (ICD-10-Katalog) — tenant-übergreifend, daher kein BaseModel/Tenant-Scope.
/**
 * @property int $id
 * @property string $code
 * @property string $bezeichnung
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IcdCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IcdCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IcdCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IcdCode whereBezeichnung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IcdCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IcdCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IcdCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IcdCode whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class IcdCode extends Model
{
    protected $fillable = ['code', 'bezeichnung'];
}
