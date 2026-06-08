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
 * @property string $inhalt
 * @property Carbon|null $geloescht_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $absender
 * @property-read Konversation $konversation
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Nachricht newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Nachricht newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Nachricht query()
 *
 * @mixin \Eloquent
 */
class Nachricht extends Model
{
    use BelongsToTenant;

    protected $table = 'nachrichten';

    protected $fillable = [
        'tenant_id',
        'konversation_id',
        'user_id',
        'inhalt',
        'geloescht_am',
    ];

    protected function casts(): array
    {
        return [
            'geloescht_am' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function absender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Konversation, $this> */
    public function konversation(): BelongsTo
    {
        return $this->belongsTo(Konversation::class);
    }

    public function istZurueckgezogen(): bool
    {
        return $this->geloescht_am !== null;
    }
}
