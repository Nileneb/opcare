<?php

namespace App\Domains\Capture\Models;

use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * VLM-Analyse eines Lieferscheins. Das Originalbild liegt als Media (Collection „lieferschein",
 * Disk via opcare.media_disk → MinIO-fähig). `roh_json` hält die unveränderte Modell-Ausgabe
 * zur Nachvollziehbarkeit; die Analyse ist nie autoritativ — erst bestätigte Positionen schreiben
 * Lagerbewegungen.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $lieferant_text
 * @property int|null $lieferant_id
 * @property Carbon|null $datum
 * @property string|null $lieferschein_nr
 * @property array<array-key, mixed>|null $roh_json
 * @property string|null $modell
 * @property numeric|null $konfidenz
 * @property int|null $erstellt_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $ersteller
 * @property-read Lieferant|null $lieferant
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, LieferscheinPositionVorschlag> $positionen
 * @property-read int|null $positionen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereErstelltVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereKonfidenz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereLieferantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereLieferantText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereLieferscheinNr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereModell($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereRohJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferscheinAnalyse whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LieferscheinAnalyse extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'lieferschein_analysen';

    protected $fillable = [
        'tenant_id',
        'lieferant_text',
        'lieferant_id',
        'datum',
        'lieferschein_nr',
        'roh_json',
        'modell',
        'konfidenz',
        'erstellt_von',
    ];

    protected $casts = [
        'datum' => 'date',
        'roh_json' => 'array',
        'konfidenz' => 'decimal:3',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('lieferschein')->useDisk(config('opcare.media_disk', 'media'));
    }

    /** @return HasMany<LieferscheinPositionVorschlag, $this> */
    public function positionen(): HasMany
    {
        return $this->hasMany(LieferscheinPositionVorschlag::class, 'analyse_id');
    }

    /** @return BelongsTo<Lieferant, $this> */
    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }
}
