<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Unveränderlicher Abgang aus genau einer {@see Lagerschicht}: protokolliert, welche Schicht ein Verbrauch
 * (oder eine Inventur-Schwundbuchung) zu welchem Einstandspreis und in welcher Menge gezehrt hat. Append-only
 * — zugleich die Brücke für den späteren bewohnerbezogenen Verbrauch (§ 40 SGB XI) und die Chargen-Rückverfolgung.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $bewegung_id
 * @property int $schicht_id
 * @property numeric $menge
 * @property numeric $einstandspreis
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $resident_id
 * @property-read Lagerbewegung $bewegung
 * @property-read Resident|null $resident
 * @property-read Lagerschicht $schicht
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereBewegungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereEinstandspreis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereMenge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereSchichtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schichtabgang whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Schichtabgang extends Model
{
    use BelongsToTenant;

    protected $table = 'schichtabgaenge';

    protected $fillable = ['tenant_id', 'resident_id', 'bewegung_id', 'schicht_id', 'menge', 'einstandspreis'];

    protected $casts = [
        'menge' => 'decimal:2',
        'einstandspreis' => 'decimal:4',
    ];

    public function schicht(): BelongsTo
    {
        return $this->belongsTo(Lagerschicht::class, 'schicht_id');
    }

    public function bewegung(): BelongsTo
    {
        return $this->belongsTo(Lagerbewegung::class, 'bewegung_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'resident_id');
    }
}
