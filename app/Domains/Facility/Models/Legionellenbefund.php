<?php

namespace App\Domains\Facility\Models;

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
 * Legionellen-Untersuchungsbefund je Anlage und Probenahmestelle.
 * MASSNAHMENWERT = 100 KbE/100 ml (Anlage 3 Teil II TrinkwV 2023).
 * Bei ueberschreitung=true: Maßnahmen-/Meldepflicht nach § 51 TrinkwV 2023.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $trinkwasseranlage_id
 * @property int|null $probenahmestelle_id
 * @property Carbon $untersucht_am
 * @property string|null $labor
 * @property int $kbe_pro_100ml
 * @property bool $ueberschreitung
 * @property string|null $massnahme
 * @property Carbon|null $gesundheitsamt_gemeldet_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Trinkwasseranlage $anlage
 * @property-read Probenahmestelle|null $probenahmestelle
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class Legionellenbefund extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    /** Technischer Maßnahmenwert nach Anlage 3 Teil II TrinkwV 2023. */
    public const MASSNAHMENWERT = 100;

    protected $table = 'legionellenbefunde';

    protected $fillable = [
        'tenant_id', 'trinkwasseranlage_id', 'probenahmestelle_id',
        'untersucht_am', 'labor', 'kbe_pro_100ml', 'ueberschreitung',
        'massnahme', 'gesundheitsamt_gemeldet_am',
    ];

    protected $casts = [
        'untersucht_am' => 'date',
        'kbe_pro_100ml' => 'integer',
        'ueberschreitung' => 'boolean',
        'gesundheitsamt_gemeldet_am' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        // WHY(§ 15 Abs. 3 TrinkwV 2023): Laborbefund-PDF/Foto als akkreditierter Nachweis je Befund archivieren.
        $this->addMediaCollection('laborbefund')
            ->useDisk(config('opcare.media_disk', 'media'))
            ->singleFile();
    }

    /** @return BelongsTo<Trinkwasseranlage, $this> */
    public function anlage(): BelongsTo
    {
        return $this->belongsTo(Trinkwasseranlage::class, 'trinkwasseranlage_id');
    }

    /** @return BelongsTo<Probenahmestelle, $this> */
    public function probenahmestelle(): BelongsTo
    {
        return $this->belongsTo(Probenahmestelle::class, 'probenahmestelle_id');
    }
}
