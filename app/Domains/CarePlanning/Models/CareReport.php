<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\Shift;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareReport extends BaseModel
{
    use Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'created_by', 'superseded_by',
        'version', 'datum', 'schicht', 'text',
    ];

    // WHY(Track B, At-Rest): Pflegeverlauf ist sensibler Gesundheits-Freitext, nicht SQL-durchsucht → verschlüsselt.
    protected $casts = ['datum' => 'datetime', 'schicht' => Shift::class, 'version' => 'integer', 'text' => 'encrypted'];

    protected $attributes = ['version' => 1];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
