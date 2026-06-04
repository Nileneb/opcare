<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\ZielErreichung;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Evaluation extends BaseModel
{
    use Versionable;

    protected $fillable = [
        'tenant_id', 'evaluable_type', 'evaluable_id', 'created_by',
        'superseded_by', 'version', 'datum', 'zielerreichung', 'anlass',
    ];

    protected $casts = ['datum' => 'date', 'zielerreichung' => ZielErreichung::class, 'version' => 'integer'];

    protected $attributes = ['version' => 1];

    public function evaluable(): MorphTo
    {
        return $this->morphTo();
    }
}
