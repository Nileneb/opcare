<?php

namespace App\Domains\Arbeitsschutz\Models;

use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Selbst-initiierte Überlastungsmeldung eines Mitarbeitenden an die Leitung (Mode C).
 *
 * Ausschließlich per explizitem Knopfdruck der betroffenen Person — kein Auto-Monitoring.
 * Named (user_id sichtbar für Leitung) ist datenschutzrechtlich zulässig, da selbst-initiiert
 * und durch Mitarbeitenden-Beschluss freigeschaltet (§ 87 BetrVG-Analogie / Art. 6(1)(a) DSGVO).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $wert
 * @property string|null $notiz
 * @property Carbon $gemeldet_am
 * @property int|null $quittiert_von
 * @property Carbon|null $quittiert_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $quittierer
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SelbstmeldungUeberlastung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SelbstmeldungUeberlastung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SelbstmeldungUeberlastung query()
 *
 * @mixin \Eloquent
 */
class SelbstmeldungUeberlastung extends BaseModel
{
    protected $table = 'selbstmeldungen_ueberlastung';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'wert',
        'notiz',
        'gemeldet_am',
        'quittiert_von',
        'quittiert_am',
    ];

    protected $casts = [
        'wert' => 'integer',
        'gemeldet_am' => 'date',
        'quittiert_am' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function quittierer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quittiert_von');
    }

    public function istOffen(): bool
    {
        return $this->quittiert_am === null;
    }
}
