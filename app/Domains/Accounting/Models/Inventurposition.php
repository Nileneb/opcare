<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Zählposition einer {@see Inventur} je Artikel: Soll-Menge (Snapshot bei Anlage), erfasste Ist-Menge und
 * der Bewertungsschnitt für den Differenzwert. Solange `ist_menge` null ist, gilt die Position als nicht gezählt
 * und wird beim Abschluss NICHT als 0-Differenz gebucht, sondern transparent ausgewiesen.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $inventur_id
 * @property int $artikel_id
 * @property numeric $soll_menge
 * @property numeric|null $ist_menge
 * @property numeric $einstandspreis_schnitt
 * @property int|null $gezaehlt_von
 * @property Carbon|null $gezaehlt_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Artikel $artikel
 * @property-read Inventur $inventur
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereEinstandspreisSchnitt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereGezaehltAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereGezaehltVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereInventurId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereIstMenge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereSollMenge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventurposition whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Inventurposition extends BaseModel
{
    protected $table = 'inventur_positionen';

    protected $fillable = ['tenant_id', 'inventur_id', 'artikel_id', 'soll_menge', 'ist_menge',
        'einstandspreis_schnitt', 'gezaehlt_von', 'gezaehlt_am'];

    protected $casts = [
        'soll_menge' => 'decimal:2',
        'ist_menge' => 'decimal:2',
        'einstandspreis_schnitt' => 'decimal:4',
        'gezaehlt_am' => 'datetime',
    ];

    public function inventur(): BelongsTo
    {
        return $this->belongsTo(Inventur::class);
    }

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class);
    }

    public function gezaehlt(): bool
    {
        return $this->ist_menge !== null;
    }

    public function differenzMenge(): float
    {
        return (float) ($this->ist_menge ?? 0) - (float) $this->soll_menge;
    }

    public function differenzWert(): float
    {
        return round($this->differenzMenge() * (float) $this->einstandspreis_schnitt, 2);
    }
}
