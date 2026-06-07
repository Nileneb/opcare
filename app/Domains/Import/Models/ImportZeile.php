<?php

namespace App\Domains\Import\Models;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Import\Enums\ImportAktion;
use App\Domains\Import\Enums\ImportZeileStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine einzelne Zeile innerhalb eines Import-Batches.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $batch_id
 * @property array<array-key, mixed>|null $roh
 * @property string $ziel_typ
 * @property string|null $name
 * @property string|null $einheit
 * @property string|null $abteilung
 * @property numeric|null $einkaufspreis
 * @property numeric|null $mindestbestand
 * @property numeric|null $bestand
 * @property numeric|null $einstandspreis
 * @property string|null $pg_nummer
 * @property string|null $lieferant_text
 * @property string|null $charge_nr
 * @property Carbon|null $mhd
 * @property int|null $matched_artikel_id
 * @property int|null $matched_lieferant_id
 * @property array<array-key, mixed>|null $kandidaten
 * @property ImportAktion $aktion
 * @property ImportZeileStatus $status
 * @property int|null $ergebnis_artikel_id
 * @property int|null $ergebnis_lieferant_id
 * @property int|null $wareneingang_bewegung_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Artikel|null $artikel
 * @property-read ImportBatch $batch
 * @property-read Lieferant|null $lieferant
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereAbteilung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereAktion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereBestand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereChargeNr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereEinheit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereEinkaufspreis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereEinstandspreis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereErgebnisArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereErgebnisLieferantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereKandidaten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereLieferantText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereMatchedArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereMatchedLieferantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereMhd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereMindestbestand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile wherePgNummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereRoh($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereWareneingangBewegungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportZeile whereZielTyp($value)
 *
 * @mixin \Eloquent
 */
class ImportZeile extends BaseModel
{
    protected $table = 'import_zeilen';

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'roh',
        'ziel_typ',
        'name',
        'einheit',
        'abteilung',
        'einkaufspreis',
        'mindestbestand',
        'bestand',
        'einstandspreis',
        'pg_nummer',
        'lieferant_text',
        'charge_nr',
        'mhd',
        'matched_artikel_id',
        'matched_lieferant_id',
        'kandidaten',
        'aktion',
        'status',
        'ergebnis_artikel_id',
        'ergebnis_lieferant_id',
        'wareneingang_bewegung_id',
    ];

    protected $casts = [
        'roh' => 'array',
        'kandidaten' => 'array',
        'mhd' => 'date',
        'einkaufspreis' => 'decimal:2',
        'mindestbestand' => 'decimal:2',
        'bestand' => 'decimal:2',
        'einstandspreis' => 'decimal:4',
        'aktion' => ImportAktion::class,
        'status' => ImportZeileStatus::class,
    ];

    /** @return BelongsTo<ImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    /** @return BelongsTo<Artikel, $this> */
    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'matched_artikel_id');
    }

    /** @return BelongsTo<Lieferant, $this> */
    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class, 'matched_lieferant_id');
    }

    public function offen(): bool
    {
        return $this->status === ImportZeileStatus::Vorgeschlagen;
    }
}
