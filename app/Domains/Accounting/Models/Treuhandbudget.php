<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Contracts\BudgetGrenze;
use App\Domains\Accounting\Enums\BarbetragKategorie;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Budget-Setzung je Treuhandkonto: ein monatliches Limit je Kategorie (oder Gesamt, kategorie=null) mit
 * Warn-Schwelle und optionaler harter Sperre. Verhindert/markiert Überschreitungen der treuhänderischen
 * Auszahlungen — generisches Muster, das sich auf andere Budget-Töpfe übertragen lässt.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $treuhand_konto_id
 * @property BarbetragKategorie|null $kategorie
 * @property numeric $limit_betrag
 * @property int $warn_prozent
 * @property bool $sperre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Treuhandkonto $konto
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class Treuhandbudget extends BaseModel implements BudgetGrenze
{
    protected $table = 'treuhand_budgets';

    protected $fillable = ['tenant_id', 'treuhand_konto_id', 'kategorie', 'limit_betrag', 'warn_prozent', 'sperre'];

    protected $casts = ['kategorie' => BarbetragKategorie::class, 'limit_betrag' => 'decimal:2', 'warn_prozent' => 'integer', 'sperre' => 'boolean'];

    public function limitBetrag(): float
    {
        return (float) $this->limit_betrag;
    }

    public function warnProzent(): int
    {
        return $this->warn_prozent;
    }

    public function sperreAktiv(): bool
    {
        return $this->sperre;
    }

    public function konto(): BelongsTo
    {
        return $this->belongsTo(Treuhandkonto::class, 'treuhand_konto_id');
    }
}
