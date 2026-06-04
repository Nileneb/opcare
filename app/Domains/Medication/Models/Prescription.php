<?php

namespace App\Domains\Medication\Models;

use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'med_product_id', 'bhp_text', 'physician_id', 'situation_id',
        'bei_bedarf', 'gueltig_von', 'gueltig_bis', 'abgesetzt_am', 'abgesetzt_von', 'created_by', 'hinweis',
    ];

    protected $casts = [
        'bei_bedarf' => 'boolean',
        'gueltig_von' => 'date',
        'gueltig_bis' => 'date',
        'abgesetzt_am' => 'date',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function medProduct(): BelongsTo
    {
        return $this->belongsTo(MedProduct::class);
    }

    public function physician(): BelongsTo
    {
        return $this->belongsTo(Physician::class);
    }

    public function situation(): BelongsTo
    {
        return $this->belongsTo(Situation::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PrescriptionSchedule::class);
    }

    public function getIstAktivAttribute(): bool
    {
        return $this->abgesetzt_am === null
            && ($this->gueltig_bis === null || $this->gueltig_bis->isFuture() || $this->gueltig_bis->isToday());
    }

    public function scopeAktiv($q)
    {
        return $q->whereNull('abgesetzt_am');
    }
}
