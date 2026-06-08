<?php

namespace App\Domains\Catering\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only Erledigungs-Beleg für eine Reinigungsaufgabe.
 * Norm-Anker: VO (EG) 852/2004 Anhang II (Dokumentationspflicht).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $reinigungsaufgabe_id
 * @property Carbon $erledigt_am
 * @property int|null $erledigt_von
 * @property string|null $bemerkung
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Reinigungsaufgabe $aufgabe
 * @property-read User|null $erlediger
 *
 * @mixin \Eloquent
 */
class Reinigungsnachweis extends BaseModel
{
    protected $table = 'reinigungsnachweise';

    protected $fillable = [
        'tenant_id', 'reinigungsaufgabe_id', 'erledigt_am', 'erledigt_von', 'bemerkung',
    ];

    protected $casts = [
        'erledigt_am' => 'date',
    ];

    /** @return BelongsTo<Reinigungsaufgabe, $this> */
    public function aufgabe(): BelongsTo
    {
        return $this->belongsTo(Reinigungsaufgabe::class, 'reinigungsaufgabe_id');
    }

    /** @return BelongsTo<User, $this> */
    public function erlediger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erledigt_von');
    }
}
