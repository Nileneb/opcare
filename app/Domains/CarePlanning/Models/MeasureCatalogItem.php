<?php

namespace App\Domains\CarePlanning\Models;

use Illuminate\Database\Eloquent\Model;

// WHY: Referenzdaten (Standard-Pflegemaßnahmen) — tenant-übergreifend, daher kein BaseModel/Tenant-Scope.
class MeasureCatalogItem extends Model
{
    protected $fillable = ['bezeichnung'];
}
