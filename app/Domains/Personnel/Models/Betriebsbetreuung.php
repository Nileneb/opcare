<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Personnel\Enums\BetriebsbetreuungArt;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Stammdaten der betriebsärztlichen und sicherheitstechnischen Betreuung (ASiG §§ 2/5/6, DGUV V2):
 * Betriebsarzt bzw. Fachkraft für Arbeitssicherheit, intern/extern, jährliche Einsatzzeit und
 * Begehungsintervall → Begehungs-Ampel (Nachweis-mit-Frist).
 *
 * @property int $id
 * @property int $tenant_id
 * @property BetriebsbetreuungArt $art
 * @property string $name
 * @property string|null $firma
 * @property bool $extern
 * @property string|null $telefon
 * @property string|null $email
 * @property Carbon|null $bestellt_am
 * @property Carbon|null $vertrag_bis
 * @property int|null $einsatzzeit_stunden
 * @property Carbon|null $letzte_begehung
 * @property int|null $begehung_intervall_monate
 * @property string|null $notiz
 *
 * @mixin \Eloquent
 */
class Betriebsbetreuung extends BaseModel
{
    protected $table = 'betriebsbetreuungen';

    protected $fillable = ['tenant_id', 'art', 'name', 'firma', 'extern', 'telefon', 'email', 'bestellt_am',
        'vertrag_bis', 'einsatzzeit_stunden', 'letzte_begehung', 'begehung_intervall_monate', 'notiz'];

    protected $casts = [
        'art' => BetriebsbetreuungArt::class,
        'extern' => 'boolean',
        'bestellt_am' => 'date',
        'vertrag_bis' => 'date',
        'letzte_begehung' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function naechsteBegehung(): ?Carbon
    {
        if ($this->letzte_begehung === null || $this->begehung_intervall_monate === null) {
            return null;
        }

        return $this->letzte_begehung->copy()->addMonths($this->begehung_intervall_monate);
    }

    /** ueberfaellig | faellig | offen | aktuell — abhängig von Begehungs- und Vertragsfrist. */
    public function status(): string
    {
        if ($this->vertrag_bis !== null && $this->vertrag_bis->isPast()) {
            return 'ueberfaellig';
        }
        $naechste = $this->naechsteBegehung();
        if ($naechste === null) {
            // Pflicht-Betreuung ohne dokumentierte Begehung → sichtbar offen, nicht „aktuell".
            return $this->begehung_intervall_monate !== null ? 'offen' : 'aktuell';
        }
        if ($naechste->isPast()) {
            return 'ueberfaellig';
        }

        return $naechste->lessThanOrEqualTo(today()->addDays(30)) ? 'faellig' : 'aktuell';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'ueberfaellig' => 'red',
            'faellig', 'offen' => 'amber',
            default => 'green',
        };
    }
}
