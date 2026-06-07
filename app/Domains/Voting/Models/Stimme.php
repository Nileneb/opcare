<?php

namespace App\Domains\Voting\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Die anonyme oder namentliche Stimme. Bewusst kein BaseModel (kein LogsActivity).
 *
 * ANONYMITÄTS-INVARIANTEN (DSGVO ErwG 26 / DSG-EKD):
 *  - UUID-PK: verhindert Reihenfolge-Korrelation mit der n-ten Wahlteilnahme.
 *  - $timestamps = false: kein created_at/updated_at an der Stimme — ein Zeitstempel
 *    würde den Personenbezug wiederherstellen und die Anonymität aufheben.
 *  - waehler_*-Felder nur bei modus=Namentlich befüllen; bei Geheim NULL lassen.
 *  - beleg_token: random 128-bit hex, nie gegen Identität gespeichert (Receipt-Freeness-Abschwächung).
 *
 * @property string $id UUID-PK (kein Auto-Increment — bewusste Anonymitäts-Entscheidung)
 * @property int $tenant_id
 * @property int $abstimmung_id
 * @property int|null $option_id
 * @property string $beleg_token
 * @property int|null $waehler_user_id
 * @property int|null $waehler_resident_id
 * @property-read Abstimmung|null $abstimmung
 * @property-read AbstimmungOption|null $option
 * @property-read Tenant|null $tenant
 * @property-read Resident|null $waehlerResident
 * @property-read User|null $waehlerUser
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stimme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stimme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stimme query()
 *
 * @mixin \Eloquent
 */
class Stimme extends Model
{
    use BelongsToTenant;

    protected $table = 'stimmen';

    // WHY: UUID als PK — kein Auto-Increment verhindert Sequenz-Rückverfolgung (DSGVO ErwG 26).
    public $incrementing = false;

    protected $keyType = 'string';

    // WHY: Keine Timestamps — created_at wäre Zeitstempel an der anonymen Stimme
    // und würde durch Reihenfolge-Korrelation Personenbezug wiederherstellen.
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'abstimmung_id',
        'option_id',
        'beleg_token',
        'waehler_user_id',
        'waehler_resident_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Abstimmung, $this> */
    public function abstimmung(): BelongsTo
    {
        return $this->belongsTo(Abstimmung::class);
    }

    /** @return BelongsTo<AbstimmungOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(AbstimmungOption::class, 'option_id');
    }

    /** @return BelongsTo<User, $this> */
    public function waehlerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waehler_user_id');
    }

    /** @return BelongsTo<Resident, $this> */
    public function waehlerResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'waehler_resident_id');
    }
}
