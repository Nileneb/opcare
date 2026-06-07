<?php

namespace App\Domains\Capture\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lerngedächtnis: Welcher Positions-Text wurde für welchen Artikel beim welchen Lieferanten bestätigt.
 *
 * Reine Log-/Lern-Tabelle — kein ActivityLog, nur Tenant-Scope.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $lieferant_id
 * @property string $norm_text
 * @property int $artikel_id
 * @property int $treffer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias whereArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias whereLieferantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias whereNormText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias whereTreffer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LieferantArtikelAlias whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LieferantArtikelAlias extends Model
{
    use BelongsToTenant;

    protected $table = 'lieferant_artikel_aliasse';

    protected $fillable = [
        'tenant_id',
        'lieferant_id',
        'norm_text',
        'artikel_id',
        'treffer',
    ];
}
