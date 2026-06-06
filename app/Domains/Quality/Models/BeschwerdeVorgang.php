<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Quality\Enums\VorgangArt;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein protokollierter Vorgang an einer Beschwerde (Notiz, Weiterleitung an einen Bereich, Statuswechsel,
 * Stellungnahme, Maßnahme). Das anonym-Flag hält fest, ob bei einer Weiterleitung der Melder dem Empfänger
 * verborgen wurde — append-only Verlauf.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $beschwerde_id
 * @property VorgangArt $art
 * @property string|null $an_bereich
 * @property bool $anonym
 * @property string|null $text
 * @property int|null $von_user_id
 * @property Carbon|null $created_at
 *
 * @mixin \Eloquent
 */
class BeschwerdeVorgang extends BaseModel
{
    protected $table = 'beschwerde_vorgaenge';

    protected $fillable = ['tenant_id', 'beschwerde_id', 'art', 'an_bereich', 'anonym', 'text', 'von_user_id'];

    protected $casts = [
        'art' => VorgangArt::class,
        'anonym' => 'boolean',
    ];

    public function beschwerde(): BelongsTo
    {
        return $this->belongsTo(Beschwerde::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'von_user_id');
    }
}
