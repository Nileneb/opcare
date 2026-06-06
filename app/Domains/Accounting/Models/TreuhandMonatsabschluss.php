<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Monatliche Rechnungslegung des Treuhandkontos (HeimsicherungsV § 15) — Anfangs-/Endbestand und die Summen
 * der Ein-/Auszahlungen als prüffähiger Nachweis für Heimaufsicht und Betreuungsgericht. Nach der Sperre
 * (gesperrt_am) read-only.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $treuhand_konto_id
 * @property Carbon $monat
 * @property numeric $anfangsbestand
 * @property numeric $summe_einzahlungen
 * @property numeric $summe_auszahlungen
 * @property numeric $endbestand
 * @property string $erstellt_von
 * @property Carbon|null $gesperrt_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Treuhandkonto $konto
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class TreuhandMonatsabschluss extends BaseModel
{
    protected $table = 'treuhand_monatsabschluesse';

    protected $fillable = ['tenant_id', 'treuhand_konto_id', 'monat', 'anfangsbestand', 'summe_einzahlungen',
        'summe_auszahlungen', 'endbestand', 'erstellt_von', 'gesperrt_am'];

    protected $casts = ['monat' => 'date', 'anfangsbestand' => 'decimal:2', 'summe_einzahlungen' => 'decimal:2',
        'summe_auszahlungen' => 'decimal:2', 'endbestand' => 'decimal:2', 'gesperrt_am' => 'datetime'];

    public function konto(): BelongsTo
    {
        return $this->belongsTo(Treuhandkonto::class, 'treuhand_konto_id');
    }
}
