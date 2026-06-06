<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\GremiumFunktion;
use App\Domains\Quality\Enums\MitgliedArt;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mitglied eines Gremiums mit Funktion (Vorsitz/Stellvertretung/Schriftführung/Mitglied) und Art
 * (Bewohner/Angehörige/Mitarbeiter/extern/Betriebsarzt/Sifa).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $gremium_id
 * @property string $name
 * @property MitgliedArt $art
 * @property GremiumFunktion $funktion
 * @property int|null $user_id
 * @property int|null $resident_id
 * @property Carbon|null $bis
 *
 * @mixin \Eloquent
 */
class GremiumMitglied extends BaseModel
{
    protected $table = 'gremium_mitglieder';

    protected $fillable = ['tenant_id', 'gremium_id', 'name', 'art', 'funktion', 'user_id', 'resident_id', 'bis'];

    protected $casts = [
        'art' => MitgliedArt::class,
        'funktion' => GremiumFunktion::class,
        'bis' => 'date',
    ];

    public function gremium(): BelongsTo
    {
        return $this->belongsTo(Gremium::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
