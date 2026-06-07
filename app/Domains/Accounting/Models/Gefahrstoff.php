<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Gefahrstoff-Stammdaten zu einem Artikel (§ 6 Abs. 12 GefStoffV, TRGS 510/555).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $artikel_id
 * @property string|null $signalwort
 * @property array<array-key, mixed>|null $h_saetze
 * @property array<array-key, mixed>|null $p_saetze
 * @property array<array-key, mixed>|null $ghs_piktogramme
 * @property string|null $mengenbereich
 * @property string|null $arbeitsbereiche
 * @property string|null $lagerort
 * @property string|null $betriebsanweisung
 * @property string|null $schutzmassnahmen
 * @property string|null $stoerfall_massnahmen
 * @property string|null $erste_hilfe
 * @property string|null $entsorgung
 * @property int $unterweisung_intervall_monate
 * @property Carbon|null $sdb_version_datum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Artikel $artikel
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereArbeitsbereiche($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereBetriebsanweisung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereEntsorgung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereErsteHilfe($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereGhsPiktogramme($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereHSaetze($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereLagerort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereMengenbereich($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff wherePSaetze($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereSdbVersionDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereSchutzmassnahmen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereSignalwort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereStoerfallMassnahmen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereUnterweisungIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gefahrstoff whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Gefahrstoff extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'gefahrstoffe';

    protected $fillable = [
        'tenant_id',
        'artikel_id',
        'signalwort',
        'h_saetze',
        'p_saetze',
        'ghs_piktogramme',
        'mengenbereich',
        'arbeitsbereiche',
        'lagerort',
        'betriebsanweisung',
        'schutzmassnahmen',
        'stoerfall_massnahmen',
        'erste_hilfe',
        'entsorgung',
        'unterweisung_intervall_monate',
        'sdb_version_datum',
    ];

    protected $casts = [
        'h_saetze' => 'array',
        'p_saetze' => 'array',
        'ghs_piktogramme' => 'array',
        'sdb_version_datum' => 'date',
    ];

    /** @return BelongsTo<Artikel, $this> */
    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class);
    }

    public function registerMediaCollections(): void
    {
        // WHY(§ 6 Abs. 12 Nr. 5 GefStoffV + Art. 31 REACH): SDB-PDF als Nachweis je Gefahrstoff archivieren.
        $this->addMediaCollection('sdb')->useDisk(config('opcare.media_disk', 'media'));
    }
}
