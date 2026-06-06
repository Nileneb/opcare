<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Tageszeitliches Bedarfs-Fenster („Spitzenzeit"): Zeitspannen mit erhöhtem Personalbedarf (Frühstück, Mittag,
 * Abendversorgung) als editierbarer Katalog je Einrichtung. Ergänzt den wochenbezogenen § 113c-Betreuungsschlüssel
 * (Strang B) um eine tageszeitabhängige Soll-Vorgabe — operativ ausgewertet gegen die geplanten Schichten.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $beginn
 * @property string $ende
 * @property int $soll_personen
 * @property bool $nur_werktags
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereBeginn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereEnde($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereNurWerktags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereSollPersonen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Spitzenzeit whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Spitzenzeit extends BaseModel
{
    protected $table = 'spitzenzeiten';

    protected $fillable = ['tenant_id', 'name', 'beginn', 'ende', 'soll_personen', 'nur_werktags', 'aktiv'];

    protected $attributes = ['soll_personen' => 1, 'nur_werktags' => false, 'aktiv' => true];

    protected $casts = [
        'soll_personen' => 'integer',
        'nur_werktags' => 'boolean',
        'aktiv' => 'boolean',
    ];

    /** Überlappt eine (ggf. über Mitternacht laufende) Schicht dieses Bedarfs-Fenster? */
    public function wirdGedecktVon(string $shiftBeginn, string $shiftEnde): bool
    {
        return self::ueberlappt($shiftBeginn, $shiftEnde, $this->beginn, $this->ende);
    }

    public static function minuten(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    /**
     * Schnittmengen-Prüfung auf Minutenbasis. Das Fenster [wb,we) liegt tagsüber (kein Mitternachts-Umbruch);
     * die Schicht [sb,se) darf über Mitternacht laufen (se <= sb → zwei Teil-Intervalle [sb,1440) und [0,se)).
     */
    public static function ueberlappt(string $shiftBeginn, string $shiftEnde, string $fensterBeginn, string $fensterEnde): bool
    {
        $sb = self::minuten($shiftBeginn);
        $se = self::minuten($shiftEnde);
        $wb = self::minuten($fensterBeginn);
        $we = self::minuten($fensterEnde);

        if ($se > $sb) {
            return $sb < $we && $wb < $se;
        }

        // Schicht über Mitternacht: aktiv in [sb,1440) ODER [0,se)
        return ($sb < $we) || ($wb < $se);
    }
}
