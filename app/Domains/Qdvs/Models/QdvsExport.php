<?php

namespace App\Domains\Qdvs\Models;

use App\Support\Models\BaseModel;

class QdvsExport extends BaseModel
{
    protected $fillable = ['tenant_id', 'stichtag', 'spec', 'status', 'bewohner_count', 'pfad', 'fehler', 'regel_coverage', 'erstellt_von'];

    protected $casts = ['stichtag' => 'date', 'fehler' => 'array', 'regel_coverage' => 'array', 'bewohner_count' => 'integer'];
}
