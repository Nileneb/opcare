<?php

namespace App\Domains\Communication\Models;

use App\Domains\Communication\Enums\KonversationTyp;
use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Station;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property KonversationTyp $typ
 * @property string|null $titel
 * @property int|null $station_id
 * @property int|null $erstellt_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, KonversationTeilnehmer> $teilnehmer
 * @property-read int|null $teilnehmer_count
 * @property-read Collection<int, Nachricht> $nachrichten
 * @property-read int|null $nachrichten_count
 * @property-read Station|null $station
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Konversation query()
 *
 * @mixin \Eloquent
 */
class Konversation extends Model
{
    use BelongsToTenant;

    protected $table = 'konversationen';

    protected $fillable = [
        'tenant_id',
        'typ',
        'titel',
        'station_id',
        'erstellt_von',
    ];

    protected function casts(): array
    {
        return [
            'typ' => KonversationTyp::class,
        ];
    }

    /** @return HasMany<KonversationTeilnehmer, $this> */
    public function teilnehmer(): HasMany
    {
        return $this->hasMany(KonversationTeilnehmer::class);
    }

    /** @return HasMany<Nachricht, $this> */
    public function nachrichten(): HasMany
    {
        return $this->hasMany(Nachricht::class)->orderBy('created_at');
    }

    /** @return BelongsTo<Station, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function istMitglied(int $userId): bool
    {
        return $this->teilnehmer()->where('user_id', $userId)->exists();
    }

    public function letzteNachricht(): ?Nachricht
    {
        // WHY: nachrichten() trägt orderBy('created_at') ASC — reorder() verwirft das, sonst gewinnt
        // die ASC-Klausel und ->latest() liefert die ÄLTESTE statt der neuesten Nachricht.
        return $this->nachrichten()
            ->whereNull('geloescht_am')
            ->reorder('created_at', 'desc')
            ->first();
    }

    public function darfSchreiben(User $u): bool
    {
        if ($this->typ === KonversationTyp::Ankuendigung) {
            return $u->hasAnyRole(['admin', 'super-admin']);
        }

        $teilnehmer = $this->teilnehmer()->where('user_id', $u->id)->first();

        return $teilnehmer !== null && $teilnehmer->darf_schreiben;
    }
}
