<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\BarbetragKategorie;
use App\Domains\Accounting\Enums\TreuhandVorgang;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only Einzelbuchung eines Treuhandkontos (HeimsicherungsV § 17 — prüfungsfähige Aufzeichnung je
 * Bewohner, Einzelbelegpflicht). Es gibt KEIN updated_at: Korrekturen erfolgen über eine neue Buchung vom
 * Typ Korrektur mit Bezug auf die Fehlbuchung — die ursprüngliche Zeile bleibt unverändert.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $treuhand_konto_id
 * @property int $lfd_nr
 * @property TreuhandVorgang $vorgang
 * @property Carbon $datum
 * @property numeric $betrag
 * @property numeric $saldo_nach
 * @property BarbetragKategorie|null $kategorie
 * @property string $zweck
 * @property string|null $beleg_nr
 * @property int|null $erfasst_von
 * @property int|null $korrigiert_buchung_id
 * @property string|null $grund
 * @property Carbon|null $created_at
 * @property-read User|null $erfasser
 * @property-read Treuhandkonto $konto
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class Treuhandbuchung extends BaseModel
{
    protected $table = 'treuhand_buchungen';

    public const UPDATED_AT = null; // append-only — Buchungen werden nie geändert

    protected $fillable = ['tenant_id', 'treuhand_konto_id', 'lfd_nr', 'vorgang', 'datum', 'betrag', 'saldo_nach',
        'kategorie', 'zweck', 'beleg_nr', 'erfasst_von', 'korrigiert_buchung_id', 'grund'];

    protected $casts = ['vorgang' => TreuhandVorgang::class, 'kategorie' => BarbetragKategorie::class,
        'datum' => 'date', 'betrag' => 'decimal:2', 'saldo_nach' => 'decimal:2'];

    public function konto(): BelongsTo
    {
        return $this->belongsTo(Treuhandkonto::class, 'treuhand_konto_id');
    }

    public function erfasser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erfasst_von');
    }
}
