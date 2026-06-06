<?php

namespace App\Domains\Capture\Models;

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
 * Eine VLM-Analyse eines Belegfotos. Das Originalbild liegt als Media (Collection „beleg", Disk via
 * opcare.media_disk → MinIO-fähig) — Audit-Spur. `roh_json` hält die unveränderte Modell-Ausgabe zur
 * Nachvollziehbarkeit; die Analyse ist nie autoritativ, erst der bestätigte Vorschlag schreibt einen Zieldatensatz.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $modell
 * @property float|null $konfidenz
 * @property array<array-key, mixed>|null $roh_json
 * @property int|null $erstellt_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $erfasser
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Tenant $tenant
 * @property-read Collection<int, EinsortierungsVorschlag> $vorschlaege
 * @property-read int|null $vorschlaege_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse whereErstelltVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse whereKonfidenz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse whereModell($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse whereRohJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelegAnalyse whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BelegAnalyse extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'beleg_analysen';

    protected $fillable = ['tenant_id', 'modell', 'konfidenz', 'roh_json', 'erstellt_von'];

    protected $casts = [
        'roh_json' => 'array',
        'konfidenz' => 'float',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('beleg')->useDisk(config('opcare.media_disk', 'media'))->singleFile();
    }

    public function vorschlaege(): HasMany
    {
        return $this->hasMany(EinsortierungsVorschlag::class);
    }

    public function erfasser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }
}
