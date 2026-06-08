<?php

namespace App\Domains\Catering\Models;

use App\Domains\Catering\Enums\Lenkungsart;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Lenkungsmaßnahme (control measure) zur Beherrschung einer Lebensmittelgefahr.
 * Norm-Anker: Codex Alimentarius (HACCP-Prinzipien 2/3/5), VO (EG) 852/2004 Art. 5;
 * Verifizierung (verifiziert_am) = Prinzip 6 (Wirksamkeitsnachweis).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $lebensmittel_gefahr_id
 * @property Lenkungsart $art
 * @property string $beschreibung
 * @property string|null $verantwortlich
 * @property Carbon|null $frist
 * @property Carbon|null $umgesetzt_am
 * @property Carbon|null $verifiziert_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read LebensmittelGefahr $gefahr
 *
 * @mixin \Eloquent
 */
class Lenkungsmassnahme extends BaseModel
{
    protected $table = 'lenkungsmassnahmen';

    protected $fillable = [
        'tenant_id', 'lebensmittel_gefahr_id', 'art', 'beschreibung',
        'verantwortlich', 'frist', 'umgesetzt_am', 'verifiziert_am',
    ];

    protected $casts = [
        'art' => Lenkungsart::class,
        'frist' => 'date',
        'umgesetzt_am' => 'date',
        'verifiziert_am' => 'date',
    ];

    /** @return BelongsTo<LebensmittelGefahr, $this> */
    public function gefahr(): BelongsTo
    {
        return $this->belongsTo(LebensmittelGefahr::class, 'lebensmittel_gefahr_id');
    }

    public function istOffen(): bool
    {
        return $this->umgesetzt_am === null;
    }

    /** HACCP-Prinzip 6: Lenkungsmaßnahme auf Wirksamkeit verifiziert. */
    public function istVerifiziert(): bool
    {
        return $this->verifiziert_am !== null;
    }
}
