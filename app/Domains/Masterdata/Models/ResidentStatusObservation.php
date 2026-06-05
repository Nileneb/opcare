<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Masterdata\Support\StatusObservationCatalog;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentStatusObservation extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'typ', 'wert_code', 'wert_text', 'erfasst_am'];

    protected $casts = ['erfasst_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** Menschliche Anzeige: Katalog-Label des Typs + Wert (codiert → Options-Label, sonst Freitext). */
    public function anzeige(): string
    {
        $def = StatusObservationCatalog::get($this->typ);
        $wert = $this->wert_code ? ($def['options'][$this->wert_code] ?? $this->wert_code) : (string) $this->wert_text;

        return ($def['label'] ?? $this->typ).': '.$wert;
    }
}
