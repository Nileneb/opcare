<?php

namespace App\Domains\Facility\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Dokumentierte Einweisung einer Person in ein Medizinprodukt (§ 4 / § 11 MPBetreibV) — nur eingewiesene
 * Personen dürfen das Produkt benutzen. Verknüpft die Bestands-/Buch-Pflicht mit dem Personal.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $medizinprodukt_id
 * @property int $user_id
 * @property Carbon $eingewiesen_am
 * @property string|null $eingewiesen_durch
 * @property string $art
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read Medizinprodukt $medizinprodukt
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
class MedizinproduktEinweisung extends BaseModel
{
    protected $table = 'medizinprodukt_einweisungen';

    protected $fillable = ['tenant_id', 'medizinprodukt_id', 'user_id', 'eingewiesen_am', 'eingewiesen_durch', 'art', 'notiz'];

    protected $casts = ['eingewiesen_am' => 'date'];

    public function medizinprodukt(): BelongsTo
    {
        return $this->belongsTo(Medizinprodukt::class, 'medizinprodukt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
