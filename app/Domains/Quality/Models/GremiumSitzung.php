<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sitzung eines Gremiums mit Protokoll und Beschlüssen (Mitwirkungs-/QM-Nachweis).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $gremium_id
 * @property Carbon $datum
 * @property string $thema
 * @property string|null $protokoll
 * @property string|null $beschluesse
 * @property int|null $teilnehmerzahl
 * @property int|null $protokoll_von
 *
 * @mixin \Eloquent
 */
class GremiumSitzung extends BaseModel
{
    protected $table = 'gremium_sitzungen';

    protected $fillable = ['tenant_id', 'gremium_id', 'datum', 'thema', 'protokoll', 'beschluesse',
        'teilnehmerzahl', 'protokoll_von'];

    protected $casts = ['datum' => 'date'];

    public function gremium(): BelongsTo
    {
        return $this->belongsTo(Gremium::class);
    }

    public function protokollant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'protokoll_von');
    }
}
