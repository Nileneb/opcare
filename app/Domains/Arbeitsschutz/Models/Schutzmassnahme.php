<?php

namespace App\Domains\Arbeitsschutz\Models;

use App\Domains\Arbeitsschutz\Enums\Massnahmentyp;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Schutzmaßnahme zu einer Gefährdung (TOP-Hierarchie nach § 4 ArbSchG).
 * Norm-Anker: § 4 ArbSchG (Maßnahmen-Grundsätze), § 3 Abs. 1 ArbSchG (Wirksamkeitskontrolle).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $gefaehrdung_id
 * @property Massnahmentyp $typ
 * @property string $beschreibung
 * @property string|null $verantwortlich
 * @property Carbon|null $frist
 * @property Carbon|null $umgesetzt_am
 * @property Carbon|null $wirksam_geprueft_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Gefaehrdung $gefaehrdung
 *
 * @mixin \Eloquent
 */
class Schutzmassnahme extends BaseModel
{
    protected $table = 'schutzmassnahmen';

    protected $fillable = [
        'tenant_id', 'gefaehrdung_id', 'typ', 'beschreibung',
        'verantwortlich', 'frist', 'umgesetzt_am', 'wirksam_geprueft_am',
    ];

    protected $casts = [
        'typ' => Massnahmentyp::class,
        'frist' => 'date',
        'umgesetzt_am' => 'date',
        'wirksam_geprueft_am' => 'date',
    ];

    /** @return BelongsTo<Gefaehrdung, $this> */
    public function gefaehrdung(): BelongsTo
    {
        return $this->belongsTo(Gefaehrdung::class);
    }

    public function istOffen(): bool
    {
        return $this->umgesetzt_am === null;
    }

    /** § 3 Abs. 1 ArbSchG: Maßnahme auf Wirksamkeit geprüft. */
    public function istWirksamGeprueft(): bool
    {
        return $this->wirksam_geprueft_am !== null;
    }
}
