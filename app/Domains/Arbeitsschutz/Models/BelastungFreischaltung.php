<?php

namespace App\Domains\Arbeitsschutz\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Voting\Models\Abstimmung;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Governance-Datensatz: Beschluss-gestützte Freischaltung der Selbst-Ampel (Mode B/C).
 * Enthält KEINE Personendaten außer dem auslösenden Admin (erlaubt, da selbst-initiiert + Beschluss-Basis).
 *
 * Norm-Anker: § 87 Abs. 1 Nr. 6 BetrVG (Mitbestimmung technische Einrichtungen) —
 * Freischaltung nur nach Mitarbeitenden-Beschluss (§ 87 BetrVG-Analogie).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $abstimmung_id
 * @property int|null $freigeschaltet_von
 * @property Carbon $freigeschaltet_am
 * @property int|null $zurueckgenommen_von
 * @property Carbon|null $zurueckgenommen_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Abstimmung $abstimmung
 * @property-read User|null $freigeschaltetVon
 * @property-read User|null $zurueckgenommenVon
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelastungFreischaltung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelastungFreischaltung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelastungFreischaltung query()
 *
 * @mixin \Eloquent
 */
class BelastungFreischaltung extends BaseModel
{
    protected $table = 'belastung_freischaltungen';

    protected $fillable = [
        'tenant_id',
        'abstimmung_id',
        'freigeschaltet_von',
        'freigeschaltet_am',
        'zurueckgenommen_von',
        'zurueckgenommen_am',
    ];

    protected $casts = [
        'freigeschaltet_am' => 'date',
        'zurueckgenommen_am' => 'date',
    ];

    /** @return BelongsTo<Abstimmung, $this> */
    public function abstimmung(): BelongsTo
    {
        return $this->belongsTo(Abstimmung::class);
    }

    /** @return BelongsTo<User, $this> */
    public function freigeschaltetVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freigeschaltet_von');
    }

    /** @return BelongsTo<User, $this> */
    public function zurueckgenommenVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'zurueckgenommen_von');
    }

    public function istAktiv(): bool
    {
        return $this->zurueckgenommen_am === null;
    }

    public static function aktivFuer(int $tenantId): bool
    {
        return self::where('tenant_id', $tenantId)
            ->whereNull('zurueckgenommen_am')
            ->exists();
    }
}
