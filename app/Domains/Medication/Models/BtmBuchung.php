<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Medication\Enums\BtmVorgang;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Append-only Buchung im BtM-Konto (§ 13 BtMVV). Es gibt KEIN updated_at: Korrekturen erfolgen über eine
 * neue Buchung vom Typ Korrektur mit Bezug auf die Fehlbuchung — die ursprüngliche Zeile bleibt unverändert.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $btm_konto_id
 * @property int $lfd_nr
 * @property BtmVorgang $vorgang
 * @property Carbon $datum
 * @property numeric $menge
 * @property numeric $bestand_nach
 * @property string|null $lieferant
 * @property string|null $empfaenger
 * @property string|null $arzt_name
 * @property int|null $durchgefuehrt_von
 * @property string|null $zeuge_1
 * @property string|null $zeuge_2
 * @property string|null $vernichtungsmethode
 * @property int|null $korrigiert_buchung_id
 * @property string|null $grund
 * @property Carbon|null $created_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $durchfuehrer
 * @property-read BtmKonto $konto
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereArztName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereBestandNach($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereBtmKontoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereDurchgefuehrtVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereEmpfaenger($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereGrund($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereKorrigiertBuchungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereLfdNr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereLieferant($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereMenge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereVernichtungsmethode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereVorgang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereZeuge1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmBuchung whereZeuge2($value)
 *
 * @mixin \Eloquent
 */
class BtmBuchung extends BaseModel
{
    protected $table = 'btm_buchungen';

    public const UPDATED_AT = null; // append-only — Buchungen werden nie geändert

    protected $fillable = ['tenant_id', 'btm_konto_id', 'lfd_nr', 'vorgang', 'datum', 'menge', 'bestand_nach',
        'lieferant', 'empfaenger', 'arzt_name', 'durchgefuehrt_von', 'zeuge_1', 'zeuge_2', 'vernichtungsmethode', 'korrigiert_buchung_id', 'grund'];

    protected $casts = ['vorgang' => BtmVorgang::class, 'datum' => 'date', 'menge' => 'decimal:3', 'bestand_nach' => 'decimal:3'];

    public function konto(): BelongsTo
    {
        return $this->belongsTo(BtmKonto::class, 'btm_konto_id');
    }

    public function durchfuehrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'durchgefuehrt_von');
    }
}
