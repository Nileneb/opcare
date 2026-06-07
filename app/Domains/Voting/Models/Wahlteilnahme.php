<?php

namespace App\Domains\Voting\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personenbezogene Wählerliste — trägt NUR das Boolean-Häkchen „hat abgestimmt".
 *
 * Kein LogsActivity (bewusst kein BaseModel) — Datensparsamkeit Art. 5(1)(c) DSGVO/DSG-EKD.
 * Kein Zeitstempel der Teilnahme, kein Kanal, kein Gerät.
 *
 * Rechtsgrundlage: Art. 6(1)(c) DSGVO / DSG-EKD analog (gesetzliche Wahlpflicht),
 * Zweckbindung ausschließlich Wahldurchführung. Löschfrist: Amtszeit-Ende + Anfechtungsfrist + 1 Monat.
 *
 * @property-read Abstimmung|null $abstimmung
 * @property-read Resident|null $resident
 * @property-read Tenant|null $tenant
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wahlteilnahme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wahlteilnahme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wahlteilnahme query()
 *
 * @mixin \Eloquent
 */
class Wahlteilnahme extends Model
{
    use BelongsToTenant;

    protected $table = 'wahlteilnahmen';

    // WHY: keine timestamps in der Migration → Eloquent darf nicht versuchen created_at/updated_at zu schreiben.
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'abstimmung_id',
        'user_id',
        'resident_id',
        'hat_abgestimmt',
    ];

    protected $casts = [
        'hat_abgestimmt' => 'boolean',
    ];

    /** @return BelongsTo<Abstimmung, $this> */
    public function abstimmung(): BelongsTo
    {
        return $this->belongsTo(Abstimmung::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
