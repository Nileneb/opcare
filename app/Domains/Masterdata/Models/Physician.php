<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Physician extends BaseModel
{
    protected $fillable = ['tenant_id', 'name', 'fachrichtung', 'kontakt'];

    public function residents(): BelongsToMany
    {
        return $this->belongsToMany(Resident::class, 'resident_physician');
    }
}
