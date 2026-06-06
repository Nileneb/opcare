<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Enums\EreignisKategorie;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Wesentliches Bewohner-Ereignis (§ 1821 BGB), bei dem die Vertretung ein Beteiligungs-/Informationsrecht hat.
 * Die Kategorie bestimmt über {@see EreignisKategorie::erforderlicheAufgabenkreise()}, welche Vertretungen zu
 * informieren sind; status/informiert_am dokumentiert die Pflichterfüllung des Trägers.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property EreignisKategorie $kategorie
 * @property string $titel
 * @property string|null $beschreibung
 * @property Carbon $datum
 * @property string $status
 * @property Carbon|null $informiert_am
 * @property int|null $erstellt_von_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 * @property-read User|null $ersteller
 *
 * @mixin \Eloquent
 */
class BewohnerEreignis extends BaseModel
{
    protected $table = 'bewohner_ereignisse';

    protected $fillable = ['tenant_id', 'resident_id', 'kategorie', 'titel', 'beschreibung', 'datum', 'status',
        'informiert_am', 'erstellt_von_user_id'];

    protected $casts = [
        'kategorie' => EreignisKategorie::class,
        'datum' => 'date',
        'informiert_am' => 'date',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von_user_id');
    }

    public function offen(): bool
    {
        return $this->status === 'offen';
    }

    /**
     * Vertretungen, die laut Aufgabenkreis bei diesem Ereignis zu informieren sind.
     *
     * @return Collection<int, Custodian>
     */
    public function empfaenger(): Collection
    {
        return $this->resident->custodians
            ->filter(fn (Custodian $c): bool => $c->aktiv() && $c->darfEreignis($this->kategorie))
            ->values();
    }

    public function ampel(): string
    {
        if (! $this->offen()) {
            return 'green';
        }

        return $this->datum->isPast() ? 'red' : 'amber';
    }
}
