<?php

namespace App\Domains\Communication\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $konversation_id
 * @property int $user_id
 * @property Carbon|null $zuletzt_gelesen_am
 * @property bool $darf_schreiben
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Konversation $konversation
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KonversationTeilnehmer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KonversationTeilnehmer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KonversationTeilnehmer query()
 *
 * @mixin \Eloquent
 */
class KonversationTeilnehmer extends Model
{
    use BelongsToTenant;

    protected $table = 'konversation_teilnehmer';

    protected $fillable = [
        'tenant_id',
        'konversation_id',
        'user_id',
        'zuletzt_gelesen_am',
        'darf_schreiben',
    ];

    protected function casts(): array
    {
        return [
            'zuletzt_gelesen_am' => 'datetime',
            'darf_schreiben' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Konversation, $this> */
    public function konversation(): BelongsTo
    {
        return $this->belongsTo(Konversation::class);
    }
}
