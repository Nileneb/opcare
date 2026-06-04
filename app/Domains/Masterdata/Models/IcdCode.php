<?php

namespace App\Domains\Masterdata\Models;

use Illuminate\Database\Eloquent\Model;

// WHY: Referenzdaten (ICD-10-Katalog) — tenant-übergreifend, daher kein BaseModel/Tenant-Scope.
class IcdCode extends Model
{
    protected $fillable = ['code', 'bezeichnung'];
}
