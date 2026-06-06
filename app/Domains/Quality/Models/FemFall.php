<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\FemArt;
use App\Domains\Quality\Enums\FemEinwilligung;
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
 * Ein FEM-Fall (§ 1831 BGB): Genehmigungs-/Fristen-Workflow mit Ampel an der Befristung. Ärztliches Attest
 * und Gerichtsbeschluss werden als Dokumente angehängt (spatie media). Status leitet sich aus Einwilligung,
 * Befristung (gueltig_bis) und Beendigung ab.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property FemArt $art
 * @property string|null $detail
 * @property string $anlass
 * @property array<array-key, mixed>|null $mildere_mittel
 * @property string|null $mildere_begruendung
 * @property int|null $anordnung_pflegekraft
 * @property string|null $anordnung_arzt
 * @property Carbon|null $anordnung_am
 * @property FemEinwilligung $einwilligungsstatus
 * @property Carbon|null $antrag_am
 * @property string|null $aktenzeichen
 * @property string|null $gericht
 * @property Carbon|null $beschluss_am
 * @property Carbon|null $gueltig_bis
 * @property string $ueberpruefung_intervall
 * @property Carbon|null $beendet_am
 * @property string|null $beendigungsgrund
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, FemProtokoll> $protokolle
 * @property-read int|null $protokolle_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereAktenzeichen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereAnlass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereAnordnungAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereAnordnungArzt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereAnordnungPflegekraft($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereAntragAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereArt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereBeendetAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereBeendigungsgrund($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereBeschlussAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereDetail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereEinwilligungsstatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereGericht($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereGueltigBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereMildereBegruendung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereMildereMittel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereUeberpruefungIntervall($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemFall whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class FemFall extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'fem_faelle';

    protected $fillable = ['tenant_id', 'resident_id', 'art', 'detail', 'anlass', 'mildere_mittel', 'mildere_begruendung',
        'anordnung_pflegekraft', 'anordnung_arzt', 'anordnung_am', 'einwilligungsstatus', 'antrag_am', 'aktenzeichen',
        'gericht', 'beschluss_am', 'gueltig_bis', 'ueberpruefung_intervall', 'beendet_am', 'beendigungsgrund'];

    protected $casts = [
        'art' => FemArt::class,
        'einwilligungsstatus' => FemEinwilligung::class,
        'mildere_mittel' => 'array',
        'anordnung_am' => 'datetime',
        'antrag_am' => 'date',
        'beschluss_am' => 'date',
        'gueltig_bis' => 'date',
        'beendet_am' => 'datetime',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return HasMany<FemProtokoll, $this> */
    public function protokolle(): HasMany
    {
        return $this->hasMany(FemProtokoll::class)->orderByDesc('zeitpunkt');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('fem_dokumente')->useDisk(config('opcare.media_disk', 'media'));
    }

    /** beendet | einwilligung | ohne_genehmigung | notfall | beantragt | genehmigt | ueberpruefung_faellig | abgelaufen */
    public function status(): string
    {
        if ($this->beendet_am !== null) {
            return 'beendet';
        }

        return match ($this->einwilligungsstatus) {
            FemEinwilligung::BewohnerEingewilligt => 'einwilligung',
            FemEinwilligung::OhneGenehmigung => 'ohne_genehmigung',
            FemEinwilligung::NotfallNachzuholen => 'notfall',
            FemEinwilligung::GenehmigungBeantragt => 'beantragt',
            FemEinwilligung::GenehmigungErteilt => $this->genehmigungsStatus(),
        };
    }

    private function genehmigungsStatus(): string
    {
        if ($this->gueltig_bis === null) {
            return 'genehmigt';
        }
        if ($this->gueltig_bis->isPast()) {
            return 'abgelaufen';
        }

        return $this->gueltig_bis->lessThanOrEqualTo(today()->addDays(30)) ? 'ueberpruefung_faellig' : 'genehmigt';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'genehmigt', 'einwilligung' => 'green',
            'beantragt', 'ueberpruefung_faellig' => 'amber',
            'abgelaufen', 'ohne_genehmigung', 'notfall' => 'red',
            default => 'gray', // beendet
        };
    }

    public function aktiv(): bool
    {
        return $this->beendet_am === null;
    }
}
